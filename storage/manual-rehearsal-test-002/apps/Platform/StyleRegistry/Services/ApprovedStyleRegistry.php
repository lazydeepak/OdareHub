<?php
declare(strict_types=1);

namespace Apps\Platform\StyleRegistry\Services;

use Apps\Platform\StyleRegistry\Contracts\ApprovedStyleRegistryContract;

require_once __DIR__ . '/../Contracts/ApprovedStyleRegistryContract.php';

/**
 * Approved style registry implementation supporting radius.scale only.
 *
 * Stores approved values under Platform-owned storage. No Shell or Studio
 * runtime is connected in this slice. The storage path is internal to
 * Platform and must not be read directly by Studio or Shell.
 */
final class ApprovedStyleRegistry implements ApprovedStyleRegistryContract
{
    /** @var array<string,list<string>> Socket allowlist with allowed values. */
    private const ALLOWED_SOCKETS = [
        'radius.scale' => ['sharp', 'soft', 'round'],
    ];

    public function getValue(string $socketId): ?string
    {
        $socketId = trim($socketId);
        if ($socketId === '' || !$this->isWritable($socketId)) {
            return null;
        }

        $data = self::readStorage($socketId);
        if ($data === null) {
            return null;
        }

        $value = $data['approved_value'] ?? null;
        return is_string($value) ? $value : null;
    }

    public function setValue(string $socketId, string $value, array $context = []): array
    {
        $socketId = trim($socketId);
        $value = trim($value);

        if ($socketId === '') {
            return ['ok' => false, 'error' => 'missing_socket_id'];
        }

        $allowedValues = self::ALLOWED_SOCKETS[$socketId] ?? null;
        if ($allowedValues === null) {
            return ['ok' => false, 'error' => 'unknown_socket', 'socket' => $socketId];
        }

        if ($value === '') {
            return ['ok' => false, 'error' => 'missing_value'];
        }

        if (!in_array($value, $allowedValues, true)) {
            return [
                'ok' => false,
                'error' => 'invalid_value',
                'socket' => $socketId,
                'value' => $value,
                'allowed' => $allowedValues,
            ];
        }

        $previousValue = $this->getValue($socketId);
        $now = gmdate('c');

        $record = [
            'socket_id' => $socketId,
            'approved_value' => $value,
            'previous_value' => $previousValue,
            'status' => 'applied',
            'applied_at' => $now,
            'applied_by_user_id' => isset($context['applied_by_user_id']) ? (int)$context['applied_by_user_id'] : 0,
            'provenance' => [
                'request_id' => (string)($context['request_id'] ?? ''),
                'snapshot_id' => (string)($context['snapshot_id'] ?? ''),
            ],
        ];

        $writeOk = self::writeStorage($socketId, $record);
        if (!$writeOk) {
            return ['ok' => false, 'error' => 'write_failed'];
        }

        return ['ok' => true, 'socket' => $socketId, 'value' => $value];
    }

    public function isWritable(string $socketId): bool
    {
        return isset(self::ALLOWED_SOCKETS[trim($socketId)]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function readStorage(string $socketId): ?array
    {
        $path = self::storagePath($socketId);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function writeStorage(string $socketId, array $data): bool
    {
        $dir = self::storageDir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $path = self::storagePath($socketId);
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || $encoded === '') {
            return false;
        }

        return @file_put_contents($path, $encoded . PHP_EOL) !== false;
    }

    private static function storagePath(string $socketId): string
    {
        return self::storageDir() . '/' . $socketId . '.json';
    }

    private static function storageDir(): string
    {
        return APP_ROOT . '/storage/platform/style-registry/approved-values';
    }
}
