<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Entity;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Negromovich\ProcessSchedulerBundle\Processor\JobException;
use Symfony\Component\Uid\Ulid;

/**
 * @ORM\Entity(repositoryClass="Negromovich\ProcessSchedulerBundle\Repository\JobRepository")
 * @ORM\Table(indexes={
 *     @ORM\Index(name="search_idx", columns={"queue", "state", "execute_after"})
 * })
 */
class Job
{
    /** @ORM\Id @ORM\Column(name="id", type="string", length=26) */
    private string $id;

    /** @ORM\Column(name="queue", type="string", options={"default": "default"}) */
    private string $queue;


    /** @ORM\Column(name="command", type="json") */
    private array $command;

    /** @ORM\Column(name="timeout", type="float", nullable=true) */
    private ?float $timeout = null;


    /** @ORM\Column(name="priority", type="smallint") */
    private int $priority = 0;

    /** @ORM\Column(name="state", type="string", length=10) */
    private string $state = JobState::PENDING;

    /** @ORM\Column(name="created_at", type="datetime_immutable") */
    private DateTimeImmutable $createdAt;

    /** @ORM\Column(name="execute_after", type="datetime_immutable") */
    private ?DateTimeImmutable $executeAfter;

    /** @ORM\Column(name="started_at", type="datetime_immutable", nullable=true) */
    private ?DateTimeImmutable $startedAt = null;

    /** @ORM\Column(name="closed_at", type="datetime_immutable", nullable=true) */
    private ?DateTimeImmutable $closedAt = null;

    /** @ORM\Column(name="pid", type="integer", nullable=true) */
    private ?int $pid = null;

    /** @ORM\Column(name="output", type="text", nullable=true) */
    private ?string $output = null;

    /** @ORM\Column(name="error_output", type="text", nullable=true) */
    private ?string $errorOutput = null;

    /** @ORM\Column(name="exit_code", type="smallint", nullable=true) */
    private ?int $exitCode = null;


    public function __construct(array $command, string $queue = 'default', int $priority = 0, ?float $timeout = null)
    {
        $this->id = (string)(new Ulid());
        $this->queue = $queue;
        $this->command = $command;
        $this->timeout = $timeout;
        $this->priority = $priority;
        $this->createdAt = $this->executeAfter = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function __clone()
    {
        $this->id = (string)(new Ulid());
    }

    public function scheduleJob(DateTimeImmutable $executeAfter, int $priority = null): void
    {
        $this->executeAfter = $executeAfter;
        if ($priority !== null) {
            $this->priority = $priority;
        }
    }

    public function cancelJob(): void
    {
        if ($this->state === JobState::PENDING) {
            $this->state = JobState::CANCELLED;
            $this->closedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
        throw new JobException('Can\'t cancel already running job');
    }

    public function runJob(DateTimeImmutable $startedAt = null, int $pid = null): void
    {
        $this->state = JobState::RUNNING;
        $this->startedAt = $startedAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->pid = $pid;
    }

    public function finishJob(int $exitCode): void
    {
        $this->state = JobState::FINISHED;
        $this->closeJob($exitCode);
    }

    public function errorJob(int $exitCode): void
    {
        $this->state = JobState::FAILED;
        $this->closeJob($exitCode);
    }

    private function closeJob(int $exitCode): void
    {
        $this->closedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->exitCode = $exitCode;
    }

    public function addOutput(string $output): void
    {
        $this->output ??= '';
        $this->output .= $output;
        $this->output = $this->prepareOutput($this->output);
    }

    public function addErrorOutput(string $errorOutput): void
    {
        $this->errorOutput ??= '';
        $this->errorOutput .= $errorOutput;
        $this->errorOutput = $this->prepareOutput($this->errorOutput);
    }

    private function prepareOutput(string $output): string
    {
        if (mb_strlen($output) > 25000) {
            $new = preg_replace('/(INSERT INTO.*? )VALUES.*\]:\e/i', "\${1} ...(truncated)\e", $output);
            $output = is_string($new) ? $new : $output;
            $new = preg_replace('/(INSERT INTO.*? )VALUES.*\]:\s*\n/is', "\${1} ...(truncated)\n", $output);
            $output = is_string($new) ? $new : $output;
            return substr($output, 0, 10240) . " ...(truncated)\n";
        }
        return $output;
    }


    public function getId(): string
    {
        return $this->id;
    }

    public function getCommand(): array
    {
        return $this->command;
    }

    public function getTimeout(): ?float
    {
        return $this->timeout;
    }
}
