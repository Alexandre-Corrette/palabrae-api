<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Investigation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Investigation>
 */
class InvestigationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Investigation::class);
    }

    public function findOneByReference(string $reference): ?Investigation
    {
        return $this->findOneBy(['reference' => $reference]);
    }
}
