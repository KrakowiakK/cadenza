<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Connector;

use Cadenza\Runtime\Message\SyncConnections;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('cadenza')]
final class SyncSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(RecurringMessage::every('1 day', new SyncConnections()));
    }
}
