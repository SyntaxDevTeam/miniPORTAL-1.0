<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Migration\Legacy;

final readonly class PagesImportPlan
{
    public function __construct(public string $checksum, public int $pages, public int $published)
    {
    }
}
