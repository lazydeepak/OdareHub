<?php
declare(strict_types=1);

$appName = (string)($data['app_name'] ?? APP_NAME);
$displayName = trim((string)($data['display_name'] ?? ''));
$setupUrl = (string)($data['setup_url'] ?? '#');
$expiresInHours = (int)($data['expires_in_hours'] ?? 48);

$intro = $displayName !== ''
    ? 'Hello ' . e($displayName) . ', your account is ready.'
    : 'Your account is ready.';

return [
    'subject' => $appName . ' account setup',
    'html' => '<p>' . $intro . '</p>'
        . '<p>Use the secure setup link below to create your password and finish your first sign-in setup.</p>'
        . '<p><a href="' . e($setupUrl) . '">Open account setup</a></p>'
        . '<p>This link expires in ' . e((string)$expiresInHours) . ' hours.</p>',
    'text' => ($displayName !== '' ? ('Hello ' . $displayName . ', your account is ready.') : 'Your account is ready.') . "\n"
        . "Use the secure setup link below to create your password and finish your first sign-in setup.\n"
        . 'Open account setup: ' . $setupUrl . "\n"
        . 'This link expires in ' . $expiresInHours . " hours.\n",
];
