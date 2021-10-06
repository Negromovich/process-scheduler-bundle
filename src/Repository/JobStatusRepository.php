<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Negromovich\ProcessSchedulerBundle\Entity\JobStatus;

class JobStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobStatus::class);
    }

    public function setStatus(string $queue, string $status): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $table = $this->getClassMetadata()->getTableName();
        $conn->transactional(function() use ($conn, $table, $queue, $status) {
            $exists = (bool)$this->findStatus($queue);
            if ($exists) {
                $qbUpdate = $conn->createQueryBuilder()->update($table);
                $qbUpdate->set('status', ':status')->setParameter('status', $status);
                $qbUpdate->where('queue = :queue')->setParameter('queue', $queue);
                $qbUpdate->execute();
            } else {
                $qbInsert = $conn->createQueryBuilder()->insert($table);
                $qbInsert->setValue('queue', ':queue')->setParameter('queue', $queue);
                $qbInsert->setValue('status', ':status')->setParameter('status', $status);
                $qbInsert->execute();
            }
        });
    }

    public function getStatus(string $queue): string
    {
        return $this->findStatus($queue) ?? JobStatus::INACTIVE;
    }

    private function findStatus(string $queue): ?string
    {
        $conn = $this->getEntityManager()->getConnection();
        $table = $this->getClassMetadata()->getTableName();
        $qbSelect = $conn->createQueryBuilder()->select('status')->from($table);
        $qbSelect->where('queue = :queue')->setParameter('queue', $queue);
        return $qbSelect->execute()->fetchOne() ?: null;
    }
}
