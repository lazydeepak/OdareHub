<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\EngineeringWorkspaces\Services;

use Platform\Security\EngineeringWorkspaceContentContract;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureOwnerDiscoveryService;

final class EngineeringWorkspaceCoverageAssignmentService
{
    private const ASSIGNMENT_PATH = APP_ROOT . '/storage/engineering-workspace-coverage-assignment.json';
    private const SNAPSHOT_DIR = APP_ROOT . '/storage/studio-snapshots/engineering-workspaces/coverage-assignment';

    private const MODE_DEDICATED = 'dedicated';
    private const MODE_COVERED_BY = 'covered_by';
    private const MODE_SKIP = 'skip';

    private const VALID_MODES = [self::MODE_DEDICATED, self::MODE_COVERED_BY, self::MODE_SKIP];

    public static function loadAssignments(): array
    {
        $path = self::ASSIGNMENT_PATH;
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return $decoded;
    }

    /**
     * @return array{ok:bool,error:string,path?:string,snapshot_path?:string}
     */
    public static function saveAssignments(array $assignments, ?array $actor): array
    {
        $encoded = json_encode($assignments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return ['ok' => false, 'error' => 'Failed to encode assignments JSON'];
        }

        $snapshotDir = self::SNAPSHOT_DIR . '/' . date('Ymd_His') . '-' . bin2hex(random_bytes(8));
        if (!is_dir($snapshotDir) && !@mkdir($snapshotDir, 0755, true)) {
            return ['ok' => false, 'error' => 'Could not create snapshot directory'];
        }

        if (is_file(self::ASSIGNMENT_PATH)) {
            $before = file_get_contents(self::ASSIGNMENT_PATH);
            if ($before !== false) {
                file_put_contents($snapshotDir . '/before.json', $before);
            }
        }

        $manifest = [
            'timestamp' => time(),
            'datetime' => date('c'),
            'actor' => $actor['login'] ?? 'unknown',
        ];
        file_put_contents($snapshotDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $tmpPath = self::ASSIGNMENT_PATH . '.tmp.' . bin2hex(random_bytes(8));
        $written = @file_put_contents($tmpPath, $encoded, LOCK_EX);
        if ($written === false) {
            @unlink($tmpPath);
            return ['ok' => false, 'error' => 'Write to temp file failed'];
        }

        if (!@rename($tmpPath, self::ASSIGNMENT_PATH)) {
            @unlink($tmpPath);
            return ['ok' => false, 'error' => 'Atomic rename failed'];
        }

        clearstatcache(true, self::ASSIGNMENT_PATH);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(self::ASSIGNMENT_PATH, true);
        }

        $after = file_get_contents(self::ASSIGNMENT_PATH);
        if ($after !== false) {
            file_put_contents($snapshotDir . '/after.json', $after);
            file_put_contents($snapshotDir . '/after.hash', hash('sha256', $after));
        }

        return [
            'ok' => true,
            'path' => self::ASSIGNMENT_PATH,
            'snapshot_path' => $snapshotDir,
        ];
    }

    /**
     * @return array{ok:bool,error:string}
     */
    public static function validateOwnerKey(string $ownerKey): array
    {
        $trimmed = trim($ownerKey);
        if ($trimmed === '') {
            return ['ok' => false, 'error' => 'Owner key is required'];
        }

        try {
            $owners = OwnerStructureOwnerDiscoveryService::discover();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Owner catalogue unavailable: ' . $e->getMessage()];
        }

        $keys = array_map(static fn(array $o): string => (string)($o['owner_key'] ?? ''), $owners);
        if (!in_array($trimmed, $keys, true)) {
            return ['ok' => false, 'error' => 'Unknown owner key "' . $trimmed . '"'];
        }

        return ['ok' => true, 'error' => ''];
    }

    /**
     * @return array{ok:bool,error:string}
     */
    public static function validateWorkspaceKey(string $workspaceKey): array
    {
        $trimmed = trim($workspaceKey);
        if ($trimmed === '') {
            return ['ok' => false, 'error' => 'Workspace key is required'];
        }

        if (str_starts_with($trimmed, '/')) {
            return ['ok' => false, 'error' => 'Workspace key must not be an absolute path'];
        }

        if (str_contains($trimmed, '..')) {
            return ['ok' => false, 'error' => 'Workspace key must not contain path traversal'];
        }

        if (EngineeringWorkspaceContentContract::isReservedTemplateWorkspaceKey($trimmed)) {
            return ['ok' => false, 'error' => 'Reserved template workspace key is not a valid workspace'];
        }

        $dir = APP_ROOT . '/engineering/' . $trimmed;
        if (!is_dir($dir)) {
            return ['ok' => false, 'error' => 'Engineering workspace directory does not exist: "' . $trimmed . '"'];
        }

        $realDir = realpath($dir);
        $root = realpath(APP_ROOT . '/engineering');
        if ($realDir === false || $root === false || !str_starts_with($realDir, $root)) {
            return ['ok' => false, 'error' => 'Workspace key resolves outside engineering root'];
        }

        return ['ok' => true, 'error' => ''];
    }

    /**
     * @return string[]
     */
    public static function collectExistingWorkspaces(): array
    {
        $root = realpath(APP_ROOT . '/engineering');
        if ($root === false) {
            return [];
        }

        $workspaces = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $path => $info) {
            if (!$info->isDir()) {
                continue;
            }

            $relPath = str_replace('\\', '/', substr($path, strlen($root) + 1));

            if (EngineeringWorkspaceContentContract::isReservedTemplateWorkspaceKey($relPath)) {
                continue;
            }

            if (str_contains($relPath, '..')) {
                continue;
            }

            $hasDoc = false;
            foreach (['overview.md', 'work.md', 'rules.md', 'decisions.md'] as $fn) {
                if (is_file($path . '/' . $fn)) {
                    $hasDoc = true;
                    break;
                }
            }

            if ($hasDoc) {
                $workspaces[] = $relPath;
            }
        }

