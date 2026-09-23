<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Rendering;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;

final class RendererRegistry
{
    /** @var array<class-string<Component>, ComponentRenderer> */
    private array $renderers = [];

    public function __construct(private readonly ?self $parent = null)
    {
    }

    /** @param class-string<Component> $componentClass */
    public function register(string $componentClass, ComponentRenderer $renderer): void
    {
        if (isset($this->renderers[$componentClass])) {
            throw new \LogicException(sprintf('Renderer for %s is already registered.', $componentClass));
        }
        $this->renderers[$componentClass] = $renderer;
    }

    public function render(Component $component): string
    {
        $renderer = $this->findRenderer($component::class);
        if ($renderer === null) {
            throw new RendererNotFound(sprintf('No renderer registered for %s.', $component::class));
        }
        return $renderer->render($component, $this);
    }

    /** @param list<Component> $components */
    public function renderMany(array $components): string
    {
        return implode('', array_map($this->render(...), $components));
    }

    /** @return list<class-string<Component>> */
    public function registeredComponents(): array
    {
        $inherited = $this->parent?->registeredComponents() ?? [];
        return array_values(array_unique([...$inherited, ...array_keys($this->renderers)]));
    }

    /** @return list<class-string<Component>> */
    public function overriddenComponents(): array
    {
        return array_keys($this->renderers);
    }

    /** @param class-string<Component> $componentClass */
    private function findRenderer(string $componentClass): ?ComponentRenderer
    {
        return $this->renderers[$componentClass] ?? $this->parent?->findRenderer($componentClass);
    }
}
