<?php
$tt = static function (string $key): string {
  return t($key);
};
$model = isset($labelDesignerPreviewModel) && is_array($labelDesignerPreviewModel)
    ? $labelDesignerPreviewModel
    : [];

// Reference implementation data (Manufacturing QR Product Label)
$sourceSurfaces = isset($model['source_surfaces']) && is_array($model['source_surfaces'])
    ? array_values(array_filter($model['source_surfaces'], 'is_string'))
    : [];
$printRoute = trim((string)($model['print_route'] ?? '/qr/product/label'));
$scanRouteTemplate = trim((string)($model['scan_route_template'] ?? '/qr/product/scan?product_id={id}&part_number={parts_number}'));
$defaults = isset($model['defaults']) && is_array($model['defaults'])
    ? $model['defaults']
    : [];
$fieldOrder = isset($model['field_order']) && is_array($model['field_order'])
    ? array_values(array_filter($model['field_order'], 'is_string'))
    : [];
$refImplName = trim((string)($model['reference_implementation_name'] ?? 'Manufacturing QR Product Label'));

// Architecture data
$ownerResourcePaths = isset($model['architecture_owner_resource_paths']) && is_array($model['architecture_owner_resource_paths'])
    ? array_values(array_filter($model['architecture_owner_resource_paths'], 'is_string'))
    : [];
$futureExamples = isset($model['future_label_examples']) && is_array($model['future_label_examples'])
    ? array_values(array_filter($model['future_label_examples'], 'is_string'))
    : [];
$resourceContracts = isset($model['resource_contracts']) && is_array($model['resource_contracts'])
    ? array_values(array_filter($model['resource_contracts'], 'is_array'))
    : [];
$resourceExamples = isset($model['resource_examples']) && is_array($model['resource_examples'])
    ? array_values(array_filter($model['resource_examples'], 'is_string'))
    : [];
$resourceDiscovery = isset($model['label_resource_discovery']) && is_array($model['label_resource_discovery'])
    ? $model['label_resource_discovery']
    : ['owners' => [], 'owner_count' => 0, 'resource_count' => 0];
$discoveredOwners = isset($resourceDiscovery['owners']) && is_array($resourceDiscovery['owners'])
    ? array_values(array_filter($resourceDiscovery['owners'], 'is_array'))
    : [];
$existingContexts = isset($model['label_existing_contexts']) && is_array($model['label_existing_contexts'])
    ? array_values(array_filter($model['label_existing_contexts'], 'is_array'))
    : [];
$existingTemplates = isset($model['label_existing_templates']) && is_array($model['label_existing_templates'])
    ? array_values(array_filter($model['label_existing_templates'], 'is_array'))
    : [];
$dataSourceDiscovery = isset($model['label_data_source_discovery']) && is_array($model['label_data_source_discovery'])
    ? $model['label_data_source_discovery']
    : ['owners' => [], 'selected_owner_key' => '', 'candidate_sources' => [], 'source_count' => 0, 'column_count' => 0, 'error' => ''];
$dataSourceOwners = isset($dataSourceDiscovery['owners']) && is_array($dataSourceDiscovery['owners'])
    ? array_values(array_filter($dataSourceDiscovery['owners'], 'is_array'))
    : [];
$candidateSources = isset($dataSourceDiscovery['candidate_sources']) && is_array($dataSourceDiscovery['candidate_sources'])
    ? array_values(array_filter($dataSourceDiscovery['candidate_sources'], 'is_array'))
    : [];
$selectedDataSourceOwnerKey = (string)($dataSourceDiscovery['selected_owner_key'] ?? '');
$dataSourceError = trim((string)($dataSourceDiscovery['error'] ?? ''));
$contextCreatePreview = isset($model['label_context_create_preview']) && is_array($model['label_context_create_preview'])
  ? $model['label_context_create_preview']
  : [];
$templateCreatePreview = isset($model['label_template_create_preview']) && is_array($model['label_template_create_preview'])
  ? $model['label_template_create_preview']
  : [];
