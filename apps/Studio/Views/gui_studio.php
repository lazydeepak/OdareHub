<?php
$showLegacyStudioWorkbench = isset($_GET['legacy_studio']) && $_GET['legacy_studio'] === '1';
$csrf = (string)($csrf ?? '');
$flash = trim((string)($flash ?? ''));
$error = trim((string)($error ?? ''));
$result = isset($result) && is_array($result) ? $result : null;
$inputs = isset($inputs) && is_array($inputs) ? $inputs : [];
$templateLibrary = isset($templateLibrary) && is_array($templateLibrary) ? array_values(array_filter($templateLibrary, 'is_array')) : [];
$draftLifecycle = isset($draftLifecycle) && is_array($draftLifecycle) ? array_values(array_filter($draftLifecycle, 'is_array')) : [];
$studioProject = isset($studioProject) && is_array($studioProject) ? $studioProject : [];
$appLifecycleEntries = isset($appLifecycleEntries) && is_array($appLifecycleEntries)
    ? array_values(array_filter($appLifecycleEntries, 'is_array'))
    : \Apps\Studio\Services\GuiStudioService::generatedAppLifecycleEntries();
$generatedLibraryEntries = isset($generatedLibraryEntries) && is_array($generatedLibraryEntries)
  ? array_values(array_filter($generatedLibraryEntries, 'is_array'))
  : \Apps\Studio\Services\GuiStudioService::listGeneratedAppsWithModules();
$libraryInspectorBundle = isset($libraryInspectorBundle) && is_array($libraryInspectorBundle) ? $libraryInspectorBundle : null;
$studioMode = trim((string)($studioMode ?? 'create_new'));
$globalLibrary = isset($globalLibrary) && is_array($globalLibrary) ? $globalLibrary : [];
$globalLibraryTree = isset($globalLibrary['tree']) && is_array($globalLibrary['tree'])
    ? array_values(array_filter($globalLibrary['tree'], 'is_array'))
    : [];
$globalLibraryCounts = isset($globalLibrary['counts']) && is_array($globalLibrary['counts'])
    ? $globalLibrary['counts']
    : [];
$globalLibrarySelected = isset($globalLibrarySelected) && is_array($globalLibrarySelected) ? $globalLibrarySelected : null;
$globalLibrarySelectedId = trim((string)($globalLibrarySelectedId ?? ($globalLibrarySelected['id'] ?? '')));
$studioDataContract = isset($studioDataContract) && is_array($studioDataContract) ? $studioDataContract : [];
$studioDataChecks = isset($studioDataContract['checks']) && is_array($studioDataContract['checks'])
  ? array_values(array_filter($studioDataContract['checks'], 'is_array'))
  : [];
$studioDependencyGraph = isset($studioDependencyGraph) && is_array($studioDependencyGraph) ? $studioDependencyGraph : [];
$studioGraphEdges = isset($studioDependencyGraph['edges']) && is_array($studioDependencyGraph['edges'])
  ? array_values(array_filter($studioDependencyGraph['edges'], 'is_array'))
  : [];
$studioToolGroups = \Apps\Studio\Services\StudioToolCatalogService::listToolsByGroup();
$studioToolGroupOrder = \Apps\Studio\Services\StudioToolCatalogService::groupOrder();
$studioDefaultPreviewTool = \Apps\Studio\Services\StudioToolCatalogService::findTool('resource_explorer');
$studioDefaultPreviewNameKey = (string)($studioDefaultPreviewTool['name_key'] ?? 'studio_tool_resource_explorer');
$studioDefaultPreviewGroupKey = (string)($studioDefaultPreviewTool['group_key'] ?? 'studio_tools_group_explore');
$studioDefaultPreviewStatusKey = (string)($studioDefaultPreviewTool['status_key'] ?? 'studio_tools_status_available');
$studioDefaultPreviewPurposeKey = (string)($studioDefaultPreviewTool['purpose_key'] ?? 'studio_tool_purpose_resource_explorer');
$studioDefaultPreviewWorksOnKey = (string)($studioDefaultPreviewTool['works_on_key'] ?? 'studio_tools_boundary_owner_resources');
$studioDefaultPreviewMustNotOwnKey = (string)($studioDefaultPreviewTool['must_not_own_key'] ?? 'studio_tools_boundary_not_owner');
$studioDefaultPreviewFirstSafeKey = (string)($studioDefaultPreviewTool['first_safe_key'] ?? 'studio_tools_preview_first_safe_linked');
$studioDefaultPreviewBackendKey = (string)($studioDefaultPreviewTool['backend_key'] ?? 'studio_tools_preview_backend_linked');

