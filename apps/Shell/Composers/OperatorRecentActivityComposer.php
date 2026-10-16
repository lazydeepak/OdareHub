<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

use App\Core\DB;

/**
 * Builds the operator recent-activity data contract without rendering markup.
 *
 * Production defaults retain the existing DB and translation authorities.
 * Injectable callbacks are limited to isolated non-database verification.
 */
final class OperatorRecentActivityComposer
{
    /** @var callable(string, array<int,mixed>):array<int,array<string,mixed>> */
    private $fetchAll;

    /** @var callable(string, array<int,mixed>):array<string,mixed>|false|null */
    private $fetchOne;

    /** @var callable(string, string, array<string,mixed>):string */
    private $translator;

    /** @var array<string,string> */
    private array $recentEntitySummaryCache = [];

    /**
     * @param null|callable(string, array<int,mixed>):array<int,array<string,mixed>> $fetchAll
     * @param null|callable(string, array<int,mixed>):array<string,mixed>|false|null $fetchOne
     * @param null|callable(string, string, array<string,mixed>):string $translator
     */
    public function __construct(?callable $fetchAll = null, ?callable $fetchOne = null, ?callable $translator = null)
    {
        $this->fetchAll = $fetchAll ?? static fn(string $sql, array $params = []): array => DB::fetchAll($sql, $params);
        $this->fetchOne = $fetchOne ?? static fn(string $sql, array $params = []) => DB::fetchOne($sql, $params);
        $this->translator = $translator ?? static function (string $key, string $fallback, array $params = []): string {
            if (function_exists('t')) {
                $translated = (string)t($key, $params);
                if ($translated !== '' && $translated !== $key) {
                    return $translated;
                }
            }
            if ($params === []) {
                return $fallback;
            }
            $replace = [];
            foreach ($params as $paramKey => $paramValue) {
                $replace['{' . $paramKey . '}'] = (string)$paramValue;
            }
            return strtr($fallback, $replace);
        };
    }

    private function tr(string $key, string $fallback, array $params = []): string
    {
        return ($this->translator)($key, $fallback, $params);
    }

