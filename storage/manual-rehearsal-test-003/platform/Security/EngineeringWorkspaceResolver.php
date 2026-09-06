<?php
declare(strict_types=1);

namespace Platform\Security;

final class EngineeringWorkspaceResolver
{
    private const ENGINEERING_ROOT = APP_ROOT . '/engineering';

    /**
     * @param string $workspaceKey Canonical workspace key (e.g. "Studio/tools/CustomizationStudio")
     * @param string $documentType Allowed: overview, work, rules, decisions
     * @param array<string,mixed>|null $actor Resolved user context
     * @return array<string,mixed>
     */
    public static function resolve(string $workspaceKey, string $documentType, ?array $actor): array
    {
        if (!PlatformAuthority::canManageEngineeringWorkspaces($actor)) {
            return [
                'authorized' => false,
                'workspace_key' => $workspaceKey,
                'error' => 'Unauthorized',
            ];
        }

        if (!self::isValidWorkspaceKey($workspaceKey)) {
            return [
                'authorized' => true,
                'workspace_key' => $workspaceKey,
                'exists' => false,
                'error' => 'Invalid workspace key',
            ];
        }

        $filename = self::resolveFilename($documentType);
        if ($filename === null) {
            return [
                'authorized' => true,
                'workspace_key' => $workspaceKey,
                'exists' => false,
                'error' => 'Invalid document type',
            ];
        }

        $workspaceDir = self::resolveWorkspaceDir($workspaceKey);
        $path = $workspaceDir . '/' . $filename;
        $realPath = realpath($path);
        $registryRoot = realpath(self::ENGINEERING_ROOT);

        // Prevent symlink escape and path traversal
        if ($registryRoot === false || $realPath === false) {
            return [
                'authorized' => true,
                'workspace_key' => $workspaceKey,
                'document_type' => $documentType,
                'path' => self::relativePath($path),
                'exists' => false,
                'content' => null,
            ];
        }

        if (!self::pathIsInsideRoot($realPath, $registryRoot)) {
            return [
                'authorized' => true,
                'workspace_key' => $workspaceKey,
                'document_type' => $documentType,
                'path' => self::relativePath($path),
                'exists' => false,
                'error' => 'Path traversal detected',
            ];
        }

        if (!is_file($realPath) || !is_readable($realPath)) {
            return [
                'authorized' => true,
                'workspace_key' => $workspaceKey,
                'document_type' => $documentType,
                'path' => self::relativePath($realPath),
                'exists' => false,
                'content' => null,
            ];
        }

        $content = file_get_contents($realPath);

        return [
            'authorized' => true,
            'workspace_key' => $workspaceKey,
            'document_type' => $documentType,
            'path' => self::relativePath($realPath),
            'exists' => true,
            'content' => $content !== false ? $content : null,
            'fingerprint' => $content !== false ? self::fingerprint($content) : null,
        ];
    }

    /**
     * Write content to a workspace document file.
     * Only allowed for platform admin, valid workspace keys, and allowed document types.
     *
     * @return array<string,mixed> Write result with 'ok' (bool), 'error' (string), 'path' (string)
     */
    public static function write(string $workspaceKey, string $documentType, string $content, ?array $actor): array
    {
        return self::writeWithFingerprint($workspaceKey, $documentType, $content, null, $actor);
    }

    /**
     * @return array<string,mixed>
     */
    public static function writeWithFingerprint(string $workspaceKey, string $documentType, string $content, ?string $submittedFingerprint, ?array $actor): array
    {
        if (!PlatformAuthority::canManageEngineeringWorkspaces($actor)) {
            return ['ok' => false, 'error' => 'Unauthorized'];
        }

        if ($submittedFingerprint === null || trim($submittedFingerprint) === '') {
            return ['ok' => false, 'error' => 'Missing source fingerprint', 'stale' => false];
        }

        if (strlen($content) > 524288) {
            return ['ok' => false, 'error' => 'Markdown content is too large', 'stale' => false];
        }

        if (!preg_match('//u', $content)) {
            return ['ok' => false, 'error' => 'Markdown content must be valid UTF-8', 'stale' => false];
        }

        if (!self::isValidWorkspaceKey($workspaceKey)) {
            return ['ok' => false, 'error' => 'Invalid workspace key'];
        }

        $filename = self::resolveFilename($documentType);
        if ($filename === null) {
            return ['ok' => false, 'error' => 'Invalid document type'];
        }

        $workspaceDir = self::resolveWorkspaceDir($workspaceKey);
        $path = $workspaceDir . '/' . $filename;
        $realPath = realpath($path);

        // File must resolve inside engineering root
        $registryRoot = realpath(self::ENGINEERING_ROOT);
        if ($registryRoot === false || $realPath === false) {
            return ['ok' => false, 'error' => 'Could not resolve workspace path'];
        }

        if (!self::pathIsInsideRoot($realPath, $registryRoot)) {
            return ['ok' => false, 'error' => 'Path traversal detected'];
        }

        if (!is_file($realPath) || !is_readable($realPath) || !is_writable($realPath)) {
            return ['ok' => false, 'error' => 'Document is not writable'];
        }

        $currentContent = file_get_contents($realPath);
        if ($currentContent === false) {
            return ['ok' => false, 'error' => 'Could not read current document'];
        }

        $currentFingerprint = self::fingerprint($currentContent);
        if (!hash_equals($currentFingerprint, trim($submittedFingerprint))) {
            return [
                'ok' => false,
                'error' => 'This document changed after you opened it. Reload the current document before saving.',
                'stale' => true,
                'current_fingerprint' => $currentFingerprint,
            ];
        }

        // Write atomically via tempfile + rename
        $tmpPath = $realPath . '.tmp.' . bin2hex(random_bytes(8));
        $written = @file_put_contents($tmpPath, $content, LOCK_EX);
        if ($written === false) {
            @unlink($tmpPath);
            return ['ok' => false, 'error' => 'Write failed'];
        }
        if (!@rename($tmpPath, $realPath)) {
            @unlink($tmpPath);
            return ['ok' => false, 'error' => 'Rename failed'];
        }
        clearstatcache(true, $realPath);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($realPath, true);
        }

