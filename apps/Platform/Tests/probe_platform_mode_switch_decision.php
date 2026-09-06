<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/Services/PlatformModeService.php';
require_once $root . '/apps/Platform/Services/PlatformModeSwitchDecisionService.php';

use Apps\Platform\Services\PlatformModeSwitchDecisionService;

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$valid = ['csrf' => 'token', 'platform_mode' => 'development', 'return_to' => '/admin/system-tools/platform-mode'];

$expired = PlatformModeSwitchDecisionService::decide(array_replace($valid, ['csrf' => 'expired']), 'token', false);
$assert($expired['result'] === 'csrf_invalid', 'expired CSRF is rejected');
$assert($expired['write_permitted'] === false, 'expired CSRF cannot mutate mode');

$missingSession = PlatformModeSwitchDecisionService::decide($valid, '', false);
$assert($missingSession['result'] === 'csrf_invalid', 'missing session CSRF is rejected');
$assert($missingSession['write_permitted'] === false, 'missing session CSRF cannot mutate mode');

$invalidMode = PlatformModeSwitchDecisionService::decide(array_replace($valid, ['platform_mode' => 'staging']), 'token', false);
$assert($invalidMode['result'] === 'invalid_mode', 'unknown mode is rejected');
$assert($invalidMode['write_permitted'] === false, 'unknown mode cannot mutate state');

$locked = PlatformModeSwitchDecisionService::decide($valid, 'token', true);
$assert($locked['result'] === 'locked', 'deployment lock is reported');
$assert($locked['write_permitted'] === false, 'deployment lock prevents mutation');

$confined = PlatformModeSwitchDecisionService::decide(array_replace($valid, ['return_to' => 'https://example.invalid/escape']), 'token', false);
$assert($confined['return_to'] === '/admin/system-tools/platform-mode', 'untrusted return path falls back to canonical Platform Mode workspace');

$ready = PlatformModeSwitchDecisionService::decide($valid, 'token', false);
$assert($ready['result'] === 'ready', 'valid unlocked request is ready');
$assert($ready['write_permitted'] === true, 'only valid unlocked request permits write');
$assert($ready['mode'] === 'development', 'validated mode is returned to the mutation owner');
$assert($ready['return_to'] === '/admin/system-tools/platform-mode', 'allowlisted specialist return path is preserved');

echo "Platform mode switch decision probe: {$checks}/{$checks} assertions passed\n";
