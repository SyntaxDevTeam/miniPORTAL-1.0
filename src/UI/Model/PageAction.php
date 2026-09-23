<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

final readonly class PageAction
{
    public function __construct(
        public string $id,
        public string $label,
        public ActionIntent $intent,
        public ?string $url = null,
        public bool $confirmationRequired = false,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $id) !== 1 || trim($label) === '') {
            throw new \InvalidArgumentException('Page action requires a valid ID and label.');
        }
        if ($url !== null && !str_starts_with($url, '/')
            && preg_match('/^https:\/\/[A-Za-z0-9.-]+(?::[0-9]+)?(?:\/|$)/D', $url) !== 1) {
            throw new \InvalidArgumentException('Action URL must be relative or HTTPS.');
        }
        if ($intent === ActionIntent::Delete && !$confirmationRequired) {
            throw new \InvalidArgumentException('Delete actions require confirmation.');
        }
        if (in_array($intent, [ActionIntent::Navigate, ActionIntent::Submit, ActionIntent::Delete], true) && $url === null) {
            throw new \InvalidArgumentException('Request actions require a URL.');
        }
    }
}
