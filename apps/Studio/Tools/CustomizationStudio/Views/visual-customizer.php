<?php
$vcModel = isset($vcModel) && is_array($vcModel) ? $vcModel : [];
$vcMetadata = isset($vcModel['metadata']) && is_array($vcModel['metadata']) ? $vcModel['metadata'] : [];
$vcPreviewFixtures = isset($vcMetadata['preview_fixtures']) && is_array($vcMetadata['preview_fixtures'])
  ? array_values(array_filter($vcMetadata['preview_fixtures'], 'is_array'))
  : [];
$vcFixtureSelectorItems = [];
foreach ($vcPreviewFixtures as $fixtureEntry) {
  $fixtureId = trim((string)($fixtureEntry['id'] ?? ''));
  if ($fixtureId === '') {
    continue;
  }

  $fixtureName = trim((string)($fixtureEntry['name'] ?? ''));
  $fixtureDescription = trim((string)($fixtureEntry['description'] ?? ''));
  $vcFixtureSelectorItems[] = [
    'id' => $fixtureId,
    'name' => $fixtureName === '' ? $fixtureId : $fixtureName,
    'description' => $fixtureDescription,
    'related_socket_catalogs' => isset($fixtureEntry['related_socket_catalogs']) && is_array($fixtureEntry['related_socket_catalogs'])
      ? array_values(array_filter($fixtureEntry['related_socket_catalogs'], 'is_string'))
      : [],
    'outline_sections' => isset($fixtureEntry['outline_sections']) && is_array($fixtureEntry['outline_sections'])
      ? array_values(array_filter($fixtureEntry['outline_sections'], 'is_string'))
      : [],
  ];
}
$vcDefaultFixture = $vcFixtureSelectorItems[0] ?? null;
$vcSocketCatalogs = isset($vcMetadata['socket_catalogs']) && is_array($vcMetadata['socket_catalogs'])
  ? array_values(array_filter($vcMetadata['socket_catalogs'], 'is_array'))
  : [];
