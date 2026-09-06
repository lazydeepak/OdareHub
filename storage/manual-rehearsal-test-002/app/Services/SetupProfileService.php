<?php
declare(strict_types=1);

namespace App\Services;

final class SetupProfileService
{
    /**
     * @return array<string,array<string,mixed>>
     */
    public function platformProfiles(): array
    {
        $profiles = [
            'core_only' => [
                'key' => 'core_only',
                'label' => 'Core only',
                'description' => 'Set up the platform foundation only. Choose this if you want to finish the basic install now and add business suites later.',
                'what_this_does' => 'Creates the platform, your first admin account, and the base navigation and security modules.',
                'what_happens_next' => 'After sign-in, you can choose SBAIO or Manufacturing from guided onboarding.',
                'suites' => [],
            ],
        ];

        $discoveredSuites = $this->discoverBusinessSuites();
        $allSuiteDefaults = [];
        foreach ($discoveredSuites as $suiteKey => $suite) {
            $defaultProfile = $this->defaultProfileForSuite($suiteKey);
            $allSuiteDefaults[$suiteKey] = $defaultProfile;

            $profiles['core_' . $suiteKey] = [
                'key' => 'core_' . $suiteKey,
                'label' => 'Core + ' . (string)($suite['label'] ?? ucfirst($suiteKey)),
                'description' => 'Set up the platform and the ' . (string)($suite['label'] ?? $suiteKey) . ' suite with a safe starter profile.',
                'what_this_does' => 'Builds the platform first, then installs and configures the selected business suite profile.',
                'what_happens_next' => 'You can verify setup health and continue from onboarding after sign-in.',
                'suites' => [
                    $suiteKey => $defaultProfile,
                ],
            ];
        }

        if ($allSuiteDefaults !== []) {
            $profiles['core_all_packages'] = [
                'key' => 'core_all_packages',
                'label' => 'Core + All Business Packages',
                'description' => 'Set up the platform and all discovered business suites using their default profiles.',
                'what_this_does' => 'Builds the platform, then installs and configures every discovered business suite in dependency-safe order.',
                'what_happens_next' => 'You can verify all selected suites and then continue from admin onboarding.',
                'suites' => $allSuiteDefaults,
            ];
        }

        return $profiles;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function suiteProfiles(): array
    {
        $profiles = [
            'sbaio_minimal' => [
                'key' => 'sbaio_minimal',
                'suite' => 'sbaio',
                'label' => 'SBAIO minimal',
                'description' => 'A simple starting point for people records and attendance.',
                'what_this_does' => 'Turns on staff and attendance so you can start using SBAIO quickly.',
                'modules' => ['Staff', 'Attendance'],
                'settings' => [
                    'sbaio.host_contributions' => 'enabled',
                    'sbaio.workspace_mode' => 'minimal',
                    'suite.sbaio.default_dashboard' => '/apps/sbaio',
                    'suite.sbaio.default_navigation' => 'dashboard',
                ],
            ],
            'sbaio_hr_timecard_payroll' => [
                'key' => 'sbaio_hr_timecard_payroll',
                'suite' => 'sbaio',
                'label' => 'SBAIO HR/timecard/payroll',
                'description' => 'A fuller SBAIO setup for HR, attendance, leave, timecards, and payroll.',
                'what_this_does' => 'Turns on the main employee, schedule, leave, timecard, and payroll features together.',
                'modules' => ['Staff', 'Attendance', 'Schedules', 'Leave', 'Timecards', 'Payroll'],
                'settings' => [
                    'sbaio.host_contributions' => 'enabled',
                    'sbaio.workspace_mode' => 'hr_payroll',
                    'suite.sbaio.default_dashboard' => '/apps/sbaio',
                    'suite.sbaio.default_navigation' => 'dashboard',
                ],
            ],
            'manufacturing_minimal' => [
                'key' => 'manufacturing_minimal',
                'suite' => 'manufacturing',
                'label' => 'Manufacturing minimal',
                'description' => 'A simple starting point for workflow, supply, coverage, products, and machines.',
                'what_this_does' => 'Turns on the foundation needed to begin manufacturing setup without the full execution stack.',
                'modules' => ['Workflow', 'Supply', 'Coverage', 'Products', 'Machines'],
                'settings' => [
                    'manufacturing.runtime_mode' => 'minimal',
                    'manufacturing.coverage_enabled' => '1',
                    'suite.manufacturing.default_dashboard' => '/apps/manufacturing',
                    'suite.manufacturing.default_navigation' => 'dashboard',
                ],
            ],
            'manufacturing_full_ipm_stack' => [
                'key' => 'manufacturing_full_ipm_stack',
                'suite' => 'manufacturing',
                'label' => 'Manufacturing full IPM stack',
                'description' => 'The full manufacturing setup for planning, execution, QC, dispatch, and stock ledger.',
                'what_this_does' => 'Turns on the end-to-end manufacturing workflow for planning through dispatch.',
                'modules' => [
                    'Workflow',
                    'Supply',
                    'Coverage',
                    'Products',
                    'Machines',
                    'PartMachineMap',
                    'DailyOrders',
                    'PreOrders',
                    'ProductionPlans',
                    'ProductionEntries',
                    'ProductionQueue',
                    'QCPlans',
                    'QCEntries',
                    'DispatchEntries',
                    'Ledger',
                ],
                'settings' => [
                    'manufacturing.runtime_mode' => 'full_ipm',
                    'manufacturing.coverage_enabled' => '1',
                    'suite.manufacturing.default_dashboard' => '/apps/manufacturing',
                    'suite.manufacturing.default_navigation' => 'dashboard',
                ],
            ],
        ];

        foreach ($this->discoverBusinessSuites() as $suiteKey => $suite) {
            $defaultKey = $this->defaultProfileForSuite($suiteKey);
            if (isset($profiles[$defaultKey])) {
                continue;
            }

            $suiteModules = array_values(array_unique(array_map('strval', (array)($suite['modules'] ?? []))));
            $dashboardRoute = '/apps/' . $suiteKey;
            $profiles[$defaultKey] = [
                'key' => $defaultKey,
                'suite' => $suiteKey,
                'label' => (string)($suite['label'] ?? ucfirst($suiteKey)) . ' default',
                'description' => 'Recommended default setup for ' . (string)($suite['label'] ?? $suiteKey) . '.',
                'what_this_does' => 'Enables the suite runtime and configures available modules in dependency-safe order.',
                'modules' => $suiteModules,
                'settings' => [
                    'suite.' . $suiteKey . '.default_dashboard' => $dashboardRoute,
                    'suite.' . $suiteKey . '.default_navigation' => 'dashboard',
                ],
            ];
        }

        return $profiles;
    }

    /**
     * @return array<string,mixed>
     */
    public function recommendedDefaults(): array
    {
        return [
            'first_admin' => 'The first account becomes the main platform administrator and is protected with 2FA.',
            'language' => 'English',
            'currency' => 'JPY',
            'theme' => 'system',
            'timezone' => 'Asia/Tokyo',
            'suite_navigation' => [
                'sbaio' => '/apps/sbaio',
                'manufacturing' => '/apps/manufacturing',
            ],
            'host_contributions' => [
                'sbaio' => true,
                'manufacturing' => true,
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function platformProfile(string $key): ?array
    {
        return $this->platformProfiles()[$key] ?? null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function suiteProfile(string $key): ?array
    {
        return $this->suiteProfiles()[$key] ?? null;
    }

    public function defaultProfileForSuite(string $suiteKey): string
    {
        $suiteKey = strtolower(trim($suiteKey));

        return match ($suiteKey) {
            'sbaio' => 'sbaio_minimal',
            'manufacturing' => 'manufacturing_minimal',
            default => $suiteKey !== '' ? $suiteKey . '_default' : '',
        };
    }

    /**
     * @return array<int,string>
     */
    public function modulesForSuiteProfile(string $profileKey): array
    {
        $profile = $this->suiteProfile($profileKey);
        if (!is_array($profile)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', (array)($profile['modules'] ?? []))));
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function discoverBusinessSuites(): array
    {
        $suites = [];
        foreach (glob(APP_ROOT . '/apps/*/manifest.json') ?: [] as $manifestPath) {
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = AppManifestService::loadFromFile($manifestPath);
            $suiteKey = strtolower(trim((string)($manifest['id'] ?? '')));
            if ($suiteKey === '') {
                continue;
            }

            $appType = strtolower(trim((string)($manifest['type'] ?? 'business')));
            if ($appType !== 'business') {
                continue;
            }

            $suites[$suiteKey] = [
                'suite_key' => $suiteKey,
                'label' => trim((string)($manifest['name'] ?? $suiteKey)),
                'modules' => array_values(array_unique(array_map('strval', (array)($manifest['legacy_bridge_plugins'] ?? [])))),
            ];
        }

        ksort($suites);
        return $suites;
    }
}
