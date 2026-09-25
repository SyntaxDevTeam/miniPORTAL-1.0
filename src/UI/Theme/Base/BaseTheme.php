<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base;

use SyntaxDevTeam\MiniPortal\UI\Contract\Theme;
use SyntaxDevTeam\MiniPortal\UI\Component\Alert;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\Heading;
use SyntaxDevTeam\MiniPortal\UI\Component\Stack;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Component\Form;
use SyntaxDevTeam\MiniPortal\UI\Component\TextField;
use SyntaxDevTeam\MiniPortal\UI\Component\SelectField;
use SyntaxDevTeam\MiniPortal\UI\Component\CheckboxField;
use SyntaxDevTeam\MiniPortal\UI\Component\DataTable;
use SyntaxDevTeam\MiniPortal\UI\Component\EmptyState;
use SyntaxDevTeam\MiniPortal\UI\Component\ErrorState;
use SyntaxDevTeam\MiniPortal\UI\Component\LoadingState;
use SyntaxDevTeam\MiniPortal\UI\Component\Pagination;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\AlertRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\CardRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\HeadingRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\StackRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\TextRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\FormRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\TextFieldRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\SelectFieldRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\CheckboxFieldRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\DataTableRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\EmptyStateRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\ErrorStateRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\LoadingStateRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\PaginationRenderer;

final class BaseTheme implements Theme
{
    public function id(): string
    {
        return 'base';
    }

    public function uiApiConstraint(): string
    {
        return '^1.0';
    }

    public function supportsLayout(string $_layoutRole): bool
    {
        return true;
    }

    public function renderers(): RendererRegistry
    {
        $registry = new RendererRegistry();
        $registry->register(Text::class, new TextRenderer());
        $registry->register(Heading::class, new HeadingRenderer());
        $registry->register(Alert::class, new AlertRenderer());
        $registry->register(Stack::class, new StackRenderer());
        $registry->register(Card::class, new CardRenderer());
        $registry->register(Form::class, new FormRenderer());
        $registry->register(TextField::class, new TextFieldRenderer());
        $registry->register(SelectField::class, new SelectFieldRenderer());
        $registry->register(CheckboxField::class, new CheckboxFieldRenderer());
        $registry->register(EmptyState::class, new EmptyStateRenderer());
        $registry->register(ErrorState::class, new ErrorStateRenderer());
        $registry->register(LoadingState::class, new LoadingStateRenderer());
        $registry->register(DataTable::class, new DataTableRenderer());
        $registry->register(Pagination::class, new PaginationRenderer());
        return $registry;
    }

    public function render(PageDefinition $page, string $language = 'pl'): string
    {
        return (new BasePageRenderer($this->renderers()))->render($page, $language);
    }
}
