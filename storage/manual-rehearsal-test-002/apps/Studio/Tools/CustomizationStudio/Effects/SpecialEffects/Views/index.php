<?php
$model = isset($specialEffectsModel) && is_array($specialEffectsModel) ? $specialEffectsModel : [];
$runtime = isset($model['runtime_contract']) && is_array($model['runtime_contract']) ? $model['runtime_contract'] : [];
$status = isset($model['runtime_status']) && is_array($model['runtime_status']) ? $model['runtime_status'] : [];
$profiles = isset($model['profiles']) && is_array($model['profiles']) ? $model['profiles'] : [];
$readiness = isset($model['readiness']) && is_array($model['readiness']) ? $model['readiness'] : [];
$handoffs = isset($model['handoffs']) && is_array($model['handoffs']) ? $model['handoffs'] : [];
$handoffGroups = isset($handoffs['groups']) && is_array($handoffs['groups']) ? $handoffs['groups'] : [];
$handoffReconciliation = isset($model['handoff_reconciliation']) && is_array($model['handoff_reconciliation']) ? $model['handoff_reconciliation'] : [];
$handoffParity = isset($model['handoff_parity']) && is_array($model['handoff_parity']) ? $model['handoff_parity'] : [];
$scanContext = isset($model['scan_context']) && is_array($model['scan_context']) ? $model['scan_context'] : [];
$mappingRules = isset($model['mapping_rules']) && is_array($model['mapping_rules']) ? $model['mapping_rules'] : [];
$previewState = isset($model['preview_state']) && is_array($model['preview_state']) ? $model['preview_state'] : [];
$futureEffectControls = isset($model['future_effect_controls']) && is_array($model['future_effect_controls']) ? $model['future_effect_controls'] : [];
$futureSettingsContract = isset($futureEffectControls['settings_contract']) && is_array($futureEffectControls['settings_contract']) ? $futureEffectControls['settings_contract'] : [];
$futureResolvedState = isset($futureEffectControls['resolved_effect_state']) && is_array($futureEffectControls['resolved_effect_state']) ? $futureEffectControls['resolved_effect_state'] : [];
$futureAuthorityRows = isset($futureEffectControls['authority_contract']) && is_array($futureEffectControls['authority_contract']) ? $futureEffectControls['authority_contract'] : [];
$futureControlPlans = isset($futureEffectControls['control_plans']) && is_array($futureEffectControls['control_plans']) ? $futureEffectControls['control_plans'] : [];
$futureAuditEvidence = isset($futureEffectControls['audit_and_rollback_design']) && is_array($futureEffectControls['audit_and_rollback_design']) ? $futureEffectControls['audit_and_rollback_design'] : [];
$futurePreviewReference = isset($futureEffectControls['preview_simulation_reference']) && is_array($futureEffectControls['preview_simulation_reference']) ? $futureEffectControls['preview_simulation_reference'] : [];
$appearanceAudit = isset($model['appearance_integration_audit']) && is_array($model['appearance_integration_audit']) ? $model['appearance_integration_audit'] : [];
$appearanceMap = isset($appearanceAudit['appearance_state_map']) && is_array($appearanceAudit['appearance_state_map']) ? $appearanceAudit['appearance_state_map'] : [];
$appearanceReadinessRecords = isset($appearanceAudit['readiness_records']) && is_array($appearanceAudit['readiness_records']) ? $appearanceAudit['readiness_records'] : [];
$appearanceMainBlockers = isset($appearanceAudit['main_blockers']) && is_array($appearanceAudit['main_blockers']) ? $appearanceAudit['main_blockers'] : [];
$themeDoctorDependency = isset($appearanceAudit['theme_doctor_dependency']) && is_array($appearanceAudit['theme_doctor_dependency']) ? $appearanceAudit['theme_doctor_dependency'] : [];
$runtimeReadiness = isset($model['runtime_readiness']) && is_array($model['runtime_readiness']) ? $model['runtime_readiness'] : [];
$futureControls = isset($model['future_controls']) && is_array($model['future_controls']) ? $model['future_controls'] : [];
$layers = isset($model['layers']) && is_array($model['layers']) ? $model['layers'] : [];
$safety = isset($model['safety_contract']) && is_array($model['safety_contract']) ? $model['safety_contract'] : [];
$shellState = isset($model['shell_appearance_state']) && is_array($model['shell_appearance_state']) ? $model['shell_appearance_state'] : [];
$browserNormMap = isset($shellState['browser_normalization_map']) && is_array($shellState['browser_normalization_map']) ? $shellState['browser_normalization_map'] : [];
$authComparison = isset($model['auth_server_comparison']) && is_array($model['auth_server_comparison']) ? $model['auth_server_comparison'] : [];
$authEvidenceLedger = isset($model['auth_parity_evidence_ledger']) && is_array($model['auth_parity_evidence_ledger']) ? $model['auth_parity_evidence_ledger'] : [];
$authComparisonInventory = isset($model['auth_comparison_inventory']) && is_array($model['auth_comparison_inventory']) ? $model['auth_comparison_inventory'] : [];

