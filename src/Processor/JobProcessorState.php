<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Processor;

use Doctrine\ORM\EntityManagerInterface;
use Negromovich\ProcessSchedulerBundle\Repository\JobRepository;
use Psr\Log\LoggerInterface;

class JobProcessorState
{
    private LoggerInterface $logger;
    private EntityManagerInterface $em;
    private JobRepository $jobRepository;
    private JobProcessorStatus $jobProcessorStatus;
    private JobProcessFactory $jobProcessFactory;
    private string $queue;
    private int $concurrency;
    private int $idle;

    /** @var JobProcess[] */
    private array $list = [];

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $em,
        JobRepository $jobRepository,
        JobProcessorStatus $jobProcessorStatus,
        JobProcessFactory $jobProcessFactory,
        string $queue = 'default',
        int $concurrency = 1,
        float $idle = 1.0
    ) {
        $this->logger = $logger;
        $this->em = $em;
        $this->jobRepository = $jobRepository;
        $this->jobProcessorStatus = $jobProcessorStatus;
        $this->jobProcessFactory = $jobProcessFactory;
        $this->queue = $queue;
        $this->concurrency = $concurrency;
        $this->idle = (int)round(1_000_000 * $idle);
    }

    public function run(): void
    {
        $this->fill();
        while ($this->list) {
            $this->tick();
            usleep($this->idle);
        }
    }

    private function fill(): void
    {
        if (false === $this->jobProcessorStatus->isActive($this->queue)) {
            return;
        }
        while ($this->concurrency - count($this->list) > 0) {
            $job = $this->jobRepository->findNextJob($this->queue);
            if ($job) {
                $this->list[] = $this->jobProcessFactory->run($job, $this->logger);
                $this->em->flush();
            } else {
                break;
            }
        }
    }

    private function tick(): void
    {
        $removes = [];
        foreach ($this->list as $idx => $jobProcess) {
            $jobProcess->update();
            if ($jobProcess->isFinished()) {
                unset($this->list[$idx]);
                $removes[] = $jobProcess->getJob();
            }
        }
        $this->em->flush();
        foreach ($removes as $job) {
            $this->em->detach($job);
        }
        $this->list = array_values($this->list);
        $this->fill();
    }
}
