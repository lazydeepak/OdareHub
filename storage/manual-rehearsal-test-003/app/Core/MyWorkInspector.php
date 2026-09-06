<?php
declare(strict_types=1);

namespace App\Core;

final class MyWorkInspector
{
    public const BUCKET_PRIMARY_WORK = 'primary_work';
    public const BUCKET_CROSS_FUNCTIONAL = 'cross_functional_work';
    public const BUCKET_VISIBILITY = 'visibility_monitoring';

    public const SECTION_NEEDS_ACTION = 'needs_action';
    public const SECTION_AWAITING_APPROVAL = 'awaiting_approval';
    public const SECTION_OVERDUE = 'overdue_escalated';
    public const SECTION_BLOCKED = 'blocked_followup';

    public static function explainItem(array $item, EntityContext $context, array $options = []): array
    {
        $entityType = strtolower(trim((string)($item['entity_type'] ?? '')));
        $entityKey = self::entityTypeToEntityKey($entityType);
        $policyResolver = new EntityPolicyResolver();
        $slaConfig = ManufacturingSlaConfig::get($entityKey);
        $hasSla = !empty($slaConfig);

        $canView = $policyResolver->canViewRow($entityKey, $item, $context);
        $visibilityReason = $canView ? 'policy_allowed' : 'policy_hidden';

        $enriched = [];
        if ($hasSla) {
            $slaConfigMerged = array_merge(['enabled' => true], $slaConfig);
            $slaResult = EntitySlaEngine::evaluate($slaConfigMerged, $item, $context);
            $enriched['sla_state'] = $slaResult['state'];
            $enriched['sla_label'] = $slaResult['label'] ?? 'On Track';
            $enriched['sla_deadline'] = $slaResult['deadline_at'];
            $enriched['sla_escalated'] = $slaResult['escalated'];
            $enriched['sla_age_seconds'] = $slaResult['age_seconds'];
        }

        $stageLabel = self::extractStageLabel($item, $entityType);
        $workArea = self::workAreaForEntity($entityType);

        $primaryArea = $options['primary_area'] ?? self::primaryWorkArea($context);
        $crossAreas = $options['cross_areas'] ?? self::crossWorkAreas($context);
        $bucket = self::determineBucketByAreas($workArea, $primaryArea, $crossAreas);

        $included = $canView && self::isIncludedByStatus($item, $entityType);
        $inclusionReason = self::buildInclusionReason($included, $canView, $entityType, $item);

        $section = self::determineSection($item, $enriched, $entityType);

        return [
            'entity_type' => $entityType,
            'entity_key' => $entityKey,
            'entity_id' => (int)($item['id'] ?? $item['entity_id'] ?? 0),
            'included' => $included,
            'inclusion_reason' => $inclusionReason,
            'can_view' => $canView,
            'visibility_reason' => $visibilityReason,
            'bucket' => $bucket,
            'work_area' => $workArea,
            'has_sla' => $hasSla,
            'sla_config' => $hasSla ? $slaConfig : null,
            'sla_state' => $enriched['sla_state'] ?? null,
            'sla_label' => $enriched['sla_label'] ?? null,
            'sla_deadline' => $enriched['sla_deadline'] ?? null,
            'sla_escalated' => $enriched['sla_escalated'] ?? false,
            'stage_label' => $stageLabel,
            'section' => $section,
            'status' => self::extractStatus($item, $entityType),
            'age_hours' => self::calculateAgeHours($item),
            'detail_url' => (string)($item['detail_url'] ?? '#'),
        ];
    }

    public static function explainItems(array $items, EntityContext $context, array $options = []): array
    {
        $results = [];
        foreach ($items as $item) {
            $results[] = self::explainItem($item, $context, $options);
        }
        return $results;
    }

    public static function entityTypeToEntityKey(string $entityType): string
    {
        return match ($entityType) {
            'daily_order' => 'DailyOrder',
            'production_entry' => 'ProductionEntry',
            'production_plan' => 'ProductionPlan',
            'qc_entry' => 'QCEntry',
            'dispatch_entry' => 'DispatchEntry',
            'assembly_plan' => 'AssemblyPlan',
            'assembly_entry' => 'AssemblyEntry',
            default => '',
        };
    }

    public static function workAreaForEntity(string $entityType): string
    {
        return match ($entityType) {
            'daily_order' => 'order',
            'assembly_plan', 'assembly_entry' => 'assembly',
            'qc_entry' => 'qc',
            'dispatch_entry' => 'dispatch',
            'production_plan', 'production_entry' => 'production',
            default => 'production',
        };
    }

    public static function determineBucketByAreas(string $workArea, string $primaryArea, array $crossAreas): string
    {
        if ($workArea === $primaryArea) {
            return self::BUCKET_PRIMARY_WORK;
        }
        if (in_array($workArea, $crossAreas, true)) {
            return self::BUCKET_CROSS_FUNCTIONAL;
        }
        return self::BUCKET_VISIBILITY;
    }

