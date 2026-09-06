<?php
declare(strict_types=1);

namespace Plugins\MaterialManagement\Controllers;

use App\Core\Auth;
use App\Core\View;
use Plugins\MaterialManagement\Services\MaterialAccessService;
use Plugins\MaterialManagement\Services\MaterialManagementService;

final class MaterialManagementController
{
    public static function dashboard(View $view): void
    {
        $summary = MaterialManagementService::summary();
        $orderRows = MaterialManagementService::orderRows();
        $stockRows = MaterialManagementService::stockRows();

        $openOrderCount = 0;
        $delayedOrderCount = 0;
        foreach ($orderRows as $row) {
            $remainingQty = (float)($row['remaining_qty'] ?? 0);
            $status = strtolower(trim((string)($row['status'] ?? 'planned')));
            if ($remainingQty > 0.0001 && !in_array($status, ['received', 'cancelled'], true)) {
                $openOrderCount++;
            }
            if (!empty($row['has_delayed_inbound']) || !empty($row['late_incoming_risk'])) {
                $delayedOrderCount++;
            }
        }

        $view->render('MaterialManagement::materials/dashboard.php', array_merge(
            self::chrome('dashboard', 'Materials Workspace', 'Small truthful hub for the live Material v1 surfaces only.'),
            [
                'summary' => $summary,
                'openOrderCount' => $openOrderCount,
                'delayedOrderCount' => $delayedOrderCount,
                'trackedStockCount' => count($stockRows),
            ]
        ));
    }

    public static function stock(View $view): void
    {
        $planningRows = MaterialManagementService::planningRows();
        $planningByMaterial = [];
        foreach ($planningRows as $row) {
            $planningByMaterial[(int)($row['material_id'] ?? 0)] = $row;
        }

        $query = trim((string)($_GET['q'] ?? ''));
        $statusFilter = trim((string)($_GET['status'] ?? ''));
        $materialIdFilter = (int)($_GET['material_id'] ?? 0);
        $stockRows = [];

        foreach (MaterialManagementService::stockRows() as $row) {
            $materialId = (int)($row['id'] ?? 0);
            $planning = $planningByMaterial[$materialId] ?? [];
            $coverageStatus = (string)($planning['coverage_status'] ?? 'Balanced');
            $searchBlob = strtolower(implode(' ', array_filter([
                (string)($row['material_name'] ?? ''),
                (string)($row['material_code'] ?? ''),
                (string)($row['material_number'] ?? ''),
                (string)($row['supplier_name'] ?? ''),
                (string)($row['storage_location'] ?? ''),
            ])));

            if ($query !== '' && !str_contains($searchBlob, strtolower($query))) {
                continue;
            }
            if ($materialIdFilter > 0 && $materialId !== $materialIdFilter) {
                continue;
            }
            if ($statusFilter !== '' && $coverageStatus !== $statusFilter) {
                continue;
            }

            $row['coverage_status'] = $coverageStatus;
            $stockRows[] = $row;
        }

        $view->render('MaterialManagement::materials/stock.php', array_merge(
            self::chrome('stock', 'Material Stock', 'Live stock position across on hand, reserved, available, and confirmed incoming inventory.'),
            [
                'query' => $query,
                'statusFilter' => $statusFilter,
                'materialIdFilter' => $materialIdFilter,
                'statusOptions' => ['Critical', 'Low', 'Balanced', 'High', 'Overflow Risk'],
                'stockRows' => $stockRows,
            ]
        ));
    }

    public static function orders(View $view): void
    {
        $view->render('MaterialManagement::materials/orders.php', array_merge(
            self::chrome('orders', 'Material Orders', 'Create and track inbound orders that feed the receipt workflow.'),
            [
                'materialOptions' => MaterialManagementService::materialOptions(),
                'orderRows' => MaterialManagementService::orderRows(),
                'orderStatuses' => ['planned', 'ordered', 'partial', 'received', 'delayed', 'cancelled'],
            ]
        ));
    }

