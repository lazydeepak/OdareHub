<?php
declare(strict_types=1);

namespace Apps\Hospitality\Controllers;

use App\Core\Auth;
use App\Core\AclPolicy;

/**
 * Server-side permission guard for Hospitality surfaces.
 * hospitality.view = read/list pages. hospitality.manage = reserved for future
 * write-capable surfaces (slice 5+); no write routes exist yet.
 */
final class HospitalityAccess
{
    public static function requireView($view): void
    {
        if (AclPolicy::can('hospitality.view', Auth::user())) {
            return;
        }

        http_response_code(403);
        $view->render('hospitality::forbidden.php', [
            'pageTitle' => t('hospitality.forbidden.title'),
        ]);
        exit;
    }

    public static function requireManage($view): void
    {
        if (AclPolicy::can('hospitality.manage', Auth::user())) {
            return;
        }

        http_response_code(403);
        $view->render('hospitality::forbidden.php', [
            'pageTitle' => t('hospitality.forbidden.manage_title'),
        ]);
        exit;
    }
}
