<?php

namespace App\Core;

class EntityStore
{
    private EntityRegistry $registry;
    private object $gateway;
    private EntityHookRunner $hookRunner;
    private EntityPolicyResolver $policyResolver;
    private EntitySlaEngine $slaEngine;
    private EntityWorkflowGuard $workflowGuard;
    private EntityDefinitionValidator $validator;

    public function __construct(
        EntityRegistry $registry,
        object $gateway,
        EntityHookRunner $hookRunner,
        EntityPolicyResolver $policyResolver,
        EntitySlaEngine $slaEngine,
        EntityWorkflowGuard $workflowGuard,
        EntityDefinitionValidator $validator
    ) {
        $this->registry = $registry;
        $this->gateway = $gateway;
        $this->hookRunner = $hookRunner;
        $this->policyResolver = $policyResolver;
        $this->slaEngine = $slaEngine;
        $this->workflowGuard = $workflowGuard;
        $this->validator = $validator;
    }

    public function create(string $entityKey, array $data, EntityContext $context): int
    {
        $data = $this->validator->validateCreate($entityKey, $data, $context);

        $this->hookRunner->run('before_create', $entityKey, $data, null, $context);

        $id = $this->gateway->create($entityKey, $data);

        $this->hookRunner->run('after_create', $entityKey, $data, null, $context);

        $this->gateway->update($entityKey, $id, $data);

        return $id;
    }

    public function update(string $entityKey, int|string $id, array $data, EntityContext $context, ?array $original = null): bool
    {
        $id = (int) $id;

        if ($original === null) {
            $original = $this->gateway->read($entityKey, $id);
        }

        if (!$this->policyResolver->canEditRow($entityKey, $original, $context)) {
            throw new \RuntimeException("Edit not allowed for this row");
        }

        $definition = EntityRegistry::get($entityKey);
        if ($definition && isset($definition['workflow']['field'])) {
            $workflowField = $definition['workflow']['field'];
            if (isset($data[$workflowField]) && isset($original[$workflowField])) {
                $fromState = (string) $original[$workflowField];
                $toState = (string) $data[$workflowField];
                if ($fromState !== $toState) {
                    $this->workflowGuard->validateTransition($entityKey, $fromState, $toState);
                }
            }
        }

        $data = $this->validator->validateUpdate($entityKey, $id, $data, $context, $original);

        $this->hookRunner->run('before_update', $entityKey, $data, $original, $context);

        $this->gateway->update($entityKey, $id, $data);

        $this->hookRunner->run('after_update', $entityKey, $data, $original, $context);

        $this->gateway->update($entityKey, $id, $data);

        return true;
    }

    public function delete(string $entityKey, int|string $id, EntityContext $context): bool
    {
        $id = (int) $id;

        $original = $this->gateway->read($entityKey, $id);

        $this->hookRunner->run('before_delete', $entityKey, $original, null, $context);

        $result = $this->gateway->delete($entityKey, $id);

        $this->hookRunner->run('after_delete', $entityKey, $original, null, $context);

        return $result;
    }

    public function find(string $entityKey, int|string $id, EntityContext $context): ?array
    {
        $id = (int) $id;
        return $this->gateway->read($entityKey, $id);
    }
}
