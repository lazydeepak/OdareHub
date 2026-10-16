<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Shell/Composers/OperatorAccountPanelComposer.php';

use Apps\Shell\Composers\OperatorAccountPanelComposer;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passed++ : $failed++;
};

$empty = ['account' => [], 'policy' => [], 'flash' => '', 'error' => ''];

$service = static function (array $account, array $policy, ?Throwable $accountError = null, ?Throwable $policyError = null, ?array &$calls = null): object {
    return new class($account, $policy, $accountError, $policyError, $calls) {
        public function __construct(
            private array $account,
            private array $policy,
            private ?Throwable $accountError,
            private ?Throwable $policyError,
            private ?array &$calls
        ) {
        }

        public function accountForUser(int $userId): array
        {
            $this->calls['account'][] = $userId;
            if ($this->accountError !== null) {
                throw $this->accountError;
            }
            return $this->account;
        }

        public function passwordPolicy(): array
        {
            $this->calls['policy']++;
            if ($this->policyError !== null) {
                throw $this->policyError;
            }
            return $this->policy;
        }
    };
};

$factory = static function (object $instance): callable {
    return static function () use ($instance): object {
        return $instance;
    };
};

$_SESSION = ['unrelated' => 'keep', 'my_account_flash_ok' => 'Saved', 'my_account_flash_err' => ''];
$calls = ['account' => [], 'policy' => 0];
$validService = $service(['name' => 'Ada'], ['min_length' => 12], null, null, $calls);
$valid = (new OperatorAccountPanelComposer(
    static fn(): array => ['id' => 42],
    $factory($validService)
))->build();
$assert($valid === [
    'account' => ['name' => 'Ada'],
    'policy' => ['min_length' => 12],
    'flash' => 'Saved',
    'error' => '',
], 'valid identity preserves account, policy, and success flash payload');
$assert($calls === ['account' => [42], 'policy' => 1], 'valid identity calls existing service methods once with the authenticated ID');
$assert(!isset($_SESSION['my_account_flash_ok'], $_SESSION['my_account_flash_err']) && $_SESSION['unrelated'] === 'keep', 'successful retrieval clears only account flash keys');

$_SESSION = ['unrelated' => 'keep', 'my_account_flash_ok' => 'Pending'];
$serviceCalled = false;
$missing = (new OperatorAccountPanelComposer(
    static fn(): array => [],
    static function () use (&$serviceCalled): object {
        $serviceCalled = true;
        throw new RuntimeException('service must not be called');
    }
))->build();
$assert($missing === $empty, 'missing identity preserves the empty payload');
$assert($serviceCalled === false && $_SESSION['my_account_flash_ok'] === 'Pending', 'early invalid-identity return preserves flash and skips service access');

$_SESSION = ['unrelated' => 'keep', 'my_account_flash_err' => 'Previous error'];
$invalid = (new OperatorAccountPanelComposer(static fn(): array => ['id' => 0]))->build();
$assert($invalid === $empty && $_SESSION['my_account_flash_err'] === 'Previous error', 'zero identity preserves the empty payload and flash');

$_SESSION = ['unrelated' => 'keep', 'my_account_flash_ok' => 'Retry', 'my_account_flash_err' => ''];
$exceptionCalls = ['account' => [], 'policy' => 0];
$exceptionService = $service([], [], new RuntimeException('account failure'), null, $exceptionCalls);
$exception = (new OperatorAccountPanelComposer(
    static fn(): array => ['id' => 7],
    $factory($exceptionService)
))->build();
$assert($exception === [
    'account' => [],
    'policy' => [],
    'flash' => '',
    'error' => 'operator.common.data_unavailable',
], 'service exception preserves the unavailable fallback payload');
$assert($exceptionCalls === ['account' => [7], 'policy' => 0], 'account failure stops before password-policy retrieval');
$assert(!isset($_SESSION['my_account_flash_ok'], $_SESSION['my_account_flash_err']) && $_SESSION['unrelated'] === 'keep', 'exception path clears account flash keys but preserves unrelated session state');

$_SESSION = ['unrelated' => 'keep', 'my_account_flash_err' => 'Error once'];
    $secondCalls = ['account' => [], 'policy' => 0];
    $secondService = $service(['name' => 'Ada'], ['min_length' => 12], null, null, $secondCalls);
$first = (new OperatorAccountPanelComposer(static fn(): array => ['id' => 9], $factory($secondService)))->build();
$second = (new OperatorAccountPanelComposer(static fn(): array => ['id' => 9], $factory($secondService)))->build();
$assert($first['error'] === 'Error once' && $second['error'] === '', 'error flash is exposed once and consumed exactly once');

$surface = file_get_contents(APP_ROOT . '/apps/Shell/Composers/OperatorSurfaceComposer.php');
$assert(is_string($surface) && str_contains($surface, "'/u/' . rawurlencode(\$this->username) . '/account'"), 'existing account URL construction remains in the renderer');
$assert(is_string($surface) && str_contains($surface, 'renderAccountPanelMarkup'), 'existing account markup renderer remains in OperatorSurfaceComposer');

if ($failed > 0) {
    fwrite(STDERR, "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed; {$failed} failed\n");
    exit(1);
}

echo "RESULT: {$passed}/{$passed} assertions passed\n";