$gs = static function (string $key, array $params = []): string {
    $lang = function_exists('current_lang') ? current_lang() : 'en';
    $dict = [
        'en' => [
            'title' => 'ERP App Studio',
            'subtitle' => 'Govern app, module, view, navigation, template, and package drafts before any publish work exists.',
            'compat' => 'Compatibility route remains /apps/studio.',
            'capabilities' => 'Foundation Scope',
            'cap.apps' => 'Create app manifest drafts for System Apps and Business Apps.',
            'cap.modules' => 'Create module manifest drafts for CRUD, dashboard, and queue/workflow modules.',
            'cap.views' => 'Create view manifest drafts for table, form, and detail surfaces.',
            'cap.nav' => 'Create navigation manifest drafts with wrapper confinement.',
            'cap.templates' => 'Manage non-executing template registry placeholders.',
            'cap.packages' => 'Prepare package artifact plans without writing package files.',
            'cap.validate' => 'Validate before publish governance; publish stays locked.',
            'projects' => 'Studio Projects',
            'project.note' => 'Project storage is session-only in this phase. No database rows are created.',
            'template_library' => 'Template Library',
            'drafts' => 'Drafts',
            'validation' => 'Validation',
            'compile_plan' => 'Compile Plan',
            'compile_graph' => 'Compile Graph',
            'artifact_types' => 'Artifact Types',
            'dependency_checks' => 'Dependency Checks',
            'conflict_checks' => 'Conflict Checks',
            'diff_readiness' => 'Diff Readiness',
            'diff_preview' => 'Diff Preview',
            'approval_gate' => 'Approval Gate',
            'approval_disabled' => 'Approval Disabled',
            'approval_ack_required' => 'Requires acknowledgment',
            'approval_ready' => 'Ready for approval',
            'approval_summary' => 'Approval Summary',
            'approval_result' => 'Approval Result',
            'validation_errors' => 'Validation Errors',
            'payload_preview' => 'Payload Preview',
            'decision' => 'Decision',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'reason' => 'Reason',
            'risk_acknowledged' => 'Risk Acknowledged',
            'risk_ack_label' => 'I acknowledge high-risk changes',
            'preview_approval' => 'Preview Approval',
            'high_risk_items' => 'High Risk Items',
            'blocked_items' => 'Blocked Items',
            'valid' => 'Valid',
            'invalid' => 'Invalid',
            'approval_id' => 'Approval ID',
            'approved_by' => 'Approved By',
            'approved_at' => 'Approved At',
            'snapshot_preview' => 'Snapshot Preview',
            'snapshot_ready' => 'Snapshot ready',
            'snapshot_disabled' => 'Snapshot disabled',
            'integrity_verified' => 'Integrity verified',
            'snapshot_unavailable' => 'Snapshot cannot be created until approval is valid',
            'preview_snapshot' => 'Preview Snapshot',
            'snapshot_id' => 'Snapshot ID',
            'snapshot_hash' => 'Snapshot Hash',
            'snapshot_integrity' => 'Snapshot Integrity',
            'snapshot_artifacts' => 'Snapshot Artifacts',
            'hash_verified' => 'Hash Verified',
            'approved' => 'Approved',
            'execution_preview' => 'Execution Preview',
            'preview_execution' => 'Preview Execution',
            'execution_ready' => 'Execution Ready',
            'execution_blocked' => 'Execution Blocked',
            'execution_simulated' => 'Simulation Complete',
            'execution_unavailable' => 'Execution blocked: approval not valid or blocked items present',
            'execution_summary' => 'Execution Summary',
            'execution_results' => 'Execution Results',
            'execution_id' => 'Execution ID',
            'can_execute' => 'Can Execute',
            'action' => 'Action',
            'ok' => 'OK',
            'skipped' => 'Skipped',
            'blocked' => 'Blocked',
            'reason_label' => 'Reason',
            'filters' => 'Filters',
            'risk_filter' => 'Risk',
            'ownership_filter' => 'Ownership',
            'drift_filter' => 'Drift',
            'filter_all' => 'All',
            'ownership_governance' => 'Ownership Governance',
            'drift_status' => 'Drift Status',
            'risk_escalation' => 'Risk Escalation',
            'human_diff_summary' => 'Human Diff Summary',
            'compile_snapshot_identity' => 'Compile Snapshot Identity',
            'compile_lineage' => 'Compile Lineage',
            'publish_governance' => 'Publish Governance',
            'rollback' => 'Rollback',
            'locked_future' => 'locked / future',
            'future' => 'future',
            'implemented' => 'implemented placeholder',
            'status' => 'Status',
            'category' => 'Category',
            'outputs' => 'Outputs',
            'guardrails' => 'Guardrails',
            'form_title' => 'Draft Bundle Editor',
            'app_manifest' => 'App Manifest JSON',
            'module_manifest' => 'Module Manifest JSON',
            'view_manifest' => 'View Manifest JSON',
            'navigation_manifest' => 'Navigation Manifest JSON',
            'validate' => 'Validate',
            'compile' => 'Compile Plan',
            'results' => 'Validation Result',
            'check' => 'Check',
            'detail' => 'Detail',
            'pass' => 'Pass',
            'fail' => 'Fail',
            'errors' => 'Errors',
            'target_path' => 'Target Path',
            'artifact_id' => 'Artifact ID',
            'artifact_type' => 'Artifact Type',
            'layer' => 'Layer',
            'change_type' => 'Change Type',
            'risk_level' => 'Risk',
            'dependencies' => 'Dependencies',
            'target_exists' => 'Target Exists',
            'diff_status' => 'Diff Status',
            'before_hash' => 'Before Hash',
            'after_hash' => 'After Hash',
            'source_template' => 'Source Template',
            'content_hash' => 'Content Hash',
            'generated_by' => 'Generated By',
            'studio_project_id' => 'Studio Project',
            'studio_draft_id' => 'Studio Draft',
            'ownership_scope' => 'Ownership Scope',
            'upgrade_safe' => 'Upgrade Safe',
            'customization_zone' => 'Customization Zone',
            'diff_summary' => 'Diff Summary',
            'compile_id' => 'Compile ID',
            'bundle_hash' => 'Bundle Hash',
            'artifact_count' => 'Artifacts',
            'dependency_check_count' => 'Dependency Checks',
            'conflict_check_count' => 'Conflict Checks',
            'drift_check_count' => 'Drift Checks',
            'generated_at' => 'Generated At',
            'parent_compile_id' => 'Parent Compile',
            'schema_version' => 'Schema',
            'unique_conflicts' => 'Unique Conflicts',
            'total_conflict_instances' => 'Conflict Instances',
            'artifact_name' => 'Artifact',
            'owner' => 'Owner',
            'risk_score' => 'Risk Score',
            'dominant_reason' => 'Dominant Reason',
            'summary_group' => 'Summary Group',
            'bool.true' => 'true',
            'bool.false' => 'false',
            'summary' => 'Summary',
            'check.no_php.ok' => 'Draft bundle contains no PHP code.',
            'check.no_php.invalid' => 'Draft bundle cannot contain PHP code.',
            'error.no_php' => 'Arbitrary PHP/code editing is not allowed in Studio drafts.',
            'flash.compile.ready' => 'Compile plan generated.',
            'flash.compile.blocked' => 'Compile plan blocked until validation passes.',
            'flash.approval.ready' => 'Approval preview generated.',
            'flash.snapshot.ready' => 'Snapshot preview generated.',
            'flash.execution.ready' => 'Execution simulation generated.',
            'flash.apply.applied' => 'Apply completed. Generated module created.',
            'flash.apply.failed' => 'Apply failed. Preconditions not met or artifact blocked.',
            'approval.error.invalid_decision' => 'Approval decision must be approved or rejected.',
            'approval.error.blocked_present' => 'Approval is disabled while blocked artifacts are present.',
            'approval.error.risk_ack_required' => 'High-risk artifacts require acknowledgment.',
            'approval.error.reason_required' => 'High-risk approval requires a reason.',
            'apply_snapshot' => 'Apply Snapshot',
            'apply_snapshot_title' => 'Apply Snapshot',
            'apply_status' => 'Apply Status',
            'apply_id' => 'Apply ID',
            'apply_results' => 'Apply Results',
            'apply_applied' => 'Applied',
            'apply_failed' => 'Failed',
            'apply_blocked' => 'Blocked: approval or preconditions not met',
            'apply_unavailable' => 'Apply is not available until execution simulation passes',
            'apply_btn' => 'Apply Snapshot',
            'apply_disabled_label' => 'Apply Disabled',
            'precondition_failures' => 'Precondition Failures',
            'precond.snapshot_already_applied' => 'This snapshot has already been applied. Duplicate apply blocked.',
            'precond.concurrent_apply_in_progress' => 'Another apply is already in progress for this snapshot. Try again shortly.',
            'precond.snapshot_not_found' => 'Stored snapshot was not found for this compile ID.',
            'precond.generator_mismatch' => 'Rollback blocked because the snapshot was created by a different generator.',
            'precond.invalid_snapshot_context' => 'Rollback blocked because the snapshot context is invalid.',
            'precond.unsafe_live_path' => 'Rollback blocked because the generated module path is unsafe.',
            'precond.unsafe_route_path' => 'Rollback blocked because the generated route path is unsafe.',
            'precond.no_compile_id' => 'Rollback requires a compile ID.',
            'safe_pipeline_analyze_required' => 'Analyze must complete successfully before publish gate checks can run.',
            'safe_pipeline_confirm_required' => 'Approval confirmation is required before apply can proceed.',
            'safe_pipeline_context_mismatch' => 'Current draft context no longer matches the approved gate context. Re-run Analyze and Publish Gate.',
            'publish_gate_token_missing' => 'Publish gate token is missing. Re-run Publish Gate Check.',
            'publish_gate_token_mismatch' => 'Publish gate token mismatch detected. Re-run Publish Gate Check.',
            'publish_decision_record_write_failed' => 'Publish decision could not be persisted. Try again and verify storage permissions.',
            'snapshot_persisted' => 'Snapshot Persisted',
            'post_publish_verification' => 'Post-Publish Verification',
            'verification_status' => 'Verification Status',
            'apply_mode_label' => 'Execution Mode',
            'apply_mode_simulation' => 'Simulation',
            'apply_mode_real' => 'Real',
            'apply_mode_first_apply' => 'First Apply',
            'apply_mode_disabled' => 'Disabled',
            'transaction_steps' => 'Transaction Steps',
            'step_result' => 'Step Result',
            'rollback_binding' => 'Rollback Binding',
            'rollback_binding_status' => 'Rollback Status',
            'rollback_bound' => 'Bound (not executed)',
            'rollback_preview' => 'Rollback Preview',
            'rollback_summary' => 'Rollback Summary',
            'planned_action' => 'Planned Action',
            'rollback_action' => 'Rollback Action',
            'reversible' => 'Reversible',
            'non_reversible_label' => 'Non-Reversible',
            'rollback_unavailable' => 'Rollback plan is not available. Generate a snapshot first.',
            'flash.rollback.ready' => 'Rollback preview computed. No changes have been made.',
            'rollback_execute_title' => 'Rollback Execution',
            'rollback_execute_btn' => 'Execute Rollback',
            'rollback_execute_status' => 'Rollback Execution Status',
            'rollback_execute_result' => 'Rollback Execution Result',
            'rollback_execute_steps' => 'Rollback Steps',
            'rollback_execute_no_binding' => 'No rollback binding found. A real apply record with a rollback binding is required.',
            'rollback_execute_all_non_reversible' => 'All steps are non-reversible. Rollback cannot proceed.',
            'rollback_execute_non_reversible_warning' => 'Warning: some steps are non-reversible and will be skipped.',
            'rollback_execute_completed' => 'Rollback completed.',
            'rollback_execute_failed' => 'Rollback failed. See step results.',
            'rollback_execute_message' => 'Message',
            'rollback_execute_apply_id_label' => 'Apply ID',
            'rollback_execute_compile_id_label' => 'Compile ID',
            'flash.rollback.executed' => 'Rollback executed successfully.',
            'flash.rollback.execute_failed' => 'Rollback execution failed. See step results.',
            'flash.rollback.blocked' => 'Rollback blocked. Check preconditions.',
            'history_link' => 'View History',
            'tab.edit' => 'Edit',
            'tab.analyze' => 'Analyze',
            'tab.changes' => 'Changes',
            'tab.apply' => 'Apply',
            'primary.edit_analyze' => 'Analyze',
            'primary.analyze_review_changes' => 'Review Changes',
            'primary.changes_proceed_apply' => 'Proceed to Apply',
            'primary.apply_apply_changes' => 'Apply Changes',
            'editor_editing' => 'EDITING',
            'editor_creating' => 'CREATING',
            'editor_safe_mode' => 'SAFE MODE',
            'studio_root_label' => 'Studio',
            'app_lifecycle_manager' => 'App Lifecycle Manager',
            'global_library_title' => 'Global System Library',
            'global_library_subtitle' => 'Task-first view and module discovery with optional system registry metadata.',
            'library_role_helper' => 'Library: find and load resources.',
            'global_library_tree' => 'Tree',
            'global_library_detail' => 'Selection Detail',
            'global_library_counts' => 'Entity Counts',
            'global_library_empty' => 'Global library scan returned no nodes.',
            'global_library_no_selection' => 'Select any node to inspect metadata.',
            'global_library_load_into_studio' => 'Load into Studio',
            'library_search_placeholder' => 'Search views, modules, routes...',
            'library_filter_group_label' => 'Library filters',
            'library_filter_views' => 'Views',
            'library_filter_routes' => 'Routes',
            'library_filter_modules' => 'Modules',
            'library_filter_apps' => 'Apps',
            'library_filter_recent' => 'Recent',
            'library_affordance_legend_label' => 'Library policy:',
            'library_affordance_legend_loadable' => 'Loadable in editor',
            'library_affordance_legend_loadable_kinds' => 'app, module, view, dashboard',
            'library_affordance_legend_inspect_only' => 'Inspect-only',
            'library_affordance_legend_inspect_only_kinds' => 'route, nav',
            'library_load_app' => 'Load App',
            'library_search_placeholder_full' => 'Search views, modules, apps...',
            'app_settings' => 'App Settings',
            'app_key_label' => 'App Key',
            'app_display_name_label' => 'App Display Name',
            'app_type_label' => 'App Type',
            'app_version_label' => 'Version',
            'mobile_mode_library' => 'Library',
            'mobile_mode_editor' => 'Editor',
            'mobile_mode_run' => 'Run',
            'library_advanced_registry_title' => 'Advanced Technical Registry',
            'library_detail_title' => 'Selection Detail',
            'library_detail_close' => 'Close',
            'library_detail_inspect_only_note' => 'Inspectable in Studio. Editor loading is not available for this artifact type yet ({kind}).',
            'library_detail_owner' => 'Owner',
            'library_detail_owner_path' => 'Owner Path',
            'library_detail_resource_type' => 'Resource Type',
            'library_detail_owner_unknown' => 'Unknown owner',
            'library_detail_tools_title' => 'Eligible Tools',
            'library_detail_tools_loading' => 'Loading Resource Explorer data...',
            'library_detail_tools_empty' => 'No tools are eligible for this resource type.',
            'library_detail_tools_unavailable' => 'Resource Explorer metadata is unavailable right now.',
            'library_recent_title' => 'Recent',
            'library_quick_load_title' => 'Quick Load',
            'library_quick_recent_views' => 'Recent Views',
            'library_quick_most_used_views' => 'Most used Views',
            'library_quick_last_edited_views' => 'Last edited Views',
            'content_outline_title' => 'Loaded Content',
            'content_outline_back' => 'Back to Library',
            'content_outline_empty' => 'Start from Library/Search to load a resource. Nothing is staged and no apply action is active.',
            'content_outline_summary' => 'Current content',
            'content_outline_module_settings' => 'Module settings',
            'content_outline_fields' => 'Fields',
            'content_outline_components' => 'Components',
            'content_outline_layout' => 'Layout',
            'content_outline_governance' => 'Governance',
            'content_outline_selected_component' => 'Selected component',
            'content_outline_change_summary' => 'Change summary',
            'content_outline_migration_plan' => 'Migration plan',
            'content_outline_impact_analysis' => 'Impact analysis',
            'content_outline_simulation_preview' => 'Simulation preview',
            'content_outline_table_mode' => 'Table edit mode',
            'content_outline_table_mode_direct_db' => 'Direct DB edits',
            'content_outline_table_mode_view_only' => 'View-driven edits',
            'content_outline_toggle_direct_db' => 'Switch to direct DB edits',
            'content_outline_toggle_view_only' => 'Switch to view-driven edits',
            'create_flow_title' => 'Create Flow',
            'create_flow_empty' => 'Follow these steps to build new content.',
            'editor_role_helper' => 'Editor: inspect the loaded resource.',
            'downstream_tabs_helper' => 'Analysis, Changes, and Apply become meaningful after a resource is loaded.',
            'create_flow_steps' => 'Creation Steps',
            'create_flow_intent_label' => 'Creation target',
            'create_intent_label' => 'What to create',
            'create_intent_app' => 'New app',
            'create_intent_module' => 'New module',
            'create_intent_view' => 'New view/page',
            'create_intent_navigation' => 'Navigation only',
            'create_intent_dashboard' => 'Dashboard/chart',
            'create_flow_step_intent' => 'Choose creation target',
            'create_flow_step_module' => 'Configure module',
            'create_flow_step_view' => 'Choose view type',
            'create_flow_step_table_mode' => 'Choose table edit mode',
            'create_flow_step_fields' => 'Define fields',
            'create_flow_step_layout' => 'Compose layout',
            'create_flow_step_governance' => 'Review governance',
            'create_flow_actions' => 'Quick Actions',
            'create_flow_action_configure_navigation' => 'Configure navigation',
            'create_flow_action_set_dashboard' => 'Set dashboard view type',
            'create_flow_action_add_table' => 'Add table component',
            'create_flow_action_add_form' => 'Add form component',
            'create_flow_action_add_kpi' => 'Add KPI component',
            'create_flow_action_add_text' => 'Add text component',
            'create_flow_action_direct_db' => 'Use direct DB table edits',
            'create_flow_action_view_mode' => 'Use view-driven table edits',
            'library_search_results_title' => 'Search Results',
            'library_search_results_empty' => 'No matching views found.',
            'library_load_table_only' => 'Load Table Only',
            'library_load_form_only' => 'Load Form Only',
            'library_all_views_title' => 'All Views',
            'library_all_modules_title' => 'All Modules',
            'library_system_registry_title' => 'System Registry (Advanced)',
            'advanced_debug_title' => 'Advanced / Debug',
            'advanced_debug_help' => 'JSON editors are available here for advanced inspection only.',
            'analyze_summary_title' => 'Quick Analysis Summary',
            'analyze_summary_structure_ok' => 'Structure OK',
            'analyze_summary_structure_pending' => 'Structure incomplete',
            'analyze_summary_missing_bindings' => 'Missing bindings',
            'analyze_summary_bindings_ok' => 'Bindings complete',
            'analyze_summary_blockers' => 'Current blockers',
            'analyze_show_technical_details' => '[Show Technical Details]',
            'apply_next_step_message' => 'Next step: Run Analyze before applying',
            'apply_next_step_ready_message' => 'Next step: Review changes and confirm approval before applying',
            'apply_step_1_edit' => 'Step 1: Edit',
            'apply_step_2_analyze' => 'Step 2: Analyze',
            'apply_step_3_changes' => 'Step 3: Changes',
            'apply_step_4_apply' => 'Step 4: Apply',
            'global_count_apps' => 'Apps',
            'global_count_modules' => 'Modules',
            'global_count_views' => 'Views',
            'global_count_dashboards' => 'Dashboards',
            'global_count_routes' => 'Routes',
            'global_count_navs' => 'Navigation',
            'global_count_plugins' => 'Plugins',
            'data_contract_title' => 'Data Contract',
            'data_contract_subtitle' => 'Extracted bindings, fields, and data sources for the selected Studio bundle.',
            'data_contract_confidence' => 'Confidence',
            'data_contract_fields' => 'Fields',
            'data_contract_bindings' => 'Bindings',
            'data_contract_sources' => 'Data Sources',
            'data_contract_required_fields' => 'Required Fields',
            'data_contract_empty' => 'No data contract was extracted yet.',
            'data_contract_checks' => 'Validation Checks',
            'data_contract_check_status' => 'Status',
            'data_contract_check_detail' => 'Detail',
            'data_contract_status.pass' => 'Pass',
            'data_contract_status.fail' => 'Fail',
            'data_contract_confidence.full' => 'full',
            'data_contract_confidence.partial' => 'partial',
            'data_contract_confidence.none' => 'none',
            'data_contract_check.bindings_extracted' => 'Bindings extracted',
            'data_contract_check.bindings_known' => 'Binding paths known',
            'data_contract_check.required_fields_resolved' => 'Required fields resolved',
            'data_contract_check.data_sources_declared' => 'Data sources declared',
            'dependency_graph_title' => 'Dependency Graph',
            'dependency_graph_subtitle' => 'Tracked relations from field to view to dashboard to workflow.',
            'dependency_graph_nodes' => 'Nodes',
            'dependency_graph_edges' => 'Edges',
            'dependency_graph_depends_on' => 'depends_on',
            'dependency_graph_affects' => 'affects',
            'dependency_graph_issues' => 'Graph Issues',
            'dependency_graph_none' => 'No dependency graph data is available yet.',
            'dependency_graph_edge_table' => 'Edge List',
            'dependency_graph_from' => 'From',
            'dependency_graph_to' => 'To',
            'dependency_graph_relation' => 'Relation',
            'generated_library_title' => 'Generated Apps Library',
            'library_publish_state' => 'Publish State',
            'library_publish_approved' => 'approved',
            'library_publish_pending' => 'pending',
            'library_empty' => 'No generated modules discovered in registry.',
            'library_app' => 'App',
            'library_module' => 'Module',
            'library_route' => 'Route',
            'library_version' => 'Version',
            'library_snapshot' => 'Snapshot',
            'library_last_updated' => 'Last Updated',
            'inspect' => 'Inspect',
            'load_into_studio' => 'Load into Studio',
            'import_existing_view' => 'Import Existing View',
            'library_loading' => 'Loading...',
            'library_load_failed' => 'Failed to load module into Studio.',
            'library_load_failed_with_reason' => 'Failed to load into Studio ({reason}).',
            'library_load_unsupported_kind' => 'This artifact type cannot be loaded in Phase 1A ({kind}).',
            'library_load_invalid_response' => 'Load endpoint returned an invalid response.',
            'library_import_partial' => 'Partial import loaded. Review inferred layout before reapply.',
            'library_import_complete' => 'Existing view imported into Studio.',
            'studio_mode_label' => 'Studio Mode',
            'studio_mode_create_new' => 'create_new',
            'studio_mode_edit_existing' => 'edit_existing',
            'studio_mode_switch_create' => 'Create Flow',
            'studio_mode_switch_upgrade' => 'Upgrade Flow',
            'tier_label' => 'Detail Level',
            'tier_simple' => 'Simple',
            'tier_guided' => 'Guided',
            'tier_advanced' => 'Advanced',
            'structured_editor_title' => 'Structured Module Editor',
            'structured_editor_note' => 'Edit module settings using form controls. JSON is generated internally and not directly editable.',
            'editor_title_create' => 'Create Bundle Editor',
            'editor_title_upgrade' => '{type} Upgrade Editor',
            'editor_note_create' => 'Edit module settings using form controls. JSON is generated internally and not directly editable.',
            'editor_note_upgrade' => 'Review and update loaded {type} content with form controls. Validate and analyze impact before apply.',
            'editor_note_upgrade_app' => 'Review app manifest scope, route ownership, and governance metadata before applying changes.',
            'editor_note_upgrade_module' => 'Update module settings and routes carefully. Validate downstream dependencies before apply.',
            'editor_note_upgrade_view' => 'Refine fields, bindings, and layout for the loaded view. Run analysis to confirm downstream safety.',
            'editor_note_upgrade_navigation' => 'Adjust navigation label, target, icon, and visibility carefully to avoid route leakage.',
            'simulation_broken_views' => 'Broken Views',
            'simulation_removed_fields' => 'Removed Fields',
            'simulation_new_fields' => 'New Fields',
            'simulation_warning' => 'Broken views detected. Apply requires explicit simulation override and reason.',
            'simulation_empty' => 'No simulation warnings detected.',
            'simulation_override_label' => 'Allow simulation override for broken views',
            'simulation_override_reason' => 'Simulation Override Reason',
            'simulation_reason_missing_required_field' => 'Missing required field in view',
            'simulation_reason_filter_references_removed_field' => 'Filter references removed field',
            'simulation_reason_navigation_invalid_route' => 'Navigation points to invalid route',
            'simulation.error.override_required' => 'Broken views require explicit simulation override.',
            'simulation.error.reason_required' => 'Broken views require a simulation override reason.',
            'error.upgrade_baseline_required' => 'Upgrade flow requires a loaded baseline before compile or apply.',
            'error.layout_invalid_type' => 'Layout type must be grid.',
            'error.layout_invalid_columns' => 'Layout must use 12 columns.',
            'error.layout_invalid_rows' => 'Layout rows must be defined.',
            'error.layout_missing_items' => 'Layout must contain at least one item.',
            'error.layout_invalid_item' => 'Layout contains an invalid item entry.',
            'error.layout_item_id_required' => 'Each layout item must have an id.',
            'error.layout_item_duplicate_id' => 'Layout item ids must be unique.',
            'error.layout_invalid_component' => 'Layout contains an unsupported component.',
            'error.layout_missing_component_props' => 'Layout component props are missing required values.',
            'error.layout_missing_data_binding' => 'Each layout item must define a data binding source.',
            'error.layout_invalid_data_binding' => 'Data binding path must use dot notation (example: module.fields.part_name).',
            'error.layout_binding_type_table' => 'Table component binding must be module.rows.',
            'error.layout_binding_type_filter' => 'Filter component binding must be module.rows.',
            'error.layout_binding_type_form' => 'Form component binding must be module.fields.',
            'error.layout_binding_type_kpi' => 'KPI component binding must be module.metrics.*.',
            'error.layout_binding_type_text' => 'Text component binding must be module.description.',
            'error.layout_invalid_group' => 'Each layout item must belong to a valid group.',
            'error.layout_invalid_relation' => 'Layout relations must reference valid source and target items.',
            'error.layout_out_of_bounds' => 'Layout items must stay within grid bounds.',
            'error.layout_overlap' => 'Layout items cannot overlap.',
            'app_registry_empty' => 'No generated apps are registered yet.',
            'current_version' => 'Current Version',
            'modules' => 'Modules',
            'enable' => 'Enable',
            'disable' => 'Disable',
            'uninstall' => 'Uninstall',
            'files_present' => 'Files Present',
            'lifecycle_result' => 'Lifecycle Result',
            'lifecycle_status_enabled' => 'enabled',
            'lifecycle_status_disabled' => 'disabled',
            'lifecycle_status_installed' => 'installed',
            'lifecycle_status_removed' => 'removed',
            'lifecycle.error.app_not_found' => 'App not found in registry.',
            'lifecycle.error.module_files_missing' => 'Cannot enable because module files are missing.',
            'lifecycle.error.rollback_in_progress' => 'Lifecycle action blocked while rollback is in progress.',
            'lifecycle.error.invalid_lifecycle_request' => 'Invalid lifecycle request.',
            'lifecycle.error.registry_write_failed' => 'Registry update failed.',
            'lifecycle.error.uninstall_blocked' => 'Uninstall blocked by safety checks.',
            'flash.lifecycle.updated' => 'App lifecycle updated.',
            'flash.lifecycle.failed' => 'App lifecycle update failed.',
            'governance_title' => 'Governance — G1–G4 No-Code Policy',
            'governance_policy' => 'Authoring Mode Policy',
            'governance_mode' => 'GUI-First',
            'preflight_title' => 'Preflight Checks (G2)',
            'preflight_btn' => 'Run Preflight',
            'preflight_ok' => 'All preflight checks passed.',
            'preflight_failed' => 'Preflight failed. Fix errors before compile.',
            'lint_title' => 'Lint Checks (G3)',
            'lint_ok' => 'Bundle lint passed.',
            'lint_failed' => 'Lint failed. Fix manifest structure errors.',
            'publish_gate_title' => 'Publish Gate (G4)',
            'publish_gate_btn' => 'Run Publish Gate Check',
            'publish_gate_ok' => 'All publish gates passed. Ready for approval.',
            'publish_gate_failed' => 'Publish gate failed. Resolve all gate errors.',
            'apply_mode_gate' => 'Apply Mode',
            'rollback_plan_title' => 'Rollback Plan',
            'rollback_plan_artifacts' => 'Artifacts in Rollback Plan',
            'rollback_reversible' => 'Reversible',
            'publish_decision_title' => 'Publish Decision Record',
            'publish_decision_by' => 'Decided By',
            'publish_decision_at' => 'Decided At',
            'publish_decision_allowed' => 'Publish Allowed',
            'audit_written' => 'Audit Snapshot Written',
            'audit_ok' => 'Audit snapshot saved to storage.',
            'audit_failed' => 'Audit snapshot could not be written.',
            'todo_title' => 'Workflow Checklist (G4)',
            'focused_plan_title' => 'Focused-Start Plan (G1)',
            'focused_plan_step' => 'Step',
            'focused_plan_gate' => 'Gate',
            'checkpoints_title' => 'Approval Checkpoints (G1)',
            'checkpoint_label' => 'Checkpoint',
            'checkpoint_stage' => 'Lifecycle Stage',
            'checkpoint_gates' => 'Required Gates',
            'flash.preflight.ok' => 'Preflight passed.',
            'flash.preflight.failed' => 'Preflight failed. Correct errors before proceeding.',
            'flash.publish_gate.ok' => 'Publish gate passed.',
            'flash.publish_gate.failed' => 'Publish gate failed. All gates must pass before publish.',
            'flash.library_bundle_loaded' => 'Generated module loaded into Studio editor.',
            'flash.library_bundle_missing' => 'Generated module not found or manifest is invalid.',
            'flash.import_loaded' => 'Existing view imported into Studio.',
            'flash.import_partial' => 'Partial import loaded. Review inferred layout before reapply.',
            'flash.import_missing' => 'Import failed. Existing view source is unavailable.',
            'pending' => 'Pending',
            'ready' => 'Ready',
            'fields' => 'Fields',
            'bindings' => 'Bindings',
            'sources' => 'Sources',
            'required' => 'Required',
            'nodes' => 'Nodes',
            'relations' => 'Relations',
            'dependencies' => 'Dependencies',
            'affected' => 'Affected',
            'impact_analysis_title' => 'Impact Analysis',
            'impact_analysis_subtitle' => 'Downstream impact on workflow, data, and dependency chains.',
            'impact_analysis_pending' => 'No downstream impact detected yet.',
            'changes_summary_title' => 'Change Summary',
            'changes_total_artifacts' => 'Total Artifacts',
            'changes_new_files' => 'New Files',
            'changes_modified_files' => 'Modified Files',
            'changes_routes_affected' => 'Routes Affected',
            'changes_views_affected' => 'Views Affected',
            'changes_artifacts_title' => 'Artifacts',
            'changes_preview_title' => 'Preview Changes',
            'changes_preview_subtitle' => 'Side-by-side comparison of before and after for key artifacts.',
            'changes_diff_summary' => 'Diff Summary',
            'changes_no_preview' => 'No diff preview available. Compile to generate changes.',
            'changes_no_data' => 'No changes are staged.',
            'read_only_analysis_badge' => 'Read-only analysis',
            'no_changes_staged_badge' => 'No changes staged',
            'loaded_owner_app_label' => 'Owner App',
            'loaded_module_label' => 'Module',
            'loaded_resource_type_label' => 'Resource Type',
            'loaded_resource_key_label' => 'Resource Key',
            'loaded_mode_label' => 'Mode',
            'loaded_source_path_label' => 'Source Path',
            'loaded_identity_title' => 'Loaded Resource Identity',
            'loaded_identity_helper' => 'Studio is inspecting owner-owned resources. Studio does not become the owner.',
            'clear_loaded_context_action' => 'Clear loaded context',
            'clear_loaded_context_helper' => 'Clears the current loaded-resource preview only. It does not delete files, records, or saved resources.',
            'not_loaded' => 'Not loaded',
            'workflow_status_title' => 'Workflow Status',
            'workflow_status_helper' => 'Validation, diff, preview, approval, and apply are governed steps. Nothing is changed until an approved apply action runs.',
            'workflow_stage_analyze' => 'Analyze',
            'workflow_stage_changes' => 'Changes / Diff',
            'workflow_stage_preview' => 'Preview',
            'workflow_stage_approval' => 'Approval',
            'workflow_stage_apply' => 'Apply',
            'workflow_state_load_before_analysis' => 'Load a resource before analysis.',
            'workflow_state_no_diff' => 'No diff is available.',
            'workflow_state_no_preview' => 'No preview is active.',
            'workflow_state_no_approval' => 'No approval is pending.',
            'workflow_state_no_apply' => 'No apply action is active.',
            'workflow_state_ready_readonly' => 'Ready for read-only inspection',
            'workflow_state_no_changes_staged' => 'No changes staged',
            'workflow_state_apply_inactive' => 'Apply is inactive',
            'mode_panel_title' => 'Mode',
            'mode_panel_helper' => 'Mode describes the intended work type. This view does not change resources until a governed apply action is approved.',
            'mode_read_only' => 'Read-only',
            'mode_create' => 'Create',
            'mode_edit' => 'Edit',
            'mode_upgrade' => 'Upgrade',
            'mode_state_active' => 'Active',
            'mode_state_planned' => 'Planned',
            'mode_state_not_active' => 'Not active',
            'mode_state_requires_governance' => 'Requires governed workflow',
            'mode_state_context_available' => 'Context available',
            'studio_tools_title' => 'Studio Workbench',
            'studio_tools_helper' => 'Studio is a governed workbench. These tools prepare, inspect, validate, and hand over owner-owned resources. Backend actions will be wired later through approved workflows.',
            'studio_tools_group_explore' => 'Explore',
            'studio_tools_group_build' => 'Build',
            'studio_tools_group_validate' => 'Validate',
            'studio_tools_group_govern' => 'Govern',
            'studio_tools_group_history' => 'History',
            'studio_tools_status_available' => 'Available',
            'studio_tools_status_read_only' => 'Read-only',
            'studio_tools_status_planned' => 'Planned',
            'studio_tools_status_requires_governed' => 'Requires governed workflow',
            'studio_tool_resource_explorer' => 'Resource Explorer / Library',
            'studio_tool_app_builder' => 'App Builder',
            'studio_tool_module_builder' => 'Module Builder',
            'studio_tool_view_layout_builder' => 'View / Layout Builder',
            'studio_tool_navigation_menu' => 'Navigation / Menu Tool',
            'studio_tool_widget_card_builder' => 'Widget / Card Builder',
            'studio_tool_report_builder' => 'Report Builder',
            'studio_tool_data_model_schema' => 'Data Model / DB Schema Tool',
            'studio_tool_validation_preview_center' => 'Validation / Preview Center',
            'studio_tool_approval_apply_center' => 'Approval / Apply Center',
            'studio_tool_change_history_snapshots' => 'Change History / Snapshots',
            'studio_tool_purpose_resource_explorer' => 'Discover existing owner resources and load inspectable context.',
            'studio_tool_purpose_app_builder' => 'Shape app-level draft structure and ownership metadata.',
            'studio_tool_purpose_module_builder' => 'Prepare module structure, contracts, and composition drafts.',
            'studio_tool_purpose_view_layout_builder' => 'Compose view and layout drafts before governed handoff.',
            'studio_tool_purpose_navigation_menu' => 'Draft route/menu exposure intent without changing runtime routing.',
            'studio_tool_purpose_widget_card_builder' => 'Prepare widget and card layouts for owner review.',
            'studio_tool_purpose_report_builder' => 'Draft report structure and export intent for governed review.',
            'studio_tool_purpose_data_model_schema' => 'Plan schema-level intent and impact before approved workflow.',
            'studio_tool_purpose_validation_preview_center' => 'Inspect validations and preview diffs in a read-only lane.',
            'studio_tool_purpose_approval_apply_center' => 'Review governed approval/apply state without direct runtime control.',
            'studio_tool_purpose_change_history_snapshots' => 'Review change lineage, snapshots, and audit history.',
            'studio_tools_boundary_owner_resources' => 'Works on owner-owned resources',
            'studio_tools_boundary_not_owner' => 'Does not own business modules',
            'studio_tools_boundary_no_runtime_without_apply' => 'No runtime changes until approved apply',
            'studio_tools_open_library' => 'Open Library',
            'studio_tools_open_history' => 'Open History',
            'studio_tools_preview_title' => 'Workbench Tool Preview',
            'studio_tools_preview_helper' => 'This preview explains Studio tool intent only. Tool execution will be wired later through governed workflows.',
            'studio_tools_preview_default' => 'Select a Studio tool to inspect its purpose and boundaries.',
            'studio_tools_preview_field_name' => 'Tool name',
            'studio_tools_preview_field_group' => 'Group',
            'studio_tools_preview_field_status' => 'Status',
            'studio_tools_preview_field_purpose' => 'Purpose',
            'studio_tools_preview_field_works_on' => 'Works on',
            'studio_tools_preview_field_must_not_own' => 'Must not own',
            'studio_tools_preview_field_first_safe' => 'First safe implementation',
            'studio_tools_preview_field_backend' => 'Backend wiring status',
            'studio_tools_preview_first_safe_readonly' => 'Read-only inspection and metadata review in Studio panels.',
            'studio_tools_preview_first_safe_linked' => 'Use the existing Studio page link for read-only context only.',
            'studio_tools_preview_first_safe_governed' => 'Enable through governed analyze/diff/approval/apply workflow only.',
            'studio_tools_preview_backend_unwired' => 'Not wired yet (intent preview only).',
            'studio_tools_preview_backend_linked' => 'Linked Studio page is available; execution workflows remain governed/unwired.',
            'studio_workbench_context_title' => 'Tool + Resource Preview',
            'studio_workbench_context_field_selected_tool' => 'Selected Tool',
            'studio_workbench_context_field_selected_resource' => 'Selected Resource',
            'studio_workbench_context_field_owner_app' => 'Owner App',
            'studio_workbench_context_field_module' => 'Module',
            'studio_workbench_context_field_resource_type' => 'Resource Type',
            'studio_workbench_context_field_action_state' => 'Action State',
            'studio_workbench_context_field_ownership_boundary' => 'Ownership Boundary',
            'studio_workbench_context_no_resource' => 'No resource loaded',
            'studio_workbench_context_no_tool' => 'No tool selected',
            'studio_workbench_context_action_preview_only' => 'Preview only',
            'studio_workbench_context_action_readonly_analysis' => 'Read-only analysis',
            'studio_workbench_context_action_no_apply' => 'No apply action active',
            'studio_workbench_context_boundary_text' => 'The selected tool may inspect owner-owned resources. Studio does not become the owner.',
            'studio_workbench_context_backend_planned' => 'Backend wiring: planned',
            'studio_workbench_context_backend_linked' => 'Backend wiring: linked page only',
            'studio_workbench_context_no_execution' => 'No tool execution is active',
            'loaded_mode_read_only' => 'Read-only',
            'loaded_mode_edit' => 'Edit',
            'loaded_mode_create' => 'Create',
            'loaded_mode_upgrade' => 'Upgrade',
            'artifacts' => 'artifacts',
            'created' => 'created',
            'modified' => 'modified',
            'routes' => 'routes',
            'views' => 'views',
            'artifact_name' => 'Artifact',
            'operation' => 'Operation',
            'operation.create' => 'Create',
            'operation.modify' => 'Modify',
            'operation.delete' => 'Delete',
            'status' => 'Status',
            'status.ready' => 'Ready',
            'status.pending' => 'Pending',
            'additions' => 'additions',
            'deletions' => 'deletions',
            'files_changed' => 'files changed',
            'apply_tab_title' => 'Apply Changes',
            'apply_tab_subtitle' => 'Execute only after pipeline gates pass and approval is confirmed.',
            'apply_pipeline_title' => 'Pipeline Status',
            'apply_pipeline_compile' => 'Compile',
            'apply_pipeline_analyze' => 'Analyze',
            'apply_pipeline_impact' => 'Impact',
            'apply_pipeline_risk_level' => 'Risk Level',
            'apply_pipeline_ok' => 'OK',
            'apply_pipeline_pending' => 'Pending',
            'apply_metadata_title' => 'Approval Metadata',
            'apply_confirmation_label' => 'I confirm these changes are ready to apply',
            'apply_gate_requirements_title' => 'Apply Gate Requirements',
            'apply_gate_requirements_analyze' => 'Analyze must be completed',
            'apply_gate_requirements_confirm' => 'Confirmation checkbox must be checked',
            'apply_gate_requirements_reason' => 'Approval reason is required',
            'apply_gate_failed_title' => 'Apply blocked by gate checks',
            'apply_gate_error_analyze' => 'Analyze has not been completed.',
            'apply_gate_error_confirm' => 'Confirmation is required before apply.',
            'apply_gate_error_reason' => 'Approval reason is required before apply.',
            'apply_gate_runtime_errors' => 'Apply is currently blocked by these gate checks:',
            'apply_changes_btn' => 'Apply Changes',
            'apply_no_data' => 'Compile and analyze a plan before applying changes.',
            'pipeline_risk_medium' => 'Medium',
          ],
          'ja' => [
            'title' => 'ERP App Studio',
            'subtitle' => '公開実装の前に、アプリ、モジュール、ビュー、ナビゲーション、テンプレート、パッケージ下書きを統制します。',
            'compat' => '互換ルートは /apps/studio のままです。',
            'capabilities' => '基盤スコープ',
            'cap.apps' => 'System Apps と Business Apps のアプリマニフェスト下書きを作成します。',
            'cap.modules' => 'CRUD、ダッシュボード、キュー/ワークフローモジュールの下書きを作成します。',
            'cap.views' => 'テーブル、フォーム、詳細サーフェスのビュー下書きを作成します。',
            'cap.nav' => 'ラッパー制約付きのナビゲーション下書きを作成します。',
            'cap.templates' => '実行しないテンプレートレジストリのプレースホルダーを管理します。',
            'cap.packages' => 'パッケージファイルを書き込まず、成果物プランのみ準備します。',
            'cap.validate' => '公開ガバナンス前に検証します。公開はロックされています。',
            'projects' => 'Studio Projects',
            'project.note' => 'このフェーズのプロジェクト保存はセッション内のみです。DB行は作成しません。',
            'template_library' => 'Template Library',
            'drafts' => 'Drafts',
            'validation' => 'Validation',
            'compile_plan' => 'Compile Plan',
            'compile_graph' => 'Compile Graph',
            'artifact_types' => 'Artifact Types',
            'dependency_checks' => 'Dependency Checks',
            'conflict_checks' => 'Conflict Checks',
            'diff_readiness' => 'Diff Readiness',
            'diff_preview' => 'Diff Preview',
            'approval_gate' => 'Approval Gate',
            'approval_disabled' => 'Approval Disabled',
            'approval_ack_required' => 'Requires acknowledgment',
            'approval_ready' => 'Ready for approval',
            'approval_summary' => 'Approval Summary',
            'approval_result' => 'Approval Result',
            'validation_errors' => 'Validation Errors',
            'payload_preview' => 'Payload Preview',
            'decision' => 'Decision',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'reason' => 'Reason',
            'risk_acknowledged' => 'Risk Acknowledged',
            'risk_ack_label' => 'I acknowledge high-risk changes',
            'preview_approval' => 'Preview Approval',
            'high_risk_items' => 'High Risk Items',
            'blocked_items' => 'Blocked Items',
            'valid' => 'Valid',
            'invalid' => 'Invalid',
            'approval_id' => 'Approval ID',
            'approved_by' => 'Approved By',
            'approved_at' => 'Approved At',
            'snapshot_preview' => 'Snapshot Preview',
            'snapshot_ready' => 'Snapshot ready',
            'snapshot_disabled' => 'Snapshot disabled',
            'integrity_verified' => 'Integrity verified',
            'snapshot_unavailable' => 'Snapshot cannot be created until approval is valid',
            'preview_snapshot' => 'Preview Snapshot',
            'snapshot_id' => 'Snapshot ID',
            'snapshot_hash' => 'Snapshot Hash',
            'snapshot_integrity' => 'Snapshot Integrity',
            'snapshot_artifacts' => 'Snapshot Artifacts',
            'hash_verified' => 'Hash Verified',
            'approved' => 'Approved',
            'execution_preview' => 'Execution Preview',
            'preview_execution' => 'Preview Execution',
            'execution_ready' => 'Execution Ready',
            'execution_blocked' => 'Execution Blocked',
            'execution_simulated' => 'Simulation Complete',
            'execution_unavailable' => 'Execution blocked: approval not valid or blocked items present',
            'execution_summary' => 'Execution Summary',
            'execution_results' => 'Execution Results',
            'execution_id' => 'Execution ID',
            'can_execute' => 'Can Execute',
            'action' => 'Action',
            'ok' => 'OK',
            'skipped' => 'Skipped',
            'blocked' => 'Blocked',
            'reason_label' => 'Reason',
            'filters' => 'フィルター',
            'risk_filter' => 'リスク',
            'ownership_filter' => '所有権',
            'drift_filter' => 'ドリフト',
            'filter_all' => 'すべて',
            'ownership_governance' => '所有権ガバナンス',
            'drift_status' => 'ドリフト状態',
            'risk_escalation' => 'リスク引き上げ',
            'human_diff_summary' => '人向けDiff概要',
            'compile_snapshot_identity' => 'Compile Snapshot識別子',
            'compile_lineage' => 'Compile Lineage',
            'publish_governance' => 'Publish Governance',
            'rollback' => 'Rollback',
            'locked_future' => 'locked / future',
            'future' => 'future',
            'implemented' => 'implemented placeholder',
            'status' => '状態',
            'category' => 'カテゴリ',
            'outputs' => '出力',
            'guardrails' => 'ガードレール',
            'form_title' => '下書きバンドルエディタ',
            'app_manifest' => 'アプリマニフェストJSON',
            'module_manifest' => 'モジュールマニフェストJSON',
            'view_manifest' => 'ビューマニフェストJSON',
            'navigation_manifest' => 'ナビゲーションマニフェストJSON',
            'validate' => '検証',
            'compile' => 'Compile Plan',
            'results' => '検証結果',
            'check' => 'チェック',
            'detail' => '詳細',
            'pass' => '合格',
            'fail' => '失敗',
            'errors' => 'エラー',
            'target_path' => '対象パス',
            'artifact_id' => '成果物ID',
            'artifact_type' => '成果物種別',
            'layer' => 'レイヤー',
            'change_type' => '変更種別',
            'risk_level' => 'リスク',
            'dependencies' => '依存関係',
            'target_exists' => '対象の存在',
            'diff_status' => 'Diff状態',
            'before_hash' => '変更前ハッシュ',
            'after_hash' => '変更後ハッシュ',
            'source_template' => '元テンプレート',
            'content_hash' => 'コンテンツハッシュ',
            'generated_by' => '生成元',
            'studio_project_id' => 'Studio Project',
            'studio_draft_id' => 'Studio Draft',
            'ownership_scope' => '所有権スコープ',
            'upgrade_safe' => 'アップグレード安全',
            'customization_zone' => 'カスタマイズゾーン',
            'diff_summary' => 'Diff概要',
            'compile_id' => 'Compile ID',
            'bundle_hash' => 'Bundle Hash',
            'artifact_count' => '成果物数',
            'dependency_check_count' => '依存チェック数',
            'conflict_check_count' => '競合チェック数',
            'drift_check_count' => 'ドリフトチェック数',
            'generated_at' => '生成時刻',
            'parent_compile_id' => 'Parent Compile',
            'schema_version' => 'Schema',
            'unique_conflicts' => '一意の競合',
            'total_conflict_instances' => '競合インスタンス',
            'artifact_name' => '成果物',
            'owner' => '所有者',
            'risk_score' => 'リスクスコア',
            'dominant_reason' => '主な理由',
            'summary_group' => '概要グループ',
            'bool.true' => 'true',
            'bool.false' => 'false',
            'summary' => '概要',
            'check.no_php.ok' => '下書きバンドルにPHPコードは含まれていません。',
            'check.no_php.invalid' => '下書きバンドルにPHPコードを含めることはできません。',
            'error.no_php' => 'Studio下書きで任意のPHP/コード編集は許可されていません。',
            'flash.compile.ready' => 'Compile Planを生成しました。',
            'flash.compile.blocked' => '検証に合格するまでCompile Planは作成できません。',
            'flash.approval.ready' => 'Approval Previewを生成しました。',
            'flash.snapshot.ready' => 'Snapshot Previewを生成しました。',
            'flash.execution.ready' => 'Execution simulationを生成しました。',
            'flash.apply.applied' => 'Apply完了。生成モジュールを作成しました。',
            'flash.apply.failed' => 'Apply失敗。事前条件未達またはartifactがブロックされています。',
            'approval.error.invalid_decision' => 'Approval decision must be approved or rejected.',
            'approval.error.blocked_present' => 'Blocked artifactsがあるためApprovalは無効です。',
            'approval.error.risk_ack_required' => 'High-risk artifactsにはacknowledgmentが必要です。',
            'approval.error.reason_required' => 'High-risk approvalにはreasonが必要です。',
            'apply_snapshot' => 'Apply Snapshot',
            'apply_snapshot_title' => 'Apply Snapshot',
            'apply_status' => 'Apply ステータス',
            'apply_id' => 'Apply ID',
            'apply_results' => 'Apply 結果',
            'apply_applied' => '適用済み',
            'apply_failed' => '失敗',
            'apply_blocked' => 'ブロック: 承認または事前条件未達',
            'apply_unavailable' => 'Execution simulationが通るまでApplyは利用できません',
            'apply_btn' => 'Apply Snapshot',
            'apply_disabled_label' => 'Apply 無効',
            'precondition_failures' => '事前条件の失敗',
            'precond.snapshot_already_applied' => 'このSnapshotはすでにApply済みです。重複Applyはブロックされました。',
            'precond.concurrent_apply_in_progress' => 'このSnapshotに対して別のApplyが進行中です。しばらくしてから再試行してください。',
            'precond.snapshot_not_found' => 'このCompile IDの保存済みSnapshotが見つかりません。',
            'precond.generator_mismatch' => 'Snapshotの生成元が異なるためRollbackをブロックしました。',
            'precond.invalid_snapshot_context' => 'Snapshotコンテキストが不正なためRollbackをブロックしました。',
            'precond.unsafe_live_path' => '生成モジュールパスが安全でないためRollbackをブロックしました。',
            'precond.unsafe_route_path' => '生成ルートパスが安全でないためRollbackをブロックしました。',
            'precond.no_compile_id' => 'RollbackにはCompile IDが必要です。',
            'safe_pipeline_analyze_required' => '公開ゲートチェックの前にAnalyzeを成功させる必要があります。',
            'safe_pipeline_confirm_required' => 'Applyを実行する前に承認確認が必要です。',
            'safe_pipeline_context_mismatch' => '現在のドラフト内容が承認済みゲート文脈と一致しません。Analyzeと公開ゲートチェックを再実行してください。',
            'publish_gate_token_missing' => '公開ゲートトークンが見つかりません。公開ゲートチェックを再実行してください。',
            'publish_gate_token_mismatch' => '公開ゲートトークンの不一致を検出しました。公開ゲートチェックを再実行してください。',
            'publish_decision_record_write_failed' => '公開判断の保存に失敗しました。再試行し、ストレージ権限を確認してください。',
            'snapshot_persisted' => 'Snapshot保存',
            'post_publish_verification' => '公開後検証',
            'verification_status' => '検証ステータス',
            'apply_mode_label' => '実行モード',
            'apply_mode_simulation' => 'シミュレーション',
            'apply_mode_real' => 'リアル',
            'apply_mode_first_apply' => 'First Apply',
            'apply_mode_disabled' => '無効',
            'transaction_steps' => 'トランザクションステップ',
            'step_result' => 'ステップ結果',
            'rollback_binding' => 'ロールバックバインディング',
            'rollback_binding_status' => 'ロールバックステータス',
            'rollback_bound' => 'バインド済み（未実行）',
            'rollback_preview' => 'ロールバックプレビュー',
            'rollback_summary' => 'ロールバックサマリー',
            'planned_action' => '予定アクション',
            'rollback_action' => 'ロールバックアクション',
            'reversible' => '元に戻せる',
            'non_reversible_label' => '元に戻せない',
            'rollback_unavailable' => 'ロールバック計画は利用できません。先にスナップショットを生成してください。',
            'flash.rollback.ready' => 'ロールバックプレビューを計算しました。変更は行われていません。',
            'rollback_execute_title' => 'ロールバック実行',
            'rollback_execute_btn' => 'ロールバックを実行',
            'rollback_execute_status' => 'ロールバック実行ステータス',
            'rollback_execute_result' => 'ロールバック実行結果',
            'rollback_execute_steps' => 'ロールバックステップ',
            'rollback_execute_no_binding' => 'ロールバックバインディングが見つかりません。ロールバックバインディングを持つ実行済みApplyレコードが必要です。',
            'rollback_execute_all_non_reversible' => '全ステップが元に戻せないため、ロールバックできません。',
            'rollback_execute_non_reversible_warning' => '警告: 一部のステップは元に戻せないためスキップされます。',
            'rollback_execute_completed' => 'ロールバックが完了しました。',
            'rollback_execute_failed' => 'ロールバックが失敗しました。ステップ結果を確認してください。',
            'rollback_execute_message' => 'メッセージ',
            'rollback_execute_apply_id_label' => 'Apply ID',
            'rollback_execute_compile_id_label' => 'Compile ID',
            'flash.rollback.executed' => 'ロールバックが正常に実行されました。',
            'flash.rollback.execute_failed' => 'ロールバック実行が失敗しました。ステップ結果を確認してください。',
            'flash.rollback.blocked' => 'ロールバックがブロックされました。事前条件を確認してください。',
            'history_link' => '履歴を見る',
            'tab.edit' => '編集',
            'tab.analyze' => '分析',
            'tab.changes' => '変更',
            'tab.apply' => '適用',
            'primary.edit_analyze' => '分析',
            'primary.analyze_review_changes' => '変更レビュー',
            'primary.changes_proceed_apply' => '適用へ進む',
            'primary.apply_apply_changes' => '変更を適用',
            'editor_editing' => '編集中',
            'editor_creating' => '作成中',
            'editor_safe_mode' => 'セーフモード',
            'studio_root_label' => 'スタジオ',
            'app_lifecycle_manager' => 'アプリライフサイクル管理',
            'global_library_title' => 'グローバルシステムライブラリ',
            'global_library_subtitle' => 'ビューとモジュールを優先し、必要時のみシステムレジストリを参照します。',
            'library_role_helper' => 'ライブラリ: リソースを探して読み込みます。',
            'global_library_tree' => 'ツリー',
            'global_library_detail' => '選択詳細',
            'global_library_counts' => 'エンティティ件数',
            'global_library_empty' => 'グローバルライブラリの対象が見つかりません。',
            'global_library_no_selection' => '任意のノードを選択してメタデータを確認してください。',
            'global_library_load_into_studio' => 'Studioで読み込む',
            'library_search_placeholder' => 'ビュー、モジュール、ルートを検索...',
            'library_filter_group_label' => 'ライブラリフィルター',
            'library_filter_views' => 'ビュー',
            'library_filter_routes' => 'ルート',
            'library_filter_modules' => 'モジュール',
            'library_filter_apps' => 'アプリ',
            'library_filter_recent' => '最近使った項目',
            'library_affordance_legend_label' => 'ライブラリポリシー:',
            'library_affordance_legend_loadable' => 'エディターで読み込み可能',
            'library_affordance_legend_loadable_kinds' => 'app, module, view, dashboard',
            'library_affordance_legend_inspect_only' => '閲覧のみ',
            'library_affordance_legend_inspect_only_kinds' => 'route, nav',
            'library_load_app' => 'アプリを読み込む',
            'library_search_placeholder_full' => 'ビュー、モジュール、アプリを検索...',
            'app_settings' => 'アプリ設定',
            'app_key_label' => 'アプリキー',
            'app_display_name_label' => 'アプリ表示名',
            'app_type_label' => 'アプリ種別',
            'app_version_label' => 'バージョン',
            'mobile_mode_library' => 'ライブラリ',
            'mobile_mode_editor' => 'エディタ',
            'mobile_mode_run' => '実行',
            'library_advanced_registry_title' => '技術レジストリ（詳細）',
            'library_detail_title' => '選択詳細',
            'library_detail_close' => '閉じる',
            'library_detail_inspect_only_note' => 'Studioでの参照は可能です。このアーティファクト種別はまだエディタ読込に対応していません（{kind}）。',
            'library_detail_owner' => '所有者',
            'library_detail_owner_path' => '所有者パス',
            'library_detail_resource_type' => 'リソース種別',
            'library_detail_owner_unknown' => '所有者未判定',
            'library_detail_tools_title' => '利用可能ツール',
            'library_detail_tools_loading' => 'Resource Explorer の情報を読み込み中...',
            'library_detail_tools_empty' => 'このリソース種別で使えるツールはありません。',
            'library_detail_tools_unavailable' => 'Resource Explorer のメタデータを取得できませんでした。',
            'library_recent_title' => '最近使った項目',
            'library_quick_load_title' => 'クイック読み込み',
            'library_quick_recent_views' => '最近のビュー',
            'library_quick_most_used_views' => 'よく使うビュー',
            'library_quick_last_edited_views' => '最終更新ビュー',
            'content_outline_title' => '読み込み済みコンテンツ',
            'content_outline_back' => 'ライブラリに戻る',
            'content_outline_empty' => 'ライブラリ/検索からリソースを読み込んでください。変更は未ステージで、適用操作は未アクティブです。',
            'content_outline_summary' => '現在のコンテンツ',
            'content_outline_module_settings' => 'モジュール設定',
            'content_outline_fields' => 'フィールド',
            'content_outline_components' => 'コンポーネント',
            'content_outline_layout' => 'レイアウト',
            'content_outline_governance' => 'ガバナンス',
            'content_outline_selected_component' => '選択中のコンポーネント',
            'content_outline_change_summary' => '変更サマリー',
            'content_outline_migration_plan' => '移行計画',
            'content_outline_impact_analysis' => '影響分析',
            'content_outline_simulation_preview' => 'シミュレーション結果',
            'content_outline_table_mode' => 'テーブル編集モード',
            'content_outline_table_mode_direct_db' => '直接DB編集',
            'content_outline_table_mode_view_only' => 'ビュー定義編集',
            'content_outline_toggle_direct_db' => '直接DB編集に切り替え',
            'content_outline_toggle_view_only' => 'ビュー定義編集に切り替え',
            'create_flow_title' => '作成フロー',
            'create_flow_empty' => '新しいコンテンツを作るために、以下の手順を進めてください。',
            'editor_role_helper' => 'エディター: 読み込まれたリソースを確認します。',
            'downstream_tabs_helper' => '分析・変更・適用は、リソース読み込み後に意味を持ちます。',
            'create_flow_steps' => '作成ステップ',
            'create_flow_intent_label' => '作成対象',
            'create_intent_label' => '何を作成しますか',
            'create_intent_app' => '新しいアプリ',
            'create_intent_module' => '新しいモジュール',
            'create_intent_view' => '新しいビュー / ページ',
            'create_intent_navigation' => 'ナビゲーションのみ',
            'create_intent_dashboard' => 'ダッシュボード / チャート',
            'create_flow_step_intent' => '作成対象を選択',
            'create_flow_step_module' => 'モジュール設定',
            'create_flow_step_view' => 'ビュー種別を選択',
            'create_flow_step_table_mode' => 'テーブル編集モードを選択',
            'create_flow_step_fields' => 'フィールド定義',
            'create_flow_step_layout' => 'レイアウト構成',
            'create_flow_step_governance' => 'ガバナンス確認',
            'create_flow_actions' => 'クイック操作',
            'create_flow_action_configure_navigation' => 'ナビゲーション設定',
            'create_flow_action_set_dashboard' => 'ダッシュボード種別に設定',
            'create_flow_action_add_table' => 'テーブルコンポーネントを追加',
            'create_flow_action_add_form' => 'フォームコンポーネントを追加',
            'create_flow_action_add_kpi' => 'KPIコンポーネントを追加',
            'create_flow_action_add_text' => 'テキストコンポーネントを追加',
            'create_flow_action_direct_db' => '直接DBのテーブル編集を使う',
            'create_flow_action_view_mode' => 'ビュー定義のテーブル編集を使う',
            'library_search_results_title' => '検索結果',
            'library_search_results_empty' => '一致するビューがありません。',
            'library_load_table_only' => 'テーブルのみ読み込む',
            'library_load_form_only' => 'フォームのみ読み込む',
            'library_all_views_title' => 'すべてのビュー',
            'library_all_modules_title' => 'すべてのモジュール',
            'library_system_registry_title' => 'システムレジストリ（詳細）',
            'advanced_debug_title' => '詳細 / デバッグ',
            'advanced_debug_help' => 'JSONエディタは高度な確認用としてここにまとめています。',
            'analyze_summary_title' => '分析サマリー',
            'analyze_summary_structure_ok' => '構造は正常です',
            'analyze_summary_structure_pending' => '構造が未完了です',
            'analyze_summary_missing_bindings' => 'バインディング不足',
            'analyze_summary_bindings_ok' => 'バインディングは整合しています',
            'analyze_summary_blockers' => '現在のブロッカー',
            'analyze_show_technical_details' => '[技術詳細を表示]',
            'apply_next_step_message' => '次のステップ: 適用前に分析を実行してください',
            'apply_next_step_ready_message' => '次のステップ: 変更内容を確認し、承認を確定してから適用してください',
            'apply_step_1_edit' => 'ステップ1: 編集',
            'apply_step_2_analyze' => 'ステップ2: 分析',
            'apply_step_3_changes' => 'ステップ3: 変更',
            'apply_step_4_apply' => 'ステップ4: 適用',
            'global_count_apps' => 'アプリ',
            'global_count_modules' => 'モジュール',
            'global_count_views' => 'ビュー',
            'global_count_dashboards' => 'ダッシュボード',
            'global_count_routes' => 'ルート',
            'global_count_navs' => 'ナビゲーション',
            'global_count_plugins' => 'プラグイン',
            'data_contract_title' => 'データコントラクト',
            'data_contract_subtitle' => '選択中Studioバンドルから抽出したバインディング・フィールド・データソースです。',
            'data_contract_confidence' => '信頼度',
            'data_contract_fields' => 'フィールド',
            'data_contract_bindings' => 'バインディング',
            'data_contract_sources' => 'データソース',
            'data_contract_required_fields' => '必須フィールド',
            'data_contract_empty' => 'データコントラクトはまだ抽出されていません。',
            'data_contract_checks' => '検証チェック',
            'data_contract_check_status' => '状態',
            'data_contract_check_detail' => '詳細',
            'data_contract_status.pass' => '合格',
            'data_contract_status.fail' => '失敗',
            'data_contract_confidence.full' => 'full',
            'data_contract_confidence.partial' => 'partial',
            'data_contract_confidence.none' => 'none',
            'data_contract_check.bindings_extracted' => 'バインディング抽出',
            'data_contract_check.bindings_known' => 'バインディングパス整合',
            'data_contract_check.required_fields_resolved' => '必須フィールド整合',
            'data_contract_check.data_sources_declared' => 'データソース宣言',
            'dependency_graph_title' => '依存グラフ',
            'dependency_graph_subtitle' => 'field -> view -> dashboard -> workflow の関連を追跡します。',
            'dependency_graph_nodes' => 'ノード',
            'dependency_graph_edges' => 'エッジ',
            'dependency_graph_depends_on' => 'depends_on',
            'dependency_graph_affects' => 'affects',
            'dependency_graph_issues' => 'グラフ課題',
            'dependency_graph_none' => '依存グラフデータはまだありません。',
            'dependency_graph_edge_table' => 'エッジ一覧',
            'dependency_graph_from' => 'From',
            'dependency_graph_to' => 'To',
            'dependency_graph_relation' => 'Relation',
            'generated_library_title' => '生成アプリライブラリ',
            'library_publish_state' => '公開状態',
            'library_publish_approved' => '承認済み',
            'library_publish_pending' => '保留',
            'library_empty' => 'レジストリに生成モジュールが見つかりません。',
            'library_app' => 'アプリ',
            'library_module' => 'モジュール',
            'library_route' => 'ルート',
            'library_version' => 'バージョン',
            'library_snapshot' => 'スナップショット',
            'library_last_updated' => '最終更新',
            'inspect' => '詳細',
            'load_into_studio' => 'Studioへ読み込み',
            'import_existing_view' => '既存ビューをインポート',
            'library_loading' => '読み込み中...',
            'library_load_failed' => '生成モジュールのStudio読み込みに失敗しました。',
            'library_load_failed_with_reason' => 'Studio読み込みに失敗しました（{reason}）。',
            'library_load_unsupported_kind' => 'このアーティファクト種別はPhase 1Aでは読み込めません（{kind}）。',
            'library_load_invalid_response' => '読み込みエンドポイントの応答が不正です。',
            'library_import_partial' => '部分インポートを読み込みました。再適用前に推定レイアウトを確認してください。',
            'library_import_complete' => '既存ビューをStudioにインポートしました。',
            'studio_mode_label' => 'Studioモード',
            'studio_mode_create_new' => '新規作成',
            'studio_mode_edit_existing' => '既存編集',
            'studio_mode_switch_create' => '作成フロー',
            'studio_mode_switch_upgrade' => 'アップグレードフロー',
            'tier_label' => '詳細レベル',
            'tier_simple' => 'シンプル',
            'tier_guided' => 'ガイド',
            'tier_advanced' => '詳細',
            'structured_editor_title' => '構造化モジュールエディタ',
            'structured_editor_note' => 'モジュール設定はフォームで編集します。JSONは内部生成され、直接編集できません。',
            'editor_title_create' => '新規バンドル作成エディタ',
            'editor_title_upgrade' => '{type} アップグレードエディタ',
            'editor_note_create' => 'モジュール設定はフォームで編集します。JSONは内部生成され、直接編集できません。',
            'editor_note_upgrade' => '読み込んだ {type} コンテンツをフォームで更新します。適用前に検証と影響分析を確認してください。',
            'editor_note_upgrade_app' => 'アプリのマニフェスト範囲、ルート所有、ガバナンス情報を確認してから適用してください。',
            'editor_note_upgrade_module' => 'モジュール設定とルートを慎重に更新してください。適用前に下流依存を検証してください。',
            'editor_note_upgrade_view' => '読み込んだビューのフィールド、バインディング、レイアウトを調整してください。下流安全性のため分析を実行してください。',
            'editor_note_upgrade_navigation' => 'ナビゲーションのラベル、ターゲット、アイコン、表示設定を慎重に更新してルート漏れを防いでください。',
            'editor_note_upgrade_db_table' => 'スキーマ変更は慎重に確認してください。適用前にマイグレーションと影響分析を確認してください。',
            'editor_note_upgrade_unknown' => '読み込んだコンテンツを慎重に確認し、適用前に検証と分析を実行してください。',
            'module_settings' => 'モジュール設定',
            'module_key' => 'モジュールキー',
            'module_display_name' => 'モジュール表示名',
            'module_type' => 'モジュール種別',
            'module_description' => 'モジュール説明',
            'route_path' => 'ルートパス',
            'field_editor' => 'フィールドエディタ',
            'field_name' => 'フィールド名',
            'field_type' => '型',
            'field_required' => '必須',
            'field_default' => 'デフォルト',
            'add_field' => 'フィールド追加',
            'remove_field' => '削除',
            'view_config_editor' => 'ビュー設定エディタ',
            'visual_builder_title' => 'ビジュアルビルダー',
            'visual_builder_note' => '12カラムグリッド上でドラッグ&ドロップとリサイズによりビュー配置を構成します。',
            'visual_builder_templates_title' => 'テンプレートから開始',
            'visual_builder_recommended_templates_title' => 'おすすめテンプレート',
            'visual_builder_all_templates_title' => 'すべてのテンプレート',
            'visual_builder_save_template' => 'テンプレートとして保存',
            'visual_builder_template_name_prompt' => 'テンプレート名を入力',
            'visual_builder_my_templates_title' => 'マイテンプレート',
            'visual_builder_suggestions_title' => '次のおすすめ',
            'visual_builder_suggestion_add_kpi' => 'KPIを追加',
            'visual_builder_suggestion_add_table' => 'テーブルを追加',
            'visual_builder_suggestion_add_form' => 'フォームを追加',
            'visual_builder_suggestion_use_template' => 'おすすめテンプレートを使用',
            'visual_builder_suggestion_none' => '作成に応じて候補が更新されます。',
            'visual_builder_views_title' => 'ビュー',
            'visual_builder_add_view' => '+ ビュー追加',
            'visual_builder_view_name_prompt' => 'ビュー名を入力',
            'visual_builder_view_default_name' => 'ビュー',
            'visual_builder_view_none' => 'ビューはまだありません。',
            'visual_builder_export_app' => 'アプリをエクスポート',
            'visual_builder_import_app' => 'アプリバンドルをインポート',
            'visual_builder_export_missing' => 'エクスポートする内容がありません。',
            'visual_builder_import_invalid' => 'アプリバンドルJSONが不正です。',
            'visual_builder_import_failed' => 'アプリバンドルのインポートに失敗しました。',
            'visual_builder_import_success' => 'アプリバンドルをStudioに読み込みました。',
            'visual_builder_export_filename' => 'sample_app_bundle.json',
            'component_config.open_view' => '開くビュー',
            'component_config.open_view_none' => 'リンク先なし',
            'visual_builder_template_kpi_grid' => 'KPIダッシュボード',
            'visual_builder_template_table_view' => 'テーブルビュー',
            'visual_builder_template_form_entry' => 'フォーム入力',
            'visual_builder_template_replace_confirm' => '現在のレイアウトを置き換えますか？',
            'visual_builder_template_metric_1' => '指標 1',
            'visual_builder_template_metric_2' => '指標 2',
            'visual_builder_template_metric_3' => '指標 3',
            'visual_builder_template_metric_4' => '指標 4',
            'visual_builder_phase1_note' => 'このフェーズでは静的グリッド行とコンポーネントを構成します（ドラッグとリサイズは無効）。',
            'visual_builder_palette' => 'コンポーネントパレット',
            'visual_builder_canvas' => 'グリッドキャンバス',
            'visual_builder_items' => 'レイアウト項目',
            'visual_builder_preview_toggle' => 'プレビューモード',
            'visual_builder_empty' => 'ここにコンポーネントをドロップしてレイアウトを開始してください。',
            'visual_builder_binding_schema' => 'バインディングスキーマ',
            'visual_builder_relations' => 'コンポーネント関連',
            'visual_builder_add_relation' => '関連を追加',
            'visual_builder_relation_source' => 'ソース',
            'visual_builder_relation_target' => 'ターゲット',
            'visual_builder_relation_type' => '関連タイプ',
            'visual_builder_relation_affects_table' => 'テーブルへ反映',
            'visual_builder_relation_filter_to_table' => 'フィルター -> テーブル',
            'visual_builder_relation_filter_to_kpi' => 'フィルター -> KPI',
            'visual_builder_relation_form_refresh_table' => 'フォーム -> テーブル（再読込）',
            'visual_builder_relation_table_to_kpi_derived' => 'テーブル -> KPI（派生）',
            'visual_builder_relation_empty' => '関連は未設定です。',
            'visual_builder_group' => 'グループ',
            'visual_builder_reorder_up' => '上へ',
            'visual_builder_reorder_down' => '下へ',
            'visual_builder_warning_overlap' => '重なりは許可されません。空き領域に移動またはリサイズしてください。',
            'visual_builder_warning_bounds' => '項目がグリッド範囲外です。12カラム内に収めてください。',
            'visual_builder_warning_binding_invalid' => 'CompileまたはValidateの前にバインディングエラーを修正してください。',
            'component_settings_title' => 'コンポーネント設定',
            'visual_builder_component_config' => 'コンポーネント設定',
            'visual_builder_select_component_hint' => 'レイアウト項目を選択してコンポーネントプロパティを編集してください。',
            'visual_builder_binding_source' => 'データバインディング元',
            'visual_builder_binding_picker' => 'バインディング選択',
            'visual_builder_binding_placeholder' => '例: module.fields.part_name',
            'visual_builder_binding_error.invalid_path' => 'バインディングパスがスキーマに存在しません。',
            'visual_builder_binding_error.table' => 'テーブルコンポーネントはmodule.rowsにバインドする必要があります。',
            'visual_builder_binding_error.form' => 'フォームコンポーネントはmodule.fieldsにバインドする必要があります。',
            'visual_builder_binding_error.kpi' => 'KPIコンポーネントはmodule.metrics.*にバインドする必要があります。',
            'visual_builder_binding_error.text' => 'テキストコンポーネントはmodule.descriptionにバインドする必要があります。',
            'visual_builder_add_component' => 'コンポーネントを追加',
            'visual_builder_preview' => 'プレビュー',
            'visual_builder_default_kpi' => '総レコード数',
            'visual_builder_default_table' => 'レコード',
            'visual_builder_default_form' => 'レコードフォーム',
            'visual_builder_default_text' => 'テキストブロック',
            'component_config.item_id' => '項目ID',
            'component_config.width' => '幅',
            'component_config.width_narrower' => '狭くする',
            'component_config.width_wider' => '広くする',
            'component_config.filter_label' => 'フィルターラベル',
            'component_config.filter_field' => 'フィルター項目',
            'component_config.filter_placeholder' => 'プレースホルダー',
            'component_config.table_title' => 'テーブルタイトル',
            'component_config.table_rows' => 'サンプル行数',
            'component_config.table_density' => '表示密度',
            'component_config.form_title' => 'フォームタイトル',
            'component_config.form_submit_label' => '送信ラベル',
            'component_config.default_submit' => '送信',
            'component_config.form_show_required' => '必須マークを表示',
            'component_config.kpi_label' => 'KPIラベル',
            'component_config.kpi_value' => 'KPI値',
            'component_config.kpi_delta' => 'KPI差分',
            'component_config.text_title' => 'テキストタイトル',
            'component_config.text_body' => '本文',
            'component_config.text_align' => '文字寄せ',
            'component_config.option.compact' => 'コンパクト',
            'component_config.option.comfortable' => '快適',
            'component_config.option.left' => '左',
            'component_config.option.center' => '中央',
            'component_config.option.right' => '右',
            'component.table' => 'テーブル',
            'component.filter' => 'フィルター',
            'component.form' => 'フォーム',
            'component.kpi_card' => 'KPIカード',
            'component.text_block' => 'テキストブロック',
            'component.duplicate' => '複製',
            'component.delete' => '削除',
            'component.type.kpi_card' => 'KPI',
            'component.type.table' => 'TABLE',
            'component.type.form' => 'FORM',
            'component.type.filter' => 'FILTER',
            'component.type.text_block' => 'TEXT',
            'visual_builder_add_component_inline' => '+ コンポーネント追加',
            'feedback.add' => 'コンポーネントを追加しました',
            'feedback.move' => 'コンポーネントを移動しました',
            'feedback.resize' => 'コンポーネントをリサイズしました',
            'feedback.template' => 'テンプレートを適用しました',
            'builder_item_move' => '移動',
            'builder_item_remove' => '削除',
            'view_type' => 'ビュー種別',
            'view_type_table' => 'テーブル',
            'view_type_form' => 'フォーム',
            'view_type_dashboard' => 'ダッシュボード',
            'table_edit_mode_label' => 'テーブル編集モード',
            'table_edit_mode_view_only' => 'ビュー定義編集',
            'table_edit_mode_direct_db' => '直接DB編集',
            'table_edit_mode_note_view_only' => 'ビュー定義編集では表示カラムやレイアウト設定を編集できます。',
            'table_edit_mode_note_direct_db' => '直接DB編集ではビュー専用設定を隠し、スキーマ中心の編集に集中します。',
            'db_editor_title' => 'DBテーブルエディタ',
            'db_editor_loading' => 'テーブルデータを読み込み中…',
            'db_editor_load_failed' => 'テーブルデータの読み込みに失敗しました。',
            'db_editor_schema_title' => 'スキーマ',
            'db_editor_rows_title' => 'データ行',
            'db_editor_table_type_label' => 'テーブル',
            'db_editor_orders_table' => '注文',
            'db_editor_parts_table' => '部品',
            'db_editor_add_row' => '行を追加',
            'db_editor_save_row' => '保存',
            'db_editor_cancel' => 'キャンセル',
            'db_editor_delete_row' => '削除',
            'db_editor_confirm_delete' => 'この行を削除しますか？',
            'db_editor_empty' => '行がありません。',
            'db_editor_note' => 'スキーマ安全編集のみ。id と created_at 列は読み取り専用です。',
            'db_editor_field_col' => 'フィールド',
            'db_editor_type_col' => 'タイプ',
            'db_editor_nullable_col' => 'NULL許容',
            'db_editor_no_app' => 'DBを直接編集するにはまずモジュールを読み込んでください。',
            'loaded_type_app' => 'アプリ',
            'loaded_type_module' => 'モジュール',
            'loaded_type_view' => 'ビュー',
            'loaded_type_dashboard' => 'ダッシュボード',
            'loaded_type_navigation' => 'ナビゲーション',
            'loaded_type_db_table' => 'DBテーブル',
            'loaded_type_unknown' => '不明',
            'loaded_type_label' => '編集中',
            'loaded_context_label' => '読み込みコンテキスト',
            'loaded_context_source' => 'ソース',
            'loaded_context_source_library' => 'ライブラリ',
            'loaded_context_source_import' => 'インポート',
            'loaded_context_unknown' => '不明',
            'surface_exposure_title' => 'サーフェス公開状態',
            'surface_exposure_subtitle' => 'ビュー・ルート・ナビゲーションの構成状態。',
            'surface_exposure_summary_label' => '関係状態',
            'surface_exposure_aspect' => '対象',
            'surface_exposure_value' => '値',
            'surface_exposure_state' => '状態',
            'surface_exposure_aspect_view' => 'ビュー',
            'surface_exposure_aspect_route' => 'ルート',
            'surface_exposure_aspect_navigation' => 'ナビゲーション',
            'surface_exposure_aspect_policy' => '編集可否',
            'surface_exposure_value_none' => 'なし',
            'surface_exposure_policy_inspect_only' => '参照のみ',
            'surface_exposure_policy_route_linkable' => 'ルート：リンク提案',
            'surface_exposure_state_connected' => '接続済み',
            'surface_exposure_state_missing_route' => 'ルート未設定',
            'surface_exposure_state_missing_nav' => 'ナビ未設定',
            'surface_exposure_state_nav_unknown_route' => 'ナビ先ルート不明',
            'surface_exposure_state_ambiguous_conflicting' => '曖昧/競合',
            'surface_exposure_state_inspect_only' => '参照のみ',
            'surface_exposure_diag_connected' => 'ビューのルートとナビゲーションは接続されています。',
            'surface_exposure_diag_missing_route' => 'このビューはルートに公開されていません。',
            'surface_exposure_diag_missing_nav' => 'このビューにはルートがありますが、ナビゲーション項目がありません。',
            'surface_exposure_diag_nav_unknown_route' => 'ナビゲーションが存在しない、または不明なルートを指しています。',
            'surface_exposure_diag_ambiguous_conflicting' => '複数候補または競合が検出されました。手動で解決してください。',
            'route_link_title' => 'ルートリンクの提案',
            'route_link_subtitle' => 'このビューにルートパスを紐付けます。スタジオ生成アーティファクトのみ対象です。',
            'route_link_upgrade_subtitle' => 'このビューの既存ルートパスを変更します。意図的な変更であることを確認してください。',
            'route_link_label' => 'ルートパス',
            'route_link_placeholder' => '/apps/generated/my_app/my_module',
            'route_link_upgrade_acknowledge' => 'これは意図的なルート変更です（アップグレードモード）。',
            'route_link_analyze_btn' => '解析',
            'route_link_apply_btn' => '適用',
            'route_link_cancel_btn' => 'キャンセル',
            'route_link_analyzing' => '解析中...',
            'route_link_applying' => '適用中...',
            'route_link_changes_title' => '提案された変更',
            'route_link_changes_file' => 'ファイル',
            'route_link_changes_field' => 'フィールド',
            'route_link_changes_before' => '変更前',
            'route_link_changes_after' => '変更後',
            'route_link_changes_none' => '変更なし。',
            'route_link_gate_ready' => '適用可能',
            'route_link_gate_blocked' => 'ブロック',
            'route_link_success' => 'ルートリンクを適用しました。',
            'route_link_error_blocked' => '適用がブロックされました。エラーを確認してください。',
            'route_link_error_failed' => '適用に失敗しました。詳細を確認してください。',
            'route_link_open_btn' => 'ルートリンクを提案',
            'route_link_change_btn' => 'ルート変更を提案',
            'nav_link_title' => 'ナビリンクを提案',
            'nav_link_subtitle' => 'このビューにナビゲーション項目を追加します。Studio生成アーティファクトのみ対応。',
            'nav_link_upgrade_subtitle' => 'このビューのナビゲーションURLを更新します。変更が意図的であることを確認してください。',
            'nav_link_url_label' => 'ナビURL',
            'nav_link_label_label' => 'ナビラベル',
            'nav_link_placeholder' => '/apps/generated/my_app/my_module',
            'nav_link_label_placeholder' => 'マイモジュール',
            'nav_link_upgrade_acknowledge' => '意図的なナビゲーション変更（更新モード）であることを確認します。',
            'nav_link_analyze_btn' => '解析',
            'nav_link_apply_btn' => '適用',
            'nav_link_cancel_btn' => 'キャンセル',
            'nav_link_analyzing' => '解析中...',
            'nav_link_applying' => '適用中...',
            'nav_link_changes_title' => '変更案',
            'nav_link_changes_file' => 'ファイル',
            'nav_link_changes_field' => 'フィールド',
            'nav_link_changes_before' => '変更前',
            'nav_link_changes_after' => '変更後',
            'nav_link_changes_none' => '変更なし。',
            'nav_link_gate_ready' => '準備完了',
            'nav_link_gate_blocked' => 'ブロック',
            'nav_link_success' => 'ナビリンクを適用しました。',
            'nav_link_error_blocked' => '適用がブロックされました。エラーを確認してください。',
            'nav_link_error_failed' => '適用に失敗しました。詳細を確認してください。',
            'nav_link_open_btn' => 'ナビリンクを提案',
            'nav_link_update_btn' => 'ナビ更新を提案',
            'visible_columns' => '表示カラム',
            'layout_compact' => 'コンパクトレイアウト',
            'navigation_editor' => 'ナビゲーションエディタ',
            'navigation_section' => 'セクション',
            'navigation_group' => 'グループ',
            'navigation_label' => 'ラベル',
            'navigation_target' => '遷移先URL',
            'navigation_icon' => 'アイコン',
            'navigation_order' => '表示順',
            'navigation_visibility' => '表示する',
            'navigation_section_operations' => '運用',
            'navigation_section_apps' => 'アプリ',
            'navigation_section_admin_system' => '管理 / システム',
            'structured_mapping_ok' => '構造化マッピング有効',
            'change_summary_title' => '変更サマリー',
            'change_summary_empty' => 'ステージされた変更はありません。',
            'impact_low' => '低',
            'impact_medium' => '中',
            'impact_high' => '高',
            'change_severity_safe' => '安全',
            'change_severity_additive' => '追加',
            'change_severity_breaking' => '破壊的',
            'breaking_changes_warning' => '破壊的変更を検出しました。既存のデータまたは連携に影響する小素があります。Apply前に注意深く確認してください。',
            'breaking_changes_blocked' => '全ての破壊的変更を承認するまでApplyはブロックされます。',
            'rollback_snapshot_notice' => '破壊的変更のApply前にロールバックスナップショットを自動作成します。',
            'change_requires_ack' => '高リスク変更を検出しました。Compile前に理由とリスク承認が必要です。',
            'migration_plan_title' => 'マイグレーション計画',
            'migration_action' => 'マイグレーション操作',
            'migration_strategy' => '戦略',
            'migration_warning_destructive' => '破壊的マイグレーションを検出しました。Apply前に明示的オーバーライドと理由が必要です。',
            'migration_empty' => '必要なスキーマ移行はありません。',
            'migration_override_label' => '破壊的マイグレーションをオーバーライドする',
            'migration_override_reason' => 'オーバーライド理由',
            'migration.strategy.safe' => 'safe',
            'migration.strategy.destructive' => 'destructive',
            'migration.strategy.requires_migration' => 'requires_migration',
            'migration.error.override_required' => '破壊的マイグレーションには明示的オーバーライドが必要です。',
            'migration.error.override_reason_required' => '破壊的マイグレーションにはオーバーライド理由が必要です。',
            'impact_analysis_title' => '影響分析',
            'impact_change' => '変更',
            'impact_affected_components' => '影響対象コンポーネント',
            'impact_severity' => '重大度',
            'impact_warning_high' => '高影響を検出しました。Apply前に追加確認と影響確認が必要です。',
            'impact_empty' => '下流影響は検出されませんでした。',
            'impact_confirmation_label' => '高影響変更を適用することを確認します',
            'impact_ack_label' => '下流影響を理解しました',
            'impact.error.confirmation_required' => '高影響の適用には追加確認が必要です。',
            'impact.error.ack_required' => '高影響の適用には影響確認が必要です。',
            'simulation_preview_title' => 'シミュレーションプレビュー',
            'simulation_views_after' => '変更後ビュー',
            'simulation_broken_views' => '破損ビュー',
            'simulation_removed_fields' => '削除フィールド',
            'simulation_new_fields' => '追加フィールド',
            'simulation_warning' => '破損ビューを検出しました。Applyには明示的なシミュレーションオーバーライドと理由が必要です。',
            'simulation_empty' => 'シミュレーション警告はありません。',
            'simulation_override_label' => '破損ビューに対するシミュレーションオーバーライドを許可する',
            'simulation_override_reason' => 'シミュレーションオーバーライド理由',
            'simulation_reason_missing_required_field' => 'ビューで必須フィールドが不足しています',
            'simulation_reason_filter_references_removed_field' => 'フィルターが削除フィールドを参照しています',
            'simulation_reason_navigation_invalid_route' => 'ナビゲーションが無効なルートを指しています',
            'simulation.error.override_required' => '破損ビューには明示的なシミュレーションオーバーライドが必要です。',
            'simulation.error.reason_required' => '破損ビューにはシミュレーションオーバーライド理由が必要です。',
            'error.upgrade_baseline_required' => 'アップグレードフローではCompileまたはApplyの前に既存内容の読み込みが必要です。',
            'error.layout_invalid_type' => 'レイアウト種別はgridである必要があります。',
            'error.layout_invalid_columns' => 'レイアウトは12カラムである必要があります。',
            'error.layout_invalid_rows' => 'レイアウトのrowsを定義してください。',
            'error.layout_missing_items' => 'レイアウトには少なくとも1つの項目が必要です。',
            'error.layout_invalid_item' => 'レイアウトに無効な項目があります。',
            'error.layout_item_id_required' => '各レイアウト項目にはidが必要です。',
            'error.layout_item_duplicate_id' => 'レイアウト項目のidは一意である必要があります。',
            'error.layout_invalid_component' => 'レイアウトに未対応コンポーネントがあります。',
            'error.layout_missing_component_props' => 'レイアウトコンポーネントの必須プロパティが不足しています。',
            'error.layout_missing_data_binding' => '各レイアウト項目にデータバインディング元が必要です。',
            'error.layout_invalid_data_binding' => 'データバインディングパスはドット区切りで指定してください（例: module.fields.part_name）。',
            'error.layout_binding_type_table' => 'テーブルコンポーネントのバインディングはmodule.rowsである必要があります。',
            'error.layout_binding_type_filter' => 'フィルターコンポーネントのバインディングはmodule.rowsである必要があります。',
            'error.layout_binding_type_form' => 'フォームコンポーネントのバインディングはmodule.fieldsである必要があります。',
            'error.layout_binding_type_kpi' => 'KPIコンポーネントのバインディングはmodule.metrics.*である必要があります。',
            'error.layout_binding_type_text' => 'テキストコンポーネントのバインディングはmodule.descriptionである必要があります。',
            'error.layout_invalid_group' => '各レイアウト項目には有効なグループが必要です。',
            'error.layout_invalid_relation' => 'レイアウト関連は有効なソース項目とターゲット項目を参照する必要があります。',
            'error.layout_out_of_bounds' => 'レイアウト項目はグリッド範囲内に配置してください。',
            'error.layout_overlap' => 'レイアウト項目は重ねられません。',
            'app_registry_empty' => '登録済みの生成アプリはまだありません。',
            'current_version' => '現在のバージョン',
            'modules' => 'モジュール',
            'enable' => '有効化',
            'disable' => '無効化',
            'uninstall' => 'アンインストール',
            'files_present' => 'ファイルあり',
            'lifecycle_result' => 'ライフサイクル結果',
            'lifecycle_status_enabled' => '有効',
            'lifecycle_status_disabled' => '無効',
            'lifecycle_status_installed' => 'インストール済み',
            'lifecycle_status_removed' => '削除済み',
            'lifecycle.error.app_not_found' => 'アプリがレジストリに見つかりません。',
            'lifecycle.error.module_files_missing' => 'モジュールファイルが不足しているため有効化できません。',
            'lifecycle.error.rollback_in_progress' => 'ロールバック中のためライフサイクル操作をブロックしました。',
            'lifecycle.error.invalid_lifecycle_request' => 'ライフサイクル要求が不正です。',
            'lifecycle.error.registry_write_failed' => 'レジストリ更新に失敗しました。',
            'lifecycle.error.uninstall_blocked' => '安全チェックによりアンインストールをブロックしました。',
            'flash.lifecycle.updated' => 'アプリライフサイクルを更新しました。',
            'flash.lifecycle.failed' => 'アプリライフサイクル更新に失敗しました。',
            'governance_title' => 'ガバナンス — G1〜G4 ノーコードポリシー',
            'governance_policy' => '作成モードポリシー',
            'governance_mode' => 'GUI優先',
            'preflight_title' => 'プリフライトチェック (G2)',
            'preflight_btn' => 'プリフライト実行',
            'preflight_ok' => 'すべてのプリフライトチェックが通過しました。',
            'preflight_failed' => 'プリフライト失敗。コンパイル前にエラーを修正してください。',
            'lint_title' => 'リントチェック (G3)',
            'lint_ok' => 'バンドルリントが通過しました。',
            'lint_failed' => 'リント失敗。マニフェスト構造エラーを修正してください。',
            'publish_gate_title' => '公開ゲート (G4)',
            'publish_gate_btn' => '公開ゲートチェック実行',
            'publish_gate_ok' => 'すべての公開ゲートが通過しました。承認準備完了。',
            'publish_gate_failed' => '公開ゲート失敗。すべてのゲートエラーを解決してください。',
            'apply_mode_gate' => '適用モード',
            'rollback_plan_title' => 'ロールバックプラン',
            'rollback_plan_artifacts' => 'ロールバック対象アーティファクト',
            'rollback_reversible' => '元に戻せる',
            'publish_decision_title' => '公開決定記録',
            'publish_decision_by' => '決定者',
            'publish_decision_at' => '決定日時',
            'publish_decision_allowed' => '公開許可',
            'audit_written' => '監査スナップショット書き込み',
            'audit_ok' => '監査スナップショットを保存しました。',
            'audit_failed' => '監査スナップショットを書き込めませんでした。',
            'todo_title' => 'ワークフローチェックリスト (G4)',
            'focused_plan_title' => 'フォーカス開始プラン (G1)',
            'focused_plan_step' => 'ステップ',
            'focused_plan_gate' => 'ゲート',
            'checkpoints_title' => '承認チェックポイント (G1)',
            'checkpoint_label' => 'チェックポイント',
            'checkpoint_stage' => 'ライフサイクル段階',
            'checkpoint_gates' => '必要ゲート',
            'flash.preflight.ok' => 'プリフライト通過。',
            'flash.preflight.failed' => 'プリフライト失敗。続行前にエラーを修正してください。',
            'flash.publish_gate.ok' => '公開ゲート通過。',
            'flash.publish_gate.failed' => '公開ゲート失敗。公開前にすべてのゲートが通過する必要があります。',
            'flash.library_bundle_loaded' => '生成モジュールをStudioエディタに読み込みました。',
            'flash.library_bundle_missing' => '生成モジュールが見つからないか、マニフェストが不正です。',
            'flash.import_loaded' => '既存ビューをStudioにインポートしました。',
            'flash.import_partial' => '部分インポートを読み込みました。再適用前に推定レイアウトを確認してください。',
            'flash.import_missing' => 'インポートに失敗しました。既存ビューのソースを取得できません。',
            'changes_summary_title' => '変更の要約',
            'changes_total_artifacts' => '合計アーティファクト',
            'changes_new_files' => '新しいファイル',
            'changes_modified_files' => '変更されたファイル',
            'changes_routes_affected' => '影響を受けるルート',
            'changes_views_affected' => '影響を受けるビュー',
            'changes_artifacts_title' => 'アーティファクト',
            'changes_preview_title' => '変更プレビュー',
            'changes_preview_subtitle' => '主要なアーティファクトの前後を並べて比較します。',
            'changes_diff_summary' => 'Diff概要',
            'changes_no_preview' => 'Diffプレビューが利用できません。変更を生成するようにコンパイルしてください。',
            'changes_no_data' => 'ステージされた変更はありません。',
            'read_only_analysis_badge' => '読み取り専用分析',
            'no_changes_staged_badge' => '変更は未ステージです',
            'loaded_owner_app_label' => '所有アプリ',
            'loaded_module_label' => 'モジュール',
            'loaded_resource_type_label' => 'リソース種別',
            'loaded_resource_key_label' => 'リソースキー',
            'loaded_mode_label' => 'モード',
            'loaded_source_path_label' => 'ソースパス',
            'loaded_identity_title' => '読み込みリソース識別情報',
            'loaded_identity_helper' => 'Studio は所有者のリソースを確認しています。Studio 自体が所有者になることはありません。',
            'clear_loaded_context_action' => '読み込みコンテキストをクリア',
            'clear_loaded_context_helper' => '現在の読み込みリソースのプレビューだけをクリアします。ファイル、レコード、保存済みリソースは削除されません。',
            'not_loaded' => '未読み込み',
            'workflow_status_title' => 'ワークフロー状態',
            'workflow_status_helper' => '検証、差分、プレビュー、承認、適用はガバナンス管理された手順です。承認済みの適用が実行されるまで変更は反映されません。',
            'workflow_stage_analyze' => '分析',
            'workflow_stage_changes' => '変更 / 差分',
            'workflow_stage_preview' => 'プレビュー',
            'workflow_stage_approval' => '承認',
            'workflow_stage_apply' => '適用',
            'workflow_state_load_before_analysis' => '分析の前にリソースを読み込んでください。',
            'workflow_state_no_diff' => '利用可能な差分はありません。',
            'workflow_state_no_preview' => '有効なプレビューはありません。',
            'workflow_state_no_approval' => '保留中の承認はありません。',
            'workflow_state_no_apply' => '有効な適用アクションはありません。',
            'workflow_state_ready_readonly' => '読み取り専用の確認準備ができています',
            'workflow_state_no_changes_staged' => 'ステージされた変更はありません',
            'workflow_state_apply_inactive' => '適用は非アクティブです',
            'mode_panel_title' => 'モード',
            'mode_panel_helper' => 'モードは想定される作業種別を示します。承認されたガバナンス適用が実行されるまで、この画面でリソースは変更されません。',
            'mode_read_only' => '読み取り専用',
            'mode_create' => '作成',
            'mode_edit' => '編集',
            'mode_upgrade' => 'アップグレード',
            'mode_state_active' => 'アクティブ',
            'mode_state_planned' => '計画済み',
            'mode_state_not_active' => '非アクティブ',
            'mode_state_requires_governance' => 'ガバナンスワークフローが必要',
            'mode_state_context_available' => 'コンテキスト利用可能',
            'studio_tools_title' => 'Studio Workbench',
            'studio_tools_helper' => 'Studio は統制されたワークベンチです。これらのツールは owner-owned リソースを準備・確認・検証し、引き渡しを支援します。バックエンド動作は承認済みワークフローで後から接続されます。',
            'studio_tools_group_explore' => '探索',
            'studio_tools_group_build' => '構築',
            'studio_tools_group_validate' => '検証',
            'studio_tools_group_govern' => '統制',
            'studio_tools_group_history' => '履歴',
            'studio_tools_status_available' => '利用可能',
            'studio_tools_status_read_only' => '読み取り専用',
            'studio_tools_status_planned' => '計画中',
            'studio_tools_status_requires_governed' => '統制ワークフローが必要',
            'studio_tool_resource_explorer' => 'リソースエクスプローラー / ライブラリ',
            'studio_tool_app_builder' => 'アプリビルダー',
            'studio_tool_module_builder' => 'モジュールビルダー',
            'studio_tool_view_layout_builder' => 'ビュー / レイアウトビルダー',
            'studio_tool_navigation_menu' => 'ナビゲーション / メニューツール',
            'studio_tool_widget_card_builder' => 'ウィジェット / カードビルダー',
            'studio_tool_report_builder' => 'レポートビルダー',
            'studio_tool_data_model_schema' => 'データモデル / DB スキーマツール',
            'studio_tool_validation_preview_center' => '検証 / プレビューセンター',
            'studio_tool_approval_apply_center' => '承認 / 適用センター',
            'studio_tool_change_history_snapshots' => '変更履歴 / スナップショット',
            'studio_tool_purpose_resource_explorer' => '既存の owner リソースを探索し、確認用コンテキストを読み込みます。',
            'studio_tool_purpose_app_builder' => 'アプリ下書き構造と所有メタデータを整えます。',
            'studio_tool_purpose_module_builder' => 'モジュール構成、契約、構成下書きを準備します。',
            'studio_tool_purpose_view_layout_builder' => '統制された引き渡し前にビュー/レイアウト下書きを構成します。',
            'studio_tool_purpose_navigation_menu' => '実行ルートを変更せずにナビ/メニュー公開意図を下書きします。',
            'studio_tool_purpose_widget_card_builder' => 'owner レビュー向けにウィジェット/カード構成を準備します。',
            'studio_tool_purpose_report_builder' => '統制レビュー向けにレポート構造と出力意図を下書きします。',
            'studio_tool_purpose_data_model_schema' => '承認ワークフロー前にスキーマ意図と影響を計画します。',
            'studio_tool_purpose_validation_preview_center' => '読み取り専用レーンで検証結果と差分を確認します。',
            'studio_tool_purpose_approval_apply_center' => '実行制御なしで承認/適用の統制状態を確認します。',
            'studio_tool_purpose_change_history_snapshots' => '変更系譜、スナップショット、監査履歴を確認します。',
            'studio_tools_boundary_owner_resources' => 'Works on owner-owned resources',
            'studio_tools_boundary_not_owner' => 'Does not own business modules',
            'studio_tools_boundary_no_runtime_without_apply' => 'No runtime changes until approved apply',
            'studio_tools_open_library' => 'ライブラリを開く',
            'studio_tools_open_history' => '履歴を開く',
            'studio_tools_preview_title' => 'Workbench Tool Preview',
            'studio_tools_preview_helper' => 'このプレビューは Studio ツールの意図のみを説明します。ツール実行は後で統制ワークフローを通じて接続されます。',
            'studio_tools_preview_default' => 'Studio ツールを選択して、目的と境界を確認してください。',
            'studio_tools_preview_field_name' => 'ツール名',
            'studio_tools_preview_field_group' => 'グループ',
            'studio_tools_preview_field_status' => 'ステータス',
            'studio_tools_preview_field_purpose' => '目的',
            'studio_tools_preview_field_works_on' => '対象',
            'studio_tools_preview_field_must_not_own' => '所有してはならないもの',
            'studio_tools_preview_field_first_safe' => '最初の安全な実装',
            'studio_tools_preview_field_backend' => 'バックエンド接続状態',
            'studio_tools_preview_first_safe_readonly' => 'Studio パネルでの読み取り専用の確認とメタデータレビュー。',
            'studio_tools_preview_first_safe_linked' => '既存の Studio ページリンクを読み取り専用コンテキストとして利用。',
            'studio_tools_preview_first_safe_governed' => '統制された analyze/diff/approval/apply ワークフローでのみ有効化。',
            'studio_tools_preview_backend_unwired' => '未接続（意図プレビューのみ）。',
            'studio_tools_preview_backend_linked' => 'リンク先 Studio ページは利用可能。実行ワークフローは引き続き統制/未接続。',
            'studio_workbench_context_title' => 'Tool + Resource Preview',
            'studio_workbench_context_field_selected_tool' => '選択中ツール',
            'studio_workbench_context_field_selected_resource' => '選択中リソース',
            'studio_workbench_context_field_owner_app' => 'Owner App',
            'studio_workbench_context_field_module' => 'Module',
            'studio_workbench_context_field_resource_type' => 'リソース種別',
            'studio_workbench_context_field_action_state' => 'アクション状態',
            'studio_workbench_context_field_ownership_boundary' => '所有境界',
            'studio_workbench_context_no_resource' => 'リソースは未読み込みです',
            'studio_workbench_context_no_tool' => 'ツール未選択',
            'studio_workbench_context_action_preview_only' => 'プレビューのみ',
            'studio_workbench_context_action_readonly_analysis' => '読み取り専用分析',
            'studio_workbench_context_action_no_apply' => '適用アクションは非アクティブ',
            'studio_workbench_context_boundary_text' => '選択中ツールは owner-owned リソースを確認できます。Studio 自体は所有者になりません。',
            'studio_workbench_context_backend_planned' => 'バックエンド接続: 計画中',
            'studio_workbench_context_backend_linked' => 'バックエンド接続: リンクページのみ',
            'studio_workbench_context_no_execution' => 'ツール実行はアクティブではありません',
            'loaded_mode_read_only' => '読み取り専用',
            'loaded_mode_edit' => '編集',
            'loaded_mode_create' => '作成',
            'loaded_mode_upgrade' => 'アップグレード',
            'artifacts' => 'アーティファクト',
            'created' => '作成',
            'modified' => '変更',
            'routes' => 'ルート',
            'views' => 'ビュー',
            'artifact_name' => 'アーティファクト',
            'operation' => '操作',
            'operation.create' => '作成',
            'operation.modify' => '変更',
            'operation.delete' => '削除',
            'status' => 'ステータス',
            'status.ready' => '準備完了',
            'status.pending' => '保留中',
            'additions' => '追加',
            'deletions' => '削除',
            'files_changed' => 'ファイル変更',
            'apply_tab_title' => '変更を適用',
            'apply_tab_subtitle' => 'パイプラインゲート通過と承認確認の後にのみ実行します。',
            'apply_pipeline_title' => 'パイプライン状態',
            'apply_pipeline_compile' => 'コンパイル',
            'apply_pipeline_analyze' => '分析',
            'apply_pipeline_impact' => '影響',
            'apply_pipeline_risk_level' => 'リスクレベル',
            'apply_pipeline_ok' => 'OK',
            'apply_pipeline_pending' => '保留中',
            'apply_metadata_title' => '承認メタデータ',
            'apply_confirmation_label' => 'これらの変更を適用する準備ができていることを確認します',
            'apply_gate_requirements_title' => '適用ゲート要件',
            'apply_gate_requirements_analyze' => '分析が完了している必要があります',
            'apply_gate_requirements_confirm' => '確認チェックボックスの選択が必要です',
            'apply_gate_requirements_reason' => '承認理由が必要です',
            'apply_gate_failed_title' => 'ゲートチェックで適用がブロックされました',
            'apply_gate_error_analyze' => '分析が完了していません。',
            'apply_gate_error_confirm' => '適用前に確認が必要です。',
            'apply_gate_error_reason' => '適用前に承認理由が必要です。',
            'apply_gate_runtime_errors' => '現在のApplyは次のゲートチェックでブロックされています:',
            'apply_changes_btn' => '変更を適用',
            'apply_no_data' => '変更を適用する前にプランをコンパイルして分析してください。',
            'pipeline_risk_medium' => '中',
          ],
          'ne' => [
            'title' => 'ERP App Studio',
            'subtitle' => 'प्रकाशन काम सुरु हुनु अघि एप्प, मोड्युल, भ्यु, नेभिगेसन, टेम्प्लेट, र प्याकेज मस्यौदा शासन गर्नुहोस्।',
            'compat' => 'Compatibility route /apps/studio नै रहन्छ।',
            'capabilities' => 'Foundation Scope',
            'cap.apps' => 'System Apps र Business Apps का app manifest मस्यौदा बनाउनुहोस्।',
            'cap.modules' => 'CRUD, dashboard, र queue/workflow module मस्यौदा बनाउनुहोस्।',
            'cap.views' => 'Table, form, र detail surface का view manifest मस्यौदा बनाउनुहोस्।',
            'cap.nav' => 'Wrapper confinement सहित navigation manifest मस्यौदा बनाउनुहोस्।',
            'cap.templates' => 'Non-executing template registry placeholders व्यवस्थापन गर्नुहोस्।',
            'cap.packages' => 'Package file नलेखी package artifact plans तयार गर्नुहोस्।',
            'cap.validate' => 'Publish governance अघि validate गर्नुहोस्; publish locked छ।',
            'projects' => 'Studio Projects',
            'project.note' => 'यो चरणमा project storage session-only हो। Database row बनाइँदैन।',
            'template_library' => 'Template Library',
            'drafts' => 'Drafts',
            'validation' => 'Validation',
            'compile_plan' => 'Compile Plan',
            'compile_graph' => 'Compile Graph',
            'artifact_types' => 'Artifact Types',
            'dependency_checks' => 'Dependency Checks',
            'conflict_checks' => 'Conflict Checks',
            'diff_readiness' => 'Diff Readiness',
            'diff_preview' => 'Diff Preview',
            'approval_gate' => 'Approval Gate',
            'approval_disabled' => 'Approval Disabled',
            'approval_ack_required' => 'Requires acknowledgment',
            'approval_ready' => 'Ready for approval',
            'approval_summary' => 'Approval Summary',
            'approval_result' => 'Approval Result',
            'validation_errors' => 'Validation Errors',
            'payload_preview' => 'Payload Preview',
            'decision' => 'Decision',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'reason' => 'Reason',
            'risk_acknowledged' => 'Risk Acknowledged',
            'risk_ack_label' => 'I acknowledge high-risk changes',
            'preview_approval' => 'Preview Approval',
            'high_risk_items' => 'High Risk Items',
            'blocked_items' => 'Blocked Items',
            'valid' => 'Valid',
            'invalid' => 'Invalid',
            'approval_id' => 'Approval ID',
            'approved_by' => 'Approved By',
            'approved_at' => 'Approved At',
            'snapshot_preview' => 'Snapshot Preview',
            'snapshot_ready' => 'Snapshot ready',
            'snapshot_disabled' => 'Snapshot disabled',
            'integrity_verified' => 'Integrity verified',
            'snapshot_unavailable' => 'Snapshot cannot be created until approval is valid',
            'preview_snapshot' => 'Preview Snapshot',
            'snapshot_id' => 'Snapshot ID',
            'snapshot_hash' => 'Snapshot Hash',
            'snapshot_integrity' => 'Snapshot Integrity',
            'snapshot_artifacts' => 'Snapshot Artifacts',
            'hash_verified' => 'Hash Verified',
            'approved' => 'Approved',
            'execution_preview' => 'Execution Preview',
            'preview_execution' => 'Preview Execution',
            'execution_ready' => 'Execution Ready',
            'execution_blocked' => 'Execution Blocked',
            'execution_simulated' => 'Simulation Complete',
            'execution_unavailable' => 'Execution blocked: approval not valid or blocked items present',
            'execution_summary' => 'Execution Summary',
            'execution_results' => 'Execution Results',
            'execution_id' => 'Execution ID',
            'can_execute' => 'Can Execute',
            'action' => 'Action',
            'ok' => 'OK',
            'skipped' => 'Skipped',
            'blocked' => 'Blocked',
            'reason_label' => 'Reason',
            'filters' => 'Filters',
            'risk_filter' => 'Risk',
            'ownership_filter' => 'Ownership',
            'drift_filter' => 'Drift',
            'filter_all' => 'All',
            'ownership_governance' => 'Ownership Governance',
            'drift_status' => 'Drift Status',
            'risk_escalation' => 'Risk Escalation',
            'human_diff_summary' => 'Human Diff Summary',
            'compile_snapshot_identity' => 'Compile Snapshot Identity',
            'compile_lineage' => 'Compile Lineage',
            'publish_governance' => 'Publish Governance',
            'rollback' => 'Rollback',
            'locked_future' => 'locked / future',
            'future' => 'future',
            'implemented' => 'implemented placeholder',
            'status' => 'स्थिति',
            'category' => 'Category',
            'outputs' => 'Outputs',
            'guardrails' => 'Guardrails',
            'form_title' => 'Draft Bundle Editor',
            'app_manifest' => 'App Manifest JSON',
            'module_manifest' => 'Module Manifest JSON',
            'view_manifest' => 'View Manifest JSON',
            'navigation_manifest' => 'Navigation Manifest JSON',
            'validate' => 'Validate',
            'compile' => 'Compile Plan',
            'results' => 'Validation Result',
            'check' => 'Check',
            'detail' => 'Detail',
            'pass' => 'Pass',
            'fail' => 'Fail',
            'errors' => 'Errors',
            'target_path' => 'Target Path',
            'artifact_id' => 'Artifact ID',
            'artifact_type' => 'Artifact Type',
            'layer' => 'Layer',
            'change_type' => 'Change Type',
            'risk_level' => 'Risk',
            'dependencies' => 'Dependencies',
            'target_exists' => 'Target Exists',
            'diff_status' => 'Diff Status',
            'before_hash' => 'Before Hash',
            'after_hash' => 'After Hash',
            'source_template' => 'Source Template',
            'content_hash' => 'Content Hash',
            'generated_by' => 'Generated By',
            'studio_project_id' => 'Studio Project',
            'studio_draft_id' => 'Studio Draft',
            'ownership_scope' => 'Ownership Scope',
            'upgrade_safe' => 'Upgrade Safe',
            'customization_zone' => 'Customization Zone',
            'diff_summary' => 'Diff Summary',
            'compile_id' => 'Compile ID',
            'bundle_hash' => 'Bundle Hash',
            'artifact_count' => 'Artifacts',
            'dependency_check_count' => 'Dependency Checks',
            'conflict_check_count' => 'Conflict Checks',
            'drift_check_count' => 'Drift Checks',
            'generated_at' => 'Generated At',
            'parent_compile_id' => 'Parent Compile',
            'schema_version' => 'Schema',
            'unique_conflicts' => 'Unique Conflicts',
            'total_conflict_instances' => 'Conflict Instances',
            'artifact_name' => 'Artifact',
            'owner' => 'Owner',
            'risk_score' => 'Risk Score',
            'dominant_reason' => 'Dominant Reason',
            'summary_group' => 'Summary Group',
            'bool.true' => 'true',
            'bool.false' => 'false',
            'summary' => 'Summary',
            'check.no_php.ok' => 'Draft bundle मा PHP code छैन।',
            'check.no_php.invalid' => 'Draft bundle मा PHP code राख्न मिल्दैन।',
            'error.no_php' => 'Studio drafts मा arbitrary PHP/code editing अनुमति छैन।',
            'flash.compile.ready' => 'Compile plan तयार भयो।',
            'flash.compile.blocked' => 'Validation pass नभएसम्म compile plan रोकिएको छ।',
            'flash.approval.ready' => 'Approval preview तयार भयो।',
            'flash.snapshot.ready' => 'Snapshot preview तयार भयो।',
            'flash.execution.ready' => 'Execution simulation तयार भयो।',
            'flash.apply.applied' => 'Apply सम्पन्न। Generated module सिर्जना गरियो।',
            'flash.apply.failed' => 'Apply असफल। पूर्वशर्त पूरा भएन वा artifact अवरुद्ध।',
            'approval.error.invalid_decision' => 'Approval decision approved वा rejected हुनुपर्छ।',
            'approval.error.blocked_present' => 'Blocked artifacts हुँदा approval disabled हुन्छ।',
            'approval.error.risk_ack_required' => 'High-risk artifacts का लागि acknowledgment चाहिन्छ।',
            'approval.error.reason_required' => 'High-risk approval का लागि reason चाहिन्छ।',
            'apply_snapshot' => 'Apply Snapshot',
            'apply_snapshot_title' => 'Apply Snapshot',
            'apply_status' => 'Apply स्थिति',
            'apply_id' => 'Apply ID',
            'apply_results' => 'Apply नतिजाहरू',
            'apply_applied' => 'लागू भयो',
            'apply_failed' => 'असफल',
            'apply_blocked' => 'अवरुद्ध: स्वीकृति वा पूर्वशर्त पूरा भएन',
            'apply_unavailable' => 'Execution simulation पास नभएसम्म Apply उपलब्ध छैन',
            'apply_btn' => 'Apply Snapshot',
            'apply_disabled_label' => 'Apply अक्षम',
            'precondition_failures' => 'पूर्वशर्त विफलताहरू',
            'precond.snapshot_already_applied' => 'यो Snapshot पहिलैनै Apply भइसकेको छ। डुप्लिकेट Apply ब्लक गरियो।',
            'precond.concurrent_apply_in_progress' => 'यो Snapshotका लागि अर्को Apply प्रगतिमा छ। केही समयपछि पुनः प्रयास गर्नुहोस्।',
            'precond.snapshot_not_found' => 'यो Compile ID का लागि सुरक्षित Snapshot भेटिएन।',
            'precond.generator_mismatch' => 'Snapshot फरक generator बाट बनेकोले Rollback रोकियो।',
            'precond.invalid_snapshot_context' => 'Snapshot context अमान्य भएकाले Rollback रोकियो।',
            'precond.unsafe_live_path' => 'Generated module path असुरक्षित भएकाले Rollback रोकियो।',
            'precond.unsafe_route_path' => 'Generated route path असुरक्षित भएकाले Rollback रोकियो।',
            'precond.no_compile_id' => 'Rollback का लागि Compile ID चाहिन्छ।',
            'safe_pipeline_analyze_required' => 'Publish gate चलाउनुअघि Analyze सफल हुनुपर्छ।',
            'safe_pipeline_confirm_required' => 'Apply अघि approval confirmation अनिवार्य छ।',
            'safe_pipeline_context_mismatch' => 'हालको draft context, approved gate context सँग मिलेन। Analyze र Publish Gate फेरि चलाउनुहोस्।',
            'publish_gate_token_missing' => 'Publish gate token भेटिएन। Publish Gate Check फेरि चलाउनुहोस्।',
            'publish_gate_token_mismatch' => 'Publish gate token mismatch भेटियो। Publish Gate Check फेरि चलाउनुहोस्।',
            'publish_decision_record_write_failed' => 'Publish decision सुरक्षित गर्न सकिएन। फेरि प्रयास गर्नुहोस् र storage permission जाँच गर्नुहोस्।',
            'snapshot_persisted' => 'Snapshot सुरक्षित',
            'post_publish_verification' => 'Post-Publish Verification',
            'verification_status' => 'Verification Status',
            'apply_mode_label' => 'कार्यान्वयन मोड',
            'apply_mode_simulation' => 'सिमुलेशन',
            'apply_mode_real' => 'वास्तविक',
            'apply_mode_first_apply' => 'First Apply',
            'apply_mode_disabled' => 'अक्षम',
            'transaction_steps' => 'Transaction Steps',
            'step_result' => 'Step नतिजा',
            'rollback_binding' => 'Rollback Binding',
            'rollback_binding_status' => 'Rollback स्थिति',
            'rollback_bound' => 'बाँधिएको (कार्यान्वयन नगरिएको)',
            'rollback_preview' => 'Rollback Preview',
            'rollback_summary' => 'Rollback सारांश',
            'planned_action' => 'योजनाबद्ध कार्य',
            'rollback_action' => 'Rollback कार्य',
            'reversible' => 'उल्टाउन मिल्छ',
            'non_reversible_label' => 'उल्टाउन मिल्दैन',
            'rollback_unavailable' => 'Rollback plan उपलब्ध छैन। पहिले snapshot बनाउनुहोस्।',
            'flash.rollback.ready' => 'Rollback preview गणना भयो। कुनै परिवर्तन भएको छैन।',
            'rollback_execute_title' => 'Rollback कार्यान्वयन',
            'rollback_execute_btn' => 'Rollback कार्यान्वयन गर्नुहोस्',
            'rollback_execute_status' => 'Rollback कार्यान्वयन स्थिति',
            'rollback_execute_result' => 'Rollback कार्यान्वयन नतिजा',
            'rollback_execute_steps' => 'Rollback Steps',
            'rollback_execute_no_binding' => 'Rollback binding फेला परेन। Rollback binding सहितको real apply record चाहिन्छ।',
            'rollback_execute_all_non_reversible' => 'सबै steps उल्टाउन मिल्दैन। Rollback अगाडि बढ्न सक्दैन।',
            'rollback_execute_non_reversible_warning' => 'सावधानी: केही steps उल्टाउन मिल्दैन र छोडिनेछ।',
            'rollback_execute_completed' => 'Rollback सम्पन्न भयो।',
            'rollback_execute_failed' => 'Rollback असफल। Step नतिजाहरू हेर्नुहोस्।',
            'rollback_execute_message' => 'सन्देश',
            'rollback_execute_apply_id_label' => 'Apply ID',
            'rollback_execute_compile_id_label' => 'Compile ID',
            'flash.rollback.executed' => 'Rollback सफलतापूर्वक कार्यान्वयन भयो।',
            'flash.rollback.execute_failed' => 'Rollback कार्यान्वयन असफल भयो। Step नतिजाहरू हेर्नुहोस्।',
            'flash.rollback.blocked' => 'Rollback अवरुद्ध। पूर्वशर्तहरू जाँच गर्नुहोस्।',
            'history_link' => 'इतिहास हेर्नुहोस्',
            'tab.edit' => 'सम्पादन गर्नुहोस्',
            'tab.analyze' => 'विश्लेषण गर्नुहोस्',
            'tab.changes' => 'परिवर्तन हरू',
            'tab.apply' => 'लागू गर्नुहोस्',
            'primary.edit_analyze' => 'विश्लेषण',
            'primary.analyze_review_changes' => 'परिवर्तन समीक्षा',
            'primary.changes_proceed_apply' => 'लागू चरणमा जानुहोस्',
            'primary.apply_apply_changes' => 'परिवर्तन लागू गर्नुहोस्',
            'editor_editing' => 'सम्पादन',
            'editor_creating' => 'सिर्जना',
            'editor_safe_mode' => 'सुरक्षित मोड',
            'studio_root_label' => 'स्टुडियो',
            'app_lifecycle_manager' => 'App Lifecycle Manager',
            'global_library_title' => 'Global System Library',
            'global_library_subtitle' => 'View र module लाई प्राथमिकता दिँदै आवश्यक पर्दा मात्र system registry metadata हेर्छ।',
            'library_role_helper' => 'Library: resource खोजेर load गर्नुहोस्।',
            'global_library_tree' => 'Tree',
            'global_library_detail' => 'Selection Detail',
            'global_library_counts' => 'Entity Counts',
            'global_library_empty' => 'Global library scan बाट कुनै node भेटिएन।',
            'global_library_no_selection' => 'Metadata हेर्न कुनै node छान्नुहोस्।',
            'global_library_load_into_studio' => 'Studio मा लोड गर्नुहोस्',
            'library_search_placeholder' => 'Views, modules, routes खोज्नुहोस्...',
            'library_filter_group_label' => 'लाइब्रेरी फिल्टरहरू',
            'library_filter_views' => 'भ्यूहरू',
            'library_filter_routes' => 'मार्गहरू',
            'library_filter_modules' => 'मोड्युलहरू',
            'library_filter_apps' => 'एप्स',
            'library_filter_recent' => 'हालको',
            'library_affordance_legend_label' => 'लाइब्रेरी नीति:',
            'library_affordance_legend_loadable' => 'सम्पादकमा लोडयोग्य',
            'library_affordance_legend_loadable_kinds' => 'app, module, view, dashboard',
            'library_affordance_legend_inspect_only' => 'केवल निरीक्षण',
            'library_affordance_legend_inspect_only_kinds' => 'route, nav',
            'library_load_app' => 'एप लोड गर्नुहोस्',
            'library_search_placeholder_full' => 'Views, modules, apps खोज्नुहोस्...',
            'app_settings' => 'एप सेटिङहरू',
            'app_key_label' => 'एप कुञ्जी',
            'app_display_name_label' => 'एप प्रदर्शन नाम',
            'app_type_label' => 'एप प्रकार',
            'app_version_label' => 'संस्करण',
            'mobile_mode_library' => 'Library',
            'mobile_mode_editor' => 'Editor',
            'mobile_mode_run' => 'Run',
            'library_advanced_registry_title' => 'Advanced Technical Registry',
            'library_detail_title' => 'Selection Detail',
            'library_detail_close' => 'Close',
            'library_detail_inspect_only_note' => 'Studio मा निरीक्षण गर्न सकिन्छ। यो artifact प्रकारलाई editor मा लोड गर्ने समर्थन अझै उपलब्ध छैन ({kind})।',
            'library_detail_owner' => 'Owner',
            'library_detail_owner_path' => 'Owner Path',
            'library_detail_resource_type' => 'Resource Type',
            'library_detail_owner_unknown' => 'Unknown owner',
            'library_detail_tools_title' => 'Eligible Tools',
            'library_detail_tools_loading' => 'Resource Explorer data लोड हुँदैछ...',
            'library_detail_tools_empty' => 'यो resource type का लागि कुनै eligible tool छैन।',
            'library_detail_tools_unavailable' => 'Resource Explorer metadata अहिले उपलब्ध छैन।',
            'library_recent_title' => 'Recent',
            'library_quick_load_title' => 'Quick Load',
            'library_quick_recent_views' => 'Recent Views',
            'library_quick_most_used_views' => 'Most used Views',
            'library_quick_last_edited_views' => 'Last edited Views',
            'content_outline_title' => 'लोड गरिएको सामग्री',
            'content_outline_back' => 'लाइब्रेरीमा फर्किनुहोस्',
            'content_outline_empty' => 'सुरु Library/Search बाट गर्नुहोस् र resource load गर्नुहोस्। परिवर्तन stage गरिएको छैन र apply action सक्रिय छैन।',
            'content_outline_summary' => 'हालको सामग्री',
            'content_outline_module_settings' => 'मोड्युल सेटिङहरू',
            'content_outline_fields' => 'फिल्डहरू',
            'content_outline_components' => 'कम्पोनेन्टहरू',
            'content_outline_layout' => 'लेआउट',
            'content_outline_governance' => 'शासन',
            'content_outline_selected_component' => 'छानिएको कम्पोनेन्ट',
            'content_outline_change_summary' => 'परिवर्तन सारांश',
            'content_outline_migration_plan' => 'स्थानान्तरण योजना',
            'content_outline_impact_analysis' => 'प्रभाव विश्लेषण',
            'content_outline_simulation_preview' => 'सिमुलेसन पूर्वावलोकन',
            'content_outline_table_mode' => 'टेबल सम्पादन मोड',
            'content_outline_table_mode_direct_db' => 'प्रत्यक्ष DB सम्पादन',
            'content_outline_table_mode_view_only' => 'View-आधारित सम्पादन',
            'content_outline_toggle_direct_db' => 'प्रत्यक्ष DB सम्पादनमा स्विच गर्नुहोस्',
            'content_outline_toggle_view_only' => 'View-आधारित सम्पादनमा स्विच गर्नुहोस्',
            'create_flow_title' => 'सिर्जना प्रवाह',
            'create_flow_empty' => 'नयाँ सामग्री बनाउन यी चरणहरू पालना गर्नुहोस्।',
            'editor_role_helper' => 'Editor: लोड गरिएको resource निरीक्षण गर्नुहोस्।',
            'downstream_tabs_helper' => 'Analysis, Changes, र Apply ट्याब resource लोड भएपछि मात्र अर्थपूर्ण हुन्छन्।',
            'create_flow_steps' => 'सिर्जना चरणहरू',
            'create_flow_intent_label' => 'सिर्जना लक्ष्य',
            'create_intent_label' => 'के सिर्जना गर्ने',
            'create_intent_app' => 'नयाँ एप',
            'create_intent_module' => 'नयाँ मोड्युल',
            'create_intent_view' => 'नयाँ भ्यू / पृष्ठ',
            'create_intent_navigation' => 'नेभिगेसन मात्र',
            'create_intent_dashboard' => 'ड्यासबोर्ड / चार्ट',
            'create_flow_step_intent' => 'सिर्जना लक्ष्य छान्नुहोस्',
            'create_flow_step_module' => 'मोड्युल कन्फिगर गर्नुहोस्',
            'create_flow_step_view' => 'भ्यू प्रकार छान्नुहोस्',
            'create_flow_step_table_mode' => 'टेबल सम्पादन मोड छान्नुहोस्',
            'create_flow_step_fields' => 'फिल्डहरू परिभाषित गर्नुहोस्',
            'create_flow_step_layout' => 'लेआउट तयार गर्नुहोस्',
            'create_flow_step_governance' => 'गभर्नेन्स समीक्षा गर्नुहोस्',
            'create_flow_actions' => 'द्रुत कार्यहरू',
            'create_flow_action_configure_navigation' => 'नेभिगेसन कन्फिगर गर्नुहोस्',
            'create_flow_action_set_dashboard' => 'ड्यासबोर्ड प्रकार सेट गर्नुहोस्',
            'create_flow_action_add_table' => 'टेबल कम्पोनेन्ट थप्नुहोस्',
            'create_flow_action_add_form' => 'फारम कम्पोनेन्ट थप्नुहोस्',
            'create_flow_action_add_kpi' => 'KPI कम्पोनेन्ट थप्नुहोस्',
            'create_flow_action_add_text' => 'टेक्स्ट कम्पोनेन्ट थप्नुहोस्',
            'create_flow_action_direct_db' => 'प्रत्यक्ष DB टेबल सम्पादन प्रयोग गर्नुहोस्',
            'create_flow_action_view_mode' => 'View-आधारित टेबल सम्पादन प्रयोग गर्नुहोस्',
            'library_search_results_title' => 'Search Results',
            'library_search_results_empty' => 'No matching views found.',
            'library_load_table_only' => 'Load Table Only',
            'library_load_form_only' => 'Load Form Only',
            'library_all_views_title' => 'All Views',
            'library_all_modules_title' => 'All Modules',
            'library_system_registry_title' => 'System Registry (Advanced)',
            'advanced_debug_title' => 'Advanced / Debug',
            'advanced_debug_help' => 'JSON editors advanced inspection का लागि यहाँ राखिएका छन्।',
            'analyze_summary_title' => 'द्रुत विश्लेषण सारांश',
            'analyze_summary_structure_ok' => 'संरचना ठीक',
            'analyze_summary_structure_pending' => 'संरचना अधूरो',
            'analyze_summary_missing_bindings' => 'हराइएको बाइन्डिङहरू',
            'analyze_summary_bindings_ok' => 'बाइन्डिङहरू पूर्ण',
            'analyze_summary_blockers' => 'हालका अवरोधकहरू',
            'analyze_show_technical_details' => '[प्राविधिक विवरण देखाउनुहोस्]',
            'apply_next_step_message' => 'अगिलो चरण: लागू गर्नु अघि विश्लेषण चलाउनुहोस्',
            'apply_next_step_ready_message' => 'अगिलो चरण: लागू गर्नु अघि परिवर्तनहरू समीक्षा र अनुमोदन पुष्टि गर्नुहोस्',
            'apply_step_1_edit' => 'चरण १: सम्पादन गर्नुहोस्',
            'apply_step_2_analyze' => 'चरण २: विश्लेषण गर्नुहोस्',
            'apply_step_3_changes' => 'चरण ३: परिवर्तन हरू',
            'apply_step_4_apply' => 'चरण ४: लागू गर्नुहोस्',
            'global_count_apps' => 'एपहरू',
            'global_count_modules' => 'मोड्युलहरू',
            'global_count_views' => 'भ्यूहरू',
            'global_count_dashboards' => 'ड्यासबोर्डहरू',
            'global_count_routes' => 'मार्गहरू',
            'global_count_navs' => 'नेभिगेसन',
            'global_count_plugins' => 'प्लगइनहरू',
            'data_contract_title' => 'Data Contract',
            'data_contract_subtitle' => 'छानिएको Studio bundle का bindings, fields, र data sources निकालिएको जानकारी।',
            'data_contract_confidence' => 'आत्मविश्वास',
            'data_contract_fields' => 'फिल्डहरू',
            'data_contract_bindings' => 'बाइन्डिङहरू',
            'data_contract_sources' => 'डेटा स्रोतहरू',
            'data_contract_required_fields' => 'आवश्यक फिल्डहरू',
            'data_contract_empty' => 'अहिलेसम्म data contract निकालिएको छैन।',
            'data_contract_checks' => 'सत्यापन जाँचहरू',
            'data_contract_check_status' => 'स्थिति',
            'data_contract_check_detail' => 'विवरण',
            'data_contract_status.pass' => 'पास',
            'data_contract_status.fail' => 'असफल',
            'data_contract_confidence.full' => 'पूर्ण',
            'data_contract_confidence.partial' => 'आंशिक',
            'data_contract_confidence.none' => 'कुनै पनि छैन',
            'data_contract_check.bindings_extracted' => 'बाइन्डिङहरू निकालिएका',
            'data_contract_check.bindings_known' => 'बाइन्डिङ मार्गहरू ज्ञात',
            'data_contract_check.required_fields_resolved' => 'आवश्यक फिल्डहरू समाधान भएका',
            'data_contract_check.data_sources_declared' => 'डेटा स्रोतहरू घोषणा भएका',
            'dependency_graph_title' => 'निर्भरता ग्राफ',
            'dependency_graph_subtitle' => 'field -> view -> dashboard -> workflow सम्बन्ध ट्र्याक गरिन्छ।',
            'dependency_graph_nodes' => 'नोडहरू',
            'dependency_graph_edges' => 'किनाराहरू',
            'dependency_graph_depends_on' => 'मा निर्भर',
            'dependency_graph_affects' => 'प्रभाव',
            'dependency_graph_issues' => 'ग्राफ समस्याहरू',
            'dependency_graph_none' => 'Dependency graph data उपलब्ध छैन।',
            'dependency_graph_edge_table' => 'किनारा सूची',
            'dependency_graph_from' => 'बाट',
            'dependency_graph_to' => 'लाई',
            'dependency_graph_relation' => 'सम्बन्ध',
            'generated_library_title' => 'उत्पन्न एप्स लाइब्रेरी',
            'library_publish_state' => 'प्रकाशन स्थिति',
            'library_publish_approved' => 'अनुमोदित',
            'library_publish_pending' => 'पेंडिङ',
            'library_empty' => 'Registry मा generated modules फेला परेन।',
            'library_app' => 'एप',
            'library_module' => 'मोड्युल',
            'library_route' => 'मार्ग',
            'library_version' => 'संस्करण',
            'library_snapshot' => 'स्न्यापशट',
            'library_last_updated' => 'अन्तिम अपडेट',
            'inspect' => 'निरीक्षण गर्नुहोस्',
            'load_into_studio' => 'Studio मा लोड गर्नुहोस्',
            'import_existing_view' => 'अवस्थित भ्यू आयात गर्नुहोस्',
            'library_loading' => 'लोड गर्दैछ...',
            'library_load_failed' => 'Module लाई Studio मा लोड गर्न सकिएन।',
            'library_load_failed_with_reason' => 'Studio मा लोड गर्न असफल भयो ({reason})।',
            'library_load_unsupported_kind' => 'यो artifact प्रकार Phase 1A मा लोड गर्न मिल्दैन ({kind})।',
            'library_load_invalid_response' => 'लोड endpoint बाट अमान्य प्रतिक्रिया आयो।',
            'library_import_partial' => 'Partial import loaded. Reapply अघि inferred layout समीक्षा गर्नुहोस्।',
            'library_import_complete' => 'Existing view Studio मा import गरियो।',
            'studio_mode_label' => 'Studio मोड',
            'studio_mode_create_new' => 'नयाँ_सिर्जना',
            'studio_mode_edit_existing' => 'अवस्थित_सम्पादन',
            'studio_mode_switch_create' => 'सिर्जना प्रवाह',
            'studio_mode_switch_upgrade' => 'अपग्रेड प्रवाह',
            'tier_label' => 'विवरण स्तर',
            'tier_simple' => 'सरल',
            'tier_guided' => 'निर्देशित',
            'tier_advanced' => 'उन्नत',
            'structured_editor_title' => 'संरचित मोड्युल एडिटर',
            'structured_editor_note' => 'मोड्युल सेटिङहरू लाई form बाट सम्पादन गर्नुहोस्। JSON internal रूपमा generate हुन्छ।',
            'editor_title_create' => 'नयाँ बन्डल सिर्जना एडिटर',
            'editor_title_upgrade' => '{type} अपग्रेड एडिटर',
            'editor_note_create' => 'मोड्युल सेटिङहरूलाई form नियन्त्रणबाट सम्पादन गर्नुहोस्। JSON internal रूपमा generate हुन्छ।',
            'editor_note_upgrade' => 'लोड गरिएको {type} सामग्रीलाई form नियन्त्रणबाट अपडेट गर्नुहोस्। apply अघि validation र impact विश्लेषण जाँच गर्नुहोस्।',
            'editor_note_upgrade_app' => 'परिवर्तन apply गर्नुअघि app manifest, route ownership, र governance metadata समीक्षा गर्नुहोस्।',
            'editor_note_upgrade_module' => 'मोड्युल सेटिङ र route सावधानीपूर्वक अपडेट गर्नुहोस्। apply अघि downstream dependency जाँच गर्नुहोस्।',
            'editor_note_upgrade_view' => 'लोड गरिएको view का fields, bindings, र layout सुधार गर्नुहोस्। downstream safety का लागि analysis चलाउनुहोस्।',
            'editor_note_upgrade_navigation' => 'route leakage रोक्न navigation label, target, icon, र visibility सावधानीपूर्वक अपडेट गर्नुहोस्।',
            'editor_note_upgrade_db_table' => 'schema परिवर्तन सावधानीपूर्वक समीक्षा गर्नुहोस्। apply अघि migration र impact analysis पुष्टि गर्नुहोस्।',
            'editor_note_upgrade_unknown' => 'लोड गरिएको सामग्री सावधानीपूर्वक समीक्षा गरी apply अघि validation र analysis चलाउनुहोस्।',
            'module_settings' => 'मोड्युल सेटिङहरू',
            'module_key' => 'मोड्युल कुञ्जी',
            'module_display_name' => 'मोड्युल प्रदर्शन नाम',
            'module_type' => 'मोड्युल प्रकार',
            'module_description' => 'मोड्युल विवरण',
            'route_path' => 'मार्ग पथ',
            'field_editor' => 'फिल्ड एडिटर',
            'field_name' => 'फिल्ड नाम',
            'field_type' => 'प्रकार',
            'field_required' => 'आवश्यक',
            'field_default' => 'पूर्वनिर्धारित',
            'add_field' => 'फिल्ड थप्नुहोस्',
            'remove_field' => 'हटाउनुहोस्',
            'view_config_editor' => 'भ्यू कन्फिग एडिटर',
            'visual_builder_title' => 'भिजुअल बिल्डर',
            'visual_builder_note' => '12-column grid मा drag/drop र resize गरेर view layout बनाउनुहोस्।',
            'visual_builder_templates_title' => 'टेम्प्लेटबाट सुरु गर्नुहोस्',
            'visual_builder_recommended_templates_title' => 'सिफारिस गरिएका टेम्प्लेटहरू',
            'visual_builder_all_templates_title' => 'सबै टेम्प्लेटहरू',
            'visual_builder_save_template' => 'टेम्प्लेटको रूपमा बचत गर्नुहोस्',
            'visual_builder_template_name_prompt' => 'टेम्प्लेट नाम प्रविष्ट गर्नुहोस्',
            'visual_builder_my_templates_title' => 'मेरो टेम्प्लेटहरू',
            'visual_builder_suggestions_title' => 'अर्का सिफारिस गरिएका चरणहरू',
            'visual_builder_suggestion_add_kpi' => 'KPI थप्नुहोस्',
            'visual_builder_suggestion_add_table' => 'टेबल थप्नुहोस्',
            'visual_builder_suggestion_add_form' => 'फारम थप्नुहोस्',
            'visual_builder_suggestion_use_template' => 'सिफारिस गरिएको टेम्प्लेट प्रयोग गर्नुहोस्',
            'visual_builder_suggestion_none' => 'तपाईंले बनाउँदै जाँदा सुझावहरू अपडेट हुन्छन्।',
            'visual_builder_views_title' => 'दृश्यहरू',
            'visual_builder_add_view' => '+ दृश्य थप्नुहोस्',
            'visual_builder_view_name_prompt' => 'दृश्य नाम प्रविष्ट गर्नुहोस्',
            'visual_builder_view_default_name' => 'दृश्य',
            'visual_builder_view_none' => 'अहिलेसम्म कुनै दृश्य छैन।',
            'visual_builder_export_app' => 'एप निर्यात गर्नुहोस्',
            'visual_builder_import_app' => 'एप बन्डल आयात गर्नुहोस्',
            'visual_builder_export_missing' => 'निर्यात गर्न सामग्री उपलब्ध छैन।',
            'visual_builder_import_invalid' => 'एप बन्डल JSON अमान्य छ।',
            'visual_builder_import_failed' => 'एप बन्डल आयात असफल भयो।',
            'visual_builder_import_success' => 'एप बन्डल Studio मा लोड गरियो।',
            'visual_builder_export_filename' => 'sample_app_bundle.json',
            'component_config.open_view' => 'दृश्य खोल्नुहोस्',
            'component_config.open_view_none' => 'लिङ्क गरिएको दृश्य छैन',
            'visual_builder_template_kpi_grid' => 'KPI ड्यासबोर्ड',
            'visual_builder_template_table_view' => 'टेबल दृश्य',
            'visual_builder_template_form_entry' => 'फारम प्रविष्टि',
            'visual_builder_template_replace_confirm' => 'हालको लेआउट प्रतिस्थापन गर्ने?',
            'visual_builder_template_metric_1' => 'मेट्रिक १',
            'visual_builder_template_metric_2' => 'मेट्रिक २',
            'visual_builder_template_metric_3' => 'मेट्रिक ३',
            'visual_builder_template_metric_4' => 'मेट्रिक ४',
            'visual_builder_phase1_note' => 'यस चरणमा स्थिर grid row र component layout बनाउनुहोस् (drag र resize निष्क्रिय छन्)।',
            'visual_builder_palette' => 'Component Palette',
            'visual_builder_canvas' => 'Grid Canvas',
            'visual_builder_items' => 'Layout Items',
            'visual_builder_preview_toggle' => 'Preview Mode',
            'visual_builder_empty' => 'Layout सुरु गर्न components यहाँ drop गर्नुहोस्।',
            'visual_builder_binding_schema' => 'बाइन्डिङ स्कीमा',
            'visual_builder_relations' => 'कम्पोनेन्ट सम्बन्धहरू',
            'visual_builder_add_relation' => 'सम्बन्ध थप्नुहोस्',
            'visual_builder_relation_source' => 'स्रोत',
            'visual_builder_relation_target' => 'लक्ष्य',
            'visual_builder_relation_type' => 'सम्बन्ध प्रकार',
            'visual_builder_relation_affects_table' => 'टेबल प्रभावित गर्छ',
            'visual_builder_relation_filter_to_table' => 'फिल्टर -> टेबल',
            'visual_builder_relation_filter_to_kpi' => 'फिल्टर -> KPI',
            'visual_builder_relation_form_refresh_table' => 'फारम -> टेबल (ताजा गर्नुहोस्)',
            'visual_builder_relation_table_to_kpi_derived' => 'टेबल -> KPI (व्युत्पन्न)',
            'visual_builder_relation_empty' => 'कुनै सम्बन्ध कन्फिगर गरिएको छैन।',
            'visual_builder_group' => 'समूह',
            'visual_builder_reorder_up' => 'माथिको',
            'visual_builder_reorder_down' => 'तलको',
            'visual_builder_warning_overlap' => 'Overlap अनुमति छैन। खाली ठाउँमा move वा resize गर्नुहोस्।',
            'visual_builder_warning_bounds' => 'Item grid bounds बाहिर छ। 12 columns भित्र राख्नुहोस्।',
            'visual_builder_warning_binding_invalid' => 'Compile वा validate अघि binding errors सच्याउनुहोस्।',
            'component_settings_title' => 'कम्पोनेन्ट सेटिङहरू',
            'visual_builder_component_config' => 'कम्पोनेन्ट कन्फिग',
            'visual_builder_select_component_hint' => 'Layout item छानेर component props सम्पादन गर्नुहोस्।',
            'visual_builder_binding_source' => 'डेटा बाइन्डिङ स्रोत',
            'visual_builder_binding_picker' => 'बाइन्डिङ पिकर',
            'visual_builder_binding_placeholder' => 'उदाहरण: module.fields.part_name',
            'visual_builder_binding_error.invalid_path' => 'बाइन्डिङ पथ schema मा उपलब्ध छैन।',
            'visual_builder_binding_error.table' => 'टेबल कम्पोनेन्ट ले module.rows मा bind हुनुपर्छ।',
            'visual_builder_binding_error.form' => 'फारम कम्पोनेन्ट ले module.fields मा bind हुनुपर्छ।',
            'visual_builder_binding_error.kpi' => 'KPI कम्पोनेन्ट ले module.metrics.* मा bind हुनुपर्छ।',
            'visual_builder_binding_error.text' => 'पाठ कम्पोनेन्ट ले module.description मा bind हुनुपर्छ।',
            'visual_builder_add_component' => 'घटक थप्नुहोस्',
            'visual_builder_preview' => 'पूर्वावलोकन',
            'visual_builder_default_kpi' => 'कुल रेकर्ड',
            'visual_builder_default_table' => 'रेकर्डहरू',
            'visual_builder_default_form' => 'रेकर्ड फारम',
            'visual_builder_default_text' => 'टेक्स्ट ब्लक',
            'component_config.item_id' => 'Item ID',
            'component_config.width' => 'चौडाइ',
            'component_config.width_narrower' => 'साँघुरो',
            'component_config.width_wider' => 'फराकिलो',
            'component_config.filter_label' => 'फिल्टर लेबल',
            'component_config.filter_field' => 'फिल्टर फिल्ड',
            'component_config.filter_placeholder' => 'प्लेसहोल्डर',
            'component_config.table_title' => 'टेबल शीर्षक',
            'component_config.table_rows' => 'नमुना पङ्क्तिहरू',
            'component_config.table_density' => 'घनत्व',
            'component_config.form_title' => 'फारम शीर्षक',
            'component_config.form_submit_label' => 'जमा गर्नुहोस् लेबल',
            'component_config.default_submit' => 'जमा गर्नुहोस्',
            'component_config.form_show_required' => 'आवश्यक सूचक देखाउने',
            'component_config.kpi_label' => 'KPI लेबल',
            'component_config.kpi_value' => 'KPI मान',
            'component_config.kpi_delta' => 'KPI डेल्टा',
            'component_config.text_title' => 'पाठ शीर्षक',
            'component_config.text_body' => 'पाठ निकाय',
            'component_config.text_align' => 'पाठ संरेखण',
            'component_config.option.compact' => 'कम्प्याक्ट',
            'component_config.option.comfortable' => 'आरामदायक',
            'component_config.option.left' => 'बायाँ',
            'component_config.option.center' => 'केन्द्र',
            'component_config.option.right' => 'दायाँ',
            'component.table' => 'टेबल',
            'component.filter' => 'फिल्टर',
            'component.form' => 'फारम',
            'component.kpi_card' => 'KPI कार्ड',
            'component.text_block' => 'पाठ ब्लक',
            'component.duplicate' => 'डुप्लिकेट',
            'component.delete' => 'मेटाउनुहोस्',
            'component.type.kpi_card' => 'KPI',
            'component.type.table' => 'टेबल',
            'component.type.form' => 'फारम',
            'component.type.filter' => 'फिल्टर',
            'component.type.text_block' => 'पाठ',
            'visual_builder_add_component_inline' => '+ Component थप्नुहोस्',
            'feedback.add' => 'Component थपियो',
            'feedback.move' => 'Component सारियो',
            'feedback.resize' => 'Component आकार परिवर्तन भयो',
            'feedback.template' => 'Template लागू गरियो',
            'builder_item_move' => 'सार्नुहोस्',
            'builder_item_remove' => 'हटाउनुहोस्',
            'view_type' => 'भ्यू प्रकार',
            'view_type_table' => 'टेबल',
            'view_type_form' => 'फारम',
            'view_type_dashboard' => 'ड्यासबोर्ड',
            'table_edit_mode_label' => 'टेबल सम्पादन मोड',
            'table_edit_mode_view_only' => 'View-आधारित सम्पादन',
            'table_edit_mode_direct_db' => 'प्रत्यक्ष DB सम्पादन',
            'table_edit_mode_note_view_only' => 'View-आधारित मोडमा view columns र layout विकल्पहरू देखिन्छन्।',
            'table_edit_mode_note_direct_db' => 'प्रत्यक्ष DB मोडमा view-only विकल्पहरू लुकाइन्छन् र schema-safe table सम्पादनमा ध्यान दिइन्छ।',
            'db_editor_title' => 'DB टेबल एडिटर',
            'db_editor_loading' => 'तालिका डेटा लोड गर्दैछ…',
            'db_editor_load_failed' => 'तालिका डेटा लोड गर्न असफल।',
            'db_editor_schema_title' => 'स्किमा',
            'db_editor_rows_title' => 'डेटा पङ्क्तिहरू',
            'db_editor_table_type_label' => 'तालिका',
            'db_editor_orders_table' => 'अर्डरहरू',
            'db_editor_parts_table' => 'पार्टहरू',
            'db_editor_add_row' => 'पङ्क्ति थप्नुहोस्',
            'db_editor_save_row' => 'सेभ गर्नुहोस्',
            'db_editor_cancel' => 'रद्द गर्नुहोस्',
            'db_editor_delete_row' => 'मेट्नुहोस्',
            'db_editor_confirm_delete' => 'यो पङ्क्ति मेट्ने?',
            'db_editor_empty' => 'कुनै पङ्क्ति फेला परेन।',
            'db_editor_note' => 'स्किमा-सुरक्षित सम्पादन मात्र। id र created_at कलमहरू पढ्न मात्र हुन्।',
            'db_editor_field_col' => 'फिल्ड',
            'db_editor_type_col' => 'प्रकार',
            'db_editor_nullable_col' => 'Nullable',
            'db_editor_no_app' => 'प्रत्यक्ष DB सम्पादन सक्षम गर्न पहिले मोड्युल लोड गर्नुहोस्।',
            'loaded_type_app' => 'एप',
            'loaded_type_module' => 'मोड्युल',
            'loaded_type_view' => 'भ्यू',
            'loaded_type_dashboard' => 'ड्यासबोर्ड',
            'loaded_type_navigation' => 'नेभिगेसन',
            'loaded_type_db_table' => 'DB टेबल',
            'loaded_type_unknown' => 'अज्ञात',
            'loaded_type_label' => 'सम्पादन गर्दै',
            'loaded_context_label' => 'लोड गरिएको सन्दर्भ',
            'loaded_context_source' => 'स्रोत',
            'loaded_context_source_library' => 'लाइब्रेरी',
            'loaded_context_source_import' => 'इम्पोर्ट',
            'loaded_context_unknown' => 'अज्ञात',
            'surface_exposure_title' => 'Surface Exposure',
            'surface_exposure_subtitle' => 'View-route-navigation composition status.',
            'surface_exposure_summary_label' => 'Relationship',
            'surface_exposure_aspect' => 'Aspect',
            'surface_exposure_value' => 'Value',
            'surface_exposure_state' => 'State',
            'surface_exposure_aspect_view' => 'View',
            'surface_exposure_aspect_route' => 'Route',
            'surface_exposure_aspect_navigation' => 'Navigation',
            'surface_exposure_aspect_policy' => 'Editability',
            'surface_exposure_value_none' => 'None',
            'surface_exposure_policy_inspect_only' => 'Inspect-only',
            'surface_exposure_policy_route_linkable' => 'Route: propose link',
            'surface_exposure_state_connected' => 'connected',
            'surface_exposure_state_missing_route' => 'missing route',
            'surface_exposure_state_missing_nav' => 'missing navigation',
            'surface_exposure_state_nav_unknown_route' => 'navigation target unknown',
            'surface_exposure_state_ambiguous_conflicting' => 'ambiguous/conflicting',
            'surface_exposure_state_inspect_only' => 'inspect-only',
            'surface_exposure_diag_connected' => 'View route and navigation are connected.',
            'surface_exposure_diag_missing_route' => 'This view is not exposed by a route.',
            'surface_exposure_diag_missing_nav' => 'This view has a route but no navigation entry.',
            'surface_exposure_diag_nav_unknown_route' => 'Navigation points to a missing or unknown route.',
            'surface_exposure_diag_ambiguous_conflicting' => 'Multiple or conflicting route/navigation candidates detected; manual resolution required.',
            'route_link_title' => 'Propose Route Link',
            'route_link_subtitle' => 'Connect this view to a route path. Only applies to Studio-generated artifacts.',
            'route_link_upgrade_subtitle' => 'Change the existing route path for this view. Confirm the change is intentional.',
            'route_link_label' => 'Route Path',
            'route_link_placeholder' => '/apps/generated/my_app/my_module',
            'route_link_upgrade_acknowledge' => 'I confirm this is an intentional route change (upgrade mode).',
            'route_link_analyze_btn' => 'Analyze',
            'route_link_apply_btn' => 'Apply',
            'route_link_cancel_btn' => 'Cancel',
            'route_link_analyzing' => 'Analyzing...',
            'route_link_applying' => 'Applying...',
            'route_link_changes_title' => 'Proposed Changes',
            'route_link_changes_file' => 'File',
            'route_link_changes_field' => 'Field',
            'route_link_changes_before' => 'Before',
            'route_link_changes_after' => 'After',
            'route_link_changes_none' => 'No changes.',
            'route_link_gate_ready' => 'READY',
            'route_link_gate_blocked' => 'BLOCKED',
            'route_link_success' => 'Route link applied successfully.',
            'route_link_error_blocked' => 'Apply blocked. Review errors above.',
            'route_link_error_failed' => 'Apply failed. See error details.',
            'route_link_open_btn' => 'Propose Route Link',
            'route_link_change_btn' => 'Propose Route Change',
            'nav_link_title' => 'Nav Link प्रस्ताव',
            'nav_link_subtitle' => 'यो viewका लागि navigation entry थप्नुहोस्। केवल Studio-generated artifacts मा लागू।',
            'nav_link_upgrade_subtitle' => 'यो viewको navigation URL अपडेट गर्नुहोस्। परिवर्तन जानाजानी छ भनी पुष्टि गर्नुहोस्।',
            'nav_link_url_label' => 'Nav URL',
            'nav_link_label_label' => 'Nav Label',
            'nav_link_placeholder' => '/apps/generated/my_app/my_module',
            'nav_link_label_placeholder' => 'मेरो मड्युल',
            'nav_link_upgrade_acknowledge' => 'यो जानाजानी navigation परिवर्तन (update mode) हो भनी म पुष्टि गर्छु।',
            'nav_link_analyze_btn' => 'विश्लेषण',
            'nav_link_apply_btn' => 'लागू गर्नुहोस्',
            'nav_link_cancel_btn' => 'रद्द गर्नुहोस्',
            'nav_link_analyzing' => 'विश्लेषण हुँदैछ...',
            'nav_link_applying' => 'लागू हुँदैछ...',
            'nav_link_changes_title' => 'प्रस्तावित परिवर्तनहरू',
            'nav_link_changes_file' => 'फाइल',
            'nav_link_changes_field' => 'फिल्ड',
            'nav_link_changes_before' => 'पहिले',
            'nav_link_changes_after' => 'पछि',
            'nav_link_changes_none' => 'परिवर्तन छैन।',
            'nav_link_gate_ready' => 'तयार',
            'nav_link_gate_blocked' => 'अवरुद्ध',
            'nav_link_success' => 'Nav link सफलतापूर्वक लागू भयो।',
            'nav_link_error_blocked' => 'लागू अवरुद्ध। माथिका त्रुटिहरू हेर्नुहोस्।',
            'nav_link_error_failed' => 'लागू असफल। विवरण हेर्नुहोस्।',
            'nav_link_open_btn' => 'Nav Link प्रस्ताव',
            'nav_link_update_btn' => 'Nav अपडेट प्रस्ताव',
            'navigation_label' => 'लेबल',
            'navigation_target' => 'लक्ष्य URL',
            'navigation_icon' => 'आइकन',
            'navigation_order' => 'क्रम',
            'navigation_visibility' => 'दृश्यमान',
            'navigation_section_operations' => 'अपरेशनहरू',
            'navigation_section_apps' => 'एपहरू',
            'navigation_section_admin_system' => 'प्रशासन / प्रणाली',
            'structured_mapping_ok' => 'संरचित म्यापिङ सक्रिय',
            'change_summary_title' => 'परिवर्तन सारांश',
            'change_summary_empty' => 'Stage गरिएका परिवर्तन छैनन्।',
            'impact_low' => 'कम',
            'impact_medium' => 'मध्यम',
            'impact_high' => 'उच्च',
            'change_severity_safe' => 'सुरक्षित',
            'change_severity_additive' => 'योजक',
            'change_severity_breaking' => 'विभाजक',
            'breaking_changes_warning' => 'Breaking changes भेटियो। यी परिवर्तनहरूले मौजूदा डेटा वा integration मा समस्या आउन सक्छ। Apply अगाडि ध्यानपूर्वक समीक्षा गर्नुहोस्।',
            'breaking_changes_blocked' => 'सबै breaking changes स्वीकार नगरिएसम्म Apply अवरुद्ध छ।',
            'rollback_snapshot_notice' => 'Breaking changes apply गर्नुअगाडि rollback snapshot स्वचालित रूपमा सिर्जना हुनेछ।',
            'change_requires_ack' => 'High-risk changes भेटियो। Compile अघि reason र risk acknowledgment चाहिन्छ।',
            'migration_plan_title' => 'Migration Plan',
            'migration_action' => 'Migration Action',
            'migration_strategy' => 'Strategy',
            'migration_warning_destructive' => 'Destructive migration भेटियो। Apply अघि override र reason चाहिन्छ।',
            'migration_empty' => 'Schema migration आवश्यक छैन।',
            'migration_override_label' => 'Destructive migration override अनुमति दिनुहोस्',
            'migration_override_reason' => 'Migration Override Reason',
            'migration.strategy.safe' => 'safe',
            'migration.strategy.destructive' => 'destructive',
            'migration.strategy.requires_migration' => 'requires_migration',
            'migration.error.override_required' => 'Destructive migration का लागि explicit override चाहिन्छ।',
            'migration.error.override_reason_required' => 'Destructive migration का लागि override reason चाहिन्छ।',
            'impact_analysis_title' => 'Impact Analysis',
            'impact_change' => 'Change',
            'impact_affected_components' => 'Affected Components',
            'impact_severity' => 'Severity',
            'impact_warning_high' => 'High impact भेटियो। Apply अघि extra confirmation र impact acknowledgment चाहिन्छ।',
            'impact_empty' => 'Downstream impact भेटिएन।',
            'impact_confirmation_label' => 'High-impact परिवर्तन apply गर्न म पुष्टि गर्छु',
            'impact_ack_label' => 'Downstream impact म स्वीकार गर्छु',
            'impact.error.confirmation_required' => 'High-impact apply का लागि extra confirmation चाहिन्छ।',
            'impact.error.ack_required' => 'High-impact apply का लागि impact acknowledgment चाहिन्छ।',
            'simulation_preview_title' => 'Simulation Preview',
            'simulation_views_after' => 'Views After',
            'simulation_broken_views' => 'Broken Views',
            'simulation_removed_fields' => 'Removed Fields',
            'simulation_new_fields' => 'New Fields',
            'simulation_warning' => 'Broken views भेटियो। Apply अघि explicit simulation override र reason चाहिन्छ।',
            'simulation_empty' => 'Simulation warning भेटिएन।',
            'simulation_override_label' => 'Broken views का लागि simulation override अनुमति दिनुहोस्',
            'simulation_override_reason' => 'Simulation Override Reason',
            'simulation_reason_missing_required_field' => 'View मा required field छैन',
            'simulation_reason_filter_references_removed_field' => 'Filter ले हटाइएको field प्रयोग गरेको छ',
            'simulation_reason_navigation_invalid_route' => 'Navigation ले invalid route देखाएको छ',
            'simulation.error.override_required' => 'Broken views का लागि explicit simulation override चाहिन्छ।',
            'simulation.error.reason_required' => 'Broken views का लागि simulation override reason चाहिन्छ।',
            'error.upgrade_baseline_required' => 'Upgrade flow मा compile वा apply अघि loaded baseline चाहिन्छ।',
            'error.layout_invalid_type' => 'Layout type grid हुनुपर्छ।',
            'error.layout_invalid_columns' => 'Layout 12 columns हुनुपर्छ।',
            'error.layout_invalid_rows' => 'Layout rows परिभाषित हुनुपर्छ।',
            'error.layout_missing_items' => 'Layout मा कम्तिमा एउटा item हुनुपर्छ।',
            'error.layout_invalid_item' => 'Layout मा invalid item entry छ।',
            'error.layout_item_id_required' => 'प्रत्येक layout item मा id चाहिन्छ।',
            'error.layout_item_duplicate_id' => 'Layout item id unique हुनुपर्छ।',
            'error.layout_invalid_component' => 'Layout मा unsupported component छ।',
            'error.layout_missing_component_props' => 'Layout component props मा required मानहरू छैनन्।',
            'error.layout_missing_data_binding' => 'प्रत्येक layout item मा data binding source चाहिन्छ।',
            'error.layout_invalid_data_binding' => 'Data binding path dot notation मा हुनुपर्छ (उदाहरण: module.fields.part_name)।',
            'error.layout_binding_type_table' => 'Table component binding module.rows हुनुपर्छ।',
            'error.layout_binding_type_filter' => 'Filter component binding module.rows हुनुपर्छ।',
            'error.layout_binding_type_form' => 'Form component binding module.fields हुनुपर्छ।',
            'error.layout_binding_type_kpi' => 'KPI component binding module.metrics.* हुनुपर्छ।',
            'error.layout_binding_type_text' => 'Text component binding module.description हुनुपर्छ।',
            'error.layout_invalid_group' => 'प्रत्येक layout item मा मान्य group हुनुपर्छ।',
            'error.layout_invalid_relation' => 'Layout relations ले मान्य source र target items जनाउनुपर्छ।',
            'error.layout_out_of_bounds' => 'Layout items grid सीमा भित्र हुनुपर्छ।',
            'error.layout_overlap' => 'Layout items overlap गर्न मिल्दैन।',
            'app_registry_empty' => 'अहिलेसम्म कुनै generated app दर्ता भएको छैन।',
            'current_version' => 'हालको संस्करण',
            'modules' => 'Modules',
            'enable' => 'Enable',
            'disable' => 'Disable',
            'uninstall' => 'Uninstall',
            'files_present' => 'Files Present',
            'lifecycle_result' => 'Lifecycle Result',
            'lifecycle_status_enabled' => 'सक्षम',
            'lifecycle_status_disabled' => 'अक्षम',
            'lifecycle_status_installed' => 'स्थापित',
            'lifecycle_status_removed' => 'हटाइएको',
            'lifecycle.error.app_not_found' => 'Registry मा app भेटिएन।',
            'lifecycle.error.module_files_missing' => 'Module files नभएकाले enable गर्न मिल्दैन।',
            'lifecycle.error.rollback_in_progress' => 'Rollback चलिरहेको बेला lifecycle action रोकियो।',
            'lifecycle.error.invalid_lifecycle_request' => 'Invalid lifecycle request।',
            'lifecycle.error.registry_write_failed' => 'Registry update असफल भयो।',
            'lifecycle.error.uninstall_blocked' => 'Safety checks ले uninstall रोक्यो।',
            'flash.lifecycle.updated' => 'App lifecycle update भयो।',
            'flash.lifecycle.failed' => 'App lifecycle update असफल भयो।',
            'governance_title' => 'शासन — G1–G4 कोड-रहित नीति',
            'governance_policy' => 'लेखन मोड नीति',
            'governance_mode' => 'GUI-प्रथम',
            'preflight_title' => 'प्रारम्भिक जाँचहरू (G2)',
            'preflight_btn' => 'Preflight चलाउनुहोस्',
            'preflight_ok' => 'सबै preflight checks पास भयो।',
            'preflight_failed' => 'Preflight असफल। Compile गर्नु अघि त्रुटि सुधार्नुहोस्।',
            'lint_title' => 'Lint जाँचहरू (G3)',
            'lint_ok' => 'Bundle lint पास भयो।',
            'lint_failed' => 'Lint असफल। Manifest structure त्रुटि सुधार्नुहोस्।',
            'publish_gate_title' => 'प्रकाशन गेट (G4)',
            'publish_gate_btn' => 'प्रकाशन गेट जाँच चलाउनुहोस्',
            'publish_gate_ok' => 'सबै publish gates पास भयो। Approval को लागि तयार।',
            'publish_gate_failed' => 'Publish gate असफल। सबै gate त्रुटि समाधान गर्नुहोस्।',
            'apply_mode_gate' => 'लागू मोड',
            'rollback_plan_title' => 'रोलब्याक योजना',
            'rollback_plan_artifacts' => 'रोलब्याकमा कलाकृतिहरू',
            'rollback_reversible' => 'उल्टाउन मिल्छ',
            'publish_decision_title' => 'प्रकाशन निर्णय रेकर्ड',
            'publish_decision_by' => 'निर्णयकर्ता',
            'publish_decision_at' => 'निर्णय समय',
            'publish_decision_allowed' => 'प्रकाशन अनुमति',
            'audit_written' => 'Audit स्न्यापशट लेखिएको',
            'audit_ok' => 'Audit snapshot storage मा सुरक्षित गरियो।',
            'audit_failed' => 'Audit snapshot लेख्न सकिएन।',
            'todo_title' => 'वर्कफ्लो चेकलिस्ट (G4)',
            'focused_plan_title' => 'फोकस्ड-स्टार्ट योजना (G1)',
            'focused_plan_step' => 'चरण',
            'focused_plan_gate' => 'गेट',
            'checkpoints_title' => 'अनुमोदन चेकपोइन्टहरू (G1)',
            'checkpoint_label' => 'चेकपोइन्ट',
            'checkpoint_stage' => 'जीवनचक्र चरण',
            'checkpoint_gates' => 'आवश्यक गेटहरू',
            'flash.preflight.ok' => 'Preflight पास भयो।',
            'flash.preflight.failed' => 'Preflight असफल। अगाडि बढ्नु अघि त्रुटि सुधार्नुहोस्।',
            'flash.publish_gate.ok' => 'Publish gate पास भयो।',
            'flash.publish_gate.failed' => 'Publish gate असफल। Publish गर्नु अघि सबै gates पास हुनु पर्छ।',
            'flash.library_bundle_loaded' => 'Generated module Studio editor मा लोड गरियो।',
            'flash.library_bundle_missing' => 'Generated module फेला परेन वा manifest अमान्य छ।',
            'flash.import_loaded' => 'Existing view Studio मा import गरियो।',
            'flash.import_partial' => 'Partial import loaded. Reapply अघि inferred layout समीक्षा गर्नुहोस्।',
            'flash.import_missing' => 'Import असफल। Existing view source उपलब्ध छैन।',
            'changes_summary_title' => 'परिवर्तन सारांश',
            'changes_total_artifacts' => 'कुल कलाकृति',
            'changes_new_files' => 'नयाँ फाइलहरु',
            'changes_modified_files' => 'परिवर्तित फाइलहरु',
            'changes_routes_affected' => 'असर गरिएको मार्गहरु',
            'changes_views_affected' => 'असर गरिएको दृश्यहरु',
            'changes_artifacts_title' => 'कलाकृति',
            'changes_preview_title' => 'परिवर्तन पूर्वावलोकन',
            'changes_preview_subtitle' => 'मुख्य कलाकृतिहरुको पहिले र पछि को छेडिको तुलना।',
            'changes_diff_summary' => 'Diff सारांश',
            'changes_no_preview' => 'Diff पूर्वावलोकन उपलब्ध छैन। परिवर्तनहरु उत्पन्न गर्न संकलन गर्नुहोस्।',
            'changes_no_data' => 'Stage गरिएका परिवर्तन छैनन्।',
            'read_only_analysis_badge' => 'Read-only विश्लेषण',
            'no_changes_staged_badge' => 'Stage गरिएका परिवर्तन छैनन्',
            'loaded_owner_app_label' => 'Owner App',
            'loaded_module_label' => 'Module',
            'loaded_resource_type_label' => 'Resource Type',
            'loaded_resource_key_label' => 'Resource Key',
            'loaded_mode_label' => 'Mode',
            'loaded_source_path_label' => 'Source Path',
            'loaded_identity_title' => 'Loaded Resource Identity',
            'loaded_identity_helper' => 'Studio ले owner-owned resources निरीक्षण गर्छ। Studio मालिक बन्दैन।',
            'clear_loaded_context_action' => 'Clear loaded context',
            'clear_loaded_context_helper' => 'Clears the current loaded-resource preview only. It does not delete files, records, or saved resources.',
            'not_loaded' => 'लोड गरिएको छैन',
            'workflow_status_title' => 'Workflow Status',
            'workflow_status_helper' => 'Validation, diff, preview, approval, and apply governed steps हुन्। Approved apply action नचलेसम्म केही पनि परिवर्तन हुँदैन।',
            'workflow_stage_analyze' => 'Analyze',
            'workflow_stage_changes' => 'Changes / Diff',
            'workflow_stage_preview' => 'Preview',
            'workflow_stage_approval' => 'Approval',
            'workflow_stage_apply' => 'Apply',
            'workflow_state_load_before_analysis' => 'Analysis अघि resource load गर्नुहोस्।',
            'workflow_state_no_diff' => 'Diff उपलब्ध छैन।',
            'workflow_state_no_preview' => 'Preview सक्रिय छैन।',
            'workflow_state_no_approval' => 'Approval pending छैन।',
            'workflow_state_no_apply' => 'Apply action सक्रिय छैन।',
            'workflow_state_ready_readonly' => 'Read-only inspection का लागि तयार',
            'workflow_state_no_changes_staged' => 'No changes staged',
            'workflow_state_apply_inactive' => 'Apply inactive छ',
            'mode_panel_title' => 'Mode',
            'mode_panel_helper' => 'Mode ले intended work type देखाउँछ। Approved governed apply action नभएसम्म यो दृश्यले resource परिवर्तन गर्दैन।',
            'mode_read_only' => 'Read-only',
            'mode_create' => 'Create',
            'mode_edit' => 'Edit',
            'mode_upgrade' => 'Upgrade',
            'mode_state_active' => 'Active',
            'mode_state_planned' => 'Planned',
            'mode_state_not_active' => 'Not active',
            'mode_state_requires_governance' => 'Requires governed workflow',
            'mode_state_context_available' => 'Context available',
            'studio_tools_title' => 'Studio Workbench',
            'studio_tools_helper' => 'Studio is a governed workbench. These tools prepare, inspect, validate, and hand over owner-owned resources. Backend actions will be wired later through approved workflows.',
            'studio_tools_group_explore' => 'Explore',
            'studio_tools_group_build' => 'Build',
            'studio_tools_group_validate' => 'Validate',
            'studio_tools_group_govern' => 'Govern',
            'studio_tools_group_history' => 'History',
            'studio_tools_status_available' => 'Available',
            'studio_tools_status_read_only' => 'Read-only',
            'studio_tools_status_planned' => 'Planned',
            'studio_tools_status_requires_governed' => 'Requires governed workflow',
            'studio_tool_resource_explorer' => 'Resource Explorer / Library',
            'studio_tool_app_builder' => 'App Builder',
            'studio_tool_module_builder' => 'Module Builder',
            'studio_tool_view_layout_builder' => 'View / Layout Builder',
            'studio_tool_navigation_menu' => 'Navigation / Menu Tool',
            'studio_tool_widget_card_builder' => 'Widget / Card Builder',
            'studio_tool_report_builder' => 'Report Builder',
            'studio_tool_data_model_schema' => 'Data Model / DB Schema Tool',
            'studio_tool_validation_preview_center' => 'Validation / Preview Center',
            'studio_tool_approval_apply_center' => 'Approval / Apply Center',
            'studio_tool_change_history_snapshots' => 'Change History / Snapshots',
            'studio_tool_purpose_resource_explorer' => 'Discover existing owner resources and load inspectable context.',
            'studio_tool_purpose_app_builder' => 'Shape app-level draft structure and ownership metadata.',
            'studio_tool_purpose_module_builder' => 'Prepare module structure, contracts, and composition drafts.',
            'studio_tool_purpose_view_layout_builder' => 'Compose view and layout drafts before governed handoff.',
            'studio_tool_purpose_navigation_menu' => 'Draft route/menu exposure intent without changing runtime routing.',
            'studio_tool_purpose_widget_card_builder' => 'Prepare widget and card layouts for owner review.',
            'studio_tool_purpose_report_builder' => 'Draft report structure and export intent for governed review.',
            'studio_tool_purpose_data_model_schema' => 'Plan schema-level intent and impact before approved workflow.',
            'studio_tool_purpose_validation_preview_center' => 'Inspect validations and preview diffs in a read-only lane.',
            'studio_tool_purpose_approval_apply_center' => 'Review governed approval/apply state without direct runtime control.',
            'studio_tool_purpose_change_history_snapshots' => 'Review change lineage, snapshots, and audit history.',
            'studio_tools_boundary_owner_resources' => 'Works on owner-owned resources',
            'studio_tools_boundary_not_owner' => 'Does not own business modules',
            'studio_tools_boundary_no_runtime_without_apply' => 'No runtime changes until approved apply',
            'studio_tools_open_library' => 'Open Library',
            'studio_tools_open_history' => 'Open History',
            'studio_tools_preview_title' => 'Workbench Tool Preview',
            'studio_tools_preview_helper' => 'This preview explains Studio tool intent only. Tool execution will be wired later through governed workflows.',
            'studio_tools_preview_default' => 'Select a Studio tool to inspect its purpose and boundaries.',
            'studio_tools_preview_field_name' => 'Tool name',
            'studio_tools_preview_field_group' => 'Group',
            'studio_tools_preview_field_status' => 'Status',
            'studio_tools_preview_field_purpose' => 'Purpose',
            'studio_tools_preview_field_works_on' => 'Works on',
            'studio_tools_preview_field_must_not_own' => 'Must not own',
            'studio_tools_preview_field_first_safe' => 'First safe implementation',
            'studio_tools_preview_field_backend' => 'Backend wiring status',
            'studio_tools_preview_first_safe_readonly' => 'Read-only inspection and metadata review in Studio panels.',
            'studio_tools_preview_first_safe_linked' => 'Use the existing Studio page link for read-only context only.',
            'studio_tools_preview_first_safe_governed' => 'Enable through governed analyze/diff/approval/apply workflow only.',
            'studio_tools_preview_backend_unwired' => 'Not wired yet (intent preview only).',
            'studio_tools_preview_backend_linked' => 'Linked Studio page is available; execution workflows remain governed/unwired.',
            'studio_workbench_context_title' => 'Tool + Resource Preview',
            'studio_workbench_context_field_selected_tool' => 'Selected Tool',
            'studio_workbench_context_field_selected_resource' => 'Selected Resource',
            'studio_workbench_context_field_owner_app' => 'Owner App',
            'studio_workbench_context_field_module' => 'Module',
            'studio_workbench_context_field_resource_type' => 'Resource Type',
            'studio_workbench_context_field_action_state' => 'Action State',
            'studio_workbench_context_field_ownership_boundary' => 'Ownership Boundary',
            'studio_workbench_context_no_resource' => 'No resource loaded',
            'studio_workbench_context_no_tool' => 'No tool selected',
            'studio_workbench_context_action_preview_only' => 'Preview only',
            'studio_workbench_context_action_readonly_analysis' => 'Read-only analysis',
            'studio_workbench_context_action_no_apply' => 'No apply action active',
            'studio_workbench_context_boundary_text' => 'The selected tool may inspect owner-owned resources. Studio does not become the owner.',
            'studio_workbench_context_backend_planned' => 'Backend wiring: planned',
            'studio_workbench_context_backend_linked' => 'Backend wiring: linked page only',
            'studio_workbench_context_no_execution' => 'No tool execution is active',
            'loaded_mode_read_only' => 'Read-only',
            'loaded_mode_edit' => 'Edit',
            'loaded_mode_create' => 'Create',
            'loaded_mode_upgrade' => 'Upgrade',
            'artifacts' => 'कलाकृति',
            'created' => 'सिर्जना गरिएको',
            'modified' => 'परिवर्तित',
            'routes' => 'मार्गहरु',
            'views' => 'दृश्यहरु',
            'artifact_name' => 'कलाकृति',
            'operation' => 'अपरेशन',
            'operation.create' => 'सिर्जना गर्नुहोस्',
            'operation.modify' => 'परिवर्तन गर्नुहोस्',
            'operation.delete' => 'मेटाउनुहोस्',
            'status' => 'स्थिति',
            'status.ready' => 'तयार',
            'status.pending' => 'पेंडिङ',
            'additions' => 'जोडहरु',
            'deletions' => 'मेटाहरु',
            'files_changed' => 'फाइलहरु परिवर्तन',
            'apply_tab_title' => 'परिवर्तन लागू गर्नुहोस्',
            'apply_tab_subtitle' => 'पाइपलाइन गेट पास र अनुमोदन पुष्टि भएपछि मात्र कार्यान्वयन गर्नुहोस्।',
            'apply_pipeline_title' => 'पाइपलाइन स्थिति',
            'apply_pipeline_compile' => 'कम्पाइल',
            'apply_pipeline_analyze' => 'विश्लेषण',
            'apply_pipeline_impact' => 'प्रभाव',
            'apply_pipeline_risk_level' => 'जोखिम स्तर',
            'apply_pipeline_ok' => 'ठीक',
            'apply_pipeline_pending' => 'पेन्डिङ',
            'apply_metadata_title' => 'अनुमोदन मेटाडाटा',
            'apply_confirmation_label' => 'यी परिवर्तन लागू गर्न तयार रहेको म पुष्टि गर्छु',
            'apply_gate_requirements_title' => 'Apply Gate आवश्यकताहरु',
            'apply_gate_requirements_analyze' => 'विश्लेषण पूरा भएको हुनुपर्छ',
            'apply_gate_requirements_confirm' => 'पुष्टि चेकबक्स अनिवार्य छ',
            'apply_gate_requirements_reason' => 'अनुमोदन कारण अनिवार्य छ',
            'apply_gate_failed_title' => 'गेट जाँचले Apply रोकेको छ',
            'apply_gate_error_analyze' => 'विश्लेषण पूरा भएको छैन।',
            'apply_gate_error_confirm' => 'Apply अघि पुष्टि अनिवार्य छ।',
            'apply_gate_error_reason' => 'Apply अघि अनुमोदन कारण अनिवार्य छ।',
            'apply_gate_runtime_errors' => 'हाल Apply यी gate checks का कारण रोकिएको छ:',
            'apply_changes_btn' => 'परिवर्तन लागू गर्नुहोस्',
            'apply_no_data' => 'परिवर्तन लागू गर्नु अघि योजना कम्पाइल र विश्लेषण गर्नुहोस्।',
            'pipeline_risk_medium' => 'मध्यम',
          ],
        ];

    $text = (string)($dict[$lang][$key] ?? $dict['en'][$key] ?? $key);
    foreach ($params as $paramKey => $paramValue) {
        $text = str_replace('{' . $paramKey . '}', (string)$paramValue, $text);
    }
    return $text;
};

  $gsGlobalMap = [
    'apply_mode_label' => 'ops.gui_studio.apply_mode_label',
    'apply_mode_simulation' => 'ops.gui_studio.apply_mode_simulation',
    'apply_mode_real' => 'ops.gui_studio.apply_mode_real',
    'apply_mode_disabled' => 'ops.gui_studio.apply_mode_disabled',
    'apply_mode_first_apply' => 'ops.gui_studio.apply_mode_first_apply',
    'snapshot_persisted' => 'ops.gui_studio.snapshot_persisted',
    'rollback_snapshot_notice' => 'ops.gui_studio.rollback_snapshot_notice',
    'post_publish_verification' => 'ops.gui_studio.post_publish_verification',
    'verification_status' => 'ops.gui_studio.verification_status',
    'transaction_steps' => 'ops.gui_studio.transaction_steps',
    'step_result' => 'ops.gui_studio.step_result',
    'rollback_binding' => 'ops.gui_studio.rollback_binding',
    'rollback_binding_status' => 'ops.gui_studio.rollback_binding_status',
    'rollback_bound' => 'ops.gui_studio.rollback_bound',
    'rollback_preview' => 'ops.gui_studio.rollback_preview',
    'rollback_summary' => 'ops.gui_studio.rollback_summary',
    'planned_action' => 'ops.gui_studio.planned_action',
    'rollback_action' => 'ops.gui_studio.rollback_action',
    'reversible' => 'ops.gui_studio.reversible',
    'non_reversible_label' => 'ops.gui_studio.non_reversible_label',
    'rollback_execute_btn' => 'ops.gui_studio.rollback_execute_btn',
    'rollback_execute_steps' => 'ops.gui_studio.rollback_execute_steps',
    'rollback_execute_message' => 'ops.gui_studio.rollback_execute_message',
    'rollback_execute_no_binding' => 'ops.gui_studio.rollback_execute_no_binding',
    'rollback_unavailable' => 'ops.gui_studio.rollback_unavailable',
    'rollback_execute_title' => 'ops.gui_studio.rollback_execute_title',
    'rollback_execute_status' => 'ops.gui_studio.rollback_execute_status',
    'rollback_execute_result' => 'ops.gui_studio.rollback_execute_result',
    'rollback_execute_all_non_reversible' => 'ops.gui_studio.rollback_execute_all_non_reversible',
    'rollback_execute_non_reversible_warning' => 'ops.gui_studio.rollback_execute_non_reversible_warning',
    'rollback_execute_completed' => 'ops.gui_studio.rollback_execute_completed',
    'rollback_execute_failed' => 'ops.gui_studio.rollback_execute_failed',
    'apply_mode_gate' => 'ops.gui_studio.apply_mode_gate',
    'rollback_plan_title' => 'ops.gui_studio.rollback_plan_title',
    'rollback_plan_artifacts' => 'ops.gui_studio.rollback_plan_artifacts',
    'studio_tools_title' => 'ops.gui_studio.studio_tools_title',
    'studio_tools_helper' => 'ops.gui_studio.studio_tools_helper',
    'loaded_identity_title' => 'ops.gui_studio.loaded_identity_title',
    'loaded_owner_app_label' => 'ops.gui_studio.loaded_owner_app_label',
    'loaded_module_label' => 'ops.gui_studio.loaded_module_label',
    'loaded_resource_type_label' => 'ops.gui_studio.loaded_resource_type_label',
    'loaded_resource_key_label' => 'ops.gui_studio.loaded_resource_key_label',
    'loaded_mode_label' => 'ops.gui_studio.loaded_mode_label',
    'loaded_type_label' => 'ops.gui_studio.loaded_type_label',
    'loaded_context_label' => 'ops.gui_studio.loaded_context_label',
    'loaded_context_source' => 'ops.gui_studio.loaded_context_source',
    'loaded_context_source_library' => 'ops.gui_studio.loaded_context_source_library',
    'loaded_context_source_import' => 'ops.gui_studio.loaded_context_source_import',
    'loaded_source_path_label' => 'ops.gui_studio.loaded_source_path_label',
    'loaded_context_unknown' => 'ops.gui_studio.loaded_context_unknown',
    'loaded_identity_helper' => 'ops.gui_studio.loaded_identity_helper',
    'loaded_mode_read_only' => 'ops.gui_studio.loaded_mode_read_only',
    'loaded_mode_edit' => 'ops.gui_studio.loaded_mode_edit',
    'loaded_mode_create' => 'ops.gui_studio.loaded_mode_create',
    'loaded_mode_upgrade' => 'ops.gui_studio.loaded_mode_upgrade',
    'workflow_status_title' => 'ops.gui_studio.workflow_status_title',
    'workflow_stage_analyze' => 'ops.gui_studio.workflow_stage_analyze',
    'workflow_stage_changes' => 'ops.gui_studio.workflow_stage_changes',
    'workflow_stage_preview' => 'ops.gui_studio.workflow_stage_preview',
    'workflow_stage_approval' => 'ops.gui_studio.workflow_stage_approval',
    'workflow_stage_apply' => 'ops.gui_studio.workflow_stage_apply',
    'workflow_status_helper' => 'ops.gui_studio.workflow_status_helper',
    'workflow_state_load_before_analysis' => 'ops.gui_studio.workflow_state_load_before_analysis',
    'workflow_state_no_diff' => 'ops.gui_studio.workflow_state_no_diff',
    'workflow_state_no_preview' => 'ops.gui_studio.workflow_state_no_preview',
    'workflow_state_no_approval' => 'ops.gui_studio.workflow_state_no_approval',
    'workflow_state_no_apply' => 'ops.gui_studio.workflow_state_no_apply',
    'workflow_state_no_changes_staged' => 'ops.gui_studio.workflow_state_no_changes_staged',
    'workflow_state_ready_readonly' => 'ops.gui_studio.workflow_state_ready_readonly',
    'workflow_state_apply_inactive' => 'ops.gui_studio.workflow_state_apply_inactive',
    'mode_panel_title' => 'ops.gui_studio.mode_panel_title',
    'mode_panel_helper' => 'ops.gui_studio.mode_panel_helper',
    'mode_read_only' => 'ops.gui_studio.mode_read_only',
    'mode_create' => 'ops.gui_studio.mode_create',
    'mode_edit' => 'ops.gui_studio.mode_edit',
    'mode_upgrade' => 'ops.gui_studio.mode_upgrade',
    'mode_state_active' => 'ops.gui_studio.mode_state_active',
    'mode_state_planned' => 'ops.gui_studio.mode_state_planned',
    'mode_state_not_active' => 'ops.gui_studio.mode_state_not_active',
    'mode_state_requires_governance' => 'ops.gui_studio.mode_state_requires_governance',
    'mode_state_context_available' => 'ops.gui_studio.mode_state_context_available',
    'clear_loaded_context_action' => 'ops.gui_studio.clear_loaded_context_action',
    'clear_loaded_context_helper' => 'ops.gui_studio.clear_loaded_context_helper',
  ];

  $gsBatch1 = static function (string $localKey, array $params = []) use ($gs, $gsGlobalMap): string {
    $globalKey = (string)($gsGlobalMap[$localKey] ?? '');
    if ($globalKey !== '' && function_exists('t')) {
      $translated = (string)t($globalKey, $params);
      if ($translated !== '' && $translated !== $globalKey) {
        return $translated;
      }
    }
    return $gs($localKey, $params);
  };

