<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-004 Customers contract probe.
 *
 * This probe uses in-memory Auth/DB/view doubles only. It never loads the
 * application bootstrap and never connects to a database.
 */

namespace App\Core {
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

        public static function fetchAll(string $sql, array $params = []): array
        {
            self::$queries[] = ['method' => 'fetchAll', 'sql' => $sql, 'params' => $params];
            return self::$rows;
        }

        public static function query(string $sql, array $params = []): void
        {
            self::$queries[] = ['method' => 'query', 'sql' => $sql, 'params' => $params];
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

    // Existing persistence contract is invoked only with normalized values.
    DB::$queries = [];
    CustomersService::create($valid['values']);
    $assert(count(DB::$queries) === 1 && DB::$queries[0]['method'] === 'query', 'valid create invokes one insert query');
    $assert(DB::$queries[0]['params'] === ['Acme', 'Jane', 'jane@example.test', '+1 555 0100', 'active'], 'insert receives normalized existing fields');

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

    $source = file_get_contents(__DIR__ . '/../Views/index.php') ?: '';
    $routes = file_get_contents(__DIR__ . '/../routes.php') ?: '';
    $assert(str_contains($source, 'e((string)($row[\'customer_name\'] ?? \'\'))'), 'list customer values are escaped');
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

    echo "Customers admin workflow probe: {$assertions} assertions, {$failures} failures\n";
    exit($failures === 0 ? 0 : 1);
}
