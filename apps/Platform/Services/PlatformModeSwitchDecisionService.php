<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

final class PlatformModeSwitchDecisionService
{
    private const DEFAULT_RETURN_PATH = '/admin/system-tools/platform-mode';
    private const ALLOWED_RETURN_PATHS = [
        self::DEFAULT_RETURN_PATH,
    ];

    /** @param array<string,mixed> $input @return array{result:string,return_to:string,mode:string,write_permitted:bool} */
    public static function decide(array $input, string $sessionCsrf, bool $locked): array
    {
        $returnTo = trim((string)($input['return_to'] ?? self::DEFAULT_RETURN_PATH));
        if (!in_array($returnTo, self::ALLOWED_RETURN_PATHS, true)) {
            $returnTo = self::DEFAULT_RETURN_PATH;
        }

        $mode = strtolower(trim((string)($input['platform_mode'] ?? '')));
        $submittedCsrf = (string)($input['csrf'] ?? '');

        if ($sessionCsrf === '' || !hash_equals($sessionCsrf, $submittedCsrf)) {
            return self::result('csrf_invalid', $returnTo, $mode, false);
        }
        if (!in_array($mode, \App\Services\PlatformModeService::VALID_MODES, true)) {
            return self::result('invalid_mode', $returnTo, $mode, false);
        }
        if ($locked) {
            return self::result('locked', $returnTo, $mode, false);
        }

        return self::result('ready', $returnTo, $mode, true);
    }

    /** @return array{result:string,return_to:string,mode:string,write_permitted:bool} */
    private static function result(string $result, string $returnTo, string $mode, bool $writePermitted): array
    {
        return [
            'result' => $result,
            'return_to' => $returnTo,
            'mode' => $mode,
            'write_permitted' => $writePermitted,
        ];
    }
}
