<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Command;

use Negromovich\ProcessSchedulerBundle\Entity\JobStatus;
use Negromovich\ProcessSchedulerBundle\Repository\JobStatusRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerStopCommand extends Command
{
    protected static $defaultName = 'negromovich:process-scheduler:worker-stop';
    private JobStatusRepository $jobStatusRepository;
    private LoggerInterface $logger;

    public function __construct(JobStatusRepository $jobStatusRepository, LoggerInterface $logger)
    {
        parent::__construct();
        $this->jobStatusRepository = $jobStatusRepository;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this->addArgument('queue', InputArgument::OPTIONAL, 'Queue name', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queue = $input->getArgument('queue');
        $this->jobStatusRepository->setStatus($queue, JobStatus::STOPPING);
        $this->logger->warning("Send signal to stop workers for queue \"{$queue}\". New processes won't start.");
        return 0;
    }
}