$labelDesignerFlash = isset($model['label_designer_flash']) && is_array($model['label_designer_flash'])
  ? $model['label_designer_flash']
  : [];
$labelEditDiagnostics = isset($model['label_edit_diagnostics']) && is_array($model['label_edit_diagnostics'])
  ? $model['label_edit_diagnostics']
  : [];
$csrf = trim((string)($model['csrf'] ?? ''));


$labelDesignerLang = function_exists('current_lang') ? current_lang() : 'en';
$labelDesignerLang = in_array($labelDesignerLang, ['en', 'ja', 'ne'], true) ? $labelDesignerLang : 'en';
$labelDesignerFallback = require __DIR__ . '/../lang/en.php';
$labelDesignerLangPath = __DIR__ . '/../lang/' . $labelDesignerLang . '.php';
$labelDesignerStrings = is_file($labelDesignerLangPath) ? require $labelDesignerLangPath : [];
$labelDesignerStrings = is_array($labelDesignerStrings) ? array_replace($labelDesignerFallback, $labelDesignerStrings) : $labelDesignerFallback;
$ld = static function (string $key) use ($labelDesignerStrings, $labelDesignerFallback): string {
    return (string)($labelDesignerStrings[$key] ?? ($labelDesignerFallback[$key] ?? $key));
};
require __DIR__ . '/partials/flash.php';
$resourceReadiness = isset($model['label_resource_readiness']) && is_array($model['label_resource_readiness'])
    ? $model['label_resource_readiness']
    : ['owners' => [], 'owner_count' => 0, 'complete_count' => 0, 'partial_count' => 0, 'absent_count' => 0];
$readinessOwners = isset($resourceReadiness['owners']) && is_array($resourceReadiness['owners'])
    ? array_values(array_filter($resourceReadiness['owners'], 'is_array'))
    : [];

$metadataAnalysis = isset($model['label_metadata_analysis']) && is_array($model['label_metadata_analysis'])
    ? $model['label_metadata_analysis']
    : [];
$migrationPreview = isset($model['label_migration_preview']) && is_array($model['label_migration_preview'])
    ? $model['label_migration_preview']
    : [];
$migrationApply = isset($model['label_migration_apply']) && is_array($model['label_migration_apply'])
    ? $model['label_migration_apply']
    : [];
$runtimeDryRun = isset($model['label_runtime_dry_run']) && is_array($model['label_runtime_dry_run'])
    ? $model['label_runtime_dry_run']
    : [];
$runtimeDryRunMatrix = isset($model['label_runtime_dry_run_matrix']) && is_array($model['label_runtime_dry_run_matrix'])
  ? $model['label_runtime_dry_run_matrix']
  : [];
$runtimeDryRunRequest = isset($runtimeDryRun['request']) && is_array($runtimeDryRun['request'])
    ? $runtimeDryRun['request']
    : [];
$runtimeDryRunDiagnostics = isset($runtimeDryRun['diagnostics']) && is_array($runtimeDryRun['diagnostics'])
    ? array_values(array_filter($runtimeDryRun['diagnostics'], 'is_array'))
    : [];
$runtimeDryRunSummary = isset($runtimeDryRun['summary']) && is_array($runtimeDryRun['summary'])
    ? $runtimeDryRun['summary']
    : [];
$runtimeDryRunMatrixRows = isset($runtimeDryRunMatrix['rows']) && is_array($runtimeDryRunMatrix['rows'])
    ? array_values(array_filter($runtimeDryRunMatrix['rows'], 'is_array'))
    : [];
$runtimeDryRunMatrixSummary = isset($runtimeDryRunMatrix['summary']) && is_array($runtimeDryRunMatrix['summary'])
    ? $runtimeDryRunMatrix['summary']
    : [];

$previewResult = isset($model['label_preview_result']) && is_array($model['label_preview_result'])
    ? $model['label_preview_result']
    : [];
$previewHtml = trim((string)($previewResult['html'] ?? ''));
$previewDiagnostics = isset($previewResult['diagnostics']) && is_array($previewResult['diagnostics'])
    ? array_values(array_filter($previewResult['diagnostics'], 'is_array'))
    : [];
