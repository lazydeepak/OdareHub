<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationScanExtraction\Services;

use Apps\Studio\Tools\LocalizationScanExtraction\ValueObjects\CorrectionReport;

final class HistoryStore
{
    private const HISTORY_DIR = APP_ROOT . '/storage/studio-snapshots/localization-scan-extraction/history';
    private const INDEX_FILE = 'index.json';

    public static function store(CorrectionReport $report): bool
    {
        if (!is_dir(self::HISTORY_DIR)) {
            @mkdir(self::HISTORY_DIR, 0755, true);
        }
        if (!is_dir(self::HISTORY_DIR)) {
            return false;
        }

        $ts = date('Ymd_His');
        $rand = rand(1000, 9999);
        $filename = $ts . '_' . $rand . '.json';
        $path = self::HISTORY_DIR . '/' . $filename;

        $written = @file_put_contents($path, json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($written === false) {
            return false;
        }

        self::appendToIndex($filename, $report);
        self::pruneOldEntries();

        return true;
    }

    public static function getRecent(int $limit = 20): array
    {
        $index = self::loadIndex();
        $reports = [];
        $count = 0;

        foreach ($index as $entry) {
            if ($count >= $limit) {
                break;
            }
            $path = self::HISTORY_DIR . '/' . ($entry['file'] ?? '');
            if (!is_file($path)) {
                continue;
            }
            $data = @file_get_contents($path);
            if ($data === false) {
                continue;
            }
            $decoded = json_decode($data, true);
            if (!is_array($decoded)) {
                continue;
            }
            $reports[] = CorrectionReport::fromArray($decoded);
            $count++;
        }

        return $reports;
    }

    public static function count(): int
    {
        $index = self::loadIndex();
        return count($index);
    }

    private static function appendToIndex(string $filename, CorrectionReport $report): void
    {
        $index = self::loadIndex();
        array_unshift($index, [
            'file' => $filename,
            'action' => $report->action,
            'owner_key' => $report->ownerKey,
            'success' => $report->success,
            'occurred_at' => $report->occurredAt,
            'added_count' => $report->addedCount,
        ]);
        self::writeIndex($index);
    }

    private static function loadIndex(): array
    {
        $path = self::HISTORY_DIR . '/' . self::INDEX_FILE;
        if (!is_file($path)) {
            return [];
        }
        $data = @file_get_contents($path);
        if ($data === false) {
            return [];
        }
        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function writeIndex(array $index): void
    {
        $path = self::HISTORY_DIR . '/' . self::INDEX_FILE;
        @file_put_contents($path, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private static function pruneOldEntries(): void
    {
        $index = self::loadIndex();
        $maxEntries = 200;

        if (count($index) <= $maxEntries) {
            return;
        }

        $toKeep = array_slice($index, 0, $maxEntries);
        $keptFiles = [];
        foreach ($toKeep as $entry) {
            $keptFiles[$entry['file']] = true;
        }

        $allFiles = glob(self::HISTORY_DIR . '/*.json');
        foreach ($allFiles as $f) {
            $base = basename($f);
            if ($base === self::INDEX_FILE) {
                continue;
            }
            if (!isset($keptFiles[$base])) {
                @unlink($f);
            }
        }

        self::writeIndex($toKeep);
    }
}
