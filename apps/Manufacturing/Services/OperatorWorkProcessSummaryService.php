<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use Apps\Manufacturing\Services\OperatorLayerAdapters\ProcessingAdapter;
use Apps\Manufacturing\Services\MyWorkContributionService;

require_once APP_ROOT . '/apps/Manufacturing/Services/OperatorLayerAdapters/ProcessingAdapter.php';
require_once APP_ROOT . '/apps/Manufacturing/Services/MyWorkContributionService.php';

/**
 * Read-only, scope-safe summary for the operator dashboard.
 *
 * ProcessingAdapter is the authority boundary. This service only summarizes
 * records already returned by ProcessingOperationService::operatorFocus(); it
 * never queries the broad My Work queue or treats presentation labels as scope.
 */
final class OperatorWorkProcessSummaryService
{
    /**
     * @param array<string,mixed>|null $user
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function build(
        string $date,
        ?array $user,
        array $context,
        string $username,
        ?callable $routeResolver = null
    ): array {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ? $date
            : (new \DateTimeImmutable('today'))->format('Y-m-d');
        try {
            // This is the operator dashboard contribution; no profile or
            // presentation field is used as a record-access grant.
            $projection = ProcessingAdapter::getData($date, $user, 0, 'operator');
        } catch (\Throwable) {
            return self::unavailable('operator.work_process.unavailable');
        }

        return self::summarizeProjection($projection, $context, $username, $routeResolver, $date);
    }

    /**
     * Pure summary boundary used by the isolated proof and by the runtime.
     * The projection must already carry Processing's positive authorization and
     * exact lane authority flags.
     *
     * @param array<string,mixed> $projection
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function summarizeProjection(
        array $projection,
        array $context,
        string $username,
        ?callable $routeResolver = null,
        string $selectedDate = ''
    ): array {
        $empty = self::emptySummary($selectedDate);
        if (($projection['authorized'] ?? false) !== true) {
            return self::unavailable('operator.work_process.unauthorized');
        }

        $laneDefinitions = [
            'assembly' => [
                'allowed_key' => 'assembly_allowed',
                'rows_key' => 'assembly_rows',
                'label_key' => 'operator.work_process.lane.assembly',
                'label_fallback' => 'Assembly',
            ],
            'qc' => [
                'allowed_key' => 'qc_allowed',
                'rows_key' => 'qc_rows',
                'label_key' => 'operator.work_process.lane.qc',
                'label_fallback' => 'QC',
            ],
        ];

        $primaryArea = MyWorkContributionService::primaryWorkArea($context);
        $crossAreas = MyWorkContributionService::crossWorkAreas($context);
        $lanes = [];

        foreach ($laneDefinitions as $laneKey => $definition) {
            // These booleans are emitted by the existing Processing authority;
            // labels and row contents never create lane access.
            if (($projection[$definition['allowed_key']] ?? false) !== true) {
                continue;
            }

            $records = [];
            foreach ((array)($projection[$definition['rows_key']] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $record = self::normalizeRecord($laneKey, $row);
                if ($record !== null) {
                    $records[] = $record;
                }
            }

            $areaLabel = '';
            if ($primaryArea === $laneKey || in_array($laneKey, $crossAreas, true)) {
                $areaLabel = self::translate(
                    'operator.work_process.area.' . $laneKey,
                    ucfirst($laneKey)
                );
            }

            $lanes[] = [
                'key' => $laneKey,
                'label' => self::translate($definition['label_key'], $definition['label_fallback']),
                'count' => count($records),
                'records' => array_slice($records, 0, 4),
                'presentation_area' => $areaLabel,
                'process_context' => self::translate($definition['label_key'], $definition['label_fallback']),
                'destination' => self::safeProcessingDestination($username, $selectedDate, $routeResolver),
            ];
        }

        if ($lanes === []) {
            return self::unavailable('operator.work_process.no_authorized_lane');
        }

        $total = 0;
        foreach ($lanes as $lane) {
            $total += (int)$lane['count'];
        }

        $empty['available'] = true;
        $empty['state'] = $total > 0 ? 'available' : 'empty';
        $empty['error'] = '';
        $empty['authorized'] = true;
        $empty['lanes'] = $lanes;
        $empty['total_count'] = $total;
        return $empty;
    }

    /** @return array<string,mixed> */
    private static function normalizeRecord(string $lane, array $row): ?array
    {
        $id = (int)($row['id'] ?? 0);
        $productId = (int)($row['product_id'] ?? 0);
        if ($id <= 0 || $productId <= 0) {
            return null;
        }

        return [
            'id' => $id,
            'product_id' => $productId,
            'part_name' => trim((string)($row['part_name'] ?? '')),
            'part_number' => trim((string)($row['part_number'] ?? '')),
            'date' => trim((string)($row[$lane === 'assembly' ? 'demand_date' : 'plan_date'] ?? '')),
            'status' => trim((string)($row['status'] ?? '')),
            'priority' => trim((string)($row['priority'] ?? '')),
            'planned_qty' => (float)($row['planned_qty'] ?? 0),
        ];
    }

    private static function safeProcessingDestination(string $username, string $date, ?callable $resolver): string
    {
        $username = trim($username);
        if ($username === '' || str_contains($username, '/') || !is_callable($resolver)) {
            return '';
        }
        $path = '/u/' . rawurlencode($username) . '/processing';
        try {
            if ($resolver($path) !== true) {
                return '';
            }
        } catch (\Throwable) {
            return '';
        }
        return $path . '?focus=processing&processing_date=' . rawurlencode($date);
    }

    /** @return array<string,mixed> */
    private static function emptySummary(string $date): array
    {
        return [
            'available' => false,
            'authorized' => false,
            'state' => 'unavailable',
            'error' => '',
            'source' => 'manufacturing_processing_projection',
            'selected_date' => $date,
            'total_count' => 0,
            'lanes' => [],
        ];
    }

    /** @return array<string,mixed> */
    private static function unavailable(string $error): array
    {
        $summary = self::emptySummary('');
        $summary['error'] = $error;
        return $summary;
    }

    private static function translate(string $key, string $fallback): string
    {
        if (function_exists('t')) {
            $value = (string)t($key);
            if ($value !== '' && $value !== $key) {
                return $value;
            }
        }
        return $fallback;
    }
}