$previewRenderAllowed = !empty($previewResult['render_allowed']);
$previewSampleData = isset($previewResult['sample_data']) && is_array($previewResult['sample_data'])
    ? $previewResult['sample_data']
    : [];
$previewResolvedFields = isset($previewResult['resolved_fields']) && is_array($previewResult['resolved_fields'])
    ? array_values(array_filter($previewResult['resolved_fields'], 'is_array'))
    : [];
$previewContextOptions = isset($model['label_preview_context_options']) && is_array($model['label_preview_context_options'])
    ? array_values(array_filter($model['label_preview_context_options'], 'is_array'))
    : [];
$previewTemplateOptions = isset($model['label_preview_template_options']) && is_array($model['label_preview_template_options'])
    ? array_values(array_filter($model['label_preview_template_options'], 'is_array'))
    : [];
$previewSelectedTemplateId = trim((string)($model['label_preview_selected_template_id'] ?? ''));
$previewSelectedContextId = trim((string)($model['label_preview_selected_context_id'] ?? ''));
$previewRulesEnabled = trim((string)($model['label_preview_rules_enabled'] ?? ''));
$previewActiveRules = isset($model['label_preview_active_rules']) && is_array($model['label_preview_active_rules'])
    ? array_values(array_filter($model['label_preview_active_rules'], 'is_string'))
    : [];
$previewRuleEvaluation = isset($model['label_preview_rule_evaluation']) && is_array($model['label_preview_rule_evaluation'])
    ? array_values(array_filter($model['label_preview_rule_evaluation'], 'is_array'))
    : [];
$previewRuleCount = (int)($model['label_preview_rule_count'] ?? 0);
$previewRuleMatchedCount = (int)($model['label_preview_rule_matched_count'] ?? 0);

$viewResource = trim((string)($model['label_view_resource'] ?? ''));
$selectedContext = isset($model['label_selected_context']) && is_array($model['label_selected_context'])
    ? $model['label_selected_context']
    : null;
$selectedTemplate = isset($model['label_selected_template']) && is_array($model['label_selected_template'])
    ? $model['label_selected_template']
    : null;
$inspectorError = '';

$ruleSandboxResult = isset($model['label_rule_sandbox_result']) && is_array($model['label_rule_sandbox_result'])
    ? $model['label_rule_sandbox_result']
    : [];
$ruleSandboxFields = isset($model['label_rule_sandbox_fields']) && is_array($model['label_rule_sandbox_fields'])
    ? array_values(array_filter($model['label_rule_sandbox_fields'], 'is_array'))
    : [];

$ruleCreatePreview = isset($model['label_rule_create_preview']) && is_array($model['label_rule_create_preview'])
  ? $model['label_rule_create_preview']
  : [];
$ruleCreateSelected = isset($ruleCreatePreview['selected']) && is_array($ruleCreatePreview['selected'])
  ? $ruleCreatePreview['selected']
  : [];
$ruleCreateContexts = isset($ruleCreatePreview['contexts']) && is_array($ruleCreatePreview['contexts'])
  ? array_values(array_filter($ruleCreatePreview['contexts'], 'is_array'))
  : [];
$ruleCreateTemplates = isset($ruleCreatePreview['templates']) && is_array($ruleCreatePreview['templates'])
  ? array_values(array_filter($ruleCreatePreview['templates'], 'is_array'))
  : [];
$ruleCreateFieldKeys = isset($ruleCreatePreview['context_field_keys']) && is_array($ruleCreatePreview['context_field_keys'])
  ? array_values(array_filter(array_map(static fn ($v): string => trim((string)$v), $ruleCreatePreview['context_field_keys']), static fn (string $v): bool => $v !== ''))
  : [];
$ruleCreateTargetOptions = isset($ruleCreatePreview['effect_target_options']) && is_array($ruleCreatePreview['effect_target_options'])
  ? $ruleCreatePreview['effect_target_options']
  : ['fields' => [], 'blocks' => [], 'style_tokens' => []];
