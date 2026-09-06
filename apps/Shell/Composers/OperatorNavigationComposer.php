<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

final class OperatorNavigationComposer
{
    /**
     * @param callable(string,string,array<string,mixed>):string $tr
     * @param callable(string,array<string,mixed>):string $operatorUrl
     * @return array<int,array{label:string,url:string}>
     */
    public static function composeTopHeaderMenu(callable $tr, callable $operatorUrl): array
    {
        return [
            ['label' => $tr('operator.surface.workspace', 'Workspace', []), 'url' => $operatorUrl('dashboard', [])],
            ['label' => $tr('operator.surface.notifications', 'Notifications', []), 'url' => $operatorUrl('notifications', [])],
            ['label' => $tr('operator.surface.shift_context', 'Shift Context', []), 'url' => $operatorUrl('recent', [])],
            ['label' => $tr('operator.surface.profile', 'Profile', []), 'url' => $operatorUrl('account', [])],
        ];
    }

    /**
     * @param callable(string,string,array<string,mixed>):string $tr
     * @param callable(string,array<string,mixed>):string $operatorUrl
     * @return array<int,array{label:string,url:string}>
     */
    public static function composeMobileTopNav(callable $tr, callable $operatorUrl): array
    {
        return [
            ['label' => $tr('common.today', 'Today', []), 'url' => $operatorUrl('dashboard', [])],
            ['label' => $tr('operator.surface.queue', 'Queue', []), 'url' => $operatorUrl('work-entry', ['action' => 'update'])],
            ['label' => $tr('nav.alerts', 'Alerts', []), 'url' => $operatorUrl('notifications', [])],
            ['label' => $tr('operator.surface.context', 'Context', []), 'url' => $operatorUrl('account', [])],
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @param callable(string,string,array<string,mixed>):string $tr
     * @return array<int,array{label:string,url:string,is_active:bool}>
     */
    public static function composeWorkflowEntryNav(array $query, string $username, callable $tr): array
    {
        $focus = strtolower(trim((string)($query['focus'] ?? '')));
        $action = strtolower(trim((string)($query['action'] ?? '')));
        $user = rawurlencode($username);

        return [
            [
                'label' => $tr('operator.surface.workflow.plan', 'Plan', []),
                'url' => '/u/' . $user . '/production',
                'is_active' => ($focus === 'production'),
            ],
            [
                'label' => $tr('operator.surface.workflow.entry_form', 'Entry Form', []),
                'url' => '/u/' . $user . '/work-entry?action=update',
                'is_active' => ($focus === 'work-entry' && $action === 'update'),
            ],
            [
                'label' => $tr('operator.surface.workflow.fulfillment', 'Fulfillment', []),
                'url' => '/u/' . $user . '/fulfillment',
                'is_active' => in_array($focus, ['dispatch', 'dispatch-detail', 'fulfillment'], true),
            ],
        ];
    }
}
