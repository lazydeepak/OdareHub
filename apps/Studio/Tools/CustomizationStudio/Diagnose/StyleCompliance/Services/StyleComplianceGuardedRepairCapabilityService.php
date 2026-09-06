<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services;

/**
 * Canonical guarded-repair capability contract for Style Compliance.
 *
 * The manifest is the server-side capability source for whether this Studio
 * tool may mutate owner artifacts. Readiness, scanner evidence, controller
 * execution, and UI rendering all consume this contract so the workspace cannot
 * drift into contradictory read-only/apply states.
 */
final class StyleComplianceGuardedRepairCapabilityService
{
    /**
     * @return array<string,mixed>
     */
    public static function contract(): array
    {
        $manifest = self::manifest();
        $capabilities = isset($manifest['capabilities']) && is_array($manifest['capabilities'])
            ? $manifest['capabilities'] : [];
        $evidenceCap = isset($capabilities['evidence']) && is_array($capabilities['evidence'])
            ? $capabilities['evidence'] : [];
        $guardedRepairCap = isset($capabilities['guarded_repair']) && is_array($capabilities['guarded_repair'])
            ? $capabilities['guarded_repair'] : [];
        $guardedRepairEnabled = !empty($guardedRepairCap['enabled']);
        $executorEnabled = !empty($manifest['can_modify'])
            && !empty($manifest['writes_to_owner_artifact'])
            && !empty($manifest['requires_approval'])
            && !empty($manifest['supports_snapshot'])
            && !empty($manifest['supports_rollback'])
            && $guardedRepairEnabled;

        return [
            'executor_enabled' => $executorEnabled,
            'can_modify' => !empty($manifest['can_modify']),
            'writes_to_owner_artifact' => !empty($manifest['writes_to_owner_artifact']),
            'requires_approval' => !empty($manifest['requires_approval']),
            'supports_snapshot' => !empty($manifest['supports_snapshot']),
            'supports_rollback' => !empty($manifest['supports_rollback']),
            'requires_fresh_successful_preflight' => true,
            'revalidates_all_preconditions_at_execution_time' => true,
            'target_scope' => 'single_verified_declaration',
            'applies_only_canonical_replacement' => true,
            'requires_recovery_evidence' => true,
            'requires_post_change_rescan' => true,
            'reports_resolved_state_honestly' => true,
            'owner_specific_rules_allowed' => false,
            'evidence' => [
                'enabled' => !empty($evidenceCap['enabled']),
            ],
            'guarded_repair' => [
                'enabled' => $guardedRepairEnabled,
                'environments' => isset($guardedRepairCap['environments']) && is_array($guardedRepairCap['environments'])
                    ? $guardedRepairCap['environments'] : [],
                'production_enabled' => !empty($guardedRepairCap['production_enabled']),
                'static_css_only' => !empty($guardedRepairCap['static_css_only']),
                'no_php_style_blocks' => !empty($guardedRepairCap['no_php_style_blocks']),
                'no_inline_html_styles' => !empty($guardedRepairCap['no_inline_html_styles']),
                'no_generated_minified_built_compiled_vendor_fixture_print_effect_sources' => !empty($guardedRepairCap['no_generated_minified_built_compiled_vendor_fixture_print_effect_sources']),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function manifest(): array
    {
        $path = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/manifest.php';
        $manifest = is_file($path) ? require $path : [];
        return is_array($manifest) ? $manifest : [];
    }
}