$getInput = static function (string $key, array $values): string {
    $v = $values[$key] ?? '';
    return is_string($v) ? $v : '';
};

$flashText = $flash !== '' ? t($flash) : '';
$errorText = $error !== '' ? t($error) : '';
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_compile_plan_ready') {
    $flashText = $gs('flash.compile.ready');
}
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_approval_preview_ready') {
    $flashText = $gs('flash.approval.ready');
}
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_snapshot_preview_ready') {
    $flashText = $gs('flash.snapshot.ready');
}
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_execution_preview_ready') {
    $flashText = $gs('flash.execution.ready');
}
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_apply_applied') {
    $flashText = $gs('flash.apply.applied');
}
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_rollback_preview_ready') {
    $flashText = $gs('flash.rollback.ready');
}
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_rollback_executed') {
    $flashText = $gs('flash.rollback.executed');
}
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_lifecycle_updated') {
    $flashText = $gs('flash.lifecycle.updated');
}
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_preflight_ok') {
  $flashText = $gs('flash.preflight.ok');
}
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_publish_gate_ok') {
  $flashText = $gs('flash.publish_gate.ok');
}
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_library_bundle_loaded') {
  $flashText = $gs('flash.library_bundle_loaded');
}
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_import_loaded') {
  $flashText = $gs('flash.import_loaded');
}
if ($flashText === $flash && $flash === 'ops.gui_studio.flash_import_partial') {
  $flashText = $gs('flash.import_partial');
}
if ($errorText === $error && $error === 'ops.gui_studio.flash_apply_failed') {
    $errorText = $gs('flash.apply.failed');
}
if ($errorText === $error && $error === 'ops.gui_studio.flash_rollback_execute_failed') {
    $errorText = $gs('flash.rollback.execute_failed');
}
if ($errorText === $error && $error === 'ops.gui_studio.flash_rollback_blocked') {
    $errorText = $gs('flash.rollback.blocked');
}
if ($errorText === $error && $error === 'ops.gui_studio.flash_compile_plan_blocked') {
    $errorText = $gs('flash.compile.blocked');
}
if ($errorText === $error && $error === 'ops.gui_studio.flash_lifecycle_failed') {
    $errorText = $gs('flash.lifecycle.failed');
}
if ($errorText === $error && $error === 'ops.gui_studio.flash_preflight_failed') {
  $errorText = $gs('flash.preflight.failed');
}
if ($errorText === $error && $error === 'ops.gui_studio.flash_publish_gate_failed') {
  $errorText = $gs('flash.publish_gate.failed');
}
if ($errorText === $error && $error === 'ops.gui_studio.flash_library_bundle_missing') {
  $errorText = $gs('flash.library_bundle_missing');
}
if ($errorText === $error && $error === 'ops.gui_studio.flash_import_missing') {
  $errorText = $gs('flash.import_missing');
}
$projectPayload = is_array($studioProject['project'] ?? null) ? $studioProject['project'] : [];
$projectMeta = is_array($projectPayload['project'] ?? null) ? $projectPayload['project'] : [];
?>

