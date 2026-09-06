<?php

namespace App\Core;

class EntityDefinitionValidator
{
    public function validateCreate(string $entityKey, array $data, EntityContext $context): array
    {
        $definition = EntityRegistry::get($entityKey);
        if (!$definition) {
            throw new \InvalidArgumentException("Unknown entity: {$entityKey}");
        }

        $data = $this->applyDefaults($definition, $data);
        $this->validateHiddenFields($entityKey, $data, $context);
        $this->validateRequiredFields($entityKey, $data, $context);

        return $data;
    }

    public function validateUpdate(string $entityKey, int $id, array $data, EntityContext $context, ?array $original = null): array
    {
        $definition = EntityRegistry::get($entityKey);
        if (!$definition) {
            throw new \InvalidArgumentException("Unknown entity: {$entityKey}");
        }

        $this->validateReadonlyFields($entityKey, $data, $context);

        return $data;
    }

    protected function applyDefaults(array $definition, array $data): array
    {
        $fields = $definition['fields'] ?? [];
        foreach ($fields as $fieldName => $fieldDef) {
            if (!isset($data[$fieldName]) && isset($fieldDef['default'])) {
                $data[$fieldName] = $fieldDef['default'];
            }
        }
        return $data;
    }

    protected function validateHiddenFields(string $entityKey, array $data, EntityContext $context): void
    {
        $definition = EntityRegistry::get($entityKey);
        $fields = $definition['fields'] ?? [];

        foreach ($fields as $fieldName => $fieldDef) {
            if (!empty($fieldDef['hidden']) && isset($data[$fieldName])) {
                throw new \InvalidArgumentException("Field '{$fieldName}' is hidden and cannot be set directly");
            }
        }
    }

    protected function validateReadonlyFields(string $entityKey, array $data, EntityContext $context): void
    {
        $definition = EntityRegistry::get($entityKey);
        $fields = $definition['fields'] ?? [];

        foreach ($fields as $fieldName => $fieldDef) {
            if (!empty($fieldDef['readonly']) && isset($data[$fieldName])) {
                throw new \InvalidArgumentException("Field '{$fieldName}' is readonly");
            }
        }
    }

    protected function validateRequiredFields(string $entityKey, array $data, EntityContext $context): void
    {
        $definition = EntityRegistry::get($entityKey);
        $fields = $definition['fields'] ?? [];

        foreach ($fields as $fieldName => $fieldDef) {
            if (!empty($fieldDef['required']) && !isset($data[$fieldName]) && !isset($fieldDef['default'])) {
                throw new \InvalidArgumentException("Required field '{$fieldName}' is missing");
            }
        }
    }

    public function getFieldDefinition(string $entityKey, string $fieldName): ?array
    {
        $definition = EntityRegistry::get($entityKey);
        if (!$definition) {
            return null;
        }
        return $definition['fields'][$fieldName] ?? null;
    }

    public function getPrimaryKey(string $entityKey): ?string
    {
        $definition = EntityRegistry::get($entityKey);
        if (!$definition) {
            return null;
        }

        foreach ($definition['fields'] ?? [] as $fieldName => $fieldDef) {
            if (!empty($fieldDef['primary'])) {
                return $fieldName;
            }
        }

        return null;
    }
}
