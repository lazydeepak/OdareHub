<?php
declare(strict_types=1);

namespace Platform\Style\Proof;

use Platform\Style\Runtime\RuntimeStyleApplication;
use Platform\Style\ShellInsertion\ShellInsertion;

final class RenderedAdminProof
{
    public function __construct(
        private readonly RuntimeStyleApplication $runtimeStyleApplication,
        private readonly ShellInsertion $shellInsertion,
    ) {
    }

    public function render(): string
    {
        return '<div class="layout-main" data-proof="rendered-admin-proof"><!-- disabled: no active style emitted --></div>';
    }

    public function diagnostics(): array
    {
        $runtimeEnabled = $this->runtimeStyleApplication->isRuntimeApplicationEnabled();
        $insertionEnabled = $this->shellInsertion->isShellInsertionEnabled();

        return [
            'proof_status' => 'skeleton',
            'proof_enabled' => $this->isProofEnabled(),
            'runtime_application_enabled' => $runtimeEnabled,
            'shell_insertion_enabled' => $insertionEnabled,
            'surface' => 'admin',
            'target' => 'layout-main',
            'socket_key' => 'radius.scale',
            'css_property' => '--corner-radius',
            'allowed_values' => ['sharp' => '4px', 'soft' => '8px', 'round' => '16px'],
            'fallback_value' => '8px',
            'diagnostics' => [
                [
                    'code' => 'RAP-P001',
                    'severity' => 'PASS',
                    'message' => 'RenderedAdminProof proof object is available.',
                ],
                [
                    'code' => 'RAP-P002',
                    'severity' => 'PASS',
                    'message' => 'RuntimeStyleApplication and ShellInsertion dependencies are attached.',
                ],
                [
                    'code' => 'RAP-W001',
                    'severity' => 'WARN',
                    'message' => 'Proof is disabled and does not render active styles.',
                ],
                [
                    'code' => 'RAP-W002',
                    'severity' => 'WARN',
                    'message' => 'No active style emitted; proof output contains diagnostic marker only.',
                ],
                [
                    'code' => 'RAP-F001',
                    'severity' => 'FAIL',
                    'message' => 'Reserved: approved value did not reach consumption surface.',
                ],
                [
                    'code' => 'RAP-E001',
                    'severity' => 'ERROR',
                    'message' => 'Reserved: proof attempted to modify a production Shell template or layout.',
                ],
            ],
        ];
    }

    public function isProofEnabled(): bool
    {
        return false;
    }
}
