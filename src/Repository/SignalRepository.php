<?php

namespace App\Repository;

use App\Api\SignalListQuery;
use App\Entity\Company;
use App\Entity\Signal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Signal> */
class SignalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Signal::class);
    }

    /** @return list<Signal> */
    public function findByCompany(Company $company): array
    {
        /** @var list<Signal> $items */
        $items = $this->createQueryBuilder('s')
            ->where('s.company = :company')
            ->setParameter('company', $company)
            ->orderBy('s.detectedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $items;
    }

    /** @return array{items: list<Signal>, total: int} */
    public function search(SignalListQuery $query): array
    {
        $qb = $this->createQueryBuilder('s');
        if ($query->unresolved) {
            $qb->andWhere('s.company IS NULL');
        }

        $total = (int) (clone $qb)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();

        /** @var list<Signal> $items */
        $items = $qb->leftJoin('s.company', 'co')->addSelect('co')
            ->orderBy('s.detectedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setFirstResult(($query->page - 1) * $query->perPage)
            ->setMaxResults($query->perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }
}
