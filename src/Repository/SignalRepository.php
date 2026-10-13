<?php

namespace App\Repository;

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
}
