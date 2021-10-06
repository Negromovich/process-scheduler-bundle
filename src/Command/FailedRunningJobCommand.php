<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Command;

use Negromovich\ProcessSchedulerBundle\Repository\JobRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FailedRunningJobCommand extends Command
{
    protected static $defaultName = 'negromovich:process-scheduler:failed-running-job';
    private JobRepository $jobRepository;
    private LoggerInterface $logger;

    public function __construct(JobRepository $jobRepository, LoggerInterface $logger)
    {
        parent::__construct();
        $this->jobRepository = $jobRepository;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this->setDescription('Make running jobs as failed after particular time after starting');
        $this->addArgument(
            'timeInterval',
            InputArgument::OPTIONAL,
            'Time interval for search all jobs before this time',
            '1 day'
        );
        $this->addArgument('queue', InputArgument::OPTIONAL, 'Queue name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeInterval = $input->getArgument('timeInterval');
        $time = (new \DateTimeImmutable('-' . $timeInterval, new \DateTimeZone('UTC')));
        $queue = $input->getArgument('queue');
        $cnt = $this->jobRepository->failedRunningBefore($time, $queue);

        $message = "Failed $cnt running jobs successfully";
        $this->logger->notice($message);
        $output->writeln($message);
        return 0;
    }
}