    public static function receipt(View $view): void
    {
        $receiptMode = self::normalizeReceiptMode((string)($_GET['mode'] ?? 'all'));
        $allMaterialOptions = MaterialManagementService::materialOptions();
        $materialOptions = array_values(array_filter(
            $allMaterialOptions,
            static fn(array $row): bool => self::matchesReceiptMode($row, $receiptMode)
        ));

        $orderRows = MaterialManagementService::orderRows();
        $openOrderRows = [];
        foreach ($orderRows as $row) {
            $remainingQty = (float)($row['remaining_qty'] ?? 0);
            $status = strtolower(trim((string)($row['status'] ?? 'planned')));
            if ($remainingQty <= 0.0001 || in_array($status, ['received', 'cancelled'], true)) {
                continue;
            }
            if (!self::matchesReceiptMode($row, $receiptMode)) {
                continue;
            }
            $openOrderRows[] = $row;
        }

        $receiptOrderId = (int)($_GET['order_id'] ?? 0);
        $receiptMaterialId = (int)($_GET['material_id'] ?? 0);
        $selectedOrder = null;
        foreach ($orderRows as $row) {
            if ((int)($row['id'] ?? 0) !== $receiptOrderId) {
                continue;
            }
            $selectedOrder = $row;
            if ($receiptMaterialId <= 0) {
                $receiptMaterialId = (int)($row['material_id'] ?? 0);
            }
            break;
        }

        if ($receiptMaterialId > 0) {
            $hasMaterial = false;
            foreach ($materialOptions as $option) {
                if ((int)($option['id'] ?? 0) === $receiptMaterialId) {
                    $hasMaterial = true;
                    break;
                }
            }
            if (!$hasMaterial) {
                foreach ($allMaterialOptions as $option) {
                    if ((int)($option['id'] ?? 0) === $receiptMaterialId) {
                        $materialOptions[] = $option;
                        break;
                    }
                }
            }
        }

        $stockByMaterial = [];
        foreach (MaterialManagementService::stockRows() as $row) {
            $stockByMaterial[(int)($row['id'] ?? 0)] = $row;
        }
        $selectedStock = $receiptMaterialId > 0 ? ($stockByMaterial[$receiptMaterialId] ?? null) : null;

        $receivedQtyDefault = abs((float)($_GET['qty'] ?? 0));
        if ($receivedQtyDefault <= 0.0001 && is_array($selectedOrder)) {
            $receivedQtyDefault = max(0.0, (float)($selectedOrder['remaining_qty'] ?? 0));
        }

        $receivedUnitDefault = trim((string)($_GET['unit'] ?? ''));
        if ($receivedUnitDefault === '' && is_array($selectedOrder)) {
            $receivedUnitDefault = trim((string)($selectedOrder['uom'] ?? $selectedOrder['unit'] ?? ''));
        }
        if ($receivedUnitDefault === '' && is_array($selectedStock)) {
            $receivedUnitDefault = trim((string)($selectedStock['uom'] ?? $selectedStock['unit'] ?? ''));
        }
        if ($receivedUnitDefault === '') {
            foreach ($materialOptions as $option) {
                if ((int)($option['id'] ?? 0) !== $receiptMaterialId) {
                    continue;
                }
                $receivedUnitDefault = trim((string)($option['uom'] ?? $option['unit'] ?? ''));
                break;
            }
        }
        if ($receivedUnitDefault === '') {
            $receivedUnitDefault = 'kg';
        }

        $receivedConversionRateDefault = abs((float)($_GET['conversion_rate'] ?? 0));
        if ($receivedConversionRateDefault <= 0.0001 && is_array($selectedOrder)) {
            $orderUnit = trim((string)($selectedOrder['uom'] ?? $selectedOrder['unit'] ?? ''));
            $orderPackSize = abs((float)($selectedOrder['pack_size'] ?? 0));
            if ($orderUnit !== '' && strcasecmp($orderUnit, $receivedUnitDefault) !== 0 && $orderPackSize > 0) {
                $receivedConversionRateDefault = $orderPackSize;
            }
        }
        if ($receivedConversionRateDefault <= 0.0001) {
            $receivedConversionRateDefault = 1.0;
        }

        $projection = null;
        if (is_array($selectedStock)) {
            $projection = [
                'on_hand_qty' => (float)($selectedStock['on_hand_qty'] ?? 0),
                'reserved_qty' => (float)($selectedStock['reserved_qty'] ?? 0),
                'available_qty' => (float)($selectedStock['available_qty'] ?? 0),
                'incoming_qty' => (float)($selectedStock['incoming_qty'] ?? 0),
                'projected_on_hand_qty' => (float)($selectedStock['on_hand_qty'] ?? 0) + $receivedQtyDefault,
                'projected_available_qty' => (float)($selectedStock['available_qty'] ?? 0) + $receivedQtyDefault,
            ];
        }

        $receivedItemClassDefault = match ($receiptMode) {
            'resin' => 'resin_jairo_material',
            'functional' => 'functional_part',
            default => 'material',
        };

        $view->render('MaterialManagement::materials/receipt.php', array_merge(
            self::chrome('receipt', 'Material Receipt', 'Canonical stock-in workflow for recording received material against open inbound supply.'),
            [
                'materialOptions' => $materialOptions,
                'openOrderRows' => $openOrderRows,
                'selectedOrder' => $selectedOrder,
                'selectedStock' => $selectedStock,
                'receivedQtyDefault' => $receivedQtyDefault,
                'projection' => $projection,
                'receiptMaterialId' => $receiptMaterialId,
                'receiptMode' => $receiptMode,
                'receiptModeLabel' => self::receiptModeLabel($receiptMode),
                'receivedItemClassDefault' => $receivedItemClassDefault,
                'receivedUnitDefault' => $receivedUnitDefault,
                'receivedConversionRateDefault' => $receivedConversionRateDefault,
            ]
        ));
    }

