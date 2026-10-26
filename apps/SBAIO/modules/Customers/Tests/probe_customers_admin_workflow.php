<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-004 Customers contract probe.
 *
 * This probe uses in-memory Auth/DB/view doubles only. It never loads the
 * application bootstrap and never connects to a database.
 */

namespace App\Core {
    final class ConnectionDouble
    {
        public int|string $insert_id = 417;
        public bool $beginResult = true;
        public bool $commitResult = true;
        public bool $rollbackResult = true;
        public array $events = [];

        public function begin_transaction(): bool
        {
            $this->events[] = 'begin';
            return $this->beginResult;
        }

        public function commit(): bool
        {
            $this->events[] = 'commit';
            return $this->commitResult;
        }

        public function rollback(): bool
        {
            $this->events[] = 'rollback';
            return $this->rollbackResult;
        }
    }

    final class Auth
    {
        public static ?array $user = null;
        public static array $calls = [];

        public static function requireAppAccess(string $appKey): void
        {
            self::$calls[] = ['method' => 'requireAppAccess', 'app' => $appKey];
            if (self::$user === null) {
                throw new \RuntimeException('unauthenticated');
            }
        }

        public static function user(): ?array
        {
            return self::$user;
        }

        public static function requireCsrf(string $token): void
        {
            self::$calls[] = ['method' => 'requireCsrf', 'token' => $token];
            if ($token !== 'valid-csrf') {
                throw new \RuntimeException('csrf denied');
            }
        }
    }

    final class DB
    {
        public static array $queries = [];
        public static array $rows = [];
        public static ?array $fetchAllRows = null;
        public static array $fetchOneRows = [];
        public static bool $fetchOneFailure = false;
        public static bool $queryFailure = false;
        public static ?ConnectionDouble $connection = null;

        public static function conn(): ConnectionDouble
        {
            return self::$connection ??= new ConnectionDouble();
        }

        public static function fetchAll(string $sql, array $params = []): array
        {
            self::$queries[] = ['method' => 'fetchAll', 'sql' => $sql, 'params' => $params];
            if (self::$fetchAllRows !== null) {
                return array_shift(self::$fetchAllRows) ?: [];
            }
            return self::$rows;
        }

        public static function query(string $sql, array $params = []): void
        {
            self::$queries[] = ['method' => 'query', 'sql' => $sql, 'params' => $params];
            if (self::$queryFailure) {
                throw new \RuntimeException('query failed');
            }
        }

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            self::$queries[] = ['method' => 'fetchOne', 'sql' => $sql, 'params' => $params];
            if (self::$fetchOneFailure) {
                throw new \RuntimeException('report read failed');
            }
            return array_shift(self::$fetchOneRows) ?: null;
        }
    }
}

namespace {
    function t(string $key): string
    {
        return [
            'sbaio.customers.admin_required' => 'Administrator access is required.',
            'sbaio.customers.required' => 'Customer name is required.',
            'sbaio.customers.invalid' => 'Please correct the customer details.',
            'sbaio.customers.email_invalid' => 'Enter a valid email address.',
            'sbaio.customers.status_invalid' => 'Only active customers can be created here.',
            'sbaio.customers.created' => 'Customer created.',
        ][$key] ?? $key;
    }

    require_once __DIR__ . '/../Services/CustomersService.php';
    require_once __DIR__ . '/../Controllers/CustomersController.php';

    use App\Core\Auth;
    use App\Core\DB;
    use Plugins\Customers\Controllers\CustomersController;
    use Plugins\Customers\Services\CustomersService;

