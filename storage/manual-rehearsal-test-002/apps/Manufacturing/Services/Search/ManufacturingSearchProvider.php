<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services\Search;

use App\Core\AclPolicy;
use App\Core\DB;
use Platform\Search\SearchProviderContext;
use Platform\Search\SearchProviderInterface;
use Plugins\Workflow\Services\WorkflowRegistry;

final class ManufacturingSearchProvider implements SearchProviderInterface
{
    private const RESULT_LIMIT = 40;

    public function groupLabels(): array
    {
        return [
            'machines' => $this->label('module.machines.search_group', 'Machines'),
            'parts' => $this->label('module.products.search_group', 'Parts'),
            'orders' => $this->label('module.daily_orders.search_group', 'Orders'),
            'plans' => $this->label('module.production_plans.search_group', 'Plans'),
            'entries' => $this->label('module.production_entries.search_group', 'Entries'),
            'charts' => 'Charts',
            'workflow_actions' => 'Workflow Actions',
        ];
    }

    public function groupPriorities(): array
    {
        return [
            'default' => [
                'machines' => 10,
                'parts' => 20,
                'orders' => 30,
                'plans' => 40,
                'entries' => 50,
                'charts' => 90,
                'workflow_actions' => 110,
            ],
        ];
    }

    private function label(string $key, string $fallback): string
    {
        if (function_exists('t')) {
            $translated = trim((string)t($key));
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }
        return $fallback;
    }

    public function search(string $query, SearchProviderContext $context): array
    {
        $results = [];
        if ($context->pathAllowed('/machines/edit')) {
            $results['machines'] = $this->searchMachines($query, $context);
        }
        if ($context->pathAllowed('/products')) {
            $results['parts'] = $this->searchParts($query, $context);
        }
        if ($context->pathAllowed('/daily-orders/360')) {
            $results['orders'] = $this->searchOrders($query, $context);
        }
        if ($context->pathAllowed('/production-plans/edit')) {
            $results['plans'] = $this->searchPlans($query, $context);
        }
        $entries = $this->searchEntries($query, $context);
        if ($entries !== []) {
            $results['entries'] = $entries;
        }
        $charts = $this->searchCharts($query, $context);
        if ($charts !== []) {
            $results['charts'] = $charts;
        }
        $workflowActions = $this->searchWorkflowActions($query, $context);
        if ($workflowActions !== []) {
            $results['workflow_actions'] = $workflowActions;
        }
        return $results;
    }

