<?php
declare(strict_types=1);

namespace Apps\Studio\Workflow;

use Apps\Studio\Authorization\StudioAuthorizationService;

require_once __DIR__ . '/../Authorization/StudioAuthorizationService.php';

final class WorkflowService
{
    /**
     * @param array<string,mixed> $context
     */
    public static function canTransition(string $entity, string $from, string $to): bool
    {
        $entityConfig = self::entityConfig($entity);
        if ($entityConfig === []) {
            return false;
        }

        $fromState = self::normalizeState($from);
        $toState = self::normalizeState($to);
        if ($fromState === '' || $toState === '') {
            return false;
        }

        $states = array_values(array_filter((array)($entityConfig['states'] ?? []), 'is_string'));
        if (!in_array($fromState, $states, true) || !in_array($toState, $states, true)) {
            return false;
        }

        $transitions = is_array($entityConfig['transitions'] ?? null) ? $entityConfig['transitions'] : [];
        $allowed = array_values(array_filter((array)($transitions[$fromState] ?? []), 'is_string'));
        return in_array($toState, $allowed, true);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function canUserTransition(string $entity, string $from, string $to, array $context): bool
    {
        if (!self::canTransition($entity, $from, $to)) {
            return false;
        }

        $entityConfig = self::entityConfig($entity);
        if ($entityConfig === []) {
            return false;
        }

        $transitionRoles = is_array($entityConfig['transition_roles'] ?? null) ? $entityConfig['transition_roles'] : [];
        $key = self::normalizeState($from) . ':' . self::normalizeState($to);
        $roles = array_values(array_filter((array)($transitionRoles[$key] ?? []), 'is_string'));
        if ($roles === []) {
            return false;
        }

        $role = StudioAuthorizationService::resolveRole($context);
        return in_array($role, $roles, true);
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,string>
     */
    public static function allowedNextStates(string $entity, string $from, array $context): array
    {
        $entityConfig = self::entityConfig($entity);
        if ($entityConfig === []) {
            return [];
        }

        $fromState = self::normalizeState($from);
        $transitions = is_array($entityConfig['transitions'] ?? null) ? $entityConfig['transitions'] : [];
        $candidates = array_values(array_filter((array)($transitions[$fromState] ?? []), 'is_string'));
        if ($candidates === []) {
            return [];
        }

        $allowed = [];
        foreach ($candidates as $candidate) {
            $candidateState = self::normalizeState($candidate);
            if ($candidateState === '') {
                continue;
            }
            if (self::canUserTransition($entity, $fromState, $candidateState, $context)) {
                $allowed[] = $candidateState;
            }
        }

        return array_values(array_unique($allowed));
    }

    public static function isValidState(string $entity, string $state): bool
    {
        $entityConfig = self::entityConfig($entity);
        if ($entityConfig === []) {
            return false;
        }

        $states = array_values(array_filter((array)($entityConfig['states'] ?? []), 'is_string'));
        return in_array(self::normalizeState($state), $states, true);
    }

    /** @return array<string,mixed> */
    private static function config(): array
    {
        /** @var array<string,mixed> $config */
        $config = require __DIR__ . '/WorkflowConfig.php';
        return $config;
    }

    /** @return array<string,mixed> */
    private static function entityConfig(string $entity): array
    {
        $entityKey = strtolower(trim($entity));
        if ($entityKey === '') {
            return [];
        }

        $config = self::config();
        $entityConfig = $config[$entityKey] ?? null;
        return is_array($entityConfig) ? $entityConfig : [];
    }

    private static function normalizeState(string $state): string
    {
        return strtolower(trim($state));
    }
}
