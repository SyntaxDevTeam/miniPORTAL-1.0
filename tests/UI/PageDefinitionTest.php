<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\UI;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\UI\FixtureComponent;
use SyntaxDevTeam\MiniPortal\UI\Model\ActionIntent;
use SyntaxDevTeam\MiniPortal\UI\Model\Breadcrumb;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;

final class PageDefinitionTest extends TestCase
{
    public function testDefinesSemanticRegionsWithoutLayoutStructure(): void
    {
        $content = new FixtureComponent(new ComponentIdentity('server.files'));
        $page = new PageDefinition(
            'server-files',
            'Pliki',
            'dashboard',
            [PageRegion::CONTENT => [$content]],
            [new Breadcrumb('Serwery', '/servers'), new Breadcrumb('Pliki')],
            [new PageAction('upload', 'Prześlij', ActionIntent::Submit, '/servers/1/files')],
        );

        self::assertSame([$content], $page->region(PageRegion::CONTENT));
        self::assertSame([], $page->region(PageRegion::ASIDE));
        self::assertSame('dashboard', $page->layoutRole);
    }

    public function testRejectsDuplicateIdentityAcrossRegions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PageDefinition('duplicate', 'Duplicate', 'application', [
            PageRegion::CONTENT => [new FixtureComponent(new ComponentIdentity('shared'))],
            PageRegion::ASIDE => [new FixtureComponent(new ComponentIdentity('shared'))],
        ]);
    }

    public function testRejectsComponentCycles(): void
    {
        $parent = new FixtureComponent();
        $child = new FixtureComponent();
        $parent->append($child);
        $child->append($parent);

        $this->expectException(\InvalidArgumentException::class);
        new PageDefinition('cycle', 'Cycle', 'application', [PageRegion::CONTENT => [$parent]]);
    }

    public function testDeleteActionRequiresConfirmation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PageAction('delete', 'Usuń', ActionIntent::Delete, '/items/1');
    }

    public function testRejectsUnsafeActionUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PageAction('go', 'Go', ActionIntent::Navigate, 'javascript:alert(1)');
    }
}