    /** @return array<int,array<string,mixed>> */
    private function searchMachines(string $query, SearchProviderContext $context): array
    {
        try {
            $expressions = ['CAST(id AS CHAR)', 'machine_no', 'machine_name', 'section', 'status', 'notes'];
            $rows = $this->queryRows(
                $query,
                $expressions,
                'SELECT id, machine_no, machine_name, section, status FROM machines',
                'id',
                $context,
                'machines',
                ['id' => 'machine_ids', 'department_code' => 'department_code', 'branch_code' => 'branch_code']
            );
            $items = [];
            foreach ($rows as $row) {
                $id = trim((string)($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $number = trim((string)($row['machine_no'] ?? ''));
                $name = trim((string)($row['machine_name'] ?? ''));
                $section = trim((string)($row['section'] ?? ''));
                $items[] = [
                    'id' => $id,
                    'label' => trim(($number !== '' ? $number : 'Machine #' . $id) . ($name !== '' ? ' - ' . $name : '')),
                    'status' => trim((string)($row['status'] ?? 'active')),
                    'url' => '/machines/edit?id=' . urlencode($id),
                    'meta' => $section !== '' ? 'Section ' . $section : null,
                ];
            }
            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function searchParts(string $query, SearchProviderContext $context): array
    {
        try {
            $expressions = $context->tableExpressions('products', [
                'id', 'parts_number', 'parts_name', 'model', 'producer', 'notes',
                'activity_type', 'supply_mode', 'fulfillment_mode',
            ]);
            $rows = $this->queryRows(
                $query,
                $expressions,
                'SELECT id, parts_number, parts_name, is_active, model, producer FROM products',
                'id',
                $context,
                'products',
                ['id' => 'part_ids', 'department_code' => 'department_code', 'branch_code' => 'branch_code']
            );
            $items = [];
            foreach ($rows as $row) {
                $id = trim((string)($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $number = trim((string)($row['parts_number'] ?? ''));
                $name = trim((string)($row['parts_name'] ?? ''));
                $model = trim((string)($row['model'] ?? ''));
                $producer = trim((string)($row['producer'] ?? ''));
                $items[] = [
                    'id' => $id,
                    'label' => $number !== '' ? $number : ($name ?: 'Unnamed Part'),
                    'status' => ((int)($row['is_active'] ?? 1) === 1) ? 'active' : 'inactive',
                    'url' => '/products?id=' . urlencode($id),
                    'meta' => trim(($name !== '' && $name !== $number ? $name : '') . ($model !== '' ? ' • ' . $model : '') . ($producer !== '' ? ' • ' . $producer : '')),
                ];
            }
            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function searchOrders(string $query, SearchProviderContext $context): array
    {
        try {
            $expressions = $context->tableExpressions('daily_orders', [
                'id', 'customer_name', 'status', 'order_date', 'required_date', 'notes', 'coverage_status',
            ]);
            $rows = $this->queryRows(
                $query,
                $expressions,
                'SELECT id, customer_name, status, order_date, required_date, coverage_status FROM daily_orders',
                'id',
                $context,
                'daily_orders',
                [
                    'machine_id' => 'machine_ids', 'product_id' => 'part_ids', 'part_id' => 'part_ids',
                    'department_code' => 'department_code', 'branch_code' => 'branch_code',
                ]
            );
            $items = [];
            foreach ($rows as $row) {
                $id = trim((string)($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $customer = trim((string)($row['customer_name'] ?? ''));
                $orderDate = trim((string)($row['order_date'] ?? ''));
                $requiredDate = trim((string)($row['required_date'] ?? ''));
                $coverage = trim((string)($row['coverage_status'] ?? ''));
                $items[] = [
                    'id' => $id,
                    'label' => 'Daily Order #' . $id,
                    'status' => trim((string)($row['status'] ?? 'open')),
                    'url' => '/daily-orders/360?id=' . urlencode($id),
                    'meta' => trim($customer . ($orderDate !== '' ? ' • ' . $orderDate : '') . ($requiredDate !== '' ? ' • due ' . $requiredDate : '') . ($coverage !== '' ? ' • ' . $coverage : '')),
                ];
            }
            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function searchPlans(string $query, SearchProviderContext $context): array
    {
        try {
            $expressions = [
                'CAST(pp.id AS CHAR)', 'pp.plan_type', 'pp.status', 'pp.plan_date', 'pp.workflow_state',
                'pp.approval_status', 'pp.reference_doctype', 'pp.reference_name', 'pp.notes',
                'pp.override_reason', 'pp.reopen_reason', 'm.machine_no', 'm.machine_name', 'm.section',
            ];
            [$scopeSql, $scopeParams] = $context->scopeClause('production_plans', [
                'machine_id' => 'machine_ids', 'product_id' => 'part_ids', 'part_id' => 'part_ids',
                'department_code' => 'department_code', 'branch_code' => 'branch_code',
            ], 'pp');
            $baseSql = 'SELECT pp.id, pp.plan_type, pp.status, pp.plan_date, pp.planned_qty, pp.workflow_state, pp.approval_status,
                               m.machine_no, m.machine_name
                        FROM production_plans pp LEFT JOIN machines m ON m.id = pp.machine_id';
            $rows = $this->runQuery($query, $expressions, $baseSql, 'pp.id', $scopeSql, $scopeParams, $context);
            $items = [];
            foreach ($rows as $row) {
                $id = trim((string)($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $type = trim((string)($row['plan_type'] ?? 'production'));
                $date = trim((string)($row['plan_date'] ?? ''));
                $qty = (string)($row['planned_qty'] ?? '');
                $machine = trim((string)($row['machine_no'] ?? '') . ' ' . (string)($row['machine_name'] ?? ''));
                $workflow = trim((string)($row['workflow_state'] ?? ''));
                $approval = trim((string)($row['approval_status'] ?? ''));
                $items[] = [
                    'id' => $id,
                    'label' => 'Production Plan #' . $id,
                    'status' => trim((string)($row['status'] ?? 'open')),
                    'url' => '/production-plans/edit?id=' . urlencode($id),
                    'meta' => trim(ucfirst($type) . ' • ' . $date . ($qty !== '' ? ' • Qty ' . $qty : '') . ($machine !== '' ? ' • ' . $machine : '') . ($workflow !== '' ? ' • ' . $workflow : '') . ($approval !== '' ? ' • ' . $approval : '')),
                ];
            }
            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function searchEntries(string $query, SearchProviderContext $context): array
    {
        $items = [];
        if ($context->pathAllowed('/production-entries/edit')) {
            $items = array_merge($items, $this->searchProductionEntries($query, $context));
        }
        if ($context->pathAllowed('/qc-entries/edit')) {
            $items = array_merge($items, $this->searchQcEntries($query, $context));
        }
        if ($context->pathAllowed('/dispatch-entries/edit')) {
            $items = array_merge($items, $this->searchDispatchEntries($query, $context));
        }
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private function searchProductionEntries(string $query, SearchProviderContext $context): array
    {
        try {
            $expressions = [
                'CAST(pe.id AS CHAR)', 'pe.status', 'pe.production_date', 'pe.`shift`', 'pe.notes',
                'm.machine_no', 'm.machine_name', 'm.section',
            ];
            [$scopeSql, $scopeParams] = $context->scopeClause('production_entries', [
                'machine_id' => 'machine_ids', 'product_id' => 'part_ids', 'part_id' => 'part_ids',
                'department_code' => 'department_code', 'branch_code' => 'branch_code',
            ], 'pe');
            $baseSql = 'SELECT pe.id, pe.status, pe.production_date, pe.`shift` AS shift_name, pe.notes,
                               m.machine_no, m.machine_name
                        FROM production_entries pe LEFT JOIN machines m ON m.id = pe.machine_id';
            $rows = $this->runQuery($query, $expressions, $baseSql, 'pe.id', $scopeSql, $scopeParams, $context, 13);
            $items = [];
            foreach ($rows as $row) {
                $id = trim((string)($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $date = trim((string)($row['production_date'] ?? ''));
                $shift = trim((string)($row['shift_name'] ?? ''));
                $machine = trim((string)($row['machine_no'] ?? '') . ' ' . (string)($row['machine_name'] ?? ''));
                $notes = trim((string)($row['notes'] ?? ''));
                $items[] = [
                    'id' => $id,
                    'label' => 'Production Entry #' . $id,
                    'status' => trim((string)($row['status'] ?? 'open')),
                    'url' => '/production-entries/edit?id=' . urlencode($id),
                    'meta' => trim('Production Entry' . ($date !== '' ? ' • ' . $date : '') . ($shift !== '' ? ' • ' . $shift : '') . ($machine !== '' ? ' • ' . $machine : '') . ($notes !== '' ? ' • ' . $notes : '')),
                ];
            }
            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function searchQcEntries(string $query, SearchProviderContext $context): array
    {
        try {
            $expressions = $context->tableExpressions('qc_entries', [
                'id', 'status', 'qc_type', 'remarks', 'workflow_state', 'approval_status',
                'approval_note', 'reopen_reason', 'override_reason',
            ]);
            $rows = $this->queryRows(
                $query,
                $expressions,
                'SELECT id, status, qc_type, remarks, workflow_state, approval_status FROM qc_entries',
                'id',
                $context,
                'qc_entries',
                [
                    'machine_id' => 'machine_ids', 'product_id' => 'part_ids', 'part_id' => 'part_ids',
                    'department_code' => 'department_code', 'branch_code' => 'branch_code',
                ],
                13
            );
            $items = [];
            foreach ($rows as $row) {
                $id = trim((string)($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $type = trim((string)($row['qc_type'] ?? ''));
                $workflow = trim((string)($row['workflow_state'] ?? ''));
                $approval = trim((string)($row['approval_status'] ?? ''));
                $remarks = trim((string)($row['remarks'] ?? ''));
                $items[] = [
                    'id' => $id,
                    'label' => 'QC Entry #' . $id,
                    'status' => trim((string)($row['status'] ?? 'open')),
                    'url' => '/qc-entries/edit?id=' . urlencode($id),
                    'meta' => trim('QC Entry' . ($type !== '' ? ' • ' . $type : '') . ($workflow !== '' ? ' • ' . $workflow : '') . ($approval !== '' ? ' • ' . $approval : '') . ($remarks !== '' ? ' • ' . $remarks : '')),
                ];
            }
            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function searchDispatchEntries(string $query, SearchProviderContext $context): array
    {
        try {
            $expressions = $context->tableExpressions('dispatch_entries', [
                'id', 'dispatch_status', 'destination', 'destination_code', 'dispatch_type', 'remarks',
                'status_reason', 'status_note', 'third_party_reference', 'dispatch_mode', 'delivery_flow',
                'source_type', 'workflow_state', 'approval_status', 'approval_note', 'reopen_reason', 'override_reason',
            ]);
            $rows = $this->queryRows(
                $query,
                $expressions,
                'SELECT id, dispatch_status, destination, destination_code, dispatch_type, dispatch_mode, delivery_flow FROM dispatch_entries',
                'id',
                $context,
                'dispatch_entries',
                [
                    'machine_id' => 'machine_ids', 'product_id' => 'part_ids', 'part_id' => 'part_ids',
                    'department_code' => 'department_code', 'branch_code' => 'branch_code',
                ],
                13
            );
            $items = [];
            foreach ($rows as $row) {
                $id = trim((string)($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $destination = trim((string)($row['destination'] ?? ''));
                $code = trim((string)($row['destination_code'] ?? ''));
                $type = trim((string)($row['dispatch_type'] ?? ''));
                $mode = trim((string)($row['dispatch_mode'] ?? ''));
                $flow = trim((string)($row['delivery_flow'] ?? ''));
                $items[] = [
                    'id' => $id,
                    'label' => 'Dispatch Entry #' . $id,
                    'status' => trim((string)($row['dispatch_status'] ?? 'open')),
                    'url' => '/dispatch-entries/edit?id=' . urlencode($id),
                    'meta' => trim('Dispatch Entry' . ($destination !== '' ? ' • ' . $destination : '') . ($code !== '' ? ' • ' . $code : '') . ($type !== '' ? ' • ' . $type : '') . ($mode !== '' ? ' • ' . $mode : '') . ($flow !== '' ? ' • ' . $flow : '')),
                ];
            }
            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function searchCharts(string $query, SearchProviderContext $context): array
    {
        $tokens = array_values(array_unique(array_map(
            'strval',
            (array)($context->scopeContext['user_context']['chart_access'] ?? [])
        )));
        if ($tokens === []) {
            return [];
        }

        $catalog = [
            'throughput_trend' => [
                'label' => 'Throughput Trend',
                'url' => '/apps/manufacturing/production-dashboard',
                'meta' => 'Production throughput analytics',
            ],
            'plan_vs_actual' => [
                'label' => 'Plan vs Actual',
                'url' => '/apps/manufacturing/production-dashboard',
                'meta' => 'Planning adherence chart',
            ],
            'assembly_output' => [
                'label' => 'Assembly Output',
                'url' => '/apps/manufacturing/assembly-dashboard',
                'meta' => 'Assembly productivity chart',
            ],
            'qc_pass_rate' => [
                'label' => 'QC Pass Rate',
                'url' => '/apps/manufacturing/qc-dashboard',
                'meta' => 'Quality performance chart',
            ],
            'dispatch_volume' => [
                'label' => 'Dispatch Volume',
                'url' => '/apps/manufacturing/dispatch-dashboard',
                'meta' => 'Dispatch throughput chart',
            ],
        ];

        $items = [];
        foreach ($tokens as $token) {
            $key = strtolower(trim($token));
            if ($key === '' || !isset($catalog[$key])) {
                continue;
            }
            $definition = $catalog[$key];
            $url = (string)$definition['url'];
            if (!$context->pathAllowed($url)) {
                continue;
            }
            if (!$context->matches($query, [
                (string)$definition['label'],
                $key,
                (string)$definition['meta'],
                $url,
                'chart',
                'dashboard chart',
                'analytics',
            ])) {
                continue;
            }
            $items[] = [
                'id' => 'chart:' . md5($key),
                'label' => (string)$definition['label'],
                'status' => 'available',
                'url' => $url,
                'meta' => 'Chart • ' . (string)$definition['meta'] . ' • token ' . $key,
                '_search_precedence' => 100,
            ];
        }

        return array_slice($items, 0, self::RESULT_LIMIT);
    }

    /** @return array<int,array<string,mixed>> */
    private function searchWorkflowActions(string $query, SearchProviderContext $context): array
    {
        if (!class_exists(WorkflowRegistry::class)) {
            $registryFile = APP_ROOT . '/apps/Manufacturing/modules/Workflow/Services/WorkflowRegistry.php';
            if (is_file($registryFile)) {
                require_once $registryFile;
            }
        }
        if (!class_exists(WorkflowRegistry::class)) {
            return [];
        }

        $registry = WorkflowRegistry::registry();
        if (!is_array($registry)) {
            return [];
        }

        $moduleUrl = [
            'production' => '/production-plans',
            'assembly' => '/manufacturing/assembly-queue',
            'qc' => '/qc-entries/leader',
            'dispatch' => '/dispatch-entries/leader',
        ];

        $items = [];
        foreach ($registry as $entity => $definition) {
            if (!is_array($definition)) {
                continue;
            }
            $entityLabel = trim((string)($definition['label'] ?? $entity));
            $module = trim((string)($definition['module'] ?? ''));
            $url = $moduleUrl[$module] ?? '/';
            if (!$context->pathAllowed($url)) {
                continue;
            }

            foreach ((array)($definition['transitions'] ?? []) as $action => $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                $permission = trim((string)($rule['permission'] ?? ''));
                if ($permission !== '' && method_exists(AclPolicy::class, 'can') && !AclPolicy::can($permission, $context->user)) {
                    continue;
                }

                $from = implode(', ', array_map('strval', (array)($rule['from'] ?? [])));
                $to = trim((string)($rule['to'] ?? ''));
                $label = $entityLabel . ' • ' . ucfirst((string)$action);
                $meta = trim(($from !== '' ? 'from ' . $from : '') . ($to !== '' ? ' -> ' . $to : '') . ($permission !== '' ? ' • ' . $permission : ''));
                if (!$context->matches($query, [$label, (string)$entity, (string)$action, $permission, $from, $to, 'workflow transition'])) {
                    continue;
                }

                $items[] = [
                    'id' => 'workflow:' . md5((string)$entity . '|' . (string)$action),
                    'label' => $label,
                    'status' => 'available',
                    'url' => $url,
                    'meta' => $meta,
                ];
            }
        }

        return array_slice($items, 0, self::RESULT_LIMIT);
    }

    /**
     * @param array<int,string> $expressions
     * @param array<string,string> $scopeMap
     * @return array<int,array<string,mixed>>
     */
    private function queryRows(
        string $query,
        array $expressions,
        string $baseSql,
        string $orderColumn,
        SearchProviderContext $context,
        string $table,
        array $scopeMap,
        int $limit = self::RESULT_LIMIT
    ): array {
        [$scopeSql, $scopeParams] = $context->scopeClause($table, $scopeMap);
        return $this->runQuery($query, $expressions, $baseSql, $orderColumn, $scopeSql, $scopeParams, $context, $limit);
    }

    /** @param array<int,string> $expressions @param array<int,int|string> $scopeParams @return array<int,array<string,mixed>> */
    private function runQuery(
        string $query,
        array $expressions,
        string $baseSql,
        string $orderColumn,
        string $scopeSql,
        array $scopeParams,
        SearchProviderContext $context,
        int $limit = self::RESULT_LIMIT
    ): array {
        [$where, $params] = $context->phraseWhere($expressions, $query);
        $rows = DB::fetchAll(
            $baseSql . " WHERE {$where}{$scopeSql} ORDER BY {$orderColumn} DESC LIMIT ?",
            array_merge($params, $scopeParams, [$limit])
        );
        if ($rows !== []) {
            return $rows;
        }
        [$where, $params] = $context->tokenWhere($expressions, $context->queryTokens($query), false);
        return DB::fetchAll(
            $baseSql . " WHERE {$where}{$scopeSql} ORDER BY {$orderColumn} DESC LIMIT ?",
            array_merge($params, $scopeParams, [$limit])
        );
    }
}
