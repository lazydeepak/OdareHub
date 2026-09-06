<?php

namespace App\Core;

class EntityHookRunner
{
    public function run(
        string $stage,
        string $entityKey,
        array &$data,
        ?array $original,
        EntityContext $context
    ): void {
        $hooks = $this->getHooksForStage($entityKey, $stage);

        if ($hooks === []) {
            return;
        }

        foreach ($hooks as $hook) {
            [$class, $method] = $hook;
            $class::$method($data, $original, $context);
        }
    }

    protected function getHooksForStage(string $entityKey, string $stage): array
    {
        $definition = EntityRegistry::get($entityKey);
        $hooks = $definition['hooks'] ?? [];
        if (!is_array($hooks) || !array_key_exists($stage, $hooks)) {
            return [];
        }
        $stageHooks = $hooks[$stage];
        return is_array($stageHooks) ? $stageHooks : [];
    }
}
