<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioExperienceGovernanceService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../apps/Studio/Services/StudioExperienceGovernanceService.php';

final class StudioExperienceGovernanceServiceTest extends TestCase
{
    public function testWorkspaceProfileProposalAllowsAclAllowedCatalogCapability(): void
    {
        $result = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), [
            'artifact_type' => 'workspace_profile',
            'surface' => 'operator',
            'kind' => 'view',
            'items' => [$this->navItem('production', 'Production')],
        ]);

        $this->assertSame('studio.experience_governance.v1', $result['version']);
        $this->assertSame('READY', $result['apply_gate']['status']);
        $this->assertTrue($result['apply_gate']['can_apply']);
        $this->assertSame('ready', $result['target_artifact']['status']);
        $this->assertSame('workspace_profile:manufacturing_operator', $result['target_artifact']['artifact_key']);
        $this->assertSame('allowed', $result['changes'][0]['status']);
        $this->assertSame('acl_allowed', $result['changes'][0]['reason']);
        $this->assertSame('workspace_profiles.nav_sections', $result['changes'][0]['target_field']);
    }

    public function testProposalBlocksUnknownOwnerCapability(): void
    {
        $result = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), [
            'artifact_type' => 'workspace_profile',
            'surface' => 'operator',
            'kind' => 'view',
            'items' => ['ghost-zone'],
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $this->assertFalse($result['apply_gate']['can_apply']);
        $this->assertSame('blocked', $result['changes'][0]['status']);
        $this->assertSame('not_in_owner_capability_catalog', $result['changes'][0]['reason']);
    }

    public function testProposalBlocksAclDeniedCapability(): void
    {
        $result = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), [
            'artifact_type' => 'workspace_profile',
            'surface' => 'operator',
            'kind' => 'view',
            'items' => ['qc'],
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $this->assertSame('acl_denied', $result['changes'][0]['reason']);
        $this->assertSame('module_not_visible:qc', $result['changes'][0]['acl_reason']);
    }

    public function testUserOverrideCannotReincludeProfileHiddenCapability(): void
    {
        $result = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), [
            'artifact_type' => 'user_override',
            'surface' => 'operator',
            'kind' => 'view',
            'items' => ['materials'],
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $this->assertSame('blocked_by_profile', $result['changes'][0]['reason']);
    }

    public function testWorkspaceProfileProposalRequiresProfileArtifactKey(): void
    {
        $resolved = $this->resolvedExperience();
        unset($resolved['workspace_profile']);

        $result = StudioExperienceGovernanceService::analyzeProposal($resolved, [
            'artifact_type' => 'workspace_profile',
            'surface' => 'operator',
            'kind' => 'view',
            'items' => ['production'],
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $this->assertSame('blocked', $result['target_artifact']['status']);
        $this->assertSame('workspace_profile_key_required', $result['target_artifact']['reason']);
    }

    public function testUserOverrideProposalMustMatchResolvedTargetUser(): void
    {
        $result = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), [
            'artifact_type' => 'user_override',
            'target_user_id' => 99,
            'surface' => 'operator',
            'kind' => 'view',
            'items' => ['production'],
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $this->assertSame('blocked', $result['target_artifact']['status']);
        $this->assertSame('target_user_mismatch', $result['target_artifact']['reason']);
    }

    public function testStudioCannotGrantPermissionAsExperienceChange(): void
    {
        $result = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), [
            'artifact_type' => 'workspace_profile',
            'surface' => 'operator',
            'kind' => 'view',
            'items' => [
                ['token' => 'production', 'action' => 'grant_permission'],
            ],
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $this->assertSame('studio_cannot_grant_permission', $result['changes'][0]['reason']);
    }

    public function testProposalBlocksCapabilitiesWithoutSupportedPersistenceField(): void
    {
        $resolved = $this->resolvedExperience();
        $resolved['items'][] = [
            'key' => 'operator.panel.production',
            'surface' => 'operator',
            'kind' => 'panel',
            'owner_type' => 'module',
            'owner_key' => 'manufacturing',
            'allowed_by_acl' => true,
            'acl_reason' => 'acl_allowed',
            'included_by_workspace_profile' => true,
            'profile_reason' => 'profile_nav_includes',
        ];

        $result = StudioExperienceGovernanceService::analyzeProposal($resolved, [
            'artifact_type' => 'workspace_profile',
            'surface' => 'operator',
            'kind' => 'panel',
            'items' => ['production'],
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $this->assertSame('unsupported_experience_artifact_field', $result['changes'][0]['reason']);
    }

    public function testQuickActionProposalRequiresStructuredPayload(): void
    {
        $result = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), [
            'artifact_type' => 'workspace_profile',
            'surface' => 'operator',
            'kind' => 'quick_action',
            'items' => ['report_issue'],
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $this->assertSame('quick_action_payload_required', $result['changes'][0]['reason']);
    }

    public function testApplyPlanPreservesCapabilityOwnerAndDoesNotWritePermissions(): void
    {
        $proposal = $this->workspaceProfileNavProposal();
        $analysis = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), $proposal);

        $plan = StudioExperienceGovernanceService::buildApplyPlan($analysis, $proposal);

        $this->assertSame('READY', $plan['status']);
        $this->assertTrue($plan['can_apply']);
        $this->assertSame('studio', $plan['provenance']['created_by']);
        $this->assertFalse($plan['provenance']['permissions_granted']);
        $this->assertTrue($plan['provenance']['owner_handoff_required']);
        $this->assertSame('workspace_profiles.nav_sections', $plan['planned_writes'][0]['target']);
        $this->assertSame('module', $plan['planned_writes'][0]['capability_owner_type']);
        $this->assertSame('manufacturing', $plan['planned_writes'][0]['capability_owner_key']);
        $this->assertSame('workspace_profile:manufacturing_operator', $plan['planned_writes'][0]['target_artifact_key']);
        $this->assertSame('manufacturing_operator', $plan['planned_writes'][0]['target_profile_key']);
        $this->assertSame('workspace_profile:manufacturing_operator', $plan['provenance']['target_artifact_key']);
        $this->assertFalse($plan['planned_writes'][0]['permission_write']);
        $this->assertTrue($plan['planned_writes'][0]['runtime_owner_preserved']);
    }

    public function testApplyPlanRefusesBlockedAnalysis(): void
    {
        $proposal = [
            'artifact_type' => 'workspace_profile',
            'surface' => 'operator',
            'kind' => 'view',
            'items' => ['qc'],
        ];
        $analysis = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), $proposal);

        $plan = StudioExperienceGovernanceService::buildApplyPlan($analysis, $proposal);

        $this->assertSame('BLOCKED', $plan['status']);
        $this->assertFalse($plan['can_apply']);
        $this->assertSame([], $plan['planned_writes']);
        $this->assertSame('analysis_gate_blocked', $plan['reason']);
    }

    public function testEvaluateProposalReturnsAnalysisAndApplyPlanEnvelope(): void
    {
        $proposal = $this->workspaceProfileNavProposal();

        $result = StudioExperienceGovernanceService::evaluateProposal($this->resolvedExperience(), $proposal);

        $this->assertTrue($result['ok']);
        $this->assertSame('analyze_changes_apply_plan', $result['mode']);
        $this->assertSame('READY', $result['analysis']['apply_gate']['status']);
        $this->assertSame('READY', $result['apply_plan']['status']);
    }

    public function testUserOverrideApplyPlanTargetsCanonicalOverrideArtifactField(): void
    {
        $proposal = [
            'artifact_type' => 'user_override',
            'target_user_id' => 7,
            'surface' => 'operator',
            'kind' => 'view',
            'items' => ['production'],
        ];
        $analysis = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), $proposal);

        $plan = StudioExperienceGovernanceService::buildApplyPlan($analysis, $proposal);

        $this->assertSame('READY', $plan['status']);
        $this->assertSame('user_surface_overrides.operator_views', $plan['planned_writes'][0]['target']);
        $this->assertSame('user:7', $plan['planned_writes'][0]['target_artifact_key']);
        $this->assertSame(7, $plan['planned_writes'][0]['target_user_id']);
    }

    public function testApplyApprovedPlanPersistsReadyUserOverridePlan(): void
    {
        $proposal = [
            'artifact_type' => 'user_override',
            'target_user_id' => 7,
            'surface' => 'operator',
            'kind' => 'view',
            'items' => ['production'],
        ];
        $analysis = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), $proposal);
        $plan = StudioExperienceGovernanceService::buildApplyPlan($analysis, $proposal);
        $fingerprint = (string)$plan['provenance']['fingerprint'];

        $result = StudioExperienceGovernanceService::applyApprovedPlan(
            $plan,
            $fingerprint,
            static fn(array $write): array => [
                'ok' => true,
                'target' => $write['target'],
                'target_user_id' => $write['target_user_id'],
                'token' => $write['token'],
            ]
        );

        $this->assertSame('APPLIED', $result['status']);
        $this->assertTrue($result['ok']);
        $this->assertSame($fingerprint, $result['fingerprint']);
        $this->assertSame('user_surface_overrides.operator_views', $result['applied_writes'][0]['target']);
    }

    public function testApplyApprovedPlanRequiresMatchingFingerprint(): void
    {
        $proposal = [
            'artifact_type' => 'user_override',
            'target_user_id' => 7,
            'surface' => 'operator',
            'kind' => 'view',
            'items' => ['production'],
        ];
        $analysis = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), $proposal);
        $plan = StudioExperienceGovernanceService::buildApplyPlan($analysis, $proposal);

        $result = StudioExperienceGovernanceService::applyApprovedPlan(
            $plan,
            'wrong-fingerprint',
            static fn(array $write): array => ['ok' => true]
        );

        $this->assertSame('BLOCKED', $result['status']);
        $this->assertFalse($result['ok']);
        $this->assertSame('apply_fingerprint_mismatch', $result['reason']);
        $this->assertSame([], $result['applied_writes']);
    }

    public function testApplyApprovedPlanReturnsFailedResultWhenPersistenceThrows(): void
    {
        $proposal = [
            'artifact_type' => 'user_override',
            'target_user_id' => 7,
            'surface' => 'operator',
            'kind' => 'view',
            'items' => ['production'],
        ];
        $analysis = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), $proposal);
        $plan = StudioExperienceGovernanceService::buildApplyPlan($analysis, $proposal);

        $result = StudioExperienceGovernanceService::applyApprovedPlan(
            $plan,
            (string)$plan['provenance']['fingerprint'],
            static function (array $write): array {
                throw new RuntimeException('database unavailable');
            }
        );

        $this->assertSame('FAILED', $result['status']);
        $this->assertFalse($result['ok']);
        $this->assertSame('persist_failed', $result['reason']);
        $this->assertSame('persist_exception', $result['applied_writes'][0]['reason']);
    }

    public function testApplyApprovedPlanAllowsWorkspaceProfileTokenListWrites(): void
    {
        $proposal = [
            'artifact_type' => 'workspace_profile',
            'surface' => 'admin',
            'kind' => 'block',
            'items' => ['quality_kpis'],
        ];
        $analysis = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), $proposal);
        $plan = StudioExperienceGovernanceService::buildApplyPlan($analysis, $proposal);

        $result = StudioExperienceGovernanceService::applyApprovedPlan(
            $plan,
            (string)$plan['provenance']['fingerprint'],
            static fn(array $write): array => [
                'ok' => true,
                'target' => $write['target'],
                'target_profile_key' => $write['target_profile_key'],
                'token' => $write['token'],
            ]
        );

        $this->assertSame('APPLIED', $result['status']);
        $this->assertTrue($result['ok']);
        $this->assertSame('workspace_profiles.dashboard_blocks', $result['applied_writes'][0]['target']);
        $this->assertSame('manufacturing_operator', $result['applied_writes'][0]['target_profile_key']);
    }

    public function testApplyApprovedPlanAllowsWorkspaceProfileQuickActionWrites(): void
    {
        $proposal = [
            'artifact_type' => 'workspace_profile',
            'surface' => 'operator',
            'kind' => 'quick_action',
            'items' => [
                [
                    'token' => 'report_issue',
                    'label' => 'Report issue',
                    'url' => '/u/{user}/work-entry?action=report',
                    'icon' => 'alert-triangle',
                ],
            ],
        ];
        $analysis = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), $proposal);
        $plan = StudioExperienceGovernanceService::buildApplyPlan($analysis, $proposal);

        $result = StudioExperienceGovernanceService::applyApprovedPlan(
            $plan,
            (string)$plan['provenance']['fingerprint'],
            static fn(array $write): array => [
                'ok' => true,
                'target' => $write['target'],
                'quick_action' => $write['quick_action'],
            ]
        );

        $this->assertSame('READY', $analysis['apply_gate']['status']);
        $this->assertSame('workspace_profiles.quick_actions', $plan['planned_writes'][0]['target']);
        $this->assertSame('report_issue', $plan['planned_writes'][0]['quick_action']['key']);
        $this->assertSame('APPLIED', $result['status']);
        $this->assertSame('Report issue', $result['applied_writes'][0]['quick_action']['label']);
    }

    public function testWorkspaceProfileNavProposalRequiresStructuredPayload(): void
    {
        $result = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), [
            'artifact_type' => 'workspace_profile',
            'surface' => 'operator',
            'kind' => 'view',
            'items' => ['production'],
        ]);

        $this->assertSame('BLOCKED', $result['apply_gate']['status']);
        $this->assertSame('nav_item_payload_required', $result['changes'][0]['reason']);
    }

    public function testApplyApprovedPlanAllowsWorkspaceProfileNavSectionWrites(): void
    {
        $proposal = $this->workspaceProfileNavProposal();
        $analysis = StudioExperienceGovernanceService::analyzeProposal($this->resolvedExperience(), $proposal);
        $plan = StudioExperienceGovernanceService::buildApplyPlan($analysis, $proposal);

        $result = StudioExperienceGovernanceService::applyApprovedPlan(
            $plan,
            (string)$plan['provenance']['fingerprint'],
            static fn(array $write): array => [
                'ok' => true,
                'target' => $write['target'],
                'nav_item' => $write['nav_item'],
            ]
        );

        $this->assertSame('READY', $analysis['apply_gate']['status']);
        $this->assertSame('workspace_profiles.nav_sections', $plan['planned_writes'][0]['target']);
        $this->assertSame('production', $plan['planned_writes'][0]['nav_item']['key']);
        $this->assertSame('APPLIED', $result['status']);
        $this->assertSame('Production', $result['applied_writes'][0]['nav_item']['label']);
    }

    /**
     * @return array<string,mixed>
     */
    private function workspaceProfileNavProposal(): array
    {
        return [
            'artifact_type' => 'workspace_profile',
            'surface' => 'operator',
            'kind' => 'view',
            'items' => [$this->navItem('production', 'Production')],
        ];
    }

    /**
     * @return array<string,string>
     */
    private function navItem(string $token, string $label): array
    {
        return [
            'token' => $token,
            'label' => $label,
            'route' => '/u/{user}/' . $token,
            'section' => 'Manufacturing',
            'icon' => 'factory',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function resolvedExperience(): array
    {
        return [
            'version' => 'resolved_experience.diagnostic.v1',
            'surface' => 'operator',
            'target_user_id' => 7,
            'workspace_profile' => [
                'profile_key' => 'manufacturing_operator',
            ],
            'items' => [
                [
                    'key' => 'operator.view.production',
                    'surface' => 'operator',
                    'kind' => 'view',
                    'owner_type' => 'module',
                    'owner_key' => 'manufacturing',
                    'allowed_by_acl' => true,
                    'acl_reason' => 'acl_allowed',
                    'included_by_workspace_profile' => true,
                    'profile_reason' => 'profile_nav_includes',
                ],
                [
                    'key' => 'operator.view.qc',
                    'surface' => 'operator',
                    'kind' => 'view',
                    'owner_type' => 'module',
                    'owner_key' => 'manufacturing',
                    'allowed_by_acl' => false,
                    'acl_reason' => 'module_not_visible:qc',
                    'included_by_workspace_profile' => true,
                    'profile_reason' => 'profile_nav_includes',
                ],
                [
                    'key' => 'operator.view.materials',
                    'surface' => 'operator',
                    'kind' => 'view',
                    'owner_type' => 'module',
                    'owner_key' => 'manufacturing',
                    'allowed_by_acl' => true,
                    'acl_reason' => 'acl_allowed',
                    'included_by_workspace_profile' => false,
                    'profile_reason' => 'profile_nav_missing',
                ],
                [
                    'key' => 'admin.block.quality_kpis',
                    'surface' => 'admin',
                    'kind' => 'block',
                    'owner_type' => 'module',
                    'owner_key' => 'manufacturing',
                    'allowed_by_acl' => true,
                    'acl_reason' => 'acl_allowed',
                    'included_by_workspace_profile' => true,
                    'profile_reason' => 'profile_dashboard_includes',
                ],
                [
                    'key' => 'operator.quick_action.report_issue',
                    'surface' => 'operator',
                    'kind' => 'quick_action',
                    'owner_type' => 'module',
                    'owner_key' => 'manufacturing',
                    'allowed_by_acl' => true,
                    'acl_reason' => 'acl_allowed',
                    'included_by_workspace_profile' => true,
                    'profile_reason' => 'profile_action_includes',
                ],
            ],
        ];
    }
}