$se = static function (string $key): string {
    $lang = function_exists('current_lang') ? current_lang() : 'en';
    $dict = [
        'en' => [
            'title' => 'Special Effects',
            'subtitle' => 'Read-only control-plane workspace for optional visual effects, runtime readiness, and future profile governance.',
            'read_only' => 'No CSS writes, compiled asset edits, setting changes, source migrations, apply routes, snapshots, or rollback actions exist in this phase.',
            'runtime_status' => 'Effects Runtime Status',
            'palette_mode' => 'Palette mode',
            'effect_profile' => 'Effect profile',
            'configured_profile' => 'Configured profile',
            'effects_fallback' => 'Effects-disabled fallback',
            'motion_mode' => 'Motion mode',
            'handoff_count' => 'Handoff count',
            'blocked_degraded' => 'Blocked / degraded deps',
            'active_runtime_readiness' => 'Active Runtime Readiness',
            'preview_effect_profile' => 'Preview Effect Profile',
            'preview_only_note' => 'Preview only. These controls do not save or change the active application theme.',
            'active_combined_mode' => 'Active combined mode',
            'active_palette' => 'Active palette',
            'active_effect_profile' => 'Active effect profile',
            'preview_palette' => 'Preview palette',
            'preview_profile' => 'Preview effect profile',
            'preview_effects_state' => 'Preview effects state',
            'preview_motion' => 'Preview motion mode',
            'preview_status' => 'Preview status',
            'fallback_status' => 'Fallback status',
            'accessibility_status' => 'Accessibility status',
            'render_preview' => 'Render preview',
            'reset_preview' => 'Reset preview to active mode',
            'effects_on' => 'Effects on',
            'effects_off' => 'Effects off',
            'preview_canvas' => 'Isolated preview canvas',
            'theme_asset_health' => 'Runtime theme asset health',
            'selector_status' => 'Required selector status',
            'token_status' => 'Required token status',
            'reduced_motion_fallback' => 'Reduced-motion fallback',
            'capability_status' => 'Capability evidence status',
            'fallback_profile' => 'Fallback profile',
            'full_preview_state' => 'Full preview state',
            'selector_token_evidence' => 'Selector/token evidence',
            'future_effect_controls_title' => 'Future Effects Controls',
            'future_controls_note' => 'Future persistent controls require audit and rollback design. Persistence is not enabled in this phase.',
            'resolution_hierarchy' => 'Control resolution hierarchy',
            'current_active_resolved_state' => 'Current active resolved state',
            'organization_policy_model' => 'Organization policy model',
            'user_override_model' => 'User override model',
            'accessibility_runtime_constraints' => 'Accessibility and runtime constraints',
            'future_persistence_requirements' => 'Future persistence requirements',
            'future_control_matrix' => 'Future control matrix',
            'setting_name' => 'Setting name',
            'current_resolved_state' => 'Current resolved state',
            'future_configurable_scope' => 'Future configurable scope',
            'required_authority' => 'Required authority',
            'user_override_allowed' => 'User override allowed',
            'policy_status' => 'Policy status',
            'current_phase' => 'Current phase',
            'design_only' => 'Design only - persistence not enabled',
            'full_resolution_trace' => 'Full resolution trace',
            'future_control_plan_records' => 'Future control-plan records',
            'required_audit_evidence' => 'Required audit evidence',
            'capability_accessibility_constraints' => 'Capability and accessibility constraints',
            'requested_value' => 'Requested value',
            'projected_resolved_value' => 'Projected resolved value',
            'persistent_controls_readiness' => 'Persistent Controls Readiness',
            'appearance_origin' => 'Current appearance-state origin',
            'active_combined_mode' => 'Active combined mode',
            'canonical_ownership_status' => 'Canonical ownership status',
            'recommended_strategy' => 'Recommended future persistence strategy',
            'readiness_by_scope' => 'Readiness by setting scope',
            'main_blockers' => 'Main blockers',
            'theme_doctor_dependency' => 'Theme Doctor dependency',
            'integration_phase' => 'Read-only integration audit - persistence not enabled',
            'future_scope' => 'Future scope',
            'existing_source_of_truth' => 'Existing source of truth',
            'write_path_known' => 'Write path known',
            'authority_known' => 'Authority known',
            'audit_rollback_known' => 'Audit/rollback known',
            'runtime_application_known' => 'Runtime application known',
            'readiness_status' => 'Readiness',
            'blockers' => 'Blockers',
            'full_appearance_state_map' => 'Full appearance-state map',
            'rendering_path' => 'Rendering path',
            'existing_write_path_evidence' => 'Existing write-path evidence',
            'compatibility_strategy_evaluation' => 'Compatibility strategy evaluation',
            'conflict_prevention_model' => 'Conflict-prevention model',
            'future_resolver_integration_records' => 'Future resolver integration records',
            'theme_doctor_dependency_evidence' => 'Theme Doctor dependency evidence',
            'combined_mode' => 'Combined mode',
            'derived_palette' => 'Derived palette',
            'derived_effect_profile' => 'Derived effect profile',
            'effects_enabled' => 'Effects enabled',
            'resolver_source' => 'Resolver source',
            'profile_readiness' => 'Profile readiness',
            'current_scan_context' => 'Current Scan Context',
            'scan_scope' => 'Scan scope',
            'selected_owner' => 'Selected owner',
            'source_root' => 'Source root',
            'scanner_contract_version' => 'Scanner contract version',
            'operational_filtering_status' => 'Operational filtering status',
            'source_revision_fingerprint' => 'Source revision fingerprint',
            'parity' => 'Style Compliance ↔ Special Effects Parity',
            'matching_ids' => 'Matching handoff IDs',
            'missing_in_effects' => 'Missing in Special Effects',
            'unexpected_in_effects' => 'Unexpected in Special Effects',
            'excluded_by_adapter' => 'Excluded by adapter',
            'unmapped_category_records' => 'Unmapped category records',
            'parity_status' => 'Parity status',
            'full_parity_comparison' => 'Full parity comparison',
            'difference_reason' => 'Difference reason',
            'style_compliance_status' => 'Style Compliance status',
            'special_effects_status' => 'Special Effects status',
            'category_totals' => 'Category totals',
            'mapping_evidence' => 'Detailed category mapping evidence',
            'mapping_registry' => 'Mapping-rule registry',
            'mapping_rule' => 'Mapping rule',
            'mapping_status' => 'Mapping status',
            'why_applies' => 'Why this category applies',
            'why_not' => 'Why other categories do not apply',
            'full_provenance' => 'Full source provenance',
            'reconciliation' => 'Style Compliance Handoff Reconciliation',
            'handoffs_available' => 'Style Compliance handoffs available',
            'handoffs_displayed' => 'Special Effects handoffs displayed',
            'handoffs_excluded' => 'Excluded handoffs',
            'handoffs_unmapped' => 'Unmapped handoffs',
            'missing_profile_mapping' => 'Blocked by missing profile mapping',
            'outside_selected_scope' => 'Outside current selected scope',
            'category_mapping_status' => 'Category mapping status',
            'source_context_used' => 'Source context used',
            'future_controls' => 'Future controls',
            'not_enabled' => 'Not enabled in this phase',
            'runtime_layers' => 'Runtime Layers',
            'registry' => 'Effects Registry',
            'required_selectors' => 'Required selectors',
            'required_tokens' => 'Required tokens',
            'required_capabilities' => 'Required capabilities',
            'fallback' => 'Fallback',
            'accessibility' => 'Accessibility constraints',
            'contrast_contract' => 'Contrast contract',
            'readiness' => 'Readiness',
            'dependencies' => 'Dependencies',
            'missing_selectors' => 'Missing selectors',
            'missing_tokens' => 'Missing tokens',
            'runtime_evidence' => 'Runtime evidence',
            'handoff_queue' => 'Style Compliance Handoff Queue',
            'handoff_note' => 'Rows are consumed from Style Compliance future_tool_handoff proposals only. Deterministic fixes and manual semantic decisions are not included.',
            'source_owner' => 'Source owner',
            'file' => 'File',
            'line' => 'Line',
            'handoff_id' => 'Handoff ID',
            'selector' => 'Selector',
            'property' => 'Property',
            'current_value' => 'Current value',
            'category' => 'Effect category',
            'confidence' => 'Confidence',
            'reason' => 'Reason',
            'fallback_requirement' => 'Fallback requirement',
            'profile_relevance' => 'Profile relevance',
            'category_backdrop_blur' => 'Backdrop blur',
            'category_motion_transition' => 'Motion / transition',
            'category_decorative_transform' => 'Decorative transform',
            'category_filter_mask_blend' => 'Filter / mask / blend',
            'category_decorative_glow_shadow' => 'Decorative glow / shadow',
            'category_decorative_gradient' => 'Decorative gradient',
            'category_glass_translucency' => 'Glass translucency',
            'category_decorative_opacity' => 'Decorative opacity',
            'category_review_required' => 'Review required',
            'control' => 'Control',
            'scope' => 'Scope',
            'authority' => 'Authority',
            'persistence_owner' => 'Persistence owner',
            'enabled_now' => 'Enabled now',
            'resolver_details' => 'Compatibility Resolver Details',
            'safety_contract' => 'Safety Contract',
            'empty' => 'None',
            'yes' => 'Yes',
            'no' => 'No',
            'shell_appearance_consistency_title' => 'Appearance State Consistency',
            'shell_appearance_consistency_desc' => 'Shadow resolver state computed by Shell\'s canonical AppearanceStateResolver, compared with the current Special Effects appearance audit.',
            'shell_resolved_mode' => 'Shell-resolved combined mode',
            'shell_resolved_palette' => 'Shell-resolved palette',
            'shell_resolved_profile' => 'Shell-resolved effect profile',
            'shell_effects_enabled' => 'Shell-resolved effects enabled',
            'shell_parity_status' => 'Rendering parity status',
            'shell_parity_aligned' => 'Aligned',
            'shell_parity_mismatch' => 'Mismatch (diagnostic only)',
            'shell_source_trace_title' => 'Appearance source trace',
            'shell_browser_override_title' => 'Browser override contract',
            'shell_future_readiness_title' => 'Future structured-state readiness',
            'shell_future_precedence_title' => 'Future precedence model',
            'shell_full_resolver_output' => 'Full Shell resolver output',
            'shell_drift_detected' => 'Drift detected',
            'shell_drift_resolved' => 'Resolved',
            'shell_drift_not_observable' => 'Not observable server-side',
            'shell_drift_unknown' => 'Unknown',
            'shell_observer_title' => 'Browser Appearance Observer',
            'shell_observer_desc' => 'Read-only observation of the effective browser appearance state. No writes, no localStorage changes, no network requests.',
            'shell_observer_not_started' => 'Observer not started',
            'shell_observer_status' => 'Observer status',
            'shell_observed_mode' => 'Observed combined mode',
            'shell_observed_palette' => 'Observed palette',
            'shell_observed_profile' => 'Observed profile',
            'shell_observed_html_attrs' => 'HTML attributes observed',
            'shell_drift_consistent' => 'Consistent',
            'shell_drift_inconsistent' => 'Inconsistent with resolved intent',
            'shell_observer_pending' => 'Waiting for browser...',
            'shell_no_observer_data' => 'No browser observation available',
            'drift' => 'Drift',
            'auth_comparison_title' => 'Auth Server-Render Comparison',
            'auth_comparison_desc' => 'Parallel comparison of the legacy Auth server-default mode against Shell\'s canonical AppearanceStateResolver server intent. No Auth rendering, localStorage, session, database, or browser state is read or mutated.',
            'auth_comparison_legacy_mode' => 'Auth server-default mode',
            'auth_comparison_shell_mode' => 'Shell server intent mode',
            'auth_comparison_comparison_id' => 'Comparison ID',
            'auth_comparison_evidence_basis' => 'Evidence basis',
            'auth_comparison_status' => 'Comparison status',
            'auth_comparison_aligned' => 'Aligned',
            'auth_comparison_mismatch' => 'Mismatch',
            'auth_comparison_unknown' => 'Unknown',
            'auth_comparison_reason' => 'Reason',
            'auth_comparison_mismatch_field' => 'Mismatch field',
            'auth_comparison_legacy_value' => 'Legacy value',
            'auth_comparison_shell_value' => 'Shell value',
            'auth_comparison_legacy_trace' => 'Legacy source trace',
            'auth_comparison_shell_trace' => 'Shell source trace',
            'auth_comparison_browser_override' => 'Browser override included',
            'auth_comparison_browser_override_excluded' => 'Browser override excluded',
            'auth_comparison_browser_state' => 'Browser effective state claimed',
            'auth_comparison_diagnostic_only' => 'Diagnostic only',
            'auth_comparison_cutover_status' => 'Cutover status',
            'auth_comparison_not_ready' => 'Not ready',
            'auth_comparison_blockers' => 'Cutover blockers',
            'auth_comparison_reader_id' => 'Reader ID',
            'auth_comparison_full_contract' => 'Full comparison contract',
            'auth_comparison_mismatch_details' => 'Mismatch fields',
            'auth_comparison_inventory' => 'Appearance Reader Inventory',
            'auth_comparison_inventory_desc' => 'Folded read-only inventory with Auth comparison status attached. Reader classifications are unchanged.',
            'auth_comparison_no_mismatch' => 'No mismatch fields',
            'auth_comparison_source_label' => 'Auth server-default path comparison. Browser runtime override is excluded.',
            'auth_ledger_title' => 'Auth Parity Evidence Ledger',
            'auth_ledger_desc' => 'This ledger compares Auth server-source behavior with Shell server intent. It does not verify browser-effective appearance and does not change Auth rendering.',
            'auth_ledger_known_cases' => 'Known cases',
            'auth_ledger_aligned' => 'Aligned',
            'auth_ledger_mismatch' => 'Mismatch',
            'auth_ledger_unknown' => 'Unknown',
            'auth_ledger_observed_responses' => 'Observed Auth responses',
            'auth_ledger_evidence_basis' => 'Evidence basis',
            'auth_ledger_all_cases' => 'All ledger case rows',
            'auth_ledger_raw_json' => 'Raw ledger JSON',
            'auth_ledger_case_id' => 'Case ID',
            'auth_ledger_auth_mode' => 'Auth source-contract mode',
            'auth_ledger_shell_mode' => 'Shell intent mode',
            'auth_ledger_status' => 'Parity status',
            'auth_ledger_fallback' => 'Fallback evidence',
            'auth_ledger_source_status' => 'Source-evidence status',
            'auth_ledger_reason' => 'Reason',
            'auth_ledger_observed_flag' => 'Observed render',
        ],
        'ja' => [
            'title' => 'Special Effects',
            'subtitle' => '任意の視覚効果、ランタイム準備状況、将来のプロファイル統制を確認する読み取り専用ワークスペースです。',
            'read_only' => 'このフェーズでは CSS 書き込み、コンパイル済みアセット編集、設定変更、ソース移行、適用ルート、スナップショット、ロールバックはありません。',
            'runtime_status' => 'エフェクト ランタイム状態',
            'palette_mode' => 'パレットモード',
            'effect_profile' => 'エフェクトプロファイル',
            'configured_profile' => '設定済みプロファイル',
            'effects_fallback' => 'エフェクト無効時フォールバック',
            'motion_mode' => 'モーションモード',
            'handoff_count' => '引き継ぎ件数',
            'blocked_degraded' => 'ブロック / 低下依存',
            'active_runtime_readiness' => 'アクティブ ランタイム準備状況',
            'preview_effect_profile' => 'エフェクトプロファイルのプレビュー',
            'preview_only_note' => 'プレビューのみです。この操作はアクティブなアプリケーションテーマを保存または変更しません。',
            'active_combined_mode' => 'アクティブ結合モード',
            'active_palette' => 'アクティブパレット',
            'active_effect_profile' => 'アクティブエフェクトプロファイル',
            'preview_palette' => 'プレビューパレット',
            'preview_profile' => 'プレビューエフェクトプロファイル',
            'preview_effects_state' => 'プレビューエフェクト状態',
            'preview_motion' => 'プレビューモーションモード',
            'preview_status' => 'プレビュー状態',
            'fallback_status' => 'フォールバック状態',
            'accessibility_status' => 'アクセシビリティ状態',
            'render_preview' => 'プレビュー表示',
            'reset_preview' => 'アクティブモードにリセット',
            'effects_on' => 'エフェクトあり',
            'effects_off' => 'エフェクトなし',
            'preview_canvas' => '分離プレビューキャンバス',
            'theme_asset_health' => 'ランタイムテーマアセット状態',
            'selector_status' => '必須セレクター状態',
            'token_status' => '必須トークン状態',
            'reduced_motion_fallback' => 'Reduced-motion fallback',
            'capability_status' => '機能証跡状態',
            'fallback_profile' => 'フォールバックプロファイル',
            'full_preview_state' => '完全なプレビュー状態',
            'selector_token_evidence' => 'セレクター/トークン証跡',
            'future_effect_controls_title' => '将来のエフェクト制御',
            'future_controls_note' => '将来の永続制御には監査とロールバック設計が必要です。このフェーズでは永続化は有効ではありません。',
            'resolution_hierarchy' => '制御解決階層',
            'current_active_resolved_state' => '現在のアクティブ解決状態',
            'organization_policy_model' => '組織ポリシーモデル',
            'user_override_model' => 'ユーザー上書きモデル',
            'accessibility_runtime_constraints' => 'アクセシビリティとランタイム制約',
            'future_persistence_requirements' => '将来の永続化要件',
            'future_control_matrix' => '将来制御マトリクス',
            'setting_name' => '設定名',
            'current_resolved_state' => '現在の解決状態',
            'future_configurable_scope' => '将来の設定スコープ',
            'required_authority' => '必要権限',
            'user_override_allowed' => 'ユーザー上書き可',
            'policy_status' => 'ポリシー状態',
            'current_phase' => '現在フェーズ',
            'design_only' => '設計のみ - 永続化は未有効',
            'full_resolution_trace' => '完全な解決トレース',
            'future_control_plan_records' => '将来制御プランレコード',
            'required_audit_evidence' => '必要監査証跡',
            'capability_accessibility_constraints' => '機能とアクセシビリティ制約',
            'requested_value' => '要求値',
            'projected_resolved_value' => '投影解決値',
            'persistent_controls_readiness' => '永続制御の準備状況',
            'appearance_origin' => '現在の外観状態の由来',
            'active_combined_mode' => 'アクティブ結合モード',
            'canonical_ownership_status' => '正規所有状態',
            'recommended_strategy' => '推奨される将来の永続化戦略',
            'readiness_by_scope' => '設定スコープ別準備状況',
            'main_blockers' => '主なブロッカー',
            'theme_doctor_dependency' => 'Theme Doctor 依存',
            'integration_phase' => '読み取り専用統合監査 - 永続化は未有効',
            'future_scope' => '将来スコープ',
            'existing_source_of_truth' => '既存の正規ソース',
            'write_path_known' => '書き込み経路既知',
            'authority_known' => '権限既知',
            'audit_rollback_known' => '監査/ロールバック既知',
            'runtime_application_known' => 'ランタイム適用既知',
            'readiness_status' => '準備状況',
            'blockers' => 'ブロッカー',
            'full_appearance_state_map' => '完全な外観状態マップ',
            'rendering_path' => 'レンダリング経路',
            'existing_write_path_evidence' => '既存書き込み経路証跡',
            'compatibility_strategy_evaluation' => '互換性戦略評価',
            'conflict_prevention_model' => '競合防止モデル',
            'future_resolver_integration_records' => '将来リゾルバー統合レコード',
            'theme_doctor_dependency_evidence' => 'Theme Doctor 依存証跡',
            'combined_mode' => '結合モード',
            'derived_palette' => '派生パレット',
            'derived_effect_profile' => '派生エフェクトプロファイル',
            'effects_enabled' => 'エフェクト有効',
            'resolver_source' => 'リゾルバーソース',
            'profile_readiness' => 'プロファイル準備状況',
            'current_scan_context' => '現在のスキャンコンテキスト',
            'scan_scope' => 'スキャンスコープ',
            'selected_owner' => '選択所有者',
            'source_root' => 'ソースルート',
            'scanner_contract_version' => 'スキャナー契約バージョン',
            'operational_filtering_status' => '運用フィルター状態',
            'source_revision_fingerprint' => 'ソース改訂フィンガープリント',
            'parity' => 'Style Compliance ↔ Special Effects 照合',
            'matching_ids' => '一致した引き継ぎ ID',
            'missing_in_effects' => 'Special Effects に不足',
            'unexpected_in_effects' => 'Special Effects の想定外',
            'excluded_by_adapter' => 'アダプターで除外',
            'unmapped_category_records' => '未マップ分類レコード',
            'parity_status' => '照合状態',
            'full_parity_comparison' => '完全な照合比較',
            'difference_reason' => '差分理由',
            'style_compliance_status' => 'Style Compliance 状態',
            'special_effects_status' => 'Special Effects 状態',
            'category_totals' => '分類合計',
            'mapping_evidence' => '詳細な分類マッピング証跡',
            'mapping_registry' => 'マッピングルール レジストリ',
            'mapping_rule' => 'マッピングルール',
            'mapping_status' => 'マッピング状態',
            'why_applies' => 'この分類が適用される理由',
            'why_not' => '他分類を適用しない理由',
            'full_provenance' => '完全なソース来歴',
            'reconciliation' => 'Style Compliance 引き継ぎ照合',
            'handoffs_available' => 'Style Compliance 引き継ぎ利用可能',
            'handoffs_displayed' => 'Special Effects 表示引き継ぎ',
            'handoffs_excluded' => '除外引き継ぎ',
            'handoffs_unmapped' => '未マップ引き継ぎ',
            'missing_profile_mapping' => 'プロファイルマップ不足でブロック',
            'outside_selected_scope' => '現在の選択スコープ外',
            'category_mapping_status' => '分類マップ状態',
            'source_context_used' => '使用ソースコンテキスト',
            'future_controls' => '将来のコントロール',
            'not_enabled' => 'このフェーズでは未有効',
            'runtime_layers' => 'ランタイムレイヤー',
            'registry' => 'エフェクト レジストリ',
            'required_selectors' => '必須セレクター',
            'required_tokens' => '必須トークン',
            'required_capabilities' => '必須機能',
            'fallback' => 'フォールバック',
            'accessibility' => 'アクセシビリティ制約',
            'contrast_contract' => 'コントラスト契約',
            'readiness' => '準備状況',
            'dependencies' => '依存関係',
            'missing_selectors' => '不足セレクター',
            'missing_tokens' => '不足トークン',
            'runtime_evidence' => 'ランタイム証跡',
            'handoff_queue' => 'Style Compliance 引き継ぎキュー',
            'handoff_note' => 'Style Compliance の future_tool_handoff 提案のみを表示します。決定的修復と手動意味判断は含めません。',
            'source_owner' => 'ソース所有者',
            'file' => 'ファイル',
            'line' => '行',
            'handoff_id' => '引き継ぎ ID',
            'selector' => 'セレクター',
            'property' => 'プロパティ',
            'current_value' => '現在値',
            'category' => 'エフェクト分類',
            'confidence' => '信頼度',
            'reason' => '理由',
            'fallback_requirement' => 'フォールバック要件',
            'profile_relevance' => '関連プロファイル',
            'category_backdrop_blur' => 'Backdrop blur',
            'category_motion_transition' => 'モーション / トランジション',
            'category_decorative_transform' => '装飾 transform',
            'category_filter_mask_blend' => 'フィルター / マスク / ブレンド',
            'category_decorative_glow_shadow' => '装飾グロー / シャドウ',
            'category_decorative_gradient' => '装飾グラデーション',
            'category_glass_translucency' => 'Glass translucency',
            'category_decorative_opacity' => '装飾不透明度',
            'category_review_required' => 'レビュー必須',
            'control' => 'コントロール',
            'scope' => 'スコープ',
            'authority' => '権限',
            'persistence_owner' => '永続化所有者',
            'enabled_now' => '現在有効',
            'resolver_details' => '互換性リゾルバー詳細',
            'safety_contract' => '安全契約',
            'empty' => 'なし',
            'yes' => 'はい',
            'no' => 'いいえ',
            'shell_appearance_consistency_title' => '外観状態の一貫性',
            'shell_appearance_consistency_desc' => 'Shellの正規 AppearanceStateResolver で計算されたシャドウ解決状態と現在の Special Effects 外観監査の比較。',
            'shell_resolved_mode' => 'Shell解決済み結合モード',
            'shell_resolved_palette' => 'Shell解決済みパレット',
            'shell_resolved_profile' => 'Shell解決済みエフェクトプロファイル',
            'shell_effects_enabled' => 'Shell解決済みエフェクト有効',
            'shell_parity_status' => 'レンダリング一致状態',
            'shell_parity_aligned' => '一致',
            'shell_parity_mismatch' => '不一致（診断のみ）',
            'shell_source_trace_title' => '外観ソーストレース',
            'shell_browser_override_title' => 'ブラウザ上書き契約',
            'shell_future_readiness_title' => '将来の構造化状態準備状況',
            'shell_future_precedence_title' => '将来の優先順位モデル',
            'shell_full_resolver_output' => 'Shellリゾルバー出力（完全）',
            'shell_drift_detected' => 'ドリフト検出',
            'shell_drift_resolved' => '解決済み',
            'shell_drift_not_observable' => 'サーバー側で観測不可',
            'shell_drift_unknown' => '不明',
            'shell_observer_title' => 'ブラウザ外観オブザーバー',
            'shell_observer_desc' => '有効なブラウザ外観状態の読み取り専用観測。書き込み、localStorage変更、ネットワーク要求はありません。',
            'shell_observer_not_started' => 'オブザーバー未開始',
            'shell_observer_status' => 'オブザーバー状態',
            'shell_observed_mode' => '観測済み結合モード',
            'shell_observed_palette' => '観測済みパレット',
            'shell_observed_profile' => '観測済みプロファイル',
            'shell_observed_html_attrs' => '観測済みHTML属性',
            'shell_drift_consistent' => '一致',
            'shell_drift_inconsistent' => '解決意図と不一致',
            'shell_observer_pending' => 'ブラウザ待機中...',
            'shell_no_observer_data' => 'ブラウザ観測データなし',
            'drift' => 'ドリフト',
            'auth_comparison_title' => '認証サーバーレンダリング比較',
            'auth_comparison_desc' => '従来の認証サーバー既定モードと Shell の正規 AppearanceStateResolver サーバー意図の並行比較。認証レンダリング、localStorage、セッション、データベース、ブラウザ状態を読み取りまたは変更しません。',
            'auth_comparison_legacy_mode' => '認証サーバー既定モード',
            'auth_comparison_shell_mode' => 'Shell サーバー意図モード',
            'auth_comparison_comparison_id' => '比較 ID',
            'auth_comparison_evidence_basis' => '証拠基準',
            'auth_comparison_status' => '比較状態',
            'auth_comparison_aligned' => '一致',
            'auth_comparison_mismatch' => '不一致',
            'auth_comparison_unknown' => '不明',
            'auth_comparison_reason' => '理由',
            'auth_comparison_mismatch_field' => '不一致フィールド',
            'auth_comparison_legacy_value' => '従来値',
            'auth_comparison_shell_value' => 'Shell 値',
            'auth_comparison_legacy_trace' => '従来ソーストレース',
            'auth_comparison_shell_trace' => 'Shell ソーストレース',
            'auth_comparison_browser_override' => 'ブラウザ上書き含む',
            'auth_comparison_browser_override_excluded' => 'ブラウザ上書き除外',
            'auth_comparison_browser_state' => 'ブラウザ有効状態主張',
            'auth_comparison_diagnostic_only' => '診断のみ',
            'auth_comparison_cutover_status' => '移行状態',
            'auth_comparison_not_ready' => '未準備',
            'auth_comparison_blockers' => '移行ブロッカー',
            'auth_comparison_reader_id' => 'リーダー ID',
            'auth_comparison_full_contract' => '完全な比較契約',
            'auth_comparison_mismatch_details' => '不一致フィールド',
            'auth_comparison_inventory' => '外観リーダーインベントリ',
            'auth_comparison_inventory_desc' => 'Auth 比較状態だけを付加した折りたたみ式の読み取り専用インベントリ。リーダー分類は変更されません。',
            'auth_comparison_no_mismatch' => '不一致フィールドなし',
            'auth_comparison_source_label' => 'Auth サーバー既定パス比較。ブラウザランタイム上書きは除外されています。',
            'auth_ledger_title' => 'Auth パリティ証拠台帳',
            'auth_ledger_desc' => 'この台帳は Auth のサーバーソース動作と Shell のサーバー意図を比較します。ブラウザ有効外観を検証せず、Auth レンダリングを変更しません。',
            'auth_ledger_known_cases' => '既知ケース',
            'auth_ledger_aligned' => '一致',
            'auth_ledger_mismatch' => '不一致',
            'auth_ledger_unknown' => '不明',
            'auth_ledger_observed_responses' => '観測済み Auth 応答',
            'auth_ledger_evidence_basis' => '証拠基準',
            'auth_ledger_all_cases' => 'すべての台帳ケース行',
            'auth_ledger_raw_json' => '台帳 JSON',
            'auth_ledger_case_id' => 'ケース ID',
            'auth_ledger_auth_mode' => 'Auth ソース契約モード',
            'auth_ledger_shell_mode' => 'Shell 意図モード',
            'auth_ledger_status' => 'パリティ状態',
            'auth_ledger_fallback' => 'フォールバック証拠',
            'auth_ledger_source_status' => 'ソース証拠状態',
            'auth_ledger_reason' => '理由',
            'auth_ledger_observed_flag' => 'レンダー観測',
        ],
        'ne' => [
            'title' => 'Special Effects',
            'subtitle' => 'वैकल्पिक दृश्य effects, runtime readiness, र भविष्य profile governance का लागि पढ्न-मात्र control-plane workspace।',
            'read_only' => 'यस चरणमा CSS writes, compiled asset edits, setting changes, source migrations, apply routes, snapshots, वा rollback actions छैनन्।',
            'runtime_status' => 'Effects Runtime Status',
            'palette_mode' => 'Palette mode',
            'effect_profile' => 'Effect profile',
            'configured_profile' => 'Configured profile',
            'effects_fallback' => 'Effects-disabled fallback',
            'motion_mode' => 'Motion mode',
            'handoff_count' => 'Handoff count',
            'blocked_degraded' => 'Blocked / degraded deps',
            'active_runtime_readiness' => 'Active Runtime Readiness',
            'preview_effect_profile' => 'Preview Effect Profile',
            'preview_only_note' => 'Preview only. These controls do not save or change the active application theme.',
            'active_combined_mode' => 'Active combined mode',
            'active_palette' => 'Active palette',
            'active_effect_profile' => 'Active effect profile',
            'preview_palette' => 'Preview palette',
            'preview_profile' => 'Preview effect profile',
            'preview_effects_state' => 'Preview effects state',
            'preview_motion' => 'Preview motion mode',
            'preview_status' => 'Preview status',
            'fallback_status' => 'Fallback status',
            'accessibility_status' => 'Accessibility status',
            'render_preview' => 'Render preview',
            'reset_preview' => 'Reset preview to active mode',
            'effects_on' => 'Effects on',
            'effects_off' => 'Effects off',
            'preview_canvas' => 'Isolated preview canvas',
            'theme_asset_health' => 'Runtime theme asset health',
            'selector_status' => 'Required selector status',
            'token_status' => 'Required token status',
            'reduced_motion_fallback' => 'Reduced-motion fallback',
            'capability_status' => 'Capability evidence status',
            'fallback_profile' => 'Fallback profile',
            'full_preview_state' => 'Full preview state',
            'selector_token_evidence' => 'Selector/token evidence',
            'future_effect_controls_title' => 'Future Effects Controls',
            'future_controls_note' => 'Future persistent controls require audit and rollback design. Persistence is not enabled in this phase.',
            'resolution_hierarchy' => 'Control resolution hierarchy',
            'current_active_resolved_state' => 'Current active resolved state',
            'organization_policy_model' => 'Organization policy model',
            'user_override_model' => 'User override model',
            'accessibility_runtime_constraints' => 'Accessibility and runtime constraints',
            'future_persistence_requirements' => 'Future persistence requirements',
            'future_control_matrix' => 'Future control matrix',
            'setting_name' => 'Setting name',
            'current_resolved_state' => 'Current resolved state',
            'future_configurable_scope' => 'Future configurable scope',
            'required_authority' => 'Required authority',
            'user_override_allowed' => 'User override allowed',
            'policy_status' => 'Policy status',
            'current_phase' => 'Current phase',
            'design_only' => 'Design only - persistence not enabled',
            'full_resolution_trace' => 'Full resolution trace',
            'future_control_plan_records' => 'Future control-plan records',
            'required_audit_evidence' => 'Required audit evidence',
            'capability_accessibility_constraints' => 'Capability and accessibility constraints',
            'requested_value' => 'Requested value',
            'projected_resolved_value' => 'Projected resolved value',
            'persistent_controls_readiness' => 'Persistent Controls Readiness',
            'appearance_origin' => 'Current appearance-state origin',
            'active_combined_mode' => 'Active combined mode',
            'canonical_ownership_status' => 'Canonical ownership status',
            'recommended_strategy' => 'Recommended future persistence strategy',
            'readiness_by_scope' => 'Readiness by setting scope',
            'main_blockers' => 'Main blockers',
            'theme_doctor_dependency' => 'Theme Doctor dependency',
            'integration_phase' => 'Read-only integration audit - persistence not enabled',
            'future_scope' => 'Future scope',
            'existing_source_of_truth' => 'Existing source of truth',
            'write_path_known' => 'Write path known',
            'authority_known' => 'Authority known',
            'audit_rollback_known' => 'Audit/rollback known',
            'runtime_application_known' => 'Runtime application known',
            'readiness_status' => 'Readiness',
            'blockers' => 'Blockers',
            'full_appearance_state_map' => 'Full appearance-state map',
            'rendering_path' => 'Rendering path',
            'existing_write_path_evidence' => 'Existing write-path evidence',
            'compatibility_strategy_evaluation' => 'Compatibility strategy evaluation',
            'conflict_prevention_model' => 'Conflict-prevention model',
            'future_resolver_integration_records' => 'Future resolver integration records',
            'theme_doctor_dependency_evidence' => 'Theme Doctor dependency evidence',
            'combined_mode' => 'Combined mode',
            'derived_palette' => 'Derived palette',
            'derived_effect_profile' => 'Derived effect profile',
            'effects_enabled' => 'Effects enabled',
            'resolver_source' => 'Resolver source',
            'profile_readiness' => 'Profile readiness',
            'current_scan_context' => 'Current Scan Context',
            'scan_scope' => 'Scan scope',
            'selected_owner' => 'Selected owner',
            'source_root' => 'Source root',
            'scanner_contract_version' => 'Scanner contract version',
            'operational_filtering_status' => 'Operational filtering status',
            'source_revision_fingerprint' => 'Source revision fingerprint',
            'parity' => 'Style Compliance ↔ Special Effects Parity',
            'matching_ids' => 'Matching handoff IDs',
            'missing_in_effects' => 'Missing in Special Effects',
            'unexpected_in_effects' => 'Unexpected in Special Effects',
            'excluded_by_adapter' => 'Excluded by adapter',
            'unmapped_category_records' => 'Unmapped category records',
            'parity_status' => 'Parity status',
            'full_parity_comparison' => 'Full parity comparison',
            'difference_reason' => 'Difference reason',
            'style_compliance_status' => 'Style Compliance status',
            'special_effects_status' => 'Special Effects status',
            'category_totals' => 'Category totals',
            'mapping_evidence' => 'Detailed category mapping evidence',
            'mapping_registry' => 'Mapping-rule registry',
            'mapping_rule' => 'Mapping rule',
            'mapping_status' => 'Mapping status',
            'why_applies' => 'Why this category applies',
            'why_not' => 'Why other categories do not apply',
            'full_provenance' => 'Full source provenance',
            'reconciliation' => 'Style Compliance Handoff Reconciliation',
            'handoffs_available' => 'Style Compliance handoffs available',
            'handoffs_displayed' => 'Special Effects handoffs displayed',
            'handoffs_excluded' => 'Excluded handoffs',
            'handoffs_unmapped' => 'Unmapped handoffs',
            'missing_profile_mapping' => 'Blocked by missing profile mapping',
            'outside_selected_scope' => 'Outside current selected scope',
            'category_mapping_status' => 'Category mapping status',
            'source_context_used' => 'Source context used',
            'future_controls' => 'Future controls',
            'not_enabled' => 'यो चरणमा enabled छैन',
            'runtime_layers' => 'Runtime Layers',
            'registry' => 'Effects Registry',
            'required_selectors' => 'Required selectors',
            'required_tokens' => 'Required tokens',
            'required_capabilities' => 'Required capabilities',
            'fallback' => 'Fallback',
            'accessibility' => 'Accessibility constraints',
            'contrast_contract' => 'Contrast contract',
            'readiness' => 'Readiness',
            'dependencies' => 'Dependencies',
            'missing_selectors' => 'Missing selectors',
            'missing_tokens' => 'Missing tokens',
            'runtime_evidence' => 'Runtime evidence',
            'handoff_queue' => 'Style Compliance Handoff Queue',
            'handoff_note' => 'Style Compliance future_tool_handoff proposals मात्र देखाइन्छ। Deterministic fixes र manual semantic decisions समावेश छैनन्।',
            'source_owner' => 'Source owner',
            'file' => 'File',
            'line' => 'Line',
            'handoff_id' => 'Handoff ID',
            'selector' => 'Selector',
            'property' => 'Property',
            'current_value' => 'Current value',
            'category' => 'Effect category',
            'confidence' => 'Confidence',
            'reason' => 'Reason',
            'fallback_requirement' => 'Fallback requirement',
            'profile_relevance' => 'Profile relevance',
            'category_backdrop_blur' => 'Backdrop blur',
            'category_motion_transition' => 'Motion / transition',
            'category_decorative_transform' => 'Decorative transform',
            'category_filter_mask_blend' => 'Filter / mask / blend',
            'category_decorative_glow_shadow' => 'Decorative glow / shadow',
            'category_decorative_gradient' => 'Decorative gradient',
            'category_glass_translucency' => 'Glass translucency',
            'category_decorative_opacity' => 'Decorative opacity',
            'category_review_required' => 'Review required',
            'control' => 'Control',
            'scope' => 'Scope',
            'authority' => 'Authority',
            'persistence_owner' => 'Persistence owner',
            'enabled_now' => 'Enabled now',
            'resolver_details' => 'Compatibility Resolver Details',
            'safety_contract' => 'Safety Contract',
            'empty' => 'None',
            'yes' => 'Yes',
            'no' => 'No',
            'shell_appearance_consistency_title' => 'Appearance State Consistency',
            'shell_appearance_consistency_desc' => 'Shadow resolver state computed by Shell\'s canonical AppearanceStateResolver, compared with the current Special Effects appearance audit.',
            'shell_resolved_mode' => 'Shell-resolved combined mode',
            'shell_resolved_palette' => 'Shell-resolved palette',
            'shell_resolved_profile' => 'Shell-resolved effect profile',
            'shell_effects_enabled' => 'Shell-resolved effects enabled',
            'shell_parity_status' => 'Rendering parity status',
            'shell_parity_aligned' => 'Aligned',
            'shell_parity_mismatch' => 'Mismatch (diagnostic only)',
            'shell_source_trace_title' => 'Appearance source trace',
            'shell_browser_override_title' => 'Browser override contract',
            'shell_future_readiness_title' => 'Future structured-state readiness',
            'shell_future_precedence_title' => 'Future precedence model',
            'shell_full_resolver_output' => 'Full Shell resolver output',
            'shell_drift_detected' => 'Drift detected',
            'shell_drift_resolved' => 'Resolved',
            'shell_drift_not_observable' => 'Not observable server-side',
            'shell_drift_unknown' => 'Unknown',
            'shell_observer_title' => 'Browser Appearance Observer',
            'shell_observer_desc' => 'Read-only observation of the effective browser appearance state. No writes, no localStorage changes, no network requests.',
            'shell_observer_not_started' => 'Observer not started',
            'shell_observer_status' => 'Observer status',
            'shell_observed_mode' => 'Observed combined mode',
            'shell_observed_palette' => 'Observed palette',
            'shell_observed_profile' => 'Observed profile',
            'shell_observed_html_attrs' => 'HTML attributes observed',
            'shell_drift_consistent' => 'Consistent',
            'shell_drift_inconsistent' => 'Inconsistent with resolved intent',
            'shell_observer_pending' => 'Waiting for browser...',
            'shell_no_observer_data' => 'No browser observation available',
            'drift' => 'Drift',
            'auth_comparison_title' => 'Auth Server-Render Comparison',
            'auth_comparison_desc' => 'Parallel comparison of the legacy Auth server-default mode against Shell\'s canonical AppearanceStateResolver server intent. No Auth rendering, localStorage, session, database, or browser state is read or mutated.',
            'auth_comparison_legacy_mode' => 'Auth server-default mode',
            'auth_comparison_shell_mode' => 'Shell server intent mode',
            'auth_comparison_comparison_id' => 'Comparison ID',
            'auth_comparison_evidence_basis' => 'Evidence basis',
            'auth_comparison_status' => 'Comparison status',
            'auth_comparison_aligned' => 'Aligned',
            'auth_comparison_mismatch' => 'Mismatch',
            'auth_comparison_unknown' => 'Unknown',
            'auth_comparison_reason' => 'Reason',
            'auth_comparison_mismatch_field' => 'Mismatch field',
            'auth_comparison_legacy_value' => 'Legacy value',
            'auth_comparison_shell_value' => 'Shell value',
            'auth_comparison_legacy_trace' => 'Legacy source trace',
            'auth_comparison_shell_trace' => 'Shell source trace',
            'auth_comparison_browser_override' => 'Browser override included',
            'auth_comparison_browser_override_excluded' => 'Browser override excluded',
            'auth_comparison_browser_state' => 'Browser effective state claimed',
            'auth_comparison_diagnostic_only' => 'Diagnostic only',
            'auth_comparison_cutover_status' => 'Cutover status',
            'auth_comparison_not_ready' => 'Not ready',
            'auth_comparison_blockers' => 'Cutover blockers',
            'auth_comparison_reader_id' => 'Reader ID',
            'auth_comparison_full_contract' => 'Full comparison contract',
            'auth_comparison_mismatch_details' => 'Mismatch fields',
            'auth_comparison_inventory' => 'Appearance Reader Inventory',
            'auth_comparison_inventory_desc' => 'Folded read-only inventory with Auth comparison status attached. Reader classifications are unchanged.',
            'auth_comparison_no_mismatch' => 'No mismatch fields',
            'auth_comparison_source_label' => 'Auth server-default path comparison. Browser runtime override is excluded.',
            'auth_ledger_title' => 'Auth Parity Evidence Ledger',
            'auth_ledger_desc' => 'This ledger compares Auth server-source behavior with Shell server intent. It does not verify browser-effective appearance and does not change Auth rendering.',
            'auth_ledger_known_cases' => 'Known cases',
            'auth_ledger_aligned' => 'Aligned',
            'auth_ledger_mismatch' => 'Mismatch',
            'auth_ledger_unknown' => 'Unknown',
            'auth_ledger_observed_responses' => 'Observed Auth responses',
            'auth_ledger_evidence_basis' => 'Evidence basis',
            'auth_ledger_all_cases' => 'All ledger case rows',
            'auth_ledger_raw_json' => 'Raw ledger JSON',
            'auth_ledger_case_id' => 'Case ID',
            'auth_ledger_auth_mode' => 'Auth source-contract mode',
            'auth_ledger_shell_mode' => 'Shell intent mode',
            'auth_ledger_status' => 'Parity status',
            'auth_ledger_fallback' => 'Fallback evidence',
            'auth_ledger_source_status' => 'Source-evidence status',
            'auth_ledger_reason' => 'Reason',
            'auth_ledger_observed_flag' => 'Observed render',
        ],
    ];
    $set = isset($dict[$lang]) && is_array($dict[$lang]) ? $dict[$lang] : $dict['en'];
    return (string)($set[$key] ?? ($dict['en'][$key] ?? $key));
};

