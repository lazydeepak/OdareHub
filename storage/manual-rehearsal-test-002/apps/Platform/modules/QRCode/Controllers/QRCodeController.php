<?php
declare(strict_types=1);

namespace Plugins\QRCode\Controllers;

use App\Core\DB;
use App\Core\View;
use Plugins\Coverage\Services\CoverageService;

final class QRCodeController
{
    public static function productLabel(View $view, int $productId, array $options = []): void
    {
        if (!self::tableExists('products')) {
            self::flash('err', 'Products module is not available for QR label generation.');
            header('Location: /');
            exit;
        }

        $product = self::findProduct($productId, '');
        if (!$product) {
            self::flash('err', 'Product not found for label generation.');
            header('Location: /products');
            exit;
        }

        $copies = max(1, min(48, (int)($options['copies'] ?? 8) > 0 ? (int)($options['copies'] ?? 8) : 8));
        $labelOptions = [
            'production_date' => trim((string)($options['production_date'] ?? '')) ?: date('Y-m-d'),
            'serial_number' => trim((string)($options['serial_number'] ?? '')),
            'machine_no' => trim((string)($options['machine_no'] ?? '')),
            'case_number' => trim((string)($options['case_number'] ?? '')),
            'include_date' => !empty($options['include_date']),
            'include_serial_number' => !empty($options['include_serial_number']),
            'include_machine_no' => !empty($options['include_machine_no']),
            'include_case_number' => !empty($options['include_case_number']),
            'include_qty_per_case' => !empty($options['include_qty_per_case']),
            'include_case_spec' => !empty($options['include_case_spec']),
            'include_cases_per_pallet' => !empty($options['include_cases_per_pallet']),
        ];

        $scanUrl = self::baseUrl() . '/qr/product/scan?product_id=' . (int)$product['id'] . '&part_number=' . rawurlencode((string)$product['parts_number']);
        $qrImageSrc = self::qrImageSrc($scanUrl, 180);
        $view->render('QRCode::product_label.php', [
            'pageTitle' => 'Product Label A4',
            'product' => $product,
            'copies' => $copies,
            'labelOptions' => $labelOptions,
            'scanUrl' => $scanUrl,
            'qrImageSrc' => $qrImageSrc,
        ]);
    }

    public static function scanProduct(View $view, int $productId, string $partNumber): void
    {
        if (!self::tableExists('products')) {
            $view->render('QRCode::scan_result.php', [
                'pageTitle' => 'QR Scan',
                'product' => null,
                'error' => 'Products module is not available for QR scan resolution.',
            ]);
            return;
        }

        $product = self::findProduct($productId, $partNumber);
        if (!$product) {
            $view->render('QRCode::scan_result.php', [
                'pageTitle' => 'QR Scan',
                'product' => null,
                'error' => 'Product not found for scanned QR code.',
            ]);
            return;
        }

        $view->render('QRCode::scan_result.php', [
            'pageTitle' => 'QR Scan',
            'product' => $product,
            'error' => '',
        ]);
    }

    public static function stockIndex(View $view): void
    {
        $rows = [];
        if (self::tableExists('products')) {
            $rows = DB::fetchAll(
                'SELECT s.*, p.parts_name, p.parts_number
                 FROM qr_stock_updates s
                 INNER JOIN products p ON p.id = s.product_id
                 ORDER BY s.created_at DESC, s.id DESC
                 LIMIT 200'
            );
        }

        $view->render('QRCode::stock_index.php', [
            'pageTitle' => 'QR Stock Updates',
            'rows' => $rows,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err') ?: (!self::tableExists('products') ? 'Products module is not available for stock-linked QR workflows.' : ''),
        ]);
    }

