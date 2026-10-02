<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;
use SyntaxDevTeam\MiniPortal\UI\Model\ContentFormat;

/** Authored content; the renderer must sanitize before emitting markup. */
final readonly class RichText implements Component
{
    public function __construct(
        public string $content,
        public ContentFormat $format = ContentFormat::Markdown,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
    }

    public static function componentType(): string { return 'rich_text'; }
    public function identity(): ?ComponentIdentity { return $this->componentIdentity; }
    public function children(): array { return []; }
}
