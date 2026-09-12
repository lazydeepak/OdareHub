<?php

declare(strict_types=1);

namespace Apps\Manufacturing\Modules\Bom\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use Apps\Manufacturing\Modules\Bom\BomService;

final class BomController
{
    public static function index(View $view): void
    {
        BomService::ensureSchema();

        $status = strtolower(trim((string)($_GET['status'] ?? '')));
        $itemRef = (int)($_GET['item_ref'] ?? 0);
        $onlyActive = ((string)($_GET['only_active'] ?? '') === '1');

        self::enforceView();

        $view->render('Bom::index.php', [
            'pageTitle' => 'BOM / Recipes',
            'rows' => BomService::listBoms($status, $itemRef, $onlyActive),
            'items' => BomService::referenceableItems(),
            'issues' => BomService::integrityIssues(),
            'status' => $status,
            'item_ref' => $itemRef,
            'only_active' => $onlyActive,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function detail(View $view, int $id): void
    {
        BomService::ensureSchema();
        self::enforceView();

        if ($id <= 0) {
            self::flash('err', 'bom.error.invalid_id');
            header('Location: /apps/manufacturing/bom');
            exit;
        }

        $payload = BomService::bomDetail($id);
        if (!$payload) {
            self::flash('err', 'bom.error.not_found');
            header('Location: /apps/manufacturing/bom');
            exit;
        }

        $view->render('Bom::detail.php', [
            'pageTitle' => 'BOM Detail',
            'payload' => $payload,
            'items' => BomService::referenceableItems(),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function form(View $view): void
    {
        BomService::ensureSchema();
        self::enforceView();

        $view->render('Bom::form.php', [
            'pageTitle' => 'New BOM',
            'items' => BomService::referenceableItems(),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function create(array $input): void
    {
        try {
            self::enforceView();
            $id = BomService::createBom(
                (int)($input['finished_item_ref'] ?? 0),
                (string)($input['version'] ?? '1.0'),
                (int)($input['revision'] ?? 1),
                trim((string)($input['notes'] ?? '')),
                ['user' => BomService::currentUserLabel()]
            );
            self::flash('ok', 'bom.flash.created');
            header('Location: /apps/manufacturing/bom/detail?id=' . $id);
            exit;
        } catch (\Throwable $e) {
            self::flash('err', self::label($e->getMessage()));
            header('Location: /apps/manufacturing/bom/add');
            exit;
        }
    }

    public static function updateHeader(array $input): void
    {
        try {
            self::enforceView();
            $id = (int)($input['id'] ?? 0);
            BomService::updateBomHeader($id, trim((string)($input['notes'] ?? '')), BomService::currentUserLabel());
            self::flash('ok', 'bom.flash.updated');
        } catch (\Throwable $e) {
            self::flash('err', self::label($e->getMessage()));
        }
        header('Location: /apps/manufacturing/bom/detail?id=' . $id);
        exit;
    }

    public static function transition(array $input): void
    {
        try {
            self::enforceView();
            $id = (int)($input['id'] ?? 0);
            $action = strtolower(trim((string)($input['action'] ?? '')));
            BomService::transitionBom($id, $action, []);
            self::flash('ok', 'bom.flash.transitioned');
        } catch (\Throwable $e) {
            self::flash('err', self::label($e->getMessage()));
        }
        header('Location: /apps/manufacturing/bom/detail?id=' . $id);
        exit;
    }

    public static function addLine(array $input): void
    {
        try {
            self::enforceView();
            $id = (int)($input['id'] ?? 0);
            BomService::addLine(
                $id,
                (int)($input['component_item_ref'] ?? 0),
                (float)($input['quantity'] ?? 1),
                (string)($input['unit'] ?? 'each'),
                (int)($input['sequence'] ?? 0),
                []
            );
            self::flash('ok', 'bom.flash.line_added');
        } catch (\Throwable $e) {
            self::flash('err', self::label($e->getMessage()));
        }
        header('Location: /apps/manufacturing/bom/detail?id=' . $id);
        exit;
    }

    public static function updateLine(array $input): void
    {
        try {
            self::enforceView();
            $id = (int)($input['id'] ?? 0);
            BomService::updateLine(
                (int)($input['line_id'] ?? 0),
                $id,
                (float)($input['quantity'] ?? 1),
                (string)($input['unit'] ?? 'each'),
                (int)($input['sequence'] ?? 0),
                []
            );
            self::flash('ok', 'bom.flash.line_updated');
        } catch (\Throwable $e) {
            self::flash('err', self::label($e->getMessage()));
        }
        header('Location: /apps/manufacturing/bom/detail?id=' . $id);
        exit;
    }

    public static function deleteLine(array $input): void
    {
        try {
            self::enforceView();
            $id = (int)($input['id'] ?? 0);
            BomService::deleteLine((int)($input['line_id'] ?? 0), $id, []);
            self::flash('ok', 'bom.flash.line_deleted');
        } catch (\Throwable $e) {
            self::flash('err', self::label($e->getMessage()));
        }
        header('Location: /apps/manufacturing/bom/detail?id=' . $id);
        exit;
    }

    private static function enforceView(): void
    {
        Auth::requireAppAccess('manufacturing');
    }

    private static function label(string $msg): string
    {
        if ($msg === '' || !str_starts_with($msg, 'bom.')) {
            return $msg;
        }
        $t = t($msg);
        return $t === $msg ? $msg : $t;
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['mfg_bom_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'mfg_bom_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }
}