    /**
     * @return array{title:string,subtitle:string,empty_label:string,total:int,unique_events:int,event_bars:array<int,array{label:string,count:int,percent:float}>,rows:array<int,array{when:string,event_type:string,action:string,module:string,note:string,actor:string,event_display:string,action_display:string}>}
     */
    public function build(array $context): array
    {
        $empty = [
            'title' => $this->tr('operator.recent.title', 'Recent User Activity'),
            'subtitle' => $this->tr('operator.recent.subtitle', 'Latest logged actions for this operator account.'),
            'empty_label' => $this->tr('operator.recent.empty', 'No activity log entries found.'),
            'total' => 0,
            'unique_events' => 0,
            'event_bars' => [],
            'rows' => [],
        ];

        $userEmail = trim((string)($context['user_email'] ?? ''));
        if ($userEmail === '') {
            return $empty;
        }

        try {
            $rows = ($this->fetchAll)(
                "SELECT
                    created_at,
                    event_type,
                    action_name,
                    actor_email,
                    actor_display_name,
                    entity_type,
                    entity_id,
                    app_key,
                    module_key,
                    note_text,
                    reason_text,
                    new_state
                 FROM audit_activity_log
                 WHERE actor_email = ?
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY created_at DESC, id DESC
                 LIMIT 200",
                [$userEmail]
            );
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildRecentActivityData: ' . $e->getMessage());
            return $empty;
        }

        if ($rows === []) {
            return $empty;
        }

        $eventCounts = [];
        $formattedRows = [];
        foreach ((array)$rows as $row) {
            $eventType = trim((string)($row['event_type'] ?? ''));
            $eventKey = strtolower($eventType);
            $eventLabel = $eventType !== ''
                ? ucwords((string)preg_replace('/[\/_\-]+/', ' ', $eventType))
                : $this->tr('operator.recent.event_unknown', 'Unknown');
            if ($eventKey === 'workflow') {
                $eventLabel = $this->tr('operator.recent.event_workflow', 'Approval Workflow');
            } elseif ($eventKey === 'approval') {
                $eventLabel = $this->tr('operator.recent.event_approval', 'Approval Event');
            }
            $eventCounts[$eventLabel] = (int)($eventCounts[$eventLabel] ?? 0) + 1;

            $actionName = trim((string)($row['action_name'] ?? ''));
            $actionKey = strtolower($actionName);
            $actionLabel = $actionName !== ''
                ? ucwords((string)preg_replace('/[\/_\-]+/', ' ', $actionName))
                : $this->tr('operator.recent.action_unknown', 'Action logged');
            if ($actionKey === 'approved') {
                $actionLabel = $this->tr('operator.recent.action_approved', 'Approved Request');
            } elseif ($actionKey === 'rejected') {
                $actionLabel = $this->tr('operator.recent.action_rejected', 'Rejected Request');
            } elseif ($actionKey === 'submitted') {
                $actionLabel = $this->tr('operator.recent.action_submitted', 'Submitted Request');
            }

            $actorLabel = trim((string)($row['actor_display_name'] ?? ''));
            if ($actorLabel === '') {
                $actorLabel = trim((string)($row['actor_email'] ?? ''));
            }
            if ($actorLabel === '') {
                $actorLabel = $this->tr('operator.recent.actor_system', 'System');
            }

            $appKey = trim((string)($row['app_key'] ?? ''));
            $moduleKey = trim((string)($row['module_key'] ?? ''));
            $moduleText = trim($appKey . ($moduleKey !== '' ? ' / ' . $moduleKey : ''));
            if ($moduleText === '') {
                $moduleText = $this->tr('operator.recent.module_unknown', 'Unscoped');
            }

            $entityType = trim((string)($row['entity_type'] ?? ''));
            $entityId = (int)($row['entity_id'] ?? 0);
            $entitySummary = $this->buildRecentEntitySummary($entityType, $entityId);
            $activityDate = $this->formatRecentActivityDate((string)($row['created_at'] ?? ''));

            $newStateText = trim((string)($row['new_state'] ?? ''));
            $stateLabel = $newStateText === ''
                ? ''
                : sprintf(
                    $this->tr('operator.recent.state_label', 'status: %s'),
                    ucwords((string)preg_replace('/[\/_\-]+/', ' ', $newStateText))
                );

            $eventDisplay = $entitySummary;
            if ($stateLabel !== '') {
                $eventDisplay .= ' | ' . $stateLabel;
            }
            $eventDisplay .= ' | ' . $moduleText;

            $actionDisplay = sprintf(
                $this->tr('operator.recent.action_display_specific', '%1$s: %2$s on %3$s by %4$s'),
                $actionLabel,
                $entitySummary,
                $activityDate,
                $actorLabel
            );

            $note = trim((string)($row['note_text'] ?? ''));
            if ($note === '') {
                $note = trim((string)($row['reason_text'] ?? ''));
            }
            if ($note === '') {
                $note = trim((string)($row['new_state'] ?? ''));
            }
            if ($note === '') {
                $note = '-';
            }

            $formattedRows[] = [
                'when' => trim((string)($row['created_at'] ?? '')),
                'event_type' => $eventLabel,
                'action' => $actionLabel,
                'module' => $moduleText,
                'note' => $note,
                'actor' => $actorLabel,
                'event_display' => $eventDisplay,
                'action_display' => $actionDisplay,
            ];
        }

        arsort($eventCounts);
        $maxCount = 0;
        foreach ($eventCounts as $count) {
            $maxCount = max($maxCount, (int)$count);
        }

        $eventBars = [];
        foreach (array_slice($eventCounts, 0, 6, true) as $label => $count) {
            $eventBars[] = [
                'label' => (string)$label,
                'count' => (int)$count,
                'percent' => $maxCount > 0 ? round(((int)$count / $maxCount) * 100, 2) : 0.0,
            ];
        }

        return [
            'title' => $empty['title'],
            'subtitle' => $empty['subtitle'],
            'empty_label' => $empty['empty_label'],
            'total' => count($formattedRows),
            'unique_events' => count($eventCounts),
            'event_bars' => $eventBars,
            'rows' => $formattedRows,
        ];
    }