<link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css">


<div class="studio-shell" data-mobile-mode="library">
  <?php require __DIR__ . '/partials/gui_studio/mobile_mode_tabs.php'; ?>
  <?php require __DIR__ . '/partials/library_explorer.php'; ?>

  <main class="studio-main">
    <?php require __DIR__ . '/partials/gui_studio/header.php'; ?>

    <section class="studio-content">
      <?php require __DIR__ . '/../Tools/ViewEditor/Views/tab_edit.php'; ?>
      <?php require __DIR__ . '/partials/workflow/analyze_panel.php'; ?>
      <?php require __DIR__ . '/partials/workflow/changes_panel.php'; ?>
      <?php require __DIR__ . '/partials/workflow/tab_apply.php'; ?>
</div>

<script>
<?php require __DIR__ . '/../assets/js/tool-preview.js'; ?>
</script>

<script>
<?php require __DIR__ . '/../assets/js/workflow-mode.js'; ?>
</script>

<script>
<?php require __DIR__ . '/../assets/js/edit-workbench-state.js'; ?>
</script>

<script>
<?php require __DIR__ . '/../assets/js/loaded-resource-identity.js'; ?>
</script>

<script>
<?php require __DIR__ . '/../assets/js/apply-form-gate.js'; ?>
</script>

