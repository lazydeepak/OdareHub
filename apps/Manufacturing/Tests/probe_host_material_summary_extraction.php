<?php
declare(strict_types=1);

/**
 * OH-AUTO2-109 isolated extraction proof.
 *
 * This invokes only the extracted Material summary callback with fake policy,
 * auth, storage, schema, and demand collaborators. It never loads application
 * bootstrap or an installed database.
 */

namespace App\Core {
    final class DB
    {
        public static int $writeCalls = 0;
        public static bool $storageReady = true;
        public static bool $incompatible = false;

        public static function conn(): object
        {
            return new class {
                public function real_escape_string(string $value): string
                {
                    return addslashes($value);
                }
            };
        }

        public static function query(string $sql, array $params = []): void
        {
            self::$writeCalls++;
        }

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            if (stripos($sql, 'SHOW TABLES LIKE') !== false) {
                return self::$storageReady ? ['table' => 'present'] : null;
            }
            if (stripos($sql, 'FROM mfg_part_demands') !== false) {
                return ['c' => 0];
            }
            return null;
        }

        public static function fetchAll(string $sql, array $params = []): array
        {
            if (self::$incompatible) {
                throw new \RuntimeException('incompatible fixture');
            }
            if (stripos($sql, 'FROM materials m') !== false && stripos($sql, 'FROM material_capacity c') === false) {
                return [[
                    'id' => 1,
                    'material_number' => 'MAT-001',
                    'material_code' => 'MAT-001',
                    'material_name' => 'Fixture Resin',
                    'material_type' => 'material',
                    'material_subtype' => '',
                    'uom' => 'kg',
                    'unit' => 'kg',
                    'lead_time_days' => 7,
                    'minimum_stock_qty' => 0,
                    'safety_stock_qty' => 0,
                    'maximum_stock_qty' => 100,
                    'max_storage_qty' => 100,
                    'storage_capacity_qty' => 100,
                    'storage_location' => 'A1',
                    'standard_cost' => 1,
                    'standard_unit_cost' => 1,
                    'is_active' => 1,
                    'on_hand_qty' => 10,
                    'reserved_qty' => 0,
                    'available_qty' => 10,
                ]];
            }
            return [];
        }
    }

    final class Auth
    {
        public static function user(): ?array
        {
            return ['id' => 7, 'authority_role' => 'leader'];
        }
    }

    final class AclPolicy
    {
        public static bool $allowed = true;

        public static function canAny(array $permissions, ?array $user = null): bool
        {
            return self::$allowed;
        }

        public static function can(string $permission, ?array $user = null): bool
        {
            return self::$allowed;
        }
    }
}

namespace Plugins\MaterialManagement\Services {
    final class MaterialSchemaService
    {
        public static int $ensureCalls = 0;
        public static function ensureSchema(): void
        {
            self::$ensureCalls++;
        }
    }
}

namespace Apps\Manufacturing\Services {
    final class DemandEngineService
    {
        public static int $ensureCalls = 0;
        public static int $recalculateCalls = 0;
        public static function ensureSchema(): void
        {
            self::$ensureCalls++;
        }
        public static function recalculateDemands(array $filters, string $start, string $end): void
        {
            self::$recalculateCalls++;
        }
    }
}

namespace {
    define('APP_ROOT', dirname(__DIR__, 3));
    function t(string $key, array $replace = []): string
    {
        return $key;
    }

    final class MaterialExtractionPolicy
    {
        public bool $appEnabled = true;
        public bool $moduleAllowed = true;
        public function isAppEnabled(string $appKey): bool
        {
            return $this->appEnabled && $appKey === 'manufacturing';
        }
        public function canAccessModule(?array $user, string $module): bool
        {
            return $this->moduleAllowed && $module === 'materials';
        }
    }

    $policy = new MaterialExtractionPolicy();
    function platform_user_access_policy_contract(): MaterialExtractionPolicy
    {
        global $policy;
        return $policy;
    }

    require_once dirname(__DIR__) . '/modules/MaterialManagement/Services/MaterialManagementService.php';
    require_once dirname(__DIR__) . '/modules/MaterialManagement/Services/MaterialAccessService.php';
    require_once dirname(__DIR__) . '/Services/HostMaterialSummaryContributionService.php';
    require_once dirname(__DIR__, 2) . '/Platform/Services/HostProviderAdmissionService.php';

    $request = [
        'surface' => 'me',
        'region' => 'summary_cards',
        'context' => [
            'user' => ['id' => 7, 'authority_role' => 'leader'],
        ],
    ];

