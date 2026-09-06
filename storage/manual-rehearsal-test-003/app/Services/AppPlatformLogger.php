<?php
declare(strict_types=1);

namespace App\Services;

final class AppPlatformLogger
{
    public static function lifecycle(string $action, string $appKey, array $context = []): void
    {
        $line = json_encode([
            'ts' => date('c'),
            'action' => $action,
            'app_key' => $appKey,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            return;
        }

        $logPath = dirname(__DIR__, 2) . '/storage/logs/app-lifecycle.log';
        @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND);
        error_log('[app-platform] ' . $line);
    }

    public static function lifecycleDedup(string $action, string $appKey, array $context = []): void
    {
        $timestamp = date('c');
        $normalized = self::normalizeContext($context);
        $signature = sha1(json_encode([
            'action' => $action,
            'app_key' => $appKey,
            'context' => $normalized,
        ], JSON_UNESCAPED_SLASHES) ?: '');

        $entries = self::readDedupeEntries();
        $current = is_array($entries[$signature] ?? null) ? $entries[$signature] : null;

        if ($current) {
            $current['count'] = (int)($current['count'] ?? 1) + 1;
            $current['last_seen'] = $timestamp;
            $entries[$signature] = $current;
        } else {
            $entries[$signature] = [
                'signature' => $signature,
                'action' => $action,
                'app_key' => $appKey,
                'context' => $normalized,
                'count' => 1,
                'first_seen' => $timestamp,
                'last_seen' => $timestamp,
            ];
        }

        self::writeDedupeEntries($entries);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function dedupedEntriesForApp(string $appKey, int $maxRows = 100): array
    {
        $out = [];
        foreach (self::readDedupeEntries() as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if ((string)($entry['app_key'] ?? '') !== $appKey) {
                continue;
            }
            $out[] = [
                'ts' => (string)($entry['last_seen'] ?? ''),
                'action' => (string)($entry['action'] ?? ''),
                'app_key' => $appKey,
                'context' => array_merge(
                    (array)($entry['context'] ?? []),
                    [
                        'occurrence_count' => (int)($entry['count'] ?? 1),
                        'first_seen' => (string)($entry['first_seen'] ?? ''),
                        'last_seen' => (string)($entry['last_seen'] ?? ''),
                    ]
                ),
            ];
        }

        usort($out, static function (array $left, array $right): int {
            return strcmp((string)($right['ts'] ?? ''), (string)($left['ts'] ?? ''));
        });

        return array_slice($out, 0, $maxRows);
    }

    /**
     * @return array<string,mixed>
     */
    private static function normalizeContext(array $context): array
    {
        ksort($context);
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = self::normalizeContext($value);
            }
        }
        return $context;
    }

    private static function dedupeFilePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs/app-lifecycle-dedupe.json';
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function readDedupeEntries(): array
    {
        $path = self::dedupeFilePath();
        if (!is_file($path)) {
            return [];
        }

        $json = (string)@file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string,array<string,mixed>> $entries
     */
    private static function writeDedupeEntries(array $entries): void
    {
        $path = self::dedupeFilePath();
        @file_put_contents($path, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
