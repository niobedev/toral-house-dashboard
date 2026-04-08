<?php

namespace App\Repository;

use App\Entity\SyncState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SyncState>
 */
class SyncStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncState::class);
    }

    public function getOrCreate(): SyncState
    {
        $state = $this->findOneBy([]);
        if (!$state) {
            $state = new SyncState();
            $this->getEntityManager()->persist($state);
            $this->getEntityManager()->flush();
        }
        return $state;
    }
}