$listText = static function (array $values) use ($se): string {
    $clean = array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $values), static fn(string $v): bool => $v !== ''));
    return $clean === [] ? $se('empty') : implode(', ', $clean);
};
?>
<link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css">
<style>
.se-page { display: grid; gap: 0.85rem; }
.se-panel { border: 1px solid var(--line); border-radius: 8px; background: var(--surface); padding: 0.85rem; }
.se-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.65rem; }
.se-metric { border: 1px solid var(--line); border-radius: 8px; padding: 0.65rem; background: var(--surface-muted); min-height: 78px; }
.se-metric dt { color: var(--muted); font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }
.se-metric dd { margin: 0.35rem 0 0; font-weight: 750; overflow-wrap: anywhere; }
.se-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 0.7rem; }
.se-card { border: 1px solid var(--line); border-radius: 8px; padding: 0.75rem; background: var(--surface); }
.se-card h4 { margin: 0 0 0.35rem; }
.se-badge { display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: 999px; padding: 0.15rem 0.45rem; font-size: 0.72rem; font-weight: 700; background: var(--surface-muted); }
.se-badge--available { color: #116149; border-color: rgba(17, 97, 73, 0.26); }
.se-badge--degraded, .se-badge--not_configured { color: #7a4b00; border-color: rgba(122, 75, 0, 0.28); }
.se-badge--blocked { color: #9f1239; border-color: rgba(159, 18, 57, 0.3); }
.se-table-wrap { overflow-x: auto; }
.se-table { width: 100%; border-collapse: collapse; min-width: 760px; }
.se-table th, .se-table td { border-bottom: 1px solid var(--line); padding: 0.45rem; text-align: left; vertical-align: top; }
.se-table th { color: var(--muted); font-size: 0.72rem; text-transform: uppercase; }
.se-muted { color: var(--muted); }
.se-details { border: 1px solid var(--line); border-radius: 8px; padding: 0.65rem; background: var(--surface); }
.se-details summary { cursor: pointer; font-weight: 750; }
.se-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.8rem; overflow-wrap: anywhere; }
.se-preview-controls { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.65rem; align-items: end; }
.se-preview-controls label { display: grid; gap: 0.3rem; color: var(--muted); font-weight: 700; font-size: 0.78rem; }
.se-preview-controls select { border: 1px solid var(--line); border-radius: 8px; padding: 0.5rem; background: var(--surface); color: inherit; }
.se-preview-actions { display: flex; gap: 0.55rem; flex-wrap: wrap; align-items: center; margin-top: 0.65rem; }
.se-preview-frame { width: 100%; min-height: 620px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); }
</style>

<section class="gui-studio se-page">
  <div class="gs-head">
    <h2><?= e($se('title')) ?></h2>
    <p class="muted"><?= e($se('subtitle')) ?></p>
    <p class="se-muted"><?= e($se('read_only')) ?></p>
  </div>

  <section class="se-panel">
    <h3><?= e($se('runtime_status')) ?></h3>
    <dl class="se-summary">
      <div class="se-metric"><dt><?= e($se('palette_mode')) ?></dt><dd><?= e((string)($status['palette_mode'] ?? 'system')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('effect_profile')) ?></dt><dd><?= e((string)($status['resolved_effect_profile'] ?? 'paper')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('configured_profile')) ?></dt><dd><?= e((string)($status['configured_effect_profile'] ?? 'paper')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('effects_fallback')) ?></dt><dd><?= e((string)($status['effects_disabled_fallback'] ?? 'not_active')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('motion_mode')) ?></dt><dd><?= e((string)($status['motion_mode'] ?? 'system')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('handoff_count')) ?></dt><dd><?= e((string)($status['handoff_count'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('blocked_degraded')) ?></dt><dd><?= e((string)($status['blocked_dependencies'] ?? 0)) ?> / <?= e((string)($status['degraded_dependencies'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('future_controls')) ?></dt><dd><?= e($se('not_enabled')) ?></dd></div>
    </dl>
  </section>

  <section class="se-panel">
    <h3><?= e($se('active_runtime_readiness')) ?></h3>
    <dl class="se-summary">
      <div class="se-metric"><dt><?= e($se('combined_mode')) ?></dt><dd><?= e((string)($runtimeReadiness['combined_mode'] ?? 'System Liquid Glass')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('derived_palette')) ?></dt><dd><?= e((string)($runtimeReadiness['derived_palette'] ?? 'system')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('derived_effect_profile')) ?></dt><dd><?= e((string)($runtimeReadiness['derived_effect_profile'] ?? 'paper')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('effects_enabled')) ?></dt><dd><?= !empty($runtimeReadiness['effects_enabled']) ? e($se('yes')) : e($se('no')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('motion_mode')) ?></dt><dd><?= e((string)($runtimeReadiness['motion_mode'] ?? 'system')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('profile_readiness')) ?></dt><dd><?= e((string)($runtimeReadiness['profile_readiness'] ?? 'not_configured')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('resolver_source')) ?></dt><dd><?= e((string)($runtimeReadiness['resolver_source'] ?? '')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('category_mapping_status')) ?></dt><dd><?= e((string)($runtimeReadiness['handoff_reconciliation_status'] ?? 'unknown')) ?></dd></div>
    </dl>
  </section>

  <section class="se-panel">
    <h3><?= e($se('preview_effect_profile')) ?></h3>
    <p class="se-muted"><?= e($se('preview_only_note')) ?></p>
    <dl class="se-summary">
      <div class="se-metric"><dt><?= e($se('active_combined_mode')) ?></dt><dd><?= e((string)($previewState['active_combined_mode'] ?? '')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('active_palette')) ?></dt><dd><?= e((string)($previewState['active_palette_mode'] ?? 'system')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('active_effect_profile')) ?></dt><dd><?= e((string)($previewState['active_effect_profile'] ?? 'liquid_glass')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('preview_palette')) ?></dt><dd><?= e((string)($previewState['preview_palette_mode'] ?? 'system')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('preview_profile')) ?></dt><dd><?= e((string)($previewState['preview_effect_profile'] ?? 'liquid_glass')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('preview_effects_state')) ?></dt><dd><?= !empty($previewState['preview_effects_enabled']) ? e($se('effects_on')) : e($se('effects_off')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('preview_status')) ?></dt><dd><?= e((string)($previewState['profile_readiness'] ?? 'not_configured')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('accessibility_status')) ?></dt><dd><?= e((string)($previewState['focus_visibility'] ?? 'preserved')) ?></dd></div>
    </dl>

    <form method="get" action="/apps/studio/tools/customization-studio/effects" class="se-preview-controls">
      <input type="hidden" name="scan_scope" value="<?= e((string)($scanContext['scope'] ?? 'owner')) ?>">
      <input type="hidden" name="scan_owner" value="<?= e((string)($scanContext['owner'] ?? 'Studio')) ?>">
      <input type="hidden" name="theme" value="<?= e((string)($runtime['combined_preference'] ?? 'system-liquid-glass')) ?>">
      <input type="hidden" name="effects" value="<?= !empty($runtime['effects_enabled']) ? '1' : '0' ?>">
      <input type="hidden" name="motion" value="<?= e((string)($runtime['motion_mode'] ?? 'system')) ?>">
      <input type="hidden" name="preview_source" value="manual_preview">
      <label><?= e($se('preview_palette')) ?>
        <select name="preview_palette_mode">
          <?php foreach (['system', 'light', 'dark'] as $option): ?>
            <option value="<?= e($option) ?>" <?= (string)($previewState['preview_palette_mode'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><?= e($se('preview_profile')) ?>
        <select name="preview_effect_profile">
          <?php foreach (['paper', 'liquid_glass', 'none'] as $option): ?>
            <option value="<?= e($option) ?>" <?= (string)($previewState['preview_effect_profile'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><?= e($se('preview_effects_state')) ?>
        <select name="preview_effects_enabled">
          <option value="1" <?= !empty($previewState['preview_effects_enabled']) ? 'selected' : '' ?>><?= e($se('effects_on')) ?></option>
          <option value="0" <?= empty($previewState['preview_effects_enabled']) ? 'selected' : '' ?>><?= e($se('effects_off')) ?></option>
        </select>
      </label>
      <label><?= e($se('preview_motion')) ?>
        <select name="preview_motion_mode">
          <?php foreach (['system', 'reduced', 'off'] as $option): ?>
            <option value="<?= e($option) ?>" <?= (string)($previewState['preview_motion_mode'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="se-preview-actions">
        <button type="submit" class="btn"><?= e($se('render_preview')) ?></button>
        <a class="btn" href="/apps/studio/tools/customization-studio/effects?<?= e(http_build_query(['scan_scope' => (string)($scanContext['scope'] ?? 'owner'), 'scan_owner' => (string)($scanContext['owner'] ?? 'Studio'), 'theme' => (string)($runtime['combined_preference'] ?? 'system-liquid-glass'), 'effects' => !empty($runtime['effects_enabled']) ? '1' : '0', 'motion' => (string)($runtime['motion_mode'] ?? 'system')])) ?>"><?= e($se('reset_preview')) ?></a>
      </div>
    </form>

    <dl class="se-summary" style="margin-top:0.7rem;">
      <div class="se-metric"><dt><?= e($se('fallback_status')) ?></dt><dd><?= e((string)($previewState['fallback_status'] ?? 'ready')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('theme_asset_health')) ?></dt><dd><?= e((string)($previewState['runtime_theme_asset_health'] ?? 'unknown')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('selector_status')) ?></dt><dd><?= e((string)($previewState['required_selector_status'] ?? 'unknown')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('token_status')) ?></dt><dd><?= e((string)($previewState['required_token_status'] ?? 'unknown')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('reduced_motion_fallback')) ?></dt><dd><?= e((string)($previewState['reduced_motion_fallback'] ?? 'system')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('fallback_profile')) ?></dt><dd><?= e((string)($previewState['fallback_profile'] ?? 'none')) ?></dd></div>
    </dl>

    <?php $previewFrameUrl = (string)($previewState['preview_url'] ?? ''); ?>
    <?php if ($previewFrameUrl !== ''): ?>
      <h4><?= e($se('preview_canvas')) ?></h4>
      <iframe class="se-preview-frame" src="<?= e($previewFrameUrl) ?>" title="<?= e($se('preview_canvas')) ?>" sandbox="allow-same-origin" referrerpolicy="same-origin"></iframe>
    <?php endif; ?>

    <details class="se-details">
      <summary><?= e($se('full_preview_state')) ?></summary>
      <pre class="se-code"><?= e(json_encode($previewState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
    </details>
    <details class="se-details">
      <summary><?= e($se('selector_token_evidence')) ?></summary>
      <pre class="se-code"><?= e(json_encode(['selector_evidence' => $previewState['selector_evidence'] ?? [], 'token_evidence' => $previewState['token_evidence'] ?? [], 'capability' => $previewState['capability_evidence_status'] ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
    </details>
  </section>

  <section class="se-panel">
    <h3><?= e($se('future_effect_controls_title')) ?></h3>
    <p class="se-muted"><?= e($se('future_controls_note')) ?></p>
    <dl class="se-summary">
      <div class="se-metric"><dt><?= e($se('resolution_hierarchy')) ?></dt><dd><?= e($listText((array)($futureEffectControls['resolution_hierarchy'] ?? []))) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('current_active_resolved_state')) ?></dt><dd><?= e((string)($futureResolvedState['effect_profile'] ?? '')) ?> / <?= !empty($futureResolvedState['effects_enabled']) ? e($se('effects_on')) : e($se('effects_off')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('organization_policy_model')) ?></dt><dd><?= e(!empty($futureSettingsContract['organization_effect_policy']['allow_user_override']) ? $se('yes') : $se('no')) ?> · <?= e((string)($futureSettingsContract['organization_effect_policy']['default_effect_profile'] ?? '')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('user_override_model')) ?></dt><dd><?= e((string)($futureSettingsContract['user_effect_preference']['effect_profile'] ?? 'inherit')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('accessibility_runtime_constraints')) ?></dt><dd><?= e((string)($futureSettingsContract['runtime_constraints']['profile_readiness'] ?? '')) ?> · <?= !empty($futureSettingsContract['runtime_constraints']['reduced_motion_required']) ? e($se('yes')) : e($se('no')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('future_persistence_requirements')) ?></dt><dd><?= e($se('design_only')) ?></dd></div>
    </dl>

    <h4><?= e($se('future_control_matrix')) ?></h4>
    <div class="se-table-wrap">
      <table class="se-table">
        <thead>
          <tr>
            <th><?= e($se('setting_name')) ?></th>
            <th><?= e($se('requested_value')) ?></th>
            <th><?= e($se('current_resolved_state')) ?></th>
            <th><?= e($se('projected_resolved_value')) ?></th>
            <th><?= e($se('future_configurable_scope')) ?></th>
            <th><?= e($se('required_authority')) ?></th>
            <th><?= e($se('policy_status')) ?></th>
            <th><?= e($se('current_phase')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($futureControlPlans as $plan): ?>
            <?php if (!is_array($plan)) { continue; } ?>
            <tr>
              <td class="se-code"><?= e((string)($plan['setting_key'] ?? '')) ?></td>
              <td><?= e((string)($plan['requested_value'] ?? '')) ?></td>
              <td><?= e((string)($plan['current_resolved_value'] ?? '')) ?></td>
              <td><?= e((string)($plan['projected_resolved_value'] ?? '')) ?></td>
              <td><?= e((string)($plan['setting_scope'] ?? '')) ?></td>
              <td><?= e((string)($plan['authority_requirement'] ?? '')) ?></td>
              <td><?= e((string)($plan['policy_status'] ?? '')) ?></td>
              <td><?= e($se('design_only')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($futureControlPlans === []): ?>
            <tr><td colspan="8"><?= e($se('empty')) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <details class="se-details">
      <summary><?= e($se('full_resolution_trace')) ?></summary>
      <pre class="se-code"><?= e(json_encode($futureResolvedState['resolution_trace'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]') ?></pre>
    </details>
    <details class="se-details">
      <summary><?= e($se('future_control_plan_records')) ?></summary>
      <pre class="se-code"><?= e(json_encode($futureControlPlans, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]') ?></pre>
    </details>
    <details class="se-details">
      <summary><?= e($se('required_audit_evidence')) ?></summary>
      <pre class="se-code"><?= e(json_encode($futureAuditEvidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]') ?></pre>
    </details>
    <details class="se-details">
      <summary><?= e($se('capability_accessibility_constraints')) ?></summary>
      <pre class="se-code"><?= e(json_encode(['runtime_constraints' => $futureSettingsContract['runtime_constraints'] ?? [], 'authority_contract' => $futureAuthorityRows, 'preview_reference' => $futurePreviewReference], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
    </details>
  </section>

  <section class="se-panel">
    <h3><?= e($se('persistent_controls_readiness')) ?></h3>
    <p class="se-muted"><?= e($se('integration_phase')) ?></p>
    <dl class="se-summary">
      <div class="se-metric"><dt><?= e($se('appearance_origin')) ?></dt><dd><?= e((string)($appearanceMap['state_origin'] ?? 'unknown')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('active_combined_mode')) ?></dt><dd><?= e((string)($appearanceMap['current_combined_mode'] ?? '')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('canonical_ownership_status')) ?></dt><dd><?= e((string)($appearanceMap['state_owner'] ?? 'unknown')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('recommended_strategy')) ?></dt><dd><?= e((string)($appearanceAudit['recommended_future_persistence_strategy'] ?? '')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('main_blockers')) ?></dt><dd><?= e($listText($appearanceMainBlockers)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('theme_doctor_dependency')) ?></dt><dd><?= !empty($themeDoctorDependency['future_persistent_change_blocks_when']['profile_readiness_blocked']) ? e($se('yes')) : e($se('no')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('preview_status')) ?></dt><dd><?= e((string)($appearanceAudit['preview_relationship']['preview_contract_status'] ?? 'local_non_persistent_state_only')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('future_persistence_requirements')) ?></dt><dd><?= e($se('integration_phase')) ?></dd></div>
    </dl>

    <h4><?= e($se('readiness_by_scope')) ?></h4>
    <div class="se-table-wrap">
      <table class="se-table">
        <thead>
          <tr>
            <th><?= e($se('setting_name')) ?></th>
            <th><?= e($se('future_scope')) ?></th>
            <th><?= e($se('existing_source_of_truth')) ?></th>
            <th><?= e($se('write_path_known')) ?></th>
            <th><?= e($se('authority_known')) ?></th>
            <th><?= e($se('audit_rollback_known')) ?></th>
            <th><?= e($se('runtime_application_known')) ?></th>
            <th><?= e($se('readiness_status')) ?></th>
            <th><?= e($se('blockers')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($appearanceReadinessRecords as $record): ?>
            <?php if (!is_array($record)) { continue; } ?>
            <tr>
              <td class="se-code"><?= e((string)($record['setting_key'] ?? '')) ?></td>
              <td><?= e((string)($record['setting_scope'] ?? '')) ?></td>
              <td><?= e((string)($record['existing_source_of_truth'] ?? '')) ?></td>
              <td><?= !empty($record['existing_write_path_reusable']) ? e($se('yes')) : e($se('no')) ?></td>
              <td><?= !empty($record['authority_contract_available']) ? e($se('yes')) : e($se('no')) ?></td>
              <td><?= (!empty($record['audit_contract_available']) && !empty($record['rollback_contract_available'])) ? e($se('yes')) : e($se('no')) ?></td>
              <td><?= !empty($record['runtime_application_path_available']) ? e($se('yes')) : e($se('no')) ?></td>
              <td><?= e((string)($record['readiness_status'] ?? 'unknown')) ?></td>
              <td><?= e($listText((array)($record['blocked_by'] ?? []))) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($appearanceReadinessRecords === []): ?>
            <tr><td colspan="9"><?= e($se('empty')) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <details class="se-details">
      <summary><?= e($se('full_appearance_state_map')) ?></summary>
      <pre class="se-code"><?= e(json_encode($appearanceMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
    </details>
    <details class="se-details">
      <summary><?= e($se('rendering_path')) ?></summary>
      <pre class="se-code"><?= e(json_encode($appearanceMap['rendering_path'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]') ?></pre>
    </details>
    <details class="se-details">
      <summary><?= e($se('existing_write_path_evidence')) ?></summary>
      <pre class="se-code"><?= e(json_encode(['write_path_summary' => $appearanceMap['write_path_summary'] ?? '', 'evidence' => $appearanceMap['evidence'] ?? []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
    </details>
    <details class="se-details">
      <summary><?= e($se('compatibility_strategy_evaluation')) ?></summary>
      <pre class="se-code"><?= e(json_encode($appearanceAudit['compatibility_strategy_evaluation'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]') ?></pre>
    </details>
    <details class="se-details">
      <summary><?= e($se('conflict_prevention_model')) ?></summary>
      <pre class="se-code"><?= e(json_encode($appearanceAudit['conflict_prevention'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
    </details>
    <details class="se-details">
      <summary><?= e($se('future_resolver_integration_records')) ?></summary>
      <pre class="se-code"><?= e(json_encode($appearanceReadinessRecords, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]') ?></pre>
    </details>
    <details class="se-details">
      <summary><?= e($se('theme_doctor_dependency_evidence')) ?></summary>
      <pre class="se-code"><?= e(json_encode($themeDoctorDependency, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
    </details>
  </section>

  <section class="se-panel">
    <h3><?= e($se('current_scan_context')) ?></h3>
    <dl class="se-summary">
      <div class="se-metric"><dt><?= e($se('scan_scope')) ?></dt><dd><?= e((string)($scanContext['scope'] ?? 'owner')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('selected_owner')) ?></dt><dd><?= e((string)($scanContext['owner'] ?? 'Studio')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('source_root')) ?></dt><dd class="se-code"><?= e((string)($scanContext['source_root'] ?? '')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('scanner_contract_version')) ?></dt><dd><?= e((string)($scanContext['scanner_contract_version'] ?? '')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('operational_filtering_status')) ?></dt><dd><?= e((string)($scanContext['operational_filtering_status'] ?? '')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('source_revision_fingerprint')) ?></dt><dd class="se-code"><?= e((string)($scanContext['source_revision_fingerprint'] ?? '')) ?></dd></div>
    </dl>
  </section>

  <section class="se-panel">
    <h3><?= e($se('parity')) ?></h3>
    <dl class="se-summary">
      <div class="se-metric"><dt><?= e($se('handoffs_available')) ?></dt><dd><?= e((string)($handoffParity['style_compliance_available'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('handoffs_displayed')) ?></dt><dd><?= e((string)($handoffParity['special_effects_displayed'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('matching_ids')) ?></dt><dd><?= e((string)($handoffParity['matching_handoff_ids'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('missing_in_effects')) ?></dt><dd><?= e((string)($handoffParity['missing_in_special_effects'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('unexpected_in_effects')) ?></dt><dd><?= e((string)($handoffParity['unexpected_in_special_effects'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('excluded_by_adapter')) ?></dt><dd><?= e((string)($handoffParity['excluded_by_adapter'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('unmapped_category_records')) ?></dt><dd><?= e((string)($handoffParity['unmapped_category_records'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('parity_status')) ?></dt><dd><?= e((string)($handoffParity['parity_status'] ?? 'unknown')) ?></dd></div>
    </dl>
  </section>

  <section class="se-panel">
    <h3><?= e($se('category_totals')) ?></h3>
    <dl class="se-summary">
      <?php foreach ((array)($handoffs['summary']['groups'] ?? []) as $category => $count): ?>
        <div class="se-metric"><dt><?= e($se('category_' . (string)$category)) ?></dt><dd><?= e((string)$count) ?></dd></div>
      <?php endforeach; ?>
      <?php if ((array)($handoffs['summary']['groups'] ?? []) === []): ?>
        <div class="se-metric"><dt><?= e($se('category')) ?></dt><dd><?= e($se('empty')) ?></dd></div>
      <?php endif; ?>
    </dl>
  </section>

  <section class="se-panel">
    <h3><?= e($se('reconciliation')) ?></h3>
    <dl class="se-summary">
      <div class="se-metric"><dt><?= e($se('handoffs_available')) ?></dt><dd><?= e((string)($handoffReconciliation['available'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('handoffs_displayed')) ?></dt><dd><?= e((string)($handoffReconciliation['displayed'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('handoffs_excluded')) ?></dt><dd><?= e((string)($handoffReconciliation['excluded'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('handoffs_unmapped')) ?></dt><dd><?= e((string)($handoffReconciliation['unmapped'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('missing_profile_mapping')) ?></dt><dd><?= e((string)($handoffReconciliation['blocked_by_missing_profile_mapping'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('outside_selected_scope')) ?></dt><dd><?= e((string)($handoffReconciliation['outside_current_selected_scope'] ?? 0)) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('category_mapping_status')) ?></dt><dd><?= e((string)($handoffReconciliation['category_mapping_status'] ?? 'unknown')) ?></dd></div>
      <div class="se-metric"><dt><?= e($se('source_context_used')) ?></dt><dd><?= e((string)($handoffReconciliation['source_context_used'] ?? '')) ?></dd></div>
    </dl>
  </section>

  <section class="se-panel">
    <h3><?= e($se('runtime_layers')) ?></h3>
    <div class="se-grid">
      <?php foreach ($layers as $layer): ?>
        <?php if (!is_array($layer)) { continue; } ?>
        <article class="se-card">
          <h4><?= e((string)($layer['label'] ?? '')) ?></h4>
          <p class="se-muted"><?= e((string)($layer['contract'] ?? '')) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="se-panel">
    <h3><?= e($se('registry')) ?></h3>
    <div class="se-grid">
      <?php foreach ($profiles as $profileId => $profile): ?>
        <?php
          if (!is_array($profile)) { continue; }
          $r = isset($readiness[$profileId]) && is_array($readiness[$profileId]) ? $readiness[$profileId] : [];
          $outcome = (string)($r['outcome'] ?? 'not_configured');
        ?>
        <article class="se-card">
          <h4><?= e((string)($profile['label'] ?? $profileId)) ?> <span class="se-badge se-badge--<?= e($outcome) ?>"><?= e($outcome) ?></span></h4>
          <p class="se-muted"><?= e((string)($profile['description'] ?? '')) ?></p>
          <p><strong><?= e($se('fallback')) ?>:</strong> <?= e((string)($profile['fallback_profile'] ?? 'none')) ?></p>
          <p><strong><?= e($se('contrast_contract')) ?>:</strong> <?= e((string)($profile['contrast_contract'] ?? '')) ?></p>
          <details class="se-details">
            <summary><?= e($se('readiness')) ?></summary>
            <p><strong><?= e($se('dependencies')) ?>:</strong> <?= e($listText((array)($r['dependencies'] ?? []))) ?></p>
            <p><strong><?= e($se('missing_selectors')) ?>:</strong> <?= e($listText((array)($r['missing_selectors'] ?? []))) ?></p>
            <p><strong><?= e($se('missing_tokens')) ?>:</strong> <?= e($listText((array)($r['missing_tokens'] ?? []))) ?></p>
            <p><strong><?= e($se('required_selectors')) ?>:</strong> <?= e($listText((array)($profile['required_selectors'] ?? []))) ?></p>
            <p><strong><?= e($se('required_tokens')) ?>:</strong> <?= e($listText((array)($profile['required_tokens'] ?? []))) ?></p>
            <p><strong><?= e($se('required_capabilities')) ?>:</strong> <?= e($listText((array)($profile['required_capabilities'] ?? []))) ?></p>
            <p><strong><?= e($se('accessibility')) ?>:</strong> <?= e($listText((array)($profile['accessibility_constraints'] ?? []))) ?></p>
          </details>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="se-panel">
    <h3><?= e($se('handoff_queue')) ?></h3>
    <p class="se-muted"><?= e($se('handoff_note')) ?></p>
    <?php if ($handoffGroups === []): ?>
      <p><?= e($se('empty')) ?></p>
    <?php endif; ?>
    <?php foreach ($handoffGroups as $category => $rows): ?>
      <?php $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : []; ?>
      <details class="se-details">
        <summary><?= e($se('category_' . (string)$category)) ?> · <?= count($rows) ?></summary>
        <div class="se-table-wrap">
          <table class="se-table">
            <thead>
              <tr>
                <th><?= e($se('handoff_id')) ?></th>
                <th><?= e($se('source_owner')) ?></th>
                <th><?= e($se('file')) ?></th>
                <th><?= e($se('line')) ?></th>
                <th><?= e($se('selector')) ?></th>
                <th><?= e($se('property')) ?></th>
                <th><?= e($se('current_value')) ?></th>
                <th><?= e($se('confidence')) ?></th>
                <th><?= e($se('reason')) ?></th>
                <th><?= e($se('fallback_requirement')) ?></th>
                <th><?= e($se('profile_relevance')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($rows, 0, 80) as $row): ?>
                <tr>
                  <td class="se-code"><?= e((string)($row['handoff_id'] ?? '')) ?></td>
                  <td><?= e((string)($row['source_owner'] ?? '')) ?></td>
                  <td class="se-code"><?= e((string)($row['file_path'] ?? $row['file'] ?? '')) ?></td>
                  <td><?= e((string)($row['line'] ?? 0)) ?></td>
                  <td class="se-code"><?= e((string)($row['selector'] ?? '')) ?></td>
                  <td class="se-code"><?= e((string)($row['property'] ?? '')) ?></td>
                  <td class="se-code"><?= e((string)($row['current_value'] ?? '')) ?></td>
                  <td><?= e((string)($row['detection_confidence'] ?? $row['confidence'] ?? '')) ?></td>
                  <td><?= e((string)($row['reason_code'] ?? $row['reason'] ?? '')) ?></td>
                  <td><?= e((string)($row['fallback_requirement'] ?? '')) ?></td>
                  <td><?= e((string)($row['profile_relevance'] ?? $row['current_profile_relevance'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>
    <?php endforeach; ?>
  </section>

  <details class="se-details">
    <summary><?= e($se('full_parity_comparison')) ?></summary>
    <div class="se-table-wrap">
      <table class="se-table">
        <thead>
          <tr>
            <th><?= e($se('handoff_id')) ?></th>
            <th><?= e($se('file')) ?></th>
            <th><?= e($se('line')) ?></th>
            <th><?= e($se('selector')) ?> / <?= e($se('property')) ?></th>
            <th><?= e($se('style_compliance_status')) ?></th>
            <th><?= e($se('special_effects_status')) ?></th>
            <th><?= e($se('difference_reason')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ((array)($handoffParity['difference_rows'] ?? []) as $row): ?>
            <?php if (!is_array($row)) { continue; } ?>
            <tr>
              <td class="se-code"><?= e((string)($row['handoff_id'] ?? '')) ?></td>
              <td class="se-code"><?= e((string)($row['file_path'] ?? '')) ?></td>
              <td><?= e((string)($row['line'] ?? 0)) ?></td>
              <td class="se-code"><?= e((string)($row['selector_property'] ?? '')) ?></td>
              <td><?= e((string)($row['style_compliance_status'] ?? '')) ?></td>
              <td><?= e((string)($row['special_effects_status'] ?? '')) ?></td>
              <td><?= e((string)($row['difference_reason'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ((array)($handoffParity['difference_rows'] ?? []) === []): ?>
            <tr><td colspan="7"><?= e($se('empty')) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </details>

  <details class="se-details">
    <summary><?= e($se('mapping_evidence')) ?></summary>
    <div class="se-table-wrap">
      <table class="se-table">
        <thead>
          <tr>
            <th><?= e($se('handoff_id')) ?></th>
            <th><?= e($se('category')) ?></th>
            <th><?= e($se('mapping_status')) ?></th>
            <th><?= e($se('property')) ?></th>
            <th><?= e($se('current_value')) ?></th>
            <th><?= e($se('reason')) ?></th>
            <th><?= e($se('mapping_rule')) ?></th>
            <th><?= e($se('why_applies')) ?></th>
            <th><?= e($se('why_not')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ((array)($handoffs['rows'] ?? []) as $row): ?>
            <?php
              if (!is_array($row)) { continue; }
              $evidence = isset($row['mapping_evidence']) && is_array($row['mapping_evidence']) ? $row['mapping_evidence'] : [];
            ?>
            <tr>
              <td class="se-code"><?= e((string)($row['handoff_id'] ?? '')) ?></td>
              <td><?= e((string)($evidence['effect_category'] ?? $row['effect_category'] ?? '')) ?></td>
              <td><?= e((string)($evidence['mapping_status'] ?? '')) ?></td>
              <td class="se-code"><?= e((string)($evidence['original_property'] ?? $row['property'] ?? '')) ?></td>
              <td class="se-code"><?= e((string)($evidence['original_value'] ?? $row['current_value'] ?? '')) ?></td>
              <td><?= e((string)($evidence['original_reason_code'] ?? $row['reason_code'] ?? '')) ?></td>
              <td class="se-code"><?= e((string)($evidence['mapping_rule_id'] ?? '')) ?></td>
              <td><?= e((string)($evidence['why_this_category_applies'] ?? '')) ?></td>
              <td><?= e($listText((array)($evidence['why_other_categories_do_not_apply'] ?? []))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>

  <details class="se-details">
    <summary><?= e($se('mapping_registry')) ?></summary>
    <pre class="se-code"><?= e(json_encode($mappingRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]') ?></pre>
  </details>

  <details class="se-details">
    <summary><?= e($se('full_provenance')) ?></summary>
    <pre class="se-code"><?= e(json_encode(['scan_context' => $scanContext, 'handoffs' => $handoffs['rows'] ?? []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
  </details>

  <section class="se-panel" id="shell-appearance-consistency">
    <h3><?= e($se('shell_appearance_consistency_title')) ?></h3>
    <p class="se-muted"><?= e($se('shell_appearance_consistency_desc')) ?></p>

    <?php if ($shellState === []): ?>
      <p class="se-muted"><?= e($se('empty')) ?></p>
    <?php else: ?>
      <?php
      $shellResolved = $shellState['server_resolved_intent'] ?? [];
      $shellParity = $shellState['rendering_parity'] ?? [];
      $shellSources = $shellState['source_trace'] ?? [];
      $shellBrowserContract = $shellState['browser_override_contract'] ?? [];
      $shellReadiness = $shellState['future_structured_state_readiness'] ?? [];
      $shellPrecedence = $shellState['future_precedence_model'] ?? [];
      $shellBrowserEffective = $shellState['browser_effective_state'] ?? [];
      $shellMode = (string)($shellResolved['combined_mode'] ?? '');
      $shellPalette = (string)($shellResolved['palette_mode'] ?? '');
      $shellProfile = (string)($shellResolved['effect_profile'] ?? '');
      $shellEffects = (string)(($shellResolved['effects_enabled'] ?? false) ? $se('yes') : $se('no'));
      $parityStatus = (string)($shellParity['status'] ?? 'unknown');
      $parityLabel = $parityStatus === 'aligned' ? $se('shell_parity_aligned') : ($parityStatus === 'mismatch' ? $se('shell_parity_mismatch') : $parityStatus);
      $driftDetected = $parityStatus === 'mismatch';
      ?>

      <h4><?= e($se('appearance_origin')) ?></h4>
      <dl class="se-summary">
        <div class="se-metric">
          <dt><?= e($se('shell_resolved_mode')) ?></dt>
          <dd class="se-code"><?= e($shellMode ?: $se('empty')) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('shell_resolved_palette')) ?></dt>
          <dd><?= e($shellPalette ?: $se('empty')) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('shell_resolved_profile')) ?></dt>
          <dd><?= e($shellProfile ?: $se('empty')) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('shell_effects_enabled')) ?></dt>
          <dd><?= e($shellEffects ?: $se('empty')) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('shell_parity_status')) ?></dt>
          <dd>
            <?php if ($driftDetected): ?>
              <span class="se-badge se-badge--blocked"><?= e($se('shell_drift_detected')) ?></span> <?= e($parityLabel) ?>
            <?php elseif ($parityStatus === 'not_observable'): ?>
              <span class="se-badge se-badge--degraded"><?= e($se('shell_drift_not_observable')) ?></span>
            <?php else: ?>
              <?= e($parityLabel) ?>
            <?php endif; ?>
          </dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('resolver_source')) ?></dt>
          <dd class="se-code"><?= e((string)($shellResolved['resolution_source'] ?? $se('empty'))) ?></dd>
        </div>
      </dl>

      <details class="se-details">
        <summary><?= e($se('shell_source_trace_title')) ?></summary>
        <div class="se-table-wrap">
          <table class="se-table">
            <thead>
              <tr>
                <th><?= e($se('setting_name')) ?></th>
                <th><?= e($se('current_value')) ?></th>
                <th><?= e($se('resolver_source')) ?></th>
                <th><?= e($se('readiness_status')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($shellSources as $src): ?>
                <?php if (!is_array($src)) { continue; } ?>
                <tr>
                  <td class="se-code"><?= e((string)($src['source_name'] ?? '')) ?></td>
                  <td class="se-code"><?= e((string)($src['candidate_value'] ?? '')) ?></td>
                  <td><?= e((string)($src['availability'] ?? '')) ?></td>
                  <td>
                    <?php if (!empty($src['selected'])): ?>
                      <span class="se-badge se-badge--available"><?= e($se('yes')) ?></span>
                    <?php else: ?>
                      <?= e($se('no')) ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>

      <details class="se-details">
        <summary><?= e($se('shell_browser_override_title')) ?></summary>
        <pre class="se-code"><?= e(json_encode($shellBrowserContract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
      </details>

      <details class="se-details">
        <summary><?= e($se('shell_future_readiness_title') . ' (' . count($shellReadiness) . ')') ?></summary>
        <div class="se-table-wrap">
          <table class="se-table">
            <thead>
              <tr>
                <th><?= e($se('setting_name')) ?></th>
                <th><?= e($se('readiness_status')) ?></th>
                <th><?= e($se('blockers')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($shellReadiness as $key => $rec): ?>
                <?php if (!is_array($rec)) { continue; } ?>
                <tr>
                  <td class="se-code"><?= e($key) ?></td>
                  <td><?= e((string)($rec['readiness'] ?? 'unknown')) ?></td>
                  <td><?= e($listText((array)($rec['blockers'] ?? []))) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>

      <details class="se-details">
        <summary><?= e($se('shell_future_precedence_title')) ?></summary>
        <pre class="se-code"><?= e(json_encode($shellPrecedence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
      </details>

      <details class="se-details">
        <summary><?= e($se('shell_full_resolver_output')) ?></summary>
        <pre class="se-code"><?= e(json_encode($shellState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
      </details>
    <?php endif; ?>

    <h4><?= e($se('shell_observer_title')) ?></h4>
    <p class="se-muted"><?= e($se('shell_observer_desc')) ?></p>
    <dl class="se-summary" id="shell-observer-status">
      <div class="se-metric">
        <dt><?= e($se('shell_observer_status')) ?></dt>
        <dd id="shell-observer-state"><?= e($se('shell_observer_not_started')) ?></dd>
      </div>
      <div class="se-metric">
        <dt><?= e($se('shell_observed_mode')) ?></dt>
        <dd id="shell-observer-mode"><?= e($se('shell_no_observer_data')) ?></dd>
      </div>
      <div class="se-metric">
        <dt><?= e($se('shell_observed_palette')) ?></dt>
        <dd id="shell-observer-palette"><?= e($se('shell_no_observer_data')) ?></dd>
      </div>
      <div class="se-metric">
        <dt><?= e($se('shell_observed_profile')) ?></dt>
        <dd id="shell-observer-profile"><?= e($se('shell_no_observer_data')) ?></dd>
      </div>
      <div class="se-metric">
        <dt><?= e($se('drift')) ?></dt>
        <dd id="shell-observer-drift"><?= e($se('shell_no_observer_data')) ?></dd>
      </div>
      <div class="se-metric">
        <dt><?= e($se('shell_observed_html_attrs')) ?></dt>
        <dd id="shell-observer-attrs" class="se-code"><?= e($se('shell_no_observer_data')) ?></dd>
      </div>
    </dl>
  </section>

  <section class="se-panel">
    <h3><?= e($se('auth_comparison_title')) ?></h3>
    <p class="se-muted"><?= e($se('auth_comparison_desc')) ?></p>

    <?php if ($authComparison === []): ?>
      <p class="se-muted"><?= e($se('empty')) ?></p>
    <?php else: ?>
      <?php
      $compStatus = (string)($authComparison['status'] ?? 'unknown');
      $compStatusLabel = $compStatus === 'aligned' ? $se('auth_comparison_aligned') : ($compStatus === 'mismatch' ? $se('auth_comparison_mismatch') : $se('auth_comparison_unknown'));
      $compBadgeClass = $compStatus === 'aligned' ? 'se-badge--available' : ($compStatus === 'mismatch' ? 'se-badge--blocked' : 'se-badge--degraded');
      $legacyMode = (string)($authComparison['legacy_auth_mode'] ?? '');
      $shellMode = (string)($authComparison['shell_server_intent_mode'] ?? '');
      $compId = (string)($authComparison['comparison_id'] ?? '');
      $evidenceBasis = (string)($authComparison['evidence_basis'] ?? '');
      $reason = (string)($authComparison['reason'] ?? '');
      $mismatchFields = (array)($authComparison['mismatch_fields'] ?? []);
      $diagnosticOnly = !empty($authComparison['diagnostic_only']);
      $cutoverStatus = (string)($authComparison['cutover_status'] ?? 'not_ready');
      $cutoverBlockers = (array)($authComparison['cutover_blockers'] ?? []);
      $browserOverride = !empty($authComparison['browser_override_included']);
      $browserStateClaimed = !empty($authComparison['browser_effective_state_claimed']);
      $readerId = (string)($authComparison['reader_id'] ?? '');
      $legacyTrace = (array)($authComparison['legacy_source_trace'] ?? []);
      $shellTrace = (array)($authComparison['shell_source_trace'] ?? []);
      $ledgerSummary = [
          'known_cases' => count($authEvidenceLedger),
          'aligned' => 0,
          'mismatch' => 0,
          'unknown' => 0,
          'observed' => 0,
          'basis' => [],
      ];
      foreach ($authEvidenceLedger as $ledgerRow) {
          if (!is_array($ledgerRow)) { continue; }
          $ledgerStatus = (string)($ledgerRow['status'] ?? 'unknown');
          if ($ledgerStatus === 'aligned') {
              $ledgerSummary['aligned']++;
          } elseif ($ledgerStatus === 'mismatch') {
              $ledgerSummary['mismatch']++;
          } else {
              $ledgerSummary['unknown']++;
          }
          if (!empty($ledgerRow['rendered_auth_response_observed'])) {
              $ledgerSummary['observed']++;
          }
          $rowBasis = (string)($ledgerRow['evidence_basis'] ?? '');
          if ($rowBasis !== '' && !in_array($rowBasis, $ledgerSummary['basis'], true)) {
              $ledgerSummary['basis'][] = $rowBasis;
          }
      }
      ?>

      <p class="se-muted"><?= e($se('auth_comparison_source_label')) ?></p>
      <dl class="se-summary">
        <div class="se-metric">
          <dt><?= e($se('auth_comparison_legacy_mode')) ?></dt>
          <dd class="se-code"><?= e($legacyMode ?: $se('empty')) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_comparison_shell_mode')) ?></dt>
          <dd class="se-code"><?= e($shellMode ?: $se('empty')) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_comparison_status')) ?></dt>
          <dd><span class="se-badge <?= e($compBadgeClass) ?>"><?= e($compStatusLabel) ?></span></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_comparison_comparison_id')) ?></dt>
          <dd class="se-code"><?= e($compId ?: $se('empty')) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_comparison_evidence_basis')) ?></dt>
          <dd><?= e($evidenceBasis ?: $se('empty')) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_comparison_browser_override_excluded')) ?></dt>
          <dd><?= !$browserOverride ? e($se('yes')) : e($se('no')) ?></dd>
        </div>
      </dl>

      <?php if ($reason !== ''): ?>
        <p><strong><?= e($se('auth_comparison_reason')) ?>:</strong> <?= e($reason) ?></p>
      <?php endif; ?>

      <dl class="se-summary" style="margin-top:0.7rem;">
        <div class="se-metric">
          <dt><?= e($se('auth_comparison_cutover_status')) ?></dt>
          <dd><?= e($cutoverStatus === 'not_ready' ? $se('auth_comparison_not_ready') : $cutoverStatus) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_comparison_diagnostic_only')) ?></dt>
          <dd><?= $diagnosticOnly ? e($se('yes')) : e($se('no')) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_comparison_browser_override')) ?></dt>
          <dd><?= $browserOverride ? e($se('yes')) : e($se('no')) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_comparison_browser_state')) ?></dt>
          <dd><?= $browserStateClaimed ? e($se('yes')) : e($se('no')) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_comparison_reader_id')) ?></dt>
          <dd class="se-code"><?= e($readerId ?: $se('empty')) ?></dd>
        </div>
      </dl>

      <h4><?= e($se('auth_ledger_title')) ?></h4>
      <p class="se-muted"><?= e($se('auth_ledger_desc')) ?></p>
      <dl class="se-summary">
        <div class="se-metric">
          <dt><?= e($se('auth_ledger_known_cases')) ?></dt>
          <dd><?= e((string)$ledgerSummary['known_cases']) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_ledger_aligned')) ?></dt>
          <dd><?= e((string)$ledgerSummary['aligned']) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_ledger_mismatch')) ?></dt>
          <dd><?= e((string)$ledgerSummary['mismatch']) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_ledger_unknown')) ?></dt>
          <dd><?= e((string)$ledgerSummary['unknown']) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_ledger_observed_responses')) ?></dt>
          <dd><?= e((string)$ledgerSummary['observed']) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_ledger_evidence_basis')) ?></dt>
          <dd class="se-code"><?= e($ledgerSummary['basis'] !== [] ? implode('|', $ledgerSummary['basis']) : $se('empty')) ?></dd>
        </div>
        <div class="se-metric">
          <dt><?= e($se('auth_comparison_browser_override_excluded')) ?></dt>
          <dd><?= !$browserOverride ? e($se('yes')) : e($se('no')) ?></dd>
        </div>
      </dl>

      <details class="se-details">
        <summary><?= e($se('auth_ledger_all_cases')) ?></summary>
        <div class="se-table-wrap">
          <table class="se-table">
            <thead>
              <tr>
                <th><?= e($se('auth_ledger_case_id')) ?></th>
                <th><?= e($se('auth_ledger_auth_mode')) ?></th>
                <th><?= e($se('auth_ledger_shell_mode')) ?></th>
                <th><?= e($se('auth_ledger_status')) ?></th>
                <th><?= e($se('auth_ledger_fallback')) ?></th>
                <th><?= e($se('auth_ledger_evidence_basis')) ?></th>
                <th><?= e($se('auth_ledger_source_status')) ?></th>
                <th><?= e($se('auth_comparison_comparison_id')) ?></th>
                <th><?= e($se('auth_ledger_reason')) ?></th>
                <th><?= e($se('auth_comparison_blockers')) ?></th>
                <th><?= e($se('auth_ledger_observed_flag')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($authEvidenceLedger as $ledgerRow): ?>
                <?php if (!is_array($ledgerRow)) { continue; } ?>
                <?php $fallback = isset($ledgerRow['fallback_evidence']) && is_array($ledgerRow['fallback_evidence']) ? $ledgerRow['fallback_evidence'] : []; ?>
                <tr>
                  <td class="se-code"><?= e((string)($ledgerRow['case_id'] ?? '')) ?></td>
                  <td class="se-code"><?= e((string)($ledgerRow['auth_source_contract_mode'] ?? $se('empty'))) ?></td>
                  <td class="se-code"><?= e((string)($ledgerRow['shell_server_intent_mode'] ?? $se('empty'))) ?></td>
                  <td><?= e((string)($ledgerRow['status'] ?? 'unknown')) ?></td>
                  <td><?= e((string)($fallback['status'] ?? 'unknown')) ?>: <?= e((string)($fallback['reason'] ?? '')) ?></td>
                  <td><?= e((string)($ledgerRow['evidence_basis'] ?? '')) ?></td>
                  <td><?= e((string)($ledgerRow['source_evidence_status'] ?? 'unknown')) ?></td>
                  <td class="se-code"><?= e((string)($ledgerRow['comparison_id'] ?? '')) ?></td>
                  <td><?= e((string)($ledgerRow['reason'] ?? '')) ?></td>
                  <td><?= e(implode('; ', (array)($ledgerRow['cutover_blockers'] ?? []))) ?></td>
                  <td><?= !empty($ledgerRow['rendered_auth_response_observed']) ? e($se('yes')) : e($se('no')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>

      <details class="se-details">
        <summary><?= e($se('auth_ledger_raw_json')) ?></summary>
        <pre class="se-code"><?= e(json_encode($authEvidenceLedger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]') ?></pre>
      </details>

      <details class="se-details">
        <summary><?= e($se('auth_comparison_legacy_trace')) ?></summary>
        <pre class="se-code"><?= e(json_encode($legacyTrace, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]') ?></pre>
      </details>

      <details class="se-details">
        <summary><?= e($se('auth_comparison_shell_trace')) ?></summary>
        <pre class="se-code"><?= e(json_encode($shellTrace, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]') ?></pre>
      </details>

      <details class="se-details">
        <summary><?= e($se('auth_comparison_mismatch_details')) ?></summary>
        <?php if ($mismatchFields !== []): ?>
          <div class="se-table-wrap">
            <table class="se-table">
              <thead>
                <tr>
                  <th><?= e($se('auth_comparison_mismatch_field')) ?></th>
                  <th><?= e($se('auth_comparison_legacy_value')) ?></th>
                  <th><?= e($se('auth_comparison_shell_value')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($mismatchFields as $mf): ?>
                  <?php if (!is_array($mf)) { continue; } ?>
                  <tr>
                    <td class="se-code"><?= e((string)($mf['field'] ?? '')) ?></td>
                    <td class="se-code"><?= e((string)($mf['legacy_value'] ?? '')) ?></td>
                    <td class="se-code"><?= e((string)($mf['shell_value'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="se-muted"><?= e($se('auth_comparison_no_mismatch')) ?></p>
        <?php endif; ?>
      </details>

      <details class="se-details">
        <summary><?= e($se('auth_comparison_full_contract')) ?></summary>
        <pre class="se-code"><?= e(json_encode($authComparison, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
      </details>

      <details class="se-details">
        <summary><?= e($se('auth_comparison_blockers')) ?></summary>
        <pre class="se-code"><?= e(json_encode($cutoverBlockers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]') ?></pre>
      </details>

      <details class="se-details">
        <summary><?= e($se('auth_comparison_inventory')) ?></summary>
        <p class="se-muted"><?= e($se('auth_comparison_inventory_desc')) ?></p>
        <pre class="se-code"><?= e(json_encode($authComparisonInventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]') ?></pre>
      </details>
    <?php endif; ?>
  </section>

  <section class="se-panel">
    <h3><?= e($se('future_controls')) ?></h3>
    <div class="se-table-wrap">
      <table class="se-table">
        <thead>
          <tr>
            <th><?= e($se('control')) ?></th>
            <th><?= e($se('scope')) ?></th>
            <th><?= e($se('authority')) ?></th>
            <th><?= e($se('fallback')) ?></th>
            <th><?= e($se('persistence_owner')) ?></th>
            <th><?= e($se('enabled_now')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($futureControls as $control): ?>
            <?php if (!is_array($control)) { continue; } ?>
            <tr>
              <td><?= e((string)($control['label'] ?? '')) ?></td>
              <td><?= e((string)($control['scope'] ?? '')) ?></td>
              <td><?= e((string)($control['authority_requirement'] ?? '')) ?></td>
              <td><?= e((string)($control['fallback_behavior'] ?? '')) ?></td>
              <td><?= e((string)($control['persistence_owner'] ?? '')) ?></td>
              <td><?= !empty($control['enabled_now']) ? e($se('yes')) : e($se('not_enabled')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <details class="se-details">
    <summary><?= e($se('resolver_details')) ?></summary>
    <pre class="se-code"><?= e(json_encode($runtime, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
  </details>

  <details class="se-details">
    <summary><?= e($se('safety_contract')) ?></summary>
    <pre class="se-code"><?= e(json_encode($safety, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
  </details>
</section>

<script>
(function() {
  'use strict';
  var stateEl = document.getElementById('shell-observer-state');
  var modeEl = document.getElementById('shell-observer-mode');
  var paletteEl = document.getElementById('shell-observer-palette');
  var profileEl = document.getElementById('shell-observer-profile');
  var driftEl = document.getElementById('shell-observer-drift');
  var attrsEl = document.getElementById('shell-observer-attrs');
  if (!stateEl) { return; }

  <?php
  $shellCombinedMode = isset($shellState['server_resolved_intent']['combined_mode']) ? $shellState['server_resolved_intent']['combined_mode'] : '';
  $normKnownModes = isset($browserNormMap['known_modes']) && is_array($browserNormMap['known_modes']) ? $browserNormMap['known_modes'] : [];
  $normShorthand = isset($browserNormMap['shorthand_map']) && is_array($browserNormMap['shorthand_map']) ? $browserNormMap['shorthand_map'] : [];
  ?>
  var resolverMode = <?= json_encode($shellCombinedMode) ?>;
  var KNOWN_MODES = <?= json_encode($normKnownModes) ?>;
  var SHORTHAND_MAP = <?= json_encode($normShorthand) ?>;

  function observe() {
    var storage = null;
    try { storage = window.localStorage; } catch(e) { /* noop */ }
    var lsMode = storage ? storage.getItem('erp-theme-preference') : null;
    if (lsMode && KNOWN_MODES.indexOf(lsMode) === -1) { lsMode = null; }

    var htmlEl = document.documentElement;
    var dm = htmlEl.getAttribute('data-theme-mode') || '';
    var dt = htmlEl.getAttribute('data-theme') || '';
    var dcs = htmlEl.getAttribute('data-color-style') || '';
    var dtp = htmlEl.getAttribute('data-theme-preference') || '';
    var activeMode = lsMode || dtp || dt || dm || '';

    var normalized = activeMode;
    if (normalized && KNOWN_MODES.indexOf(normalized) === -1) {
      if (SHORTHAND_MAP[normalized] !== undefined) {
        normalized = SHORTHAND_MAP[normalized];
      }
    }
    if (!normalized || KNOWN_MODES.indexOf(normalized) === -1) { normalized = ''; }

    stateEl.textContent = lsMode ? 'localStorage override active: ' + lsMode : (normalized ? 'Observed from document attributes' : 'No appearance state found');
    if (normalized) {
      var parts = normalized.split('-');
      modeEl.textContent = normalized;
      paletteEl.textContent = parts[0] || 'system';
      profileEl.textContent = (parts.slice(1).join('-') || 'liquid-glass').replace(/-/g, '_');
    } else {
      modeEl.textContent = '<?= $se('shell_no_observer_data') ?>';
      paletteEl.textContent = '<?= $se('shell_no_observer_data') ?>';
      profileEl.textContent = '<?= $se('shell_no_observer_data') ?>';
    }

    if (resolverMode && normalized) {
      driftEl.textContent = (resolverMode === normalized) ? '<?= $se('shell_drift_consistent') ?>' : '<?= $se('shell_drift_inconsistent') ?>';
    } else if (normalized) {
      driftEl.textContent = '<?= $se('shell_drift_resolved') ?>';
    } else {
      driftEl.textContent = '<?= $se('shell_drift_not_observable') ?>';
    }

    var observed = [];
    if (dm) { observed.push('data-theme-mode=' + dm); }
    if (dt) { observed.push('data-theme=' + dt); }
    if (dcs) { observed.push('data-color-style=' + dcs); }
    if (dtp) { observed.push('data-theme-preference=' + dtp); }
    if (lsMode) { observed.push('localStorage:erp-theme-preference=' + lsMode); }
    attrsEl.textContent = observed.length ? observed.join(', ') : '<?= $se('shell_no_observer_data') ?>';
  }

  observe();
})();
</script>
