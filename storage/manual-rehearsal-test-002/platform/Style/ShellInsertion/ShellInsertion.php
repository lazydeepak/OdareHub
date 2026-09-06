<?php
declare(strict_types=1);

namespace Platform\Style\ShellInsertion;

use Platform\Style\Runtime\RuntimeStyleApplication;

final class ShellInsertion
{
    private const SURFACE = 'admin';
    private const TARGET = 'layout-main';
    private const SOCKET_KEY = 'radius.scale';
    private const CSS_PROPERTY = '--corner-radius';
    private const SHARP_VALUE = '4px';
    private const SOFT_VALUE = '8px';
    private const ROUND_VALUE = '16px';
    private const FALLBACK_VALUE = '8px';
    private const IDENTIFIERS = ['sharp', 'soft', 'round'];

    public function __construct(
        private readonly RuntimeStyleApplication $runtimeStyleApplication,
    ) {
    }

    public function adminLayoutMainStyle(): array
    {
        return [];
    }

    public function diagnostics(): array
    {
        return [
            'insertion_status' => 'skeleton',
            'shell_insertion_enabled' => $this->isShellInsertionEnabled(),
            'runtime_application_enabled' => $this->runtimeStyleApplication->isRuntimeApplicationEnabled(),
            'surface' => self::SURFACE,
            'target' => self::TARGET,
            'socket_key' => self::SOCKET_KEY,
            'css_property' => self::CSS_PROPERTY,
            'allowed_identifiers' => self::IDENTIFIERS,
            'allowed_values' => [
                self::SHARP_VALUE,
                self::SOFT_VALUE,
                self::ROUND_VALUE,
            ],
            'fallback_value' => self::FALLBACK_VALUE,
            'diagnostics' => [
                [
                    'code' => 'RSI-P001',
                    'severity' => 'PASS',
                    'message' => 'Admin layout-main insertion boundary is defined.',
                ],
                [
                    'code' => 'RSI-P002',
                    'severity' => 'PASS',
                    'message' => 'Fixed corner radius insertion scope is available.',
                ],
                [
                    'code' => 'RSI-P003',
                    'severity' => 'PASS',
                    'message' => 'RuntimeStyleApplication is the sole dependency.',
                ],
                [
                    'code' => 'RSI-W001',
                    'severity' => 'WARN',
                    'message' => 'Runtime application remains disabled.',
                ],
                [
                    'code' => 'RSI-W002',
                    'severity' => 'WARN',
                    'message' => 'Shell insertion remains disabled and returns no styles.',
                ],
            ],
        ];
    }

    public function isShellInsertionEnabled(): bool
    {
        return false;
    }
}
