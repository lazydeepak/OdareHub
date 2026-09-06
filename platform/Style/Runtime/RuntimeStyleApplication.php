<?php
declare(strict_types=1);

namespace Platform\Style\Runtime;

use Platform\Style\Consumption\StyleConsumptionSurface;

final class RuntimeStyleApplication
{
    private const SOCKET_KEY = 'radius.scale';
    private const CSS_PROPERTY = '--corner-radius';
    private const FALLBACK_VALUE = '8px';

    public function __construct(
        private readonly StyleConsumptionSurface $surface,
    ) {
    }

    public function cornerRadius(): string
    {
        return match ($this->surface->radiusScale()) {
            'sharp' => '4px',
            'soft' => '8px',
            'round' => '16px',
            default => self::FALLBACK_VALUE,
        };
    }

    public function diagnostics(): array
    {
        $identifier = $this->surface->radiusScale();
        $knownIdentifier = in_array($identifier, ['sharp', 'soft', 'round'], true);

        return [
            'application_status' => 'skeleton',
            'runtime_application_enabled' => $this->isRuntimeApplicationEnabled(),
            'socket_key' => self::SOCKET_KEY,
            'css_property' => self::CSS_PROPERTY,
            'identifier' => $identifier,
            'resolved_value' => $this->cornerRadius(),
            'diagnostics' => [
                [
                    'code' => 'RSA-P001',
                    'severity' => 'PASS',
                    'message' => 'Runtime style application skeleton is initialized.',
                ],
                [
                    'code' => 'RSA-P002',
                    'severity' => 'PASS',
                    'message' => 'Fixed radius.scale mapping and fallback are available.',
                ],
                [
                    'code' => 'RSA-W001',
                    'severity' => 'WARN',
                    'message' => 'Runtime application is disabled.',
                ],
                [
                    'code' => 'RSA-W002',
                    'severity' => $identifier === null || $knownIdentifier ? 'PASS' : 'WARN',
                    'message' => $identifier === null || $knownIdentifier
                        ? 'Identifier is absent or supported.'
                        : 'Unsupported identifier uses the fixed fallback.',
                ],
            ],
        ];
    }

    public function isRuntimeApplicationEnabled(): bool
    {
        return false;
    }
}
