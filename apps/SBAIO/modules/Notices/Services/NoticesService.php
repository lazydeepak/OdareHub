<?php
declare(strict_types=1);

namespace Plugins\Notices\Services;

use App\Core\DB;

final class NoticesService
{
    /**
     * Validate and normalize the existing Notice creation payload without
     * changing its persistence shape or acknowledgment workflow.
     *
     * @param array<string,mixed> $input
     * @return array{valid:bool,errors:list<string>,values:array<string,mixed>}
     */
    public static function validateCreateInput(array $input): array
    {
        $errors = [];
        $title = self::scalarString($input, 'title');
        $body = self::scalarString($input, 'body');
        $noticeType = self::scalarString($input, 'notice_type');
        $targetPeriod = self::scalarString($input, 'target_period');
        $noticeLevel = self::scalarString($input, 'notice_level');

        if ($title === null) {
            $errors[] = 'title_invalid';
            $title = '';
        } else {
            $title = trim($title);
            if ($title === '') {
                $errors[] = 'title_required';
            } elseif (strlen($title) > 190) {
                $errors[] = 'title_too_long';
            }
        }

        $body = self::validateOptionalText($body, 'body', $errors);
        $noticeType = self::validateOptionalText($noticeType, 'notice_type', $errors, 80);
        $targetPeriod = self::validateOptionalText($targetPeriod, 'target_period', $errors, 40);
        $noticeLevel = self::validateOptionalText($noticeLevel, 'notice_level', $errors, 40);
        if ($noticeLevel === '') {
            $noticeLevel = 'info';
        }

        $relatedStaffId = self::normalizeRelatedStaffId($input['related_staff_id'] ?? null);
        if ($relatedStaffId === false) {
            $errors[] = 'related_staff_id_invalid';
            $relatedStaffId = null;
        }

        $requiresAck = self::normalizeBinaryFlag($input, 'requires_ack', $errors);
        $isActive = self::normalizeBinaryFlag($input, 'is_active', $errors);

        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'values' => [
                'title' => $title,
                'body' => $body,
                'notice_type' => $noticeType,
                'target_period' => $targetPeriod,
                'related_staff_id' => $relatedStaffId,
                'requires_ack' => $requiresAck,
                'notice_level' => $noticeLevel,
                'is_active' => $isActive,
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recent(int $limit = 25): array
    {
        return DB::fetchAll(
            'SELECT id, title, notice_type, target_period, notice_level, requires_ack, is_active, created_at FROM sbaio_notices ORDER BY id DESC LIMIT ' . (int)$limit
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function activeStaff(): array
    {
        return DB::fetchAll(
            "SELECT id, full_name, employee_code FROM sbaio_staff WHERE employment_status='active' OR employment_status IS NULL ORDER BY full_name ASC"
        );
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function create(array $input): void
    {
        $validated = self::validateCreateInput($input);
        if (!$validated['valid']) {
            throw new \InvalidArgumentException('Invalid Notice input.');
        }
        $values = $validated['values'];

        DB::query(
            'INSERT INTO sbaio_notices (title, body, notice_type, target_period, related_staff_id, requires_ack, notice_level, is_active) VALUES (?,?,?,?,?,?,?,?)',
            [
                self::nullIfBlank($values['title']),
                self::nullIfBlank($values['body']),
                self::nullIfBlank($values['notice_type']),
                self::nullIfBlank($values['target_period']),
                $values['related_staff_id'],
                $values['requires_ack'],
                self::nullIfBlank($values['notice_level']) ?? 'info',
                $values['is_active'],
            ]
        );
    }

    /** @return string|null */
    private static function scalarString(array $input, string $key): ?string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return '';
        }
        return is_string($input[$key]) ? $input[$key] : null;
    }

    /** @param list<string> $errors */
    private static function validateOptionalText(?string $value, string $field, array &$errors, int $maxLength = PHP_INT_MAX): string
    {
        if ($value === null) {
            $errors[] = $field . '_invalid';
            return '';
        }
        $value = trim($value);
        if (strlen($value) > $maxLength) {
            $errors[] = $field . '_too_long';
        }
        return $value;
    }

    /** @return int|null|false */
    private static function normalizeRelatedStaffId(mixed $value): int|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            if ($value === 0) {
                return null;
            }
            return $value > 0 && $value <= 2147483647 ? $value : false;
        }
        if (!is_string($value)) {
            return false;
        }
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d+$/D', $value)) {
            return false;
        }
        $value = ltrim($value, '0');
        if ($value === '') {
            return null;
        }
        $normalized = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        return $normalized === false ? false : (int)$normalized;
    }

    /** @param list<string> $errors */
    private static function normalizeBinaryFlag(array $input, string $field, array &$errors): int
    {
        if (!array_key_exists($field, $input) || $input[$field] === null) {
            return 0;
        }
        $value = $input[$field];
        if ($value === 0 || $value === '0') {
            return 0;
        }
        if ($value === 1 || $value === '1') {
            return 1;
        }
        $errors[] = $field . '_invalid';
        return 0;
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