    $manifest = json_decode((string)file_get_contents(APP_ROOT . '/apps/Manufacturing/manifest.json'), true);
    $descriptor = null;
    foreach ((array)($manifest['host_provider_capabilities'] ?? []) as $candidate) {
        if (is_array($candidate) && ($candidate['key'] ?? '') === 'manufacturing.me.material_summary') {
            $descriptor = $candidate;
            break;
        }
    }
    $assertions = 0;
    $failures = [];
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures[] = $message;
        }
    };

    $admission = \Apps\Platform\Services\HostProviderAdmissionService::admit(
        ['app_key' => 'manufacturing', 'hook_key' => 'manufacturing.me.material_summary', 'install_path' => APP_ROOT . '/apps/Manufacturing'],
        [
            'surface' => 'me',
            'region' => 'summary_cards',
            'provider' => (string)($descriptor['provider'] ?? ''),
            'provider_file' => (string)($descriptor['provider_file'] ?? ''),
        ],
        'me',
        [
            'user' => $request['context']['user'],
            'context' => ['active_assigned_apps' => ['manufacturing']],
        ]
    );
    $assert(($admission['allowed'] ?? false) === true, 'trusted extracted descriptor is admitted');

    $tamperedAdmission = \Apps\Platform\Services\HostProviderAdmissionService::admit(
        ['app_key' => 'manufacturing', 'hook_key' => 'manufacturing.me.material_summary', 'install_path' => APP_ROOT . '/apps/Manufacturing'],
        [
            'surface' => 'me',
            'region' => 'summary_cards',
            'provider' => 'Apps\\Manufacturing\\Services\\HostSurfaceContributionService::contribute',
            'provider_file' => (string)($descriptor['provider_file'] ?? ''),
        ],
        'me',
        ['user' => $request['context']['user'], 'context' => ['active_assigned_apps' => ['manufacturing']]]
    );
    $assert(($tamperedAdmission['allowed'] ?? true) === false, 'tampered extracted binding is rejected');

    $reset = static function (): void {
        $reflection = new \ReflectionClass(\Plugins\MaterialManagement\Services\MaterialManagementService::class);
        foreach (['tableColumns' => [], 'coverageModelCache' => null, 'planningRowsCache' => null, 'demandSummaryCache' => null, 'readOnlySummaryMode' => false] as $property => $value) {
            $field = $reflection->getProperty($property);
            $field->setValue(null, $value);
        }
        \App\Core\DB::$writeCalls = 0;
        \Plugins\MaterialManagement\Services\MaterialSchemaService::$ensureCalls = 0;
        \Apps\Manufacturing\Services\DemandEngineService::$ensureCalls = 0;
        \Apps\Manufacturing\Services\DemandEngineService::$recalculateCalls = 0;
    };

    $reset();
    $allowed = \Apps\Manufacturing\Services\HostMaterialSummaryContributionService::contribute($request);
    $assert(count($allowed) === 1, 'extracted provider returns one aggregate');
    $assert(($allowed[0]['widget_key'] ?? '') === 'materials_summary', 'aggregate identity is preserved');
    $assert(($allowed[0]['value'] ?? null) !== null, 'aggregate value is present');
    $assert(($allowed[0]['url'] ?? '') === '/apps/manufacturing/materials', 'destination remains canonical');
    $assert(\App\Core\DB::$writeCalls === 0, 'authorized extraction performs zero writes');
    $assert(\Plugins\MaterialManagement\Services\MaterialSchemaService::$ensureCalls === 0, 'extraction performs zero schema initialization');
    $assert(\Apps\Manufacturing\Services\DemandEngineService::$ensureCalls === 0, 'extraction performs zero demand initialization');
    $assert(\Apps\Manufacturing\Services\DemandEngineService::$recalculateCalls === 0, 'extraction performs zero demand recalculation');

    $policy->moduleAllowed = false;
    $reset();
    $denied = \Apps\Manufacturing\Services\HostMaterialSummaryContributionService::contribute($request);
    $assert($denied === [], 'missing material module authority fails closed');
    $assert(\App\Core\DB::$writeCalls === 0, 'denied extraction performs zero writes');

    $policy->moduleAllowed = true;
    \App\Core\DB::$storageReady = false;
    $reset();
    $missing = \Apps\Manufacturing\Services\HostMaterialSummaryContributionService::contribute($request);
    $assert($missing === [], 'missing storage returns unavailable');
    $assert(\Plugins\MaterialManagement\Services\MaterialSchemaService::$ensureCalls === 0, 'missing storage does not initialize schema');
    $assert(\App\Core\DB::$writeCalls === 0, 'missing storage performs zero writes');

    $request['region'] = 'monitoring_sections';
    $assert(\Apps\Manufacturing\Services\HostMaterialSummaryContributionService::contribute($request) === [], 'other regions remain unsupported');

    if ($failures !== []) {
        foreach ($failures as $failure) {
            fwrite(STDERR, "FAIL: {$failure}\n");
        }
        fwrite(STDERR, "{$assertions} assertions, " . count($failures) . " failures\n");
        exit(1);
    }

    echo "OH-AUTO2-109 material extraction: {$assertions}/{$assertions} assertions passed\n";
}
