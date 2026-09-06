<?php
declare(strict_types=1);

$appName = (string)($data['app_name'] ?? APP_NAME);
$sentAt = (string)($data['sent_at'] ?? date('Y-m-d H:i:s'));

return [
    'subject' => $appName . ' mail test',
    'html' => '<p>This is a test email from ' . e($appName) . '.</p>'
        . '<p>Sent at: ' . e($sentAt) . '</p>'
        . '<p>Your SMTP settings are working.</p>',
    'text' => 'This is a test email from ' . $appName . ".\n"
        . 'Sent at: ' . $sentAt . "\n"
        . "Your SMTP settings are working.\n",
];
