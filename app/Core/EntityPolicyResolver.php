<?php

namespace App\Core;

class EntityPolicyResolver
{
    public function rowScope(string $entityKey, $query, EntityContext $context)
    {
        $callback = $this->getRuleCallback($entityKey, 'row_scope');
        if (!$callback) {
            return $query;
        }
        return $callback($query, $context);
    }

    public function canViewRow(string $entityKey, array $row, EntityContext $context): bool
    {
        $callback = $this->getRuleCallback($entityKey, 'can_view_row');
        if (!$callback) {
            return true;
        }
        return (bool) $callback($row, $context);
    }

    public function canEditRow(string $entityKey, array $row, EntityContext $context): bool
    {
        $callback = $this->getRuleCallback($entityKey, 'can_edit_row');
        if (!$callback) {
            return true;
        }
        return (bool) $callback($row, $context);
    }

    public function fieldRules(string $entityKey, EntityContext $context): array
    {
        $callback = $this->getRuleCallback($entityKey, 'field_rules');
        $defaults = $this->defaultFieldRules();
        if (!$callback) {
            return $defaults;
        }
        $rules = $callback($context);
        // Ensure stable shape and array values
        $result = [
            'readonly' => [],
            'hidden' => [],
            'required' => [],
        ];
        if (is_array($rules)) {
            foreach ($result as $key => $_) {
                if (isset($rules[$key]) && is_array($rules[$key])) {
                    $result[$key] = $rules[$key];
                }
            }
        }
        return $result;
    }

    protected function getRuleCallback(string $entityKey, string $rule): ?callable
    {
        $definition = EntityRegistry::get($entityKey);
        $rules = $definition['permissions']['rules'] ?? [];
        $callback = $rules[$rule] ?? null;
        if (!$callback) {
            return null;
        }
        if (is_array($callback)) {
            if (isset($callback[0]) && is_array($callback[0])) {
                return [$callback[0][0], $callback[0][1]];
            }
            if (isset($callback[0]) && is_string($callback[0]) && isset($callback[1])) {
                return $callback;
            }
        }
        return null;
    }

    protected function defaultFieldRules(): array
    {
        return [
            'readonly' => [],
            'hidden' => [],
            'required' => [],
        ];
    }
}