$ruleCreateDiagnostics = isset($ruleCreatePreview['diagnostics']) && is_array($ruleCreatePreview['diagnostics'])
  ? array_values(array_filter($ruleCreatePreview['diagnostics'], 'is_array'))
  : [];
$ruleCreateErrors = isset($ruleCreatePreview['errors']) && is_array($ruleCreatePreview['errors'])
  ? array_values(array_filter(array_map(static fn ($v): string => trim((string)$v), $ruleCreatePreview['errors']), static fn (string $v): bool => $v !== ''))
  : [];
$ruleCreateTargetPath = trim((string)($ruleCreatePreview['target_path_rel'] ?? ''));
$ruleCreateJson = trim((string)($ruleCreatePreview['rule_json'] ?? '{}'));
$ruleCreateSelectedContextId = trim((string)($ruleCreateSelected['rule_context_id'] ?? ''));
$ruleCreateSelectedTemplateId = trim((string)($ruleCreateSelected['rule_template_id'] ?? ''));
$ruleCreateSelectedRuleKey = trim((string)($ruleCreateSelected['rule_key'] ?? ''));
$ruleCreateSelectedConditionField = trim((string)($ruleCreateSelected['rc_condition_field'] ?? ''));
$ruleCreateSelectedOperator = trim((string)($ruleCreateSelected['rc_operator'] ?? 'equals'));
$ruleCreateSelectedConditionValue = trim((string)($ruleCreateSelected['rc_condition_value'] ?? ''));
$ruleCreateSelectedEffectType = trim((string)($ruleCreateSelected['rc_effect_type'] ?? 'show_warning'));
$ruleCreateSelectedEffectTarget = trim((string)($ruleCreateSelected['rc_effect_target'] ?? ''));
$ruleCreateSelectedEffectValue = trim((string)($ruleCreateSelected['rc_effect_value'] ?? ''));
$ruleCreateSelectedEnabled = trim((string)($ruleCreateSelected['rule_enabled'] ?? 'yes'));
$ruleCreateValidationStarted = !empty($ruleCreatePreview['validation_started']);
$ruleCreateEmptyState = !empty($ruleCreatePreview['empty_state']);

$existingRules = isset($model['label_existing_rules']) && is_array($model['label_existing_rules'])
    ? array_values(array_filter($model['label_existing_rules'], 'is_array'))
    : [];
$viewRule = trim((string)($model['label_view_rule'] ?? ''));
$selectedRule = isset($model['label_selected_rule']) && is_array($model['label_selected_rule'])
    ? $model['label_selected_rule']
    : null;
$ruleSummary = trim((string)($model['label_rule_summary'] ?? ''));

// Phase 15: Workspace progress model
$overviewContextKey = isset($overviewContextKey) ? (string)$overviewContextKey : '';
$overviewTemplateKey = isset($overviewTemplateKey) ? (string)$overviewTemplateKey : '';
$overviewRuleCount = isset($overviewRuleCount) ? (int)$overviewRuleCount : 0;
$selectedReadinessSummary = isset($selectedReadinessSummary) && is_array($selectedReadinessSummary)
    ? $selectedReadinessSummary
    : [];
$wsProgress = [];
$wsProgress['has_context'] = ($overviewContextKey !== '');
$wsProgress['has_template'] = ($overviewTemplateKey !== '');
$wsProgress['has_rules'] = ($overviewRuleCount > 0);
$wsProgress['has_preview'] = ($previewResult !== []);
$wsProgress['is_lifecycle'] = !empty($selectedReadinessSummary['is_label_lifecycle_owner']);
$wsProgress['all_complete'] = $wsProgress['has_context'] && $wsProgress['has_template'] && $wsProgress['has_rules'] && $wsProgress['has_preview'];
$wsProgress['none_started'] = !$wsProgress['has_context'] && !$wsProgress['has_template'] && !$wsProgress['has_rules'] && !$wsProgress['has_preview'];
$includeKeys = [
    'include_date',
    'include_serial_number',
    'include_machine_no',
    'include_case_number',
    'include_qty_per_case',
    'include_case_spec',
    'include_cases_per_pallet',
];

