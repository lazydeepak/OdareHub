<?php
declare(strict_types=1);

namespace Platform\Updates;

/** Test-scoped explicit enrollment adapter; never a production trust flow. */
final class DisposableTrustProvisioner
{
    public const INDEPENDENT_EXPECTED_FINGERPRINT = 'independent-operator-input';

    private InstallationOwnedTrustStore $store;

    public function __construct(InstallationOwnedTrustStore $store)
    {
        $this->store = $store;
    }

    /** @param array<string,mixed> $record @return array<string,mixed> */
    public function enroll(array $record, string $expectedFingerprint, bool $operatorApproved): array
    {
        return $this->store->provisionDisposable(
            $record,
            $expectedFingerprint,
            $operatorApproved,
            self::INDEPENDENT_EXPECTED_FINGERPRINT
        );
    }
}
