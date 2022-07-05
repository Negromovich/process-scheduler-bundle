<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Processor;

use Negromovich\ProcessSchedulerBundle\Entity\Job;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class JobProcessFactory
{
    private ?array $env;
    private ?string $phpPath = null;
    private ?string $binConsolePath = null;

    public function __construct(?array $env = null)
    {
        $this->env = $env;
    }

    public function run(Job $job, LoggerInterface $logger): JobProcess
    {
        $command = $this->prepareCommand($job->getCommand());
        $process = new Process($command, null, $this->env, null, $job->getTimeout());
        $jobProcess = new JobProcess($job, $process, $logger);
        $jobProcess->start();
        return $jobProcess;
    }

    private function prepareCommand(array $command): array
    {
        if (($command[0] ?? null) === 'php') {
            $phpPath = $this->findPhpPath();
            if ($phpPath) {
                $command[0] = $phpPath;
            }
            foreach ($command as $i => $arg) {
                if ($arg === 'bin/console') {
                    $binConsolePath = $this->findBinConsolePath();
                    if ($binConsolePath) {
                        $command[$i] = $binConsolePath;
                    }
                    break;
                }
            }
        }
        if (($command[0] ?? null) === 'bin/console') {
            $binConsolePath = $this->findBinConsolePath();
            if ($binConsolePath) {
                $command[0] = $binConsolePath;
            }
        }
        return $command;
    }

    private function findPhpPath(): ?string
    {
        if ($this->phpPath !== null) {
            return $this->phpPath ?: null;
        }
        $process = new Process(['which', 'php']);
        $status = $process->run();
        if ($status === 0) {
            $output = $process->getOutput();
            return $this->phpPath = trim($output);
        }
        $this->phpPath = '';
        return null;
    }

    private function findBinConsolePath(): ?string
    {
        if ($this->binConsolePath !== null) {
            return $this->binConsolePath ?: null;
        }
        $attempts = 15;
        $dir = __DIR__ . '/../../';
        while ($attempts-- > 0) {
            $binConsole = $dir . 'bin/console';
            if (file_exists($binConsole)) {
                return $this->binConsolePath = realpath($binConsole);
            }
            $dir .= '../';
        }
        $this->binConsolePath = '';
        return null;
    }
}