$contextPreviewPurposes = [
    'Product Label',
    'Part Label',
    'Pallet Label',
    'Case Label',
    'QC Label',
    'Bin/Location Label',
    'Dispatch Label',
    'Asset Label',
    'Custom Purpose',
];
$selectedPreviewOwner = null;
foreach ($dataSourceOwners as $owner) {
    $ownerKey = (string)($owner['owner_key'] ?? '');
    if ($ownerKey !== '' && hash_equals($selectedDataSourceOwnerKey, $ownerKey)) {
        $selectedPreviewOwner = $owner;
        break;
    }
}
if (!is_array($selectedPreviewOwner) && !empty($dataSourceOwners)) {
    $selectedPreviewOwner = $dataSourceOwners[0];
}
$previewOwnerKey = is_array($selectedPreviewOwner) ? (string)($selectedPreviewOwner['owner_key'] ?? '') : '';
$previewOwnerType = is_array($selectedPreviewOwner) ? (string)($selectedPreviewOwner['owner_type'] ?? '') : '';
$previewSource = $candidateSources[0] ?? [];
$previewSourceName = is_array($previewSource) ? (string)($previewSource['source_name'] ?? '') : '';
$previewColumns = isset($previewSource['columns']) && is_array($previewSource['columns'])
    ? array_slice(array_values(array_filter($previewSource['columns'], 'is_array')), 0, 6)
    : [];
$previewFields = [];
foreach ($previewColumns as $column) {
    $columnName = (string)($column['column_name'] ?? '');
    if ($columnName === '') {
        continue;
    }

    $previewFields[] = [
        'field_key' => $columnName,
        'label' => ucwords(str_replace('_', ' ', $columnName)),
        'source_column' => $columnName,
        'data_type' => (string)($column['data_type'] ?? ''),
        'candidate_only' => true,
    ];
}
$contextKeyBase = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '.', $previewOwnerKey), '.'));
$selectedCreate = isset($contextCreatePreview['selected']) && is_array($contextCreatePreview['selected'])
  ? $contextCreatePreview['selected']
  : [];
$selectedCreateOwner = trim((string)($selectedCreate['owner'] ?? $previewOwnerKey));
$selectedCreatePurpose = trim((string)($selectedCreate['purpose'] ?? 'Product Label'));
$selectedCreateSource = trim((string)($selectedCreate['source'] ?? $previewSourceName));
$selectedCreateFields = isset($selectedCreate['fields']) && is_array($selectedCreate['fields'])
  ? array_values(array_filter(array_map(static fn ($v): string => trim((string)$v), $selectedCreate['fields']), static fn (string $v): bool => $v !== ''))
  : [];
$selectedCreateContextKey = trim((string)($selectedCreate['context_key'] ?? (($contextKeyBase !== '' ? $contextKeyBase : 'owner') . '.product_label')));
$contextPreviewErrors = isset($contextCreatePreview['errors']) && is_array($contextCreatePreview['errors'])
  ? $contextCreatePreview['errors']
  : [];
$contextPreviewValidation = isset($contextCreatePreview['validation']) && is_array($contextCreatePreview['validation'])
  ? $contextCreatePreview['validation']
  : [];
$contextPreviewTargetPath = trim((string)($contextCreatePreview['target_path_rel'] ?? ''));
$contextPreviewJsonText = trim((string)($contextCreatePreview['context_json'] ?? '{}'));

$templatePreviewErrors = isset($templateCreatePreview['errors']) && is_array($templateCreatePreview['errors'])
  ? $templateCreatePreview['errors']
  : [];
$templatePreviewValidation = isset($templateCreatePreview['validation']) && is_array($templateCreatePreview['validation'])
  ? $templateCreatePreview['validation']
  : [];
$templatePreviewJsonText = trim((string)($templateCreatePreview['template_json'] ?? '{}'));
$templatePreviewContexts = isset($templateCreatePreview['contexts']) && is_array($templateCreatePreview['contexts'])
  ? array_values(array_filter($templateCreatePreview['contexts'], 'is_array'))
  : [];