        sort($workspaces);
        return $workspaces;
    }

    /**
     * @return array{ok:bool,error:string}
     */
    public static function validateAndNormalizeAssignment(
        string $ownerKey,
        string $mode,
        string $workspaceKey,
        array $existingWorkspaces
    ): array {
        $ownerValidation = self::validateOwnerKey($ownerKey);
        if (!$ownerValidation['ok']) {
            return $ownerValidation;
        }

        $trimmedMode = trim($mode);
        if (!in_array($trimmedMode, self::VALID_MODES, true)) {
            return ['ok' => false, 'error' => 'Invalid mode "' . $mode . '". Must be: dedicated, covered_by, or skip.'];
        }

        if ($trimmedMode === self::MODE_DEDICATED) {
            $trimmedWorkspaceKey = trim($workspaceKey);
            if ($trimmedWorkspaceKey === '') {
                return ['ok' => false, 'error' => 'Workspace key is required for dedicated mode'];
            }
            if (EngineeringWorkspaceContentContract::isReservedTemplateWorkspaceKey($trimmedWorkspaceKey)) {
                return ['ok' => false, 'error' => 'Reserved template workspace key is not a valid workspace'];
            }
            if (str_starts_with($trimmedWorkspaceKey, '/') || str_contains($trimmedWorkspaceKey, '..')) {
                return ['ok' => false, 'error' => 'Invalid workspace key for dedicated mode'];
            }
        }

        if ($trimmedMode === self::MODE_COVERED_BY) {
            $trimmedWorkspaceKey = trim($workspaceKey);
            if ($trimmedWorkspaceKey === '') {
                return ['ok' => false, 'error' => 'Existing workspace key is required for covered_by mode'];
            }
            $wsValidation = self::validateWorkspaceKey($trimmedWorkspaceKey);
            if (!$wsValidation['ok']) {
                return $wsValidation;
            }
        }

        return ['ok' => true, 'error' => ''];
    }

    /**
     * @return array{ok:bool,error:string,mode?:string,workspace_key?:string}
     */
    public static function normalizeAndSet(string $ownerKey, string $mode, string $workspaceKey, ?array $actor, array $existingWorkspaces): array
    {
        $trimmedOwner = trim($ownerKey);
        $trimmedMode = trim($mode);
        $trimmedWorkspaceKey = $trimmedMode === self::MODE_SKIP ? '' : trim($workspaceKey);

        $validation = self::validateAndNormalizeAssignment($trimmedOwner, $trimmedMode, $trimmedWorkspaceKey, $existingWorkspaces);
        if (!$validation['ok']) {
            return $validation;
        }

        $assignments = self::loadAssignments();

        if ($trimmedMode === self::MODE_DEDICATED) {
            $assignments[$trimmedOwner] = [
                'mode' => self::MODE_DEDICATED,
                'workspace_key' => $trimmedWorkspaceKey,
            ];
        } elseif ($trimmedMode === self::MODE_COVERED_BY) {
            $assignments[$trimmedOwner] = [
                'mode' => self::MODE_COVERED_BY,
                'workspace_key' => $trimmedWorkspaceKey,
            ];
        } else {
            $assignments[$trimmedOwner] = [
                'mode' => self::MODE_SKIP,
            ];
        }

        $saveResult = self::saveAssignments($assignments, $actor);
        if (!$saveResult['ok']) {
            return $saveResult;
        }

        return [
            'ok' => true,
            'mode' => $trimmedMode,
            'workspace_key' => $trimmedWorkspaceKey,
        ];
    }

    public static function getAssignmentState(array $assignment): string
    {
        $mode = (string)($assignment['mode'] ?? '');
        return match ($mode) {
            self::MODE_DEDICATED => 'Dedicated workspace assigned',
            self::MODE_COVERED_BY => 'Covered by existing workspace',
            self::MODE_SKIP => 'Skipped for now',
            default => '',
        };
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function buildAssignmentRows(array $coverage, array $assignments): array
    {
        $rows = isset($coverage['rows']) && is_array($coverage['rows']) ? $coverage['rows'] : [];
        $assignmentRows = [];

        foreach ($rows as $row) {
            $state = (string)($row['state'] ?? '');
            if ($state !== 'coverage_decision_required') {
                continue;
            }

            $ownerKey = (string)($row['context_key'] ?? '');
            $contextLabel = (string)($row['context_label'] ?? $ownerKey);
            $suggestedKey = $ownerKey;

            $existingAssignment = isset($assignments[$ownerKey]) && is_array($assignments[$ownerKey])
                ? $assignments[$ownerKey]
                : null;

            $assignmentState = $existingAssignment !== null
                ? self::getAssignmentState($existingAssignment)
                : '';

            $prefilledMode = '';
            $prefilledWorkspaceKey = '';
            if ($existingAssignment !== null) {
                $prefilledMode = (string)($existingAssignment['mode'] ?? '');
                $prefilledWorkspaceKey = (string)($existingAssignment['workspace_key'] ?? '');
            }

            $assignmentRows[] = [
                'owner_key' => $ownerKey,
                'context_label' => $contextLabel,
                'suggested_workspace_key' => $suggestedKey,
                'assignment_state' => $assignmentState,
                'prefilled_mode' => $prefilledMode,
                'prefilled_workspace_key' => $prefilledWorkspaceKey,
            ];
        }

        return $assignmentRows;
    }
}