    private function formatRecentActivityDate(string $rawDateTime): string
    {
        $rawDateTime = trim($rawDateTime);
        if ($rawDateTime === '') {
            return '-';
        }

        try {
            $dt = new \DateTimeImmutable($rawDateTime);
            return $dt->format('m/d');
        } catch (\Throwable $e) {
            return $rawDateTime;
        }
    }

    private function buildRecentEntitySummary(string $entityType, int $entityId): string
    {
        $normalizedType = strtolower(trim($entityType));
        if ($entityId <= 0) {
            return $this->tr('operator.recent.entity_unknown', 'record');
        }

        $cacheKey = $normalizedType . ':' . $entityId;
        if (isset($this->recentEntitySummaryCache[$cacheKey])) {
            return $this->recentEntitySummaryCache[$cacheKey];
        }

        $summary = '';
        try {
            if ($normalizedType === 'production_plan') {
                $row = ($this->fetchOne)(
                    "SELECT
                        pp.plan_date,
                        pp.product_id,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), NULLIF(TRIM(p.parts_number), ''), CONCAT('Part #', pp.product_id)) AS part_name
                     FROM production_plans pp
                     LEFT JOIN products p ON p.id = pp.product_id
                     WHERE pp.id = ?
                     LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $partName = trim((string)($row['part_name'] ?? ''));
                    $planDate = $this->formatRecentActivityDate((string)($row['plan_date'] ?? ''));
                    if ($partName === '') {
                        $partName = 'Part #' . (int)($row['product_id'] ?? 0);
                    }
                    $summary = sprintf(
                        $this->tr('operator.recent.entity.production_plan_with_part_date', 'production plan for %1$s dated %2$s'),
                        $partName,
                        $planDate
                    );
                }
            } elseif ($normalizedType === 'qc_plan') {
                $row = ($this->fetchOne)(
                    "SELECT
                        qp.plan_date,
                        qp.product_id,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), NULLIF(TRIM(p.parts_number), ''), CONCAT('Part #', qp.product_id)) AS part_name
                     FROM qc_plans qp
                     LEFT JOIN products p ON p.id = qp.product_id
                     WHERE qp.id = ?
                     LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $summary = sprintf(
                        $this->tr('operator.recent.entity.qc_plan_with_part_date', 'qc plan for %1$s dated %2$s'),
                        trim((string)($row['part_name'] ?? 'Part #' . (int)($row['product_id'] ?? 0))),
                        $this->formatRecentActivityDate((string)($row['plan_date'] ?? ''))
                    );
                }
            } elseif ($normalizedType === 'daily_order') {
                $row = ($this->fetchOne)(
                    "SELECT
                        d.order_date,
                        d.product_id,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), NULLIF(TRIM(p.parts_number), ''), CONCAT('Part #', d.product_id)) AS part_name
                     FROM daily_orders d
                     LEFT JOIN products p ON p.id = d.product_id
                     WHERE d.id = ?
                     LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $summary = sprintf(
                        $this->tr('operator.recent.entity.daily_order_with_part_date', 'daily order for %1$s dated %2$s'),
                        trim((string)($row['part_name'] ?? 'Part #' . (int)($row['product_id'] ?? 0))),
                        $this->formatRecentActivityDate((string)($row['order_date'] ?? ''))
                    );
                }
            } elseif ($normalizedType === 'procurement_request') {
                $row = ($this->fetchOne)(
                    "SELECT
                        r.request_ref,
                        r.needed_date,
                        r.product_id,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), NULLIF(TRIM(p.parts_number), ''), CONCAT('Part #', r.product_id)) AS part_name
                     FROM procurement_requests r
                     LEFT JOIN products p ON p.id = r.product_id
                     WHERE r.id = ?
                     LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $requestRef = trim((string)($row['request_ref'] ?? 'REQ-' . $entityId));
                    $partName = trim((string)($row['part_name'] ?? ''));
                    $neededDate = $this->formatRecentActivityDate((string)($row['needed_date'] ?? ''));
                    if ($partName !== '') {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_request_with_part_date', 'procurement request %1$s for %2$s needed by %3$s'),
                            $requestRef,
                            $partName,
                            $neededDate
                        );
                    } else {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_request_with_date', 'procurement request %1$s needed by %2$s'),
                            $requestRef,
                            $neededDate
                        );
                    }
                }
            } elseif ($normalizedType === 'procurement_order') {
                $row = ($this->fetchOne)(
                    "SELECT
                        po.po_ref,
                        po.expected_date,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), NULLIF(TRIM(p.parts_number), ''), '') AS part_name
                     FROM procurement_purchase_orders po
                     LEFT JOIN procurement_purchase_order_lines pol ON pol.po_id = po.id
                     LEFT JOIN products p ON p.id = pol.product_id
                     WHERE po.id = ?
                     ORDER BY pol.id ASC
                     LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $poRef = trim((string)($row['po_ref'] ?? 'PO-' . $entityId));
                    $partName = trim((string)($row['part_name'] ?? ''));
                    $expectedDate = $this->formatRecentActivityDate((string)($row['expected_date'] ?? ''));
                    if ($partName !== '') {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_order_with_part_date', 'purchase order %1$s for %2$s expected %3$s'),
                            $poRef,
                            $partName,
                            $expectedDate
                        );
                    } else {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_order_with_date', 'purchase order %1$s expected %2$s'),
                            $poRef,
                            $expectedDate
                        );
                    }
                }
            } elseif ($normalizedType === 'procurement_receipt') {
                $row = ($this->fetchOne)(
                    "SELECT
                        r.receipt_ref,
                        r.receipt_date,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), NULLIF(TRIM(p.parts_number), ''), '') AS part_name
                     FROM procurement_receipts r
                     LEFT JOIN procurement_purchase_order_lines pol ON pol.id = r.po_line_id
                     LEFT JOIN products p ON p.id = pol.product_id
                     WHERE r.id = ?
                     LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $receiptRef = trim((string)($row['receipt_ref'] ?? 'GRN-' . $entityId));
                    $partName = trim((string)($row['part_name'] ?? ''));
                    $receiptDate = $this->formatRecentActivityDate((string)($row['receipt_date'] ?? ''));
                    if ($partName !== '') {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_receipt_with_part_date', 'receipt %1$s for %2$s dated %3$s'),
                            $receiptRef,
                            $partName,
                            $receiptDate
                        );
                    } else {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_receipt_with_date', 'receipt %1$s dated %2$s'),
                            $receiptRef,
                            $receiptDate
                        );
                    }
                }
            } elseif ($normalizedType === 'procurement_supplier') {
                $row = ($this->fetchOne)(
                    "SELECT supplier_code, supplier_name FROM procurement_suppliers WHERE id = ? LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $supplierCode = trim((string)($row['supplier_code'] ?? ''));
                    $supplierName = trim((string)($row['supplier_name'] ?? 'Supplier #' . $entityId));
                    if ($supplierCode !== '') {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_supplier_with_code', 'supplier %1$s (%2$s)'),
                            $supplierName,
                            $supplierCode
                        );
                    } else {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_supplier', 'supplier %1$s'),
                            $supplierName
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildRecentEntitySummary: ' . $e->getMessage());
            $summary = '';
        }

        if ($summary === '') {
            $readableType = $normalizedType !== ''
                ? strtolower((string)preg_replace('/[\/_\-]+/', ' ', $normalizedType))
                : $this->tr('operator.recent.entity_record', 'record');
            $summary = sprintf(
                $this->tr('operator.recent.entity.generic_with_id', '%1$s #%2$d'),
                $readableType,
                $entityId
            );
        }

        $this->recentEntitySummaryCache[$cacheKey] = $summary;
        return $summary;
    }

}
