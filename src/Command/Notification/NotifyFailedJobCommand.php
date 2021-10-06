<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Command\Notification;

use Negromovich\ProcessSchedulerBundle\Repository\JobRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;

class NotifyFailedJobCommand extends Command
{
    protected static $defaultName = 'negromovich:process-scheduler:notification:notify-failed-job';
    private NotifierInterface $notifier;
    private JobRepository $jobRepository;

    public function __construct(NotifierInterface $notifier, JobRepository $jobRepository)
    {
        parent::__construct();
        $this->notifier = $notifier;
        $this->jobRepository = $jobRepository;
    }

    protected function configure(): void
    {
        $this->addArgument(
            'percent',
            InputArgument::OPTIONAL,
            'Send notification when failed percent greater than this value',
            0.0
        );
        $this->addArgument('queue', InputArgument::OPTIONAL, 'Queue name');
        $this->addOption(
            'interval',
            null,
            InputOption::VALUE_REQUIRED,
            'Possible values: ' . implode(', ', FilterTimeInterval::getIntervals()),
            FilterTimeInterval::PREVIOUS_HOUR
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $interval = new FilterTimeInterval($input->getOption('interval'));
        $queue = $input->getArgument('queue');
        $percent = $this->jobRepository->findPercentFailedCommands($interval->getFrom(), $interval->getTo(), $queue);

        $output->writeln(sprintf('Find %d failed jobs and %d overall jobs', $percent->getFailed(), $percent->getAll()));

        $notifyPercent = (float)$input->getArgument('percent');
        $failedPercent = $percent->getPercent() * 100;
        if ($failedPercent > $notifyPercent) {
            $message = sprintf(
                'Failed %d/%d (%.2f %%) jobs from %s to %s',
                $percent->getFailed(),
                $percent->getAll(),
                $percent->getPercent() * 100,
                $interval->getFrom()->format('Y-m-d H:i:s'),
                $interval->getTo()->format('Y-m-d H:i:s')
            );
            if ($queue) {
                $message .= " in queue \"{$queue}\"";
            }
            $this->notifier->send(new Notification($message));
            $output->writeln('Sent notification');
        }
        return 0;
    }
}
