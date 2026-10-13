<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Interaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Interaction> */
class InteractionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Interaction::class);
    }

    /** @return list<Interaction> */
    public function findByCompany(Company $company): array
    {
        /** @var list<Interaction> $items */
        $items = $this->createQueryBuilder('i')
            ->where('i.company = :company')
            ->setParameter('company', $company)
            ->orderBy('i.occurredAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $items;
    }

    /** @return list<Interaction> */
    public function findByContact(Contact $contact): array
    {
        /** @var list<Interaction> $items */
        $items = $this->findBy(['contact' => $contact]);

        return $items;
    }
}
