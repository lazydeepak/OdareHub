<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

final class OperatorFocusLabelComposer
{
    /**
     * @param array<string,mixed> $query
     * @param callable(string,string,array<string,mixed>):string $tr
     */
    public static function resolveFromQuery(array $query, callable $tr): string
    {
        return self::resolve((string)($query['focus'] ?? ''), $tr);
    }

    public static function normalizeFocus(string $focus): string
    {
        return strtolower(trim($focus));
    }

    /**
     * @param callable(string,string,array<string,mixed>):string $tr
     */
    public static function resolve(string $focus, callable $tr): string
    {
        $labels = [
            'dashboard' => $tr('wrapper.operator.focus.dashboard', 'Dashboard', []),
            'production' => $tr('wrapper.operator.focus.production', 'Production', []),
            'demand' => $tr('wrapper.operator.focus.demand', 'Demand', []),
            'orders' => $tr('wrapper.operator.focus.orders', 'Daily Orders', []),
            'processing' => $tr('wrapper.operator.focus.processing', 'Processing', []),
            'assembly' => $tr('wrapper.operator.focus.assembly', 'Assembly', []),
            'dispatch' => $tr('wrapper.operator.focus.dispatch', 'Dispatch', []),
            'dispatch-detail' => $tr('wrapper.operator.focus.dispatch_detail', 'Dispatch Detail', []),
            'dispatch-adapter' => $tr('wrapper.operator.focus.dispatch_adapter', 'Dispatch', []),
            'qc' => $tr('wrapper.operator.focus.qc', 'Quality Control', []),
            'fulfillment' => $tr('wrapper.operator.focus.fulfillment', 'Fulfillment', []),
            'preparation' => $tr('wrapper.operator.focus.preparation', 'Preparation', []),
            'parts' => $tr('wrapper.operator.focus.parts', 'Parts', []),
            'parts-detail' => $tr('wrapper.operator.focus.parts_detail', 'Part Detail', []),
            'machines' => $tr('wrapper.operator.focus.machines', 'Machines', []),
            'materials' => $tr('wrapper.operator.focus.materials', 'Materials', []),
            'sbaio' => $tr('wrapper.operator.focus.sbaio', 'SBAIO Workspace', []),
            'hospitality' => $tr('wrapper.operator.focus.hospitality', 'Hospitality', []),
            'coverage' => $tr('wrapper.operator.focus.coverage', 'Coverage', []),
            'account' => $tr('wrapper.operator.focus.account', 'My Account', []),
            'notifications' => $tr('wrapper.operator.focus.notifications', 'Notifications', []),
            'messages' => $tr('wrapper.operator.focus.messages', 'Messages', []),
            'work-entry' => $tr('wrapper.operator.focus.work_entry', 'Work Entry', []),
            'data-exchange' => $tr('wrapper.operator.focus.data_exchange', 'Data Exchange', []),
            'critical' => $tr('wrapper.operator.focus.critical', 'Critical Items', []),
            'recent' => $tr('wrapper.operator.focus.recent', 'Recent', []),
        ];

        return $labels[self::normalizeFocus($focus)] ?? '';
    }
}
