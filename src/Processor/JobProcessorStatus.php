<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Processor;

use Negromovich\ProcessSchedulerBundle\Entity\JobStatus;
use Negromovich\ProcessSchedulerBundle\Repository\JobStatusRepository;

class JobProcessorStatus
{
    private JobStatusRepository $jobStatusRepository;

    public function __construct(JobStatusRepository $jobStatusRepository)
    {
        $this->jobStatusRepository = $jobStatusRepository;
    }

    public function isActive(string $queue = 'default'): bool
    {
        return $this->jobStatusRepository->getStatus($queue) === JobStatus::ACTIVE;
    }
}
