<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use Apps\Platform\Contracts\UserAccessPolicyContract;
use Apps\Platform\Contracts\UserContextContract;
use Apps\Platform\Contracts\UserScopeContract;

require_once __DIR__ . '/../Contracts/UserAccessPolicyContract.php';
require_once __DIR__ . '/../Contracts/UserContextContract.php';
require_once __DIR__ . '/../Contracts/UserScopeContract.php';
require_once __DIR__ . '/PlatformUserAssignmentAdapter.php';

final class UserAssignmentContext
{
    private static ?PlatformUserAssignmentAdapter $instance = null;

    private static function instance(): PlatformUserAssignmentAdapter
    {
        if (self::$instance === null) {
            self::$instance = new PlatformUserAssignmentAdapter();
        }

        return self::$instance;
    }

    public static function context(): UserContextContract
    {
        return self::instance();
    }

    public static function accessPolicy(): UserAccessPolicyContract
    {
        return self::instance();
    }

    public static function scope(): UserScopeContract
    {
        return self::instance();
    }
}