$templatePreviewLabelSizes = isset($templateCreatePreview['label_sizes']) && is_array($templateCreatePreview['label_sizes'])
  ? array_values(array_filter($templateCreatePreview['label_sizes'], 'is_string'))
  : [];
$templatePreviewSelected = isset($templateCreatePreview['selected']) && is_array($templateCreatePreview['selected'])
  ? $templateCreatePreview['selected']
  : [];
$templatePreviewContextId = trim((string)($templatePreviewSelected['template_context'] ?? ''));
$templatePreviewSize = trim((string)($templatePreviewSelected['template_size'] ?? '100x50_mm'));
$templatePreviewFieldKeys = isset($templatePreviewSelected['template_fields']) && is_array($templatePreviewSelected['template_fields'])
  ? array_values(array_filter(array_map(static fn ($v): string => trim((string)$v), $templatePreviewSelected['template_fields']), static fn (string $v): bool => $v !== ''))
  : [];
$templatePreviewContext = isset($templateCreatePreview['selected_context']) && is_array($templateCreatePreview['selected_context'])
  ? $templateCreatePreview['selected_context']
  : [];
$templatePreviewContextFields = isset($templatePreviewContext['allowed_fields']) && is_array($templatePreviewContext['allowed_fields'])
  ? array_values(array_filter($templatePreviewContext['allowed_fields'], 'is_array'))
  : [];
$templatePreviewContent = isset($templateCreatePreview['template']) && is_array($templateCreatePreview['template'])
  ? $templateCreatePreview['template']
  : [];
$templatePreviewTemplateKey = trim((string)($templatePreviewContent['template_key'] ?? ''));
$templatePreviewOwnerKey = trim((string)($templatePreviewContext['owner_key'] ?? ''));
$templatePreviewTargetPath = '';
if ($templatePreviewOwnerKey !== '' && $templatePreviewTemplateKey !== '') {
    $ownerParts = explode('/', $templatePreviewOwnerKey);
    $ownerRoot = 'apps/' . $ownerParts[0];
    if (count($ownerParts) > 1) {
        $ownerRoot .= '/modules/' . implode('/', array_slice($ownerParts, 1));
    }
    $templatePreviewTargetPath = $ownerRoot . '/Resources/labels/templates/' . $templatePreviewTemplateKey . '.json';
}

$selectedCandidateSource = [];
foreach ($candidateSources as $source) {
  if (hash_equals((string)($source['source_name'] ?? ''), $selectedCreateSource)) {
    $selectedCandidateSource = $source;
    break;
  }
}
if ($selectedCandidateSource === [] && !empty($candidateSources)) {
  $selectedCandidateSource = $candidateSources[0];
  $selectedCreateSource = (string)($selectedCandidateSource['source_name'] ?? '');
}
$selectedCandidateColumns = isset($selectedCandidateSource['columns']) && is_array($selectedCandidateSource['columns'])
  ? array_values(array_filter($selectedCandidateSource['columns'], 'is_array'))
  : [];


// Phase 8: User-oriented workspace navigation with legacy query aliases resolved by the page model.
$validWorkspaces = ['overview', 'build', 'rules', 'preview', 'governance'];
$activeWorkspace = trim((string)($model['label_active_workspace'] ?? 'overview'));
$activeWorkspace = in_array($activeWorkspace, $validWorkspaces, true) ? $activeWorkspace : 'overview';
require __DIR__ . '/partials/shared-ui.php';
$selectedOwnerSummary = null;
foreach ($discoveredOwners as $ownerSummary) {
    if (strcasecmp((string)($ownerSummary['owner_key'] ?? ''), $selectedDataSourceOwnerKey) === 0) {
        $selectedOwnerSummary = $ownerSummary;
        break;
    }
}
$selectedReadinessSummary = null;
foreach ($readinessOwners as $readinessSummary) {
    if (strcasecmp((string)($readinessSummary['owner_key'] ?? ''), $selectedDataSourceOwnerKey) === 0) {
        $selectedReadinessSummary = $readinessSummary;
        break;
    }
}
$selectedOwnerResources = is_array($selectedOwnerSummary['resources'] ?? null)
    ? $selectedOwnerSummary['resources']
    : [];
