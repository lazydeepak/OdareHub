<?php
declare(strict_types=1);

namespace App\Services;

final class MailTemplateService
{
    public function render(string $template, array $data = []): array
    {
        $path = APP_ROOT . '/app/MailTemplates/' . trim($template) . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException('Unknown mail template: ' . $template);
        }

        $payload = $data;
        $rendered = (static function (string $__path, array $__data): array {
            $data = $__data;
            $result = require $__path;
            return is_array($result) ? $result : [];
        })($path, $payload);

        return [
            'subject' => (string)($rendered['subject'] ?? ''),
            'html' => (string)($rendered['html'] ?? ''),
            'text' => (string)($rendered['text'] ?? ''),
        ];
    }
}