    public static function stockAddForm(View $view): void
    {
        $old = $_SESSION['qr_stock_old'] ?? [];
        if ((int)($old['product_id'] ?? 0) <= 0 && (int)($_GET['product_id'] ?? 0) > 0) {
            $old['product_id'] = (int)$_GET['product_id'];
        }
        unset($_SESSION['qr_stock_old']);

        $view->render('QRCode::stock_add.php', [
            'pageTitle' => 'Add Stock Update',
            'products' => self::products(),
            'old' => $old,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function stockCreate(array $input): void
    {
        if (!self::tableExists('products')) {
            self::flash('err', 'Products module is not available for QR stock updates.');
            header('Location: /qr/stock/add');
            exit;
        }

        $data = [
            'product_id' => (int)($input['product_id'] ?? 0),
            'movement_type' => trim((string)($input['movement_type'] ?? 'ADJUST')),
            'qty' => (float)($input['qty'] ?? 0),
            'reference_no' => trim((string)($input['reference_no'] ?? '')),
            'notes' => trim((string)($input['notes'] ?? '')),
        ];
        $_SESSION['qr_stock_old'] = $data;

        if ($data['product_id'] <= 0 || $data['qty'] <= 0 || !in_array($data['movement_type'], ['IN', 'OUT', 'ADJUST'], true)) {
            self::flash('err', 'Product, movement type, and positive quantity are required.');
            header('Location: /qr/stock/add' . ($data['product_id'] > 0 ? '?product_id=' . $data['product_id'] : ''));
            exit;
        }

        $db = DB::conn();
        $db->begin_transaction();
        try {
            DB::query(
                'INSERT INTO qr_stock_updates (product_id, movement_type, qty, reference_no, notes) VALUES (?,?,?,?,?)',
                [$data['product_id'], $data['movement_type'], $data['qty'], $data['reference_no'], $data['notes']]
            );

            $qrUpdateId = (int)$db->insert_id;

            if (self::hasLedgerTable()) {
                self::postLedgerEntry(
                    $data['product_id'],
                    $data['movement_type'],
                    self::movementDelta($data['movement_type'], $data['qty']),
                    $data['reference_no'],
                    'QRCode',
                    $qrUpdateId,
                    $data['notes']
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            self::flash('err', 'Stock update failed: ' . $e->getMessage());
            header('Location: /qr/stock/add' . ($data['product_id'] > 0 ? '?product_id=' . $data['product_id'] : ''));
            exit;
        }

        unset($_SESSION['qr_stock_old']);
        self::recalculateCoverage([$data['product_id']]);
        self::flash('ok', self::hasLedgerTable() ? 'Stock update saved and posted to ledger.' : 'Stock update saved.');
    }

    private static function products(): array
    {
        if (!self::tableExists('products')) {
            return [];
        }
        return DB::fetchAll('SELECT id, parts_name, parts_number FROM products WHERE is_active=1 ORDER BY parts_name ASC LIMIT 1000');
    }

    private static function findProduct(int $productId, string $partNumber): ?array
    {
        if (!self::tableExists('products')) {
            return null;
        }
        if ($productId > 0) {
            return DB::fetchOne('SELECT * FROM products WHERE id=? LIMIT 1', [$productId]);
        }
        $partNumber = trim($partNumber);
        if ($partNumber !== '') {
            return DB::fetchOne('SELECT * FROM products WHERE parts_number=? LIMIT 1', [$partNumber]);
        }
        return null;
    }

    private static function baseUrl(): string
    {
        $configured = trim((string)getenv('ERP_APP_URL'));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '')));
        if ($host === '') {
            $host = '127.0.0.1';
        }

        return $scheme . '://' . $host;
    }

    private static function qrImageSrc(string $text, int $size): string
    {
        $builderClass = 'Endroid\\QrCode\\Builder\\Builder';
        if (class_exists($builderClass)) {
            try {
                $result = $builderClass::create()
                    ->data($text)
                    ->size($size)
                    ->margin(8)
                    ->build();
                return $result->getDataUri();
            } catch (\Throwable $e) {
            }
        }

        return 'https://quickchart.io/qr?size=' . max(80, min(400, $size)) . '&text=' . rawurlencode($text);
    }

    private static function hasLedgerTable(): bool
    {
        return DB::fetchOne("SHOW TABLES LIKE 'stock_ledger_entries'") !== null;
    }

    private static function movementDelta(string $movementType, float $qty): float
    {
        if ($movementType === 'OUT') {
            return -abs($qty);
        }
        if ($movementType === 'IN') {
            return abs($qty);
        }
        return $qty;
    }

    private static function postLedgerEntry(int $productId, string $movementType, float $qtyDelta, string $referenceNo, string $sourceModule, int $sourceId, string $notes): void
    {
        $currentBalanceRow = DB::fetchOne('SELECT COALESCE(SUM(qty_delta), 0) AS balance FROM stock_ledger_entries WHERE product_id = ?', [$productId]);
        $currentBalance = (float)($currentBalanceRow['balance'] ?? 0);
        $balanceAfter = $currentBalance + $qtyDelta;

        DB::query(
            'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
            [$productId, $movementType, $qtyDelta, $balanceAfter, $referenceNo, $sourceModule, $sourceId, $notes]
        );
    }

    private static function tableExists(string $table): bool
    {
        return DB::fetchOne(
            'SELECT 1 AS present FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
            [$table]
        ) !== null;
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['qr_code_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $sessionKey = 'qr_code_flash_' . $key;
        $value = (string)($_SESSION[$sessionKey] ?? '');
        unset($_SESSION[$sessionKey]);
        return $value;
    }

    private static function recalculateCoverage(array $productIds): void
    {
        try {
            CoverageService::recalculateForProducts($productIds);
        } catch (\Throwable $e) {
            self::flash('err', 'Coverage refresh failed: ' . $e->getMessage());
        }
    }
}