$vcSocketCatalogSelectorItems = [];
foreach ($vcSocketCatalogs as $catalogEntry) {
  $catalogId = trim((string)($catalogEntry['id'] ?? ''));
  if ($catalogId === '') {
    continue;
  }

  $catalogName = trim((string)($catalogEntry['name'] ?? ''));
  $catalogDescription = trim((string)($catalogEntry['description'] ?? ''));
  $socketRows = isset($catalogEntry['sockets']) && is_array($catalogEntry['sockets'])
    ? array_values(array_filter($catalogEntry['sockets'], 'is_array'))
    : [];
  $vcSocketCatalogSelectorItems[] = [
    'id' => $catalogId,
    'name' => $catalogName === '' ? $catalogId : $catalogName,
    'description' => $catalogDescription,
    'socket_count' => (int)($catalogEntry['socket_count'] ?? count($socketRows)),
    'sockets' => $socketRows,
  ];
}
$vcDefaultCatalogId = '';
if (is_array($vcDefaultFixture) && isset($vcDefaultFixture['related_socket_catalogs']) && is_array($vcDefaultFixture['related_socket_catalogs'])) {
  $vcDefaultCatalogId = (string)($vcDefaultFixture['related_socket_catalogs'][0] ?? '');
}
$vcDefaultSocketCatalog = null;
foreach ($vcSocketCatalogSelectorItems as $catalogItem) {
  if ((string)($catalogItem['id'] ?? '') === $vcDefaultCatalogId) {
    $vcDefaultSocketCatalog = $catalogItem;
    break;
  }
}
if (!is_array($vcDefaultSocketCatalog)) {
  $vcDefaultSocketCatalog = $vcSocketCatalogSelectorItems[0] ?? null;
  $vcDefaultCatalogId = (string)($vcDefaultSocketCatalog['id'] ?? '');
}
$vc = static function (string $key): string {
    $lang = function_exists('current_lang') ? current_lang() : 'en';
    $dict = [
        'en' => [
            'title' => 'Visual Customizer',
            'subtitle' => 'Preview-only draft editing is enabled for Corner scale only. Apply and runtime changes are still disabled.',
            'simple_mode_label' => 'Preview only',
            'simple_mode_note' => 'Choose a preview on the left, inspect it in the center, and review simple controls on the right.',
            'top_controls' => 'Toolbar',
            'theme_selector' => 'Theme',
            'mode_selector' => 'Mode',
            'density_selector' => 'Density',
            'preview_type_selector' => 'Device',
            'save_draft' => 'Manual save disabled',
            'apply' => 'Apply disabled',
            'fixture_selector' => 'Choose preview',
            'preview_types' => 'Preview types',
            'socket_catalog_selector' => 'Socket catalog selector (read-only)',
            'preview_canvas' => 'Preview area',
            'fixture_selected_title' => 'Selected fixture placeholder',
            'fixture_selected_description' => 'Selection updates this title/description only. Real rendering remains disabled.',
            'fixture_selector_empty' => 'No preview fixtures available',
            'socket_catalog_selected_title' => 'Selected socket catalog placeholder',
            'socket_catalog_selected_description' => 'Selection updates this socket catalog summary only. No value editing or runtime consumption.',
            'socket_catalog_selector_empty' => 'No socket catalogs available',
            'socket_count' => 'Socket count',
            'socket_labels' => 'Socket labels',
            'socket_detail_title' => 'Simple controls',
            'socket_detail_hint' => 'Selected component summary. Editing is not enabled yet.',
            'socket_field_label' => 'Label',
            'socket_field_description' => 'Description',
            'socket_field_category' => 'Category',
            'socket_field_scope' => 'Scope',
            'socket_field_value_type' => 'Value type',
            'socket_field_simple_controls' => 'Simple controls',
            'socket_field_advanced_token' => 'Advanced token',
            'socket_field_default_value' => 'Default value',
            'socket_simple_mode_title' => 'Controls',
            'socket_simple_mode_helper' => 'Editing is not enabled yet.',
            'socket_advanced_token_title' => 'Advanced token',
            'socket_advanced_token_helper' => 'Developer token reference only. Not connected to runtime style values.',
            'socket_no_advanced_token' => 'No advanced token declared yet.',
            'socket_source_label' => 'Source',
            'socket_source_value' => 'Shell Style socket catalog',
            'socket_runtime_status_label' => 'Runtime status',
            'socket_runtime_status_value' => 'catalog_only_not_consumed',
            'socket_current_value_label' => 'Current value',
            'socket_current_value_not_connected' => 'Not connected yet',
            'socket_proposed_value_label' => 'Proposed value',
            'socket_proposed_value_none' => 'No draft yet',
            'socket_local_draft_status_label' => 'Draft',
            'socket_local_draft_empty' => 'No local draft yet.',
            'socket_local_draft_saved' => 'Local draft saved for Corner scale.',
            'socket_local_draft_runtime_note' => 'This does not change the live system.',
            'socket_runtime_not_applied_label' => 'Runtime',
            'socket_runtime_not_applied_value' => 'Not applied',
            'socket_apply_disabled_label' => 'Apply',
            'socket_apply_disabled_value' => 'Disabled',
            'socket_apply_disabled_note' => 'Apply is disabled until approval/runtime registry flow is implemented.',
            'socket_storage_label' => 'Storage',
            'socket_storage_studio_only' => 'Studio only',
            'socket_reset_status_label' => 'Reset status',
            'socket_reset_status_disabled' => 'Disabled until editing is enabled',
            'socket_reset_status_local_draft' => 'Studio-local draft only',
            'socket_value_safety_title' => 'Value safety',
            'socket_value_safety_helper' => 'Local drafts are saved in Studio only. Runtime style is not applied.',
            'draft_diff_preview_title' => 'Draft change preview',
            'draft_diff_no_change' => 'No draft change',
            'draft_diff_changed_prefix' => 'Corner scale changed from soft to',
            'draft_diff_default_label' => 'Default',
            'draft_diff_current_label' => 'Current',
            'draft_diff_proposed_label' => 'Proposed',
            'draft_diff_impact_label' => 'Impact',
            'draft_diff_impact_preview_only' => 'Studio preview only',
            'draft_diff_apply_label' => 'Apply',
            'draft_diff_apply_disabled' => 'Disabled',
            'draft_diff_socket_label' => 'Socket',
            'draft_diff_token_label' => 'Token',
            'draft_diff_source_label' => 'Source',
            'draft_diff_source_studio_local' => 'Studio-local draft',
            'draft_diff_runtime_label' => 'Runtime',
            'draft_diff_runtime_not_applied' => 'not applied',
            'socket_reset_control' => 'Reset this control',
            'socket_reset_section' => 'Reset this section',
            'socket_discard_draft' => 'Discard draft',
            'socket_restore_last_approved' => 'Restore last approved',
            'lifecycle_title' => 'Customization lifecycle',
            'lifecycle_hint' => 'Follow the safe path from draft to preview, review, approval, apply, and recovery.',
            'lifecycle_draft_label' => 'Draft',
            'lifecycle_draft_value' => 'Not started',
            'lifecycle_preview_label' => 'Preview',
            'lifecycle_preview_value' => 'Static only',
            'lifecycle_diff_label' => 'Diff',
            'lifecycle_diff_value' => 'Not generated',
            'lifecycle_approval_label' => 'Approval',
            'lifecycle_approval_value' => 'Not requested',
            'lifecycle_apply_label' => 'Apply',
            'lifecycle_apply_value' => 'Disabled',
            'lifecycle_rollback_label' => 'Rollback',
            'lifecycle_rollback_value' => 'Not available',
            'socket_detail_empty' => 'No socket selected',
            'socket_not_editable_yet' => 'Not editable yet',
            'socket_editable_safe_control_only' => 'Draft edit enabled for Corner scale only',
            'advanced_details_summary' => 'Advanced details / developer metadata',
            'advanced_details_hint' => 'Socket catalogs, token fields, relationships, metadata discovery, and architecture ownership stay read-only here.',
            'relationship_title' => 'Preview/socket relationship view (read-only)',
            'relationship_hint' => 'Shows fixture-to-catalog and catalog-to-fixture metadata relationships only.',
            'relationship_fixture_to_catalogs' => 'Selected fixture uses socket catalogs',
            'relationship_catalog_to_fixtures' => 'Selected socket catalog may be used by fixtures',
            'relationship_none' => 'No related entries found',
            'canvas_placeholder_title' => 'Fixture preview canvas placeholder',
            'canvas_placeholder_hint' => 'Static placeholder layout by fixture type. No rendering engine is active.',
            'canvas_static_marker' => 'static preview placeholder',
            'outline_title' => 'Fixture preview outline (read-only)',
            'outline_hint' => 'Shows intended sections/components from fixture metadata only.',
            'outline_sections' => 'Sections/components in selected fixture',
            'outline_none' => 'No preview outline sections found',
            'component_samples' => 'Component samples',
            'card' => 'Card',
            'button' => 'Button',
            'table' => 'Table',
            'form' => 'Form',
            'chart' => 'Chart',
            'diagram' => 'Diagram',
            'workflow_card' => 'Workflow card',
            'print_preview' => 'Print preview',
            'inspector' => 'Inspector panel',
            'inspector_hint' => 'Clicking components will later show related style sockets.',
            'draft_status' => 'Draft status',
            'diff' => 'Diff preview',
            'risk_approval' => 'Risk and approval',
            'rollback' => 'Rollback plan',
            'status_disabled' => 'Not editable yet. This control is intentionally non-functional.',
            'bottom_status' => 'Apply disabled',
            'ownership_title' => 'Architecture ownership',
            'ownership_studio' => 'Customization Studio owns visual editing workflow only.',
            'ownership_shell' => 'Shell owns style sockets and future runtime consumption.',
            'ownership_platform' => 'Platform/System owns approved active/default style registry truth.',
            'ownership_apps' => 'Apps/modules own scoped CSS.',
            'ownership_org' => 'Organization owns brand identity.',
            'ownership_assets' => 'public/assets is generated delivery output only.',
            'metadata_read_only' => 'Metadata discovery (read-only)',
            'metadata_source_scope' => 'Source scope',
            'socket_catalogs' => 'Socket catalogs',
            'preview_fixtures' => 'Preview fixtures',
            'metadata_none' => 'No metadata files discovered in this Studio tool resource folder.',
            'readiness_title' => 'Request Approval readiness',
            'readiness_hint' => 'Readiness checks before a Request Approval can be created. Server validation is checked at page load — refresh to re-evaluate after draft changes.',
            'readiness_gate_doc' => 'Validation gate rules',
            'readiness_request_disabled' => 'Request Approval: Disabled',
            'readiness_socket_pass' => 'Selected socket: radius.scale',
            'readiness_proposed_value' => 'Proposed value exists',
            'readiness_allowed_value' => 'Allowed value: sharp / soft / round',
            'readiness_diff' => 'Diff preview exists',
            'readiness_storage' => 'Storage: Studio-local',
            'readiness_apply' => 'Apply: Disabled',
            'readiness_runtime' => 'Runtime: Untouched',
            'readiness_yes' => 'Yes',
            'readiness_no' => 'No',
            'readiness_eligible' => 'Eligible',
            'readiness_not_eligible' => 'Not eligible',
            'readiness_request_eligible' => 'Request Approval: Eligible',
            'readiness_request_not_eligible' => 'Request Approval: Not eligible',
            'readiness_recheck' => 'Recheck readiness',
            'approval_request_create' => 'Request Approval',
            'approval_request_pending' => 'Request: Pending review',
            'approval_request_already_pending' => 'Request already pending',
            'approval_request_creating' => 'Requesting\u2026',
            'approval_request_failed' => 'Request creation failed',
            'approval_requests_title' => 'Pending Approval Requests',
            'approval_requests_empty' => 'No pending requests',
            'approval_requests_request_id' => 'Request ID',
            'approval_requests_requested_by' => 'Requested by',
            'approval_requests_socket' => 'Socket',
            'approval_requests_proposed_value' => 'Proposed value',
            'approval_requests_diff' => 'Diff',
            'approval_requests_status' => 'Status',
            'approval_requests_created' => 'Created',
            'approval_requests_runtime' => 'Runtime',
            'approval_requests_runtime_not_applied' => 'Not applied',
        ],
        'ja' => [
            'title' => 'Visual Customizer',
            'subtitle' => 'Preview-only draft editing is enabled for Corner scale only. Apply and runtime changes are still disabled.',
            'simple_mode_label' => 'Preview only',
            'simple_mode_note' => 'Choose a preview on the left, inspect it in the center, and review simple controls on the right.',
            'top_controls' => 'Toolbar',
            'theme_selector' => 'Theme',
            'mode_selector' => 'Mode',
            'density_selector' => 'Density',
            'preview_type_selector' => 'Device',
            'save_draft' => 'Manual save disabled',
            'apply' => 'Apply disabled',
            'fixture_selector' => 'Choose preview',
            'preview_types' => 'Preview types',
            'socket_catalog_selector' => 'Socket catalog selector (read-only)',
            'preview_canvas' => 'Preview area',
            'fixture_selected_title' => 'Selected fixture placeholder',
            'fixture_selected_description' => 'Selection updates this title/description only. Real rendering remains disabled.',
            'fixture_selector_empty' => 'No preview fixtures available',
            'socket_catalog_selected_title' => 'Selected socket catalog placeholder',
            'socket_catalog_selected_description' => 'Selection updates this socket catalog summary only. No value editing or runtime consumption.',
            'socket_catalog_selector_empty' => 'No socket catalogs available',
            'socket_count' => 'Socket count',
            'socket_labels' => 'Socket labels',
            'socket_detail_title' => 'Simple controls',
            'socket_detail_hint' => 'Selected component summary. Editing is not enabled yet.',
            'socket_field_label' => 'Label',
            'socket_field_description' => 'Description',
            'socket_field_category' => 'Category',
            'socket_field_scope' => 'Scope',
            'socket_field_value_type' => 'Value type',
            'socket_field_simple_controls' => 'Simple controls',
            'socket_field_advanced_token' => 'Advanced token',
            'socket_field_default_value' => 'Default value',
            'socket_simple_mode_title' => 'Controls',
            'socket_simple_mode_helper' => 'Editing is not enabled yet.',
            'socket_advanced_token_title' => 'Advanced token',
            'socket_advanced_token_helper' => 'Developer token reference only. Not connected to runtime style values.',
            'socket_no_advanced_token' => 'No advanced token declared yet.',
            'socket_source_label' => 'Source',
            'socket_source_value' => 'Shell Style socket catalog',
            'socket_runtime_status_label' => 'Runtime status',
            'socket_runtime_status_value' => 'catalog_only_not_consumed',
            'socket_current_value_label' => 'Current value',
            'socket_current_value_not_connected' => 'Not connected yet',
            'socket_proposed_value_label' => 'Proposed value',
            'socket_proposed_value_none' => 'No draft yet',
            'socket_local_draft_status_label' => 'Draft',
            'socket_local_draft_empty' => 'No local draft yet.',
            'socket_local_draft_saved' => 'Local draft saved for Corner scale.',
            'socket_local_draft_runtime_note' => 'This does not change the live system.',
            'socket_runtime_not_applied_label' => 'Runtime',
            'socket_runtime_not_applied_value' => 'Not applied',
            'socket_apply_disabled_label' => 'Apply',
            'socket_apply_disabled_value' => 'Disabled',
            'socket_apply_disabled_note' => 'Apply is disabled until approval/runtime registry flow is implemented.',
            'socket_storage_label' => 'Storage',
            'socket_storage_studio_only' => 'Studio only',
            'socket_reset_status_label' => 'Reset status',
            'socket_reset_status_disabled' => 'Disabled until editing is enabled',
            'socket_reset_status_local_draft' => 'Studio-local draft only',
            'socket_value_safety_title' => 'Value safety',
            'socket_value_safety_helper' => 'Local drafts are saved in Studio only. Runtime style is not applied.',
            'draft_diff_preview_title' => 'Draft change preview',
            'draft_diff_no_change' => 'No draft change',
            'draft_diff_changed_prefix' => 'Corner scale changed from soft to',
            'draft_diff_default_label' => 'Default',
            'draft_diff_current_label' => 'Current',
            'draft_diff_proposed_label' => 'Proposed',
            'draft_diff_impact_label' => 'Impact',
            'draft_diff_impact_preview_only' => 'Studio preview only',
            'draft_diff_apply_label' => 'Apply',
            'draft_diff_apply_disabled' => 'Disabled',
            'draft_diff_socket_label' => 'Socket',
            'draft_diff_token_label' => 'Token',
            'draft_diff_source_label' => 'Source',
            'draft_diff_source_studio_local' => 'Studio-local draft',
            'draft_diff_runtime_label' => 'Runtime',
            'draft_diff_runtime_not_applied' => 'not applied',
            'socket_reset_control' => 'Reset this control',
            'socket_reset_section' => 'Reset this section',
            'socket_discard_draft' => 'Discard draft',
            'socket_restore_last_approved' => 'Restore last approved',
            'lifecycle_title' => 'Customization lifecycle',
            'lifecycle_hint' => 'Follow the safe path from draft to preview, review, approval, apply, and recovery.',
            'lifecycle_draft_label' => 'Draft',
            'lifecycle_draft_value' => 'Not started',
            'lifecycle_preview_label' => 'Preview',
            'lifecycle_preview_value' => 'Static only',
            'lifecycle_diff_label' => 'Diff',
            'lifecycle_diff_value' => 'Not generated',
            'lifecycle_approval_label' => 'Approval',
            'lifecycle_approval_value' => 'Not requested',
            'lifecycle_apply_label' => 'Apply',
            'lifecycle_apply_value' => 'Disabled',
            'lifecycle_rollback_label' => 'Rollback',
            'lifecycle_rollback_value' => 'Not available',
            'socket_detail_empty' => 'No socket selected',
            'socket_not_editable_yet' => 'Not editable yet',
            'socket_editable_safe_control_only' => 'Draft edit enabled for Corner scale only',
            'advanced_details_summary' => 'Advanced details / developer metadata',
            'advanced_details_hint' => 'Socket catalogs, token fields, relationships, metadata discovery, and architecture ownership stay read-only here.',
            'relationship_title' => 'Preview/socket relationship view (read-only)',
            'relationship_hint' => 'Shows fixture-to-catalog and catalog-to-fixture metadata relationships only.',
            'relationship_fixture_to_catalogs' => 'Selected fixture uses socket catalogs',
            'relationship_catalog_to_fixtures' => 'Selected socket catalog may be used by fixtures',
            'relationship_none' => 'No related entries found',
            'canvas_placeholder_title' => 'Fixture preview canvas placeholder',
            'canvas_placeholder_hint' => 'Static placeholder layout by fixture type. No rendering engine is active.',
            'canvas_static_marker' => 'static preview placeholder',
            'outline_title' => 'Fixture preview outline (read-only)',
            'outline_hint' => 'Shows intended sections/components from fixture metadata only.',
            'outline_sections' => 'Sections/components in selected fixture',
            'outline_none' => 'No preview outline sections found',
            'component_samples' => 'Component samples',
            'card' => 'Card',
            'button' => 'Button',
            'table' => 'Table',
            'form' => 'Form',
            'chart' => 'Chart',
            'diagram' => 'Diagram',
            'workflow_card' => 'Workflow card',
            'print_preview' => 'Print preview',
            'inspector' => 'Inspector panel',
            'inspector_hint' => 'Clicking components will later show related style sockets.',
            'draft_status' => 'Draft status',
            'diff' => 'Diff preview',
            'risk_approval' => 'Risk and approval',
            'rollback' => 'Rollback plan',
            'status_disabled' => 'Not editable yet. This control is intentionally non-functional.',
            'bottom_status' => 'Apply disabled',
            'ownership_title' => 'Architecture ownership',
            'ownership_studio' => 'Customization Studio owns visual editing workflow only.',
            'ownership_shell' => 'Shell owns style sockets and future runtime consumption.',
            'ownership_platform' => 'Platform/System owns approved active/default style registry truth.',
            'ownership_apps' => 'Apps/modules own scoped CSS.',
            'ownership_org' => 'Organization owns brand identity.',
            'ownership_assets' => 'public/assets is generated delivery output only.',
            'metadata_read_only' => 'Metadata discovery (read-only)',
            'metadata_source_scope' => 'Source scope',
            'socket_catalogs' => 'Socket catalogs',
            'preview_fixtures' => 'Preview fixtures',
            'metadata_none' => 'No metadata files discovered in this Studio tool resource folder.',
            'readiness_title' => 'Request Approval readiness',
            'readiness_hint' => 'Readiness checks before a Request Approval can be created. Server validation is checked at page load — refresh to re-evaluate after draft changes.',
            'readiness_gate_doc' => 'Validation gate rules',
            'readiness_request_disabled' => 'Request Approval: Disabled',
            'readiness_socket_pass' => 'Selected socket: radius.scale',
            'readiness_proposed_value' => 'Proposed value exists',
            'readiness_allowed_value' => 'Allowed value: sharp / soft / round',
            'readiness_diff' => 'Diff preview exists',
            'readiness_storage' => 'Storage: Studio-local',
            'readiness_apply' => 'Apply: Disabled',
            'readiness_runtime' => 'Runtime: Untouched',
            'readiness_yes' => 'Yes',
            'readiness_no' => 'No',
            'readiness_eligible' => 'Eligible',
            'readiness_not_eligible' => 'Not eligible',
            'readiness_request_eligible' => 'Request Approval: Eligible',
            'readiness_request_not_eligible' => 'Request Approval: Not eligible',
            'readiness_recheck' => 'Recheck readiness',
            'approval_request_create' => '承認リクエスト',
            'approval_request_pending' => 'リクエスト: レビュー保留中',
            'approval_request_already_pending' => 'リクエストはすでに保留中です',
            'approval_request_creating' => 'リクエスト中\u2026',
            'approval_request_failed' => 'リクエスト作成に失敗しました',
            'approval_requests_title' => '保留中の承認リクエスト',
            'approval_requests_empty' => '保留中のリクエストはありません',
            'approval_requests_request_id' => 'リクエストID',
            'approval_requests_requested_by' => 'リクエスト元',
            'approval_requests_socket' => 'ソケット',
            'approval_requests_proposed_value' => '提案値',
            'approval_requests_diff' => '差分',
            'approval_requests_status' => 'ステータス',
            'approval_requests_created' => '作成日時',
            'approval_requests_runtime' => 'ランタイム',
            'approval_requests_runtime_not_applied' => '未適用',
        ],
        'ne' => [
            'title' => 'Visual Customizer',
            'subtitle' => 'Preview-only draft editing is enabled for Corner scale only. Apply and runtime changes are still disabled.',
            'simple_mode_label' => 'Preview only',
            'simple_mode_note' => 'Choose a preview on the left, inspect it in the center, and review simple controls on the right.',
            'top_controls' => 'Toolbar',
            'theme_selector' => 'Theme',
            'mode_selector' => 'Mode',
            'density_selector' => 'Density',
            'preview_type_selector' => 'Device',
            'save_draft' => 'Manual save disabled',
            'apply' => 'Apply disabled',
            'fixture_selector' => 'Choose preview',
            'preview_types' => 'Preview types',
            'socket_catalog_selector' => 'Socket catalog selector (read-only)',
            'preview_canvas' => 'Preview area',
            'fixture_selected_title' => 'Selected fixture placeholder',
            'fixture_selected_description' => 'Selection updates this title/description only. Real rendering remains disabled.',
            'fixture_selector_empty' => 'No preview fixtures available',
            'socket_catalog_selected_title' => 'Selected socket catalog placeholder',
            'socket_catalog_selected_description' => 'Selection updates this socket catalog summary only. No value editing or runtime consumption.',
            'socket_catalog_selector_empty' => 'No socket catalogs available',
            'socket_count' => 'Socket count',
            'socket_labels' => 'Socket labels',
            'socket_detail_title' => 'Simple controls',
            'socket_detail_hint' => 'Selected component summary. Editing is not enabled yet.',
            'socket_field_label' => 'Label',
            'socket_field_description' => 'Description',
            'socket_field_category' => 'Category',
            'socket_field_scope' => 'Scope',
            'socket_field_value_type' => 'Value type',
            'socket_field_simple_controls' => 'Simple controls',
            'socket_field_advanced_token' => 'Advanced token',
            'socket_field_default_value' => 'Default value',
            'socket_simple_mode_title' => 'Controls',
            'socket_simple_mode_helper' => 'Editing is not enabled yet.',
            'socket_advanced_token_title' => 'Advanced token',
            'socket_advanced_token_helper' => 'Developer token reference only. Not connected to runtime style values.',
            'socket_no_advanced_token' => 'No advanced token declared yet.',
            'socket_source_label' => 'Source',
            'socket_source_value' => 'Shell Style socket catalog',
            'socket_runtime_status_label' => 'Runtime status',
            'socket_runtime_status_value' => 'catalog_only_not_consumed',
            'socket_current_value_label' => 'Current value',
            'socket_current_value_not_connected' => 'Not connected yet',
            'socket_proposed_value_label' => 'Proposed value',
            'socket_proposed_value_none' => 'No draft yet',
            'socket_local_draft_status_label' => 'Draft',
            'socket_local_draft_empty' => 'No local draft yet.',
            'socket_local_draft_saved' => 'Local draft saved for Corner scale.',
            'socket_local_draft_runtime_note' => 'This does not change the live system.',
            'socket_runtime_not_applied_label' => 'Runtime',
            'socket_runtime_not_applied_value' => 'Not applied',
            'socket_apply_disabled_label' => 'Apply',
            'socket_apply_disabled_value' => 'Disabled',
            'socket_apply_disabled_note' => 'Apply is disabled until approval/runtime registry flow is implemented.',
            'socket_storage_label' => 'Storage',
            'socket_storage_studio_only' => 'Studio only',
            'socket_reset_status_label' => 'Reset status',
            'socket_reset_status_disabled' => 'Disabled until editing is enabled',
            'socket_reset_status_local_draft' => 'Studio-local draft only',
            'socket_value_safety_title' => 'Value safety',
            'socket_value_safety_helper' => 'Local drafts are saved in Studio only. Runtime style is not applied.',
            'draft_diff_preview_title' => 'Draft change preview',
            'draft_diff_no_change' => 'No draft change',
            'draft_diff_changed_prefix' => 'Corner scale changed from soft to',
            'draft_diff_default_label' => 'Default',
            'draft_diff_current_label' => 'Current',
            'draft_diff_proposed_label' => 'Proposed',
            'draft_diff_impact_label' => 'Impact',
            'draft_diff_impact_preview_only' => 'Studio preview only',
            'draft_diff_apply_label' => 'Apply',
            'draft_diff_apply_disabled' => 'Disabled',
            'draft_diff_socket_label' => 'Socket',
            'draft_diff_token_label' => 'Token',
            'draft_diff_source_label' => 'Source',
            'draft_diff_source_studio_local' => 'Studio-local draft',
            'draft_diff_runtime_label' => 'Runtime',
            'draft_diff_runtime_not_applied' => 'not applied',
            'socket_reset_control' => 'Reset this control',
            'socket_reset_section' => 'Reset this section',
            'socket_discard_draft' => 'Discard draft',
            'socket_restore_last_approved' => 'Restore last approved',
            'lifecycle_title' => 'Customization lifecycle',
            'lifecycle_hint' => 'Follow the safe path from draft to preview, review, approval, apply, and recovery.',
            'lifecycle_draft_label' => 'Draft',
            'lifecycle_draft_value' => 'Not started',
            'lifecycle_preview_label' => 'Preview',
            'lifecycle_preview_value' => 'Static only',
            'lifecycle_diff_label' => 'Diff',
            'lifecycle_diff_value' => 'Not generated',
            'lifecycle_approval_label' => 'Approval',
            'lifecycle_approval_value' => 'Not requested',
            'lifecycle_apply_label' => 'Apply',
            'lifecycle_apply_value' => 'Disabled',
            'lifecycle_rollback_label' => 'Rollback',
            'lifecycle_rollback_value' => 'Not available',
            'socket_detail_empty' => 'No socket selected',
            'socket_not_editable_yet' => 'Not editable yet',
            'socket_editable_safe_control_only' => 'Draft edit enabled for Corner scale only',
            'advanced_details_summary' => 'Advanced details / developer metadata',
            'advanced_details_hint' => 'Socket catalogs, token fields, relationships, metadata discovery, and architecture ownership stay read-only here.',
            'relationship_title' => 'Preview/socket relationship view (read-only)',
            'relationship_hint' => 'Shows fixture-to-catalog and catalog-to-fixture metadata relationships only.',
            'relationship_fixture_to_catalogs' => 'Selected fixture uses socket catalogs',
            'relationship_catalog_to_fixtures' => 'Selected socket catalog may be used by fixtures',
            'relationship_none' => 'No related entries found',
            'canvas_placeholder_title' => 'Fixture preview canvas placeholder',
            'canvas_placeholder_hint' => 'Static placeholder layout by fixture type. No rendering engine is active.',
            'canvas_static_marker' => 'static preview placeholder',
            'outline_title' => 'Fixture preview outline (read-only)',
            'outline_hint' => 'Shows intended sections/components from fixture metadata only.',
            'outline_sections' => 'Sections/components in selected fixture',
            'outline_none' => 'No preview outline sections found',
            'component_samples' => 'Component samples',
            'card' => 'Card',
            'button' => 'Button',
            'table' => 'Table',
            'form' => 'Form',
            'chart' => 'Chart',
            'diagram' => 'Diagram',
            'workflow_card' => 'Workflow card',
            'print_preview' => 'Print preview',
            'inspector' => 'Inspector panel',
            'inspector_hint' => 'Clicking components will later show related style sockets.',
            'draft_status' => 'Draft status',
            'diff' => 'Diff preview',
            'risk_approval' => 'Risk and approval',
            'rollback' => 'Rollback plan',
            'status_disabled' => 'Not editable yet. This control is intentionally non-functional.',
            'bottom_status' => 'Apply disabled',
            'ownership_title' => 'Architecture ownership',
            'ownership_studio' => 'Customization Studio owns visual editing workflow only.',
            'ownership_shell' => 'Shell owns style sockets and future runtime consumption.',
            'ownership_platform' => 'Platform/System owns approved active/default style registry truth.',
            'ownership_apps' => 'Apps/modules own scoped CSS.',
            'ownership_org' => 'Organization owns brand identity.',
            'ownership_assets' => 'public/assets is generated delivery output only.',
            'metadata_read_only' => 'Metadata discovery (read-only)',
            'metadata_source_scope' => 'Source scope',
            'socket_catalogs' => 'Socket catalogs',
            'preview_fixtures' => 'Preview fixtures',
            'metadata_none' => 'No metadata files discovered in this Studio tool resource folder.',
            'readiness_title' => 'Request Approval readiness',
            'readiness_hint' => 'Readiness checks before a Request Approval can be created. Server validation is checked at page load — refresh to re-evaluate after draft changes.',
            'readiness_gate_doc' => 'Validation gate rules',
            'readiness_request_disabled' => 'Request Approval: Disabled',
            'readiness_socket_pass' => 'Selected socket: radius.scale',
            'readiness_proposed_value' => 'Proposed value exists',
            'readiness_allowed_value' => 'Allowed value: sharp / soft / round',
            'readiness_diff' => 'Diff preview exists',
            'readiness_storage' => 'Storage: Studio-local',
            'readiness_apply' => 'Apply: Disabled',
            'readiness_runtime' => 'Runtime: Untouched',
            'readiness_yes' => 'Yes',
            'readiness_no' => 'No',
            'readiness_eligible' => 'Eligible',
            'readiness_not_eligible' => 'Not eligible',
            'readiness_request_eligible' => 'Request Approval: Eligible',
            'readiness_request_not_eligible' => 'Request Approval: Not eligible',
            'readiness_recheck' => 'Recheck readiness',
            'approval_request_create' => 'Request Approval',
            'approval_request_pending' => 'Request: Pending review',
            'approval_request_already_pending' => 'Request already pending',
            'approval_request_creating' => 'Requesting\u2026',
            'approval_request_failed' => 'Request creation failed',
            'approval_requests_title' => 'Pending Approval Requests',
            'approval_requests_empty' => 'No pending requests',
            'approval_requests_request_id' => 'Request ID',
            'approval_requests_requested_by' => 'Requested by',
            'approval_requests_socket' => 'Socket',
            'approval_requests_proposed_value' => 'Proposed value',
            'approval_requests_diff' => 'Diff',
            'approval_requests_status' => 'Status',
            'approval_requests_created' => 'Created',
            'approval_requests_runtime' => 'Runtime',
            'approval_requests_runtime_not_applied' => 'Not applied',
            'socket_browse_title' => 'All Style Sockets',
            'socket_browse_hint' => 'Read-only browse of all Shell Style socket catalog entries. Sockets are not consumed by Shell runtime yet.',
            'socket_browse_search' => 'Search sockets\u2026',
            'socket_browse_search_label' => 'Search',
            'socket_browse_filter_catalog' => 'All catalogs',
            'socket_browse_filter_category' => 'All categories',
            'socket_browse_filter_type' => 'All types',
            'socket_browse_count' => 'sockets',
            'socket_browse_visible' => 'Visible',
            'socket_browse_total' => 'Total',
            'socket_browse_catalog_total' => 'Catalogs',
            'socket_browse_category_total' => 'Categories',
            'socket_browse_type_total' => 'Value types',
            'socket_browse_catalog' => 'Catalog',
            'socket_browse_category' => 'Category',
            'socket_browse_type' => 'Type',
            'socket_browse_socket' => 'Socket',
            'socket_browse_label' => 'Label',
            'socket_browse_description' => 'Description',
            'socket_browse_scope' => 'Scope',
            'socket_browse_default_value' => 'Default value',
            'socket_browse_advanced_token' => 'Advanced token',
            'socket_browse_simple_controls' => 'Simple controls',
            'socket_browse_not_declared' => 'Not declared',
            'socket_browse_readonly_metadata' => 'Read-only metadata',
            'socket_browse_none' => 'No sockets match your filters.',
            'socket_browse_none_suggestion' => 'Try clearing some filters to see more results.',
            'socket_browse_detail_title' => 'Socket details',
            'socket_browse_detail_none' => 'Select a socket to see details.',
            'socket_browse_status_banner' => 'Catalog metadata only \u2014 not consumed by Shell runtime.',
            'socket_browse_status_row' => 'Runtime status',
            'socket_browse_status_inline' => 'Catalog only \u2014 not consumed',
        ],
    ];

    $socketBrowserLabels = [
        'socket_browse_title' => 'All Style Sockets',
        'socket_browse_hint' => 'Read-only browse of all Shell Style socket catalog entries. Sockets are not consumed by Shell runtime yet.',
        'socket_browse_search' => 'Search sockets...',
        'socket_browse_search_label' => 'Search',
        'socket_browse_filter_catalog' => 'All catalogs',
        'socket_browse_filter_category' => 'All categories',
        'socket_browse_filter_type' => 'All types',
        'socket_browse_count' => 'sockets',
        'socket_browse_visible' => 'Visible',
        'socket_browse_total' => 'Total',
        'socket_browse_catalog_total' => 'Catalogs',
        'socket_browse_category_total' => 'Categories',
        'socket_browse_type_total' => 'Value types',
        'socket_browse_catalog' => 'Catalog',
        'socket_browse_category' => 'Category',
        'socket_browse_type' => 'Type',
        'socket_browse_socket' => 'Socket',
        'socket_browse_label' => 'Label',
        'socket_browse_description' => 'Description',
        'socket_browse_scope' => 'Scope',
        'socket_browse_default_value' => 'Default value',
        'socket_browse_advanced_token' => 'Advanced token',
        'socket_browse_simple_controls' => 'Simple controls',
        'socket_browse_not_declared' => 'Not declared',
        'socket_browse_readonly_metadata' => 'Read-only metadata',
        'socket_browse_none' => 'No sockets match your filters.',
        'socket_browse_none_suggestion' => 'Try clearing some filters to see more results.',
        'socket_browse_detail_title' => 'Socket details',
        'socket_browse_detail_none' => 'Select a socket to see details.',
        'socket_browse_status_banner' => 'Catalog metadata only - not consumed by Shell runtime.',
        'socket_browse_status_row' => 'Runtime status',
        'socket_browse_status_inline' => 'Catalog only - not consumed',
    ];

    foreach (array_keys($dict) as $locale) {
        $dict[$locale] = $dict[$locale] + $socketBrowserLabels;
    }

    $set = isset($dict[$lang]) && is_array($dict[$lang]) ? $dict[$lang] : $dict['en'];
    return (string)($set[$key] ?? ($dict['en'][$key] ?? $key));
};

