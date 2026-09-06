<?php
declare(strict_types=1);

namespace Platform\Style\Consumption;

use Platform\Style\ResolvedStyleConsumer;

final class StyleConsumptionSurface
{
    public function __construct(
        private readonly ResolvedStyleConsumer $consumer,
    ) {
    }

    public function radiusScale(): ?string
    {
        return null;
    }

    public function diagnostics(): array
    {
        $consumerDiagnostics = $this->consumer->diagnostics();

        $surfaceDiagnostics = [
            [
                'code' => 'PSC-P001',
                'severity' => 'PASS',
                'message' => 'Surface available and initialized.',
            ],
            [
                'code' => 'PSC-P002',
                'severity' => 'PASS',
                'message' => 'Consumer attached.',
            ],
            [
                'code' => 'PSC-W001',
                'severity' => 'WARN',
                'message' => 'Runtime consumption disabled.',
            ],
        ];

        return [
            'surface_status' => 'skeleton',
            'runtime_consumption_enabled' => $this->isRuntimeConsumptionEnabled(),
            'consumer_diagnostics' => $consumerDiagnostics,
            'diagnostics' => $surfaceDiagnostics,
            'boundary_limits' => [
                'No registry reads' => true,
                'No adapter imports' => true,
                'No reader contract imports' => true,
                'No Shell imports' => true,
                'No Studio imports' => true,
                'No filesystem writes' => true,
                'No DB connections' => true,
                'No HTTP calls' => true,
                'No shell commands' => true,
                'No route registration' => true,
                'No theme mutation' => true,
                'No CSS generation' => true,
            ],
        ];
    }

    public function isRuntimeConsumptionEnabled(): bool
    {
        return false;
    }
}
