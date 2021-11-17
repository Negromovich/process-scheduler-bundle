<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Processor;

use DateTimeImmutable;
use DateTimeZone;
use Negromovich\ProcessSchedulerBundle\Entity\Job;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;

class JobProcess
{
    private Job $job;
    private Process $process;
    private LoggerInterface $logger;
    private bool $finished = false;

    public static function run(Job $job, LoggerInterface $logger): self
    {
        $command = self::prepareCommand($job->getCommand());
        $process = new Process($command, null, null, null, $job->getTimeout());
        $jobProcess = new self($job, $process, $logger);
        $jobProcess->start();
        return $jobProcess;
    }

    private static function prepareCommand(array $command): array
    {
        if (($command[0] ?? null) === 'bin/console') {
            $attempts = 15;
            $dir = __DIR__ . '/../../';
            while ($attempts-- > 0) {
                $binConsole = $dir . 'bin/console';
                if (file_exists($binConsole)) {
                    $command[0] = $binConsole;
                    break;
                }
                $dir .= '../';
            }
        }
        return $command;
    }

    private function __construct(Job $job, Process $process, LoggerInterface $logger = null)
    {
        $this->job = $job;
        $this->process = $process;
        $this->logger = $logger ?? new NullLogger();
    }

    private function start(): void
    {
        $this->logger->notice("Start process \"{$this->process->getCommandLine()}\"");
        $this->process->start();
        $startedAt = DateTimeImmutable::createFromFormat(
            'U.u',
            (string)$this->process->getStartTime(),
            new DateTimeZone('UTC')
        );
        $this->job->runJob($startedAt, $this->process->getPid());
    }

    public function update(): void
    {
        if (false === $this->process->isStarted()) {
            throw new JobException('You must start job before update');
        }

        $isRunning = $this->process->isRunning();
        $this->job->addErrorOutput($this->process->getIncrementalErrorOutput());
        $this->job->addOutput($this->process->getIncrementalOutput());

        if (false === $isRunning) {
            $this->finalize();
        }
    }

    private function finalize(): void
    {
        $exitCode = $this->process->getExitCode();
        if ($this->process->isSuccessful()) {
            $this->job->finishJob($exitCode);
            $this->logger->notice("Process \"{$this->process->getCommandLine()}\" finished successfully");
        } else {
            $this->job->errorJob($exitCode);
            $this->logger->error("Process \"{$this->process->getCommandLine()}\" finished with error");
        }
        $this->finished = true;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function getJob(): Job
    {
        return $this->job;
    }
}
