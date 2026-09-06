<?php
declare(strict_types=1);

namespace Apps\Procurement\Controllers;

use App\Core\Auth;
use Apps\Procurement\Services\ProcurementOverviewService;

final class ProcurementDashboardController
{
    public static function dashboard($view): void
    {
        $summary = ProcurementOverviewService::summary();
        $view->render('procurement::dashboard.php', [
            'pageTitle' => 'Procurement',
            'summary' => $summary,
            'latest_requests' => ProcurementOverviewService::latestRequests(8),
            'latest_orders' => ProcurementOverviewService::latestOrders(8),
            'latest_receipts' => ProcurementOverviewService::latestReceipts(8),
            'recent_transitions' => ProcurementOverviewService::recentTransitions(24),
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function requests($view): void
    {
        $view->render('procurement::requests.php', [
            'pageTitle' => 'Procurement Requests',
            'rows' => ProcurementOverviewService::latestRequests(40),
            'mfg_demands' => ProcurementOverviewService::latestManufacturingDemands(40),
            'suppliers' => ProcurementOverviewService::supplierOptions(120),
            'summary' => ProcurementOverviewService::summary(),
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function orders($view): void
    {
        $view->render('procurement::orders.php', [
            'pageTitle' => 'Purchase Orders',
            'rows' => ProcurementOverviewService::latestOrders(40),
            'summary' => ProcurementOverviewService::summary(),
            'suppliers' => ProcurementOverviewService::supplierOptions(120),
            'requests' => ProcurementOverviewService::latestRequests(120),
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function receipts($view): void
    {
        $view->render('procurement::receipts.php', [
            'pageTitle' => 'Receipts',
            'rows' => ProcurementOverviewService::latestReceipts(40),
            'summary' => ProcurementOverviewService::summary(),
            'orders' => ProcurementOverviewService::latestOrders(120),
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function createSupplier(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $supplierName = trim((string)($_POST['supplier_name'] ?? ''));
        if ($supplierName === '') {
            self::redirect('/apps/procurement', 'err', 'Supplier name is required.');
        }

        try {
            ProcurementOverviewService::createSupplier($_POST, (string)(Auth::user()['email'] ?? ''));
            self::redirect('/apps/procurement', 'ok', 'Supplier created.');
        } catch (\Throwable $e) {
            self::redirect('/apps/procurement', 'err', $e->getMessage());
        }
    }

    public static function createRequest(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $qty = (float)($_POST['requested_qty'] ?? 0);
        if ($qty <= 0) {
            self::redirect('/apps/procurement/requests', 'err', 'Requested quantity must be greater than zero.');
        }

        try {
            ProcurementOverviewService::createRequest($_POST, (string)(Auth::user()['email'] ?? ''));
            self::redirect('/apps/procurement/requests', 'ok', 'Procurement request created.');
        } catch (\Throwable $e) {
            self::redirect('/apps/procurement/requests', 'err', $e->getMessage());
        }
    }

    public static function intakeManufacturingRequest(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $demandId = (int)($_POST['demand_id'] ?? 0);
        if ($demandId <= 0) {
            self::redirect('/apps/procurement/requests', 'err', 'Invalid manufacturing demand id.');
        }

        try {
            $result = ProcurementOverviewService::intakeFromManufacturingDemand($demandId, (string)(Auth::user()['email'] ?? ''));
            if ((bool)($result['already_exists'] ?? false)) {
                self::redirect('/apps/procurement/requests', 'ok', 'Manufacturing demand was already linked to an existing procurement request.');
            }
            self::redirect('/apps/procurement/requests', 'ok', 'Manufacturing demand imported into procurement request.');
        } catch (\Throwable $e) {
            self::redirect('/apps/procurement/requests', 'err', $e->getMessage());
        }
    }

    public static function approveRequest(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            self::redirect('/apps/procurement/requests', 'err', 'Invalid request id.');
        }

        try {
            ProcurementOverviewService::setRequestStatus($id, 'approved', (string)(Auth::user()['email'] ?? ''));
            self::redirect('/apps/procurement/requests', 'ok', 'Request approved.');
        } catch (\Throwable $e) {
            self::redirect('/apps/procurement/requests', 'err', $e->getMessage());
        }
    }

    public static function createOrder(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        try {
            ProcurementOverviewService::createOrder($_POST, (string)(Auth::user()['email'] ?? ''));
            self::redirect('/apps/procurement/orders', 'ok', 'Purchase order created.');
        } catch (\Throwable $e) {
            self::redirect('/apps/procurement/orders', 'err', $e->getMessage());
        }
    }

    public static function createOrderFromRequest(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $requestId = (int)($_POST['request_id'] ?? 0);
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $redirectTo = (string)($_POST['redirect_to'] ?? '/apps/procurement/orders');
        if ($redirectTo === '' || strncmp($redirectTo, '/apps/procurement', 17) !== 0) {
            $redirectTo = '/apps/procurement/orders';
        }

        if ($requestId <= 0) {
            self::redirect($redirectTo, 'err', 'Invalid request id.');
        }

        try {
            $result = ProcurementOverviewService::createOrderFromApprovedRequest($requestId, $supplierId > 0 ? $supplierId : null, (string)(Auth::user()['email'] ?? ''));
            if ((bool)($result['already_exists'] ?? false)) {
                self::redirect($redirectTo, 'ok', 'PO already exists for this request.');
            }
            self::redirect($redirectTo, 'ok', 'PO created from approved request.');
        } catch (\Throwable $e) {
            self::redirect($redirectTo, 'err', $e->getMessage());
        }
    }

    public static function issueOrder(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            self::redirect('/apps/procurement/orders', 'err', 'Invalid order id.');
        }

        try {
            ProcurementOverviewService::setOrderStatus($id, 'issued', (string)(Auth::user()['email'] ?? ''));
            self::redirect('/apps/procurement/orders', 'ok', 'Purchase order issued.');
        } catch (\Throwable $e) {
            self::redirect('/apps/procurement/orders', 'err', $e->getMessage());
        }
    }

    public static function createReceipt(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $qty = (float)($_POST['received_qty'] ?? 0);
        if ($qty <= 0) {
            self::redirect('/apps/procurement/receipts', 'err', 'Received quantity must be greater than zero.');
        }

        try {
            ProcurementOverviewService::createReceipt($_POST, (string)(Auth::user()['email'] ?? ''));
            self::redirect('/apps/procurement/receipts', 'ok', 'Receipt created.');
        } catch (\Throwable $e) {
            self::redirect('/apps/procurement/receipts', 'err', $e->getMessage());
        }
    }

    public static function postReceipt(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            self::redirect('/apps/procurement/receipts', 'err', 'Invalid receipt id.');
        }

        try {
            ProcurementOverviewService::setReceiptStatus($id, 'posted', (string)(Auth::user()['email'] ?? ''));
            self::redirect('/apps/procurement/receipts', 'ok', 'Receipt posted.');
        } catch (\Throwable $e) {
            self::redirect('/apps/procurement/receipts', 'err', $e->getMessage());
        }
    }

    private static function redirect(string $path, string $key, string $message): void
    {
        header('Location: ' . $path . '?' . $key . '=' . rawurlencode($message));
        exit;
    }
}
