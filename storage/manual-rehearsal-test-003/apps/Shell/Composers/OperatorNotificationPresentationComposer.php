<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

use Apps\Shell\Services\OperatorLayerWidgetService;
use Plugins\Base\Services\NotificationService;

final class OperatorNotificationPresentationComposer
{
    public static function mapSeverityToFocus(string $severity): string
    {
        $normalized = strtolower(trim($severity));
        if ($normalized === NotificationService::SEVERITY_CRITICAL) {
            return 'critical';
        }
        if (in_array($normalized, [NotificationService::SEVERITY_WARNING, NotificationService::SEVERITY_ACTION_REQUIRED, NotificationService::SEVERITY_APPROVAL_REQUIRED], true)) {
            return 'warning';
        }

        return 'info';
    }

    public static function iconForEvent(string $eventType, string $severity): string
    {
        return match ($eventType) {
            NotificationService::EVENT_ADMIN_MESSAGE => '💬',
            NotificationService::EVENT_ADMIN_GUIDE => '📣',
            NotificationService::EVENT_ASSIGNED => '🧩',
            NotificationService::EVENT_NEWLY_READY => '✅',
            NotificationService::EVENT_WORKFLOW_APPROVED => '✔️',
            NotificationService::EVENT_WORKFLOW_REJECTED => '✖️',
            NotificationService::EVENT_WORKFLOW_REOPENED => '🔁',
            NotificationService::EVENT_WORKFLOW_BLOCKED => '⛔',
            NotificationService::EVENT_WORKFLOW_UNBLOCKED => '🟢',
            NotificationService::EVENT_SLA_WARNING => '⏰',
            NotificationService::EVENT_SLA_OVERDUE => '⚠️',
            NotificationService::EVENT_SLA_BREACH => '🔥',
            NotificationService::EVENT_ESCALATED => '🚨',
            default => ($severity === 'critical' ? '🚨' : ($severity === 'warning' ? '⚠️' : 'ℹ️')),
        };
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function normalizeUrl(string $actionUrl, string $fallback, array $context, string $username): string
    {
        $trimmed = trim($actionUrl);
        if ($trimmed === '') {
            return $fallback;
        }

        if (!str_starts_with($trimmed, '/')) {
            return $fallback;
        }

        $path = trim((string)parse_url($trimmed, PHP_URL_PATH));
        if ($path === '') {
            return $fallback;
        }

        if (str_starts_with($path, '/u/')) {
            return $trimmed;
        }

        if ($path === '/me' && strtolower(trim((string)($context['authority_role'] ?? 'app_user'))) === 'platform_admin') {
            return $trimmed;
        }

        $normalized = OperatorLayerWidgetService::normalizeOperatorWidgetUrl($trimmed, array_merge(
            $context,
            ['username' => $username]
        ));

        if (str_starts_with($normalized, '/u/')) {
            return $normalized;
        }

        return $fallback;
    }
}
