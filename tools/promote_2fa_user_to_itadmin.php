<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/app/Core/helpers.php';
$autoload = APP_ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once APP_ROOT . '/app/Core/DB.php';

use App\Core\DB;

$email = strtolower(trim((string)($argv[1] ?? '')));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/promote_2fa_user_to_itadmin.php <email>  # promotes to platform_admin\n");
    exit(1);
}

try {
    $user = DB::fetchOne(
        'SELECT id, email, role, twofa_enabled, twofa_secret FROM users WHERE email=? LIMIT 1',
        [$email]
    );

    if (!$user) {
        fwrite(STDERR, "User not found: {$email}\n");
        exit(2);
    }

    $twofaEnabled = (int)($user['twofa_enabled'] ?? 0) === 1;
    $twofaSecret = trim((string)($user['twofa_secret'] ?? ''));
    if (!$twofaEnabled || $twofaSecret === '') {
        fwrite(STDERR, "Refused: user is not fully 2FA-enabled (twofa_enabled=1 and twofa_secret required).\n");
        exit(3);
    }

    $hasRoleTier = DB::fetchOne("SHOW COLUMNS FROM users LIKE 'role_tier'") !== null;
    $hasAccountType = DB::fetchOne("SHOW COLUMNS FROM users LIKE 'authority_role'") !== null;
    $targetRole = 'PlatformOperations';

    if ($hasRoleTier && $hasAccountType) {
        DB::query('UPDATE users SET role=?, role_tier=?, authority_role=? WHERE id=? LIMIT 1', [$targetRole, 'admin', 'platform_admin', (int)$user['id']]);
    } elseif ($hasRoleTier) {
        DB::query('UPDATE users SET role=?, role_tier=? WHERE id=? LIMIT 1', [$targetRole, 'admin', (int)$user['id']]);
    } else {
        DB::query('UPDATE users SET role=? WHERE id=? LIMIT 1', [$targetRole, (int)$user['id']]);
    }

    $updated = DB::fetchOne('SELECT email, role, twofa_enabled FROM users WHERE id=? LIMIT 1', [(int)$user['id']]);
    $outRole = (string)($updated['role'] ?? '');
    $out2fa = (int)($updated['twofa_enabled'] ?? 0);

    fwrite(STDOUT, "Updated {$email}: role={$outRole}, twofa_enabled={$out2fa}\n");
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(10);
}
