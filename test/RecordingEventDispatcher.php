<?php

declare(strict_types=1);

namespace Horde\Smtp\Test;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Captures all dispatched events for test assertions.
 */
class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var object[] */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
