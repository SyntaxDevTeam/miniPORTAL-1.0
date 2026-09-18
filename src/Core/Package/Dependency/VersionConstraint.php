<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Dependency;

final class VersionConstraint
{
    public function matches(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '*') {
            return true;
        }

        $current = $this->parseVersion($version);
        if ($current === null) {
            return false;
        }

        if (str_starts_with($constraint, '^')) {
            $minimum = $this->parsePartialVersion(substr($constraint, 1));
            if ($minimum === null) {
                throw new \InvalidArgumentException('Unsupported version constraint: ' . $constraint);
            }

            $maximum = match (true) {
                $minimum[0] > 0 => [$minimum[0] + 1, 0, 0],
                $minimum[1] > 0 => [0, $minimum[1] + 1, 0],
                default => [0, 0, $minimum[2] + 1],
            };

            return $this->compare($current, $minimum) >= 0
                && $this->compare($current, $maximum) < 0;
        }

        foreach (['>=', '<=', '>', '<'] as $operator) {
            if (str_starts_with($constraint, $operator)) {
                $target = $this->parsePartialVersion(substr($constraint, strlen($operator)));
                if ($target === null) {
                    throw new \InvalidArgumentException('Unsupported version constraint: ' . $constraint);
                }

                $comparison = $this->compare($current, $target);

                return match ($operator) {
                    '>=' => $comparison >= 0,
                    '<=' => $comparison <= 0,
                    '>' => $comparison > 0,
                    '<' => $comparison < 0,
                };
            }
        }

        $exact = $this->parseVersion($constraint);
        if ($exact === null) {
            throw new \InvalidArgumentException('Unsupported version constraint: ' . $constraint);
        }

        return $this->compare($current, $exact) === 0;
    }

    /** @return array{int, int, int}|null */
    private function parseVersion(string $version): ?array
    {
        if (preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', trim($version), $matches) !== 1) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
    }

    /** @return array{int, int, int}|null */
    private function parsePartialVersion(string $version): ?array
    {
        if (preg_match('/^(0|[1-9]\d*)(?:\.(0|[1-9]\d*))?(?:\.(0|[1-9]\d*))?$/', trim($version), $matches) !== 1) {
            return null;
        }

        return [
            (int) $matches[1],
            isset($matches[2]) ? (int) $matches[2] : 0,
            isset($matches[3]) ? (int) $matches[3] : 0,
        ];
    }

    /**
     * @param array{int, int, int} $left
     * @param array{int, int, int} $right
     */
    private function compare(array $left, array $right): int
    {
        return $left <=> $right;
    }
}
