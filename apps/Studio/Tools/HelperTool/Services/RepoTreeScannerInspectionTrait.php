<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\HelperTool\Services;

trait RepoTreeScannerInspectionTrait
{
    /**
     * @return array<string,mixed>
     */
    public static function inspectFileFromRoot(string $relativePath, ?string $root = null): array
    {
        $rootPath = (string)(realpath($root ?? APP_ROOT) ?: ($root ?? APP_ROOT));
        $normalizedPath = self::normalizeRequestedPath($relativePath);
        if ($normalizedPath === '' || $normalizedPath === '.') {
            return self::inspectionError($normalizedPath, 'invalid_path');
        }

        $absolutePath = $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
        $realPath = realpath($absolutePath);
        $rootPrefix = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($realPath === false || !str_starts_with($realPath, $rootPrefix)) {
            return self::inspectionError($normalizedPath, 'path_outside_root');
        }
        if (!is_file($realPath)) {
            return self::inspectionError($normalizedPath, 'not_a_file');
        }

        $size = self::safeFileSize($realPath);
        if ($size < 0) {
            $size = 0;
        }

        $name = basename($realPath);
        $typeKey = self::fileTypeKey($name);
        $classification = self::classifyFile($normalizedPath, $name, $typeKey, $size);
        $preview = self::buildPreview($realPath, $size);
        $ownerGuess = self::guessOwnerForPath($normalizedPath);
        $owners = self::discoverOwners($rootPath);
        $ownerKey = self::assignOwnerKey($normalizedPath, $owners);

        return [
            'ok' => true,
            'path' => $normalizedPath,
            'name' => $name,
            'type' => self::fileTypeLabel($typeKey),
            'type_key' => $typeKey,
            'size' => $size,
            'categories' => $classification['categories'],
            'reasons' => $classification['reasons'],
            'owner_guess' => $ownerGuess['owner'],
            'path_scope' => $ownerGuess['scope'],
            'owner_key' => $ownerKey,
            'preview' => $preview,
        ];
    }
    private static function normalizeRequestedPath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $relativePath = ltrim($relativePath, '/');
        $parts = [];
        foreach (explode('/', $relativePath) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return '';
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    /**
     * @return array<string,mixed>
     */
    private static function inspectionError(string $relativePath, string $reason): array
    {
        return [
            'ok' => false,
            'path' => $relativePath,
            'reason' => $reason,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildPreview(string $path, int $size): array
    {
        $bytesToRead = min($size, self::PREVIEW_MAX_BYTES + 1);
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [
                'mode' => 'unavailable',
                'is_text' => false,
                'is_truncated' => false,
                'max_bytes' => self::PREVIEW_MAX_BYTES,
                'lines' => [],
            ];
        }

        $sample = $bytesToRead > 0 ? (string)fread($handle, $bytesToRead) : '';
        fclose($handle);

        if (str_contains($sample, "\0") || preg_match('//u', $sample) !== 1) {
            return [
                'mode' => 'metadata_only',
                'is_text' => false,
                'is_truncated' => $size > self::PREVIEW_MAX_BYTES,
                'max_bytes' => self::PREVIEW_MAX_BYTES,
                'lines' => [],
            ];
        }

        $previewText = substr($sample, 0, self::PREVIEW_MAX_BYTES);
        $isTruncated = $size > self::PREVIEW_MAX_BYTES || strlen($sample) > self::PREVIEW_MAX_BYTES;
        $rawLines = explode("\n", str_replace("\r\n", "\n", str_replace("\r", "\n", $previewText)));
        $lines = [];
        foreach ($rawLines as $index => $line) {
            if ($index >= self::PREVIEW_MAX_LINES) {
                $isTruncated = true;
                break;
            }
            $lines[] = [
                'line' => $index + 1,
                'text' => $line,
            ];
        }

        return [
            'mode' => 'text',
            'is_text' => true,
            'is_truncated' => $isTruncated,
            'max_bytes' => self::PREVIEW_MAX_BYTES,
            'max_lines' => self::PREVIEW_MAX_LINES,
            'lines' => $lines,
        ];
    }
    /**
     * @return array{owner:string,scope:string}
     */
    private static function guessOwnerForPath(string $relativePath): array
    {
        $parts = explode('/', $relativePath);
        if (($parts[0] ?? '') === 'apps' && isset($parts[1])) {
            if (($parts[2] ?? '') === 'modules' && isset($parts[3])) {
                return ['owner' => $parts[1] . '/' . $parts[3], 'scope' => 'app_module'];
            }
            return ['owner' => $parts[1], 'scope' => 'app'];
        }
        if (($parts[0] ?? '') === 'plugins' && isset($parts[1])) {
            return ['owner' => 'Plugin/' . $parts[1], 'scope' => 'plugin'];
        }
        if (($parts[0] ?? '') === 'engineering' && isset($parts[1])) {
            return ['owner' => implode('/', array_slice($parts, 1, -1)) ?: $parts[1], 'scope' => 'engineering_workspace'];
        }
        if (($parts[0] ?? '') === 'platform') {
            return ['owner' => 'Platform', 'scope' => 'platform'];
        }
        if (($parts[0] ?? '') === 'public') {
            return ['owner' => 'Public assets', 'scope' => 'public'];
        }
        if (($parts[0] ?? '') === 'storage') {
            return ['owner' => 'Storage', 'scope' => 'runtime_storage'];
        }
        if (($parts[0] ?? '') === 'app') {
            return ['owner' => 'Core app', 'scope' => 'app_core'];
        }
        if (($parts[0] ?? '') === 'packages') {
            return ['owner' => 'Packages', 'scope' => 'package'];
        }
        return ['owner' => 'Unknown', 'scope' => 'unknown'];
    }
}