$overviewResourceCounts = [
    'contexts' => (int)($selectedOwnerResources['contexts']['count'] ?? 0),
    'templates' => (int)($selectedOwnerResources['templates']['count'] ?? 0),
    'rules' => (int)($selectedOwnerResources['rules']['count'] ?? 0),
];
$overviewDiagnostics = isset($model['label_resource_diagnostics']) && is_array($model['label_resource_diagnostics'])
    ? $model['label_resource_diagnostics']
    : [];
$selectedOwnerDiagnostics = null;
foreach (($overviewDiagnostics['by_owner'] ?? []) as $ownerDiagnostics) {
    if (is_array($ownerDiagnostics)
        && strcasecmp((string)($ownerDiagnostics['owner_key'] ?? ''), $selectedDataSourceOwnerKey) === 0) {
        $selectedOwnerDiagnostics = $ownerDiagnostics;
        break;
    }
}
$overviewDiagnosticCounts = ['pass' => 0, 'info' => 0, 'warning' => 0, 'error' => 0];
$overviewResourceDetails = [];
foreach (($selectedOwnerDiagnostics['resources'] ?? []) as $resourceDiagnostic) {
    if (!is_array($resourceDiagnostic)) {
        continue;
    }
    $overviewResourceDetails[] = [
        'type' => (string)($resourceDiagnostic['resource_type'] ?? ''),
        'key' => (string)($resourceDiagnostic['resource_key'] ?? ''),
        'path' => (string)($resourceDiagnostic['path'] ?? ''),
    ];
    foreach (($resourceDiagnostic['diagnostics'] ?? []) as $diagnostic) {
        $severity = (string)($diagnostic['severity'] ?? '');
        if (array_key_exists($severity, $overviewDiagnosticCounts)) {
            $overviewDiagnosticCounts[$severity]++;
        }
    }
}
$overviewDryRunSummary = isset($runtimeDryRun['summary']) && is_array($runtimeDryRun['summary'])
    ? $runtimeDryRun['summary']
    : [];
$selectedMetadataSummary = null;
foreach (($metadataAnalysis['owners'] ?? []) as $ownerMetadata) {
    if (is_array($ownerMetadata)
        && strcasecmp((string)($ownerMetadata['owner_key'] ?? ''), $selectedDataSourceOwnerKey) === 0) {
        $selectedMetadataSummary = $ownerMetadata;
        break;
    }
}
$selectedOwnerDisplayName = trim((string)($selectedReadinessSummary['display_name'] ?? ''));
if ($selectedOwnerDisplayName === '') {
    $selectedOwnerDisplayName = $selectedDataSourceOwnerKey !== ''
        ? str_replace('/', ' / ', $selectedDataSourceOwnerKey)
        : 'No owner selected';
}
$selectedOwnerKey = (string)($selectedReadinessSummary['owner_key'] ?? $selectedDataSourceOwnerKey);
$selectedOwnerType = (string)($selectedReadinessSummary['owner_type'] ?? $selectedOwnerSummary['owner_type'] ?? '');
$selectedOwnerRoot = (string)($selectedReadinessSummary['root_path'] ?? $selectedOwnerSummary['root_path'] ?? '');
$selectedOwnerHasResources = array_sum($overviewResourceCounts) > 0;
$selectedOwnerLifecycleStatus = empty($selectedReadinessSummary['is_label_lifecycle_owner'])
    ? $ld('overview_lifecycle_not_lifecycle')
    : ($selectedOwnerHasResources ? $ld('overview_lifecycle_has_resources') : $ld('overview_lifecycle_ready'));
$overviewContextKey = '';
$overviewTemplateKey = '';
$overviewRuleCount = 0;
foreach ($overviewResourceDetails as $resourceDetail) {
    if ($resourceDetail['type'] === 'context' && $overviewContextKey === '') {
        $overviewContextKey = $resourceDetail['key'];
    } elseif ($resourceDetail['type'] === 'template' && $overviewTemplateKey === '') {
        $overviewTemplateKey = $resourceDetail['key'];
    } elseif ($resourceDetail['type'] === 'rule') {
        $overviewRuleCount++;
    }
}
$overviewDiagnosticsClean = $selectedOwnerHasResources
    && $overviewDiagnosticCounts['warning'] === 0
    && $overviewDiagnosticCounts['error'] === 0;
