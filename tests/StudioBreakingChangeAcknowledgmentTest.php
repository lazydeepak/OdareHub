<?php
declare(strict_types=1);

use Apps\Studio\Services\GuiStudioService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../apps/Studio/Services/GuiStudioService.php';

final class StudioBreakingChangeAcknowledgmentTest extends TestCase
{
    public function testBreakingFieldRemovalRequiresRiskAcknowledgmentAndReason(): void
    {
        $diff = [
            'changes' => [
                [
                    'type' => 'field_removed',
                    'path' => 'module.fields.legacy_code',
                    'impact' => 'high',
                ],
            ],
        ];

        $blocked = GuiStudioService::buildStructuredRiskEscalation($diff, [
            'reason' => '',
            'risk_acknowledged' => false,
        ]);

        $this->assertTrue($blocked['requires_escalation']);
        $this->assertFalse($blocked['valid']);
        $this->assertContains('ops.gui_studio.approval.error.risk_ack_required', $blocked['errors']);
        $this->assertContains('ops.gui_studio.approval.error.reason_required', $blocked['errors']);

        $allowed = GuiStudioService::buildStructuredRiskEscalation($diff, [
            'reason' => 'Reviewed destructive Studio change.',
            'risk_acknowledged' => true,
        ]);

        $this->assertTrue($allowed['requires_escalation']);
        $this->assertTrue($allowed['valid']);
        $this->assertSame([], $allowed['errors']);
    }

    public function testHighImpactRequiresImpactConfirmationAndAcknowledgment(): void
    {
        $impactAnalysis = [
            [
                'change' => 'field_removed',
                'path' => 'module.fields.legacy_code',
                'severity' => 'high',
            ],
        ];

        $blocked = GuiStudioService::validateImpactAcknowledgment($impactAnalysis, [
            'impact_confirmation' => false,
            'impact_acknowledged' => false,
        ]);

        $this->assertTrue($blocked['requires_acknowledgment']);
        $this->assertTrue($blocked['has_high_impact']);
        $this->assertFalse($blocked['valid']);
        $this->assertContains('ops.gui_studio.impact.error.confirmation_required', $blocked['errors']);
        $this->assertContains('ops.gui_studio.impact.error.ack_required', $blocked['errors']);

        $allowed = GuiStudioService::validateImpactAcknowledgment($impactAnalysis, [
            'impact_confirmation' => true,
            'impact_acknowledged' => true,
        ]);

        $this->assertTrue($allowed['valid']);
        $this->assertSame([], $allowed['errors']);
    }

    public function testUpgradeModeRequiresLoadedBaselineAndCreateModeIgnoresStaleBaseline(): void
    {
        $currentBundle = [
            'app_manifest' => ['app' => ['app_key' => 'smoke']],
            'module_manifest' => ['fields' => [['key' => 'new_note']]],
            'view_definition' => [],
            'navigation_definition' => [],
        ];

        $blocked = GuiStudioService::validateStudioFlowMode([
            'studio_mode' => 'edit_existing',
            'se_previous_bundle' => '{}',
        ]);

        $this->assertFalse($blocked['valid']);
        $this->assertSame(['ops.gui_studio.error.upgrade_baseline_required'], $blocked['errors']);

        $previous = GuiStudioService::resolveStructuredPreviousBundle([
            'studio_mode' => 'create_new',
            'se_previous_bundle' => json_encode([
                'module_manifest' => ['fields' => [['key' => 'legacy_code']]],
            ], JSON_THROW_ON_ERROR),
        ], $currentBundle);

        $this->assertSame([], $previous['module_manifest']);
        $this->assertSame([], $previous['view_definition']);
    }
}
