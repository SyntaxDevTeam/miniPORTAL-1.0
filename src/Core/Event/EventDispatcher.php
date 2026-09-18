<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Event;

use Closure;
use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final class EventDispatcher
{
    /** @var array<class-string, list<Closure(object): void>> */
    private array $listeners = [];

    public function __construct(private readonly Logger $logger)
    {
    }

    /**
     * @param class-string $eventClass
     * @param Closure(object): void $listener
     */
    public function subscribe(string $eventClass, Closure $listener): void
    {
        $this->listeners[$eventClass] ??= [];
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): EventDispatchReport
    {
        $eventClass = $event::class;
        $listeners = $this->listeners[$eventClass] ?? [];
        $delivered = 0;
        /** @var list<EventListenerFailure> $failures */
        $failures = [];

        foreach ($listeners as $index => $listener) {
            try {
                $listener($event);
                $delivered++;
            } catch (\Throwable $throwable) {
                $errorId = (string) CorrelationId::generate();
                $failures[] = new EventListenerFailure($eventClass, $index, $errorId);
                $this->logger->error('Event listener failed.', [
                    'event' => $eventClass,
                    'listener_index' => $index,
                    'error_id' => $errorId,
                    'exception' => $throwable::class,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        return new EventDispatchReport($delivered, $failures);
    }
}
