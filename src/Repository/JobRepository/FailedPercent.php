<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Repository\JobRepository;

class FailedPercent
{
    private int $failed;
    private int $all;

    public function __construct(int $failed, int $all)
    {
        $this->failed = $failed;
        $this->all = $all;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getAll(): int
    {
        return $this->all;
    }

    public function getPercent(): float
    {
        return $this->all > 0 ? $this->failed / $this->all : 0.0;
    }
}
