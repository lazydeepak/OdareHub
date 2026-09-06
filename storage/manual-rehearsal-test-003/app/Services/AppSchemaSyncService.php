<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class AppSchemaSyncService
{
    public static function runAdditiveSql(string $sql): void
    {
        $statements = self::splitStatements($sql);

        foreach ($statements as $statement) {
            $stmt = self::normalizeStatement($statement);
            if ($stmt === '') {
                continue;
            }

            if (!self::isAdditiveSafe($stmt)) {
                throw new \RuntimeException('Schema sync blocked non-additive SQL: ' . self::shorten($stmt));
            }

            try {
                DB::query($stmt);
            } catch (\Throwable $e) {
                if (!self::isIgnorable($e)) {
                    throw $e;
                }
            }
        }
    }

    private static function isAdditiveSafe(string $stmt): bool
    {
        $s = strtoupper(ltrim($stmt));

        if (preg_match('/^CREATE\s+TABLE/', $s)) {
            return true;
        }
        if (preg_match('/^ALTER\s+TABLE\s+.+\s+ADD\s+COLUMN/', $s)) {
            return true;
        }
        if (preg_match('/^ALTER\s+TABLE\s+.+\s+ADD\s+(INDEX|KEY|UNIQUE|CONSTRAINT|FOREIGN\s+KEY)/', $s)) {
            return true;
        }

        return false;
    }

    private static function isIgnorable(\Throwable $e): bool
    {
        $code = (int)$e->getCode();
        if (in_array($code, [1060, 1061, 1826], true)) {
            return true;
        }

        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'already exists')
            || str_contains($msg, 'duplicate column')
            || str_contains($msg, 'duplicate key');
    }

    private static function shorten(string $stmt): string
    {
        return strlen($stmt) <= 120 ? $stmt : substr($stmt, 0, 117) . '...';
    }

    /**
     * @return array<int,string>
     */
    private static function splitStatements(string $sql): array
    {
        return preg_split('/;\s*(?:\R|$)/', $sql) ?: [];
    }

    private static function normalizeStatement(string $statement): string
    {
        $statement = preg_replace('#/\*.*?\*/#s', ' ', $statement) ?? $statement;
        $lines = preg_split('/\R/', $statement) ?: [];
        $filtered = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }

            $line = preg_replace('/\s+--.*$/', '', $line) ?? $line;
            $line = preg_replace('/\s+#.*$/', '', $line) ?? $line;
            $line = rtrim($line);
            if ($line !== '') {
                $filtered[] = $line;
            }
        }

        return trim(implode("\n", $filtered));
    }
}