$overviewMetadataFirst = $selectedOwnerHasResources && !empty($selectedMetadataSummary['metadata_complete']);
$overviewDryRunValid = strcasecmp($selectedOwnerKey, 'Manufacturing/Products') === 0
    && !empty($overviewDryRunSummary['request_valid']);
$overviewWorkspaceReady = $overviewContextKey !== ''
    && $overviewTemplateKey !== ''
    && $overviewDiagnosticsClean
    && $overviewMetadataFirst
    && $overviewDryRunValid;
$selectedOwnerIsLifecycle = !empty($selectedReadinessSummary['is_label_lifecycle_owner']);
$eligibleLifecycleOwners = [];
$primaryRecommendedOwner = [];
if (!$selectedOwnerIsLifecycle && $selectedOwnerKey !== '') {
    $selectedOwnerPrefix = strtolower($selectedOwnerKey) . '/';
    foreach ($readinessOwners as $candidateOwner) {
        if (!is_array($candidateOwner) || empty($candidateOwner['is_label_lifecycle_owner'])) {
            continue;
        }
        $candidateKey = (string)($candidateOwner['owner_key'] ?? '');
        if ($candidateKey === '' || strncmp(strtolower($candidateKey), $selectedOwnerPrefix, strlen($selectedOwnerPrefix)) !== 0) {
            continue;
        }
        $eligibleLifecycleOwners[] = $candidateOwner;
    }

    foreach ($eligibleLifecycleOwners as $candidateOwner) {
        if (!empty($candidateOwner['all_exist'])) {
            $primaryRecommendedOwner = $candidateOwner;
            break;
        }
    }
    if ($primaryRecommendedOwner === [] && $eligibleLifecycleOwners !== []) {
        $primaryRecommendedOwner = $eligibleLifecycleOwners[0];
    }
}
?>
<link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css">
<section class="gui-studio" data-active-workspace="<?= e($activeWorkspace) ?>">
  <div class="gs-head">
    <h2><?= e($ld('title')) ?></h2>
    <p class="muted"><?= e($ld('subtitle')) ?></p>
  </div>

  <!-- Phase 1: Workspace tab navigation -->
  <style>
  <?php require __DIR__ . '/../Assets/label-designer.css'; ?>
  </style>

  <?php require __DIR__ . '/partials/workspace-tabs.php'; ?>

  <?php require __DIR__ . '/partials/owner-status.php'; ?>

  <section class="gs-studio-tools-panel">
    <div class="gs-studio-tools-heading">
      <div>
        <h4 class="gs-studio-tools-title"><?= e($ld('readonly')) ?></h4>
        <p class="gs-studio-tools-helper"><?= e($ld('safety')) ?></p>
      </div>
    </div>
    <div class="gs-studio-tools-layout">
      <div class="gs-studio-tools-grid">
        <dl class="gs-studio-tools-group">

          <?php if ($activeWorkspace === 'overview'): ?>
            <?php require __DIR__ . '/workspaces/overview.php'; ?>
          <?php elseif ($activeWorkspace === 'build'): ?>
            <?php require __DIR__ . '/workspaces/build.php'; ?>
          <?php elseif ($activeWorkspace === 'rules'): ?>
            <?php require __DIR__ . '/workspaces/rules.php'; ?>
          <?php elseif ($activeWorkspace === 'preview'): ?>
            <?php require __DIR__ . '/workspaces/preview.php'; ?>
          <?php elseif ($activeWorkspace === 'governance'): ?>
            <?php require __DIR__ . '/workspaces/governance.php'; ?>
          <?php endif; ?>
        </dl>
      </div>
    </div>
  <script>
  <?php require __DIR__ . '/../Assets/label-designer.js'; ?>
  </script>
</section>
