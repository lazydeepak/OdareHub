<?php
declare(strict_types=1);

namespace App\Core {
    final class Auth
    {
        public static function csrfToken(): string
        {
            return 'offline-csrf-token';
        }
    }
}

namespace {
    define('APP_ROOT', dirname(__DIR__, 3));

    $passed = 0;
    $failed = 0;
    $assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
        if ($condition) {
            $passed++;
            return;
        }
        $failed++;
        fwrite(STDERR, "FAIL: {$message}\n");
    };

    $renderer = new class {
        public function tr(string $key, string $fallback, array $params = []): string
        {
            return $fallback;
        }

        /** @param array<string,mixed> $data */
        public function render(array $data): string
        {
            ob_start();
            include APP_ROOT . '/apps/Shell/Views/operator/processing.php';
            return (string)ob_get_clean();
        }
    };

    $baseFocus = [
        'title' => 'Processing',
        'subtitle' => 'Processing queues',
        'authorized' => true,
        'can_mutate' => true,
        'assembly_allowed' => true,
        'qc_allowed' => true,
        'selected_date' => '2026-10-20',
        'selected_product_id' => 42,
        'products' => [
            ['id' => 42, 'parts_name' => 'Part 42', 'parts_number' => 'P-42'],
        ],
        'assembly_rows' => [[
            'id' => 11,
            'product_id' => 42,
            'demand_date' => '2026-10-20',
            'planned_qty' => 5,
            'status' => 'adjusted',
            'part_name' => 'Part 42',
            'part_number' => 'P-42',
        ]],
        'qc_rows' => [
            [
                'id' => 21,
                'product_id' => 42,
                'plan_date' => '2026-10-20',
                'planned_qty' => 5,
                'priority' => 'High',
                'status' => 'system generated',
                'part_name' => 'Part 42',
                'part_number' => 'P-42',
            ],
            [
                'id' => 22,
                'product_id' => 42,
                'plan_date' => '2026-10-20',
                'planned_qty' => 5,
                'priority' => 'High',
                'status' => 'verified by qc',
                'part_name' => 'Part 42',
                'part_number' => 'P-42',
            ],
        ],
    ];

    $rendered = $renderer->render([
        'username' => 'operator',
        'processing_focus' => $baseFocus,
    ]);
    $validReturn = '/u/operator/processing?focus=processing&amp;processing_date=2026-10-20&amp;product_id=42';
    $assert(substr_count($rendered, 'name="redirect_to"') === 3, 'Assembly and both QC status forms render return fields');
    $assert(substr_count($rendered, 'value="' . $validReturn . '"') === 3, 'all status forms preserve the selected date and product');
    $assert(!str_contains($rendered, 'value="/u/operator/processing"'), 'valid status forms do not fall back to the unfiltered page');
    $assert(str_contains($rendered, '&amp;processing_date=2026-10-20&amp;product_id=42'), 'return query is HTML-escaped for rendered form attributes');
    $assert(str_contains($rendered, 'name="demand_id" value="11"'), 'Assembly record identity remains unchanged');
    $assert(str_contains($rendered, 'name="qc_plan_id" value="21"') && str_contains($rendered, 'name="qc_plan_id" value="22"'), 'QC record identities remain unchanged');
    $assert(substr_count($rendered, 'name="csrf"') >= 3, 'status forms retain CSRF fields');

    $invalidDateFocus = $baseFocus;
    $invalidDateFocus['selected_date'] = '2026-02-30';
    $invalidDateRendered = $renderer->render([
        'username' => 'operator',
        'processing_focus' => $invalidDateFocus,
    ]);
    $assert(substr_count($invalidDateRendered, 'value="/u/operator/processing"') === 3, 'invalid date is not propagated into status redirects');
    $assert(!str_contains($invalidDateRendered, 'processing_date=2026-02-30'), 'invalid date query is absent');

    $invalidProductFocus = $baseFocus;
    $invalidProductFocus['selected_product_id'] = 999;
    $invalidProductRendered = $renderer->render([
        'username' => 'operator',
        'processing_focus' => $invalidProductFocus,
    ]);
    $assert(substr_count($invalidProductRendered, 'value="/u/operator/processing"') === 3, 'unlisted product is not propagated into status redirects');
    $assert(!str_contains($invalidProductRendered, 'product_id=999'), 'invalid product query is absent');

    $missingFilterFocus = $baseFocus;
    $missingFilterFocus['selected_date'] = '';
    $missingFilterFocus['selected_product_id'] = 0;
    $missingRendered = $renderer->render([
        'username' => 'operator',
        'processing_focus' => $missingFilterFocus,
    ]);
    $assert(substr_count($missingRendered, 'value="/u/operator/processing"') === 3, 'missing filters safely use the bare Processing path');

    $routes = (string)file_get_contents(APP_ROOT . '/apps/Shell/routes.php');
    $assert(substr_count($routes, '$normalizeOperatorRedirect($normalized, \'processing\',') >= 2, 'Assembly and QC status routes retain redirect normalization');
    $assert(str_contains($routes, 'str_starts_with($candidate, \'//\')'), 'double-slash redirects remain rejected');
    $assert(str_contains($routes, "'/u/' . rawurlencode(\$username)"), 'redirect normalization retains same-user prefix confinement');
    $assert(substr_count($routes, "Auth::requireCsrf((string)(\$_POST['csrf'] ?? ''), \$redirectTo)") >= 2, 'status routes retain CSRF enforcement');
    $assert(str_contains($routes, "authorizeOperatorMutation('assembly_status'") && str_contains($routes, "authorizeOperatorMutation('qc_status'"), 'status routes retain Manufacturing authorization');
    $assert(substr_count($routes, "processing?focus=processing&processing_date=' . rawurlencode(\$planDate)") >= 2, 'creation redirects retain their selected context');

    $view = (string)file_get_contents(APP_ROOT . '/apps/Shell/Views/operator/processing.php');
    $assert(str_contains($view, 'http_build_query'), 'view builds the return query with the URL encoder');
    $assert(str_contains($view, 'PHP_QUERY_RFC3986'), 'view uses RFC3986 query encoding');
    $assert(str_contains($view, '$processingProductValid'), 'view validates the selected product against server-rendered products');

    echo "Processing redirect context: {$passed}/" . ($passed + $failed) . " passed\n";
    exit($failed === 0 ? 0 : 1);
}
