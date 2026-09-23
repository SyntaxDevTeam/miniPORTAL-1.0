<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Validation;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;

final class ComponentTreeValidator
{
    /** @param array<string, list<Component>> $regions */
    public function validate(array $regions): void
    {
        $identities = [];
        $ancestors = [];
        foreach ($regions as $components) {
            foreach ($components as $component) {
                $this->visit($component, $identities, $ancestors);
            }
        }
    }

    /**
     * @param array<string, true> $identities
     * @param array<int, true> $ancestors
     */
    private function visit(Component $component, array &$identities, array &$ancestors): void
    {
        $objectId = spl_object_id($component);
        if (isset($ancestors[$objectId])) {
            throw new \InvalidArgumentException('Component tree contains a cycle.');
        }
        $identity = $component->identity();
        if ($identity !== null) {
            if (isset($identities[$identity->value])) {
                throw new \InvalidArgumentException(sprintf('Duplicate component identity: %s.', $identity->value));
            }
            $identities[$identity->value] = true;
        }

        $ancestors[$objectId] = true;
        foreach ($component->children() as $child) {
            $this->visit($child, $identities, $ancestors);
        }
        unset($ancestors[$objectId]);
    }
}
