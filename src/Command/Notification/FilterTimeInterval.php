<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\Command\Notification;

class FilterTimeInterval
{
    public const PREVIOUS_HOUR = 'previous_hour';
    public const CURRENT_HOUR = 'current_hour';
    public const PREVIOUS_DAY = 'previous_day';
    public const CURRENT_DAY = 'current_day';

    private \DateTimeImmutable $from;
    private \DateTimeImmutable $to;

    public static function getIntervals(): array
    {
        return array_values((new \ReflectionClass(__CLASS__))->getConstants());
    }

    public function __construct(string $interval)
    {
        $this->parseInterval($interval);
    }

    private function parseInterval(string $interval): void
    {
        switch ($interval) {
            case self::PREVIOUS_HOUR:
                $this->from = new \DateTimeImmutable('-1 hour');
                $this->from = $this->from->setTime((int)$this->from->format('H'), 0, 0);
                $this->to = $this->from->modify('+1 hour');
                break;

            case self::CURRENT_HOUR:
                $this->from = new \DateTimeImmutable('now');
                $this->from = $this->from->setTime((int)$this->from->format('H'), 0, 0);
                $this->to = $this->from->modify('+1 hour');
                break;

            case self::PREVIOUS_DAY:
                $this->from = new \DateTimeImmutable('-1 day');
                $this->from = $this->from->setTime(0, 0, 0);
                $this->to = $this->from->modify('+1 day');
                break;

            case self::CURRENT_DAY:
                $this->from = new \DateTimeImmutable('now');
                $this->from = $this->from->setTime(0, 0, 0);
                $this->to = $this->from->modify('+1 day');
                break;

            default:
                $message = sprintf(
                    'Invalid interval name, got "%s", allowed: "%s"',
                    $interval,
                    implode('", "', self::getIntervals())
                );
                throw new \InvalidArgumentException($message);
        }
    }

    public function getFrom(): \DateTimeImmutable
    {
        return $this->from;
    }

    public function getTo(): \DateTimeImmutable
    {
        return $this->to;
    }
}
