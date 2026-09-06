<?php
declare(strict_types=1);

$appName = (string)($data['app_name'] ?? APP_NAME);
$setupUrl = (string)($data['setup_url'] ?? '#');

return [
    'subject' => $appName . ' account setup',
    'html' => '<p>Your account is ready for setup.</p>'
        . '<p><a href="' . e($setupUrl) . '">Open account setup</a></p>'
        . '<p>This template is reserved for invite and first-login setup flows.</p>',
    'text' => 'Your account is ready for setup.' . "\n"
        . 'Open account setup: ' . $setupUrl . "\n"
        . "This template is reserved for invite and first-login setup flows.\n",
];
