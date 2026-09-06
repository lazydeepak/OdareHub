<?php
declare(strict_types=1);

$appName = (string)($data['app_name'] ?? APP_NAME);
$changedAt = (string)($data['changed_at'] ?? date('Y-m-d H:i:s'));

return [
    'subject' => $appName . ' password changed',
    'html' => '<p>Your ' . e($appName) . ' password was changed successfully.</p>'
        . '<p>Changed at: ' . e($changedAt) . '</p>'
        . '<p>If you did not make this change, contact your administrator immediately.</p>',
    'text' => 'Your ' . $appName . " password was changed successfully.\n"
        . 'Changed at: ' . $changedAt . "\n"
        . "If you did not make this change, contact your administrator immediately.\n",
];
