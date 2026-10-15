<?php
declare(strict_types=1);

/**
 * OH-AUTO2-104 focused read-safety proof.
 *
 * The fake DB exposes either missing, incompatible, or valid source storage.
 * It records every query classified as a write and fake schema/demand services
 * record initialization and recalculation calls. No installed DB is loaded.
 */

namespace App\Core {
    final class FakeMaterialConnection
    {
        public function real_escape_string(string $value): string
        {
            return addslashes($value);
        }
    }

    final class DB
    {
        public static bool $storageReady = true;
        public static bool $incompatible = false;
        public static int $writeCalls = 0;
        public static array $writeSql = [];

        public static function conn(): FakeMaterialConnection
        {
            return new FakeMaterialConnection();
        }

        public static function query(string $sql, array $params = []): void
        {
            self::$writeCalls++;
            self::$writeSql[] = $sql;
        }

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            if (stripos($sql, 'SHOW TABLES LIKE') !== false) {
                return self::$storageReady ? ['table' => 'present'] : null;
            }

            if (stripos($sql, 'FROM mfg_part_demands') !== false && stripos($sql, 'COUNT(*)') !== false) {
                return ['c' => 0];
            }

            return null;
        }

        /** @return array<int,array<string,mixed>> */
        public static function fetchAll(string $sql, array $params = []): array
        {
            if (self::$incompatible) {
                throw new \RuntimeException('fixture incompatible schema');
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
                    'vendor_name' => 'Fixture Vendor',
                    'supplier_name' => 'Fixture Vendor',
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
    require_once dirname(__DIR__) . '/modules/MaterialManagement/Services/MaterialManagementService.php';
    require_once dirname(__DIR__) . '/modules/MaterialManagement/Services/MaterialManagementWidgetRegistry.php';

    use App\Core\DB;
    use Apps\Manufacturing\Services\DemandEngineService;
    use Plugins\MaterialManagement\Services\MaterialManagementService;
    use Plugins\MaterialManagement\Services\MaterialSchemaService;
    use Plugins\MaterialManagement\Services\MaterialManagementWidgetRegistry;

    $assertions = 0;
    $failures = [];
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures[] = $message;
        }
    };

    $resetServiceState = static function (): void {
        $reflection = new \ReflectionClass(MaterialManagementService::class);
        foreach (['tableColumns' => [], 'coverageModelCache' => null, 'planningRowsCache' => null, 'demandSummaryCache' => null, 'readOnlySummaryMode' => false] as $property => $value) {
            $field = $reflection->getProperty($property);
            $field->setValue(null, $value);
        }
        DB::$writeCalls = 0;
        DB::$writeSql = [];
        MaterialSchemaService::$ensureCalls = 0;
        DemandEngineService::$ensureCalls = 0;
        DemandEngineService::$recalculateCalls = 0;
    };

    // Missing storage: the host read path is unavailable, not verified zero.
    $resetServiceState();
    DB::$storageReady = false;
    DB::$incompatible = false;
    $missing = MaterialManagementService::summaryReadOnly();
    $assert($missing === [], 'missing storage returns unavailable summary');
    $assert(MaterialSchemaService::$ensureCalls === 0, 'missing storage does not initialize material schema');
    $assert(DemandEngineService::$ensureCalls === 0, 'missing storage does not initialize demand schema');
    $assert(DemandEngineService::$recalculateCalls === 0, 'missing storage does not recalculate demand');
    $assert(DB::$writeCalls === 0, 'missing storage performs zero DB writes');
    $assert(MaterialManagementWidgetRegistry::contribute('summary_cards') === [], 'missing storage emits no fabricated material widget');

    // Incompatible storage: query failure remains unavailable and non-mutating.
    $resetServiceState();
    DB::$storageReady = true;
    DB::$incompatible = true;
    $incompatible = MaterialManagementService::summaryReadOnly();
    $assert($incompatible === [], 'incompatible storage returns unavailable summary');
    $assert(MaterialSchemaService::$ensureCalls === 0, 'incompatible storage does not initialize material schema');
    $assert(DemandEngineService::$ensureCalls === 0, 'incompatible storage does not initialize demand schema');
    $assert(DemandEngineService::$recalculateCalls === 0, 'incompatible storage does not recalculate demand');
    $assert(DB::$writeCalls === 0, 'incompatible storage performs zero DB writes');

    // Valid source storage: existing summary arithmetic remains available.
    $resetServiceState();
    DB::$storageReady = true;
    DB::$incompatible = false;
    $valid = MaterialManagementService::summaryReadOnly();
    $assert(($valid['materials_count'] ?? null) === 1, 'valid storage preserves material count calculation');
    $assert(($valid['balanced_count'] ?? null) === 1, 'valid storage preserves coverage calculation');
    $assert(MaterialSchemaService::$ensureCalls === 0, 'valid read summary does not initialize material schema');
    $assert(DemandEngineService::$ensureCalls === 0, 'valid read summary does not initialize demand schema');
    $assert(DemandEngineService::$recalculateCalls === 0, 'valid read summary does not recalculate demand');
    $assert(DB::$writeCalls === 0, 'valid read summary performs zero DB writes');

    // Ordinary service consumers retain their schema-backed contract.
    MaterialManagementService::materials();
    $assert(MaterialSchemaService::$ensureCalls === 1, 'ordinary materials consumer retains schema preparation');

    if ($failures !== []) {
        foreach ($failures as $failure) {
            fwrite(STDERR, "FAIL: {$failure}\n");
        }
        fwrite(STDERR, "{$assertions} assertions, " . count($failures) . " failures\n");
        exit(1);
    }

    echo "OH-AUTO2-104 material read safety: {$assertions}/{$assertions} assertions passed\n";
}
