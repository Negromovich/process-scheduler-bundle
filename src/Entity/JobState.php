<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Entity;

final class JobState
{
    public const PENDING = 'pending';
    public const CANCELLED = 'cancelled';
    public const RUNNING = 'running';
    public const FINISHED = 'finished';
    public const FAILED = 'failed';
}
