<?php

namespace App\Core;

use App\Core\EntityContext;

class EntitySlaEngine
{
    public static function evaluate(array $slaConfig, array $row, EntityContext $context, ?int $now = null): array
    {
        $now = $now ?? time();
        $result = self::defaultResult($slaConfig['enabled'] ?? false);

        if (empty($slaConfig['enabled'])) {
            return $result;
        }

        $statusField = $slaConfig['status_field'] ?? 'status';
        $deadlineField = $slaConfig['deadline_field'] ?? 'deadline_at';
        $dueSoonMinutes = $slaConfig['due_soon_minutes'] ?? 120;
        $breachLabels = $slaConfig['breach_labels'] ?? [];
        $rules = $slaConfig['rules'] ?? [];

        $status = $row[$statusField] ?? null;
        $rule = $rules[$status] ?? null;
        if (!$rule || empty($row[$deadlineField])) {
            $result['enabled'] = true;
            return $result;
        }

        $deadlineStr = $row[$deadlineField];
        $deadlineTs = strtotime($deadlineStr);
        if (!$deadlineTs) {
            $result['enabled'] = true;
            return $result;
        }

        $secondsUntilDeadline = $deadlineTs - $now;
        $overdue = $secondsUntilDeadline < 0;
        $dueSoon = !$overdue && $secondsUntilDeadline <= $dueSoonMinutes * 60;
        $escalated = false;
        $state = 'ok';

        if ($overdue) {
            $state = 'breached';
            if (!empty($rule['escalation'])) {
                $escalated = true;
            }
        } elseif ($dueSoon) {
            $state = 'due_soon';
        }

        $label = $breachLabels[$state] ?? ucfirst(str_replace('_', ' ', $state));

        $result['enabled'] = true;
        $result['state'] = $state;
        $result['label'] = $label;
        $result['deadline_at'] = $deadlineStr;
        $result['age_seconds'] = $overdue ? max(0, $now - $deadlineTs) : 0;
        $result['rule'] = $rule;
        $result['escalated'] = $escalated;

        return $result;
    }

    public static function getSlaRules(array $slaConfig): array
    {
        return $slaConfig['rules'] ?? [];
    }

    protected static function defaultResult(bool $enabled = false): array
    {
        return [
            'enabled' => $enabled,
            'state' => 'none',
            'label' => null,
            'deadline_at' => null,
            'age_seconds' => 0,
            'rule' => null,
            'escalated' => false,
        ];
    }
}
