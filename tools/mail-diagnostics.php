<?php
/**
 * Mail Diagnostics and Testing Utility
 * 
 * This script provides detailed diagnostics of mail configuration and transport,
 * with safe error reporting for dev/demo environments.
 * 
 * Usage: php tools/mail-diagnostics.php [test-email] [action]
 *   - test-email: Email to send test mail to (if not set, shows config only)
 *   - action: "config" (default), "test", "send-reset", "connection"
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/app/Core/DB.php';

use App\Services\MailConfigService;
use App\Services\MailService;
use App\Services\PasswordResetService;

class MailDiagnostics
{
    private MailConfigService $configService;
    private MailService $mailService;
    private bool $isDev;
    private array $settings;

    public function __construct()
    {
        $this->isDev = app_debug_enabled();
        $this->configService = new MailConfigService();
        $this->mailService = new MailService($this->configService);
        $this->settings = $this->configService->currentSettings();
    }

    public function run(string $testEmail = '', string $action = 'config'): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "Mail Diagnostics & Testing Utility\n";
        echo "Debug Mode: " . ($this->isDev ? "ENABLED (verbose errors)" : "DISABLED (generic messages)") . "\n";
        echo str_repeat('=', 80) . "\n\n";

        try {
            match ($action) {
                'config' => $this->showConfiguration(),
                'connection' => $this->testConnection(),
                'test' => $this->sendTestEmail($testEmail),
                'send-reset' => $this->sendPasswordResetEmail($testEmail),
                default => $this->showConfiguration(),
            };
        } catch (\Throwable $e) {
            echo "\n❌ FATAL ERROR:\n";
            echo "   " . $e->getMessage() . "\n";
            if ($this->isDev) {
                echo "\n   Stack trace:\n";
                foreach (explode("\n", $e->getTraceAsString()) as $line) {
                    echo "   " . $line . "\n";
                }
            }
        }

        echo "\n" . str_repeat('=', 80) . "\n";
    }

    private function showConfiguration(): void
    {
        echo "📋 MAIL CONFIGURATION\n\n";

        $configured = $this->configService->isConfigured($this->settings);
        echo "Status: " . ($configured ? "✅ CONFIGURED" : "⚠️  UNCONFIGURED") . "\n\n";

        if (!$configured) {
            echo "⚠️  Mail is not fully configured. Password reset emails will NOT be sent.\n";
            echo "    Minimal requirements:\n";
            echo "      • SMTP Host (required)\n";
            echo "      • From Email (required)\n";
            echo "      • SMTP Port (default: 587)\n";
            echo "      • Encryption (default: TLS)\n\n";
        }

        echo "Current Settings:\n";
        $this->printTable([
            ['Setting', 'Value'],
            ['---', '---'],
            ['Driver', (string)($this->settings['mail.driver'] ?? 'smtp')],
            ['SMTP Host', (string)($this->settings['mail.smtp_host'] ?? '[NOT SET]')],
            ['SMTP Port', (string)($this->settings['mail.smtp_port'] ?? '587')],
            ['Encryption', (string)($this->settings['mail.smtp_encryption'] ?? 'tls')],
            ['Username', $this->settings['mail.smtp_username'] ? '✓ Set' : '[NOT SET]'],
            ['Password', $this->settings['mail.smtp_password'] ? '✓ Set' : '[NOT SET]'],
            ['From Email', (string)($this->settings['mail.from_email'] ?? '[NOT SET]')],
            ['From Name', (string)($this->settings['mail.from_name'] ?? '[NOT SET]')],
            ['Reply-To Email', (string)($this->settings['mail.reply_to_email'] ?? '[NOT SET]')],
            ['Reply-To Name', (string)($this->settings['mail.reply_to_name'] ?? '[NOT SET]')],
            ['Updated By', (string)($this->settings['mail.updated_by'] ?? '-')],
            ['Updated At', (string)($this->settings['mail.updated_at'] ?? '-')],
        ]);

        echo "\nNext steps:\n";
        if ($configured) {
            echo "  1. Test SMTP connection: php tools/mail-diagnostics.php '' connection\n";
            echo "  2. Send test email:      php tools/mail-diagnostics.php your@email.com test\n";
        } else {
            echo "  1. Configure mail at:    /admin/system-tools/email-settings (web UI)\n";
            echo "     OR set environment variables:\n";
            echo "        ERP_MAIL_DRIVER=smtp\n";
            echo "        ERP_MAIL_SMTP_HOST=smtp.gmail.com\n";
            echo "        ERP_MAIL_SMTP_PORT=587\n";
            echo "        ERP_MAIL_SMTP_USERNAME=your-email@gmail.com\n";
            echo "        ERP_MAIL_SMTP_PASSWORD=your-app-password\n";
            echo "        ERP_MAIL_FROM_EMAIL=noreply@company.com\n";
            echo "        ERP_MAIL_FROM_NAME='Company Name'\n";
            echo "  2. Then return to configuration UI to verify\n";
        }
    }

    private function testConnection(): void
    {
        echo "🔌 TESTING SMTP CONNECTION\n\n";

        if (!$this->configService->isConfigured($this->settings)) {
            echo "❌ Mail not configured. Cannot test connection.\n";
            echo "   Set SMTP host and from email in configuration first.\n";
            return;
        }

        $host = (string)($this->settings['mail.smtp_host'] ?? '');
        $port = (int)($this->settings['mail.smtp_port'] ?? 587);
        $encryption = (string)($this->settings['mail.smtp_encryption'] ?? 'tls');
        $username = (string)($this->settings['mail.smtp_username'] ?? '');
        $password = (string)($this->settings['mail.smtp_password'] ?? '');

        echo "Attempting SMTP connection to: $host:$port ($encryption)\n";
        if ($username) {
            echo "  With authentication: " . substr($username, 0, 3) . "***\n";
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAuth = $username !== '';
            $mail->Username = $username;
            $mail->Password = $password;

            if ($encryption === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            echo "\nConnecting...\n";
            $mail->smtpConnect();
            
            echo "✅ SMTP connection successful!\n";
            echo "   Server: " . $mail->getLastReply() . "\n";
            
            $mail->smtpClose();
        } catch (\Throwable $e) {
            echo "❌ SMTP connection FAILED\n";
            $msg = $e->getMessage();
            echo "   Error: " . $msg . "\n";
            
            if ($this->isDev) {
                echo "\n   Debug info:\n";
                if ($e instanceof \PHPMailer\PHPMailer\Exception) {
                    echo "   PHPMailer Exception Details:\n";
                    echo "   Code: " . $e->getCode() . "\n";
                }
            }

            echo "\nCommon causes:\n";
            if (strpos($msg, 'Network is unreachable') !== false || strpos($msg, 'Connection refused') !== false) {
                echo "   • SMTP host is unreachable or DNS lookup failed\n";
                echo "   • Port is blocked by firewall or ISP\n";
            }
            if (strpos($msg, 'Authentication failed') !== false) {
                echo "   • Incorrect username or password\n";
                echo "   • Account locked or 2FA requires app-specific password\n";
            }
            if (strpos($msg, 'SSL') !== false || strpos($msg, 'TLS') !== false) {
                echo "   • Encryption mismatch (SSL vs TLS)\n";
                echo "   • SSL/TLS not supported by host\n";
            }
        }
    }

    private function sendTestEmail(string $to): void
    {
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            echo "❌ Invalid email address: $to\n";
            echo "   Usage: php tools/mail-diagnostics.php your@email.com test\n";
            return;
        }

        echo "📧 SENDING TEST EMAIL\n\n";
        echo "Recipient: $to\n";

        if (!$this->configService->isConfigured($this->settings)) {
            echo "\n❌ Mail not configured. Cannot send.\n";
            return;
        }

        try {
            $this->mailService->sendTest($to);
            echo "\n✅ Test email sent successfully!\n";
            echo "   Check your inbox (including spam/junk folders).\n";
        } catch (\Throwable $e) {
            echo "\n❌ Test email send FAILED\n";
            echo "   Error: " . $e->getMessage() . "\n";

            if ($this->isDev) {
                echo "\n   Full error:\n";
                echo "   " . $e->getTraceAsString() . "\n";
            }
        }
    }

    private function sendPasswordResetEmail(string $email): void
    {
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "❌ Invalid email address: $email\n";
            echo "   Usage: php tools/mail-diagnostics.php user@example.com send-reset\n";
            return;
        }

        echo "🔐 TESTING PASSWORD RESET EMAIL\n\n";
        echo "Email: $email\n";

        if (!$this->configService->isConfigured($this->settings)) {
            echo "\n❌ Mail not configured. Cannot send.\n";
            return;
        }

        $user = \App\Core\DB::fetchOne(
            'SELECT id, email FROM users WHERE email = ? LIMIT 1',
            [$email]
        );

        if (!$user) {
            echo "\n⚠️  User not found: $email\n";
            echo "   (Password reset flow would show generic 'check your email' message for security)\n";
            return;
        }

        try {
            $service = new PasswordResetService($this->mailService);
            $clientIp = '127.0.0.1';
            $userAgent = 'CLI Diagnostics';
            
            echo "\nRequesting password reset...\n";
            $service->requestReset($email, $clientIp, $userAgent);
            
            echo "✅ Password reset email sent successfully!\n";
            echo "   Check inbox for reset link.\n";
        } catch (\Throwable $e) {
            echo "\n❌ Password reset send FAILED\n";
            echo "   Error: " . $e->getMessage() . "\n";

            if ($this->isDev) {
                echo "\n   Full error:\n";
                echo "   " . $e->getTraceAsString() . "\n";
            }
        }
    }

    private function printTable(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $colWidths = [];
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $colWidths[$i] = max(($colWidths[$i] ?? 0), strlen($cell));
            }
        }

        foreach ($rows as $i => $row) {
            $line = '';
            foreach ($row as $j => $cell) {
                $width = $colWidths[$j] ?? 0;
                $line .= str_pad($cell, $width + 2, ' ', STR_PAD_RIGHT);
            }
            echo trim($line) . "\n";
        }
    }
}

$testEmail = (string)($argv[1] ?? '');
$action = (string)($argv[2] ?? 'config');

$diagnostics = new MailDiagnostics();
$diagnostics->run($testEmail, $action);