    public static function primaryWorkArea(EntityContext $context): string
    {
        $profile = strtolower(trim((string)($context->operationalProfile ?? '')));
        $slug = str_replace([' ', '-'], '', $profile);

        if (str_contains($slug, 'assembly')) {
            return 'assembly';
        }
        if (str_contains($slug, 'qc')) {
            return 'qc';
        }
        if (str_contains($slug, 'dispatch')) {
            return 'dispatch';
        }
        if (str_contains($slug, 'planner') || str_contains($slug, 'order') || str_contains($slug, 'planning')) {
            return 'order';
        }
        return 'production';
    }

    public static function crossWorkAreas(EntityContext $context): array
    {
        $crossFunctional = $context->crossFunctionalAccess ?? [];
        $map = [];
        foreach ((array)$crossFunctional as $bundle => $level) {
            $k = strtolower(trim((string)$bundle));
            if ($k === '') {
                continue;
            }
            if (str_contains($k, 'order')) {
                $map['order'] = 'order';
            } elseif (str_contains($k, 'assembly')) {
                $map['assembly'] = 'assembly';
            } elseif (str_contains($k, 'qc')) {
                $map['qc'] = 'qc';
            } elseif (str_contains($k, 'dispatch')) {
                $map['dispatch'] = 'dispatch';
            } elseif (str_contains($k, 'production')) {
                $map['production'] = 'production';
            }
        }
        return array_values($map);
    }

    private static function isIncludedByStatus(array $item, string $entityType): bool
    {
        $status = strtolower(trim((string)($item['status'] ?? $item['dispatch_status'] ?? 'open')));

        $completedStatuses = ['completed', 'closed', 'cancelled', 'fulfilled', 'dispatched'];
        if (in_array($status, $completedStatuses, true)) {
            return false;
        }

        return true;
    }

    private static function buildInclusionReason(bool $included, bool $canView, string $entityType, array $item): string
    {
        if (!$canView) {
            return 'policy_hidden';
        }
        if (!$included) {
            $status = strtolower(trim((string)($item['status'] ?? $item['dispatch_status'] ?? '')));
            if (in_array($status, ['completed', 'closed', 'cancelled', 'fulfilled', 'dispatched'], true)) {
                return 'filtered_by_status';
            }
            return 'no_ownership_match';
        }
        return 'active_scope';
    }

    private static function extractStatus(array $item, string $entityType): string
    {
        if ($entityType === 'dispatch_entry') {
            return (string)($item['dispatch_status'] ?? $item['status'] ?? 'Draft');
        }
        return (string)($item['status'] ?? 'Unknown');
    }

    private static function extractStageLabel(array $item, string $entityType): string
    {
        $stageLabel = (string)($item['stage_label'] ?? '');
        if ($stageLabel !== '') {
            return $stageLabel;
        }

        $status = self::extractStatus($item, $entityType);
        return ucwords(str_replace('_', ' ', strtolower($status)));
    }

    private static function determineSection(array $item, array $enriched, string $entityType): string
    {
        $status = strtolower(trim((string)($item['status'] ?? $item['dispatch_status'] ?? 'open')));
        $slaState = (string)($enriched['sla_state'] ?? 'ok');
        $slaEscalated = (bool)($enriched['sla_escalated'] ?? false);

        if ($slaEscalated || $slaState === 'breached' || $slaState === 'overdue') {
            return self::SECTION_OVERDUE;
        }

        if (str_contains($status, 'block') || str_contains($status, 'hold') || str_contains($status, 'wait')) {
            return self::SECTION_BLOCKED;
        }

        $approvalStatus = strtolower(trim((string)($item['approval_status'] ?? '')));
        if (in_array($approvalStatus, ['pending approval', 'reopened', 'draft'], true)) {
            return self::SECTION_AWAITING_APPROVAL;
        }

        return self::SECTION_NEEDS_ACTION;
    }

