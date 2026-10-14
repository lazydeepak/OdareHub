<?php
declare(strict_types=1);

namespace Platform\Updates;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Pure V1 release-statement grammar and canonical-byte authority.
 *
 * This class does not read files, access installed state, or perform signing.
 */
final class ReleaseStatementCanonicalizer
{
    public const HEADER = 'odarehub-release-v1';
    public const PRODUCT_ID = 'susankhya-os';
    public const LANE = 'app';

    /** @var list<string> */
    private const FIELD_ORDER = [
        'product_id',
        'release_version',
        'build_id',
        'lane',
        'package_sha256',
        'package_bytes',
        'metadata_sha256',
        'minimum_supported_schema',
        'created_at',
    ];

    /**
     * @param array<string,mixed> $fields
     */
    public function canonicalize(array $fields): string
    {
        $validation = $this->validateFields($fields);
        if (!$validation['ok']) {
            throw new InvalidArgumentException((string)$validation['message']);
        }
        $lines = [self::HEADER];
        foreach (self::FIELD_ORDER as $field) {
            $lines[] = $field . '=' . $fields[$field];
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Parse exact canonical bytes. Malformed input is never normalized into a
     * different accepted statement.
     *
     * @return array<string,mixed>
     */
    public function parse(string $bytes): array
    {
        if ($bytes === '' || preg_match('//u', $bytes) !== 1 || str_contains($bytes, "\r") || !str_ends_with($bytes, "\n")) {
            return $this->failure('RAV-CANONICAL-ENCODING', 'Statement bytes are not canonical UTF-8 LF data.');
        }
        $lines = explode("\n", $bytes);
        array_pop($lines);
        if (count($lines) !== count(self::FIELD_ORDER) + 1 || ($lines[0] ?? null) !== self::HEADER) {
            return $this->failure('RAV-CANONICAL-STRUCTURE', 'Statement header or line count is invalid.');
        }

        $fields = [];
        foreach (array_slice($lines, 1) as $index => $line) {
            if (substr_count($line, '=') !== 1) {
                return $this->failure('RAV-CANONICAL-DELIMITER', 'Statement field delimiter is invalid.');
            }
            [$key, $value] = explode('=', $line, 2);
            $expected = self::FIELD_ORDER[$index] ?? null;
            if (!in_array($key, self::FIELD_ORDER, true)) {
                return $this->failure('RAV-CANONICAL-UNEXPECTED-FIELD', 'Statement contains an unexpected field.');
            }
            if (array_key_exists($key, $fields)) {
                return $this->failure('RAV-CANONICAL-DUPLICATE-FIELD', 'Statement contains a duplicate field.');
            }
            if ($key !== $expected) {
                return $this->failure('RAV-CANONICAL-FIELD-ORDER', 'Statement fields are not in canonical order.');
            }
            $fields[$key] = $value;
        }

        $validation = $this->validateFields($fields);
        if (!$validation['ok']) {
            return $this->failure('RAV-CANONICAL-VALUE', (string)$validation['message']);
        }
        return [
            'ok' => true,
            'fields' => $fields,
            'canonical_bytes' => $bytes,
            'diagnostics' => [],
        ];
    }

    public function parseUtcTimestamp(string $value): ?DateTimeImmutable
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/D', $value) !== 1) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d\TH:i:s\Z') !== $value) {
            return null;
        }
        return $parsed;
    }

    /** @param array<string,mixed> $fields */
    private function validateFields(array $fields): array
    {
        $expectedKeys = self::FIELD_ORDER;
        $actualKeys = array_keys($fields);
        sort($expectedKeys);
        $sortedActual = $actualKeys;
        sort($sortedActual);
        if ($sortedActual !== $expectedKeys) {
            return ['ok' => false, 'message' => 'Statement fields are missing or unexpected.'];
        }
        foreach ($fields as $value) {
            if (!is_string($value) || preg_match('/\A[\x20-\x7E]+\z/D', $value) !== 1 || str_contains($value, '=')) {
                return ['ok' => false, 'message' => 'Statement contains an ambiguous field value.'];
            }
        }
        if ($fields['product_id'] !== self::PRODUCT_ID || $fields['lane'] !== self::LANE) {
            return ['ok' => false, 'message' => 'Product identity or release lane is not approved.'];
        }
        if (preg_match('/\A\d+\.\d+\.\d+\z/D', $fields['release_version']) !== 1) {
            return ['ok' => false, 'message' => 'Release version is not canonical.'];
        }
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/D', $fields['build_id']) !== 1) {
            return ['ok' => false, 'message' => 'Build identity is not canonical.'];
        }
        foreach (['package_sha256', 'metadata_sha256'] as $hashField) {
            if (preg_match('/\A[0-9a-f]{64}\z/D', $fields[$hashField]) !== 1) {
                return ['ok' => false, 'message' => $hashField . ' is not lowercase SHA-256.'];
            }
        }
        if (preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $fields['package_bytes']) !== 1) {
            return ['ok' => false, 'message' => 'Package byte size is not canonical decimal.'];
        }
        if (preg_match('/\A\d+\.\d+\.\d+\z/D', $fields['minimum_supported_schema']) !== 1) {
            return ['ok' => false, 'message' => 'Minimum supported schema is not canonical.'];
        }
        if ($this->parseUtcTimestamp($fields['created_at']) === null) {
            return ['ok' => false, 'message' => 'Creation timestamp is not canonical UTC.'];
        }
        return ['ok' => true, 'message' => ''];
    }

    /** @return array<string,mixed> */
    private function failure(string $code, string $message): array
    {
        return [
            'ok' => false,
            'diagnostics' => [['code' => $code, 'message' => $message]],
        ];
    }
}