    public static function master(View $view): void
    {
        $view->render('MaterialManagement::materials/master.php', array_merge(
            self::chrome('master', 'Material Master', 'Complete catalog of all registered materials with stock policy parameters, cost, and supplier data.'),
            [
                'materials' => MaterialManagementService::materials(),
            ]
        ));
    }

    public static function mapping(View $view): void
    {
        $qFilter = trim((string)($_GET['q'] ?? ''));
        $productIdFilter = (int)($_GET['product_id'] ?? 0);
        $materialIdFilter = (int)($_GET['material_id'] ?? 0);
        $mappingRows = MaterialManagementService::mappingRows();

        if ($qFilter !== '' || $productIdFilter > 0 || $materialIdFilter > 0) {
            $mappingRows = array_values(array_filter($mappingRows, static function (array $row) use ($qFilter, $productIdFilter, $materialIdFilter): bool {
                if ($productIdFilter > 0 && (int)($row['product_id'] ?? 0) !== $productIdFilter) {
                    return false;
                }
                if ($materialIdFilter > 0 && (int)($row['material_id'] ?? 0) !== $materialIdFilter) {
                    return false;
                }
                if ($qFilter !== '') {
                    $blob = strtolower(implode(' ', [
                        (string)($row['parts_name'] ?? ''),
                        (string)($row['material_name'] ?? ''),
                        (string)($row['material_number'] ?? ''),
                        (string)($row['material_code'] ?? ''),
                    ]));
                    if (!str_contains($blob, strtolower($qFilter))) {
                        return false;
                    }
                }
                return true;
            }));
        }

        $view->render('MaterialManagement::materials/mapping.php', array_merge(
            self::chrome('mapping', 'Part–Material Mapping', 'Bill of materials linkage showing which raw materials are consumed per finished part.'),
            [
                'mappingRows' => $mappingRows,
                'productOptions' => MaterialManagementService::productOptions(),
                'materialOptions' => MaterialManagementService::materialOptions(),
                'qFilter' => $qFilter,
                'productIdFilter' => $productIdFilter,
                'materialIdFilter' => $materialIdFilter,
            ]
        ));
    }

