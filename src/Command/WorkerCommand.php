<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Command;

use Negromovich\ProcessSchedulerBundle\Entity\JobStatus;
use Negromovich\ProcessSchedulerBundle\Processor\JobProcessor;
use Negromovich\ProcessSchedulerBundle\Repository\JobStatusRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WorkerCommand extends Command
{
    protected static $defaultName = 'negromovich:process-scheduler:worker';
    private JobProcessor $jobProcessor;
    private JobStatusRepository $jobStatusRepository;
    private LoggerInterface $logger;

    public function __construct(JobProcessor $jobProcessor, JobStatusRepository $jobStatusRepository, LoggerInterface $logger)
    {
        parent::__construct();
        $this->jobProcessor = $jobProcessor;
        $this->jobStatusRepository = $jobStatusRepository;
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this->addArgument('queue', InputArgument::OPTIONAL, 'Queue name', 'default');
        $this->addOption('idle', null, InputOption::VALUE_REQUIRED, 'Pause between next run', '10');
        $this->addOption('concurrency', 'c', InputOption::VALUE_REQUIRED, 'Number of concurrency processes', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queue = $input->getArgument('queue');
        $idle = $input->getOption('idle');
        $concurrency = $input->getOption('concurrency');

        foreach (compact('idle', 'concurrency') as $option => $value) {
            if (false === ctype_digit($value) || $value <= 0) {
                $out = new SymfonyStyle($input, $output);
                $out->error("Option \"${$option}\" must be integer and greater that zero");
                return 1;
            }
        }

        $this->jobStatusRepository->setStatus($queue, JobStatus::ACTIVE);
        while (true) {
            $this->jobProcessor->processJobs($queue, (int)$concurrency);

            if ($this->jobStatusRepository->getStatus($queue) !== JobStatus::ACTIVE) {
                $this->logger->warning("Workers for queue \"{$queue}\" stopped");
                break;
            }

            $this->logger->warning("Not found pending jobs for queue \"{$queue}\". Wait {$idle} seconds.");
            sleep((int)$idle);
        }
        $this->jobStatusRepository->setStatus($queue, JobStatus::INACTIVE);

        return 0;
    }
}
