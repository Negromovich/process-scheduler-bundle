<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Negromovich\ProcessSchedulerBundle\Repository\JobStatusRepository")
 */
class JobStatus
{
    public const ACTIVE = 'active';
    public const STOPPING = 'stopping';
    public const INACTIVE = 'inactive';

    /** @ORM\Id @ORM\Column(name="queue", type="string") */
    private string $queue;

    /** @ORM\Column(name="status", type="string") */
    private string $status;

    public function __construct(string $queue, string $status)
    {
        $this->queue = $queue;
        $this->status = $status;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
