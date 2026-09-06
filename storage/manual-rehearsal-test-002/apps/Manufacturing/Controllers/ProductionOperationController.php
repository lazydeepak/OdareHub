<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

use App\Core\Auth;
use App\Core\View;
use Apps\Manufacturing\Services\ProductionOperationService;

final class ProductionOperationController
{
    public static function index(View $view): void
    {
        Auth::bootSession();
        $date = trim((string)($_GET['date'] ?? date('Y-m-d')));
        $machineId = (int)($_GET['machine_id'] ?? 0);

        $payload = ProductionOperationService::build($date, $machineId, Auth::user());

        $view->render('manufacturing::execution/production_operation.php', [
            'pageTitle' => 'Production Operation',
            'payload' => $payload,
        ]);
    }
}
