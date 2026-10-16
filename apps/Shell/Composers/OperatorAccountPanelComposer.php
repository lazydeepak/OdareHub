<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

use App\Core\Auth;
use App\Services\MyAccountService;

/**
 * Builds the operator account-panel data contract without rendering markup.
 *
 * The optional closures exist only as an isolated, non-database verification
 * seam. Production defaults remain the existing Auth and MyAccountService
 * authorities.
 */
final class OperatorAccountPanelComposer
{
    /** @var null|callable():mixed */
    private $userResolver;

    /** @var null|callable():object */
    private $serviceFactory;

    /**
     * @param null|callable():mixed $userResolver
     * @param null|callable():object $serviceFactory
     */
    public function __construct(?callable $userResolver = null, ?callable $serviceFactory = null)
    {
        $this->userResolver = $userResolver;
        $this->serviceFactory = $serviceFactory;
    }

    /**
     * @return array{account:array<string,mixed>,policy:array<string,mixed>,flash:string,error:string}
     */
    public function build(): array
    {
        $empty = [
            'account' => [],
            'policy' => [],
            'flash' => '',
            'error' => '',
        ];

        $user = $this->userResolver !== null
            ? ($this->userResolver)()
            : Auth::user();
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return $empty;
        }

        try {
            $service = $this->serviceFactory !== null
                ? ($this->serviceFactory)()
                : new MyAccountService();
            return [
                'account' => $service->accountForUser($userId),
                'policy' => $service->passwordPolicy(),
                'flash' => (string)($_SESSION['my_account_flash_ok'] ?? ''),
                'error' => (string)($_SESSION['my_account_flash_err'] ?? ''),
            ];
        } catch (\Throwable $e) {
            return [
                'account' => [],
                'policy' => [],
                'flash' => '',
                'error' => 'operator.common.data_unavailable',
            ];
        } finally {
            unset($_SESSION['my_account_flash_ok'], $_SESSION['my_account_flash_err']);
        }
    }
}
