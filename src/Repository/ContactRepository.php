<?php

namespace App\Repository;

use App\Api\ContactListQuery;
use App\Entity\Contact;
use App\Entity\Interaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Contact> */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * Contacts des entreprises non archivées.
     *
     * @return array{items: list<Contact>, total: int}
     */
    public function search(ContactListQuery $query): array
    {
        $qb = $this->createQueryBuilder('ct')
            ->innerJoin('ct.company', 'co')
            ->where('co.archivedAt IS NULL');

        if (null !== $query->q && '' !== trim($query->q)) {
            $qb->andWhere('UNACCENT(LOWER(ct.fullName)) LIKE UNACCENT(:q) OR UNACCENT(LOWER(ct.role)) LIKE UNACCENT(:q) OR LOWER(ct.email) LIKE :q OR UNACCENT(LOWER(co.name)) LIKE UNACCENT(:q)')
                ->setParameter('q', '%'.addcslashes(mb_strtolower(trim($query->q)), '%_\\').'%');
        }

        $total = (int) (clone $qb)->select('COUNT(ct.id)')->getQuery()->getSingleScalarResult();

        $direction = 'desc' === $query->direction ? 'DESC' : 'ASC';
        $qb->addSelect('co');
        match ($query->sort) {
            'company' => $qb->orderBy('co.name', $direction),
            'createdAt' => $qb->orderBy('ct.createdAt', $direction),
            'lastInteractionAt' => $qb
                ->addSelect(sprintf('(SELECT MAX(si.occurredAt) FROM %s si WHERE si.contact = ct) AS HIDDEN lastInteractionSort', Interaction::class))
                ->addSelect(sprintf('CASE WHEN EXISTS (SELECT sj.id FROM %s sj WHERE sj.contact = ct) THEN 0 ELSE 1 END AS HIDDEN emptyRank', Interaction::class))
                ->orderBy('emptyRank', 'ASC')
                ->addOrderBy('lastInteractionSort', $direction),
            default => $qb->orderBy('ct.fullName', $direction),
        };

        /** @var list<Contact> $items */
        $items = $qb->addOrderBy('ct.id', 'ASC')
            ->setFirstResult(($query->page - 1) * $query->perPage)
            ->setMaxResults($query->perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param list<Contact> $contacts
     *
     * @return array<int, \DateTimeImmutable> date de la dernière interaction par identifiant de contact
     */
    public function lastInteractionDates(array $contacts): array
    {
        if ([] === $contacts) {
            return [];
        }

        /** @var list<array{cid: int|string, last: string}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(i.contact) AS cid', 'MAX(i.occurredAt) AS last')
            ->from(Interaction::class, 'i')
            ->where('i.contact IN (:contacts)')
            ->setParameter('contacts', $contacts)
            ->groupBy('i.contact')
            ->getQuery()
            ->getArrayResult();

        $dates = [];
        foreach ($rows as $row) {
            $dates[(int) $row['cid']] = new \DateTimeImmutable($row['last']);
        }

        return $dates;
    }
}