<script>
<?php require __DIR__ . '/../assets/js/change-intelligence-table-rows.js'; ?>
</script>

<script>
<?php require __DIR__ . '/../assets/js/gui_studio/01_bootstrap.js'; ?>
</script>

<script>
<?php require __DIR__ . '/../assets/js/library-explorer.js'; ?>
</script>
<script>
<?php require __DIR__ . '/../assets/js/gui_studio/02_library.js'; ?>
</script>

<script>
<?php require __DIR__ . '/../assets/js/gui_studio/03_editor_state.js'; ?>
</script>

<script>
<?php require __DIR__ . '/../assets/js/loaded-resource-context.js'; ?>
</script>
<script>
<?php require __DIR__ . '/../assets/js/gui_studio/04_loaded_resource_context_init.js'; ?>
</script>

<?php if ($showLegacyStudioWorkbench) { ?>
<div class="legacy-studio u-style-c8be1ccba6">


<section class="card u-style-f2892a2e8a">
  <h2><?= e($gs('title')) ?></h2>
  <div class="muted"><?= e($gs('subtitle')) ?></div>
  <div class="muted"><?= e($gs('compat')) ?></div>
  <div class="u-style-c9ef2a58eb"><a class="btn" href="/apps/studio/history"><?= e($gs('history_link')) ?></a></div>
  <?php if ($flashText !== ''): ?>
    <div class="note success"><?= e($flashText) ?></div>
  <?php endif; ?>
  <?php if ($errorText !== ''): ?>
    <div class="note warning"><?= e($errorText) ?></div>
  <?php endif; ?>
</section>

<details class="legacy-collapsible">
  <summary><?= e($gs('app_lifecycle_manager')) ?></summary>
  <div class="legacy-collapsible-content">
<section class="card" id="gs-app-lifecycle">
  <?php
    $lifecycleResult = (array)($result['lifecycle'] ?? []);
    $lifecycleErrors = (array)($result['errors'] ?? []);
  ?>
  <?php if ($lifecycleResult !== []): ?>
    <div class="note <?= !empty($lifecycleResult['ok']) ? 'success' : 'warning' ?>">
      <strong><?= e($gs('lifecycle_result')) ?>:</strong>
      <?= e((string)($lifecycleResult['message'] ?? '')) ?>
      <?php if ($lifecycleErrors !== []): ?>
        <?php foreach ($lifecycleErrors as $lifecycleError): ?>
          <?php $lifecycleErrorKey = 'lifecycle.error.' . (string)$lifecycleError; ?>
          <span><?= e(($gs($lifecycleErrorKey) !== $lifecycleErrorKey) ? $gs($lifecycleErrorKey) : (string)$lifecycleError) ?></span>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($appLifecycleEntries === []): ?>
    <div class="note warning"><?= e($gs('app_registry_empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('app_manifest')) ?></th>
            <th><?= e($gs('status')) ?></th>
            <th><?= e($gs('current_version')) ?></th>
            <th><?= e($gs('modules')) ?></th>
            <th><?= e($gs('files_present')) ?></th>
            <th><?= e($gs('action')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($appLifecycleEntries as $appEntry): ?>
            <?php
              $appKey = (string)($appEntry['app_key'] ?? '');
              $appStatus = (string)($appEntry['status'] ?? 'disabled');
              $statusClass = $appStatus === 'enabled' ? 'success' : ($appStatus === 'disabled' ? 'warning' : '');
              $moduleDetails = is_array($appEntry['module_details'] ?? null) ? $appEntry['module_details'] : [];
              $allFilesPresent = $moduleDetails !== [] && count(array_filter($moduleDetails, static fn($module): bool => is_array($module) && !empty($module['files_present']))) === count($moduleDetails);
              $statusLabelKey = 'lifecycle_status_' . $appStatus;
            ?>
            <tr>
              <td><code><?= e($appKey) ?></code></td>
              <td><span class="status-chip <?= e($statusClass) ?>"><?= e($gs($statusLabelKey) !== $statusLabelKey ? $gs($statusLabelKey) : $appStatus) ?></span></td>
              <td><code><?= e((string)($appEntry['current_version'] ?? '')) ?></code></td>
              <td>
                <?php foreach ($moduleDetails as $moduleDetail): ?>
                  <?php if (!is_array($moduleDetail)) { continue; } ?>
                  <div class="ui-block"><code><?= e((string)($moduleDetail['module_key'] ?? '')) ?></code></div>
                <?php endforeach; ?>
              </td>
              <td><span class="status-chip <?= $allFilesPresent ? 'success' : 'danger' ?>"><?= $allFilesPresent ? e($gs('bool.true')) : e($gs('bool.false')) ?></span></td>
              <td>
                <form method="POST" action="/apps/studio/lifecycle" class="inline-form">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="app_key" value="<?= e($appKey) ?>">
                  <?php if ($appStatus !== 'enabled'): ?>
                    <button class="btn" type="submit" name="action" value="enable"><?= e($gs('enable')) ?></button>
                  <?php endif; ?>
                  <?php if ($appStatus === 'enabled'): ?>
                    <button class="btn" type="submit" name="action" value="disable"><?= e($gs('disable')) ?></button>
                  <?php endif; ?>
                  <button class="btn danger" type="submit" name="action" value="uninstall"><?= e($gs('uninstall')) ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
  </div>
</details>

<details class="legacy-collapsible">
  <summary><?= e($gs('capabilities')) ?></summary>
  <div class="legacy-collapsible-content">
<section class="card">
  <ul>
    <li><?= e($gs('cap.apps')) ?></li>
    <li><?= e($gs('cap.modules')) ?></li>
    <li><?= e($gs('cap.views')) ?></li>
    <li><?= e($gs('cap.nav')) ?></li>
    <li><?= e($gs('cap.templates')) ?></li>
    <li><?= e($gs('cap.packages')) ?></li>
    <li><?= e($gs('cap.validate')) ?></li>
  </ul>
</section>
  </div>
</details>

<section class="home-portal-app-grid">
  <article class="dashboard-link-card">
    <h3><?= e($gs('projects')) ?></h3>
    <p class="muted"><?= e((string)($projectMeta['display_name'] ?? 'Sample Studio Project')) ?></p>
    <p class="muted"><?= e($gs('project.note')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('drafts')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('validation')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('compile_plan')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('compile_graph')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('diff_readiness')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('diff_preview')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('approval_gate')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('snapshot_preview')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('execution_preview')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('apply_snapshot_title')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('ownership_governance')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('drift_status')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('risk_escalation')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('human_diff_summary')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('compile_snapshot_identity')) ?></h3>
    <p class="muted"><?= e($gs('implemented')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('publish_governance')) ?></h3>
    <p class="muted"><?= e($gs('locked_future')) ?></p>
  </article>
  <article class="dashboard-link-card">
    <h3><?= e($gs('rollback')) ?></h3>
    <p class="muted"><?= e($gs('future')) ?></p>
  </article>
</section>

<details class="legacy-collapsible">
  <summary><?= e($gs('template_library')) ?></summary>
  <div class="legacy-collapsible-content">
<section class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th><?= e($gs('template_library')) ?></th>
          <th><?= e($gs('category')) ?></th>
          <th><?= e($gs('outputs')) ?></th>
          <th><?= e($gs('guardrails')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($templateLibrary as $template): ?>
          <tr>
            <td><?= e((string)($template['display_name'] ?? $template['template_key'] ?? '')) ?></td>
            <td><?= e((string)($template['category'] ?? '')) ?></td>
            <td><?= e(implode(', ', array_map('strval', (array)($template['outputs'] ?? [])))) ?></td>
            <td><?= e(implode(', ', array_map('strval', (array)($template['guardrails'] ?? [])))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
  </div>
</details>

<details class="legacy-collapsible">
  <summary><?= e($gs('drafts')) ?></summary>
  <div class="legacy-collapsible-content">
<section class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th><?= e($gs('drafts')) ?></th>
          <th><?= e($gs('status')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($draftLifecycle as $step): ?>
          <tr>
            <td><?= e((string)($step['label'] ?? $step['key'] ?? '')) ?></td>
            <td><?= e((string)($step['status'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
  </div>
</details>

<?php
$renderGlobalLibraryNode = static function (array $node, string $selectedId, callable $renderGlobalLibraryNode) use ($gs): void {
    $nodeId = trim((string)($node['id'] ?? ''));
    $nodeLabel = (string)($node['label'] ?? $nodeId);
    $nodeType = (string)($node['type'] ?? 'node');
    $nodeChildren = is_array($node['children'] ?? null) ? array_values(array_filter($node['children'], 'is_array')) : [];
    $nodePath = trim((string)($node['meta']['path'] ?? ''));
    $isSelected = $nodeId !== '' && $nodeId === $selectedId;
    $nodeHref = '/apps/studio?library_item=' . rawurlencode($nodeId);
    ?>
    <li>
      <a href="<?= e($nodeHref) ?>" class="<?= $isSelected ? 'status-chip success' : '' ?>">
        <?= e($nodeLabel) ?>
      </a>
      <span class="status-chip"><?= e($nodeType) ?></span>
      <?php if ($nodePath !== ''): ?>
        <code><?= e($nodePath) ?></code>
      <?php endif; ?>
      <?php if ($nodeChildren !== []): ?>
        <ul>
          <?php foreach ($nodeChildren as $childNode): ?>
            <?php $renderGlobalLibraryNode($childNode, $selectedId, $renderGlobalLibraryNode); ?>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </li>
    <?php
};
?>

<section class="card" id="gs-global-library">
  <h3><?= e($gs('global_library_title')) ?></h3>
  <p class="muted"><?= e($gs('global_library_subtitle')) ?></p>

  <div class="table-summary-badges">
    <span class="status-chip"><?= e($gs('global_library_counts')) ?>:</span>
    <span class="status-chip"><?= e($gs('global_count_apps')) ?> <strong><?= (int)($globalLibraryCounts['apps'] ?? 0) ?></strong></span>
    <span class="status-chip"><?= e($gs('global_count_plugins')) ?> <strong><?= (int)($globalLibraryCounts['plugins'] ?? 0) ?></strong></span>
    <span class="status-chip"><?= e($gs('global_count_modules')) ?> <strong><?= (int)($globalLibraryCounts['modules'] ?? 0) ?></strong></span>
    <span class="status-chip"><?= e($gs('global_count_views')) ?> <strong><?= (int)($globalLibraryCounts['views'] ?? 0) ?></strong></span>
    <span class="status-chip"><?= e($gs('global_count_dashboards')) ?> <strong><?= (int)($globalLibraryCounts['dashboards'] ?? 0) ?></strong></span>
    <span class="status-chip"><?= e($gs('global_count_routes')) ?> <strong><?= (int)($globalLibraryCounts['routes'] ?? 0) ?></strong></span>
    <span class="status-chip"><?= e($gs('global_count_navs')) ?> <strong><?= (int)($globalLibraryCounts['navs'] ?? 0) ?></strong></span>
  </div>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th><?= e($gs('global_library_tree')) ?></th>
          <th><?= e($gs('global_library_detail')) ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="u-style-3c014b2f7d">
            <?php if ($globalLibraryTree === []): ?>
              <div class="note warning"><?= e($gs('global_library_empty')) ?></div>
            <?php else: ?>
              <ul>
                <?php foreach ($globalLibraryTree as $libraryRootNode): ?>
                  <?php $renderGlobalLibraryNode($libraryRootNode, $globalLibrarySelectedId, $renderGlobalLibraryNode); ?>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </td>
          <td class="u-style-060dc9f317">
            <?php if (!is_array($globalLibrarySelected)): ?>
              <div class="note"><?= e($gs('global_library_no_selection')) ?></div>
            <?php else: ?>
              <div class="status-chip success"><?= e((string)($globalLibrarySelected['type'] ?? 'node')) ?></div>
              <h4 class="u-style-d8a81eac84"><?= e((string)($globalLibrarySelected['label'] ?? '')) ?></h4>
              <code><?= e((string)($globalLibrarySelected['id'] ?? '')) ?></code>
              <?php
              $selectedType = strtolower(trim((string)($globalLibrarySelected['type'] ?? '')));
              $selectedNodeId = trim((string)($globalLibrarySelected['id'] ?? ''));
              $selectedIsInspectOnly = in_array($selectedType, ['route', 'nav'], true);
              ?>
              <?php if ($selectedIsInspectOnly): ?>
                <p class="muted u-style-d8a81eac84"><?= e(str_replace('{kind}', $selectedType, (string)$gs('library_detail_inspect_only_note'))) ?></p>
              <?php endif; ?>
              <?php if ($selectedNodeId !== '' && in_array($selectedType, ['module', 'view', 'dashboard'], true)): ?>
                <div class="form-actions u-style-d8a81eac84">
                  <button
                    type="button"
                    class="btn"
                    data-load-library-node
                    data-node-id="<?= e($selectedNodeId) ?>"
                  ><?= e($gs('global_library_load_into_studio')) ?></button>
                </div>
              <?php endif; ?>
              <?php
              $selectedMeta = is_array($globalLibrarySelected['meta'] ?? null)
                  ? $globalLibrarySelected['meta']
                  : [];
              ?>
              <?php if ($selectedMeta !== []): ?>
                <div class="table-wrap u-style-d8a81eac84">
                  <table class="table">
                    <thead>
                      <tr>
                        <th><?= e($gs('detail')) ?></th>
                        <th><?= e($gs('summary')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($selectedMeta as $metaKey => $metaValue): ?>
                        <tr>
                          <td><code><?= e((string)$metaKey) ?></code></td>
                          <td>
                            <?php if (is_array($metaValue)): ?>
                              <code><?= e((string)json_encode($metaValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code>
                            <?php else: ?>
                              <code><?= e((string)$metaValue) ?></code>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</section>

<section class="card" id="gs-data-contract">
  <h3><?= e($gs('data_contract_title')) ?></h3>
  <p class="muted"><?= e($gs('data_contract_subtitle')) ?></p>

  <?php if ($studioDataContract === []): ?>
    <div class="note warning"><?= e($gs('data_contract_empty')) ?></div>
  <?php else: ?>
    <div class="table-summary-badges">
      <span class="status-chip"><?= e($gs('data_contract_confidence')) ?>: <strong><?= e($gs('data_contract_confidence.' . (string)($studioDataContract['confidence'] ?? 'none'))) ?></strong></span>
      <span class="status-chip"><?= e($gs('data_contract_fields')) ?> <strong><?= (int)($studioDataContract['summary']['fields'] ?? 0) ?></strong></span>
      <span class="status-chip"><?= e($gs('data_contract_bindings')) ?> <strong><?= (int)($studioDataContract['summary']['bindings'] ?? 0) ?></strong></span>
      <span class="status-chip"><?= e($gs('data_contract_sources')) ?> <strong><?= (int)($studioDataContract['summary']['data_sources'] ?? 0) ?></strong></span>
      <span class="status-chip"><?= e($gs('data_contract_required_fields')) ?> <strong><?= (int)($studioDataContract['summary']['required_fields'] ?? 0) ?></strong></span>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('data_contract_checks')) ?></th>
            <th><?= e($gs('data_contract_check_status')) ?></th>
            <th><?= e($gs('data_contract_check_detail')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($studioDataChecks as $check): ?>
            <?php
              $checkKey = trim((string)($check['key'] ?? ''));
              $checkOk = !empty($check['ok']);
              $checkDetail = is_array($check['detail'] ?? null)
                  ? (string)json_encode($check['detail'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                  : '';
            ?>
            <tr>
              <td><?= e($gs('data_contract_check.' . $checkKey)) ?></td>
              <td><span class="status-chip <?= $checkOk ? 'success' : 'warning' ?>"><?= e($gs('data_contract_status.' . ($checkOk ? 'pass' : 'fail'))) ?></span></td>
              <td><code><?= e($checkDetail !== '' ? $checkDetail : '-') ?></code></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card" id="gs-dependency-graph">
  <h3><?= e($gs('dependency_graph_title')) ?></h3>
  <p class="muted"><?= e($gs('dependency_graph_subtitle')) ?></p>

  <?php if ($studioDependencyGraph === []): ?>
    <div class="note warning"><?= e($gs('dependency_graph_none')) ?></div>
  <?php else: ?>
    <div class="table-summary-badges">
      <span class="status-chip"><?= e($gs('dependency_graph_nodes')) ?> <strong><?= (int)(count((array)($studioDependencyGraph['nodes'] ?? []))) ?></strong></span>
      <span class="status-chip"><?= e($gs('dependency_graph_edges')) ?> <strong><?= (int)($studioDependencyGraph['summary']['edges'] ?? 0) ?></strong></span>
      <span class="status-chip"><?= e($gs('dependency_graph_depends_on')) ?> <strong><?= (int)($studioDependencyGraph['depends_on_edges'] ?? 0) ?></strong></span>
      <span class="status-chip"><?= e($gs('dependency_graph_affects')) ?> <strong><?= (int)($studioDependencyGraph['affects_edges'] ?? 0) ?></strong></span>
    </div>

    <?php $graphIssues = is_array($studioDependencyGraph['issues'] ?? null) ? $studioDependencyGraph['issues'] : []; ?>
    <?php if ($graphIssues !== []): ?>
      <div class="note warning">
        <strong><?= e($gs('dependency_graph_issues')) ?>:</strong>
        <code><?= e((string)json_encode(array_values($graphIssues), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code>
      </div>
    <?php endif; ?>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('dependency_graph_from')) ?></th>
            <th><?= e($gs('dependency_graph_to')) ?></th>
            <th><?= e($gs('dependency_graph_relation')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($studioGraphEdges, 0, 60) as $edge): ?>
            <tr>
              <td><code><?= e((string)($edge['from'] ?? '')) ?></code></td>
              <td><code><?= e((string)($edge['to'] ?? '')) ?></code></td>
              <td><span class="status-chip"><?= e((string)($edge['relation'] ?? '')) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card" id="gs-generated-library">
  <h3><?= e($gs('generated_library_title')) ?></h3>
  <div class="table-summary-badges">
    <span class="status-chip"><?= e($gs('studio_mode_label')) ?>: <strong id="gs-studio-mode-chip"><?= e($gs($studioMode === 'edit_existing' ? 'studio_mode_edit_existing' : 'studio_mode_create_new')) ?></strong></span>
  </div>

  <?php if ($generatedLibraryEntries === []): ?>
    <div class="note warning"><?= e($gs('library_empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('library_app')) ?></th>
            <th><?= e($gs('status')) ?></th>
            <th><?= e($gs('library_publish_state')) ?></th>
            <th><?= e($gs('library_module')) ?></th>
            <th><?= e($gs('library_route')) ?></th>
            <th><?= e($gs('library_version')) ?></th>
            <th><?= e($gs('library_snapshot')) ?></th>
            <th><?= e($gs('library_last_updated')) ?></th>
            <th><?= e($gs('action')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($generatedLibraryEntries as $libraryApp): ?>
            <?php
              $libraryAppKey = (string)($libraryApp['app_key'] ?? '');
              $libraryAppStatus = (string)($libraryApp['status'] ?? 'disabled');
              $libraryStatusClass = $libraryAppStatus === 'enabled' ? 'success' : 'warning';
              $libraryModules = is_array($libraryApp['modules'] ?? null) ? $libraryApp['modules'] : [];
              $libraryPublishApproved = !empty($libraryApp['publish_approved']);
            ?>
            <?php if ($libraryModules === []): ?>
              <tr>
                <td><code><?= e($libraryAppKey) ?></code></td>
                <td><span class="status-chip <?= e($libraryStatusClass) ?>"><?= e($libraryAppStatus) ?></span></td>
                <td><span class="status-chip <?= $libraryPublishApproved ? 'success' : 'warning' ?>"><?= e($libraryPublishApproved ? $gs('library_publish_approved') : $gs('library_publish_pending')) ?></span></td>
                <td colspan="6" class="muted"><?= e($gs('library_empty')) ?></td>
              </tr>
              <?php continue; ?>
            <?php endif; ?>

            <?php foreach ($libraryModules as $libraryModule): ?>
              <?php if (!is_array($libraryModule)) { continue; } ?>
              <?php
                $libraryModuleKey = (string)($libraryModule['module_key'] ?? '');
                $libraryRoute = (string)($libraryModule['route_path'] ?? '');
                $libraryVersion = (string)($libraryModule['version'] ?? '');
                $librarySnapshot = (string)($libraryModule['snapshot_id'] ?? '');
                $libraryUpdated = (string)($libraryModule['last_updated'] ?? '');
                $moduleQuery = '?app_key=' . rawurlencode($libraryAppKey) . '&module_key=' . rawurlencode($libraryModuleKey);
              ?>
              <tr>
                <td><code><?= e($libraryAppKey) ?></code></td>
                <td><span class="status-chip <?= e($libraryStatusClass) ?>"><?= e($libraryAppStatus) ?></span></td>
                <td><span class="status-chip <?= $libraryPublishApproved ? 'success' : 'warning' ?>"><?= e($libraryPublishApproved ? $gs('library_publish_approved') : $gs('library_publish_pending')) ?></span></td>
                <td><code><?= e($libraryModuleKey) ?></code></td>
                <td><code><?= e($libraryRoute) ?></code></td>
                <td><code><?= e($libraryVersion !== '' ? $libraryVersion : '-') ?></code></td>
                <td><code><?= e($librarySnapshot !== '' ? $librarySnapshot : '-') ?></code></td>
                <td><?= e($libraryUpdated !== '' ? $libraryUpdated : '-') ?></td>
                <td>
                  <div class="form-actions">
                    <a class="btn" href="/apps/studio/library/module<?= e($moduleQuery) ?>"><?= e($gs('inspect')) ?></a>
                    <button
                      type="button"
                      class="btn"
                      data-load-module
                      data-app-key="<?= e($libraryAppKey) ?>"
                      data-module-key="<?= e($libraryModuleKey) ?>"
                    ><?= e($gs('import_existing_view')) ?></button>
                    <form method="POST" action="/apps/studio/lifecycle" class="inline-form">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="app_key" value="<?= e($libraryAppKey) ?>">
                      <button class="btn" type="submit" name="action" value="disable"><?= e($gs('disable')) ?></button>
                    </form>
                    <form method="POST" action="/apps/studio/lifecycle" class="inline-form">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="app_key" value="<?= e($libraryAppKey) ?>">
                      <button class="btn danger" type="submit" name="action" value="uninstall"><?= e($gs('uninstall')) ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

  <form method="post" action="/apps/studio/validate" class="stack" id="gs-structured-editor-form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" id="gs_studio_mode" name="studio_mode" value="<?= e($studioMode === 'edit_existing' ? 'edit_existing' : 'create_new') ?>">
    <input type="hidden" name="se_editor_enabled" value="1">
    <input type="hidden" name="se_fields_state" id="gs_se_fields_state" value="[]">
    <input type="hidden" name="se_view_columns_state" id="gs_se_view_columns_state" value="[]">
    <input type="hidden" name="se_layout_state" id="gs_se_layout_state" value="[]">
    <input type="hidden" name="se_layout_relations_state" id="gs_se_layout_relations_state" value="[]">
    <input type="hidden" name="se_preview_mode" id="gs_se_preview_mode" value="0">
    <input type="hidden" name="se_previous_bundle" id="gs_se_previous_bundle" value="<?= e($getInput('se_previous_bundle', $inputs)) ?>">
    <input type="hidden" name="se_current_bundle" id="gs_se_current_bundle" value="">

    <label class="form-label" for="gs_app_manifest"><?= e($gs('app_manifest')) ?></label>
    <textarea id="gs_app_manifest" name="app_manifest" class="form-input code-input" rows="10"><?= e($getInput('app_manifest', $inputs)) ?></textarea>

    <h4><?= e($gs('module_settings')) ?></h4>
    <div class="form-actions">
      <label class="form-label" for="gs_se_module_key"><?= e($gs('module_key')) ?></label>
      <input class="form-input" id="gs_se_module_key" name="se_module_key" type="text" value="">

      <label class="form-label" for="gs_se_module_display_name"><?= e($gs('module_display_name')) ?></label>
      <input class="form-input" id="gs_se_module_display_name" name="se_module_display_name" type="text" value="">

      <label class="form-label gs-module-details-control" for="gs_se_module_type"><?= e($gs('module_type')) ?></label>
      <select class="form-input gs-module-details-control" id="gs_se_module_type" name="se_module_type">
        <option value="crud">crud</option>
        <option value="dashboard">dashboard</option>
        <option value="queue_workflow">queue_workflow</option>
      </select>
    </div>

    <label class="form-label gs-module-details-control" for="gs_se_module_description"><?= e($gs('module_description')) ?></label>
    <input class="form-input gs-module-details-control" id="gs_se_module_description" name="se_module_description" type="text" value="">

    <label class="form-label gs-route-path-control" for="gs_se_route_path"><?= e($gs('route_path')) ?></label>
    <input class="form-input gs-route-path-control" id="gs_se_route_path" name="se_route_path" type="text" value="">

    <h4><?= e($gs('field_editor')) ?></h4>
    <div class="table-wrap">
      <table class="table" id="gs-fields-table">
        <thead>
          <tr>
            <th><?= e($gs('field_name')) ?></th>
            <th><?= e($gs('field_type')) ?></th>
            <th><?= e($gs('field_required')) ?></th>
            <th><?= e($gs('field_default')) ?></th>
            <th><?= e($gs('action')) ?></th>
          </tr>
        </thead>
        <tbody id="gs-fields-body"></tbody>
      </table>
    </div>
    <button type="button" class="btn" id="gs-add-field"><?= e($gs('add_field')) ?></button>

    <h4><?= e($gs('view_config_editor')) ?></h4>
    <div class="form-actions">
      <label class="form-label gs-create-intent-control" for="gs_se_create_intent"><?= e($gs('create_intent_label')) ?></label>
      <select class="form-input gs-create-intent-control" id="gs_se_create_intent" name="se_create_intent">
        <option value="create_app"><?= e($gs('create_intent_app')) ?></option>
        <option value="create_module"><?= e($gs('create_intent_module')) ?></option>
        <option value="create_view" selected><?= e($gs('create_intent_view')) ?></option>
        <option value="create_navigation"><?= e($gs('create_intent_navigation')) ?></option>
        <option value="create_dashboard"><?= e($gs('create_intent_dashboard')) ?></option>
      </select>
      <label class="form-label gs-view-kind-control" for="gs_se_view_kind"><?= e($gs('view_type')) ?></label>
      <select class="form-input gs-view-kind-control" id="gs_se_view_kind" name="se_view_kind">
        <option value="table"><?= e($gs('view_type_table')) ?></option>
        <option value="form"><?= e($gs('view_type_form')) ?></option>
        <option value="dashboard"><?= e($gs('view_type_dashboard')) ?></option>
      </select>
      <label class="form-label gs-table-edit-mode-control" for="gs_se_table_edit_mode"><?= e($gs('table_edit_mode_label')) ?></label>
      <select class="form-input gs-table-edit-mode-control" id="gs_se_table_edit_mode" name="se_table_edit_mode">
        <option value="view_only"><?= e($gs('table_edit_mode_view_only')) ?></option>
        <option value="direct_db"><?= e($gs('table_edit_mode_direct_db')) ?></option>
      </select>
      <div class="note gs-table-edit-mode-note"></div>
      <label class="form-label u-style-18ae89f4a0">
        <input type="checkbox" id="gs_se_layout_compact" name="se_layout_compact" value="1">
        <?= e($gs('layout_compact')) ?>
      </label>
    </div>
    <div class="ui-block">
      <div class="form-label"><?= e($gs('visible_columns')) ?></div>
      <div id="gs-view-columns" class="form-actions"></div>
    </div>

    <h4><?= e($gs('visual_builder_title')) ?></h4>
    <p class="muted"><?= e($gs('visual_builder_note')) ?></p>
    <label class="form-label u-style-18ae89f4a0" for="gs_visual_preview_toggle">
      <input type="checkbox" id="gs_visual_preview_toggle" value="1">
      <?= e($gs('visual_builder_preview_toggle')) ?>
    </label>
    <div class="form-actions u-style-6fb527b957" id="gs-visual-builder">
      <div class="u-style-b2160925c1">
        <div class="view-manager">
          <h3 class="u-style-1da9facb4d"><?= e($gs('visual_builder_views_title')) ?></h3>
          <button type="button" class="btn" id="btn-add-view"><?= e($gs('visual_builder_add_view')) ?></button>
          <button type="button" class="btn" id="btn-export-app"><?= e($gs('visual_builder_export_app')) ?></button>
          <button type="button" class="btn" id="btn-import-app"><?= e($gs('visual_builder_import_app')) ?></button>
          <input class="u-style-c8be1ccba6" type="file" id="input-import-app" accept="application/json,.json">
          <ul id="view-list"></ul>
        </div>
        <div class="template-panel">
          <div class="form-label"><?= e($gs('visual_builder_templates_title')) ?></div>
          <div class="form-label u-style-04cdf78d3c"><?= e($gs('visual_builder_recommended_templates_title')) ?></div>
          <div id="gs-recommended-templates" class="stack"></div>
          <div class="form-label u-style-c9ef2a58eb"><?= e($gs('visual_builder_all_templates_title')) ?></div>
          <div id="gs-all-templates" class="stack"></div>
          <button type="button" class="btn" id="btn-save-template"><?= e($gs('visual_builder_save_template')) ?></button>
          <div class="form-label u-style-c9ef2a58eb"><?= e($gs('visual_builder_my_templates_title')) ?></div>
          <div id="gs-user-templates" class="stack"></div>
        </div>
        <div class="smart-suggestions">
          <h3 class="u-style-1da9facb4d"><?= e($gs('visual_builder_suggestions_title')) ?></h3>
          <div id="gs-suggestions" class="stack"></div>
        </div>
        <div class="form-label"><?= e($gs('visual_builder_palette')) ?></div>
        <div id="gs-component-palette" class="stack"></div>
      </div>
      <div class="u-style-8e5a522962">
        <div class="form-label"><?= e($gs('visual_builder_canvas')) ?></div>
        <div id="gs-layout-warning" class="note warning u-style-c8be1ccba6"></div>
        <div class="u-style-0c6963b2c2" id="gs-layout-canvas"></div>
      </div>
      <div class="u-style-b2160925c1">
        <div class="form-label"><?= e($gs('visual_builder_items')) ?></div>
        <div id="gs-layout-items" class="stack"></div>
        <div class="form-label u-style-21d8fb5d75"><?= e($gs('visual_builder_binding_schema')) ?></div>
        <div id="gs-binding-schema-tree" class="note"></div>
        <div class="u-style-21d8fb5d75" id="component-config-panel">
          <h3 class="u-style-aedc8f4b3c"><?= e($gs('component_settings_title')) ?></h3>
          <div id="gs-component-config" class="stack">
            <div class="muted"><?= e($gs('visual_builder_select_component_hint')) ?></div>
          </div>
        </div>
        <div class="form-label u-style-21d8fb5d75"><?= e($gs('visual_builder_relations')) ?></div>
        <div id="gs-layout-relations" class="stack"></div>
        <button type="button" class="btn" id="gs-add-relation"><?= e($gs('visual_builder_add_relation')) ?></button>
      </div>
    </div>

    <h4><?= e($gs('navigation_editor')) ?></h4>
    <div class="form-actions">
      <label class="form-label" for="gs_se_nav_section"><?= e($gs('navigation_section')) ?></label>
      <select class="form-input" id="gs_se_nav_section" name="se_nav_section">
        <option value="Operations"><?= e($gs('navigation_section_operations')) ?></option>
        <option value="Apps"><?= e($gs('navigation_section_apps')) ?></option>
        <option value="Admin / System"><?= e($gs('navigation_section_admin_system')) ?></option>
      </select>

      <label class="form-label" for="gs_se_nav_group"><?= e($gs('navigation_group')) ?></label>
      <input class="form-input" id="gs_se_nav_group" name="se_nav_group" type="text" value="Apps">

      <label class="form-label" for="gs_se_nav_label"><?= e($gs('navigation_label')) ?></label>
      <input class="form-input" id="gs_se_nav_label" name="se_nav_label" type="text" value="">
    </div>

    <div class="table-summary-badges">
      <span class="status-chip success"><?= e($gs('structured_mapping_ok')) ?></span>
    </div>

    <h4><?= e($gs('change_summary_title')) ?></h4>
    <div id="gs-change-summary" class="stack">
      <div class="table-summary-badges">
        <span class="status-chip"><?= e($gs('artifact_count')) ?>: <strong id="gs-change-total">0</strong></span>
        <span class="status-chip danger"><?= e($gs('change_severity_breaking')) ?>: <strong id="gs-change-high">0</strong></span>
        <span class="status-chip warning"><?= e($gs('change_severity_additive')) ?>: <strong id="gs-change-medium">0</strong></span>
        <span class="status-chip success"><?= e($gs('change_severity_safe')) ?>: <strong id="gs-change-low">0</strong></span>
      </div>
      <div id="gs-breaking-changes-banner" class="note warning" style="display:none">
        <strong><?= e($gs('change_severity_breaking')) ?></strong>: <?= e($gs('breaking_changes_warning')) ?>
      </div>
      <div id="gs-change-risk-note" class="note warning u-style-c8be1ccba6"><?= e($gs('change_requires_ack')) ?></div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= e($gs('category')) ?></th>
              <th><?= e($gs('change_type')) ?></th>
              <th><?= e($gs('detail')) ?></th>
              <th><?= e($gs('risk_level')) ?></th>
              <th><?= e($gs('impact_severity')) ?></th>
            </tr>
          </thead>
          <tbody id="gs-change-summary-body">
            <tr>
              <td colspan="5" class="muted"><?= e($gs('change_summary_empty')) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <h4><?= e($gs('migration_plan_title')) ?></h4>
    <div id="gs-migration-plan" class="stack">
      <div id="gs-migration-warning" class="note warning u-style-c8be1ccba6"><?= e($gs('migration_warning_destructive')) ?></div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= e($gs('migration_action')) ?></th>
              <th><?= e($gs('field_name')) ?></th>
              <th><?= e($gs('migration_strategy')) ?></th>
              <th><?= e($gs('risk_level')) ?></th>
            </tr>
          </thead>
          <tbody id="gs-migration-plan-body">
            <tr>
              <td colspan="4" class="muted"><?= e($gs('migration_empty')) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <h4><?= e($gs('impact_analysis_title')) ?></h4>
    <div id="gs-impact-analysis" class="stack">
      <div id="gs-impact-warning" class="note warning u-style-c8be1ccba6"><?= e($gs('impact_warning_high')) ?></div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= e($gs('impact_change')) ?></th>
              <th><?= e($gs('field_name')) ?></th>
              <th><?= e($gs('impact_affected_components')) ?></th>
              <th><?= e($gs('impact_severity')) ?></th>
            </tr>
          </thead>
          <tbody id="gs-impact-analysis-body">
            <tr>
              <td colspan="4" class="muted"><?= e($gs('impact_empty')) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <h4><?= e($gs('simulation_preview_title')) ?></h4>
    <div id="gs-simulation-preview" class="stack">
      <div id="gs-simulation-warning" class="note warning u-style-c8be1ccba6"><?= e($gs('simulation_warning')) ?></div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= e($gs('simulation_views_after')) ?></th>
              <th><?= e($gs('simulation_broken_views')) ?></th>
              <th><?= e($gs('simulation_removed_fields')) ?></th>
              <th><?= e($gs('simulation_new_fields')) ?></th>
            </tr>
          </thead>
          <tbody id="gs-simulation-preview-body">
            <tr>
              <td colspan="4" class="muted"><?= e($gs('simulation_empty')) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <label class="form-label" for="gs_compile_reason"><?= e($gs('reason')) ?></label>
    <textarea class="form-input" id="gs_compile_reason" name="reason" rows="3"><?= e($getInput('reason', $inputs)) ?></textarea>

    <label class="form-label u-style-18ae89f4a0" for="gs_compile_risk_ack">
      <input type="checkbox" id="gs_compile_risk_ack" name="risk_acknowledged" value="1"<?= $getInput('risk_acknowledged', $inputs) === '1' ? ' checked' : '' ?>>
      <?= e($gs('risk_ack_label')) ?>
    </label>

    <label class="form-label u-style-18ae89f4a0" for="gs_migration_override">
      <input type="checkbox" id="gs_migration_override" name="migration_override" value="1"<?= $getInput('migration_override', $inputs) === '1' ? ' checked' : '' ?>>
      <?= e($gs('migration_override_label')) ?>
    </label>

    <label class="form-label" for="gs_migration_override_reason"><?= e($gs('migration_override_reason')) ?></label>
    <textarea class="form-input" id="gs_migration_override_reason" name="migration_override_reason" rows="2"><?= e($getInput('migration_override_reason', $inputs)) ?></textarea>

    <label class="form-label u-style-18ae89f4a0" for="gs_impact_confirmation">
      <input type="checkbox" id="gs_impact_confirmation" name="impact_confirmation" value="1"<?= $getInput('impact_confirmation', $inputs) === '1' ? ' checked' : '' ?>>
      <?= e($gs('impact_confirmation_label')) ?>
    </label>

    <label class="form-label u-style-18ae89f4a0" for="gs_impact_acknowledged">
      <input type="checkbox" id="gs_impact_acknowledged" name="impact_acknowledged" value="1"<?= $getInput('impact_acknowledged', $inputs) === '1' ? ' checked' : '' ?>>
      <?= e($gs('impact_ack_label')) ?>
    </label>

    <label class="form-label u-style-18ae89f4a0" for="gs_simulation_override">
      <input type="checkbox" id="gs_simulation_override" name="simulation_override" value="1"<?= $getInput('simulation_override', $inputs) === '1' ? ' checked' : '' ?>>
      <?= e($gs('simulation_override_label')) ?>
    </label>

    <label class="form-label" for="gs_simulation_override_reason"><?= e($gs('simulation_override_reason')) ?></label>
    <textarea class="form-input" id="gs_simulation_override_reason" name="simulation_override_reason" rows="2"><?= e($getInput('simulation_override_reason', $inputs)) ?></textarea>

    <textarea id="gs_module_manifest" name="module_manifest" class="form-input code-input u-style-c8be1ccba6" rows="10" readonly><?= e($getInput('module_manifest', $inputs)) ?></textarea>
    <textarea id="gs_view_definition" name="view_definition" class="form-input code-input u-style-c8be1ccba6" rows="10" readonly><?= e($getInput('view_definition', $inputs)) ?></textarea>
    <textarea id="gs_navigation_definition" name="navigation_definition" class="form-input code-input u-style-c8be1ccba6" rows="10" readonly><?= e($getInput('navigation_definition', $inputs)) ?></textarea>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= e($gs('validate')) ?></button>
      <button type="submit" class="btn" formaction="/apps/studio/compile-plan"><?= e($gs('compile')) ?></button>
      <button type="submit" class="btn" formaction="/apps/studio/preflight"><?= e($gs('preflight_btn')) ?></button>
    </div>
  </form>
</section>

<?php if ($result !== null): ?>
<section class="card">
  <h3><?= e($gs('results')) ?></h3>

  <?php $checks = isset($result['checks']) && is_array($result['checks']) ? $result['checks'] : []; ?>
  <?php if ($checks !== []): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('check')) ?></th>
            <th><?= e($gs('status')) ?></th>
            <th><?= e($gs('detail')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($checks as $check): ?>
            <?php
              $pass = (bool)($check['pass'] ?? false);
              $name = (string)($check['name'] ?? '');
              $msgKey = (string)($check['message_key'] ?? '');
              $ctx = (string)($check['context'] ?? '');
              $message = $msgKey !== '' ? t($msgKey) : '';
              if ($message === $msgKey && $name === 'no_arbitrary_php') {
                  $message = $pass ? $gs('check.no_php.ok') : $gs('check.no_php.invalid');
              }
            ?>
            <tr>
              <td><?= e($name) ?></td>
              <td><?= $pass ? e($gs('pass')) : e($gs('fail')) ?></td>
              <td><?= e($message) ?><?= $ctx !== '' ? (' (' . e($ctx) . ')') : '' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : []; ?>
  <?php if ($errors !== []): ?>
    <div class="ui-block">
      <div class="form-label"><?= e($gs('errors')) ?></div>
      <ul>
        <?php foreach ($errors as $errKey): ?>
          <?php
            $errorMessage = t((string)$errKey);
            if ($errorMessage === (string)$errKey && (string)$errKey === 'ops.gui_studio.error.no_php') {
                $errorMessage = $gs('error.no_php');
            }
            if ($errorMessage === (string)$errKey && str_starts_with((string)$errKey, 'ops.gui_studio.')) {
                $fallbackErrorKey = str_replace('ops.gui_studio.', '', (string)$errKey);
                $fallbackMessage = $gs($fallbackErrorKey);
                if ($fallbackMessage !== $fallbackErrorKey) {
                    $errorMessage = $fallbackMessage;
                }
            }
          ?>
          <li><?= e($errorMessage) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php
    $compilePlan = isset($result['compile_plan']) && is_array($result['compile_plan']) ? $result['compile_plan'] : [];
    $compileArtifacts = is_array($compilePlan['artifacts'] ?? null) ? $compilePlan['artifacts'] : $compilePlan;
    $compileLayers = is_array($compilePlan['layers'] ?? null) ? $compilePlan['layers'] : [];
    $artifactTaxonomy = is_array($compilePlan['artifact_taxonomy'] ?? null) ? $compilePlan['artifact_taxonomy'] : [];
    $dependencyChecks = is_array($compilePlan['dependency_checks'] ?? null) ? $compilePlan['dependency_checks'] : [];
    $conflictChecks = is_array($compilePlan['conflict_checks'] ?? null) ? $compilePlan['conflict_checks'] : [];
    $normalizedConflicts = is_array($compilePlan['normalized_conflicts'] ?? null) ? $compilePlan['normalized_conflicts'] : [];
    $driftChecks = is_array($compilePlan['drift_checks'] ?? null) ? $compilePlan['drift_checks'] : [];
    $diffReadiness = is_array($compilePlan['diff_readiness'] ?? null) ? $compilePlan['diff_readiness'] : [];
    $summaryGroups = is_array($compilePlan['summary_groups'] ?? null) ? $compilePlan['summary_groups'] : [];
    $compileSnapshot = is_array($compilePlan['compile_snapshot'] ?? null) ? $compilePlan['compile_snapshot'] : [];
    $compileMeta = is_array($compilePlan['compile_meta'] ?? null) ? $compilePlan['compile_meta'] : [];
    $migrationPlan = is_array($compilePlan['migration_plan'] ?? null)
      ? $compilePlan['migration_plan']
      : (isset($result['migration_plan']) && is_array($result['migration_plan']) ? $result['migration_plan'] : []);
    $migrationGuard = isset($result['migration_guard']) && is_array($result['migration_guard'])
      ? $result['migration_guard']
      : (is_array($compilePlan['migration_guard'] ?? null) ? $compilePlan['migration_guard'] : []);
    $impactAnalysis = is_array($compilePlan['impact_analysis'] ?? null)
      ? $compilePlan['impact_analysis']
      : (isset($result['impact_analysis']) && is_array($result['impact_analysis']) ? $result['impact_analysis'] : []);
    $impactGuard = isset($result['impact_guard']) && is_array($result['impact_guard'])
      ? $result['impact_guard']
      : (is_array($compilePlan['impact_guard'] ?? null) ? $compilePlan['impact_guard'] : []);
    $diffViewModel = isset($result['diff_view_model']) && is_array($result['diff_view_model']) ? $result['diff_view_model'] : [];
    $diffGroups = is_array($diffViewModel['groups'] ?? null) ? $diffViewModel['groups'] : [];
    $diffFilters = is_array($diffViewModel['filters'] ?? null) ? $diffViewModel['filters'] : [];
    $diffSummary = is_array($diffViewModel['summary'] ?? null) ? $diffViewModel['summary'] : [];
    $approvalPayload = isset($result['approval_payload']) && is_array($result['approval_payload']) ? $result['approval_payload'] : [];
    $approvalValidation = isset($result['approval_validation']) && is_array($result['approval_validation']) ? $result['approval_validation'] : [];
    $approvalSummary = isset($result['approval_summary']) && is_array($result['approval_summary']) ? $result['approval_summary'] : [];
    $snapshot = isset($result['snapshot']) && is_array($result['snapshot']) ? $result['snapshot'] : [];
    $snapshotSummary = isset($result['snapshot_summary']) && is_array($result['snapshot_summary']) ? $result['snapshot_summary'] : [];
    $execution = isset($result['execution']) && is_array($result['execution']) ? $result['execution'] : [];
    $snapshotValid = !empty($approvalValidation['valid']);
    $executionCanExecute = !empty($execution['can_execute']);
    $approvalHighRiskCount = (int)($approvalSummary['high_risk_count'] ?? 0);
    $approvalBlockedCount = (int)($approvalSummary['blocked_count'] ?? 0);
    if ($approvalSummary === []) {
        foreach ($compileArtifacts as $artifact) {
            if (!is_array($artifact)) { continue; }
            $riskLevel = strtoupper((string)($artifact['risk_level'] ?? ''));
            if ($riskLevel === 'HIGH') { $approvalHighRiskCount++; }
            if ($riskLevel === 'BLOCKED') { $approvalBlockedCount++; }
        }
    }
  ?>
  <?php if ($compileArtifacts !== []): ?>
    <h3><?= e($gs('artifact_types')) ?></h3>
    <p class="muted"><?= e(implode(', ', array_map('strval', $artifactTaxonomy))) ?></p>

    <?php if ($compileSnapshot !== []): ?>
      <h3><?= e($gs('compile_snapshot_identity')) ?></h3>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= e($gs('compile_id')) ?></th>
              <th><?= e($gs('bundle_hash')) ?></th>
              <th><?= e($gs('artifact_count')) ?></th>
              <th><?= e($gs('dependency_check_count')) ?></th>
              <th><?= e($gs('conflict_check_count')) ?></th>
              <th><?= e($gs('drift_check_count')) ?></th>
              <th><?= e($gs('generated_at')) ?></th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code><?= e((string)($compileSnapshot['compile_id'] ?? '')) ?></code></td>
              <td><code><?= e((string)($compileSnapshot['bundle_hash'] ?? '')) ?></code></td>
              <td><?= e((string)($compileSnapshot['artifact_count'] ?? '')) ?></td>
              <td><?= e((string)($compileSnapshot['dependency_check_count'] ?? '')) ?></td>
              <td><?= e((string)($compileSnapshot['conflict_check_count'] ?? '')) ?></td>
              <td><?= e((string)($compileSnapshot['drift_check_count'] ?? '')) ?></td>
              <td><?= e((string)($compileSnapshot['generated_at'] ?? '')) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($compileMeta !== []): ?>
      <h3><?= e($gs('compile_lineage')) ?></h3>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= e($gs('compile_id')) ?></th>
              <th><?= e($gs('parent_compile_id')) ?></th>
              <th><?= e($gs('bundle_hash')) ?></th>
              <th><?= e($gs('schema_version')) ?></th>
              <th><?= e($gs('unique_conflicts')) ?></th>
              <th><?= e($gs('total_conflict_instances')) ?></th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code><?= e((string)($compileMeta['compile_id'] ?? '')) ?></code></td>
              <td><code><?= e((string)($compileMeta['parent_compile_id'] ?? 'null')) ?></code></td>
              <td><code><?= e((string)($compileMeta['bundle_hash'] ?? '')) ?></code></td>
              <td><?= e((string)($compileMeta['schema_version'] ?? '')) ?></td>
              <td><?= e((string)($normalizedConflicts['unique_conflicts'] ?? 0)) ?></td>
              <td><?= e((string)($normalizedConflicts['total_instances'] ?? 0)) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($compileLayers !== []): ?>
      <h3><?= e($gs('compile_graph')) ?></h3>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= e($gs('layer')) ?></th>
              <th><?= e($gs('artifact_id')) ?></th>
              <th><?= e($gs('artifact_type')) ?></th>
              <th><?= e($gs('target_path')) ?></th>
              <th><?= e($gs('dependencies')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($compileLayers as $layerName => $layerArtifacts): ?>
              <?php foreach ((array)$layerArtifacts as $artifact): ?>
                <?php if (!is_array($artifact)) { continue; } ?>
                <tr>
                  <td><?= e((string)$layerName) ?></td>
                  <td><code><?= e((string)($artifact['artifact_id'] ?? '')) ?></code></td>
                  <td><?= e((string)($artifact['artifact_type'] ?? '')) ?></td>
                  <td><code><?= e((string)($artifact['target_path'] ?? '')) ?></code></td>
                  <td><?= e(implode(', ', array_map('strval', (array)($artifact['dependencies'] ?? [])))) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <h3><?= e($gs('compile_plan')) ?></h3>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('artifact_id')) ?></th>
            <th><?= e($gs('target_path')) ?></th>
            <th><?= e($gs('artifact_type')) ?></th>
            <th><?= e($gs('change_type')) ?></th>
            <th><?= e($gs('risk_level')) ?></th>
            <th><?= e($gs('risk_score')) ?></th>
            <th><?= e($gs('dominant_reason')) ?></th>
            <th><?= e($gs('ownership_scope')) ?></th>
            <th><?= e($gs('customization_zone')) ?></th>
            <th><?= e($gs('upgrade_safe')) ?></th>
            <th><?= e($gs('drift_status')) ?></th>
            <th><?= e($gs('source_template')) ?></th>
            <th><?= e($gs('content_hash')) ?></th>
            <th><?= e($gs('generated_by')) ?></th>
            <th><?= e($gs('studio_project_id')) ?></th>
            <th><?= e($gs('studio_draft_id')) ?></th>
            <th><?= e($gs('summary')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($compileArtifacts as $artifact): ?>
            <?php if (!is_array($artifact)) { continue; } ?>
            <tr>
              <td><code><?= e((string)($artifact['artifact_id'] ?? '')) ?></code></td>
              <td><code><?= e((string)($artifact['target_path'] ?? '')) ?></code></td>
              <td><?= e((string)($artifact['artifact_type'] ?? '')) ?></td>
              <td><?= e((string)($artifact['change_type'] ?? '')) ?></td>
              <td><?= e((string)($artifact['risk_level'] ?? '')) ?></td>
              <td><?= e((string)($artifact['risk_meta']['risk_score'] ?? '')) ?></td>
              <td><?= e((string)($artifact['risk_meta']['dominant_reason'] ?? '')) ?></td>
              <td><?= e((string)($artifact['ownership_scope'] ?? '')) ?></td>
              <td><?= e((string)($artifact['customization_zone'] ?? '')) ?></td>
              <td><?= !empty($artifact['upgrade_safe']) ? e($gs('bool.true')) : e($gs('bool.false')) ?></td>
              <td><?= e((string)($artifact['drift_status'] ?? '')) ?></td>
              <td><?= e((string)($artifact['source_template'] ?? '')) ?></td>
              <td><code><?= e((string)($artifact['content_hash'] ?? '')) ?></code></td>
              <td><?= e((string)($artifact['generated_by'] ?? '')) ?></td>
              <td><?= e((string)($artifact['studio_project_id'] ?? '')) ?></td>
              <td><?= e((string)($artifact['studio_draft_id'] ?? '')) ?></td>
              <td><?= e((string)($artifact['human_summary'] ?? $artifact['summary'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($dependencyChecks !== []): ?>
      <h3><?= e($gs('dependency_checks')) ?></h3>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e($gs('check')) ?></th><th><?= e($gs('status')) ?></th><th><?= e($gs('summary')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($dependencyChecks as $check): ?>
              <?php if (!is_array($check)) { continue; } ?>
              <tr>
                <td><?= e((string)($check['check_key'] ?? '')) ?></td>
                <td><?= e((string)($check['status'] ?? '')) ?></td>
                <td><?= e((string)($check['summary'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($conflictChecks !== []): ?>
      <h3><?= e($gs('conflict_checks')) ?></h3>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e($gs('check')) ?></th><th><?= e($gs('artifact_id')) ?></th><th><?= e($gs('target_path')) ?></th><th><?= e($gs('status')) ?></th><th><?= e($gs('summary')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($conflictChecks as $check): ?>
              <?php if (!is_array($check)) { continue; } ?>
              <tr>
                <td><?= e((string)($check['check_key'] ?? '')) ?></td>
                <td><code><?= e((string)($check['artifact_id'] ?? '')) ?></code></td>
                <td><code><?= e((string)($check['target_path'] ?? '')) ?></code></td>
                <td><?= e((string)($check['status'] ?? '')) ?></td>
                <td><?= e((string)($check['summary'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($driftChecks !== []): ?>
      <h3><?= e($gs('drift_status')) ?></h3>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e($gs('check')) ?></th><th><?= e($gs('artifact_id')) ?></th><th><?= e($gs('target_path')) ?></th><th><?= e($gs('status')) ?></th><th><?= e($gs('summary')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($driftChecks as $check): ?>
              <?php if (!is_array($check)) { continue; } ?>
              <tr>
                <td><?= e((string)($check['check_key'] ?? '')) ?></td>
                <td><code><?= e((string)($check['artifact_id'] ?? '')) ?></code></td>
                <td><code><?= e((string)($check['target_path'] ?? '')) ?></code></td>
                <td><?= e((string)($check['status'] ?? '')) ?></td>
                <td><?= e((string)($check['summary'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($summaryGroups !== []): ?>
      <h3><?= e($gs('human_diff_summary')) ?></h3>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><?= e($gs('summary_group')) ?></th><th><?= e($gs('summary')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($summaryGroups as $groupName => $items): ?>
              <tr>
                <td><?= e((string)$groupName) ?></td>
                <td><?= e(implode('; ', array_map('strval', (array)$items))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if ($diffGroups !== []): ?>
      <section class="card" id="gs-diff-preview">
        <h2><?= e($gs('diff_preview')) ?></h2>

        <?php if ($diffSummary !== []): ?>
          <div class="table-summary-badges">
            <span class="status-chip"><?= e($gs('artifact_count')) ?>: <?= e((string)($diffSummary['total_artifacts'] ?? 0)) ?></span>
            <span class="status-chip success">+: <?= e((string)($diffSummary['created'] ?? 0)) ?></span>
            <span class="status-chip warning">!: <?= e((string)($diffSummary['risky'] ?? 0)) ?></span>
            <span class="status-chip danger">✕: <?= e((string)($diffSummary['blocked'] ?? 0)) ?></span>
          </div>
        <?php endif; ?>

        <div class="filters" data-diff-filters>
          <div class="form-label"><?= e($gs('filters')) ?></div>
          <div class="form-actions">
            <label class="form-label" for="gs-diff-risk"><?= e($gs('risk_filter')) ?></label>
            <select class="form-input" id="gs-diff-risk" data-diff-filter="risk">
              <option value="ALL"><?= e($gs('filter_all')) ?></option>
              <option value="HIGH">HIGH</option>
              <option value="BLOCKED">BLOCKED</option>
            </select>

            <label class="form-label" for="gs-diff-ownership"><?= e($gs('ownership_filter')) ?></label>
            <select class="form-input" id="gs-diff-ownership" data-diff-filter="ownership">
              <option value="ALL"><?= e($gs('filter_all')) ?></option>
              <option value="APP">APP</option>
              <option value="SYSTEM">SYSTEM</option>
              <option value="EXTERNAL">EXTERNAL</option>
            </select>

            <label class="form-label" for="gs-diff-drift"><?= e($gs('drift_filter')) ?></label>
            <select class="form-input" id="gs-diff-drift" data-diff-filter="drift">
              <option value="ALL"><?= e($gs('filter_all')) ?></option>
              <option value="modified">modified</option>
              <option value="external">external</option>
              <option value="conflict">conflict</option>
            </select>
          </div>
        </div>

        <div class="diff-groups" data-diff-groups>
          <?php foreach ($diffGroups as $appName => $modules): ?>
            <section class="stack">
              <h3><?= e((string)$appName) ?></h3>
              <?php foreach ((array)$modules as $moduleName => $types): ?>
                <h3><?= e((string)$moduleName) ?></h3>
                <?php foreach ((array)$types as $artifactType => $artifacts): ?>
                  <div class="form-label"><?= e((string)$artifactType) ?></div>
                  <?php foreach ((array)$artifacts as $artifact): ?>
                    <?php
                      if (!is_array($artifact)) { continue; }
                      $risk = (string)($artifact['risk'] ?? '');
                      $drift = (string)($artifact['drift'] ?? '');
                      $ownership = (string)($artifact['ownership'] ?? '');
                      $riskClass = strtolower($risk);
                      $driftClass = strtolower($drift);
                    ?>
                    <details class="artifact-row" data-diff-row data-risk="<?= e($risk) ?>" data-ownership="<?= e($ownership) ?>" data-drift="<?= e($drift) ?>">
                      <summary>
                        <span class="symbol"><?= e((string)($artifact['symbol'] ?? '+')) ?></span>
                        <span class="artifact-name"><?= e((string)($artifact['artifact_name'] ?? $artifact['artifact'] ?? '')) ?></span>
                        <span class="status-chip risk <?= e($riskClass) ?>"><?= e($risk) ?></span>
                        <span class="status-chip drift <?= e($driftClass) ?>"><?= e($drift) ?></span>
                      </summary>
                      <div class="artifact-detail">
                        <p><?= e($gs('owner')) ?>: <?= e((string)$appName) ?></p>
                        <p><?= e($gs('ownership_scope')) ?>: <?= e((string)($artifact['ownership_scope'] ?? '')) ?></p>
                        <p><?= e($gs('drift_status')) ?>: <?= e($drift) ?></p>
                        <p><?= e($gs('risk_level')) ?>: <?= e($risk) ?></p>
                        <p><?= e($gs('risk_score')) ?>: <?= e((string)($artifact['risk_score'] ?? '')) ?></p>
                        <p><?= e($gs('dominant_reason')) ?>: <?= e((string)($artifact['dominant_reason'] ?? '')) ?></p>
                        <p><?= e($gs('summary')) ?>: <?= e((string)($artifact['summary'] ?? '')) ?></p>
                        <p><?= e($gs('target_path')) ?>: <code><?= e((string)($artifact['target_path'] ?? '')) ?></code></p>
                      </div>
                    </details>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </section>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="card" id="gs-migration-plan-result">
      <h2><?= e($gs('migration_plan_title')) ?></h2>
      <?php $hasDestructiveMigration = false; ?>
      <?php foreach ($migrationPlan as $migrationStep): ?>
        <?php if (is_array($migrationStep) && strtolower((string)($migrationStep['strategy'] ?? '')) === 'destructive') { $hasDestructiveMigration = true; break; } ?>
      <?php endforeach; ?>

      <?php if ($hasDestructiveMigration): ?>
        <div class="note warning"><?= e($gs('migration_warning_destructive')) ?></div>
      <?php endif; ?>

      <?php if ($migrationPlan === []): ?>
        <div class="note"><?= e($gs('migration_empty')) ?></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th><?= e($gs('migration_action')) ?></th>
                <th><?= e($gs('field_name')) ?></th>
                <th><?= e($gs('migration_strategy')) ?></th>
                <th><?= e($gs('risk_level')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($migrationPlan as $migrationStep): ?>
                <?php if (!is_array($migrationStep)) { continue; } ?>
                <?php
                  $risk = strtolower((string)($migrationStep['risk'] ?? 'low'));
                  $riskClass = $risk === 'high' ? 'danger' : ($risk === 'medium' ? 'warning' : 'success');
                  $strategy = (string)($migrationStep['strategy'] ?? 'safe');
                  $strategyLabel = $gs('migration.strategy.' . $strategy);
                  if ($strategyLabel === 'migration.strategy.' . $strategy) {
                      $strategyLabel = $strategy;
                  }
                ?>
                <tr>
                  <td><?= e((string)($migrationStep['action'] ?? '')) ?></td>
                  <td><code><?= e((string)($migrationStep['field'] ?? '')) ?></code></td>
                  <td><?= e($strategyLabel) ?></td>
                  <td><span class="status-chip <?= e($riskClass) ?>"><?= e(strtoupper($risk)) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($migrationGuard !== [] && !empty($migrationGuard['errors'])): ?>
        <ul>
          <?php foreach ((array)$migrationGuard['errors'] as $migrationErrorKey): ?>
            <?php
              $migrationErrorText = t((string)$migrationErrorKey);
              $fallbackMigrationKey = str_replace('ops.gui_studio.', '', (string)$migrationErrorKey);
              if ($migrationErrorText === (string)$migrationErrorKey) {
                  $migrationErrorText = $gs($fallbackMigrationKey);
              }
            ?>
            <li><?= e($migrationErrorText) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="card" id="gs-impact-analysis-result">
      <h2><?= e($gs('impact_analysis_title')) ?></h2>
      <?php $hasHighImpact = false; ?>
      <?php foreach ($impactAnalysis as $impactRow): ?>
        <?php if (is_array($impactRow) && strtolower((string)($impactRow['severity'] ?? 'low')) === 'high') { $hasHighImpact = true; break; } ?>
      <?php endforeach; ?>

      <?php if ($hasHighImpact): ?>
        <div class="note warning"><?= e($gs('impact_warning_high')) ?></div>
      <?php endif; ?>

      <?php if ($impactAnalysis === []): ?>
        <div class="note"><?= e($gs('impact_empty')) ?></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th><?= e($gs('impact_change')) ?></th>
                <th><?= e($gs('field_name')) ?></th>
                <th><?= e($gs('impact_affected_components')) ?></th>
                <th><?= e($gs('impact_severity')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($impactAnalysis as $impactRow): ?>
                <?php if (!is_array($impactRow)) { continue; } ?>
                <?php
                  $severity = strtolower((string)($impactRow['severity'] ?? 'low'));
                  $severityClass = $severity === 'high' ? 'danger' : ($severity === 'medium' ? 'warning' : 'success');
                  $affectedComponents = is_array($impactRow['affects'] ?? null) ? $impactRow['affects'] : [];
                ?>
                <tr>
                  <td><?= e((string)($impactRow['change'] ?? '')) ?></td>
                  <td><code><?= e((string)($impactRow['field'] ?? $impactRow['path'] ?? '')) ?></code></td>
                  <td><?= e(implode(', ', array_map('strval', $affectedComponents))) ?></td>
                  <td><span class="status-chip <?= e($severityClass) ?>"><?= e(strtoupper($severity)) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($impactGuard !== [] && !empty($impactGuard['errors'])): ?>
        <ul>
          <?php foreach ((array)$impactGuard['errors'] as $impactErrorKey): ?>
            <?php
              $impactErrorText = t((string)$impactErrorKey);
              $fallbackImpactKey = str_replace('ops.gui_studio.', '', (string)$impactErrorKey);
              if ($impactErrorText === (string)$impactErrorKey) {
                  $impactErrorText = $gs($fallbackImpactKey);
              }
            ?>
            <li><?= e($impactErrorText) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php $simulationPreview = isset($result['simulation_preview']) && is_array($result['simulation_preview']) ? $result['simulation_preview'] : []; ?>
    <?php $simulationGuard = isset($result['simulation_guard']) && is_array($result['simulation_guard']) ? $result['simulation_guard'] : []; ?>
    <?php $simulationViewsAfter = is_array($simulationPreview['views_after'] ?? null) ? $simulationPreview['views_after'] : []; ?>
    <?php $simulationBrokenViews = is_array($simulationPreview['broken_views'] ?? null) ? $simulationPreview['broken_views'] : []; ?>
    <?php $simulationRemovedFields = is_array($simulationPreview['removed_fields'] ?? null) ? $simulationPreview['removed_fields'] : []; ?>
    <?php $simulationNewFields = is_array($simulationPreview['new_fields'] ?? null) ? $simulationPreview['new_fields'] : []; ?>
    <section class="card" id="gs-simulation-preview-result">
      <h2><?= e($gs('simulation_preview_title')) ?></h2>
      <?php if ($simulationBrokenViews !== []): ?>
        <div class="note warning"><?= e($gs('simulation_warning')) ?></div>
      <?php endif; ?>

      <?php if ($simulationPreview === []): ?>
        <div class="note"><?= e($gs('simulation_empty')) ?></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th><?= e($gs('simulation_views_after')) ?></th>
                <th><?= e($gs('simulation_broken_views')) ?></th>
                <th><?= e($gs('simulation_removed_fields')) ?></th>
                <th><?= e($gs('simulation_new_fields')) ?></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <?php
                    $renderedViews = [];
                    foreach ($simulationViewsAfter as $viewAfter) {
                        if (!is_array($viewAfter)) {
                            continue;
                        }
                        $renderedViews[] = (string)($viewAfter['view'] ?? '');
                    }
                  ?>
                  <?= e(implode(', ', array_filter($renderedViews, static function ($value) { return $value !== ''; }))) ?>
                </td>
                <td>
                  <?php
                    $brokenItems = [];
                    foreach ($simulationBrokenViews as $brokenView) {
                        if (!is_array($brokenView)) {
                            continue;
                        }
                        $brokenItems[] = (string)($brokenView['reason'] ?? '');
                    }
                  ?>
                  <?= e(implode(', ', array_filter($brokenItems, static function ($value) { return $value !== ''; }))) ?>
                </td>
                <td><?= e(implode(', ', array_map('strval', $simulationRemovedFields))) ?></td>
                <td><?= e(implode(', ', array_map('strval', $simulationNewFields))) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($simulationGuard !== [] && !empty($simulationGuard['errors'])): ?>
        <ul>
          <?php foreach ((array)$simulationGuard['errors'] as $simulationErrorKey): ?>
            <?php
              $simulationErrorText = t((string)$simulationErrorKey);
              $fallbackSimulationKey = str_replace('ops.gui_studio.', '', (string)$simulationErrorKey);
              if ($simulationErrorText === (string)$simulationErrorKey) {
                  $simulationErrorText = $gs($fallbackSimulationKey);
              }
            ?>
            <li><?= e($simulationErrorText) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if ($compileArtifacts !== []): ?>
      <?php
        $approvalStatusKey = 'approval_ready';
        $approvalStatusClass = 'success';
        $approvalStatusSymbol = '✅';
        if ($approvalBlockedCount > 0) {
            $approvalStatusKey = 'approval_disabled';
            $approvalStatusClass = 'danger';
            $approvalStatusSymbol = '❌';
        } elseif ($approvalHighRiskCount > 0) {
            $approvalStatusKey = 'approval_ack_required';
            $approvalStatusClass = 'warning';
            $approvalStatusSymbol = '⚠';
        }
        $approvalDecision = (string)($approvalPayload['decision'] ?? 'approved');
        $approvalReason = (string)($approvalPayload['reason'] ?? '');
        $approvalValidationErrors = is_array($approvalValidation['errors'] ?? null) ? $approvalValidation['errors'] : [];
        $approvalPayloadJson = $approvalPayload !== []
            ? (string)json_encode($approvalPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : '';
      ?>
      <section class="card" id="gs-approval-gate">
        <h2><?= e($gs('approval_gate')) ?></h2>

        <div class="approval-summary table-summary-badges">
          <span class="status-chip <?= e($approvalStatusClass) ?>"><?= e($approvalStatusSymbol . ' ' . $gs($approvalStatusKey)) ?></span>
          <span class="status-chip"><?= e($gs('artifact_count')) ?>: <?= e((string)($approvalSummary['total_artifacts'] ?? count($compileArtifacts))) ?></span>
          <span class="status-chip warning"><?= e($gs('high_risk_items')) ?>: <?= e((string)$approvalHighRiskCount) ?></span>
          <span class="status-chip danger"><?= e($gs('blocked_items')) ?>: <?= e((string)$approvalBlockedCount) ?></span>
        </div>

        <form method="post" action="/apps/studio/approval-preview" class="stack">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="se_previous_bundle" value="<?= e($getInput('se_previous_bundle', $inputs)) ?>">
          <input type="hidden" name="app_manifest" value="<?= e($getInput('app_manifest', $inputs)) ?>">
          <input type="hidden" name="module_manifest" value="<?= e($getInput('module_manifest', $inputs)) ?>">
          <input type="hidden" name="view_definition" value="<?= e($getInput('view_definition', $inputs)) ?>">
          <input type="hidden" name="navigation_definition" value="<?= e($getInput('navigation_definition', $inputs)) ?>">

          <label class="form-label" for="gs-approval-decision"><?= e($gs('decision')) ?></label>
          <select class="form-input" id="gs-approval-decision" name="decision">
            <option value="approved"<?= $approvalDecision === 'approved' ? ' selected' : '' ?>><?= e($gs('approve')) ?></option>
            <option value="rejected"<?= $approvalDecision === 'rejected' ? ' selected' : '' ?>><?= e($gs('reject')) ?></option>
          </select>

          <label class="form-label" for="gs-approval-reason"><?= e($gs('reason')) ?></label>
          <textarea class="form-input" id="gs-approval-reason" name="reason" rows="3"><?= e($approvalReason) ?></textarea>

          <label class="form-label">
            <input type="checkbox" name="risk_acknowledged" value="1"<?= !empty($approvalPayload['risk_acknowledged']) ? ' checked' : '' ?>>
            <?= e($gs('risk_ack_label')) ?>
          </label>

          <label class="form-label">
            <input type="checkbox" name="migration_override" value="1"<?= $getInput('migration_override', $inputs) === '1' ? ' checked' : '' ?>>
            <?= e($gs('migration_override_label')) ?>
          </label>

          <label class="form-label" for="gs-approval-migration-reason"><?= e($gs('migration_override_reason')) ?></label>
          <textarea class="form-input" id="gs-approval-migration-reason" name="migration_override_reason" rows="2"><?= e($getInput('migration_override_reason', $inputs)) ?></textarea>

          <button class="btn btn-primary" type="submit"><?= e($gs('preview_approval')) ?></button>
          <button class="btn" type="submit" formaction="/apps/studio/snapshot-preview"><?= e($gs('preview_snapshot')) ?></button>
          <button class="btn" type="submit" formaction="/apps/studio/execution-preview"><?= e($gs('preview_execution')) ?></button>
          <button class="btn" type="submit" formaction="/apps/studio/rollback-preview"><?= e($gsBatch1('rollback_preview')) ?></button>
          <button class="btn btn-primary" type="submit" formaction="/apps/studio/publish-gate"><?= e($gs('publish_gate_btn')) ?></button>
          <button type="button" class="btn" disabled><?= e($gs('apply_disabled_label')) ?></button>
        </form>

        <?php if ($approvalPayload !== []): ?>
          <div class="approval-result stack">
            <h3><?= e($gs('approval_result')) ?></h3>
            <div class="table-summary-badges">
              <span class="status-chip <?= !empty($approvalValidation['valid']) ? 'success' : 'danger' ?>">
                <?= !empty($approvalValidation['valid']) ? e($gs('valid')) : e($gs('invalid')) ?>
              </span>
              <span class="status-chip"><?= e($gs('approval_id')) ?>: <code><?= e((string)($approvalPayload['approval_id'] ?? '')) ?></code></span>
              <span class="status-chip"><?= e($gs('approved_by')) ?>: <?= e((string)($approvalPayload['approved_by'] ?? '')) ?></span>
              <span class="status-chip"><?= e($gs('approved_at')) ?>: <?= e((string)($approvalPayload['approved_at'] ?? '')) ?></span>
            </div>

            <?php if ($approvalValidationErrors !== []): ?>
              <div class="ui-block">
                <div class="form-label"><?= e($gs('validation_errors')) ?></div>
                <ul>
                  <?php foreach ($approvalValidationErrors as $approvalErrorKey): ?>
                    <?php
                      $approvalErrorText = t((string)$approvalErrorKey);
                      $approvalFallbackKey = str_replace('ops.gui_studio.', '', (string)$approvalErrorKey);
                      if ($approvalErrorText === (string)$approvalErrorKey) {
                          $approvalErrorText = $gs($approvalFallbackKey);
                      }
                    ?>
                    <li><?= e($approvalErrorText) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <div class="ui-block">
              <div class="form-label"><?= e($gs('payload_preview')) ?></div>
              <pre><code><?= e($approvalPayloadJson) ?></code></pre>
            </div>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($compileArtifacts !== []): ?>
      <?php
        $snapshotValid = !empty($approvalValidation['valid']);
        $snapshotStatusClass = $snapshotValid ? 'success' : 'danger';
        $snapshotStatusSymbol = $snapshotValid ? '✅' : '❌';
        $snapshotStatusText = $snapshotValid ? $gs('snapshot_ready') : $gs('snapshot_disabled');
        $snapshotArtifacts = is_array($snapshot['artifacts'] ?? null) ? $snapshot['artifacts'] : [];
        $snapshotRiskSummary = is_array($snapshot['risk_summary'] ?? null) ? $snapshot['risk_summary'] : [];
        $snapshotIntegrity = is_array($snapshot['integrity'] ?? null) ? $snapshot['integrity'] : [];
      ?>
      <section class="card" id="gs-snapshot-preview">
        <h2><?= e($gs('snapshot_preview')) ?></h2>

        <div class="snapshot-summary table-summary-badges">
          <span class="status-chip <?= e($snapshotStatusClass) ?>"><?= e($snapshotStatusSymbol . ' ' . $snapshotStatusText) ?></span>
          <?php if ($snapshot !== []): ?>
            <span class="status-chip"><?= e($gs('compile_id')) ?>: <code><?= e((string)($snapshot['compile_id'] ?? '')) ?></code></span>
            <span class="status-chip"><?= e($gs('approval_id')) ?>: <code><?= e((string)($snapshot['approval_id'] ?? '')) ?></code></span>
            <span class="status-chip"><?= e($gs('artifact_count')) ?>: <?= e((string)($snapshotIntegrity['artifact_count'] ?? 0)) ?></span>
          <?php endif; ?>
        </div>

        <?php if (!$snapshotValid): ?>
          <div class="note warning"><?= e('❌ ' . $gs('snapshot_unavailable')) ?></div>
        <?php endif; ?>

        <?php if ($snapshot !== []): ?>
          <div class="snapshot-integrity table-summary-badges">
            <span class="status-chip success">🔒 <?= e($gs('integrity_verified')) ?></span>
            <span class="status-chip"><?= e($gs('snapshot_id')) ?>: <code><?= e((string)($snapshot['snapshot_id'] ?? '')) ?></code></span>
            <span class="status-chip"><?= e($gs('snapshot_hash')) ?>: <code><?= e((string)($snapshot['snapshot_hash'] ?? '')) ?></code></span>
            <span class="status-chip"><?= e($gs('hash_verified')) ?>: <?= !empty($snapshotIntegrity['hash_verified']) ? e($gs('bool.true')) : e($gs('bool.false')) ?></span>
          </div>

          <h3><?= e($gs('snapshot_integrity')) ?></h3>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th><?= e($gs('approved')) ?></th>
                  <th><?= e($gs('high_risk_items')) ?></th>
                  <th><?= e($gs('blocked_items')) ?></th>
                  <th><?= e($gs('snapshot_hash')) ?></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><?= !empty($snapshotSummary['approved']) ? e($gs('bool.true')) : e($gs('bool.false')) ?></td>
                  <td><?= e((string)($snapshotRiskSummary['high'] ?? 0)) ?></td>
                  <td><?= e((string)($snapshotRiskSummary['blocked'] ?? 0)) ?></td>
                  <td><code><?= e((string)($snapshotSummary['hash'] ?? $snapshot['snapshot_hash'] ?? '')) ?></code></td>
                </tr>
              </tbody>
            </table>
          </div>

          <?php if ($snapshotArtifacts !== []): ?>
            <h3><?= e($gs('snapshot_artifacts')) ?></h3>
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th><?= e($gs('artifact_id')) ?></th>
                    <th><?= e($gs('artifact_type')) ?></th>
                    <th><?= e($gs('target_path')) ?></th>
                    <th><?= e($gs('risk_level')) ?></th>
                    <th><?= e($gs('content_hash')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($snapshotArtifacts as $snapshotArtifact): ?>
                    <?php if (!is_array($snapshotArtifact)) { continue; } ?>
                    <tr>
                      <td><code><?= e((string)($snapshotArtifact['artifact_id'] ?? '')) ?></code></td>
                      <td><?= e((string)($snapshotArtifact['artifact_type'] ?? '')) ?></td>
                      <td><code><?= e((string)($snapshotArtifact['target_path'] ?? '')) ?></code></td>
                      <td><?= e((string)($snapshotArtifact['risk_level'] ?? '')) ?></td>
                      <td><code><?= e((string)($snapshotArtifact['content_hash'] ?? '')) ?></code></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($compileArtifacts !== []): ?>
      <?php
        $executionSummary = is_array($execution['summary'] ?? null) ? $execution['summary'] : [];
        $executionResults = is_array($execution['results'] ?? null) ? $execution['results'] : [];
        $executionStatus = strtoupper((string)($execution['status'] ?? ''));
        $executionCanExecute = !empty($execution['can_execute']);
        if ($executionStatus === '') {
            $executionCanExecute = $snapshot !== []
                && !empty($snapshotSummary['approved'])
                && (int)($snapshotSummary['blocked'] ?? 0) === 0;
            $executionStatus = $executionCanExecute ? 'READY' : 'BLOCKED';
        }
        $executionStatusClass = $executionStatus === 'BLOCKED' ? 'danger' : 'success';
        $executionStatusSymbol = '🟢';
        $executionStatusKey = 'execution_ready';
        if ($executionStatus === 'BLOCKED') {
            $executionStatusSymbol = '🔴';
            $executionStatusKey = 'execution_blocked';
        } elseif ($executionStatus === 'SIMULATED') {
            $executionStatusSymbol = '🔵';
            $executionStatusKey = 'execution_simulated';
        }
      ?>
      <section class="card" id="gs-execution-preview">
        <h2><?= e($gs('execution_preview')) ?></h2>

        <div class="execution-summary table-summary-badges">
          <span class="status-chip <?= e($executionStatusClass) ?>"><?= e($executionStatusSymbol . ' ' . $gs($executionStatusKey)) ?></span>
          <?php if ($execution !== []): ?>
            <span class="status-chip"><?= e($gs('execution_id')) ?>: <code><?= e((string)($execution['execution_id'] ?? '')) ?></code></span>
            <span class="status-chip"><?= e($gs('snapshot_id')) ?>: <code><?= e((string)($execution['snapshot_id'] ?? '')) ?></code></span>
          <?php endif; ?>
          <span class="status-chip"><?= e($gs('can_execute')) ?>: <?= $executionCanExecute ? e($gs('bool.true')) : e($gs('bool.false')) ?></span>
          <span class="status-chip"><?= e($gs('artifact_count')) ?>: <?= e((string)($executionSummary['total'] ?? count((array)($snapshot['artifacts'] ?? [])))) ?></span>
          <span class="status-chip success"><?= e($gs('ok')) ?>: <?= e((string)($executionSummary['ok'] ?? 0)) ?></span>
          <span class="status-chip warning"><?= e($gs('skipped')) ?>: <?= e((string)($executionSummary['skipped'] ?? 0)) ?></span>
          <span class="status-chip danger"><?= e($gs('blocked')) ?>: <?= e((string)($executionSummary['blocked'] ?? 0)) ?></span>
        </div>

        <?php if ($snapshot === [] || $executionStatus === 'BLOCKED'): ?>
          <div class="note warning"><?= e($gs('execution_unavailable')) ?></div>
        <?php endif; ?>

        <?php if ($executionResults !== []): ?>
          <h3><?= e($gs('execution_results')) ?></h3>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th><?= e($gs('artifact_id')) ?></th>
                  <th><?= e($gs('target_path')) ?></th>
                  <th><?= e($gs('artifact_type')) ?></th>
                  <th><?= e($gs('action')) ?></th>
                  <th><?= e($gs('status')) ?></th>
                  <th><?= e($gs('reason_label')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($executionResults as $executionResult): ?>
                  <?php if (!is_array($executionResult)) { continue; } ?>
                  <?php $resultStatus = strtolower((string)($executionResult['status'] ?? 'blocked')); ?>
                  <tr class="execution-row">
                    <td><code><?= e((string)($executionResult['artifact'] ?? '')) ?></code></td>
                    <td><code><?= e((string)($executionResult['target_path'] ?? '')) ?></code></td>
                    <td><?= e((string)($executionResult['artifact_type'] ?? '')) ?></td>
                    <td><?= e((string)($executionResult['action'] ?? '')) ?></td>
                    <td><span class="status-chip <?= e($resultStatus) ?>"><?= e(strtoupper((string)($executionResult['status'] ?? ''))) ?></span></td>
                    <td><?= e((string)($executionResult['reason'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($diffReadiness !== []): ?>
      <h3><?= e($gs('diff_readiness')) ?></h3>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th><?= e($gs('artifact_id')) ?></th>
              <th><?= e($gs('target_path')) ?></th>
              <th><?= e($gs('target_exists')) ?></th>
              <th><?= e($gs('before_hash')) ?></th>
              <th><?= e($gs('after_hash')) ?></th>
              <th><?= e($gs('drift_status')) ?></th>
              <th><?= e($gs('diff_status')) ?></th>
              <th><?= e($gs('diff_summary')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($diffReadiness as $row): ?>
              <?php if (!is_array($row)) { continue; } ?>
              <tr>
                <td><code><?= e((string)($row['artifact_id'] ?? '')) ?></code></td>
                <td><code><?= e((string)($row['target_path'] ?? '')) ?></code></td>
                <td><?= !empty($row['target_exists']) ? e($gs('bool.true')) : e($gs('bool.false')) ?></td>
                <td><code><?= e((string)($row['before_hash'] ?? '')) ?></code></td>
                <td><code><?= e((string)($row['after_hash'] ?? '')) ?></code></td>
                <td><?= e((string)($row['drift_status'] ?? '')) ?></td>
                <td><?= e((string)($row['diff_status'] ?? '')) ?></td>
                <td><?= e((string)($row['human_diff_summary'] ?? $row['diff_summary'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/apply_center.php'; ?>
<?php require __DIR__ . '/partials/rollback_panel.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/partials/governance_diagnostics_panel.php'; ?>
<?php require __DIR__ . '/partials/legacy_wrapper_inert_script.php'; ?>

<script>
<?php require __DIR__ . '/../assets/js/gui_studio/06_legacy_visibility.js'; ?>
</script>

<?php require __DIR__ . '/partials/legacy_content_visibility_script.php'; ?>

</div><!-- .legacy-studio -->
<?php } ?>
