<?php

namespace App\Core;

class EntityWorkflowGuard
{
    public function validateTransition(string $entityKey, string $fromState, string $toState): void
    {
        $definition = EntityRegistry::get($entityKey);
        if (!$definition) {
            throw new \InvalidArgumentException("Unknown entity: {$entityKey}");
        }

        $workflow = $definition['workflow'] ?? null;
        if (!$workflow) {
            return;
        }

        $transitions = $workflow['transitions'] ?? [];
        $allowed = $transitions[$fromState] ?? [];

        if (!in_array($toState, $allowed, true)) {
            throw new \RuntimeException("Invalid workflow transition from '{$fromState}' to '{$toState}'");
        }
    }

    public function canTransition(string $entityKey, string $fromState, string $toState): bool
    {
        try {
            $this->validateTransition($entityKey, $fromState, $toState);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getAllowedTransitions(string $entityKey, string $fromState): array
    {
        $definition = EntityRegistry::get($entityKey);
        if (!$definition) {
            return [];
        }

        $workflow = $definition['workflow'] ?? null;
        if (!$workflow) {
            return [];
        }

        return $workflow['transitions'][$fromState] ?? [];
    }

    public function getWorkflowField(string $entityKey): string
    {
        $definition = EntityRegistry::get($entityKey);
        if (!$definition) {
            return 'status';
        }

        $workflow = $definition['workflow'] ?? null;
        if (!$workflow) {
            return 'status';
        }

        return $workflow['field'] ?? 'status';
    }

    public function getStates(string $entityKey): array
    {
        $definition = EntityRegistry::get($entityKey);
        if (!$definition) {
            return [];
        }

        $workflow = $definition['workflow'] ?? null;
        if (!$workflow) {
            return [];
        }

        return $workflow['states'] ?? [];
    }
}
