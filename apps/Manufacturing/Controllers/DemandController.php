<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

use App\Core\AuditLogService;
use App\Core\Auth;
use App\Core\View;
use Apps\Manufacturing\Services\DemandEngineService;
use Apps\Manufacturing\Services\UpstreamSupplyGenerationService;
use Apps\Manufacturing\Services\DemandWorkBucketService;
use Apps\Manufacturing\Services\DemandWorkExecutionGeneratorService;

final class DemandController
{
    public static function index(View $view): void
    {
        DemandEngineService::ensureSchema();

        $fromDate = trim((string)($_GET['from_date'] ?? ''));
        $toDate = trim((string)($_GET['to_date'] ?? ''));
        $productId = (int)($_GET['product_id'] ?? 0);
        $demandType = strtolower(trim((string)($_GET['demand_type'] ?? '')));
        $status = strtolower(trim((string)($_GET['status'] ?? '')));

        $rows = DemandEngineService::listDemands($fromDate, $toDate, $productId, $demandType, $status);
        $products = \App\Core\DB::fetchAll('SELECT id, parts_name, parts_number FROM products ORDER BY parts_name ASC LIMIT 1200');

        $view->render('manufacturing::demands/index.php', [
            'pageTitle' => 'Manufacturing Demand Engine',
            'rows' => $rows,
            'products' => $products,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'product_id' => $productId,
            'demand_type' => $demandType,
            'status' => $status,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function recalculate(array $input): void
    {
        try {
            AuditLogService::ensureSchema();
            $fromDate = trim((string)($input['from_date'] ?? ''));
            $toDate = trim((string)($input['to_date'] ?? ''));
            $productId = (int)($input['product_id'] ?? 0);
            $updated = DemandEngineService::recalculateDemands($productId > 0 ? [$productId] : [], $fromDate !== '' ? $fromDate : null, $toDate !== '' ? $toDate : null);

            if ($productId > 0) {
                AuditLogService::logEvent(
                    'product',
                    $productId,
                    AuditLogService::EVENT_SYSTEM,
                    AuditLogService::ACTION_DEMAND_REGENERATED,
                    Auth::user(),
                    [
                        'app' => 'manufacturing',
                        'module' => 'demands',
                        'note' => 'Demand recalculation triggered from demand engine screen.',
                        'metadata' => [
                            'from_date' => $fromDate,
                            'to_date' => $toDate,
                        ],
                    ]
                );
            }

            self::flash('ok', 'Demand recalculation finished. Updated rows: ' . $updated);
        } catch (\Throwable $e) {
            self::flash('err', 'Demand recalculation failed: ' . $e->getMessage());
        }
    }

    public static function adjust(array $input): void
    {
        try {
            $id = (int)($input['id'] ?? 0);
            $qty = (float)($input['adjusted_qty'] ?? 0);
            $note = trim((string)($input['adjustment_note'] ?? ''));
            DemandEngineService::adjustDemand($id, $qty, $note);
            self::flash('ok', 'Demand adjusted.');
        } catch (\Throwable $e) {
            self::flash('err', 'Adjust failed: ' . $e->getMessage());
        }
    }

    public static function approve(array $input): void
    {
        try {
            $id = (int)($input['id'] ?? 0);
            $rawApproved = trim((string)($input['approved_qty'] ?? ''));
            $approved = $rawApproved === '' ? null : (float)$rawApproved;
            DemandEngineService::approveDemand($id, $approved);
            self::flash('ok', 'Demand approved.');
        } catch (\Throwable $e) {
            self::flash('err', 'Approval failed: ' . $e->getMessage());
        }
    }

    public static function debugDecisions(): void
    {
        try {
            $date = trim((string)($_GET['date'] ?? ''));
            $limit = (int)($_GET['limit'] ?? 50);
            $productIdsRaw = trim((string)($_GET['product_ids'] ?? ''));
            $productIds = [];
            if ($productIdsRaw !== '') {
                $productIds = array_values(array_filter(
                    array_map(static fn(string $v): int => (int)trim($v), explode(',', $productIdsRaw)),
                    static fn(int $v): bool => $v > 0
                ));
            }

            $rows = DemandEngineService::previewRouteDecisions($date !== '' ? $date : null, $limit, $productIds);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'date' => $date !== '' ? $date : null,
                'product_ids' => $productIds,
                'count' => count($rows),
                'rows' => $rows,
            ], JSON_UNESCAPED_SLASHES);
            return;
        } catch (\Throwable $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            echo json_encode([
                'ok' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES);
            return;
        }
    }

    public static function debugWorkBuckets(): void
    {
        try {
            $date = trim((string)($_GET['date'] ?? ''));
            $limit = (int)($_GET['limit'] ?? 50);
            $productIdsRaw = trim((string)($_GET['product_ids'] ?? ''));
            $productIds = [];
            if ($productIdsRaw !== '') {
                $productIds = array_values(array_filter(
                    array_map(static fn(string $v): int => (int)trim($v), explode(',', $productIdsRaw)),
                    static fn(int $v): bool => $v > 0
                ));
            }

            $bucketed = DemandWorkBucketService::previewBuckets($date !== '' ? $date : null, $limit, $productIds);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'date' => $date !== '' ? $date : null,
                'product_ids' => $productIds,
                'count' => count((array)($bucketed['rows'] ?? [])),
                'summary' => (array)($bucketed['summary'] ?? []),
                'rows' => (array)($bucketed['rows'] ?? []),
            ], JSON_UNESCAPED_SLASHES);
            return;
        } catch (\Throwable $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            echo json_encode([
                'ok' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES);
            return;
        }
    }

    public static function debugGenerateWorkExecutions(): void
    {
        try {
            $date = trim((string)($_GET['date'] ?? ''));
            $limit = (int)($_GET['limit'] ?? 50);
            $apply = ((int)($_GET['apply'] ?? 0) === 1);
            $productIdsRaw = trim((string)($_GET['product_ids'] ?? ''));
            $productIds = [];
            if ($productIdsRaw !== '') {
                $productIds = array_values(array_filter(
                    array_map(static fn(string $v): int => (int)trim($v), explode(',', $productIdsRaw)),
                    static fn(int $v): bool => $v > 0
                ));
            }

            $result = DemandWorkExecutionGeneratorService::generate($date !== '' ? $date : null, $limit, $productIds, !$apply);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'date' => $date !== '' ? $date : null,
                'product_ids' => $productIds,
                'applied' => $apply,
                'result' => $result,
            ], JSON_UNESCAPED_SLASHES);
            return;
        } catch (\Throwable $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            echo json_encode([
                'ok' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES);
            return;
        }
    }

    public static function debugGenerateUpstreamSupply(): void
    {
        try {
            $date = trim((string)($_GET['date'] ?? ''));
            $limit = (int)($_GET['limit'] ?? 50);
            $apply = ((int)($_GET['apply'] ?? 0) === 1);
            $productIdsRaw = trim((string)($_GET['product_ids'] ?? ''));
            $productIds = [];
            if ($productIdsRaw !== '') {
                $productIds = array_values(array_filter(
                    array_map(static fn(string $v): int => (int)trim($v), explode(',', $productIdsRaw)),
                    static fn(int $v): bool => $v > 0
                ));
            }

            $result = UpstreamSupplyGenerationService::generate($date !== '' ? $date : null, $limit, $productIds, !$apply);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'date' => $date !== '' ? $date : null,
                'product_ids' => $productIds,
                'applied' => $apply,
                'result' => $result,
            ], JSON_UNESCAPED_SLASHES);
            return;
        } catch (\Throwable $e) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
            echo json_encode([
                'ok' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES);
            return;
        }
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['mfg_demands_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'mfg_demands_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }
}
