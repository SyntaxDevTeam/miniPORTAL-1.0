<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Module\SitePages;

use SyntaxDevTeam\MiniPortal\UI\Model\ContentFormat;

final readonly class SitePage
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public string $summary,
        public string $content,
        public ContentFormat $format,
        public string $status,
        public string $authorId,
        public ?int $legacyId,
    ) {
    }
}
