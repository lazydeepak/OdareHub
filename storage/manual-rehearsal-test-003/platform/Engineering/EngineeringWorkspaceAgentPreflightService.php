<?php
declare(strict_types=1);

namespace Platform\Engineering;

use Platform\Security\EngineeringWorkspaceContentContract;

final class EngineeringWorkspaceAgentPreflightService
{
    private const TASK_IMPLEMENTATION_KEYWORDS = [
        'build', 'create', 'modify', 'edit', 'fix',
        'debug', 'refactor', 'upgrade', 'migrate',
        'repair', 'implement',
    ];

    private const ENGINEERING_ROOT = APP_ROOT . '/engineering';

    private const RESOLUTION_EXPLICIT = 'explicit_owner_key';
    private const RESOLUTION_COVERAGE = 'coverage_assignment';
    private const RESOLUTION_DIRECT_DIR = 'direct_owner_workspace';
    private const RESOLUTION_UNRESOLVED = 'resolution_required';
    private const RESOLUTION_NONE = 'no_workspace_context';
    private const RESOLUTION_REJECTED = 'rejected';

    /**
     * Evaluate a task and determine the bootstrap mode and resolved owner.
     *
     * @return array{ok:bool,mode:string,resolved_owner_key:string,resolution_source:string,error:string}
     */
    public static function evaluateTask(
        string $taskText,
        ?string $canonicalOwnerKey,
        array $sourcePathHints,
        ?array $actor
    ): array {
        $mode = self::detectMode($taskText);

        if ($mode === 'rejected') {
            return [
                'ok' => false,
                'mode' => 'rejected',
                'resolved_owner_key' => '',
                'resolution_source' => self::RESOLUTION_REJECTED,
                'error' => 'Task mode is invalid or unsafe',
            ];
        }

        // When the caller supplies an explicit owner key, use it directly
        if ($canonicalOwnerKey !== null && $canonicalOwnerKey !== '') {
            $safety = self::checkOwnerKeySafety($canonicalOwnerKey);
            if (!$safety['ok']) {
                return [
                    'ok' => false,
                    'mode' => $mode,
                    'resolved_owner_key' => '',
                    'resolution_source' => self::RESOLUTION_REJECTED,
                    'error' => $safety['error'],
                ];
            }

            return [
                'ok' => true,
                'mode' => $mode,
                'resolved_owner_key' => $canonicalOwnerKey,
                'resolution_source' => self::RESOLUTION_EXPLICIT,
                'error' => '',
            ];
        }

        // Try to resolve from source path hints
        if ($sourcePathHints !== []) {
            $resolved = self::resolveFromSourceHints($sourcePathHints);
            if ($resolved !== null) {
                return [
                    'ok' => true,
                    'mode' => $mode,
                    'resolved_owner_key' => $resolved,
                    'resolution_source' => self::RESOLUTION_DIRECT_DIR,
                    'error' => '',
                ];
            }
        }

        // No explicit owner and no resolution from hints
        if ($mode === 'read_only') {
            return [
                'ok' => true,
                'mode' => 'read_only',
                'resolved_owner_key' => '',
                'resolution_source' => self::RESOLUTION_NONE,
                'error' => '',
            ];
        }

        return [
            'ok' => true,
            'mode' => $mode,
            'resolved_owner_key' => '',
            'resolution_source' => self::RESOLUTION_UNRESOLVED,
            'error' => '',
        ];
    }

    /**
     * Resolve an owner key from source path hints by checking
     * if the hint is inside an engineering workspace directory
     * or if it matches a known owner pattern.
     */
    private static function resolveFromSourceHints(array $sourcePathHints): ?string
    {
        foreach ($sourcePathHints as $hint) {
            if (!is_string($hint) || trim($hint) === '') {
                continue;
            }

            $normalized = str_replace('\\', '/', trim($hint));

            // Check if hint is inside engineering root
            $engPrefix = APP_ROOT . '/engineering/';
            if (str_starts_with($normalized, $engPrefix)) {
                $relPath = substr($normalized, strlen($engPrefix));
                $parts = explode('/', $relPath);
                $ownerKey = $parts[0];
                if (count($parts) > 1) {
                    $ownerKey .= '/' . $parts[1];
                }
                $safety = self::checkOwnerKeySafety($ownerKey);
                if ($safety['ok']) {
                    $dir = self::ENGINEERING_ROOT . '/' . $ownerKey;
                    if (is_dir($dir)) {
                        return $ownerKey;
                    }
                }
            }

            // Check if hint starts with apps/ or platform/ — extract owner from path
            if (preg_match('#^(apps|platform|plugins)/([A-Za-z0-9_-]+)(/modules/([A-Za-z0-9_-]+))?#', $normalized, $m)) {
                $owner = $m[2];
                if (!empty($m[4])) {
                    $owner .= '/' . $m[4];
                }
                $safety = self::checkOwnerKeySafety($owner);
                if ($safety['ok']) {
                    $dir = self::ENGINEERING_ROOT . '/' . $owner;
                    if (is_dir($dir)) {
                        return $owner;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Detect task mode from text content.
     */
    private static function detectMode(string $taskText): string
    {
        $lower = mb_strtolower($taskText);

        foreach (self::TASK_IMPLEMENTATION_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return 'implementation';
            }
        }

        return 'read_only';
    }

    /**
     * Validate owner key safety: no traversal, no template roots, no absolute paths.
     *
     * @return array{ok:bool,error:string}
     */
    public static function checkOwnerKeySafety(string $ownerKey): array
    {
        $trimmed = trim($ownerKey);
        if ($trimmed === '') {
            return ['ok' => false, 'error' => 'Owner key is empty'];
        }

        if (str_starts_with($trimmed, '/')) {
            return ['ok' => false, 'error' => 'Owner key must not be an absolute path'];
        }

        if (str_contains($trimmed, '..')) {
            return ['ok' => false, 'error' => 'Owner key contains path traversal'];
        }

        if (EngineeringWorkspaceContentContract::isReservedTemplateWorkspaceKey($trimmed)) {
            return ['ok' => false, 'error' => 'Reserved template workspace key is not a valid owner'];
        }

        if (!preg_match('#^[A-Za-z0-9_./-]+$#', $trimmed)) {
            return ['ok' => false, 'error' => 'Owner key contains invalid characters'];
        }

        return ['ok' => true, 'error' => ''];
    }
}
