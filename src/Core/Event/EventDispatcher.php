<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Event;

use Closure;
use SyntaxDevTeam\MiniPortal\Core\Contract\Event\VersionedEvent;
use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final class EventDispatcher
{
    /** @var array<string, list<Closure(VersionedEvent): void>> */
    private array $listeners = [];

    public function __construct(private readonly Logger $logger)
    {
    }

    /**
     * @param class-string<VersionedEvent> $eventClass
     * @param Closure(VersionedEvent): void $listener
     */
    public function subscribe(string $eventClass, Closure $listener): void
    {
        $contract = EventContractId::fromEventClass($eventClass);
        $this->listeners[$contract->key()] ??= [];
        $this->listeners[$contract->key()][] = $listener;
    }

    public function dispatch(VersionedEvent $event): EventDispatchReport
    {
        $contract = EventContractId::fromEventClass($event::class);
        $listeners = $this->listeners[$contract->key()] ?? [];
        $delivered = 0;
        /** @var list<EventListenerFailure> $failures */
        $failures = [];

        foreach ($listeners as $index => $listener) {
            try {
                $listener($event);
                $delivered++;
            } catch (\Throwable $throwable) {
                $errorId = (string) CorrelationId::generate();
                $failures[] = new EventListenerFailure($contract->key(), $index, $errorId);
                $this->logger->error('Event listener failed.', [
                    'event_contract' => $contract->key(),
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
