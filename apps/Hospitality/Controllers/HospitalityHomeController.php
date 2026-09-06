<?php
declare(strict_types=1);

namespace Apps\Hospitality\Controllers;

final class HospitalityHomeController
{
    public static function index($view): void
    {
        $view->render('hospitality::home.php', [
            'pageTitle' => t('hospitality.app.name'),
        ]);
    }
}
