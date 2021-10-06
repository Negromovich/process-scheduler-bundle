<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Command\Notification;

use Negromovich\ProcessSchedulerBundle\Repository\JobRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;

class NotifyNonRunningJobCommand extends Command
{
    protected static $defaultName = 'negromovich:process-scheduler:notification:notify-non-running-job';
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
            'number',
            InputArgument::OPTIONAL,
            'Send notification when non running jobs greater than this value',
            100
        );
        $this->addArgument('queue', InputArgument::OPTIONAL, 'Queue name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queue = $input->getArgument('queue');
        $value = $this->jobRepository->findNumberNonRunning($queue);

        $output->writeln(sprintf('Find %d non running jobs', $value));

        $notifyNumber = (float)$input->getArgument('number');
        if ($value > $notifyNumber) {
            $message = sprintf('Find %d non running jobs', $value);
            if ($queue) {
                $message .= " in queue \"{$queue}\"";
            }
            $this->notifier->send(new Notification($message));
            $output->writeln('Sent notification');
        }
        return 0;
    }
}
