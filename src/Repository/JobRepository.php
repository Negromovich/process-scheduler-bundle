<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Negromovich\ProcessSchedulerBundle\Entity\Job;
use Negromovich\ProcessSchedulerBundle\Entity\JobState;
use Negromovich\ProcessSchedulerBundle\Repository\JobRepository\FailedPercent;

class JobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Job::class);
    }

    public function findNextJob(string $queue = 'default'): ?Job
    {
        $qb = $this->createQueryBuilder('j');
        $qb->andWhere($qb->expr()->eq('j.queue', ':queue'))
            ->setParameter('queue', $queue);
        $qb->andWhere($qb->expr()->eq('j.state', ':state'))
            ->setParameter('state', JobState::PENDING);
        $qb->andWhere($qb->expr()->lte('j.executeAfter', ':executeAfter'))
            ->setParameter('executeAfter', new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $qb->addOrderBy('j.priority', 'DESC');
        $qb->addOrderBy('j.executeAfter', 'ASC');
        $qb->addOrderBy('j.id', 'ASC');
        $qb->setMaxResults(1);
        return $qb->getQuery()->getOneOrNullResult();
    }

    public function removeClosedBefore(\DateTimeImmutable $time, ?string $queue = null): int
    {
        $qb = $this->createQueryBuilder('j')->delete();
        if ($queue !== null) {
            $qb->andWhere($qb->expr()->eq('j.queue', ':queue'))
                ->setParameter('queue', $queue);
        }
        $qb->andWhere($qb->expr()->neq('j.state', ':state'))
            ->setParameter('state', JobState::PENDING);
        $qb->andWhere($qb->expr()->lt('j.startedAt', ':time'))
            ->setParameter('time', $time);
        return $qb->getQuery()->execute();
    }

    public function failedRunningBefore(\DateTimeImmutable $time, ?string $queue = null): int
    {
        $qb = $this->createQueryBuilder('j')->update();
        $qb->set('j.state', ':newState')->setParameter('newState', JobState::FAILED);
        $qb->set('j.errorOutput', $qb->expr()->concat('j.errorOutput', ':errorOutput'))
            ->setParameter('errorOutput', "\n\nToo long \"running\" job");
        if ($queue !== null) {
            $qb->andWhere($qb->expr()->eq('j.queue', ':queue'))
                ->setParameter('queue', $queue);
        }
        $qb->andWhere($qb->expr()->eq('j.state', ':state'))
            ->setParameter('state', JobState::RUNNING);
        $qb->andWhere($qb->expr()->lt('j.startedAt', ':time'))
            ->setParameter('time', $time);
        return $qb->getQuery()->execute();
    }

    public function findNumberPendingByCommandName(string $commandName, string $queue = 'default'): int
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select('COUNT(j.id)');
        $qb->andWhere($qb->expr()->eq('j.queue', ':queue'))
            ->setParameter('queue', $queue);
        $qb->andWhere($qb->expr()->eq('j.state', ':state'))
            ->setParameter('state', JobState::PENDING);
        $qb->andWhere('JSON_UNQUOTE(JSON_EXTRACT(j.command, \'$[1]\')) = :commandName')
            ->setParameter('commandName', $commandName);
        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    public function findNumberNonRunning(?string $queue = null, ?\DateTimeImmutable $executeAfter = null): int
    {
        $qb = $this->createQueryBuilder('j');
        $qb->select('COUNT(j.id)');
        if ($queue !== null) {
            $qb->andWhere($qb->expr()->eq('j.queue', ':queue'))
                ->setParameter('queue', $queue);
        }
        $qb->andWhere($qb->expr()->eq('j.state', ':state'))
            ->setParameter('state', JobState::PENDING);
        $qb->andWhere($qb->expr()->lt('j.executeAfter', ':executeAfter'))
            ->setParameter('executeAfter', $executeAfter ?? new \DateTimeImmutable('now'));
        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    public function findPercentFailedCommands(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?string $queue = null
    ): FailedPercent {
        $qb = $this->createQueryBuilder('j');
        $qb->select('COUNT(j.id)');
        if ($queue !== null) {
            $qb->andWhere($qb->expr()->eq('j.queue', ':queue'))
                ->setParameter('queue', $queue);
        }
        $qb->andWhere($qb->expr()->between('j.closedAt', ':timeFrom', ':timeTo'))
            ->setParameter('timeFrom', $from)
            ->setParameter('timeTo', $to);

        $qbFailed = clone $qb;
        $qbFailed->andWhere($qb->expr()->eq('j.state', ':state'))
            ->setParameter('state', JobState::FAILED);

        $failed = (int)$qbFailed->getQuery()->getSingleScalarResult();
        $all = (int)$qb->getQuery()->getSingleScalarResult();
        return new FailedPercent($failed, $all);
    }

    public function insertJobs(iterable $jobs): int
    {
        $cnt = 0;
        $em = $this->getEntityManager();
        foreach ($jobs as $job) {
            $em->persist($job);
            ++$cnt;
        }
        $em->flush();
        $em->clear();
        return $cnt;
    }

    public function isExistsJob(Job $job, int $deltaSeconds): bool
    {
        $metadata = $this->getClassMetadata();
        $table = $metadata->getTableName();
        $queueColumn = $metadata->getColumnName('queue');
        $commandColumn = $metadata->getColumnName('command');
        $executeAfterColumn = $metadata->getColumnName('executeAfter');

        $sql = <<<SQL
SELECT COUNT(*) cnt 
FROM {$table}
WHERE {$queueColumn} = :queue
  AND {$commandColumn}->>'$' = :command
  AND ABS(TIMESTAMPDIFF(SECOND, {$executeAfterColumn}, :executeAfter)) < :seconds
SQL;
        $params = [
            'queue' => $metadata->getFieldValue($job, 'queue'),
            'command' => $metadata->getFieldValue($job, 'command'),
            'executeAfter' => $metadata->getFieldValue($job, 'executeAfter'),
            'seconds' => $deltaSeconds,
        ];
        $types = [
            'queue' => $metadata->getTypeOfField('queue'),
            'command' => $metadata->getTypeOfField('command'),
            'executeAfter' => $metadata->getTypeOfField('executeAfter'),
            'seconds' => Types::INTEGER,
        ];
        $cnt = $this->getEntityManager()->getConnection()->executeQuery($sql, $params, $types)->fetchOne();
        return $cnt > 0;
    }
}
