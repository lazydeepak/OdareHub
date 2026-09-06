<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use Plugins\Organization\Services\OrganizationService;

final class OrganizationStatusService
{
    /**
     * @return array<string,string|null>
     */
    public function getSetupStatus(): array
    {
        if (!$this->databaseReady() || !$this->organizationTablesReady()) {
            return [
                'status' => 'not_configured',
                'name' => null,
                'country' => null,
                'currency' => null,
            ];
        }

        try {
            $company = OrganizationService::primaryCompany();
        } catch (\Throwable $e) {
            return [
                'status' => 'not_configured',
                'name' => null,
                'country' => null,
                'currency' => null,
            ];
        }

        if (!is_array($company) || $company === []) {
            return [
                'status' => 'not_configured',
                'name' => null,
                'country' => null,
                'currency' => null,
            ];
        }

        $name = $this->nullableTrim((string)($company['company_name'] ?? ''));
        $country = $this->nullableTrim((string)($company['country'] ?? ''));
        $currency = $this->nullableTrim((string)($company['base_currency'] ?? ''));

        $status = ($name !== null && $country !== null)
            ? 'configured'
            : 'needs_setup';

        return [
            'status' => $status,
            'name' => $name,
            'country' => $country,
            'currency' => $currency,
        ];
    }

    private function databaseReady(): bool
    {
        try {
            $preflight = (new CoreSetupService())->preflight();
            return (bool)($preflight['database']['ok'] ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function organizationTablesReady(): bool
    {
        try {
            return DB::fetchOne("SHOW TABLES LIKE 'org_companies'") !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function nullableTrim(string $value): ?string
    {
        $value = trim($value);
        return $value !== '' ? $value : null;
    }
}
