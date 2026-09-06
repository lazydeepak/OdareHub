<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

final class MailConfigService
{
    private const DRIVER_OPTIONS = [
        'smtp' => 'SMTP',
        'disabled' => 'Disabled',
    ];

    private const ENCRYPTION_OPTIONS = [
        '' => 'None',
        'tls' => 'TLS',
        'ssl' => 'SSL',
    ];

    public function currentSettings(): array
    {
        $raw = $this->rawSettings();
        $companyName = trim(core_setting('company.name', core_setting('system.name', APP_NAME)));
        $companyEmail = trim(core_setting('company.email', ''));

        return [
            'mail.driver' => $this->normalizeDriver((string)($this->getEnvOr('MAIL_DRIVER', $raw['mail.driver'] ?? 'smtp'))),
            'mail.smtp_host' => trim((string)($this->getEnvOr('MAIL_SMTP_HOST', $raw['mail.smtp_host'] ?? ''))),
            'mail.smtp_port' => $this->normalizePort((string)($this->getEnvOr('MAIL_SMTP_PORT', $raw['mail.smtp_port'] ?? '587'))),
            'mail.smtp_username' => trim((string)($this->getEnvOr('MAIL_SMTP_USERNAME', $raw['mail.smtp_username'] ?? ''))),
            'mail.smtp_password' => trim((string)($this->getEnvOr('MAIL_SMTP_PASSWORD', $raw['mail.smtp_password'] ?? ''))),
            'mail.smtp_encryption' => $this->normalizeEncryption((string)($this->getEnvOr('MAIL_SMTP_ENCRYPTION', $raw['mail.smtp_encryption'] ?? 'tls'))),
            'mail.from_email' => strtolower(trim((string)($this->getEnvOr('MAIL_FROM_EMAIL', $raw['mail.from_email'] ?? $companyEmail)))),
            'mail.from_name' => trim((string)($this->getEnvOr('MAIL_FROM_NAME', $raw['mail.from_name'] ?? ($companyName !== '' ? $companyName : APP_NAME)))),
            'mail.reply_to_email' => strtolower(trim((string)($this->getEnvOr('MAIL_REPLY_TO_EMAIL', $raw['mail.reply_to_email'] ?? '')))),
            'mail.reply_to_name' => trim((string)($this->getEnvOr('MAIL_REPLY_TO_NAME', $raw['mail.reply_to_name'] ?? ''))),
            'mail.updated_at' => trim((string)($raw['mail.updated_at'] ?? '')),
            'mail.updated_by' => trim((string)($raw['mail.updated_by'] ?? '')),
        ];
    }

    public function dashboard(): array
    {
        $settings = $this->currentSettings();
        $isConfigured = $this->isConfigured($settings);

        return [
            'settings' => $settings,
            'masked' => $this->maskedSettings($settings),
            'is_configured' => $isConfigured,
            'driver_options' => self::DRIVER_OPTIONS,
            'encryption_options' => self::ENCRYPTION_OPTIONS,
        ];
    }

    public function save(array $input): array
    {
        $existing = $this->currentSettings();
        $driver = $this->normalizeDriver((string)($input['mail_driver'] ?? ($existing['mail.driver'] ?? 'smtp')));
        $host = trim((string)($input['mail_smtp_host'] ?? ''));
        $port = $this->normalizePort((string)($input['mail_smtp_port'] ?? '587'));
        $username = trim((string)($input['mail_smtp_username'] ?? ''));
        $password = (string)($input['mail_smtp_password'] ?? '');
        $encryption = $this->normalizeEncryption((string)($input['mail_smtp_encryption'] ?? 'tls'));
        $fromEmail = strtolower(trim((string)($input['mail_from_email'] ?? '')));
        $fromName = trim((string)($input['mail_from_name'] ?? ''));
        $replyToEmail = strtolower(trim((string)($input['mail_reply_to_email'] ?? '')));
        $replyToName = trim((string)($input['mail_reply_to_name'] ?? ''));

        if ($password === '') {
            $password = (string)($existing['mail.smtp_password'] ?? '');
        }

        $settings = [
            'mail.driver' => $driver,
            'mail.smtp_host' => $host,
            'mail.smtp_port' => $port,
            'mail.smtp_username' => $username,
            'mail.smtp_password' => $password,
            'mail.smtp_encryption' => $encryption,
            'mail.from_email' => $fromEmail,
            'mail.from_name' => $fromName,
            'mail.reply_to_email' => $replyToEmail,
            'mail.reply_to_name' => $replyToName,
        ];

        $this->validate($settings);

        $actor = (string)(Auth::user()['email'] ?? 'system');
        $settings['mail.updated_at'] = date('Y-m-d H:i:s');
        $settings['mail.updated_by'] = $actor;
        $this->persistSettings($settings);

        return $this->currentSettings();
    }

    public function maskedSettings(?array $settings = null): array
    {
        $settings = $settings ?? $this->currentSettings();
        $masked = $settings;
        $password = trim((string)($settings['mail.smtp_password'] ?? ''));
        $masked['mail.smtp_password_masked'] = $password === '' ? 'Not set' : str_repeat('•', 10);
        return $masked;
    }

    public function validate(array $settings): void
    {
        $driver = $this->normalizeDriver((string)($settings['mail.driver'] ?? 'smtp'));
        if ($driver === 'disabled') {
            return;
        }

        if (trim((string)($settings['mail.smtp_host'] ?? '')) === '') {
            throw new \RuntimeException('SMTP host is required.');
        }

        $port = (int)$this->normalizePort((string)($settings['mail.smtp_port'] ?? '587'));
        if ($port <= 0 || $port > 65535) {
            throw new \RuntimeException('SMTP port must be between 1 and 65535.');
        }

        $fromEmail = strtolower(trim((string)($settings['mail.from_email'] ?? '')));
        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('From email must be a valid email address.');
        }

        $replyToEmail = strtolower(trim((string)($settings['mail.reply_to_email'] ?? '')));
        if ($replyToEmail !== '' && !filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Reply-to email must be a valid email address.');
        }
    }

    public function isConfigured(?array $settings = null): bool
    {
        $settings = $settings ?? $this->currentSettings();
        if ($this->normalizeDriver((string)($settings['mail.driver'] ?? 'smtp')) === 'disabled') {
            return false;
        }

        return trim((string)($settings['mail.smtp_host'] ?? '')) !== ''
            && trim((string)($settings['mail.from_email'] ?? '')) !== '';
    }

    public function driverOptions(): array
    {
        return self::DRIVER_OPTIONS;
    }

    public function encryptionOptions(): array
    {
        return self::ENCRYPTION_OPTIONS;
    }

    private function rawSettings(): array
    {
        try {
            $rows = DB::fetchAll(
                "SELECT setting_key, setting_value
                 FROM core_settings
                 WHERE setting_key LIKE 'mail.%'
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

    private function normalizeDriver(string $driver): string
    {
        $key = strtolower(trim($driver));
        return array_key_exists($key, self::DRIVER_OPTIONS) ? $key : 'smtp';
    }

    private function normalizeEncryption(string $encryption): string
    {
        $key = strtolower(trim($encryption));
        return array_key_exists($key, self::ENCRYPTION_OPTIONS) ? $key : 'tls';
    }

    private function normalizePort(string $port): string
    {
        $value = (int)trim($port);
        if ($value <= 0) {
            $value = 587;
        }
        return (string)$value;
    }

    private function getEnvOr(string $key, mixed $default = null): mixed
    {
        $value = getenv('ERP_' . $key);
        if ($value === false || $value === '') {
            return $default;
        }
        return $value;
    }
}
