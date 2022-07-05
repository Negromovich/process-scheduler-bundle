<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Processor;

use Doctrine\ORM\EntityManagerInterface;
use Negromovich\ProcessSchedulerBundle\Repository\JobRepository;
use Psr\Log\LoggerInterface;

class JobProcessor
{
    private LoggerInterface $logger;
    private EntityManagerInterface $em;
    private JobRepository $jobRepository;
    private JobProcessorStatus $jobProcessorStatus;
    private JobProcessFactory $jobProcessFactory;

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $em,
        JobRepository $jobRepository,
        JobProcessorStatus $jobProcessorStatus,
        JobProcessFactory $jobProcessFactory
    ) {
        $this->logger = $logger;
        $this->em = $em;
        $this->jobRepository = $jobRepository;
        $this->jobProcessorStatus = $jobProcessorStatus;
        $this->jobProcessFactory = $jobProcessFactory;
    }

    public function processJobs(string $queue, int $concurrency = 1, float $idle = 1): void
    {
        $state = new JobProcessorState(
            $this->logger,
            $this->em,
            $this->jobRepository,
            $this->jobProcessorStatus,
            $this->jobProcessFactory,
            $queue,
            $concurrency,
            $idle
        );
        $state->run();
    }
}