        $storedContent = file_get_contents($realPath);
        if ($storedContent === false) {
            return ['ok' => false, 'error' => 'Saved document could not be re-read'];
        }

        return [
            'ok' => true,
            'error' => '',
            'path' => self::relativePath($realPath),
            'content' => $storedContent,
            'fingerprint' => self::fingerprint($storedContent),
        ];
    }

    public static function fingerprint(string $content): string
    {
        return hash('sha256', $content);
    }

    /**
     * Build workspace links for the given workspace key (only for platform admin).
     *
     * @return array<string,mixed>|null null for non-admin
     */
    public static function buildWorkspaceLinks(string $workspaceKey, ?array $actor): ?array
    {
        if (!PlatformAuthority::canManageEngineeringWorkspaces($actor)) {
            return null;
        }

        if (!self::isValidWorkspaceKey($workspaceKey)) {
            return null;
        }

        $encodedKey = rawurlencode($workspaceKey);
        $workspaceDir = self::resolveWorkspaceDir($workspaceKey);

        $hasOverview = is_file($workspaceDir . '/overview.md');
        $hasWork = is_file($workspaceDir . '/work.md');
        $hasRules = is_file($workspaceDir . '/rules.md');
        $hasDecisions = is_file($workspaceDir . '/decisions.md');

        return [
            'available' => $hasOverview || $hasWork || $hasRules || $hasDecisions,
            'workspace_key' => $workspaceKey,
            'workspace_label' => $workspaceKey,
            'overview_url' => '/apps/studio/engineering-workspaces?workspace_key=' . $encodedKey . '&document=overview',
            'work_url' => '/apps/studio/engineering-workspaces?workspace_key=' . $encodedKey . '&document=work',
            'rules_url' => '/apps/studio/engineering-workspaces?workspace_key=' . $encodedKey . '&document=rules',
            'decisions_url' => '/apps/studio/engineering-workspaces?workspace_key=' . $encodedKey . '&document=decisions',
            'has_overview' => $hasOverview,
            'has_work' => $hasWork,
            'has_rules' => $hasRules,
            'has_decisions' => $hasDecisions,
        ];
    }

    /**
     * Map a canonical owner key to its engineering workspace path.
     *
     * Manufacturing/Products → Manufacturing/Products
     * Studio → Studio
     */
    public static function ownerKeyToWorkspaceKey(string $ownerKey): string
    {
        return trim($ownerKey, "/ \t\n\r\0\x0B");
    }

    public static function isValidWorkspaceKey(string $workspaceKey): bool
    {
        $trimmed = trim($workspaceKey);
        if ($trimmed === '') {
            return false;
        }
        // Allow alphanumeric, slashes, hyphens, underscores, dots
        if (!preg_match('#^[A-Za-z0-9_./-]+$#', $trimmed)) {
            return false;
        }
        // Block path traversal sequences
        if (str_contains($trimmed, '..')) {
            return false;
        }

        return EngineeringWorkspaceContentContract::isSupportedWorkspaceKey($trimmed);
    }

    /**
     * @return string[]
     */
    public static function getAllowedDocumentTypes(): array
    {
        return EngineeringWorkspaceContentContract::allowedDocumentKeys();
    }

    private static function resolveFilename(string $documentType): ?string
    {
        return EngineeringWorkspaceContentContract::canonicalFilename($documentType);
    }

    private static function resolveWorkspaceDir(string $workspaceKey): string
    {
        return self::ENGINEERING_ROOT . '/' . $workspaceKey;
    }

    private static function relativePath(string $absolutePath): string
    {
        $root = rtrim(APP_ROOT, '/');
        if (strncmp($absolutePath, $root, strlen($root)) === 0) {
            return ltrim(substr($absolutePath, strlen($root)), '/');
        }
        return $absolutePath;
    }

    private static function pathIsInsideRoot(string $path, string $root): bool
    {
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');

        return $normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot . '/');
    }
}