    $assertions = 0;
    $failures = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures++;
            fwrite(STDERR, "FAIL: {$message}\n");
        }
    };

    if (($argv[1] ?? '') === 'denied') {
        Auth::$user = ['id' => 7, 'authority_role' => 'app_user', 'role' => 'user'];
        CustomersController::requireAdministrator();
        exit(2);
    }

    if (($argv[1] ?? '') === 'malformed-controller') {
        Auth::$user = ['id' => 8, 'authority_role' => 'app_admin', 'role' => 'user'];
        $_POST = ['csrf' => 'valid-csrf', 'customer_name' => ['Array-shaped name']];
        register_shutdown_function(static function (): void {
            echo 'malformed-controller queries=' . count(DB::$queries) . "\n";
        });
        CustomersController::create();
        exit(2);
    }

    // Validation is pure and must not touch the fake database.
    $beforeQueries = count(DB::$queries);
    $missing = CustomersService::validateCreateInput([]);
    $assert(!$missing['valid'] && in_array('required', $missing['errors'], true), 'missing customer name is rejected');
    $assert(count(DB::$queries) === $beforeQueries, 'missing input performs no persistence');

    $badEmail = CustomersService::validateCreateInput(['customer_name' => 'Acme', 'email' => 'not-an-email']);
    $assert(!$badEmail['valid'] && in_array('email_invalid', $badEmail['errors'], true), 'invalid email is rejected');

    $existingStatus = CustomersService::validateCreateInput(['customer_name' => 'Acme', 'status' => 'prospect']);
    $assert($existingStatus['valid'] && $existingStatus['values']['status'] === 'prospect', 'existing arbitrary status values remain supported');

    $badStatus = CustomersService::validateCreateInput(['customer_name' => 'Acme', 'status' => str_repeat('x', 41)]);
    $assert(!$badStatus['valid'] && in_array('status_too_long', $badStatus['errors'], true), 'overlong status is rejected');

    $valid = CustomersService::validateCreateInput([
        'customer_name' => '  Acme  ',
        'contact_name' => '  Jane  ',
        'email' => 'jane@example.test',
        'phone' => '  +1 555 0100 ',
        'status' => '',
    ]);
    $assert($valid['valid'], 'valid customer input is accepted');
    $assert($valid['values'] === [
        'customer_name' => 'Acme',
        'contact_name' => 'Jane',
        'email' => 'jane@example.test',
        'phone' => '+1 555 0100',
        'status' => 'active',
    ], 'valid customer input is normalized with active default');

    // Every supplied field is string-only. Unsupported values must fail before
    // trim/normalization and before the INSERT boundary.
    foreach (['customer_name', 'contact_name', 'email', 'phone', 'status'] as $field) {
        foreach ([['Acme'], new \stdClass(), true, 17, 12.5] as $malformedValue) {
            $malformed = ['customer_name' => 'Acme', $field => $malformedValue];
            $validatedMalformed = CustomersService::validateCreateInput($malformed);
            $assert(!$validatedMalformed['valid'] && $validatedMalformed['errors'] === ['input_type_invalid'], $field . ' rejects unsupported input type before normalization');

            DB::$queries = [];
            try {
                CustomersService::create($malformed);
                $assert(false, $field . ' malformed create unexpectedly succeeded');
            } catch (\InvalidArgumentException) {
                $assert(DB::$queries === [], $field . ' malformed create reaches no database insertion');
            }
        }
    }

    // Existing persistence contract is invoked only with normalized values.
    DB::$queries = [];
    DB::$connection = new \App\Core\ConnectionDouble();
    $createdId = CustomersService::create($valid['values']);
    $assert($createdId === 417, 'valid create returns the generated positive customer ID');
    $assert(count(DB::$queries) === 1 && DB::$queries[0]['method'] === 'query', 'valid create invokes one insert query');
    $assert(DB::$queries[0]['params'] === ['Acme', 'Jane', 'jane@example.test', '+1 555 0100', 'active'], 'insert receives normalized existing fields');
    $assert(DB::$connection->events === ['begin', 'commit'], 'successful customer creation commits after identity verification');

    DB::$queries = [];
    DB::$connection = new \App\Core\ConnectionDouble();
    DB::$connection->insert_id = 0;
    try {
        CustomersService::create($valid['values']);
        $assert(false, 'missing customer identity unexpectedly succeeded');
    } catch (\RuntimeException) {
        $assert(DB::$connection->events === ['begin', 'rollback'], 'missing customer identity rolls back before reporting failure');
    }

    DB::$queries = [];
    DB::$connection = new \App\Core\ConnectionDouble();
    DB::$connection->commitResult = false;
    try {
        CustomersService::create($valid['values']);
        $assert(false, 'customer commit failure unexpectedly succeeded');
    } catch (\RuntimeException) {
        $assert(DB::$connection->events === ['begin', 'commit', 'rollback'], 'customer commit failure rolls back and cannot report success');
    }

    DB::$queries = [];
    DB::$connection = new \App\Core\ConnectionDouble();
    DB::$queryFailure = true;
    try {
        CustomersService::create($valid['values']);
        $assert(false, 'customer insert failure unexpectedly succeeded');
    } catch (\RuntimeException) {
        $assert(DB::$connection->events === ['begin', 'rollback'], 'customer insert failure rolls back');
    }
    DB::$queryFailure = false;

    DB::$queries = [];
    DB::$fetchOneRows = [[
        'id' => 417,
        'customer_name' => 'Exact Customer',
        'contact_name' => 'Jane',
        'email' => 'jane@example.test',
        'phone' => '+1 555 0100',
        'status' => 'active',
    ]];
    $exactRecord = CustomersService::findById('417');
    $assert($exactRecord !== null && $exactRecord['id'] === 417 && $exactRecord['customer_name'] === 'Exact Customer', 'exact customer retrieval uses generated ID independent of customer fields');
    $assert(DB::$queries[0]['params'] === [417] && str_contains(DB::$queries[0]['sql'], 'WHERE id = ? LIMIT 1'), 'exact customer retrieval uses one parameterized ID predicate');
    DB::$queries = [];
    DB::$fetchOneRows = [null];
    $assert(CustomersService::findById(999999) === null && count(DB::$queries) === 1, 'missing exact customer returns no record without latest-row or field inference');
    foreach ([0, -1, '', '0', 'not-an-id', ['417'], '999999999999999999999'] as $invalidRecordId) {
        DB::$queries = [];
        try {
            CustomersService::findById($invalidRecordId);
            $assert(false, 'malformed exact customer ID unexpectedly succeeded');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'malformed exact customer ID is rejected before database access');
        }
    }

    DB::$queries = [];
    try {
        CustomersService::create(['customer_name' => '', 'status' => 'active']);
        $assert(false, 'invalid create throws before persistence');
    } catch (\InvalidArgumentException) {
        $assert(count(DB::$queries) === 0, 'invalid create performs no persistence');
    }

    // Admin authority is server-side and retains the existing app-access gate.
    Auth::$user = ['id' => 1, 'authority_role' => 'platform_admin', 'role' => 'admin'];
    Auth::$calls = [];
    CustomersController::requireAdministrator();
    $assert(Auth::$calls === [['method' => 'requireAppAccess', 'app' => 'sbaio']], 'platform admin passes existing SBAIO access gate');

    Auth::$user = ['id' => 2, 'authority_role' => 'app_admin', 'role' => 'user'];
    Auth::$calls = [];
    CustomersController::requireAdministrator();
    $assert(Auth::$calls === [['method' => 'requireAppAccess', 'app' => 'sbaio']], 'app admin passes existing SBAIO access gate');

    // List remains deterministic and bounded at the existing 25-record contract.
    DB::$queries = [];
    DB::$rows = [['id' => 9, 'customer_name' => 'Newest']];
    $rows = CustomersService::recent(1000);
    $assert($rows === DB::$rows, 'recent returns repository rows unchanged');
    $assert(str_contains(DB::$queries[0]['sql'], 'ORDER BY id DESC LIMIT 25'), 'recent uses deterministic bounded ordering');

    DB::$queries = [];
    DB::$fetchOneRows = [[
        'total' => 4,
        'active' => 3,
        'new_30d' => 2,
        'with_email' => 3,
        'with_phone' => 2,
    ]];
    DB::$fetchAllRows = [
        [['status' => 'active', 'n' => 3]],
        [['id' => 9, 'customer_name' => 'Newest', 'contact_name' => 'Jane', 'email' => 'jane@example.test', 'phone' => '+1 555 0100', 'status' => 'active', 'created_at' => '2024-01-01']],
    ];
    $reportData = CustomersService::reportData();
    $assert($reportData['summary']['total'] === 4 && $reportData['summary']['active'] === 3, 'customer report summary preserves prepared aggregate values');
    $assert($reportData['byStatus'][0]['status'] === 'active' && $reportData['recent'][0]['id'] === 9, 'customer report collections preserve existing output shape');
    $assert(count(DB::$queries) === 3 && DB::$queries[0]['method'] === 'fetchOne', 'customer report performs exactly the existing three reads in order');
    $assert(str_contains(DB::$queries[0]['sql'], 'DATE_SUB(CURDATE(), INTERVAL 30 DAY)') && str_contains(DB::$queries[1]['sql'], 'GROUP BY status ORDER BY n DESC') && str_contains(DB::$queries[2]['sql'], 'ORDER BY created_at DESC, id DESC LIMIT 25'), 'customer report preserves exact aggregate, status, and recent SQL contracts');

    DB::$queries = [];
    DB::$fetchOneRows = [];
    DB::$fetchAllRows = null;
    DB::$fetchOneFailure = true;
    $unavailableReportData = CustomersService::reportData();
    $assert($unavailableReportData['summary']['total'] === 0 && $unavailableReportData['byStatus'] === [] && $unavailableReportData['recent'] === [], 'customer report keeps the existing default payload on read failure');
    DB::$fetchOneFailure = false;

    // Historical discovery is exact-filter-only, bounded, deterministic, and
    // cursor-bound. Invalid filters/cursors must not reach the database.
    DB::$queries = [];
    DB::$fetchAllRows = [[
        ['id' => 30, 'customer_name' => 'Acme', 'contact_name' => 'A', 'email' => 'a@example.test', 'phone' => '111', 'status' => 'active', 'created_at' => '2026-01-03 12:00:00'],
        ['id' => 29, 'customer_name' => 'Acme', 'contact_name' => 'B', 'email' => 'b@example.test', 'phone' => '222', 'status' => 'active', 'created_at' => '2026-01-02 12:00:00'],
        ['id' => 28, 'customer_name' => 'Acme', 'contact_name' => 'C', 'email' => 'c@example.test', 'phone' => '333', 'status' => 'active', 'created_at' => '2026-01-01 12:00:00'],
    ]];
    $firstSearch = CustomersService::search(['customer_name' => ' Acme ', 'from' => '2026-01-01', 'to' => '2026-01-31'], 2);
    $assert($firstSearch['valid'] === true && $firstSearch['truncated'] === true && count($firstSearch['rows']) === 2, 'customer search returns a bounded first page with overflow detection');
    $assert($firstSearch['rows'][0]['id'] === 30 && $firstSearch['rows'][1]['id'] === 29, 'customer search preserves created_at DESC and id DESC ordering');
    $assert(is_string($firstSearch['next_cursor']) && $firstSearch['next_cursor'] !== '', 'customer search emits an opaque next cursor');
    $assert(str_contains(DB::$queries[0]['sql'], 'customer_name = ?') && str_contains(DB::$queries[0]['sql'], 'created_at >= ?') && str_contains(DB::$queries[0]['sql'], 'created_at < DATE_ADD(?, INTERVAL 1 DAY)') && str_contains(DB::$queries[0]['sql'], 'ORDER BY created_at DESC, id DESC LIMIT 3'), 'customer search uses exact predicates, inclusive date bounds, and one overflow row');
    $assert(DB::$queries[0]['params'] === ['Acme', '2026-01-01 00:00:00', '2026-01-31 00:00:00'], 'customer search trims filters and binds all values as parameters');

    DB::$queries = [];
    DB::$fetchAllRows = [[
        ['id' => 28, 'customer_name' => 'Acme', 'contact_name' => 'C', 'email' => 'c@example.test', 'phone' => '333', 'status' => 'active', 'created_at' => '2026-01-01 00:00:00'],
    ]];
    $secondSearch = CustomersService::search(['customer_name' => 'Acme', 'from' => '2026-01-01', 'to' => '2026-01-31'], 2, $firstSearch['next_cursor']);
    $assert($secondSearch['rows'][0]['id'] === 28 && $secondSearch['next_cursor'] === null, 'customer search cursor advances without duplicating the boundary row');
    $assert(str_contains(DB::$queries[0]['sql'], 'created_at < ? OR created_at IS NULL OR (created_at = ? AND id < ?)'), 'customer cursor adds the strict keyset continuation predicate');

    foreach ([
        [],
        ['customer_name' => ['Acme']],
        ['from' => '2026-02-30'],
        ['from' => '2026-02-02', 'to' => '2026-02-01'],
        ['customer_status' => 'active'],
    ] as $invalidFilters) {
        DB::$queries = [];
        try {
            CustomersService::search($invalidFilters);
            $assert(false, 'invalid customer search filter unexpectedly succeeded');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'invalid customer search filter is rejected before database access');
        }
    }
    foreach ([$firstSearch['next_cursor'] . 'tampered', '!', 'eyJ2IjoyfQ'] as $invalidCursor) {
        DB::$queries = [];
        try {
            CustomersService::search(['customer_name' => 'Acme'], 2, $invalidCursor);
            $assert(false, 'invalid customer search cursor unexpectedly succeeded');
        } catch (\InvalidArgumentException) {
            $assert(DB::$queries === [], 'invalid customer search cursor is rejected before database access');
        }
    }
    DB::$queries = [];
    try {
        CustomersService::search(['customer_name' => 'Other'], 2, $firstSearch['next_cursor']);
        $assert(false, 'filter-mismatched customer cursor unexpectedly succeeded');
    } catch (\InvalidArgumentException) {
        $assert(DB::$queries === [], 'filter-mismatched customer cursor is rejected before database access');
    }

    $source = file_get_contents(__DIR__ . '/../Views/index.php') ?: '';
    $reportSource = file_get_contents(__DIR__ . '/../Views/report.php') ?: '';
    $routes = file_get_contents(__DIR__ . '/../routes.php') ?: '';
    $assert(str_contains($source, 'e((string)($row[\'customer_name\'] ?? \'\'))'), 'list customer values are escaped');
    $serviceSource = file_get_contents(__DIR__ . '/../Services/CustomersService.php') ?: '';
    $controllerSource = file_get_contents(__DIR__ . '/../Controllers/CustomersController.php') ?: '';
    $assert(str_contains($serviceSource, "'input_type_invalid'") && !str_contains($serviceSource, '(string)($input['), 'customer validation has no pre-validation string coercion');
    $assert(str_contains($controllerSource, 'CustomersService::validateCreateInput($_POST)') && strpos($controllerSource, 'CustomersService::validateCreateInput($_POST)') < strpos($controllerSource, 'CustomersService::create($validated[\'values\'])'), 'controller validates the raw POST payload before persistence dispatch');
    $assert(str_contains($controllerSource, 'CustomersService::findById') && str_contains($controllerSource, "'customer_id' => \$createdId"), 'post-create redirect carries the exact generated customer ID');
    $assert(str_contains($serviceSource, 'public static function findById') && str_contains($serviceSource, 'WHERE id = ? LIMIT 1') && str_contains($serviceSource, 'begin_transaction'), 'customer service owns exact read and transaction identity guarantees');
    $assert(str_contains($serviceSource, 'public static function reportData'), 'Customers service owns the report read contract');
    $assert(str_contains($serviceSource, 'public static function search') && str_contains($serviceSource, 'customer_name = ?') && str_contains($serviceSource, 'searchFilterFingerprint'), 'Customers service owns exact-filter historical discovery and cursor binding');
    $assert(str_contains($controllerSource, "CustomersService::search(") && str_contains($controllerSource, "'customer_search'") && str_contains($source, 'customer_cursor'), 'existing Customers administration surface carries bounded search state');
    $assert(strpos($controllerSource, 'self::requireAdministrator();') < strpos($controllerSource, 'CustomersService::search('), 'administrator authorization precedes customer historical search');
    $assert(!str_contains($serviceSource . $source, ' LIKE ') && !str_contains($serviceSource . $source, 'latest-row'), 'customer discovery has no fuzzy matching or latest-row inference');
    $assert(!str_contains($reportSource, 'DB::') && !str_contains($reportSource, 'sbaio_customers') && !str_contains($reportSource, 'ModuleReportRegistryService'), 'Customers report view performs zero database and registry operations');
    $assert(str_contains($controllerSource, "'reportData' => CustomersService::reportData()") && str_contains($routes, 'CustomersController::report($view, $report)'), 'controller supplies prepared report data after registry admission');
    $assert(strpos($routes, 'CustomersController::requireAdministrator()') < strpos($routes, 'ModuleReportRegistryService::findActiveReport') && strpos($routes, 'ModuleReportRegistryService::findActiveReport') < strpos($routes, 'CustomersController::report($view, $report)'), 'report authorization and registry admission precede data preparation and rendering');
    $assert(!str_contains($controllerSource . $serviceSource, 'latest') && !str_contains($controllerSource . $serviceSource, 'ORDER BY id DESC LIMIT 1'), 'customer identity does not use latest-row inference');
    foreach (['open_created_record', 'open_record', 'record_title', 'record_id', 'record_customer_name', 'record_contact_name', 'record_email', 'record_phone', 'record_status', 'record_id_invalid', 'record_not_found', 'record_unavailable'] as $key) {
        $assert(str_contains($source, 'sbaio.customers.' . $key), 'Customers view uses localized exact-record key ' . $key);
    }
    foreach (['search_title', 'search_note', 'search_name', 'search_email', 'search_phone', 'search_status', 'search_from', 'search_to', 'search_submit', 'search_invalid', 'search_unavailable', 'search_no_results', 'search_limited', 'search_next', 'search_created'] as $key) {
        $assert(str_contains($source, 'sbaio.customers.' . $key), 'Customers view uses localized search key ' . $key);
    }
    $assert(substr_count($routes, 'CustomersController::requireAdministrator()') === 4, 'all customer record routes require administrator authority');
    $assert(!str_contains($source . $routes, 'staff_id') && !str_contains($source . $routes, 'leave_status'), 'Customers surface has no Staff or Leave coupling');
    foreach (['en.php', 'ja.php', 'ne.php'] as $locale) {
        $localeSource = file_get_contents(__DIR__ . '/../Resources/lang/' . $locale) ?: '';
        $assert(str_contains($localeSource, 'sbaio.customers.admin_required'), $locale . ' has administrator localization');
        $assert(str_contains($localeSource, 'sbaio.customers.customer_name'), $locale . ' has customer field localization');
    }
    $jaLocale = require __DIR__ . '/../Resources/lang/ja.php';
    $neLocale = require __DIR__ . '/../Resources/lang/ne.php';
    $expectedLocalized = [
        'ja' => [
            'common.status_label' => 'ステータス',
            'customers.active_message' => '有効',
            'customers.by_status_title' => 'ステータス別',
            'customers.created_column' => '作成日時',
            'customers.customer_snapshot_title' => '顧客スナップショット',
            'customers.customers_export_title' => '顧客エクスポート',
            'customers.customers_report_title' => '顧客レポート',
            'customers.export_active_message' => 'レポートキーのエクスポートは有効です',
            'customers.export_unavailable_message' => 'モジュールが無効または未登録のため、エクスポートを利用できません。',
            'customers.open_link' => 'エクスポートを開く',
            'customers.report_description' => 'ステータス別集計と連絡先情報を含む顧客マスターのスナップショットです。',
            'customers.report_key_label' => 'レポートキー',
            'customers.report_unavailable_message' => '所有モジュールが無効または未登録のため、このレポートは利用できません。',
            'sbaio.customers.create' => '顧客を作成',
            'sbaio.customers.created' => '顧客を作成しました。',
            'sbaio.customers.description' => 'SBAIO の顧客アカウントと関係情報を管理するワークスペースです。',
            'sbaio.customers.empty_desc' => '顧客情報はまだありません。このモジュールはインストール済みで利用できます。',
            'sbaio.customers.form_desc' => '売上や経費レポートと並べて顧客連絡先を管理するための画面です。',
            'sbaio.customers.form_title' => '顧客を追加',
            'sbaio.customers.records' => '顧客レコード',
            'sbaio.customers.required' => '顧客名は必須です。',
            'sbaio.customers.title' => '顧客ダッシュボード',
        ],
        'ne' => [
            'common.status_label' => 'स्थिति',
            'customers.active_message' => 'सक्रिय',
            'customers.by_status_title' => 'स्थितिअनुसार',
            'customers.created_column' => 'सिर्जना गरिएको',
            'customers.customer_snapshot_title' => 'ग्राहक स्न्यापसट',
            'customers.customers_export_title' => 'ग्राहक निर्यात',
            'customers.customers_report_title' => 'ग्राहक रिपोर्ट',
            'customers.export_active_message' => 'रिपोर्ट कुञ्जीका लागि निर्यात सक्रिय छ',
            'customers.export_unavailable_message' => 'मोड्युल निष्क्रिय वा दर्ता नभएकाले निर्यात उपलब्ध छैन।',
            'customers.open_link' => 'निर्यात खोल्नुहोस्',
            'customers.report_description' => 'स्थिति मिश्रण र सम्पर्क विवरणको उपलब्धतासहित ग्राहक मास्टरको स्न्यापसट।',
            'customers.report_key_label' => 'रिपोर्ट कुञ्जी',
            'customers.report_unavailable_message' => 'स्वामी मोड्युल निष्क्रिय वा दर्ता नभएकाले यो रिपोर्ट उपलब्ध छैन।',
            'sbaio.customers.create' => 'ग्राहक सिर्जना गर्नुहोस्',
            'sbaio.customers.created' => 'ग्राहक सिर्जना भयो।',
            'sbaio.customers.description' => 'SBAIO का लागि ग्राहक खाता र सम्बन्ध व्यवस्थापन कार्यक्षेत्र।',
            'sbaio.customers.empty_desc' => 'अहिलेसम्म ग्राहक रेकर्ड छैनन्। यो मोड्युल स्थापना भएको र तयार छ।',
            'sbaio.customers.form_desc' => 'बिक्री र खर्च रिपोर्टसँगै ग्राहक सम्पर्कहरू देखाइराख्न यो कार्यक्षेत्र प्रयोग गर्नुहोस्।',
            'sbaio.customers.form_title' => 'ग्राहक थप्नुहोस्',
            'sbaio.customers.records' => 'ग्राहक रेकर्डहरू',
            'sbaio.customers.required' => 'ग्राहकको नाम आवश्यक छ।',
            'sbaio.customers.title' => 'ग्राहक ड्यासबोर्ड',
        ],
    ];
    foreach (['ja' => $jaLocale, 'ne' => $neLocale] as $locale => $values) {
        foreach ($expectedLocalized[$locale] as $key => $expected) {
            $assert(($values[$key] ?? null) === $expected, $locale . ' localizes active Customers key ' . $key);
        }
    }

    $command = [PHP_BINARY, __FILE__, 'denied'];
    $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptorSpec, $pipes);
    $deniedOutput = '';
    $status = -1;
    if (is_resource($process)) {
        $deniedOutput = (string)stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
    }
    $assert($status === 0, 'non-admin controller authorization exits with denial response');

    $command = [PHP_BINARY, __FILE__, 'malformed-controller'];
    $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptorSpec, $pipes);
    $malformedOutput = '';
    $malformedStatus = -1;
    if (is_resource($process)) {
        $malformedOutput = (string)stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $malformedStatus = proc_close($process);
    }
    $assert($malformedStatus === 0 && str_contains($malformedOutput, 'malformed-controller queries=0'), 'malformed controller payload redirects without invoking database insertion');

    echo "Customers admin workflow probe: {$assertions} assertions, {$failures} failures\n";
    exit($failures === 0 ? 0 : 1);
}
