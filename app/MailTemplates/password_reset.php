<?php
declare(strict_types=1);

$appName = (string)($data['app_name'] ?? APP_NAME);
$resetUrl = (string)($data['reset_url'] ?? '#');
$displayName = trim((string)($data['display_name'] ?? ''));
$expiresInMinutes = (int)($data['expires_in_minutes'] ?? 60);
$greeting = $displayName !== '' ? 'Hello ' . $displayName . ',' : 'Hello,';

return [
    'subject' => $appName . ' password reset',
    'html' => '<p>' . e($greeting) . '</p>'
        . '<p>We received a request to reset the password for your ' . e($appName) . ' account.</p>'
        . '<p><a href="' . e($resetUrl) . '" style="display:inline-block;padding:10px 16px;background:#1d4ed8;color:#fff;text-decoration:none;border-radius:8px">Reset password</a></p>'
        . '<p>This link expires in ' . e((string)$expiresInMinutes) . ' minutes and can only be used once.</p>'
        . '<p>If you did not request this, you can safely ignore this email.</p>',
    'text' => $greeting . "\n\n"
        . 'We received a request to reset the password for your ' . $appName . " account.\n"
        . "Reset password: {$resetUrl}\n\n"
        . 'This link expires in ' . $expiresInMinutes . " minutes and can only be used once.\n"
        . "If you did not request this, you can safely ignore this email.\n",
];