    public static function coverage(View $view): void
    {
        $statusFilter = trim((string)($_GET['status'] ?? ''));
        $qFilter = trim((string)($_GET['q'] ?? ''));
        $coverageRows = MaterialManagementService::coverageRows();

        if ($statusFilter !== '' || $qFilter !== '') {
            $coverageRows = array_values(array_filter($coverageRows, static function (array $row) use ($statusFilter, $qFilter): bool {
                if ($statusFilter !== '' && (string)($row['coverage_status'] ?? 'Balanced') !== $statusFilter) {
                    return false;
                }
                if ($qFilter !== '') {
                    $blob = strtolower(implode(' ', [
                        (string)($row['material_name'] ?? ''),
                        (string)($row['material_number'] ?? ''),
                        (string)($row['supplier_name'] ?? ''),
                    ]));
                    if (!str_contains($blob, strtolower($qFilter))) {
                        return false;
                    }
                }
                return true;
            }));
        }

        $view->render('MaterialManagement::materials/coverage.php', array_merge(
            self::chrome('coverage', 'Material Coverage', '30-day demand vs supply analysis, shortage risk, surplus, and recommended actions per material.'),
            [
                'coverageRows' => $coverageRows,
                'statusFilter' => $statusFilter,
                'qFilter' => $qFilter,
            ]
        ));
    }

    public static function partStatus(View $view): void { self::renderDeferred($view, 'Part Status'); }

    public static function detail(View $view, int $materialId): void
    {
        $detail = MaterialManagementService::materialDetail($materialId);
        if ($detail === null) {
            http_response_code(404);
            $view->render('MaterialManagement::materials/detail.php', array_merge(
                self::chrome('detail', 'Material Detail', ''),
                ['material' => [], 'stock' => null, 'coverage' => null, 'planning' => null,
                 'orders' => [], 'capacity' => [], 'mappings' => [], 'reservations' => [], 'recentLedger' => []]
            ));
            return;
        }

        $view->render('MaterialManagement::materials/detail.php', array_merge(
            self::chrome('detail', 'Material Detail', (string)($detail['material']['material_name'] ?? '')),
            [
                'material' => $detail['material'],
                'stock' => $detail['stock'],
                'coverage' => $detail['coverage'],
                'planning' => $detail['planning'],
                'orders' => $detail['orders'],
                'capacity' => $detail['capacity'],
                'mappings' => $detail['mappings'],
                'reservations' => $detail['reservations'],
                'recentLedger' => $detail['recent_ledger'],
            ]
        ));
    }

    public static function planning(View $view): void
    {
        $view->render('MaterialManagement::materials/planning.php', array_merge(
            self::chrome('planning', 'Material Planning', '30-day horizon projections broken into today / 7d / 14d / 30d demand and supply buckets.'),
            [
                'planningRows' => MaterialManagementService::planningRows(),
            ]
        ));
    }

    public static function capacity(View $view): void
    {
        $view->render('MaterialManagement::materials/capacity.php', array_merge(
            self::chrome('capacity', 'Storage Capacity', 'Storage slot utilisation, projected 30-day occupancy, and capacity alert thresholds per material.'),
            [
                'capacityRows' => MaterialManagementService::capacityRows(),
                'materialOptions' => MaterialManagementService::materialOptions(),
            ]
        ));
    }

    public static function cost(View $view): void
    {
        $view->render('MaterialManagement::materials/cost.php', array_merge(
            self::chrome('cost', 'Material Cost', 'Standard cost, stock valuation, and open-order financial exposure per material.'),
            [
                'costRows' => MaterialManagementService::costRows(),
            ]
        ));
    }

    public static function createMaterial(array $input): void
    {
        MaterialManagementService::createMaterial($input);
        self::flash('ok', 'Material saved.');
    }

