<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Rendering;

use DOMDocument;
use DOMElement;
use DOMNode;
use League\CommonMark\CommonMarkConverter;
use SyntaxDevTeam\MiniPortal\UI\Model\ContentFormat;

/** Sanitizes legacy HTML and renders Markdown without executable markup. */
final class SafeRichText
{
    private const ALLOWED = [
        'p' => true, 'br' => true, 'h1' => true, 'h2' => true, 'h3' => true,
        'h4' => true, 'ul' => true, 'ol' => true, 'li' => true, 'strong' => true,
        'em' => true, 'b' => true, 'i' => true, 'blockquote' => true, 'code' => true,
        'pre' => true, 'a' => true, 'hr' => true, 'table' => true, 'thead' => true,
        'tbody' => true, 'tr' => true, 'th' => true, 'td' => true,
    ];
    private const DROP_TREE = [
        'script' => true, 'style' => true, 'iframe' => true, 'object' => true,
        'embed' => true, 'svg' => true, 'math' => true, 'form' => true,
        'input' => true, 'button' => true, 'textarea' => true,
    ];

    public function render(string $content, ContentFormat $format): string
    {
        if ($format === ContentFormat::Markdown) {
            $converted = (string) (new CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]))->convert($content);
            return $this->sanitizeHtml($converted);
        }
        return $this->sanitizeHtml($content);
    }

    private function sanitizeHtml(string $content): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<!doctype html><html><head><meta charset="utf-8"></head><body><div>' . $content . '</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            return '<p>' . Html::escape(strip_tags($content)) . '</p>';
        }
        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return '<p>' . Html::escape(strip_tags($content)) . '</p>';
        }
        $html = '';
        foreach ($body->childNodes as $node) {
            $html .= $this->node($node);
        }
        return $html;
    }

    private function node(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return Html::escape($node->nodeValue ?? '');
        }
        if (!$node instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if (isset(self::DROP_TREE[$tag])) {
            return '';
        }
        $children = '';
        foreach ($node->childNodes as $child) {
            $children .= $this->node($child);
        }
        if (!isset(self::ALLOWED[$tag])) {
            return $children;
        }
        if ($tag === 'br' || $tag === 'hr') {
            return '<' . $tag . '>';
        }
        $attributes = '';
        if ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            if ($this->safeHref($href)) {
                $attributes = ' href="' . Html::escape($href) . '" rel="noopener noreferrer"';
            }
        }
        return '<' . $tag . $attributes . '>' . $children . '</' . $tag . '>';
    }

    private function safeHref(string $href): bool
    {
        if ($href === '' || preg_match('/[\x00-\x20\x7f]/', $href) === 1
            || str_starts_with($href, '//')) {
            return false;
        }
        return str_starts_with($href, '/') || str_starts_with($href, '#')
            || preg_match('~^(https?://|mailto:)~i', $href) === 1;
    }
}