    private static function calculateAgeHours(array $item): float
    {
        $updatedAt = (string)($item['updated_at'] ?? '');
        if ($updatedAt === '') {
            return 0.0;
        }
        try {
            $ts = new \DateTime($updatedAt);
            $diff = (new \DateTime())->getTimestamp() - $ts->getTimestamp();
            return $diff > 0 ? round($diff / 3600, 2) : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    public static function getSupportedEntityTypes(): array
    {
        return [
            'daily_order',
            'production_entry',
            'production_plan',
            'qc_entry',
            'dispatch_entry',
            'assembly_plan',
            'assembly_entry',
        ];
    }

    public static function getEntityTypeLabel(string $entityType): string
    {
        return match ($entityType) {
            'daily_order' => 'Daily Order',
            'production_entry' => 'Production Entry',
            'production_plan' => 'Production Plan',
            'qc_entry' => 'QC Entry',
            'dispatch_entry' => 'Dispatch Entry',
            'assembly_plan' => 'Assembly Plan',
            'assembly_entry' => 'Assembly Entry',
            default => ucfirst(str_replace('_', ' ', $entityType)),
        };
    }

    public static function explainSingleRecord(string $entityType, int $id, EntityContext $context, array $options = []): array
    {
        $entityType = strtolower(trim($entityType));
        $supported = self::getSupportedEntityTypes();

        if (!in_array($entityType, $supported, true)) {
            return [
                'found' => false,
                'record' => null,
                'error' => 'unsupported_entity',
                'error_message' => "Entity type '{$entityType}' is not supported for My Work replay.",
                'entity_type' => $entityType,
                'entity_id' => $id,
            ];
        }

        $record = self::fetchSingleRecord($entityType, $id);

        if ($record === null) {
            return [
                'found' => false,
                'record' => null,
                'error' => 'not_found',
                'error_message' => "Record #{$id} not found for entity type '{$entityType}'.",
                'entity_type' => $entityType,
                'entity_id' => $id,
            ];
        }

        $primaryArea = $options['primary_area'] ?? self::primaryWorkArea($context);
        $crossAreas = $options['cross_areas'] ?? self::crossWorkAreas($context);

        $explanation = self::explainItem($record, $context, [
            'primary_area' => $primaryArea,
            'cross_areas' => $crossAreas,
        ]);

        return [
            'found' => true,
            'record' => $record,
            'error' => null,
            'error_message' => null,
            'entity_type' => $entityType,
            'entity_id' => $id,
            'primary_area' => $primaryArea,
            'cross_areas' => $crossAreas,
            'explanation' => $explanation,
        ];
    }

    private static function fetchSingleRecord(string $entityType, int $id): ?array
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        $rows = self::fetchRecordsByIds($entityType, [$id]);
        return $rows[0] ?? null;
    }

    public static function fetchRecordsByIds(string $entityType, array $ids): array
    {
        $ids = array_filter(array_map('intval', $ids));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = $ids;

        $rows = self::queryRecords($entityType, "id IN ({$placeholders})", $params);
        return self::normalizeRecords($entityType, $rows);
    }

    private static function queryRecords(string $entityType, string $whereClause, array $params): array
    {
        $table = self::getTableForEntity($entityType);
        if ($table === '') {
            return [];
        }

        try {
            $fields = self::getFieldsForEntity($entityType);
            $selectFields = implode(', ', $fields);

            $rows = DB::fetchAll(
                "SELECT {$selectFields} FROM {$table} WHERE {$whereClause} LIMIT 100",
                $params
            );
            return $rows ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function getTableForEntity(string $entityType): string
    {
        return match ($entityType) {
            'daily_order' => 'daily_orders',
            'production_entry' => 'production_entries',
            'production_plan' => 'production_plans',
            'qc_entry' => 'qc_entries',
            'dispatch_entry' => 'dispatch_entries',
            'assembly_plan' => 'assembly_plans',
            'assembly_entry' => 'assembly_entries',
            default => '',
        };
    }

    private static function getFieldsForEntity(string $entityType): array
    {
        $baseFields = ['id', 'status', 'updated_at'];

        return match ($entityType) {
            'daily_order' => array_merge($baseFields, ['qty', 'required_date', 'order_date', 'customer_name', 'product_id']),
            'production_entry' => array_merge($baseFields, ['qty_produced', 'good_qty', 'rejected_qty', 'production_date', 'machine_id', 'product_id', 'shift']),
            'production_plan' => array_merge($baseFields, ['planned_qty', 'plan_date', 'approval_status', 'machine_id', 'product_id', 'sequence_no', 'runtime', 'plan_type']),
            'qc_entry' => array_merge($baseFields, ['checked_qty', 'pass_qty', 'fail_qty', 'qc_type', 'product_id', 'approval_status', 'remarks']),
            'dispatch_entry' => array_merge($baseFields, ['dispatch_status', 'dispatchable_qty', 'dispatch_date', 'approval_status', 'destination', 'dispatch_type', 'product_id']),
            'assembly_plan' => array_merge($baseFields, ['planned_qty', 'demand_date', 'status', 'product_id']),
            'assembly_entry' => array_merge($baseFields, ['assembly_date', 'status', 'product_id']),
            default => $baseFields,
        };
    }

    private static function normalizeRecords(string $entityType, array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $item = ['entity_type' => $entityType];

            foreach ($row as $key => $value) {
                $item[$key] = $value;
            }

            $item['detail_url'] = self::buildDetailUrl($entityType, (int)($row['id'] ?? 0));

            $normalized[] = $item;
        }

        return $normalized;
    }

    private static function buildDetailUrl(string $entityType, int $id): string
    {
        if ($id <= 0) {
            return '#';
        }

        return match ($entityType) {
            'daily_order' => "/daily-orders/360?id={$id}",
            'production_entry' => "/production-entries/edit?id={$id}",
            'production_plan' => "/production-plans/edit?id={$id}",
            'qc_entry' => "/qc-entries/edit?id={$id}",
            'dispatch_entry' => "/dispatch-entries/edit?id={$id}",
            default => "#",
        };
    }
}
