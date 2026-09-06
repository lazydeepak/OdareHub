<?php
declare(strict_types=1);

namespace Apps\Hospitality\Controllers;

use App\Core\DB;

/**
 * Honest read-only table introspection for L1 surfaces.
 * Returns null when the schema is not installed yet so views can say so truthfully.
 */
final class HospitalityTables
{
    public static function count(string $table): ?int
    {
        if (!preg_match('/^hosp_[a-z_]+$/', $table)) {
            return null;
        }

        try {
            $row = DB::fetchOne(
                'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            );
            if ((int)($row['c'] ?? 0) === 0) {
                return null;
            }
            $countRow = DB::fetchOne("SELECT COUNT(*) AS c FROM `{$table}`");
            return (int)($countRow['c'] ?? 0);
        } catch (\Throwable) {
            return null;
        }
    }
}