    public static function createMapping(array $input): void
    {
        MaterialManagementService::createMapping($input);
        self::flash('ok', 'Part-material mapping saved.');
    }

    public static function postStockAdjustment(array $input): void
    {
        MaterialManagementService::postLedgerAdjustment($input, (int)(Auth::user()['id'] ?? 0));
        self::flash('ok', 'Stock ledger entry posted.');
    }

    public static function recordReceipt(array $input): void
    {
        MaterialManagementService::recordReceipt($input, (int)(Auth::user()['id'] ?? 0));
        self::flash('ok', 'Material receipt recorded.');
    }

    public static function createOrder(array $input): void
    {
        MaterialManagementService::createOrder($input);
        self::flash('ok', 'Material order saved.');
    }

    public static function createCapacity(array $input): void
    {
        MaterialManagementService::createCapacity($input);
        self::flash('ok', 'Capacity slot saved.');
    }

    public static function fail(\Throwable $e, string $redirect): void
    {
        self::flash('err', $e->getMessage());
        header('Location: ' . $redirect);
        exit;
    }

    /**
     * @return array<string,mixed>
     */
    private static function chrome(string $surfaceKey, string $surfaceTitle, string $surfaceSummary): array
    {
        return [
            'pageTitle' => $surfaceTitle,
            'surfaceKey' => $surfaceKey,
            'surfaceTitle' => $surfaceTitle,
            'surfaceSummary' => $surfaceSummary,
            'v1NavItems' => MaterialAccessService::navigation(),
            'canManage' => MaterialAccessService::canManage(),
            'canViewStock' => MaterialAccessService::canViewStock(),
            'flashOk' => self::pullFlash('ok'),
            'flashErr' => self::pullFlash('err'),
        ];
    }

    private static function renderDeferred(View $view, string $surfaceTitle, ?int $materialId = null): void
    {
        $detailNote = $materialId !== null && $materialId > 0
            ? 'Requested material id: ' . $materialId . '.'
            : '';

        $view->render('MaterialManagement::materials/deferred.php', array_merge(
            self::chrome('deferred', $surfaceTitle, 'This legacy MaterialManagement surface has been intentionally deferred out of Material v1.'),
            [
                'detailNote' => $detailNote,
            ]
        ));
    }

    private static function flash(string $key, string $message): void
    {
        Auth::bootSession();
        $_SESSION['materials_flash_' . $key] = $message;
    }

    private static function pullFlash(string $key): ?string
    {
        Auth::bootSession();
        $sessionKey = 'materials_flash_' . $key;
        $value = $_SESSION[$sessionKey] ?? null;
        unset($_SESSION[$sessionKey]);
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function normalizeReceiptMode(string $mode): string
    {
        $normalized = strtolower(trim($mode));
        return in_array($normalized, ['all', 'resin', 'functional'], true) ? $normalized : 'all';
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function matchesReceiptMode(array $row, string $mode): bool
    {
        if ($mode === 'all') {
            return true;
        }

        $type = strtolower(trim((string)($row['material_type'] ?? '')));
        $subtype = strtolower(trim((string)($row['material_subtype'] ?? '')));
        $name = strtolower(trim((string)($row['material_name'] ?? '')));
        $code = strtolower(trim((string)($row['material_number'] ?? $row['material_code'] ?? '')));
        $blob = trim($type . ' ' . $subtype . ' ' . $name . ' ' . $code);

        if ($mode === 'resin') {
            return str_contains($blob, 'resin');
        }

        if ($mode === 'functional') {
            if (str_contains($blob, 'functional')) {
                return true;
            }
            return in_array($type, ['component', 'semi_finished', 'finished'], true)
                || in_array($subtype, ['component', 'semi_finished', 'finished'], true);
        }

        return true;
    }

    private static function receiptModeLabel(string $mode): string
    {
        return match ($mode) {
            'resin' => 'Resin Received',
            'functional' => 'Functional Received',
            default => 'All Materials Received',
        };
    }
}
