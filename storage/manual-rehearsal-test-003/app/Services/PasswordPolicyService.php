<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

final class PasswordPolicyService
{
    private const COMMON_PASSWORDS = [
        'password',
        'password123',
        'qwerty123',
        'welcome123',
        'admin123',
        'letmein123',
        'changeme123',
        '12345678',
    ];

    public function currentPolicy(): array
    {
        $raw = $this->rawSettings();

        return [
            'security.password_min_length' => $this->normalizeMinLength((string)($raw['security.password_min_length'] ?? '10')),
            'security.password_require_lowercase' => $this->normalizeBool((string)($raw['security.password_require_lowercase'] ?? '1')),
            'security.password_require_uppercase' => $this->normalizeBool((string)($raw['security.password_require_uppercase'] ?? '1')),
            'security.password_require_number' => $this->normalizeBool((string)($raw['security.password_require_number'] ?? '1')),
            'security.password_special_bonus' => $this->normalizeBool((string)($raw['security.password_special_bonus'] ?? '1')),
            'security.password_updated_at' => trim((string)($raw['security.password_updated_at'] ?? '')),
            'security.password_updated_by' => trim((string)($raw['security.password_updated_by'] ?? '')),
        ];
    }

    public function save(array $input): array
    {
        $policy = [
            'security.password_min_length' => $this->normalizeMinLength((string)($input['password_min_length'] ?? '10')),
            'security.password_require_lowercase' => $this->normalizeBool((string)($input['password_require_lowercase'] ?? '1')),
            'security.password_require_uppercase' => $this->normalizeBool((string)($input['password_require_uppercase'] ?? '1')),
            'security.password_require_number' => $this->normalizeBool((string)($input['password_require_number'] ?? '1')),
            'security.password_special_bonus' => $this->normalizeBool((string)($input['password_special_bonus'] ?? '1')),
        ];

        $actor = (string)(Auth::user()['email'] ?? 'system');
        $policy['security.password_updated_at'] = date('Y-m-d H:i:s');
        $policy['security.password_updated_by'] = $actor;

        $this->persistSettings($policy);
        return $this->currentPolicy();
    }

    public function describePolicy(): array
    {
        $policy = $this->currentPolicy();

        return [
            'min_length' => (int)$policy['security.password_min_length'],
            'require_lowercase' => (string)$policy['security.password_require_lowercase'] === '1',
            'require_uppercase' => (string)$policy['security.password_require_uppercase'] === '1',
            'require_number' => (string)$policy['security.password_require_number'] === '1',
            'special_bonus' => (string)$policy['security.password_special_bonus'] === '1',
        ];
    }

    public function validate(string $password, ?string $confirm = null): array
    {
        $policy = $this->describePolicy();
        $checks = $this->checks($password, $policy);
        $errors = [];

        if (!$checks['min_length']) {
            $errors[] = 'Password must be at least ' . $policy['min_length'] . ' characters.';
        }
        if ($policy['require_lowercase'] && !$checks['lowercase']) {
            $errors[] = 'Password must include at least one lowercase letter.';
        }
        if ($policy['require_uppercase'] && !$checks['uppercase']) {
            $errors[] = 'Password must include at least one uppercase letter.';
        }
        if ($policy['require_number'] && !$checks['number']) {
            $errors[] = 'Password must include at least one number.';
        }
        if ($checks['common']) {
            $errors[] = 'Choose a less common password.';
        }
        if ($confirm !== null && !hash_equals($password, $confirm)) {
            $errors[] = 'Password confirmation does not match.';
        }

        $strength = $this->strength($password);

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'checks' => $checks,
            'strength' => $strength,
            'policy' => $policy,
        ];
    }

    public function assertValid(string $password, ?string $confirm = null): void
    {
        $result = $this->validate($password, $confirm);
        if (!$result['ok']) {
            throw new \RuntimeException((string)($result['errors'][0] ?? 'Password does not meet the security policy.'));
        }
    }

    public function strength(string $password): array
    {
        $policy = $this->describePolicy();
        $checks = $this->checks($password, $policy);
        $score = 0;
        $length = strlen($password);

        if ($length >= (int)$policy['min_length']) {
            $score += 1;
        }
        if ($length >= max(12, (int)$policy['min_length'] + 2)) {
            $score += 1;
        }
        if ($checks['lowercase']) {
            $score += 1;
        }
        if ($checks['uppercase']) {
            $score += 1;
        }
        if ($checks['number']) {
            $score += 1;
        }
        if ($checks['special']) {
            $score += 1;
        }
        if ($checks['common']) {
            $score = max(0, $score - 2);
        }

        $label = match (true) {
            $score <= 2 => 'Weak',
            $score === 3 || $score === 4 => 'Fair',
            $score === 5 => 'Strong',
            default => 'Very Strong',
        };

        return [
            'score' => $score,
            'label' => $label,
            'checks' => $checks,
        ];
    }

    private function checks(string $password, array $policy): array
    {
        $normalized = strtolower(trim($password));

        return [
            'min_length' => strlen($password) >= (int)$policy['min_length'],
            'lowercase' => preg_match('/[a-z]/', $password) === 1,
            'uppercase' => preg_match('/[A-Z]/', $password) === 1,
            'number' => preg_match('/[0-9]/', $password) === 1,
            'special' => preg_match('/[^A-Za-z0-9]/', $password) === 1,
            'common' => in_array($normalized, self::COMMON_PASSWORDS, true),
        ];
    }

    private function rawSettings(): array
    {
        try {
            $rows = DB::fetchAll(
                "SELECT setting_key, setting_value
                 FROM core_settings
                 WHERE setting_key LIKE 'security.password_%'
                 ORDER BY setting_key ASC"
            );
        } catch (\Throwable) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $key = trim((string)($row['setting_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $map[$key] = (string)($row['setting_value'] ?? '');
        }

        return $map;
    }

    private function persistSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            DB::query(
                'INSERT INTO core_settings (setting_key, setting_value, updated_at) VALUES (?,?,NOW())
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW()',
                [$key, (string)$value]
            );
        }
    }

    private function normalizeMinLength(string $length): string
    {
        $value = (int)trim($length);
        if ($value < 10) {
            $value = 10;
        }
        if ($value > 128) {
            $value = 128;
        }
        return (string)$value;
    }

    private function normalizeBool(string $value): string
    {
        return trim($value) === '0' ? '0' : '1';
    }
}
