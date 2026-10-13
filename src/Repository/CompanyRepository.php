<?php

namespace App\Repository;

use App\Api\CompanyListQuery;
use App\Entity\Company;
use App\Entity\Interaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Company> */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /** @return array{items: list<Company>, total: int} */
    public function search(CompanyListQuery $query, \DateTimeImmutable $now): array
    {
        $qb = $this->filtered($query, $now);

        $total = (int) (clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        $direction = 'desc' === $query->direction ? 'DESC' : 'ASC';
        if ('lastInteractionAt' === $query->sort) {
            $qb->addSelect(sprintf('(SELECT MAX(si.occurredAt) FROM %s si WHERE si.company = c) AS HIDDEN lastInteractionSort', Interaction::class))
                ->addSelect(sprintf('CASE WHEN EXISTS (SELECT sj.id FROM %s sj WHERE sj.company = c) THEN 0 ELSE 1 END AS HIDDEN emptyRank', Interaction::class))
                ->orderBy('emptyRank', 'ASC')
                ->addOrderBy('lastInteractionSort', $direction);
        } elseif ('recontactAfter' === $query->sort) {
            $qb->addSelect('CASE WHEN c.recontactAfter IS NULL THEN 1 ELSE 0 END AS HIDDEN emptyRank')
                ->orderBy('emptyRank', 'ASC')
                ->addOrderBy('c.recontactAfter', $direction);
        } else {
            $qb->orderBy('c.'.$query->sort, $direction);
        }

        /** @var list<Company> $items */
        $items = $qb->addOrderBy('c.id', 'ASC')
            ->setFirstResult(($query->page - 1) * $query->perPage)
            ->setMaxResults($query->perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    private function filtered(CompanyListQuery $query, \DateTimeImmutable $now): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere($query->archived ? 'c.archivedAt IS NOT NULL' : 'c.archivedAt IS NULL');

        if (null !== $query->q && '' !== trim($query->q)) {
            $qb->andWhere('UNACCENT(LOWER(c.name)) LIKE UNACCENT(:q) OR UNACCENT(LOWER(c.city)) LIKE UNACCENT(:q) OR c.siren LIKE :q')
                ->setParameter('q', '%'.addcslashes(mb_strtolower(trim($query->q)), '%_\\').'%');
        }

        if (null !== $query->status) {
            $qb->andWhere('c.status = :status')->setParameter('status', $query->status);
        }

        if (null !== $query->scope) {
            $qb->andWhere('c.scope = :scope')->setParameter('scope', $query->scope);
        }

        if ($query->due) {
            $qb->andWhere(sprintf(
                'c.recontactAfter <= :now OR EXISTS (SELECT ni.id FROM %1$s ni WHERE ni.company = c AND ni.nextActionAt <= :now AND ni.occurredAt = (SELECT MAX(li.occurredAt) FROM %1$s li WHERE li.company = c))',
                Interaction::class,
            ))->setParameter('now', $now);
        }

        return $qb;
    }

    /**
     * @param list<Company> $companies
     *
     * @return array<int, \DateTimeImmutable> date de la dernière interaction par identifiant d'entreprise
     */
    public function lastInteractionDates(array $companies): array
    {
        if ([] === $companies) {
            return [];
        }

        /** @var list<array{cid: int|string, last: string}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(i.company) AS cid', 'MAX(i.occurredAt) AS last')
            ->from(Interaction::class, 'i')
            ->where('i.company IN (:companies)')
            ->setParameter('companies', $companies)
            ->groupBy('i.company')
            ->getQuery()
            ->getArrayResult();

        $dates = [];
        foreach ($rows as $row) {
            $dates[(int) $row['cid']] = new \DateTimeImmutable($row['last']);
        }

        return $dates;
    }
}