$vcFixturePayloadJson = json_encode([
  'fixtures' => $vcFixtureSelectorItems,
  'socket_catalogs' => $vcSocketCatalogSelectorItems,
  'default_fixture_id' => (string)($vcDefaultFixture['id'] ?? ''),
  'default_socket_catalog_id' => $vcDefaultCatalogId,
  'persisted_draft' => isset($vcModel['persisted_draft']) && is_array($vcModel['persisted_draft']) ? $vcModel['persisted_draft'] : [],
  'draft_update_endpoint' => (string)($vcModel['draft_update_endpoint'] ?? ''),
  'recheck_readiness_endpoint' => (string)($vcModel['recheck_readiness_endpoint'] ?? ''),
  'create_approval_request_endpoint' => (string)($vcModel['create_approval_request_endpoint'] ?? ''),
  'csrf' => (string)($vcModel['csrf'] ?? ''),
  'validation_result' => isset($vcModel['validation_result']) && is_array($vcModel['validation_result']) ? $vcModel['validation_result'] : [],
  'i18n' => [
    'socket_detail_empty' => $vc('socket_detail_empty'),
    'socket_no_advanced_token' => $vc('socket_no_advanced_token'),
    'socket_current_value_not_connected' => $vc('socket_current_value_not_connected'),
    'socket_proposed_value_none' => $vc('socket_proposed_value_none'),
    'socket_local_draft_empty' => $vc('socket_local_draft_empty'),
    'socket_local_draft_saved' => $vc('socket_local_draft_saved'),
    'socket_local_draft_runtime_note' => $vc('socket_local_draft_runtime_note'),
    'socket_reset_status_local_draft' => $vc('socket_reset_status_local_draft'),
    'socket_editable_safe_control_only' => $vc('socket_editable_safe_control_only'),
    'draft_diff_no_change' => $vc('draft_diff_no_change'),
    'draft_diff_changed_prefix' => $vc('draft_diff_changed_prefix'),
    'draft_diff_impact_preview_only' => $vc('draft_diff_impact_preview_only'),
    'draft_diff_apply_disabled' => $vc('draft_diff_apply_disabled'),
    'draft_diff_source_studio_local' => $vc('draft_diff_source_studio_local'),
    'draft_diff_runtime_not_applied' => $vc('draft_diff_runtime_not_applied'),
    'readiness_yes' => $vc('readiness_yes'),
    'readiness_no' => $vc('readiness_no'),
    'readiness_eligible' => $vc('readiness_eligible'),
    'readiness_not_eligible' => $vc('readiness_not_eligible'),
    'readiness_request_eligible' => $vc('readiness_request_eligible'),
    'readiness_request_not_eligible' => $vc('readiness_request_not_eligible'),
    'approval_request_create' => $vc('approval_request_create'),
    'approval_request_pending' => $vc('approval_request_pending'),
    'approval_request_already_pending' => $vc('approval_request_already_pending'),
    'approval_request_creating' => $vc('approval_request_creating'),
    'approval_request_failed' => $vc('approval_request_failed'),
    'socket_browse_count' => $vc('socket_browse_count'),
    'socket_browse_visible' => $vc('socket_browse_visible'),
    'socket_browse_total' => $vc('socket_browse_total'),
    'socket_browse_catalog_total' => $vc('socket_browse_catalog_total'),
    'socket_browse_category_total' => $vc('socket_browse_category_total'),
    'socket_browse_type_total' => $vc('socket_browse_type_total'),
    'socket_browse_catalog' => $vc('socket_browse_catalog'),
    'socket_browse_category' => $vc('socket_browse_category'),
    'socket_browse_type' => $vc('socket_browse_type'),
    'socket_browse_socket' => $vc('socket_browse_socket'),
    'socket_browse_label' => $vc('socket_browse_label'),
    'socket_browse_description' => $vc('socket_browse_description'),
    'socket_browse_scope' => $vc('socket_browse_scope'),
    'socket_browse_default_value' => $vc('socket_browse_default_value'),
    'socket_browse_advanced_token' => $vc('socket_browse_advanced_token'),
    'socket_browse_simple_controls' => $vc('socket_browse_simple_controls'),
    'socket_browse_not_declared' => $vc('socket_browse_not_declared'),
    'socket_browse_readonly_metadata' => $vc('socket_browse_readonly_metadata'),
    'socket_browse_none' => $vc('socket_browse_none'),
    'socket_browse_none_suggestion' => $vc('socket_browse_none_suggestion'),
    'socket_browse_detail_none' => $vc('socket_browse_detail_none'),
    'socket_browse_status_row' => $vc('socket_browse_status_row'),
    'socket_browse_status_inline' => $vc('socket_browse_status_inline'),
  ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($vcFixturePayloadJson) || $vcFixturePayloadJson === '') {
  $vcFixturePayloadJson = '{"fixtures":[],"socket_catalogs":[],"default_fixture_id":"","default_socket_catalog_id":"","i18n":{}}';
}

$vcAllSocketsJson = json_encode($vcModel['all_sockets'] ?? ['total' => 0, 'catalogs' => [], 'sockets' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($vcAllSocketsJson) || $vcAllSocketsJson === '') {
  $vcAllSocketsJson = '{"total":0,"catalogs":[],"sockets":[]}';
}
?>
<style>
<?php require __DIR__ . '/../assets/visual-customizer.css'; ?>
</style>
<section class="cs-vc" data-runtime-status="<?= e((string)($vcModel['runtime_status'] ?? 'preview_skeleton_only_not_connected')) ?>">
  <header class="cs-vc__header" aria-label="<?= e($vc('title')) ?>">
    <div class="cs-vc__title-block">
      <h2><?= e($vc('title')) ?></h2>
      <span class="cs-vc__mode-label"><?= e($vc('simple_mode_label')) ?></span>
      <p><?= e($vc('subtitle')) ?></p>
    </div>
  </header>

  <nav class="cs-vc__toolbar" aria-label="<?= e($vc('top_controls')) ?>">
    <span class="cs-vc__section-label"><?= e($vc('top_controls')) ?></span>
    <button type="button" disabled><?= e($vc('theme_selector')) ?></button>
    <button type="button" disabled><?= e($vc('mode_selector')) ?></button>
    <button type="button" disabled><?= e($vc('density_selector')) ?></button>
    <button type="button" disabled><?= e($vc('preview_type_selector')) ?></button>
    <button type="button" disabled><?= e($vc('save_draft')) ?></button>
    <button type="button" disabled><?= e($vc('apply')) ?></button>
  </nav>

  <section class="cs-vc__lifecycle" aria-label="<?= e($vc('lifecycle_title')) ?>">
    <div class="cs-vc__lifecycle-head">
      <h3><?= e($vc('lifecycle_title')) ?></h3>
      <p><?= e($vc('lifecycle_hint')) ?></p>
    </div>
    <dl class="cs-vc__draft-status-grid">
      <div>
        <dt><?= e($vc('socket_local_draft_status_label')) ?></dt>
        <dd id="cs-vc-lifecycle-draft-status"><?= e($vc('socket_local_draft_empty')) ?></dd>
      </div>
      <div>
        <dt><?= e($vc('socket_runtime_not_applied_label')) ?></dt>
        <dd><?= e($vc('socket_runtime_not_applied_value')) ?></dd>
      </div>
      <div>
        <dt><?= e($vc('socket_apply_disabled_label')) ?></dt>
        <dd><?= e($vc('socket_apply_disabled_value')) ?></dd>
      </div>
      <div>
        <dt><?= e($vc('socket_storage_label')) ?></dt>
        <dd><?= e($vc('socket_storage_studio_only')) ?></dd>
      </div>
    </dl>
    <ol class="cs-vc__lifecycle-list">
      <li>
        <span><?= e($vc('lifecycle_draft_label')) ?></span>
        <strong><?= e($vc('lifecycle_draft_value')) ?></strong>
      </li>
      <li>
        <span><?= e($vc('lifecycle_preview_label')) ?></span>
        <strong><?= e($vc('lifecycle_preview_value')) ?></strong>
      </li>
      <li>
        <span><?= e($vc('lifecycle_diff_label')) ?></span>
        <strong><?= e($vc('lifecycle_diff_value')) ?></strong>
      </li>
      <li>
        <span><?= e($vc('lifecycle_approval_label')) ?></span>
        <strong><?= e($vc('lifecycle_approval_value')) ?></strong>
      </li>
      <li>
        <span><?= e($vc('lifecycle_apply_label')) ?></span>
        <strong><?= e($vc('lifecycle_apply_value')) ?></strong>
      </li>
      <li>
        <span><?= e($vc('lifecycle_rollback_label')) ?></span>
        <strong><?= e($vc('lifecycle_rollback_value')) ?></strong>
      </li>
    </ol>
  </section>

  <div class="cs-vc__workspace" aria-label="<?= e($vc('simple_mode_note')) ?>">
    <aside class="cs-vc__left-panel">
      <section class="cs-vc__panel cs-vc__preview-chooser">
        <h3><?= e($vc('fixture_selector')) ?></h3>
        <label class="cs-vc__fixture-selector" for="cs-vc-fixture-selector">
          <span><?= e($vc('fixture_selector')) ?></span>
          <?php if ($vcFixtureSelectorItems === []): ?>
            <select id="cs-vc-fixture-selector" disabled>
              <option value=""><?= e($vc('fixture_selector_empty')) ?></option>
            </select>
          <?php else: ?>
            <select id="cs-vc-fixture-selector">
              <?php foreach ($vcFixtureSelectorItems as $fixtureItem): ?>
                <option value="<?= e((string)($fixtureItem['id'] ?? '')) ?>"><?= e((string)($fixtureItem['name'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </label>

        <div class="cs-vc__preview-list" aria-label="<?= e($vc('preview_types')) ?>">
          <h4><?= e($vc('preview_types')) ?></h4>
          <?php if ($vcFixtureSelectorItems === []): ?>
            <p><?= e($vc('fixture_selector_empty')) ?></p>
          <?php else: ?>
            <?php foreach ($vcFixtureSelectorItems as $fixtureIndex => $fixtureItem): ?>
              <div class="cs-vc__preview-option<?= $fixtureIndex === 0 ? ' is-active' : '' ?>" data-fixture-card-id="<?= e((string)($fixtureItem['id'] ?? '')) ?>">
                <strong><?= e((string)($fixtureItem['name'] ?? '')) ?></strong>
                <?php if (trim((string)($fixtureItem['description'] ?? '')) !== ''): ?>
                  <span><?= e((string)($fixtureItem['description'] ?? '')) ?></span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </aside>

    <main class="cs-vc__canvas" aria-label="<?= e($vc('preview_canvas')) ?>">
      <article class="cs-vc__fixture-summary" aria-live="polite">
        <span class="cs-vc__section-title"><?= e($vc('preview_canvas')) ?></span>
        <h3 id="cs-vc-fixture-title"><?= e((string)($vcDefaultFixture['name'] ?? $vc('fixture_selected_title'))) ?></h3>
        <p id="cs-vc-fixture-description"><?= e((string)($vcDefaultFixture['description'] ?? $vc('fixture_selected_description'))) ?></p>
      </article>

      <?php require __DIR__ . '/preview-canvas.php'; ?>
    </main>

    <aside class="cs-vc__inspector" aria-label="<?= e($vc('socket_detail_title')) ?>">
      <section class="cs-vc__panel cs-vc__simple-inspector" aria-live="polite">
        <h3><?= e($vc('socket_detail_title')) ?></h3>
        <dl class="cs-vc__socket-summary-list">
          <dt><?= e($vc('socket_field_label')) ?></dt>
          <dd id="cs-vc-socket-detail-label"><?= e($vc('socket_detail_empty')) ?></dd>

          <dt><?= e($vc('socket_field_description')) ?></dt>
          <dd id="cs-vc-socket-detail-description">-</dd>
        </dl>

        <section class="cs-vc__socket-mode-section cs-vc__socket-mode-section--simple" aria-label="<?= e($vc('socket_simple_mode_title')) ?>">
          <h4><?= e($vc('socket_simple_mode_title')) ?></h4>
          <div id="cs-vc-socket-detail-simple-controls" class="cs-vc__simple-control-chips" aria-live="polite">
            <button type="button" disabled><?= e($vc('socket_detail_empty')) ?></button>
          </div>
        </section>

        <section class="cs-vc__draft-diff" aria-label="<?= e($vc('draft_diff_preview_title')) ?>">
          <h4><?= e($vc('draft_diff_preview_title')) ?></h4>
          <p id="cs-vc-draft-diff-summary"><?= e($vc('draft_diff_no_change')) ?></p>
          <dl class="cs-vc__draft-diff-list">
            <dt><?= e($vc('draft_diff_default_label')) ?></dt>
            <dd id="cs-vc-draft-diff-default">soft</dd>

            <dt><?= e($vc('draft_diff_current_label')) ?></dt>
            <dd id="cs-vc-draft-diff-current"><?= e($vc('socket_current_value_not_connected')) ?></dd>

            <dt><?= e($vc('draft_diff_proposed_label')) ?></dt>
            <dd id="cs-vc-draft-diff-proposed"><?= e($vc('socket_proposed_value_none')) ?></dd>

            <dt><?= e($vc('draft_diff_impact_label')) ?></dt>
            <dd id="cs-vc-draft-diff-impact"><?= e($vc('draft_diff_impact_preview_only')) ?></dd>

            <dt><?= e($vc('draft_diff_apply_label')) ?></dt>
            <dd id="cs-vc-draft-diff-apply"><?= e($vc('draft_diff_apply_disabled')) ?></dd>
          </dl>
        </section>

        <section class="cs-vc__value-safety" aria-label="<?= e($vc('socket_value_safety_title')) ?>">
          <h4><?= e($vc('socket_value_safety_title')) ?></h4>
          <p id="cs-vc-local-draft-message"><?= e($vc('socket_local_draft_empty')) ?></p>
          <dl class="cs-vc__value-safety-list">
            <dt><?= e($vc('socket_local_draft_status_label')) ?></dt>
            <dd id="cs-vc-socket-local-draft-status"><?= e($vc('socket_local_draft_empty')) ?></dd>

            <dt><?= e($vc('socket_runtime_not_applied_label')) ?></dt>
            <dd><?= e($vc('socket_runtime_not_applied_value')) ?></dd>

            <dt><?= e($vc('socket_apply_disabled_label')) ?></dt>
            <dd><?= e($vc('socket_apply_disabled_note')) ?></dd>

            <dt><?= e($vc('socket_storage_label')) ?></dt>
            <dd><?= e($vc('socket_storage_studio_only')) ?></dd>

            <dt><?= e($vc('socket_field_default_value')) ?></dt>
            <dd id="cs-vc-socket-safety-default-value">-</dd>

            <dt><?= e($vc('socket_current_value_label')) ?></dt>
            <dd id="cs-vc-socket-detail-current-value"><?= e($vc('socket_current_value_not_connected')) ?></dd>

            <dt><?= e($vc('socket_proposed_value_label')) ?></dt>
            <dd id="cs-vc-socket-safety-proposed-value"><?= e($vc('socket_proposed_value_none')) ?></dd>

            <dt><?= e($vc('socket_source_label')) ?></dt>
            <dd><?= e($vc('socket_source_value')) ?></dd>

            <dt><?= e($vc('socket_runtime_status_label')) ?></dt>
            <dd><?= e($vc('socket_runtime_status_value')) ?></dd>

            <dt><?= e($vc('socket_reset_status_label')) ?></dt>
            <dd id="cs-vc-socket-reset-status"><?= e($vc('socket_reset_status_disabled')) ?></dd>
          </dl>
          <div class="cs-vc__value-safety-actions" aria-label="<?= e($vc('socket_reset_status_label')) ?>">
            <button type="button" id="cs-vc-reset-control" disabled><?= e($vc('socket_reset_control')) ?></button>
            <button type="button" id="cs-vc-reset-section" disabled><?= e($vc('socket_reset_section')) ?></button>
            <button type="button" id="cs-vc-discard-draft" disabled><?= e($vc('socket_discard_draft')) ?></button>
            <button type="button" id="cs-vc-restore-last-approved" disabled><?= e($vc('socket_restore_last_approved')) ?></button>
          </div>
        </section>

        <section class="cs-vc__readiness" aria-label="<?= e($vc('readiness_title')) ?>">
          <h4><?= e($vc('readiness_title')) ?></h4>
          <p><?= e($vc('readiness_hint')) ?></p>
          <ul class="cs-vc__readiness-list">
            <li class="cs-vc__readiness-item" data-check-key="socket_allowed">
              <span class="cs-vc__readiness-icon" aria-hidden="true">-</span>
              <?= e($vc('readiness_socket_pass')) ?>
            </li>
            <li class="cs-vc__readiness-item" data-check-key="proposed_value_exists">
              <span class="cs-vc__readiness-icon" aria-hidden="true">-</span>
              <?= e($vc('readiness_proposed_value')) ?>
            </li>
            <li class="cs-vc__readiness-item" data-check-key="proposed_value_allowed">
              <span class="cs-vc__readiness-icon" aria-hidden="true">-</span>
              <?= e($vc('readiness_allowed_value')) ?>
            </li>
            <li class="cs-vc__readiness-item" data-check-key="studio_local_draft">
              <span class="cs-vc__readiness-icon" aria-hidden="true">-</span>
              <?= e($vc('readiness_storage')) ?>
            </li>
            <li class="cs-vc__readiness-item" data-check-key="draft_shape_valid">
              <span class="cs-vc__readiness-icon" aria-hidden="true">-</span>
              Draft shape valid
            </li>
            <li class="cs-vc__readiness-item" data-check-key="diff_exists">
              <span class="cs-vc__readiness-icon" aria-hidden="true">-</span>
              <?= e($vc('readiness_diff')) ?>
            </li>
            <li class="cs-vc__readiness-item" data-check-key="apply_disabled">
              <span class="cs-vc__readiness-icon" aria-hidden="true">-</span>
              <?= e($vc('readiness_apply')) ?>
            </li>
            <li class="cs-vc__readiness-item" data-check-key="runtime_untouched">
              <span class="cs-vc__readiness-icon" aria-hidden="true">-</span>
              <?= e($vc('readiness_runtime')) ?>
            </li>
          </ul>
          <div class="cs-vc__readiness-request">
            <span class="cs-vc__readiness-request-label" id="cs-vc-readiness-status"><?= e($vc('readiness_request_disabled')) ?></span>
          </div>
          <div class="cs-vc__readiness-actions">
            <button type="button" id="cs-vc-create-approval-request" class="cs-vc__approval-request-btn" disabled><?= e($vc('approval_request_create')) ?></button>
          </div>
          <div class="cs-vc__readiness-recheck">
            <button type="button" id="cs-vc-recheck-readiness" class="cs-vc__recheck-btn"><?= e($vc('readiness_recheck')) ?></button>
          </div>
          <div class="cs-vc__readiness-doc">
            <a href="/docs/visual-customizer-request-validation-gate.md" target="_blank" rel="noopener"><?= e($vc('readiness_gate_doc')) ?></a>
          </div>
        </section>

<?php
$pendingRequests = isset($vcModel['pending_approval_requests']) && is_array($vcModel['pending_approval_requests'])
    ? $vcModel['pending_approval_requests']
    : [];
?>
        <section class="cs-vc__pending-requests" aria-label="<?= e($vc('approval_requests_title')) ?>">
          <h4><?= e($vc('approval_requests_title')) ?></h4>
          <?php if ($pendingRequests === []): ?>
            <p class="cs-vc__pending-requests-empty"><?= e($vc('approval_requests_empty')) ?></p>
          <?php else: ?>
            <div class="cs-vc__pending-requests-list">
              <?php foreach ($pendingRequests as $reqIndex => $req): ?>
                <div class="cs-vc__pending-request-item">
                  <dl>
                    <dt><?= e($vc('approval_requests_request_id')) ?></dt>
                    <dd><a href="/apps/studio/tools/customization-studio/visual-customizer/request?request_id=<?= e((string)($req['request_id'] ?? '')) ?>"><?= e((string)($req['request_id'] ?? '')) ?></a></dd>

                    <dt><?= e($vc('approval_requests_requested_by')) ?></dt>
                    <dd><?= e((string)($req['requested_by'] ?? '')) ?></dd>

                    <dt><?= e($vc('approval_requests_socket')) ?></dt>
                    <dd><?= e((string)($req['selected_socket_id'] ?? '')) ?></dd>

                    <dt><?= e($vc('approval_requests_proposed_value')) ?></dt>
                    <dd><?= e((string)($req['proposed_value'] ?? '')) ?></dd>

                    <dt><?= e($vc('approval_requests_diff')) ?></dt>
                    <dd><?= e((string)($req['diff_summary'] ?? '')) ?></dd>

                    <dt><?= e($vc('approval_requests_status')) ?></dt>
                    <dd><?= e((string)($req['status'] ?? '')) ?></dd>

                    <dt><?= e($vc('approval_requests_created')) ?></dt>
                    <dd><?= e((string)($req['created_at'] ?? '')) ?></dd>

                    <dt><?= e($vc('approval_requests_runtime')) ?></dt>
                    <dd><?= e($vc('approval_requests_runtime_not_applied')) ?></dd>
                  </dl>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <div class="cs-vc__readonly-pill" id="cs-vc-readonly-pill"><?= e($vc('socket_not_editable_yet')) ?></div>
      </section>
    </aside>
  </div>

  <footer class="cs-vc__status" aria-label="<?= e($vc('bottom_status')) ?>">
    <h3 class="cs-vc__status-heading"><?= e($vc('bottom_status')) ?></h3>
    <?php require __DIR__ . '/disabled-actions.php'; ?>
  </footer>

  <details class="cs-vc__advanced">
    <summary><?= e($vc('advanced_details_summary')) ?></summary>
    <div class="cs-vc__advanced-body">
      <p class="cs-vc__advanced-hint"><?= e($vc('advanced_details_hint')) ?></p>

      <section class="cs-vc__fixture-summary" aria-live="polite">
        <h3><?= e($vc('outline_title')) ?></h3>
        <p><?= e($vc('outline_hint')) ?></p>
        <h4><?= e($vc('outline_sections')) ?></h4>
        <ul id="cs-vc-fixture-outline-list">
          <?php
            $vcDefaultOutlineSections = isset($vcDefaultFixture['outline_sections']) && is_array($vcDefaultFixture['outline_sections'])
              ? array_values(array_filter($vcDefaultFixture['outline_sections'], 'is_string'))
              : [];
          ?>
          <?php if ($vcDefaultOutlineSections === []): ?>
            <li><?= e($vc('outline_none')) ?></li>
          <?php else: ?>
            <?php foreach ($vcDefaultOutlineSections as $outlineSection): ?>
              <li><?= e($outlineSection) ?></li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </section>

      <section class="cs-vc__fixture-summary" aria-live="polite">
        <label class="cs-vc__fixture-selector" for="cs-vc-socket-selector">
          <span><?= e($vc('socket_catalog_selector')) ?></span>
          <?php if ($vcSocketCatalogSelectorItems === []): ?>
            <select id="cs-vc-socket-selector" disabled>
              <option value=""><?= e($vc('socket_catalog_selector_empty')) ?></option>
            </select>
          <?php else: ?>
            <select id="cs-vc-socket-selector">
              <?php foreach ($vcSocketCatalogSelectorItems as $catalogItem): ?>
                <option value="<?= e((string)($catalogItem['id'] ?? '')) ?>"<?= (string)($catalogItem['id'] ?? '') === $vcDefaultCatalogId ? ' selected' : '' ?>><?= e((string)($catalogItem['name'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </label>

        <h3 id="cs-vc-socket-title"><?= e((string)($vcDefaultSocketCatalog['name'] ?? $vc('socket_catalog_selected_title'))) ?></h3>
        <p id="cs-vc-socket-description"><?= e((string)($vcDefaultSocketCatalog['description'] ?? $vc('socket_catalog_selected_description'))) ?></p>
        <p><strong><?= e($vc('socket_count')) ?>:</strong> <span id="cs-vc-socket-count"><?= e((string)($vcDefaultSocketCatalog['socket_count'] ?? 0)) ?></span></p>
        <h4><?= e($vc('socket_labels')) ?></h4>
        <ul id="cs-vc-socket-list">
          <?php
            $vcDefaultSocketRows = isset($vcDefaultSocketCatalog['sockets']) && is_array($vcDefaultSocketCatalog['sockets'])
              ? array_values(array_filter($vcDefaultSocketCatalog['sockets'], 'is_array'))
              : [];
          ?>
          <?php if ($vcDefaultSocketRows === []): ?>
            <li><?= e($vc('socket_catalog_selector_empty')) ?></li>
          <?php else: ?>
            <?php foreach ($vcDefaultSocketRows as $socketRow): ?>
              <li>
                <button
                  type="button"
                  class="cs-vc__socket-item"
                  data-socket-id="<?= e((string)($socketRow['id'] ?? '')) ?>"
                  data-socket-label="<?= e((string)($socketRow['label'] ?? '')) ?>"
                >
                  <?= e((string)($socketRow['label'] ?? '')) ?> (<code><?= e((string)($socketRow['id'] ?? '')) ?></code>)
                </button>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>

        <section class="cs-vc__socket-mode-section cs-vc__socket-mode-section--advanced" aria-label="<?= e($vc('socket_advanced_token_title')) ?>">
          <h4><?= e($vc('socket_advanced_token_title')) ?></h4>
          <p><?= e($vc('socket_advanced_token_helper')) ?></p>
          <dl>
            <dt><?= e($vc('socket_field_advanced_token')) ?></dt>
            <dd id="cs-vc-socket-detail-advanced-token"><?= e($vc('socket_no_advanced_token')) ?></dd>

            <dt><?= e($vc('socket_field_value_type')) ?></dt>
            <dd id="cs-vc-socket-detail-value-type">-</dd>

            <dt><?= e($vc('socket_field_default_value')) ?></dt>
            <dd id="cs-vc-socket-detail-default-value">-</dd>

            <dt><?= e($vc('socket_field_scope')) ?></dt>
            <dd id="cs-vc-socket-detail-scope">-</dd>

            <dt><?= e($vc('socket_field_category')) ?></dt>
            <dd id="cs-vc-socket-detail-category">-</dd>

            <dt><?= e($vc('socket_runtime_status_label')) ?></dt>
            <dd><?= e($vc('socket_runtime_status_value')) ?></dd>
          </dl>
        </section>

        <section class="cs-vc__socket-mode-section cs-vc__socket-mode-section--advanced" aria-label="<?= e($vc('draft_diff_preview_title')) ?>">
          <h4><?= e($vc('draft_diff_preview_title')) ?></h4>
          <dl>
            <dt><?= e($vc('draft_diff_socket_label')) ?></dt>
            <dd id="cs-vc-draft-diff-socket">radius.scale</dd>

            <dt><?= e($vc('draft_diff_token_label')) ?></dt>
            <dd id="cs-vc-draft-diff-token">--radius-scale</dd>

            <dt><?= e($vc('draft_diff_source_label')) ?></dt>
            <dd id="cs-vc-draft-diff-source"><?= e($vc('draft_diff_source_studio_local')) ?></dd>

            <dt><?= e($vc('draft_diff_runtime_label')) ?></dt>
            <dd id="cs-vc-draft-diff-runtime"><?= e($vc('draft_diff_runtime_not_applied')) ?></dd>
          </dl>
        </section>

        <section class="cs-vc__relationship-view" aria-live="polite">
          <h4><?= e($vc('relationship_title')) ?></h4>
          <p><?= e($vc('relationship_hint')) ?></p>
          <div class="cs-vc__relationship-grid">
            <div>
              <h5><?= e($vc('relationship_fixture_to_catalogs')) ?></h5>
              <ul id="cs-vc-rel-fixture-to-catalogs">
                <li><?= e($vc('relationship_none')) ?></li>
              </ul>
            </div>
            <div>
              <h5><?= e($vc('relationship_catalog_to_fixtures')) ?></h5>
              <ul id="cs-vc-rel-catalog-to-fixtures">
                <li><?= e($vc('relationship_none')) ?></li>
              </ul>
            </div>
          </div>
        </section>
      </section>

      <section class="cs-vc__socket-browser" aria-label="<?= e($vc('socket_browse_title')) ?>">
        <div class="cs-vc__socket-browser-head">
          <div>
            <h3><?= e($vc('socket_browse_title')) ?></h3>
            <p class="cs-vc__socket-browser-hint"><?= e($vc('socket_browse_hint')) ?></p>
          </div>
          <p class="cs-vc__socket-browser-status"><?= e($vc('socket_browse_status_banner')) ?></p>
        </div>

        <dl class="cs-vc__socket-browser-summary" aria-label="<?= e($vc('socket_browse_total')) ?>">
          <div>
            <dt><?= e($vc('socket_browse_visible')) ?></dt>
            <dd><span id="cs-vc-socket-browser-count-val">0</span> <?= e($vc('socket_browse_count')) ?></dd>
          </div>
          <div>
            <dt><?= e($vc('socket_browse_total')) ?></dt>
            <dd><span id="cs-vc-socket-browser-total-val">0</span> <?= e($vc('socket_browse_count')) ?></dd>
          </div>
          <div>
            <dt><?= e($vc('socket_browse_catalog_total')) ?></dt>
            <dd><span id="cs-vc-socket-browser-catalog-total">0</span></dd>
          </div>
          <div>
            <dt><?= e($vc('socket_browse_category_total')) ?></dt>
            <dd><span id="cs-vc-socket-browser-category-total">0</span></dd>
          </div>
          <div>
            <dt><?= e($vc('socket_browse_type_total')) ?></dt>
            <dd><span id="cs-vc-socket-browser-type-total">0</span></dd>
          </div>
        </dl>

        <div class="cs-vc__socket-browser-controls">
          <label>
            <span class="sr-only"><?= e($vc('socket_browse_search_label')) ?></span>
            <input type="search" id="cs-vc-socket-browser-search" placeholder="<?= e($vc('socket_browse_search')) ?>">
          </label>
          <select id="cs-vc-socket-browser-catalog"><option value=""><?= e($vc('socket_browse_filter_catalog')) ?></option></select>
          <select id="cs-vc-socket-browser-category"><option value=""><?= e($vc('socket_browse_filter_category')) ?></option></select>
          <select id="cs-vc-socket-browser-type"><option value=""><?= e($vc('socket_browse_filter_type')) ?></option></select>
        </div>

        <div class="cs-vc__socket-browser-layout">
          <div class="cs-vc__socket-browser-list-pane">
            <ul id="cs-vc-socket-browser-list" role="listbox" aria-label="<?= e($vc('socket_browse_title')) ?>">
              <li role="option" aria-selected="false"><?= e($vc('socket_browse_none')) ?></li>
            </ul>
          </div>
          <div class="cs-vc__socket-browser-detail-pane" id="cs-vc-socket-browser-detail">
            <h4><?= e($vc('socket_browse_detail_title')) ?></h4>
            <p id="cs-vc-socket-browser-detail-body"><?= e($vc('socket_browse_detail_none')) ?></p>
          </div>
        </div>
      </section>

      <?php require __DIR__ . '/inspector-panel.php'; ?>
    </div>
  </details>

  <script>
window.CustomizationStudioVisualCustomizerFixtureMetadata = <?= $vcFixturePayloadJson ?>;
window.CustomizationStudioVisualCustomizerAllSockets = <?= $vcAllSocketsJson ?>;
<?php require __DIR__ . '/../assets/visual-customizer.js'; ?>
  </script>
</section>
