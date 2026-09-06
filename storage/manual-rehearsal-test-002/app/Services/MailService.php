<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    public function __construct(
        private ?MailConfigService $configService = null,
        private ?MailTemplateService $templateService = null,
    ) {
        $this->configService = $this->configService ?? new MailConfigService();
        $this->templateService = $this->templateService ?? new MailTemplateService();
    }

    public function send(string $to, string $subject, string $template, array $data = []): void
    {
        $rendered = $this->templateService->render($template, $data + [
            'app_name' => app_display_name(),
        ]);

        $resolvedSubject = trim((string)$subject);
        if ($resolvedSubject === '') {
            $resolvedSubject = (string)($rendered['subject'] ?? '');
        }

        $this->sendRaw($to, $resolvedSubject, (string)($rendered['html'] ?? ''), (string)($rendered['text'] ?? ''));
    }

    public function sendRaw(string $to, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        $recipient = strtolower(trim($to));
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Recipient email must be valid.');
        }

        $settings = $this->configService->currentSettings();
        $this->configService->validate($settings);
        if ((string)($settings['mail.driver'] ?? 'smtp') === 'disabled') {
            throw new \RuntimeException('Mail delivery is disabled.');
        }

        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException('PHPMailer is not available in vendor.');
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string)($settings['mail.smtp_host'] ?? '');
            $mail->Port = (int)($settings['mail.smtp_port'] ?? 587);
            $mail->CharSet = 'UTF-8';
            $mail->SMTPAuth = trim((string)($settings['mail.smtp_username'] ?? '')) !== '' || trim((string)($settings['mail.smtp_password'] ?? '')) !== '';
            $mail->Username = (string)($settings['mail.smtp_username'] ?? '');
            $mail->Password = (string)($settings['mail.smtp_password'] ?? '');
            $mail->Timeout = 10;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false
                ]
            ];

            $encryption = strtolower(trim((string)($settings['mail.smtp_encryption'] ?? '')));
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->setFrom((string)($settings['mail.from_email'] ?? ''), (string)($settings['mail.from_name'] ?? ''));
            $replyToEmail = trim((string)($settings['mail.reply_to_email'] ?? ''));
            if ($replyToEmail !== '') {
                $mail->addReplyTo($replyToEmail, (string)($settings['mail.reply_to_name'] ?? ''));
            }

            $mail->addAddress($recipient);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = trim((string)$textBody) !== '' ? (string)$textBody : $this->toTextBody($htmlBody);
            $mail->send();
        } catch (PhpMailerException $e) {
            throw new \RuntimeException('Mail send failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function sendTest(string $to): void
    {
        $this->send($to, '', 'test_email', [
            'to_email' => $to,
            'sent_at' => date('Y-m-d H:i:s'),
            'app_name' => app_display_name(),
        ]);
    }

    private function toTextBody(string $html): string
    {
        $normalized = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $normalized = preg_replace('/<\/p>/i', "\n\n", $normalized) ?? $normalized;
        return trim(strip_tags($normalized));
    }
}
