<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Configuration;

final class EnvironmentFileParser
{
    /** @return array<string, string> */
    public function parse(string $contents): array
    {
        $values = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            if (preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/D', $line, $matches) !== 1) {
                throw new ConfigurationException(sprintf('Invalid environment entry on line %d.', $lineNumber + 1));
            }

            $key = $matches[1];
            if (array_key_exists($key, $values)) {
                throw new ConfigurationException(sprintf('Duplicate environment key %s.', $key));
            }
            $values[$key] = $this->value($matches[2], $lineNumber + 1);
        }
        return $values;
    }

    private function value(string $raw, int $lineNumber): string
    {
        if ($raw === '') {
            return '';
        }
        $quote = $raw[0];
        if ($quote !== '"' && $quote !== "'") {
            $comment = strpos($raw, ' #');
            return trim($comment === false ? $raw : substr($raw, 0, $comment));
        }
        if (strlen($raw) < 2 || !str_ends_with($raw, $quote)) {
            throw new ConfigurationException(sprintf('Unclosed quoted value on line %d.', $lineNumber));
        }
        $value = substr($raw, 1, -1);
        if ($quote === "'") {
            return $value;
        }
        return str_replace(['\\n', '\\r', '\\t', '\\"', '\\\\'], ["\n", "\r", "\t", '"', '\\'], $value);
    }
}
