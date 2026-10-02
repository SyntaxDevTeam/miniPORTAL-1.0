<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Widget;

use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestContext;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;
use SyntaxDevTeam\MiniPortal\UI\Component\ErrorState;
use SyntaxDevTeam\MiniPortal\UI\Component\WidgetSlot;
use SyntaxDevTeam\MiniPortal\UI\Validation\ComponentTreeValidator;

/** Resolves one slot without allowing a widget failure to escape into page rendering. */
final readonly class WidgetComposer
{
    public function __construct(
        private WidgetPlacementRepository $placements,
        private WidgetCatalog $catalog,
        private PackageRegistry $packages,
        private Logger $logger,
    ) {
    }

    public function slot(string $pageId, string $slot, ?RequestContext $context): WidgetSlot
    {
        $components = [];
        try {
            $instances = $this->placements->forSlot($pageId, $slot);
        } catch (\Throwable $exception) {
            $errorId = (string) CorrelationId::generate();
            $this->logger->error('Widget placements unavailable.', [
                'page_id' => $pageId, 'slot' => $slot, 'error_id' => $errorId,
                'exception' => $exception::class, 'message' => $exception->getMessage(),
            ]);
            return new WidgetSlot($slot, [new ErrorState('Widgety są niedostępne', errorId: $errorId)]);
        }
        foreach ($instances as $instance) {
            if ($instance->requiredPermission !== null && !($context?->hasPermission($instance->requiredPermission) ?? false)) {
                continue;
            }
            try {
                if ($this->packages->active($instance->moduleId) === null) {
                    continue;
                }
                $provider = $this->catalog->provider($instance->moduleId, $instance->type);
                if ($provider === null) {
                    throw new \LogicException('Active widget provider is unavailable.');
                }
                $rendered = $provider->render($instance, $context);
                (new ComponentTreeValidator())->validate(['slot' => [...$components, ...$rendered]]);
                array_push($components, ...$rendered);
            } catch (\Throwable $exception) {
                $errorId = (string) CorrelationId::generate();
                $this->logger->error('Widget rendering failed.', [
                    'widget_id' => $instance->id,
                    'module_id' => $instance->moduleId,
                    'error_id' => $errorId,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
                $components[] = new ErrorState('Widget jest niedostępny', errorId: $errorId);
            }
        }
        return new WidgetSlot($slot, $components);
    }
}
