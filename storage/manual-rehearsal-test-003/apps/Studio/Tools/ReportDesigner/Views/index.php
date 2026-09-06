<?php
$tt = static function (string $key): string {
  return t($key);
};
$model = isset($reportDesignerModel) && is_array($reportDesignerModel)
    ? $reportDesignerModel
    : [];

$workspace = isset($_GET['workspace']) ? trim((string)$_GET['workspace']) : 'overview';
$sourceCatalog = isset($model['source_catalog']) && is_array($model['source_catalog'])
    ? $model['source_catalog']
    : [];
$discoveredSuites = isset($sourceCatalog['suites']) && is_array($sourceCatalog['suites'])
    ? $sourceCatalog['suites']
    : [];
$discoveryDiagnostics = isset($sourceCatalog['diagnostics']) && is_array($sourceCatalog['diagnostics'])
    ? $sourceCatalog['diagnostics']
    : [];
$orgMetadata = isset($model['org_metadata']) && is_array($model['org_metadata'])
    ? $model['org_metadata']
    : [];
$savedReports = isset($model['saved_reports']) && is_array($model['saved_reports'])
    ? $model['saved_reports']
    : [];
$definitionDiagnostics = isset($model['definition_diagnostics']) && is_array($model['definition_diagnostics'])
    ? $model['definition_diagnostics']
    : [];
$reportDesignerFlash = isset($model['flash']) && is_array($model['flash']) ? $model['flash'] : [];
$csrf = trim((string)($model['csrf'] ?? ''));

$rd = static function (string $key): string {
    $lang = function_exists('current_lang') ? current_lang() : 'en';
    $dict = [
        'en' => [
            'title' => 'Report Designer',
            'subtitle' => 'Governed Studio design workbench for platform-owned report artifacts across business apps and modules.',
            'readonly' => 'Definition authoring',
            'safety' => 'This surface saves report definitions only. It does not execute reports or query report rows.',
            'overview_title' => 'Overview',
            'overview_purpose' => 'Report Designer is a Studio-owned governed tool for authoring platform-owned report presentation artifacts.',
            'overview_note' => 'Reports are platform-owned presentation artifacts. Business apps and modules will later provide report sources through provider interfaces.',
            'readiness_title' => 'Platform Readiness',
            'readiness_note' => 'Mock readiness indicators for future report source providers.',
            'readiness_platform_engine' => 'Platform Engine',
            'readiness_platform_status' => 'Planned',
            'readiness_platform_desc' => 'Future platform/Reports engine will provide report compilation and runtime execution.',
            'readiness_providers' => 'Report Source Providers',
            'readiness_providers_status' => 'Not Started',
            'readiness_providers_desc' => 'Business apps/modules will expose report sources dynamically through provider contracts.',
            'readiness_definitions' => 'Report Definitions',
            'readiness_definitions_status' => 'Not Started',
            'readiness_definitions_desc' => 'System-owned report definitions will be stored under platform/Reports/Definitions/.',
            'build_title' => 'Build Report',
            'build_note' => 'Design report structure from read-only discovered metadata and placeholder preview rows.',
            'reports_title' => 'Reports',
            'open_report_label' => 'Open Report',
            'open_report_placeholder' => 'Select a saved report...',
            'no_saved_reports' => 'No saved reports found.',
            'create_new_report' => 'Create New Report',
            'save_report' => 'Save Definition',
            'save_report_title' => 'Save Definition',
            'save_report_name' => 'Report Name',
            'save_report_description' => 'Description',
            'save_report_advanced' => 'Advanced',
            'save_report_key' => 'Report Key',
            'save_report_key_help' => 'Leave empty to generate from the report name.',
            'save_report_success' => 'Definition saved successfully.',
            'save_report_failed' => 'Definition could not be saved.',
            'definition_status_label' => 'Definition Status',
            'definition_status_unsaved' => 'Unsaved',
            'definition_status_modified' => 'Modified',
            'definition_status_saved' => 'Saved',
            'save_eligibility_guidance' => 'Select at least one source and one field before saving.',
            'save_payload_review' => 'Definition Payload Review',
            'save_payload_sources' => 'Sources',
            'save_payload_fields' => 'Fields',
            'save_payload_context' => 'Context Inputs',
            'save_payload_layout' => 'Layout',
            'save_payload_presentation' => 'Presentation',
            'save_payload_preview' => 'Preview Defaults',
            'wizard_build' => 'Build',
            'wizard_presentation' => 'Presentation',
            'wizard_preview' => 'Preview',
            'wizard_save' => 'Save Definition',
            'wizard_continue_presentation' => 'Continue to Presentation',
            'wizard_back_build' => 'Back to Build',
            'wizard_continue_preview' => 'Continue to Preview',
            'wizard_back_presentation' => 'Back to Presentation',
            'wizard_continue_save' => 'Continue to Save Definition',
            'wizard_back_preview' => 'Back to Preview',
            'save_workspace_title' => 'Save Definition',
            'save_workspace_note' => 'Name and save the current report design as a Platform-owned definition.',
            'fields_context_title' => 'Fields &amp; Context Inputs',
            'data_source_title' => 'Data Source',
            'source_model_title' => 'Bootstrap DB Discovery Mode',
            'source_model_current' => 'Current sources are discovered from installed app/module metadata and database schema. This is a temporary bridge until business apps publish formal report-source providers.',
            'source_model_safety' => 'Schema metadata only: no report rows, joins, execution, or export.',
            'suite_label' => 'Suite/App',
            'suite_placeholder' => 'Choose a Suite/App',
            'modules_label' => 'Modules/Sources',
            'modules_help' => 'Select one or more discovered modules or sources.',
            'source_available_title' => 'Available Sources',
            'source_available_empty' => 'No sources available.',
            'source_selected_title' => 'Selected Sources',
            'source_selected_empty' => 'No sources selected.',
            'source_search_placeholder' => 'Search sources by name...',
            'discovery_empty' => 'No reportable database sources were discovered.',
            'discovery_diagnostics' => 'Discovery diagnostics',
            'columns_note' => 'Each selected source owns its Fields and Context Inputs. Fields are discovered database columns. Context inputs are runtime parameters the report can accept — values are chosen later in Preview. No report rows are queried here.',
            'columns_empty' => 'Select at least one module/source to inspect discovered fields and context inputs.',
            'columns_selected' => 'Selected Fields',
            'columns_selected_empty' => 'No fields selected.',
            'column_db_type' => 'DB type',
            'columns_select_all' => 'Select all',
            'columns_clear' => 'Clear',
            'columns_selected_count' => 'selected',
            'source_fields' => 'Fields',
            'source_business_fields' => 'Business Fields',
            'source_advanced_fields' => 'Advanced Fields',
            'source_business_fields_empty' => 'No business fields discovered.',
            'source_context_inputs' => 'Context Inputs',
            'filter_date_range' => 'Date Range',
            'filter_status' => 'Status Filter',
            'filter_category' => 'Category Filter',
            'layout_title' => 'Layout',
            'layout_note' => 'Configure selected columns for the report definition. No report rows are queried.',
            'layout_columns' => 'Layout Columns',
            'layout_columns_empty' => 'Select fields in Step 3 to compose layout.',
            'layout_display_label' => 'Display label',
            'layout_visible' => 'Visible',
            'layout_alignment' => 'Alignment',
            'layout_width' => 'Width',
            'layout_sort' => 'Sort',
            'layout_summary' => 'Summary',
            'layout_move_up' => 'Move up',
            'layout_move_down' => 'Move down',
            'layout_remove' => 'Remove from layout',
            'layout_grouping' => 'Grouping',
            'layout_no_grouping' => 'No grouping',
            'live_preview_title' => 'Design Summary',
            'live_preview_note' => 'Compact summary of the current in-memory design. Continue to Preview for the full report surface.',
            'live_preview_columns' => 'Visible Columns',
            'live_preview_grouping' => 'Grouping',
            'live_preview_filters' => 'Context Inputs',
            'preview_title' => 'Preview',
            'preview_note' => 'Preview uses report design metadata and placeholder rows only.',
            'preview_no_data' => 'No layout columns selected.',
            'preview_detail' => 'Select and configure columns in Build to render an in-memory preview.',
            'preview_summary_label' => 'Summary',
            'preview_actions' => 'Preview Actions',
            'preview_print' => 'Print Preview',
            'preview_generated' => 'Generated Preview',
            'preview_suite' => 'Suite/App',
            'preview_sources' => 'Selected Modules/Sources',
            'preview_filters' => 'Active Filters',
            'preview_filters_empty' => 'No active filters.',
            'preview_only' => 'Preview Only',
            'preview_generated_by' => 'Generated from Report Designer',
            'preview_page' => 'Page 1 of 1',
            'preview_untitled' => 'Untitled Report',
            'preview_unassigned' => 'Not assigned',
            'preview_state_title' => 'No in-memory preview state',
            'preview_state_note' => 'Open Build Report, configure the design, then continue to Preview.',
            'governance_title' => 'Governance',
            'governance_note' => 'Architecture notes and planned implementation.',
            'governance_architecture' => 'Planned Architecture',
            'governance_studio' => 'Studio Tool',
            'governance_platform' => 'Platform Engine',
            'governance_business' => 'Business Apps',
            'governance_studio_desc' => 'Report Designer — governed authoring workflow',
            'governance_platform_desc' => 'platform/Reports — compilation and runtime engine',
            'governance_business_desc' => 'Report source providers — expose data from app/module resources',
            'governance_flow' => 'Data Flow',
            'governance_flow_desc' => 'Business apps → report sources → platform engine → compiled reports → runtime consumption',
            'governance_status' => 'Implementation Status',
            'governance_status_phase' => 'Phase 6: Definition Persistence',
            'governance_status_desc' => 'Platform-owned report definitions can be saved and loaded. Runtime execution remains unavailable.',
            'definition_diagnostics_title' => 'Report Definition Diagnostics',
            'definition_count' => 'Definition Count',
            'definition_schema_status' => 'Schema version status',
            'definition_duplicate_keys' => 'Duplicate Keys',
            'definition_validation_failures' => 'Validation Failures',
            'definition_none' => 'None',
            'presentation_title' => 'Presentation',
            'presentation_note' => 'Configure report visual presentation options stored with the report definition.',
            'presentation_format_title' => 'Output Format',
            'presentation_format_table' => 'Table',
            'presentation_format_card' => 'Card Grid',
            'presentation_format_list' => 'List',
            'presentation_page_title' => 'Page Settings',
            'presentation_page_size' => 'Page Size',
            'presentation_page_a4' => 'A4',
            'presentation_page_letter' => 'Letter',
            'presentation_page_legal' => 'Legal',
            'presentation_orientation' => 'Orientation',
            'presentation_portrait' => 'Portrait',
            'presentation_landscape' => 'Landscape',
            'presentation_header_title' => 'Page Header',
            'presentation_header_placeholder' => 'Optional text printed at the top of each page',
            'presentation_footer_title' => 'Page Footer',
            'presentation_footer_placeholder' => 'Optional text printed at the bottom of each page',
            'presentation_state_title' => 'Presentation requires an active design session',
            'presentation_state_note' => 'Open Build Report and configure report fields before opening Presentation.',
            'presentation_header_controls_title' => 'Header Controls',
            'presentation_header_show_logo' => 'Show Company Logo',
            'presentation_header_show_company_name' => 'Show Company Name',
            'presentation_header_show_report_title' => 'Show Report Title',
            'presentation_header_show_description' => 'Show Description',
            'presentation_header_show_generated_date' => 'Show Generated Date',
            'presentation_header_show_generated_by' => 'Show Generated By',
            'presentation_header_show_branch' => 'Show Branch',
            'presentation_header_show_fiscal_period' => 'Show Fiscal Period',
            'presentation_header_show_active_filters' => 'Show Active Filters',
            'presentation_footer_controls_title' => 'Footer Controls',
            'presentation_footer_show_page_number' => 'Show Page Number Area',
            'presentation_footer_show_generated_timestamp' => 'Show Generated Timestamp',
            'presentation_footer_show_confidential_notice' => 'Show Confidential Notice',
            'presentation_footer_show_signature_area' => 'Show Signature Area',
            'presentation_footer_show_system_footer' => 'Show System Footer',
            'presentation_page_controls_title' => 'Page Controls',
            'presentation_margin_top' => 'Margin Top',
            'presentation_margin_bottom' => 'Margin Bottom',
            'presentation_margin_left' => 'Margin Left',
            'presentation_margin_right' => 'Margin Right',
            'presentation_header_height' => 'Header Height',
            'presentation_footer_height' => 'Footer Height',
            'presentation_org_metadata_title' => 'Organization Metadata (Read-Only)',
            'presentation_org_company' => 'Company',
            'presentation_org_branch' => 'Branch',
            'presentation_org_fiscal_period' => 'Fiscal Period',
            'presentation_org_current_user' => 'Current User',
            'presentation_metadata_not_available' => 'Not available',
            'filter_group_dates' => 'Dates',
            'filter_group_numbers' => 'Numbers',
            'filter_group_text' => 'Text',
            'filter_group_statuses' => 'Statuses',
            'filter_activate' => 'Activate',
            'preview_print_button' => 'Print Preview',
            'preview_no_design_state' => 'Preview requires an active design session',
            'preview_no_design_state_note' => 'Open Build Report and select report fields before opening Preview.',
            'preview_context_title' => 'Report Context',
            'preview_context_note' => 'Preview-only runtime values for the future report filter model. No report rows are queried.',
            'preview_context_date_range' => 'Date Range',
            'preview_context_current_month' => 'Current Month',
            'preview_context_custom_range' => 'Custom Range',
            'preview_context_change' => 'Change Context',
            'preview_context_editor_title' => 'Context Editor',
            'preview_context_from' => 'From',
            'preview_context_to' => 'To',
            'preview_context_include_print' => 'Include context summary when printing',
            'preview_context_value_in_preview' => 'Runtime value is selected in Preview.',
        ],
        'ne' => [
            'title' => 'रिपोर्ट डिजाइनर',
            'subtitle' => 'व्यापार एप्स र मोड्यूलहरूमा प्लेटफर्म-स्वामित्वक रिपोर्ट कलाकृतिहरूको लागि शासित स्टुडियो डिजाइन वर्कबेन्च।',
            'readonly' => 'परिभाषा लेखन',
            'safety' => 'यो सतहले रिपोर्ट परिभाषा मात्र सुरक्षित गर्छ। यसले रिपोर्ट चलाउँदैन वा रिपोर्ट पङ्क्ति सोध्दैन।',
            'overview_title' => 'अवलोकन',
            'overview_purpose' => 'रिपोर्ट डिजाइनर प्लेटफर्म-स्वामित्वक रिपोर्ट प्रस्तुति कलाकृतिहरू लेख्नको लागि एक स्टुडियो-स्वामित्वक शासित उपकरण हो।',
            'overview_note' => 'रिपोर्टहरू प्लेटफर्म-स्वामित्वक प्रस्तुति कलाकृतिहरू हुन्। व्यापार एप्स र मोड्यूलहरूले पछि प्रदायक इन्टरफेसहरू मार्फत रिपोर्ट स्रोतहरू प्रदान गर्नेछन्।',
            'readiness_title' => 'प्लेटफर्म तयारी',
            'readiness_note' => 'भविष्यको रिपोर्ट स्रोत प्रदायकहरूको लागि नकली तयारी सूचकहरू।',
            'readiness_platform_engine' => 'प्लेटफर्म इन्जिन',
            'readiness_platform_status' => 'योजना गरिएको',
            'readiness_platform_desc' => 'भविष्यको platform/Reports इन्जिनले रिपोर्ट संकलन र रनटाइम कार्यान्वयन प्रदान गर्नेछ।',
            'readiness_providers' => 'रिपोर्ट स्रोत प्रदायकहरू',
            'readiness_providers_status' => 'सुरु भएको छैन',
            'readiness_providers_desc' => 'व्यापार एप्स/मोड्यूलहरूले प्रदायक अनुबन्धहरू मार्फत रिपोर्ट स्रोतहरू गतिशील रूपमा प्रकट गर्नेछन्।',
            'readiness_definitions' => 'रिपोर्ट परिभाषाहरू',
            'readiness_definitions_status' => 'सुरु भएको छैन',
            'readiness_definitions_desc' => 'प्रणाली-स्वामित्वक रिपोर्ट परिभाषाहरू platform/Reports/Definitions/ मा भण्डारण गरिनेछ।',
            'build_title' => 'रिपोर्ट बनाउनुहोस्',
            'build_note' => 'read-only discovered metadata र placeholder preview rows बाट report संरचना डिजाइन गर्नुहोस्।',
            'reports_title' => 'रिपोर्टहरू',
            'open_report_label' => 'रिपोर्ट खोल्नुहोस्',
            'open_report_placeholder' => 'सुरक्षित रिपोर्ट छान्नुहोस्...',
            'no_saved_reports' => 'कुनै सुरक्षित रिपोर्ट फेला परेन।',
            'create_new_report' => 'नयाँ रिपोर्ट सिर्जना गर्नुहोस्',
            'save_report' => 'परिभाषा सुरक्षित गर्नुहोस्',
            'save_report_title' => 'परिभाषा सुरक्षित गर्नुहोस्',
            'save_report_name' => 'रिपोर्ट नाम',
            'save_report_description' => 'विवरण',
            'save_report_advanced' => 'उन्नत',
            'save_report_key' => 'रिपोर्ट कुञ्जी',
            'save_report_key_help' => 'रिपोर्ट नामबाट स्वतः बनाउन खाली छोड्नुहोस्।',
            'save_report_success' => 'परिभाषा सफलतापूर्वक सुरक्षित भयो।',
            'save_report_failed' => 'परिभाषा सुरक्षित गर्न सकिएन।',
            'definition_status_label' => 'परिभाषा स्थिति',
            'definition_status_unsaved' => 'असुरक्षित',
            'definition_status_modified' => 'परिमार्जित',
            'definition_status_saved' => 'सुरक्षित',
            'save_eligibility_guidance' => 'सुरक्षित गर्नु अघि कम्तीमा एउटा स्रोत र एउटा क्षेत्र चयन गर्नुहोस्।',
            'save_payload_review' => 'परिभाषा पेलोड समीक्षा',
            'save_payload_sources' => 'स्रोतहरू',
            'save_payload_fields' => 'क्षेत्रहरू',
            'save_payload_context' => 'सन्दर्भ इनपुटहरू',
            'save_payload_layout' => 'लेआउट',
            'save_payload_presentation' => 'प्रस्तुति',
            'save_payload_preview' => 'पूर्वावलोकन पूर्वनिर्धारितहरू',
            'wizard_build' => 'निर्माण',
            'wizard_presentation' => 'प्रस्तुति',
            'wizard_preview' => 'पूर्वावलोकन',
            'wizard_save' => 'परिभाषा सुरक्षित गर्नुहोस्',
            'wizard_continue_presentation' => 'प्रस्तुतिमा जारी राख्नुहोस्',
            'wizard_back_build' => 'निर्माणमा फर्कनुहोस्',
            'wizard_continue_preview' => 'पूर्वावलोकनमा जारी राख्नुहोस्',
            'wizard_back_presentation' => 'प्रस्तुतिमा फर्कनुहोस्',
            'wizard_continue_save' => 'परिभाषा सुरक्षित गर्न जारी राख्नुहोस्',
            'wizard_back_preview' => 'पूर्वावलोकनमा फर्कनुहोस्',
            'save_workspace_title' => 'परिभाषा सुरक्षित गर्नुहोस्',
            'save_workspace_note' => 'हालको रिपोर्ट डिजाइनलाई Platform-owned परिभाषाको रूपमा नाम दिएर सुरक्षित गर्नुहोस्।',
            'fields_context_title' => 'क्षेत्रहरू र सन्दर्भ इनपुटहरू',
            'data_source_title' => 'डाटा स्रोत',
            'source_model_title' => 'Bootstrap DB Discovery Mode',
            'source_model_current' => 'हालका स्रोतहरू installed app/module metadata र database schema बाट पत्ता लगाइन्छन्। business apps ले formal report-source providers प्रकाशित नगरेसम्म यो अस्थायी bridge हो।',
            'source_model_safety' => 'Schema metadata मात्र: report rows, joins, execution वा export छैन।',
            'suite_label' => 'Suite/App',
            'suite_placeholder' => 'Suite/App छान्नुहोस्',
            'modules_label' => 'Modules/Sources',
            'modules_help' => 'एक वा बढी discovered module वा source चयन गर्नुहोस्।',
            'source_available_title' => 'Available Sources',
            'source_available_empty' => 'No sources available.',
            'source_selected_title' => 'Selected Sources',
            'source_selected_empty' => 'No sources selected.',
            'source_search_placeholder' => 'स्रोतहरू नामद्वारा खोज्नुहोस्...',
            'discovery_empty' => 'कुनै reportable database source पत्ता लागेन।',
            'discovery_diagnostics' => 'Discovery diagnostics',
            'columns_note' => 'Each selected source owns its Fields and Context Inputs. Fields are discovered database columns. Context inputs are runtime parameters the report can accept — values are chosen later in Preview. No report rows are queried here.',
            'columns_empty' => 'Select at least one module/source to inspect discovered fields and context inputs.',
            'columns_selected' => 'चयन गरिएका क्षेत्रहरू',
            'columns_selected_empty' => 'कुनै क्षेत्र चयन गरिएको छैन।',
            'column_db_type' => 'DB type',
            'columns_select_all' => 'Select all',
            'columns_clear' => 'Clear',
            'columns_selected_count' => 'selected',
            'source_fields' => 'क्षेत्रहरू',
            'source_business_fields' => 'व्यावसायिक क्षेत्रहरू',
            'source_advanced_fields' => 'उन्नत क्षेत्रहरू',
            'source_business_fields_empty' => 'कुनै व्यावसायिक क्षेत्र फेला परेन।',
            'source_context_inputs' => 'सन्दर्भ इनपुटहरू',
            'filter_date_range' => 'मिति दायरा',
            'filter_status' => 'स्थिति फिल्टर',
            'filter_category' => 'श्रेणी फिल्टर',
            'layout_title' => 'लेआउट',
            'layout_note' => 'रिपोर्ट परिभाषाका लागि चयन गरिएका स्तम्भहरू configure गर्नुहोस्। रिपोर्ट पङ्क्ति सोधिँदैन।',
            'layout_columns' => 'Layout Columns',
            'layout_columns_empty' => 'लेआउट compose गर्न Step 3 मा fields चयन गर्नुहोस्।',
            'layout_display_label' => 'Display label',
            'layout_visible' => 'Visible',
            'layout_alignment' => 'Alignment',
            'layout_width' => 'Width',
            'layout_sort' => 'Sort',
            'layout_summary' => 'Summary',
            'layout_move_up' => 'Move up',
            'layout_move_down' => 'Move down',
            'layout_remove' => 'लेआउटबाट हटाउनुहोस्',
            'layout_grouping' => 'समूहीकरण',
            'layout_no_grouping' => 'No grouping',
            'live_preview_title' => 'Design Summary',
            'live_preview_note' => 'हालको in-memory डिजाइनको compact summary। पूर्ण report surface का लागि Preview मा जानुहोस्।',
            'live_preview_columns' => 'Visible Columns',
            'live_preview_grouping' => 'Grouping',
            'live_preview_filters' => 'Context Inputs',
            'preview_title' => 'पूर्वावलोकन',
            'preview_note' => 'Preview uses report design metadata and placeholder rows only.',
            'preview_no_data' => 'कुनै layout column चयन गरिएको छैन।',
            'preview_detail' => 'In-memory preview बनाउन Build मा स्तम्भहरू चयन र configure गर्नुहोस्।',
            'preview_summary_label' => 'Summary',
            'preview_actions' => 'Preview Actions',
            'preview_print' => 'Print Preview',
            'preview_generated' => 'Generated Preview',
            'preview_suite' => 'Suite/App',
            'preview_sources' => 'Selected Modules/Sources',
            'preview_filters' => 'Active Filters',
            'preview_filters_empty' => 'No active filters.',
            'preview_only' => 'Preview Only',
            'preview_generated_by' => 'Generated from Report Designer',
            'preview_page' => 'Page 1 of 1',
            'preview_untitled' => 'Untitled Report',
            'preview_unassigned' => 'Not assigned',
            'preview_state_title' => 'No in-memory preview state',
            'preview_state_note' => 'Build Report खोल्नुहोस्, डिजाइन configure गर्नुहोस्, त्यसपछि Preview मा जानुहोस्।',
            'governance_title' => 'शासन',
            'governance_note' => 'आर्किटेक्चर नोटहरू र योजना बनाइएको कार्यान्वयन।',
            'governance_architecture' => 'योजना बनाइएको आर्किटेक्चर',
            'governance_studio' => 'स्टुडियो उपकरण',
            'governance_platform' => 'प्लेटफर्म इन्जिन',
            'governance_business' => 'व्यापार एप्स',
            'governance_studio_desc' => 'रिपोर्ट डिजाइनर — शासित लेखन कार्यप्रवाह',
            'governance_platform_desc' => 'platform/Reports — संकलन र रनटाइम इन्जिन',
            'governance_business_desc' => 'रिपोर्ट स्रोत प्रदायकहरू — एप/मोड्यूल स्रोतहरूबाट डाटा प्रकट गर्नुहोस्',
            'governance_flow' => 'डाटा प्रवाह',
            'governance_flow_desc' => 'व्यापार एप्स → रिपोर्ट स्रोतहरू → प्लेटफर्म इन्जिन → संकलित रिपोर्टहरू → रनटाइम उपभोग',
            'governance_status' => 'कार्यान्वयन स्थिति',
            'governance_status_phase' => 'चरण ६: परिभाषा दृढता',
            'governance_status_desc' => 'Platform-owned रिपोर्ट परिभाषाहरू सुरक्षित र लोड गर्न सकिन्छ। रनटाइम कार्यान्वयन उपलब्ध छैन।',
            'definition_diagnostics_title' => 'रिपोर्ट परिभाषा निदान',
            'definition_count' => 'परिभाषा संख्या',
            'definition_schema_status' => 'स्किमा संस्करण स्थिति',
            'definition_duplicate_keys' => 'दोहोरो कुञ्जी चेतावनी',
            'definition_validation_failures' => 'प्रमाणीकरण असफलता',
            'definition_none' => 'कुनै छैन',
            'presentation_title' => 'प्रस्तुति',
            'presentation_note' => 'रिपोर्ट परिभाषासँग सुरक्षित हुने visual presentation options configure गर्नुहोस्।',
            'presentation_format_title' => 'Output Format',
            'presentation_format_table' => 'तालिका',
            'presentation_format_card' => 'Card Grid',
            'presentation_format_list' => 'सूची',
            'presentation_page_title' => 'Page Settings',
            'presentation_page_size' => 'Page आकार',
            'presentation_page_a4' => 'A4',
            'presentation_page_letter' => 'Letter',
            'presentation_page_legal' => 'Legal',
            'presentation_orientation' => 'Orientation',
            'presentation_portrait' => 'Portrait',
            'presentation_landscape' => 'Landscape',
            'presentation_header_title' => 'Page Header',
            'presentation_header_placeholder' => 'प्रत्येक पृष्ठको शीर्षमा छापिने वैकल्पिक पाठ',
            'presentation_footer_title' => 'Page Footer',
            'presentation_footer_placeholder' => 'प्रत्येक पृष्ठको तल्लो भागमा छापिने वैकल्पिक पाठ',
            'presentation_state_title' => 'प्रस्तुतिका लागि सक्रिय डिजाइन सत्र आवश्यक छ',
            'presentation_state_note' => 'Presentation खोल्नु अघि Build Report मा रिपोर्ट क्षेत्रहरू configure गर्नुहोस्।',
            'presentation_header_controls_title' => 'हेडर नियन्त्रणहरू',
            'presentation_header_show_logo' => 'कम्पनी लोगो देखाउनुहोस्',
            'presentation_header_show_company_name' => 'कम्पनीको नाम देखाउनुहोस्',
            'presentation_header_show_report_title' => 'रिपोर्ट शीर्षक देखाउनुहोस्',
            'presentation_header_show_description' => 'विवरण देखाउनुहोस्',
            'presentation_header_show_generated_date' => 'उत्पन्न मिति देखाउनुहोस्',
            'presentation_header_show_generated_by' => 'द्वारा उत्पन्न देखाउनुहोस्',
            'presentation_header_show_branch' => 'शाखा देखाउनुहोस्',
            'presentation_header_show_fiscal_period' => 'लेखा अवधि देखाउनुहोस्',
            'presentation_header_show_active_filters' => 'सक्रिय फिल्टरहरू देखाउनुहोस्',
            'presentation_footer_controls_title' => 'फुटर नियन्त्रणहरू',
            'presentation_footer_show_page_number' => 'पृष्ठ नम्बर क्षेत्र देखाउनुहोस्',
            'presentation_footer_show_generated_timestamp' => 'उत्पन्न टाइमस्ट्याम्प देखाउनुहोस्',
            'presentation_footer_show_confidential_notice' => 'गोपनीयता सूचना देखाउनुहोस्',
            'presentation_footer_show_signature_area' => 'हस्ताक्षर क्षेत्र देखाउनुहोस्',
            'presentation_footer_show_system_footer' => 'प्रणाली फुटर देखाउनुहोस्',
            'presentation_page_controls_title' => 'पृष्ठ नियन्त्रणहरू',
            'presentation_margin_top' => 'माथिल्लो मार्जिन',
            'presentation_margin_bottom' => 'तल्लो मार्जिन',
            'presentation_margin_left' => 'बायाँ मार्जिन',
            'presentation_margin_right' => 'दायाँ मार्जिन',
            'presentation_header_height' => 'हेडर उचाइ',
            'presentation_footer_height' => 'फुटर उचाइ',
            'presentation_org_metadata_title' => 'संगठन मेटाडेटा (पढ्ने-मात्र)',
            'presentation_org_company' => 'कम्पनी',
            'presentation_org_branch' => 'शाखा',
            'presentation_org_fiscal_period' => 'लेखा अवधि',
            'presentation_org_current_user' => 'हालको प्रयोगकर्ता',
            'presentation_metadata_not_available' => 'उपलब्ध छैन',
            'filter_group_dates' => 'मितिहरू',
            'filter_group_numbers' => 'सङ्ख्याहरू',
            'filter_group_text' => 'पाठ',
            'filter_group_statuses' => 'स्थितिहरू',
            'filter_activate' => 'सक्रिय गर्नुहोस्',
            'preview_print_button' => 'प्रिन्ट पूर्वावलोकन',
            'preview_no_design_state' => 'पूर्वावलोकनका लागि सक्रिय डिजाइन सत्र आवश्यक छ',
            'preview_no_design_state_note' => 'Preview खोल्नु अघि Build Report मा रिपोर्ट क्षेत्रहरू चयन गर्नुहोस्।',
            'preview_context_title' => 'रिपोर्ट सन्दर्भ',
            'preview_context_note' => 'भविष्यको रिपोर्ट फिल्टर मोडेलका लागि Preview-only runtime मानहरू। कुनै रिपोर्ट पङ्क्ति query गरिँदैन।',
            'preview_context_date_range' => 'मिति दायरा',
            'preview_context_current_month' => 'हालको महिना',
            'preview_context_custom_range' => 'अनुकूल मिति दायरा',
            'preview_context_change' => 'सन्दर्भ परिवर्तन गर्नुहोस्',
            'preview_context_editor_title' => 'सन्दर्भ सम्पादक',
            'preview_context_from' => 'देखि',
            'preview_context_to' => 'सम्म',
            'preview_context_include_print' => 'प्रिन्ट गर्दा सन्दर्भ सारांश समावेश गर्नुहोस्',
            'preview_context_value_in_preview' => 'Runtime मान Preview मा चयन गरिन्छ।',
        ],
    ];
    return $dict[$lang][$key] ?? $dict['en'][$key] ?? $key;
};
?>
<style>
    .rd-wizard-workspace[hidden],
    .rd-wizard-workspace:not(.is-active) {
        display: none !important;
    }
</style>
<div class="gs-studio-tool-page">
    <div class="gs-studio-tool-header">
        <h1><?= e($rd('title')) ?></h1>
        <p class="gs-studio-tool-subtitle"><?= e($rd('subtitle')) ?></p>
        <p class="gs-studio-tool-readonly"><?= e($rd('readonly')) ?> — <?= e($rd('safety')) ?></p>
    </div>

    <div class="gs-studio-tool-workspaces">
        <nav class="gs-studio-tool-workspace-nav" id="rd-workspace-nav">
            <a href="?workspace=overview" class="gs-studio-tool-workspace-link <?= $workspace === 'overview' ? 'active' : '' ?>">
                <?= e($rd('overview_title')) ?>
            </a>
            <a href="?workspace=build#rd-workspace-content" class="gs-studio-tool-workspace-link <?= in_array($workspace, ['build', 'presentation', 'preview', 'save'], true) ? 'active' : '' ?>">
                <?= e($rd('build_title')) ?>
            </a>
            <a href="?workspace=governance" class="gs-studio-tool-workspace-link <?= $workspace === 'governance' ? 'active' : '' ?>">
                <?= e($rd('governance_title')) ?>
            </a>
        </nav>

        <div class="gs-studio-tool-workspace-content" id="rd-workspace-content">
            <?php if (in_array($workspace, ['build', 'presentation', 'preview', 'save'], true)): ?>
                <?php $wizardStep = ['build' => 1, 'presentation' => 2, 'preview' => 3, 'save' => 4][$workspace]; ?>
                <nav class="rd-wizard-progress" aria-label="Report design steps">
                    <?php foreach ([
                        1 => ['workspace' => 'build', 'label' => $rd('wizard_build')],
                        2 => ['workspace' => 'presentation', 'label' => $rd('wizard_presentation')],
                        3 => ['workspace' => 'preview', 'label' => $rd('wizard_preview')],
                        4 => ['workspace' => 'save', 'label' => $rd('wizard_save')],
                    ] as $stepNumber => $step): ?>
                        <a
                            href="?workspace=<?= e($step['workspace']) ?>#rd-workspace-content"
                            class="rd-wizard-step <?= $wizardStep === $stepNumber ? 'is-current' : '' ?> <?= $wizardStep > $stepNumber ? 'is-complete' : '' ?>"
                            data-rd-workspace="<?= e($step['workspace']) ?>"
                            <?= $wizardStep === $stepNumber ? 'aria-current="step"' : '' ?>
                        >
                            <span class="rd-wizard-step-number"><?= e((string)$stepNumber) ?></span>
                            <span><?= e($step['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <?php if ($workspace === 'overview'): ?>
                <!-- Overview Workspace -->
                <div class="gs-studio-tool-card">
                    <div class="gs-studio-tool-card-title-row">
                        <span class="gs-studio-tool-card-title"><?= e($rd('overview_title')) ?></span>
                    </div>
                    <p class="gs-studio-tool-card-purpose"><?= e($rd('overview_purpose')) ?></p>
                    <p class="gs-studio-tool-card-purpose"><?= e($rd('overview_note')) ?></p>
                </div>

                <div class="gs-studio-tool-card">
                    <div class="gs-studio-tool-card-title-row">
                        <span class="gs-studio-tool-card-title"><?= e($rd('readiness_title')) ?></span>
                    </div>
                    <p class="gs-studio-tool-card-purpose"><?= e($rd('readiness_note')) ?></p>

                    <div class="rd-readiness-cards">
                        <div class="rd-readiness-card rd-status-planned">
                            <h3><?= e($rd('readiness_platform_engine')) ?></h3>
                            <p class="rd-status"><?= e($rd('readiness_platform_status')) ?></p>
                            <p class="rd-desc"><?= e($rd('readiness_platform_desc')) ?></p>
                        </div>

                        <div class="rd-readiness-card rd-status-not-started">
                            <h3><?= e($rd('readiness_providers')) ?></h3>
                            <p class="rd-status"><?= e($rd('readiness_providers_status')) ?></p>
                            <p class="rd-desc"><?= e($rd('readiness_providers_desc')) ?></p>
                        </div>

                        <div class="rd-readiness-card rd-status-not-started">
                            <h3><?= e($rd('readiness_definitions')) ?></h3>
                            <p class="rd-status"><?= e($rd('readiness_definitions_status')) ?></p>
                            <p class="rd-desc"><?= e($rd('readiness_definitions_desc')) ?></p>
                        </div>
                    </div>
                </div>

            <?php elseif (in_array($workspace, ['build', 'presentation', 'preview', 'save'], true)): ?>
                <div id="rd-wizard-workspaces" data-rd-active-workspace="<?= e($workspace) ?>">
                <?php if ($workspace === 'build'): ?>
                <section id="rd-build-workspace" class="rd-wizard-workspace is-active" data-rd-workspace-panel="build" aria-hidden="false">
                <!-- Build Report Workspace -->
                <div class="gs-studio-tool-card">
                    <div class="gs-studio-tool-card-title-row">
                        <span class="gs-studio-tool-card-title"><?= e($rd('build_title')) ?></span>
                    </div>
                    <p class="gs-studio-tool-card-purpose"><?= e($rd('build_note')) ?></p>
                </div>

                <!-- Step 1: Reports -->
                <details class="rd-collapsible-section rd-step-1" open>
                    <summary><strong>1. <?= e($rd('reports_title')) ?></strong></summary>
                    <div class="rd-section-content">
                        <div class="rd-form-group">
                            <label for="rd-open-report"><?= e($rd('open_report_label')) ?></label>
                            <select id="rd-open-report" class="rd-source-select">
                                <option value=""><?= e($rd('open_report_placeholder')) ?></option>
                                <?php if ($savedReports === []): ?>
                                    <option value="" disabled><?= e($rd('no_saved_reports')) ?></option>
                                <?php else: ?>
                                    <?php foreach ($savedReports as $report): ?>
                                        <option value="<?= e((string)($report['report_key'] ?? '')) ?>">
                                            <?= e((string)($report['report_name'] ?? $report['report_key'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="rd-report-actions">
                            <button type="button" id="rd-create-new-report" class="rd-create-button"><?= e($rd('create_new_report')) ?></button>
                            <div id="rd-definition-status" class="rd-definition-status" data-status="unsaved">
                                <span><?= e($rd('definition_status_label')) ?></span>
                                <strong><?= e($rd('definition_status_unsaved')) ?></strong>
                            </div>
                        </div>
                    </div>
                </details>

                <!-- Step 2: Data Source -->
                <details class="rd-collapsible-section rd-step-2">
                    <summary><strong>2. <?= e($rd('data_source_title')) ?></strong></summary>
                    <div class="rd-section-content">
                        <div class="rd-source-model-note">
                            <strong><?= e($rd('source_model_title')) ?></strong>
                            <p><?= e((string)($sourceCatalog['warning'] ?? $rd('source_model_current'))) ?></p>
                            <p><?= e($rd('source_model_safety')) ?></p>
                        </div>

                        <?php if ($discoveredSuites !== []): ?>
                            <!-- Source search filter -->
                            <div class="rd-form-group">
                                <input id="rd-source-search" type="text" class="rd-source-search" placeholder="<?= e($rd('source_search_placeholder')) ?>">
                            </div>
                            <!-- Selected sources chip bar -->
                            <div class="rd-source-picker">
                                <div class="rd-form-group">
                                    <label for="rd-suite-select"><?= e($rd('suite_label')) ?></label>
                                    <select id="rd-suite-select" class="rd-source-select">
                                        <option value=""><?= e($rd('suite_placeholder')) ?></option>
                                        <?php foreach ($discoveredSuites as $suiteKey => $suite): ?>
                                            <option value="<?= e((string)$suiteKey) ?>">
                                                <?= e((string)($suite['label'] ?? $suiteKey)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="rd-source-panes">
                                    <div class="rd-source-pane rd-source-pane-available">
                                        <h4 class="rd-source-pane-title"><?= e($rd('source_available_title')) ?></h4>
                                        <div id="rd-available-sources" class="rd-available-sources-list"></div>
                                    </div>
                                    <div class="rd-source-pane rd-source-pane-selected">
                                        <h4 class="rd-source-pane-title"><?= e($rd('source_selected_title')) ?> <span id="rd-selected-count" class="rd-selected-count"></span></h4>
                                        <div id="rd-selected-sources" class="rd-selected-sources-list"></div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="rd-empty-text"><?= e($rd('discovery_empty')) ?></p>
                        <?php endif; ?>

                        <?php if ($discoveryDiagnostics !== []): ?>
                            <details class="rd-discovery-diagnostics">
                                <summary><?= e($rd('discovery_diagnostics')) ?></summary>
                                <ul>
                                    <?php foreach ($discoveryDiagnostics as $diagnostic): ?>
                                        <li><?= e((string)$diagnostic) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>
                    </div>
                </details>

                <!-- Step 3: Fields & Context Inputs -->
                <details class="rd-collapsible-section rd-step-3" open>
                    <summary><strong>3. <?= e($rd('fields_context_title')) ?></strong></summary>
                    <div class="rd-section-content">
                        <p class="gs-studio-tool-card-purpose"><?= e($rd('columns_note')) ?></p>
                        <p id="rd-columns-empty" class="rd-empty-text" hidden><?= e($rd('columns_empty')) ?></p>
                        <div id="rd-column-groups" class="rd-column-groups"></div>

                        <!-- Selected fields summary -->
                        <section class="rd-selected-columns">
                            <h4><?= e($rd('columns_selected')) ?></h4>
                            <p id="rd-selected-columns-empty" class="rd-empty-text"><?= e($rd('columns_selected_empty')) ?></p>
                            <div id="rd-selected-columns-list" class="rd-chips-container"></div>
                        </section>
                    </div>
                </details>

                <!-- Step 4: Layout -->
                <details class="rd-collapsible-section rd-step-4" open>
                    <summary><strong>4. <?= e($rd('layout_title')) ?></strong></summary>
                    <div class="rd-section-content">
                        <p class="gs-studio-tool-card-purpose"><?= e($rd('layout_note')) ?></p>
                        <div class="rd-layout-options">
                            <p id="rd-layout-columns-empty" class="rd-empty-text"><?= e($rd('layout_columns_empty')) ?></p>
                            <div id="rd-layout-columns" class="rd-layout-columns rd-layout-compact"></div>
                            <div class="rd-form-group">
                                <label for="rd-layout-grouping"><?= e($rd('layout_grouping')) ?></label>
                                <select id="rd-layout-grouping" class="rd-source-select">
                                    <option value=""><?= e($rd('layout_no_grouping')) ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                </details>

                <!-- Compact Build Preview Summary -->
                <section class="rd-live-preview-summary">
                    <div class="rd-live-preview-summary-heading">
                        <div>
                            <h3><?= e($rd('live_preview_title')) ?></h3>
                            <p><?= e($rd('live_preview_note')) ?></p>
                        </div>
                    </div>
                    <div id="rd-live-preview-summary-content" class="rd-live-preview-summary-content"></div>
                </section>
                <nav class="rd-wizard-actions" aria-label="Build step navigation">
                    <span></span>
                    <a href="?workspace=presentation#rd-workspace-content" class="rd-wizard-next" data-rd-workspace="presentation">
                        <?= e($rd('wizard_continue_presentation')) ?> &rarr;
                    </a>
                </nav>
                </section>
                <?php elseif ($workspace === 'preview'): ?>

                <section id="rd-preview-workspace" class="rd-wizard-workspace is-active" data-rd-workspace-panel="preview" aria-hidden="false">
                <!-- Preview Workspace -->
                <div class="gs-studio-tool-card">
                    <div class="gs-studio-tool-card-title-row">
                        <span class="gs-studio-tool-card-title"><?= e($rd('preview_title')) ?></span>
                    </div>
                    <p class="gs-studio-tool-card-purpose"><?= e($rd('preview_note')) ?></p>
                </div>

                <div id="rd-preview-workspace-content">
                    <div id="rd-preview-has-state">
                        <!-- Phase 5.4: Preview-only Report Context -->
                        <section class="rd-report-context-panel" aria-labelledby="rd-report-context-title">
                            <div class="rd-report-context-heading">
                                <div>
                                    <h3 id="rd-report-context-title"><?= e($rd('preview_context_title')) ?></h3>
                                    <p><?= e($rd('preview_context_note')) ?></p>
                                </div>
                                <button id="rd-change-context" type="button" aria-expanded="false" aria-controls="rd-context-editor">
                                    <?= e($rd('preview_context_change')) ?>
                                </button>
                            </div>
                            <dl id="rd-report-context-summary" class="rd-report-context-summary"></dl>
                            <details id="rd-context-editor" class="rd-context-editor">
                                <summary><?= e($rd('preview_context_editor_title')) ?></summary>
                                <div class="rd-context-editor-content">
                                    <div class="rd-form-group">
                                        <label for="rd-context-date-preset"><?= e($rd('preview_context_date_range')) ?></label>
                                        <select id="rd-context-date-preset" class="rd-source-select">
                                            <option value="current_month" selected><?= e($rd('preview_context_current_month')) ?></option>
                                            <option value="custom"><?= e($rd('preview_context_custom_range')) ?></option>
                                        </select>
                                    </div>
                                    <div class="rd-context-date-inputs">
                                        <div class="rd-form-group">
                                            <label for="rd-context-date-from"><?= e($rd('preview_context_from')) ?></label>
                                            <input id="rd-context-date-from" type="date">
                                        </div>
                                        <div class="rd-form-group">
                                            <label for="rd-context-date-to"><?= e($rd('preview_context_to')) ?></label>
                                            <input id="rd-context-date-to" type="date">
                                        </div>
                                    </div>
                                    <label class="rd-context-print-option">
                                        <input id="rd-context-include-print" type="checkbox" checked>
                                        <span><?= e($rd('preview_context_include_print')) ?></span>
                                    </label>
                                </div>
                            </details>
                        </section>
                        <div class="rd-preview-actions">
                            <strong><?= e($rd('preview_actions')) ?></strong>
                            <button id="rd-print-preview" type="button"><?= e($rd('preview_print_button')) ?></button>
                        </div>
                        <div id="rd-printable-preview" class="rd-preview-container"></div>
                    </div>
                    <div id="rd-preview-no-state" class="rd-preview-state-notice" hidden>
                        <h3><?= e($rd('preview_no_design_state')) ?></h3>
                        <p><?= e($rd('preview_no_design_state_note')) ?></p>
                        <a href="?workspace=build#rd-workspace-content" class="rd-preview-state-link" data-rd-workspace="build"><?= e($rd('build_title')) ?></a>
                    </div>
                </div>
                <nav class="rd-wizard-actions" aria-label="Preview step navigation">
                    <a href="?workspace=presentation#rd-workspace-content" class="rd-wizard-back" data-rd-workspace="presentation">
                        &larr; <?= e($rd('wizard_back_presentation')) ?>
                    </a>
                    <a href="?workspace=save#rd-workspace-content" class="rd-wizard-next" data-rd-workspace="save">
                        <?= e($rd('wizard_continue_save')) ?> &rarr;
                    </a>
                </nav>
                </section>
                <?php elseif ($workspace === 'presentation'): ?>

                <section id="rd-presentation-workspace" class="rd-wizard-workspace is-active" data-rd-workspace-panel="presentation" aria-hidden="false">
                <!-- Presentation Workspace -->
                <div class="gs-studio-tool-card">
                    <div class="gs-studio-tool-card-title-row">
                        <span class="gs-studio-tool-card-title"><?= e($rd('presentation_title')) ?></span>
                    </div>
                    <p class="gs-studio-tool-card-purpose"><?= e($rd('presentation_note')) ?></p>
                </div>

                <div id="rd-presentation-workspace-content">
                    <!-- Output Format -->
                    <section class="rd-presentation-section">
                        <h3><?= e($rd('presentation_format_title')) ?></h3>
                        <div class="rd-presentation-format-options" id="rd-presentation-format-options">
                            <label class="rd-format-option rd-format-active" data-format="table">
                                <input type="radio" name="rd-output-format" value="table" checked>
                                <span class="rd-format-icon">&#x229E;</span>
                                <span><?= e($rd('presentation_format_table')) ?></span>
                            </label>
                            <label class="rd-format-option" data-format="card">
                                <input type="radio" name="rd-output-format" value="card">
                                <span class="rd-format-icon">&#x29C4;</span>
                                <span><?= e($rd('presentation_format_card')) ?></span>
                            </label>
                            <label class="rd-format-option" data-format="list">
                                <input type="radio" name="rd-output-format" value="list">
                                <span class="rd-format-icon">&#x2261;</span>
                                <span><?= e($rd('presentation_format_list')) ?></span>
                            </label>
                        </div>
                    </section>

                    <!-- Page Settings -->
                    <section class="rd-presentation-section">
                        <h3><?= e($rd('presentation_page_title')) ?></h3>
                        <div class="rd-presentation-page-settings">
                            <div class="rd-form-group">
                                <label for="rd-page-size"><?= e($rd('presentation_page_size')) ?></label>
                                <select id="rd-page-size" class="rd-source-select">
                                    <option value="a4"><?= e($rd('presentation_page_a4')) ?></option>
                                    <option value="letter"><?= e($rd('presentation_page_letter')) ?></option>
                                    <option value="legal"><?= e($rd('presentation_page_legal')) ?></option>
                                </select>
                            </div>
                            <div class="rd-form-group">
                                <label><?= e($rd('presentation_orientation')) ?></label>
                                <div class="rd-orientation-options">
                                    <label class="rd-orientation-option">
                                        <input type="radio" name="rd-orientation" value="portrait" checked>
                                        <span><?= e($rd('presentation_portrait')) ?></span>
                                    </label>
                                    <label class="rd-orientation-option">
                                        <input type="radio" name="rd-orientation" value="landscape">
                                        <span><?= e($rd('presentation_landscape')) ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Header Controls -->
                    <section class="rd-presentation-section">
                        <h3><?= e($rd('presentation_header_controls_title')) ?></h3>
                        <div class="rd-presentation-checkbox-grid" id="rd-header-controls">
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_logo" checked>
                                <?= e($rd('presentation_header_show_logo')) ?>
                            </label>
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_company_name" checked>
                                <?= e($rd('presentation_header_show_company_name')) ?>
                            </label>
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_report_title" checked>
                                <?= e($rd('presentation_header_show_report_title')) ?>
                            </label>
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_description" checked>
                                <?= e($rd('presentation_header_show_description')) ?>
                            </label>
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_generated_date" checked>
                                <?= e($rd('presentation_header_show_generated_date')) ?>
                            </label>
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_generated_by" checked>
                                <?= e($rd('presentation_header_show_generated_by')) ?>
                            </label>
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_branch" checked>
                                <?= e($rd('presentation_header_show_branch')) ?>
                            </label>
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_fiscal_period" checked>
                                <?= e($rd('presentation_header_show_fiscal_period')) ?>
                            </label>
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_active_filters" checked>
                                <?= e($rd('presentation_header_show_active_filters')) ?>
                            </label>
                        </div>
                    </section>

                    <!-- Footer Controls -->
                    <section class="rd-presentation-section">
                        <h3><?= e($rd('presentation_footer_controls_title')) ?></h3>
                        <div class="rd-presentation-checkbox-grid" id="rd-footer-controls">
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_page_number" checked>
                                <?= e($rd('presentation_footer_show_page_number')) ?>
                            </label>
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_generated_timestamp" checked>
                                <?= e($rd('presentation_footer_show_generated_timestamp')) ?>
                            </label>
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_confidential_notice">
                                <?= e($rd('presentation_footer_show_confidential_notice')) ?>
                            </label>
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_signature_area">
                                <?= e($rd('presentation_footer_show_signature_area')) ?>
                            </label>
                            <label class="rd-presentation-checkbox-option">
                                <input type="checkbox" value="show_system_footer" checked>
                                <?= e($rd('presentation_footer_show_system_footer')) ?>
                            </label>
                        </div>
                    </section>

                    <!-- Page Controls -->
                    <section class="rd-presentation-section">
                        <h3><?= e($rd('presentation_page_controls_title')) ?></h3>
                        <div class="rd-presentation-page-controls" id="rd-page-controls">
                            <div class="rd-form-group">
                                <label for="rd-margin-top"><?= e($rd('presentation_margin_top')) ?></label>
                                <input id="rd-margin-top" type="text" value="14mm" class="rd-dimension-input">
                            </div>
                            <div class="rd-form-group">
                                <label for="rd-margin-bottom"><?= e($rd('presentation_margin_bottom')) ?></label>
                                <input id="rd-margin-bottom" type="text" value="14mm" class="rd-dimension-input">
                            </div>
                            <div class="rd-form-group">
                                <label for="rd-margin-left"><?= e($rd('presentation_margin_left')) ?></label>
                                <input id="rd-margin-left" type="text" value="14mm" class="rd-dimension-input">
                            </div>
                            <div class="rd-form-group">
                                <label for="rd-margin-right"><?= e($rd('presentation_margin_right')) ?></label>
                                <input id="rd-margin-right" type="text" value="14mm" class="rd-dimension-input">
                            </div>
                            <div class="rd-form-group">
                                <label for="rd-header-height"><?= e($rd('presentation_header_height')) ?></label>
                                <input id="rd-header-height" type="text" value="auto" class="rd-dimension-input">
                            </div>
                            <div class="rd-form-group">
                                <label for="rd-footer-height"><?= e($rd('presentation_footer_height')) ?></label>
                                <input id="rd-footer-height" type="text" value="auto" class="rd-dimension-input">
                            </div>
                        </div>
                    </section>

                    <!-- Organization Metadata -->
                    <section class="rd-presentation-section rd-org-metadata">
                        <h3><?= e($rd('presentation_org_metadata_title')) ?></h3>
                        <div class="rd-org-metadata-grid">
                            <div class="rd-org-metadata-item">
                                <span class="rd-org-metadata-label"><?= e($rd('presentation_org_company')) ?></span>
                                <span class="rd-org-metadata-value" id="rd-org-company"><?= e(($orgMetadata['company'] ?? '') ?: $rd('presentation_metadata_not_available')) ?></span>
                            </div>
                            <div class="rd-org-metadata-item">
                                <span class="rd-org-metadata-label"><?= e($rd('presentation_org_branch')) ?></span>
                                <span class="rd-org-metadata-value" id="rd-org-branch"><?= e(($orgMetadata['branch'] ?? '') ?: $rd('presentation_metadata_not_available')) ?></span>
                            </div>
                            <div class="rd-org-metadata-item">
                                <span class="rd-org-metadata-label"><?= e($rd('presentation_org_fiscal_period')) ?></span>
                                <span class="rd-org-metadata-value" id="rd-org-fiscal-period"><?= e(($orgMetadata['fiscal_period'] ?? '') ?: $rd('presentation_metadata_not_available')) ?></span>
                            </div>
                            <div class="rd-org-metadata-item">
                                <span class="rd-org-metadata-label"><?= e($rd('presentation_org_current_user')) ?></span>
                                <span class="rd-org-metadata-value" id="rd-org-current-user"><?= e(($orgMetadata['current_user'] ?? '') ?: $rd('presentation_metadata_not_available')) ?></span>
                            </div>
                        </div>
                    </section>

                    <!-- Page Header -->
                    <section class="rd-presentation-section">
                        <h3><?= e($rd('presentation_header_title')) ?></h3>
                        <div class="rd-form-group">
                            <textarea id="rd-presentation-header" rows="2" placeholder="<?= e($rd('presentation_header_placeholder')) ?>"></textarea>
                        </div>
                    </section>

                    <!-- Page Footer -->
                    <section class="rd-presentation-section">
                        <h3><?= e($rd('presentation_footer_title')) ?></h3>
                        <div class="rd-form-group">
                            <textarea id="rd-presentation-footer" rows="2" placeholder="<?= e($rd('presentation_footer_placeholder')) ?>"></textarea>
                        </div>
                    </section>
                </div>
                <nav class="rd-wizard-actions" aria-label="Presentation step navigation">
                    <a href="?workspace=build#rd-workspace-content" class="rd-wizard-back" data-rd-workspace="build">
                        &larr; <?= e($rd('wizard_back_build')) ?>
                    </a>
                    <a href="?workspace=preview#rd-workspace-content" class="rd-wizard-next" data-rd-workspace="preview">
                        <?= e($rd('wizard_continue_preview')) ?> &rarr;
                    </a>
                </nav>
                </section>
                <?php elseif ($workspace === 'save'): ?>

                <section id="rd-save-workspace" class="rd-wizard-workspace is-active" data-rd-workspace-panel="save" aria-hidden="false">
                    <div class="gs-studio-tool-card">
                        <div class="gs-studio-tool-card-title-row">
                            <span class="gs-studio-tool-card-title"><?= e($rd('save_workspace_title')) ?></span>
                        </div>
                        <p class="gs-studio-tool-card-purpose"><?= e($rd('save_workspace_note')) ?></p>
                    </div>

                    <?php if ($reportDesignerFlash !== []): ?>
                        <div class="rd-save-flash <?= ($reportDesignerFlash['type'] ?? '') === 'success' ? 'is-success' : 'is-error' ?>">
                            <strong><?= e(($reportDesignerFlash['type'] ?? '') === 'success' ? $rd('save_report_success') : $rd('save_report_failed')) ?></strong>
                            <?php foreach ((array)($reportDesignerFlash['errors'] ?? []) as $error): ?>
                                <p><?= e((string)$error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="rd-save-report-panel">
                        <form id="rd-save-report-form" method="post" action="/apps/studio/tools/report-designer/save">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" id="rd-report-definition-json" name="report_definition" value="">
                            <input type="hidden" id="rd-original-report-key" name="original_report_key" value="">
                            <div class="rd-form-group">
                                <label for="rd-report-name"><?= e($rd('save_report_name')) ?></label>
                                <input id="rd-report-name" type="text" required>
                            </div>
                            <div class="rd-form-group">
                                <label for="rd-report-description"><?= e($rd('save_report_description')) ?></label>
                                <textarea id="rd-report-description" rows="2"></textarea>
                            </div>
                            <details class="rd-save-report-advanced">
                                <summary><?= e($rd('save_report_advanced')) ?></summary>
                                <div class="rd-form-group">
                                    <label for="rd-report-key"><?= e($rd('save_report_key')) ?></label>
                                    <input id="rd-report-key" type="text" pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
                                    <small><?= e($rd('save_report_key_help')) ?></small>
                                </div>
                            </details>
                            <section class="rd-save-payload-review" aria-labelledby="rd-save-payload-title">
                                <h3 id="rd-save-payload-title"><?= e($rd('save_payload_review')) ?></h3>
                                <dl>
                                    <dt><?= e($rd('save_payload_sources')) ?></dt>
                                    <dd id="rd-save-review-sources">0</dd>
                                    <dt><?= e($rd('save_payload_fields')) ?></dt>
                                    <dd id="rd-save-review-fields">0</dd>
                                    <dt><?= e($rd('save_payload_context')) ?></dt>
                                    <dd id="rd-save-review-context">0</dd>
                                    <dt><?= e($rd('save_payload_layout')) ?></dt>
                                    <dd id="rd-save-review-layout">0</dd>
                                    <dt><?= e($rd('save_payload_presentation')) ?></dt>
                                    <dd id="rd-save-review-presentation">Included</dd>
                                    <dt><?= e($rd('save_payload_preview')) ?></dt>
                                    <dd id="rd-save-review-preview">Included</dd>
                                </dl>
                            </section>
                            <p id="rd-save-eligibility-guidance" class="rd-save-guidance">
                                <?= e($rd('save_eligibility_guidance')) ?>
                            </p>
                            <nav class="rd-wizard-actions" aria-label="Save step navigation">
                                <a href="?workspace=preview#rd-workspace-content" class="rd-wizard-back" data-rd-workspace="preview">
                                    &larr; <?= e($rd('wizard_back_preview')) ?>
                                </a>
                                <button id="rd-save-definition-button" type="submit" class="rd-create-button" disabled><?= e($rd('save_workspace_title')) ?></button>
                            </nav>
                        </form>
                    </div>
                </section>
                <?php endif; ?>
                </div>

            <?php elseif ($workspace === 'governance'): ?>
                <!-- Governance Workspace -->
                <div class="gs-studio-tool-card">
                    <div class="gs-studio-tool-card-title-row">
                        <span class="gs-studio-tool-card-title"><?= e($rd('governance_title')) ?></span>
                    </div>
                    <p class="gs-studio-tool-card-purpose"><?= e($rd('governance_note')) ?></p>
                </div>

                <details class="rd-collapsible-section rd-governance-section">
                    <summary><strong><?= e($rd('governance_architecture')) ?></strong></summary>
                    <div class="rd-section-content">
                        <div class="rd-arch-diagram">
                            <div class="rd-arch-box rd-studio">
                                <h4><?= e($rd('governance_studio')) ?></h4>
                                <p><?= e($rd('governance_studio_desc')) ?></p>
                                <code>apps/Studio/Tools/ReportDesigner/</code>
                            </div>
                            <div class="rd-arch-arrow">↓</div>
                            <div class="rd-arch-box rd-platform">
                                <h4><?= e($rd('governance_platform')) ?></h4>
                                <p><?= e($rd('governance_platform_desc')) ?></p>
                                <code> <?= e($tt('studio.platform_reports_title')) ?> </code>
                            </div>
                            <div class="rd-arch-arrow">↓</div>
                            <div class="rd-arch-box rd-business">
                                <h4><?= e($rd('governance_business')) ?></h4>
                                <p><?= e($rd('governance_business_desc')) ?></p>
                                <code>Resources/report-sources/</code>
                            </div>
                        </div>

                        <div class="rd-flow-section">
                            <h4><?= e($rd('governance_flow')) ?></h4>
                            <p><?= e($rd('governance_flow_desc')) ?></p>
                        </div>
                    </div>
                </details>

                <details class="rd-collapsible-section rd-governance-section">
                    <summary><strong><?= e($rd('governance_status')) ?></strong></summary>
                    <div class="rd-section-content">
                        <div class="rd-status-badge rd-phase-badge">
                            <strong><?= e($rd('governance_status_phase')) ?></strong>
                        </div>
                        <p><?= e($rd('governance_status_desc')) ?></p>
                    </div>
                </details>

                <details class="rd-collapsible-section rd-governance-section" open>
                    <summary><strong><?= e($rd('definition_diagnostics_title')) ?></strong></summary>
                    <div class="rd-section-content">
                        <dl class="rd-definition-diagnostics">
                            <dt><?= e($rd('definition_count')) ?></dt>
                            <dd><?= e((string)($definitionDiagnostics['definition_count'] ?? 0)) ?></dd>
                            <dt><?= e($rd('definition_schema_status')) ?></dt>
                            <dd><?= e((string)($definitionDiagnostics['schema_version_status'] ?? 'current')) ?></dd>
                            <dt><?= e($rd('definition_duplicate_keys')) ?></dt>
                            <dd><?= e(($definitionDiagnostics['duplicate_keys'] ?? []) === [] ? $rd('definition_none') : implode(', ', (array)$definitionDiagnostics['duplicate_keys'])) ?></dd>
                            <dt><?= e($rd('definition_validation_failures')) ?></dt>
                            <dd><?= e(($definitionDiagnostics['validation_failures'] ?? []) === [] ? $rd('definition_none') : (string)count((array)$definitionDiagnostics['validation_failures'])) ?></dd>
                        </dl>
                        <?php foreach ((array)($definitionDiagnostics['validation_failures'] ?? []) as $failure): ?>
                            <p class="rd-diagnostic-failure">
                                <strong><?= e((string)($failure['file'] ?? 'definition')) ?>:</strong>
                                <?= e(implode('; ', (array)($failure['errors'] ?? []))) ?>
                            </p>
                        <?php endforeach; ?>
                    </div>
                </details>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(() => {
    const sourceMetadata = <?= json_encode(
        $discoveredSuites,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const orgMetadata = <?= json_encode(
        $orgMetadata,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const savedDefinitions = <?= json_encode(
        $savedReports,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const initialSavedReportKey = <?= json_encode(
        ($reportDesignerFlash['type'] ?? '') === 'success'
            ? (string)($reportDesignerFlash['report_key'] ?? '')
            : '',
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
    const wizardWorkspaces = ['build', 'presentation', 'preview', 'save'];
    const workspaceContent = document.getElementById('rd-workspace-content');
    const wizardWorkspaceContainer = document.getElementById('rd-wizard-workspaces');
    const initialWizardWorkspace = new URL(window.location.href).searchParams.get('workspace') || 'overview';
    let activeDesignSession = false;
    let refreshPreviewWorkspace = () => {};
    let syncSavePayload = () => {};
    let encodeWizardDesignState = () => '';
    const setWizardWorkspace = (workspace, updateHistory = true) => {
        if (!wizardWorkspaces.includes(workspace)) return;
        const panels = document.querySelectorAll('[data-rd-workspace-panel]');
        const targetPanel = document.querySelector(`[data-rd-workspace-panel="${workspace}"]`);
        if (panels.length === 0 || !targetPanel) {
            const destination = new URL(window.location.href);
            destination.searchParams.set('workspace', workspace);
            const encodedDesignState = encodeWizardDesignState();
            destination.hash = encodedDesignState
                ? `rd-workspace-content&rd_design=${encodedDesignState}`
                : 'rd-workspace-content';
            window.location.href = destination.toString();
            return;
        }
        panels.forEach((panel) => {
            const isActivePanel = panel.dataset.rdWorkspacePanel === workspace;
            panel.hidden = !isActivePanel;
            panel.classList.toggle('is-active', isActivePanel);
            panel.setAttribute('aria-hidden', isActivePanel ? 'false' : 'true');
            panel.style.display = isActivePanel ? '' : 'none';
        });
        if (wizardWorkspaceContainer) wizardWorkspaceContainer.dataset.rdActiveWorkspace = workspace;
        const currentIndex = wizardWorkspaces.indexOf(workspace);
        document.querySelectorAll('.rd-wizard-step').forEach((step) => {
            const stepIndex = wizardWorkspaces.indexOf(step.dataset.rdWorkspace || '');
            const isCurrent = stepIndex === currentIndex;
            step.classList.toggle('is-current', isCurrent);
            step.classList.toggle('is-complete', stepIndex >= 0 && stepIndex < currentIndex);
            if (isCurrent) {
                step.setAttribute('aria-current', 'step');
            } else {
                step.removeAttribute('aria-current');
            }
        });
        if (updateHistory) {
            const destination = new URL(window.location.href);
            destination.searchParams.set('workspace', workspace);
            destination.hash = 'rd-workspace-content';
            window.history.pushState({ reportDesignerWorkspace: workspace }, '', destination);
        }
        if (workspace !== initialWizardWorkspace || updateHistory) {
            activeDesignSession = true;
        }
        if (workspace === 'preview') refreshPreviewWorkspace();
        if (workspace === 'save') syncSavePayload();
        workspaceContent?.scrollIntoView({ block: 'start' });
    };
    document.querySelectorAll('[data-rd-workspace]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const destination = link.dataset.rdWorkspace || '';
            if (!wizardWorkspaces.includes(destination)) return;
            const encodedDesignState = encodeWizardDesignState();
            if (encodedDesignState) {
                const destinationUrl = new URL(link.getAttribute('href') || window.location.href, window.location.href);
                destinationUrl.hash = `rd-workspace-content&rd_design=${encodedDesignState}`;
                link.setAttribute('href', `${destinationUrl.pathname}${destinationUrl.search}${destinationUrl.hash}`);
            }
            if (!document.querySelector(`[data-rd-workspace-panel="${destination}"]`)) return;
            event.preventDefault();
            setWizardWorkspace(destination);
        });
    });
    window.addEventListener('popstate', () => {
        const workspace = new URL(window.location.href).searchParams.get('workspace') || 'build';
        setWizardWorkspace(workspace, false);
    });
    if (wizardWorkspaceContainer) {
        setWizardWorkspace(wizardWorkspaceContainer.dataset.rdActiveWorkspace || 'build', false);
    }
    if (window.location.hash === '#rd-workspace-content') {
        requestAnimationFrame(() => workspaceContent?.scrollIntoView({ block: 'start' }));
    }
    const text = <?= json_encode([
        'display_label' => $rd('layout_display_label'),
        'visible' => $rd('layout_visible'),
        'alignment' => $rd('layout_alignment'),
        'width' => $rd('layout_width'),
        'sort' => $rd('layout_sort'),
        'summary' => $rd('layout_summary'),
        'move_up' => $rd('layout_move_up'),
        'move_down' => $rd('layout_move_down'),
        'remove_layout' => $rd('layout_remove'),
        'no_grouping' => $rd('layout_no_grouping'),
        'live_preview_columns' => $rd('live_preview_columns'),
        'live_preview_grouping' => $rd('live_preview_grouping'),
        'live_preview_filters' => $rd('live_preview_filters'),
        'columns_select_all' => $rd('columns_select_all'),
        'columns_clear' => $rd('columns_clear'),
        'columns_selected_count' => $rd('columns_selected_count'),
        'preview_no_data' => $rd('preview_no_data'),
        'preview_detail' => $rd('preview_detail'),
        'preview_summary_label' => $rd('preview_summary_label'),
        'preview_generated' => $rd('preview_generated'),
        'preview_suite' => $rd('preview_suite'),
        'preview_sources' => $rd('preview_sources'),
        'preview_filters' => $rd('preview_filters'),
        'preview_filters_empty' => $rd('preview_filters_empty'),
        'preview_only' => $rd('preview_only'),
        'preview_generated_by' => $rd('preview_generated_by'),
        'preview_page' => $rd('preview_page'),
        'preview_untitled' => $rd('preview_untitled'),
        'preview_unassigned' => $rd('preview_unassigned'),
        'filter_group_dates' => $rd('filter_group_dates'),
        'filter_group_numbers' => $rd('filter_group_numbers'),
        'filter_group_text' => $rd('filter_group_text'),
        'filter_group_statuses' => $rd('filter_group_statuses'),
        'filter_activate' => $rd('filter_activate'),
        'source_fields' => $rd('source_fields'),
        'source_business_fields' => $rd('source_business_fields'),
        'source_advanced_fields' => $rd('source_advanced_fields'),
        'source_business_fields_empty' => $rd('source_business_fields_empty'),
        'source_context_inputs' => $rd('source_context_inputs'),
        'source_available_empty' => $rd('source_available_empty'),
        'source_selected_empty' => $rd('source_selected_empty'),
        'preview_context_title' => $rd('preview_context_title'),
        'preview_context_date_range' => $rd('preview_context_date_range'),
        'preview_context_current_month' => $rd('preview_context_current_month'),
        'preview_context_custom_range' => $rd('preview_context_custom_range'),
        'preview_context_value_in_preview' => $rd('preview_context_value_in_preview'),
        'presentation_org_branch' => $rd('presentation_org_branch'),
        'presentation_org_fiscal_period' => $rd('presentation_org_fiscal_period'),
        'presentation_org_current_user' => $rd('presentation_org_current_user'),
        'presentation_footer_confidential_notice' => $rd('presentation_footer_show_confidential_notice'),
        'presentation_footer_signature_area' => $rd('presentation_footer_show_signature_area'),
        'definition_status_unsaved' => $rd('definition_status_unsaved'),
        'definition_status_modified' => $rd('definition_status_modified'),
        'definition_status_saved' => $rd('definition_status_saved'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const buildEl = (id) => document.getElementById(id);
    const suiteSelect = buildEl('rd-suite-select');
    const sourceSearchInput = buildEl('rd-source-search');
    const availableSourcesList = buildEl('rd-available-sources');
    const selectedSourcesList = buildEl('rd-selected-sources');
    const selectedCount = buildEl('rd-selected-count');
    const columnGroups = buildEl('rd-column-groups');
    const columnsEmpty = buildEl('rd-columns-empty');
    const selectedColumnsEmpty = buildEl('rd-selected-columns-empty');
    const selectedColumnsList = buildEl('rd-selected-columns-list');
    const layoutColumnsContainer = buildEl('rd-layout-columns');
    const layoutColumnsEmpty = buildEl('rd-layout-columns-empty');
    const groupingSelect = buildEl('rd-layout-grouping');
    const livePreviewSummary = buildEl('rd-live-preview-summary-content');
    const printablePreview = buildEl('rd-printable-preview');
    const printPreview = buildEl('rd-print-preview');
    const changeContextButton = buildEl('rd-change-context');
    const contextEditor = buildEl('rd-context-editor');
    const contextSummary = buildEl('rd-report-context-summary');
    const contextDatePreset = buildEl('rd-context-date-preset');
    const contextDateFrom = buildEl('rd-context-date-from');
    const contextDateTo = buildEl('rd-context-date-to');
    const contextIncludePrint = buildEl('rd-context-include-print');
    const openReportSelect = buildEl('rd-open-report');
    const createNewReportBtn = buildEl('rd-create-new-report');
    const saveReportForm = buildEl('rd-save-report-form');
    const reportDefinitionJson = buildEl('rd-report-definition-json');
    const originalReportKeyInput = buildEl('rd-original-report-key');
    const reportNameInput = buildEl('rd-report-name');
    const reportDescriptionInput = buildEl('rd-report-description');
    const reportKeyInput = buildEl('rd-report-key');
    const formatOptions = buildEl('rd-presentation-format-options');
    const pageSizeSelect = buildEl('rd-page-size');
    const presentationHeader = buildEl('rd-presentation-header');
    const presentationFooter = buildEl('rd-presentation-footer');
    const definitionStatus = buildEl('rd-definition-status');
    const saveDefinitionButton = buildEl('rd-save-definition-button');
    const saveEligibilityGuidance = buildEl('rd-save-eligibility-guidance');
    const saveReviewSources = buildEl('rd-save-review-sources');
    const saveReviewFields = buildEl('rd-save-review-fields');
    const saveReviewContext = buildEl('rd-save-review-context');
    const saveReviewLayout = buildEl('rd-save-review-layout');

    const today = new Date();
    const currentMonthStart = new Date(today.getFullYear(), today.getMonth(), 1);
    const currentMonthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    const toDateInputValue = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    const createDefaultPresentation = () => ({
        format: 'table',
        pageSize: 'a4',
        orientation: 'portrait',
        headerText: '',
        footerText: '',
        headerControls: { show_logo: true, show_company_name: true, show_report_title: true, show_description: true, show_generated_date: true, show_generated_by: true, show_branch: true, show_fiscal_period: true, show_active_filters: true },
        footerControls: { show_page_number: true, show_generated_timestamp: true, show_confidential_notice: false, show_signature_area: false, show_system_footer: true },
        pageControls: { marginTop: '14mm', marginBottom: '14mm', marginLeft: '14mm', marginRight: '14mm', headerHeight: 'auto', footerHeight: 'auto' },
    });
    const createDefaultPreviewContext = () => ({
        datePreset: 'current_month',
        dateFrom: toDateInputValue(currentMonthStart),
        dateTo: toDateInputValue(currentMonthEnd),
        includeInPrint: true,
    });
    const designState = {
        reportKey: '',
        originalReportKey: '',
        reportName: '',
        description: '',
        suite: '',
        sourceKeys: new Set(),
        columns: [],
        contextInputs: new Map(),
        grouping: '',
        presentation: createDefaultPresentation(),
        previewDefaults: createDefaultPreviewContext(),
        status: 'unsaved',
    };
    const setDefinitionStatus = (status) => {
        designState.status = ['unsaved', 'modified', 'saved'].includes(status) ? status : 'unsaved';
        if (!definitionStatus) return;
        definitionStatus.dataset.status = designState.status;
        const labels = {
            unsaved: text.definition_status_unsaved,
            modified: text.definition_status_modified,
            saved: text.definition_status_saved,
        };
        const value = definitionStatus.querySelector('strong');
        if (value) value.textContent = labels[designState.status];
    };
    const updateSaveEligibility = () => {
        const eligible = designState.sourceKeys.size > 0 && designState.columns.length > 0;
        if (saveDefinitionButton) saveDefinitionButton.disabled = !eligible;
        if (saveEligibilityGuidance) saveEligibilityGuidance.hidden = eligible;
        if (saveReviewSources) saveReviewSources.textContent = String(designState.sourceKeys.size);
        if (saveReviewFields) saveReviewFields.textContent = String(designState.columns.length);
        if (saveReviewContext) saveReviewContext.textContent = String(designState.contextInputs.size);
        if (saveReviewLayout) saveReviewLayout.textContent = String(designState.columns.length);
        return eligible;
    };
    const markDesignChanged = () => {
        activeDesignSession = true;
        if (designState.status === 'saved') setDefinitionStatus('modified');
        updateSaveEligibility();
        if (!buildEl('rd-save-workspace')?.hidden) syncSavePayload();
    };
    const pageStyleEl = document.createElement('style');
    pageStyleEl.id = 'rd-page-size-style';
    document.head.append(pageStyleEl);
    const updatePageStyle = () => {
        const sizeMap = { a4: 'A4', letter: 'Letter', legal: 'Legal' };
        const size = sizeMap[designState.presentation.pageSize] || 'A4';
        const orient = designState.presentation.orientation === 'landscape' ? 'landscape' : 'portrait';
        const m = designState.presentation.pageControls;
        pageStyleEl.textContent = `@page { size: ${size} ${orient}; margin: ${m.marginTop} ${m.marginRight} ${m.marginBottom} ${m.marginLeft}; }`;
    };
    updatePageStyle();

    const createSelect = (options, value, onChange) => {
        const select = document.createElement('select');
        select.className = 'rd-layout-select';
        options.forEach(([optionValue, optionLabel]) => {
            const option = document.createElement('option');
            option.value = optionValue;
            option.textContent = optionLabel;
            option.selected = optionValue === value;
            select.append(option);
        });
        select.addEventListener('change', () => onChange(select.value));
        return select;
    };

    const updateSelectedColumns = () => {
        if (!selectedColumnsList || !selectedColumnsEmpty) return;
        selectedColumnsList.replaceChildren();
        const hasSelectedColumns = designState.columns.length > 0;
        selectedColumnsEmpty.hidden = hasSelectedColumns;
        selectedColumnsEmpty.style.display = hasSelectedColumns ? 'none' : '';
        selectedColumnsList.hidden = !hasSelectedColumns;
        selectedColumnsList.style.display = hasSelectedColumns ? '' : 'none';
        if (!hasSelectedColumns) return;
        designState.columns.forEach((column) => {
            const chip = document.createElement('span');
            chip.className = 'rd-column-chip';
            chip.textContent = `${column.sourceLabel}: ${column.label}`;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'rd-chip-remove';
            remove.textContent = '\u00D7';
            remove.addEventListener('click', () => {
                designState.columns = designState.columns.filter((lc) => lc.key !== column.key);
                markDesignChanged();
                updateSelectedColumns();
                renderLayoutColumns();
                // Uncheck the column checkbox
                const cb = document.querySelector(`.rd-column-checkbox[value="${column.key}"]`);
                if (cb) { cb.checked = false; }
                renderSourceDetails();
            });
            chip.append(remove);
            selectedColumnsList.append(chip);
        });
    };

    const updateGroupingOptions = () => {
        if (!groupingSelect) return;
        groupingSelect.replaceChildren();
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = text.no_grouping;
        groupingSelect.append(emptyOption);
        designState.columns.filter((column) => column.visible).forEach((column) => {
            const option = document.createElement('option');
            option.value = column.key;
            option.textContent = column.label;
            groupingSelect.append(option);
        });
        if (!designState.columns.some((column) => column.visible && column.key === designState.grouping)) {
            designState.grouping = '';
        }
        groupingSelect.value = designState.grouping;
    };

    const placeholderValue = (column, rowIndex) => {
        if (column.filterKind === 'date') return `2026-01-0${rowIndex + 1}`;
        if (column.filterKind === 'range') return String((rowIndex + 1) * 100);
        if (column.filterKind === 'select') return `Example ${rowIndex + 1}`;
        return `${column.label} ${rowIndex + 1}`;
    };

    const createPreviewTable = (visibleColumns, rowIndexes) => {
        const table = document.createElement('table');
        table.className = 'rd-preview-table';
        const head = document.createElement('thead');
        const headRow = document.createElement('tr');
        visibleColumns.forEach((column) => {
            const cell = document.createElement('th');
            cell.className = `rd-align-${column.alignment} rd-width-${column.width}`;
            cell.textContent = column.label;
            if (column.sort !== 'none') {
                const sort = document.createElement('small');
                sort.textContent = column.sort === 'ascending' ? ' ASC' : ' DESC';
                cell.append(sort);
            }
            headRow.append(cell);
        });
        head.append(headRow);
        table.append(head);
        const body = document.createElement('tbody');
        rowIndexes.forEach((rowIndex) => {
            const row = document.createElement('tr');
            visibleColumns.forEach((column) => {
                const cell = document.createElement('td');
                cell.className = `rd-align-${column.alignment} rd-width-${column.width}`;
                cell.dataset.label = column.label;
                cell.textContent = placeholderValue(column, rowIndex);
                row.append(cell);
            });
            body.append(row);
        });
        table.append(body);
        if (visibleColumns.some((column) => column.summary !== 'none')) {
            const foot = document.createElement('tfoot');
            const row = document.createElement('tr');
            visibleColumns.forEach((column) => {
                const cell = document.createElement('td');
                cell.className = `rd-align-${column.alignment}`;
                if (column.summary === 'count') {
                    cell.textContent = `${text.preview_summary_label}: ${rowIndexes.length}`;
                } else if (column.summary === 'sum') {
                    const sum = rowIndexes.reduce((total, rowIndex) => total + ((rowIndex + 1) * 100), 0);
                    cell.textContent = `${text.preview_summary_label}: ${sum}`;
                } else if (column.summary === 'average') {
                    const sum = rowIndexes.reduce((total, rowIndex) => total + ((rowIndex + 1) * 100), 0);
                    cell.textContent = `${text.preview_summary_label}: ${Math.round(sum / rowIndexes.length)}`;
                }
                row.append(cell);
            });
            foot.append(row);
            table.append(foot);
        }
        return table;
    };

    const createPreviewCards = (visibleColumns, rowIndexes) => {
        const grid = document.createElement('div');
        grid.className = 'rd-preview-card-grid';
        rowIndexes.forEach((rowIndex) => {
            const card = document.createElement('article');
            card.className = 'rd-preview-card';
            const list = document.createElement('dl');
            visibleColumns.forEach((column) => {
                list.append(
                    Object.assign(document.createElement('dt'), { textContent: column.label }),
                    Object.assign(document.createElement('dd'), { className: `rd-align-${column.alignment}`, textContent: placeholderValue(column, rowIndex) }),
                );
            });
            card.append(list);
            grid.append(card);
        });
        return grid;
    };

    const createPreviewBodyBlock = (visibleColumns, rowIndexes) => (
        designState.presentation.format === 'card'
            ? createPreviewCards(visibleColumns, rowIndexes)
            : createPreviewTable(visibleColumns, rowIndexes)
    );

    const renderLivePreviewSummary = () => {
        if (!livePreviewSummary) return;
        livePreviewSummary.replaceChildren();
        const visibleColumns = designState.columns.filter((column) => column.visible);
        if (visibleColumns.length === 0) {
            livePreviewSummary.append(Object.assign(document.createElement('p'), { className: 'rd-empty-text', textContent: text.preview_no_data }));
            return;
        }
        const groupingColumn = designState.columns.find((column) => column.key === designState.grouping);
        const enabledFilters = Array.from(designState.contextInputs.values()).filter((f) => f.enabled && f.summary);
        const summary = document.createElement('dl');
        [
            [text.live_preview_columns, visibleColumns.map((c) => c.label).join(', ')],
            [text.live_preview_grouping, groupingColumn?.label || text.no_grouping],
            [text.live_preview_filters, String(enabledFilters.length)],
        ].forEach(([label, value]) => {
            const term = document.createElement('dt'); term.textContent = label;
            const detail = document.createElement('dd'); detail.textContent = value;
            summary.append(term, detail);
        });
        livePreviewSummary.append(summary);
    };

    const formatContextDate = (value) => {
        if (!value) return text.preview_unassigned;
        const date = new Date(`${value}T00:00:00`);
        return Number.isNaN(date.getTime())
            ? value
            : new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'short', day: 'numeric' }).format(date);
    };

    const reportContextDateSummary = () => {
        const range = `${formatContextDate(designState.previewDefaults.dateFrom)} - ${formatContextDate(designState.previewDefaults.dateTo)}`;
        return designState.previewDefaults.datePreset === 'current_month'
            ? `${text.preview_context_current_month} (${range})`
            : range;
    };

    const appendContextSummary = (container, printable = false) => {
        if (!container || (printable && !designState.previewDefaults.includeInPrint)) return;
        const list = document.createElement('dl');
        list.className = printable ? 'rd-print-context-summary' : 'rd-report-context-summary';
        list.append(
            Object.assign(document.createElement('dt'), { textContent: text.preview_context_date_range }),
            Object.assign(document.createElement('dd'), { textContent: reportContextDateSummary() }),
        );
        container.append(list);
    };

    const renderReportContext = () => {
        if (contextSummary) {
            contextSummary.replaceChildren();
            contextSummary.append(
                Object.assign(document.createElement('dt'), { textContent: text.preview_context_date_range }),
                Object.assign(document.createElement('dd'), { textContent: reportContextDateSummary() }),
            );
        }
        if (contextDatePreset) contextDatePreset.value = designState.previewDefaults.datePreset;
        if (contextDateFrom) contextDateFrom.value = designState.previewDefaults.dateFrom;
        if (contextDateTo) contextDateTo.value = designState.previewDefaults.dateTo;
        if (contextIncludePrint) contextIncludePrint.checked = designState.previewDefaults.includeInPrint;
    };

    const renderPreviewIn = (container) => {
        if (!container) return;
        container.replaceChildren();
        const visibleColumns = designState.columns.filter((c) => c.visible);
        if (visibleColumns.length === 0) {
            container.append(Object.assign(document.createElement('div'), { className: 'rd-preview-empty-state' },
                Object.assign(document.createElement('h3'), { textContent: text.preview_no_data }),
                Object.assign(document.createElement('p'), { textContent: text.preview_detail }),
            ));
            return;
        }
        const suite = sourceMetadata[designState.suite] || {};
        const selectedSourceLabels = Array.from(designState.sourceKeys)
            .map((key) => suite.sources?.[key]?.label || key);
        const enabledFilters = Array.from(designState.contextInputs.values()).filter((f) => f.enabled && f.summary);
        const headerControls = designState.presentation.headerControls;
        const footerControls = designState.presentation.footerControls;
        const pageControls = designState.presentation.pageControls;
        const report = document.createElement('article');
        report.className = 'rd-printable-report';
        report.dataset.format = designState.presentation.format;
        report.dataset.pageSize = designState.presentation.pageSize;
        report.dataset.orientation = designState.presentation.orientation;
        report.style.padding = `${pageControls.marginTop || '14mm'} ${pageControls.marginRight || '14mm'} ${pageControls.marginBottom || '14mm'} ${pageControls.marginLeft || '14mm'}`;

        const headerEl = document.createElement('header');
        headerEl.className = 'rd-report-header';
        if (pageControls.headerHeight && pageControls.headerHeight !== 'auto') {
            headerEl.style.minHeight = pageControls.headerHeight;
        }
        if (headerControls.show_logo) {
            headerEl.append(Object.assign(document.createElement('div'), { className: 'rd-report-logo-mark', textContent: ((orgMetadata.company || 'RD').trim()[0] || 'R').toUpperCase() }));
        }
        if (headerControls.show_company_name) {
            headerEl.append(Object.assign(document.createElement('p'), { className: 'rd-report-company-name', textContent: orgMetadata.company || text.preview_unassigned }));
        }
        if (designState.presentation.headerText) {
            headerEl.append(Object.assign(document.createElement('p'), { className: 'rd-report-custom-header', textContent: designState.presentation.headerText }));
        }
        if (headerControls.show_generated_date) {
            headerEl.append(Object.assign(document.createElement('p'), { className: 'rd-report-kicker', textContent: text.preview_generated }));
        }
        if (headerControls.show_report_title) {
            const title = document.createElement('h1');
            title.textContent = designState.reportName || text.preview_untitled;
            headerEl.append(title);
        }
        if (headerControls.show_description && designState.description) {
            headerEl.append(Object.assign(document.createElement('p'), { className: 'rd-report-description', textContent: designState.description }));
        }
        const meta = document.createElement('dl');
        meta.className = 'rd-report-meta';
        const metaRows = [
            [text.preview_suite, suite.label || text.preview_unassigned],
            [text.preview_sources, selectedSourceLabels.join(', ') || text.preview_unassigned],
        ];
        if (headerControls.show_branch) metaRows.push([text.presentation_org_branch || 'Branch', orgMetadata.branch || text.preview_unassigned]);
        if (headerControls.show_fiscal_period) metaRows.push([text.presentation_org_fiscal_period || 'Fiscal Period', orgMetadata.fiscal_period || text.preview_unassigned]);
        if (headerControls.show_generated_by) metaRows.push([text.presentation_org_current_user || 'Generated By', orgMetadata.current_user || text.preview_unassigned]);
        if (headerControls.show_active_filters) metaRows.push([text.live_preview_filters, String(enabledFilters.length)]);
        metaRows.forEach(([l, v]) => {
            meta.append(Object.assign(document.createElement('dt'), { textContent: l }), Object.assign(document.createElement('dd'), { textContent: v }));
        });
        headerEl.append(meta);
        report.append(headerEl);

        if (designState.previewDefaults.includeInPrint) {
            const filterSection = document.createElement('section');
            filterSection.className = 'rd-report-filters';
            filterSection.append(Object.assign(document.createElement('h2'), { textContent: text.preview_context_title }));
            appendContextSummary(filterSection, true);
            if (enabledFilters.length > 0) {
                const list = document.createElement('dl');
                enabledFilters.forEach((f) => {
                    list.append(Object.assign(document.createElement('dt'), { textContent: f.label }), Object.assign(document.createElement('dd'), { textContent: f.summary }));
                });
                filterSection.append(list);
            }
            report.append(filterSection);
        }

        const body = document.createElement('section');
        body.className = 'rd-report-body';
        const groupedColumn = designState.columns.find((c) => c.key === designState.grouping);
        if (groupedColumn) {
            for (let i = 0; i < 3; i += 1) {
                const group = document.createElement('section');
                group.className = 'rd-preview-group-section';
                group.append(Object.assign(document.createElement('h2'), { className: 'rd-preview-group', textContent: `${groupedColumn.label}: ${placeholderValue(groupedColumn, i)}` }));
                group.append(createPreviewBodyBlock(visibleColumns, [i]));
                body.append(group);
            }
        } else {
            body.append(createPreviewBodyBlock(visibleColumns, [0, 1, 2]));
        }
        report.append(body);

        const footerEl = document.createElement('footer');
        footerEl.className = 'rd-report-footer';
        if (pageControls.footerHeight && pageControls.footerHeight !== 'auto') {
            footerEl.style.minHeight = pageControls.footerHeight;
        }
        if (designState.presentation.footerText) {
            footerEl.append(Object.assign(document.createElement('p'), { className: 'rd-report-custom-footer-text', textContent: designState.presentation.footerText }));
        }
        if (footerControls.show_system_footer) {
            footerEl.append(Object.assign(document.createElement('strong'), { textContent: text.preview_only }));
        }
        if (footerControls.show_generated_timestamp) {
            footerEl.append(Object.assign(document.createElement('span'), { textContent: text.preview_generated_by }));
        }
        if (footerControls.show_confidential_notice) {
            footerEl.append(Object.assign(document.createElement('span'), { className: 'rd-report-confidential', textContent: text.presentation_footer_confidential_notice }));
        }
        if (footerControls.show_signature_area) {
            footerEl.append(Object.assign(document.createElement('span'), { className: 'rd-report-signature-line', textContent: text.presentation_footer_signature_area }));
        }
        if (footerControls.show_page_number) {
            footerEl.append(Object.assign(document.createElement('span'), { className: 'rd-report-page-number', textContent: text.preview_page }));
        }
        report.append(footerEl);
        container.append(report);
    };

    const renderPreview = () => {
        renderLivePreviewSummary();
        refreshPreviewWorkspace();
        updateSaveEligibility();
    };

    // --- Filter Candidate rendering ---
    const renderFilterGroupCandidates = (filterKey, column, sourceKey) => {
        const filterState = designState.contextInputs.get(filterKey) || { enabled: false, label: column.label || column.key, summary: '', values: ['', ''] };
        const filterKind = column.filter_kind || 'text';
        const card = document.createElement('div');
        card.className = 'rd-filter-candidate';
        card.dataset.filterKey = filterKey;

        const heading = document.createElement('label');
        heading.className = 'rd-filter-candidate-heading';
        const toggle = document.createElement('input');
        toggle.type = 'checkbox';
        toggle.className = 'rd-filter-checkbox';
        toggle.checked = filterState.enabled;
        const name = document.createElement('span');
        name.textContent = column.label || column.key;
        heading.append(toggle, name);
        card.append(heading);

        const previewValueNote = document.createElement('p');
        previewValueNote.className = 'rd-filter-preview-value-note';
        previewValueNote.textContent = text.preview_context_value_in_preview;
        card.append(previewValueNote);

        toggle.addEventListener('change', () => {
            designState.contextInputs.set(filterKey, {
                enabled: toggle.checked,
                label: column.label || column.key,
                filterKind,
                summary: '',
                values: [],
            });
            renderPreview();
        });

        return card;
    };

    const fieldDisplayLabel = (column) => {
        const key = String(column.key || '').toLowerCase();
        const explicitLabels = {
            approved_at: 'Approval Time',
            created_at: 'Created At',
            parts_name: 'Parts Name',
            parts_number: 'Parts Number',
            updated_at: 'Updated At',
        };
        if (explicitLabels[key]) return explicitLabels[key];
        return key.split('_').filter(Boolean).map((word) => {
            if (word === 'id') return 'ID';
            if (word === 'ipm') return 'IPM';
            if (word === 'qc') return 'QC';
            if (word === 'qty') return 'Quantity';
            return word.charAt(0).toUpperCase() + word.slice(1);
        }).join(' ') || column.label || column.key;
    };

    const isAdvancedField = (column) => {
        const key = String(column.key || '').toLowerCase();
        return key === 'id'
            || key === 'source_id'
            || key === 'source_type'
            || key.endsWith('_id')
            || key === 'created_at'
            || key === 'updated_at'
            || /(^|_)(internal|system|audit)(_|$)/.test(key)
            || /(^|_)(created_by|updated_by|deleted_by|deleted_at|version|row_version)(_|$)/.test(key);
    };

    const classifyContextInput = (column, tableKey) => {
        const key = String(column.key || '').toLowerCase();
        const dbType = String(column.db_type || '').toLowerCase();
        if (isAdvancedField(column)) return 'advanced';
        if (column.filter_kind === 'date' || /date|time|timestamp|year/.test(dbType) || /(^|_)(date|time|at|on)$/.test(key)) return 'date_range';
        if (column.filter_kind === 'select' || /(^|_)(status|state|type|category|kind|mode)$/.test(key) || dbType.startsWith('enum(') || dbType.startsWith('set(')) return 'status';
        if (/(^|_)(product|part|sku|item|material)(_|$)/.test(key)) return 'product';
        if (column.filter_kind === 'range' && /(^|_)(qty|quantity|amount|total|price|cost|rate|balance|weight|length|width|height)(_|$)/.test(key)) return 'quantity_range';
        return 'advanced';
    };

    const renderSourceDetails = () => {
        if (!suiteSelect || !columnGroups || !columnsEmpty) return;
        const suiteKey = designState.suite;
        const suite = sourceMetadata[suiteKey] || {};
        const sources = suite.sources || {};
        designState.columns = designState.columns.filter((c) => designState.sourceKeys.has(c.sourceKey));
        columnGroups.replaceChildren();
        const discoveredFilterKeys = new Set();

        let sourceIndex = 0;
        designState.sourceKeys.forEach((sourceKey) => {
            const source = sources[sourceKey] || {};
            const allColumns = [];
            Object.values(source.tables || {}).forEach((table) => {
                (table.columns || []).forEach((col) => allColumns.push(col));
            });
            const totalAvailable = allColumns.length;

            const group = document.createElement('details');
            group.className = 'rd-column-group';
            group.dataset.sourceKey = sourceKey;
            group.open = sourceIndex === 0;
            sourceIndex += 1;

            const sum = document.createElement('summary');
            sum.className = 'rd-column-group-heading';
            const title = document.createElement('strong');
            title.textContent = source.label || sourceKey;
            const countDisplay = document.createElement('span');
            countDisplay.className = 'rd-column-selected-count';
            sum.append(title, countDisplay);
            const updateCountDisplay = () => {
                const sel = designState.columns.filter((c) => c.sourceKey === sourceKey).length;
                countDisplay.textContent = `${totalAvailable} available \u2022 ${sel} selected`;
            };
            updateCountDisplay();

            const groupContent = document.createElement('div');
            groupContent.className = 'rd-column-group-content';

            // --- Fields subsection ---
            const fieldsHeading = document.createElement('h4');
            fieldsHeading.className = 'rd-source-subsection-title';
            fieldsHeading.textContent = text.source_fields;
            groupContent.append(fieldsHeading);

            const utilities = document.createElement('div');
            utilities.className = 'rd-column-group-utilities';
            const selectAll = document.createElement('button');
            selectAll.type = 'button'; selectAll.textContent = text.columns_select_all;
            const clear = document.createElement('button');
            clear.type = 'button'; clear.textContent = text.columns_clear;
            utilities.append(selectAll, clear);
            groupContent.append(utilities);

            const groupColumns = [];
            const updateFieldSelectedCount = () => {
                updateCountDisplay();
            };

            const createFieldItem = (tableKey, column) => {
                const filterKind = column.filter_kind || 'text';
                const filterKey = `${tableKey}.${column.key}`;
                const displayLabel = fieldDisplayLabel(column);
                discoveredFilterKeys.add(filterKey);

                const item = document.createElement('li');
                const label = document.createElement('label');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'rd-column-checkbox';
                checkbox.value = filterKey;
                checkbox.dataset.label = displayLabel;
                checkbox.dataset.sourceLabel = source.label || sourceKey;
                checkbox.dataset.table = tableKey;
                checkbox.checked = designState.columns.some((lc) => lc.key === filterKey);
                const applySelection = () => {
                    if (checkbox.checked && !designState.columns.some((lc) => lc.key === filterKey)) {
                        designState.columns.push({
                            key: filterKey, sourceKey, sourceLabel: source.label || sourceKey, table: tableKey,
                            columnKey: column.key, originalLabel: displayLabel,
                            label: displayLabel, dbType: column.db_type || '',
                            filterKind, visible: true, alignment: 'left', width: 'auto', sort: 'none', summary: 'none',
                        });
                    } else if (!checkbox.checked) {
                        designState.columns = designState.columns.filter((lc) => lc.key !== filterKey);
                    }
                };
                checkbox.addEventListener('change', () => { applySelection(); updateFieldSelectedCount(); renderLayoutColumns(); });
                groupColumns.push({checkbox, applySelection});

                const name = document.createElement('span');
                name.textContent = displayLabel;
                label.append(checkbox, name);
                if (column.db_type) {
                    const badge = document.createElement('span');
                    badge.className = 'rd-type-badge';
                    badge.textContent = column.db_type;
                    label.append(badge);
                }
                item.append(label);
                return item;
            };

            const businessFieldsContainer = document.createElement('div');
            businessFieldsContainer.className = 'rd-business-fields';
            businessFieldsContainer.append(Object.assign(document.createElement('h5'), {
                className: 'rd-field-classification-title',
                textContent: text.source_business_fields,
            }));
            const advancedFieldsContainer = document.createElement('details');
            advancedFieldsContainer.className = 'rd-advanced-fields';
            const advancedSummary = document.createElement('summary');
            advancedSummary.className = 'rd-field-classification-title';
            const advancedContent = document.createElement('div');
            advancedContent.className = 'rd-advanced-fields-content';
            let businessFieldCount = 0;
            let advancedFieldCount = 0;

            Object.entries(source.tables || {}).forEach(([tableKey, table]) => {
                const businessList = document.createElement('ul');
                businessList.className = 'rd-field-list';
                const advancedList = document.createElement('ul');
                advancedList.className = 'rd-field-list';

                (table.columns || []).forEach((column) => {
                    if (isAdvancedField(column)) {
                        advancedList.append(createFieldItem(tableKey, column));
                        advancedFieldCount += 1;
                    } else {
                        businessList.append(createFieldItem(tableKey, column));
                        businessFieldCount += 1;
                    }
                });

                if (businessList.childElementCount > 0) {
                    const tableGroup = document.createElement('section');
                    tableGroup.className = 'rd-column-table-group';
                    tableGroup.append(businessList);
                    businessFieldsContainer.append(tableGroup);
                }
                if (advancedList.childElementCount > 0) {
                    const tableGroup = document.createElement('section');
                    tableGroup.className = 'rd-column-table-group';
                    const tableHeading = document.createElement('div');
                    tableHeading.className = 'rd-column-table-heading';
                    tableHeading.append(Object.assign(document.createElement('code'), { textContent: tableKey }));
                    tableGroup.append(tableHeading, advancedList);
                    advancedContent.append(tableGroup);
                }
            });

            if (businessFieldCount === 0) {
                businessFieldsContainer.append(Object.assign(document.createElement('p'), {
                    className: 'rd-empty-text',
                    textContent: text.source_business_fields_empty,
                }));
            }
            groupContent.append(businessFieldsContainer);
            if (advancedFieldCount > 0) {
                advancedSummary.textContent = `${text.source_advanced_fields} (${advancedFieldCount})`;
                advancedFieldsContainer.append(advancedSummary, advancedContent);
                groupContent.append(advancedFieldsContainer);
            }

            selectAll.addEventListener('click', () => { groupColumns.forEach(({checkbox, applySelection}) => { checkbox.checked = true; applySelection(); }); markDesignChanged(); updateFieldSelectedCount(); renderLayoutColumns(); });
            clear.addEventListener('click', () => { groupColumns.forEach(({checkbox, applySelection}) => { checkbox.checked = false; applySelection(); }); markDesignChanged(); updateFieldSelectedCount(); renderLayoutColumns(); });

            // --- Context Inputs subsection ---
            const ctxHeading = document.createElement('h4');
            ctxHeading.className = 'rd-source-subsection-title';
            ctxHeading.textContent = text.source_context_inputs;
            groupContent.append(ctxHeading);

            const groupDefs = [
                { key: 'date_range', label: 'Date Range', open: true },
                { key: 'status', label: 'Status', open: true },
                { key: 'product', label: 'Product', open: true },
                { key: 'quantity_range', label: 'Quantity Range', open: true },
                { key: 'advanced', label: 'Advanced Fields', open: false },
            ];

            const sourceFilters = [];
            Object.entries(source.tables || {}).forEach(([tableKey, table]) => {
                (table.columns || []).forEach((column) => {
                    const filterKey = `${tableKey}.${column.key}`;
                    sourceFilters.push({ filterKey, column, sourceKey, tableKey, filterKind: column.filter_kind || 'text' });
                });
            });

            if (sourceFilters.length === 0) {
                groupContent.append(Object.assign(document.createElement('p'), { className: 'rd-empty-text', textContent: text.filter_candidates_empty }));
            } else {
                const groups = {};
                sourceFilters.forEach(({ filterKey, column, tableKey }) => {
                    const cls = classifyContextInput(column, tableKey);
                    if (!groups[cls]) groups[cls] = [];
                    groups[cls].push({ filterKey, column });
                });

                const ctxList = document.createElement('div');
                ctxList.className = 'rd-source-context-inputs';
                groupDefs.forEach((def) => {
                    const items = groups[def.key] || [];
                    if (items.length === 0) return;
                    if (def.key === 'advanced') {
                        const details = document.createElement('details');
                        details.className = 'rd-ctx-group-advanced';
                        details.open = false;
                        const heading = document.createElement('summary');
                        heading.className = 'rd-ctx-group-heading';
                        heading.textContent = `${def.label} (${items.length})`;
                        details.append(heading);
                        const inner = document.createElement('div');
                        inner.className = 'rd-ctx-group-content';
                        items.forEach(({ filterKey, column }) => {
                            inner.append(renderFilterGroupCandidates(filterKey, column, sourceKey));
                        });
                        details.append(inner);
                        ctxList.append(details);
                    } else {
                        const heading = document.createElement('div');
                        heading.className = 'rd-ctx-group-heading';
                        heading.textContent = def.label;
                        ctxList.append(heading);
                        items.forEach(({ filterKey, column }) => {
                            ctxList.append(renderFilterGroupCandidates(filterKey, column, sourceKey));
                        });
                    }
                });
                groupContent.append(ctxList);
            }

            group.append(sum, groupContent);
            columnGroups.append(group);
        });

        Array.from(designState.contextInputs.keys()).forEach((fk) => { if (!discoveredFilterKeys.has(fk)) designState.contextInputs.delete(fk); });
        columnsEmpty.hidden = columnGroups.childElementCount > 0;
        renderLayoutColumns();
    };

    const renderLayoutColumns = () => {
        if (!layoutColumnsContainer || !layoutColumnsEmpty) return;
        layoutColumnsContainer.replaceChildren();
        designState.columns.forEach((column, index) => {
            const card = document.createElement('div');
            card.className = 'rd-layout-column rd-layout-composer-item';
            card.dataset.columnKey = column.key;

            const identity = document.createElement('div');
            identity.className = 'rd-layout-item-identity';
            const label = document.createElement('strong');
            label.className = 'rd-layout-item-label';
            label.textContent = column.label;
            const meta = document.createElement('span');
            meta.className = 'rd-layout-item-meta';
            meta.textContent = column.sourceLabel || column.sourceKey || '';
            identity.append(label, meta);
            if (column.dbType) {
                const typeBadge = document.createElement('span');
                typeBadge.className = 'rd-type-badge rd-layout-type-badge';
                typeBadge.textContent = column.dbType;
                identity.append(typeBadge);
            }

            const controls = document.createElement('div');
            controls.className = 'rd-layout-item-controls';

            const moveGrp = document.createElement('span');
            moveGrp.className = 'rd-layout-item-move';
            const upBtn = document.createElement('button');
            upBtn.type = 'button'; upBtn.textContent = '\u25B2'; upBtn.title = text.move_up; upBtn.disabled = index === 0;
            upBtn.addEventListener('click', () => { [designState.columns[index - 1], designState.columns[index]] = [designState.columns[index], designState.columns[index - 1]]; markDesignChanged(); renderLayoutColumns(); });
            const downBtn = document.createElement('button');
            downBtn.type = 'button'; downBtn.textContent = '\u25BC'; downBtn.title = text.move_down; downBtn.disabled = index === designState.columns.length - 1;
            downBtn.addEventListener('click', () => { [designState.columns[index], designState.columns[index + 1]] = [designState.columns[index + 1], designState.columns[index]]; markDesignChanged(); renderLayoutColumns(); });
            moveGrp.append(upBtn, downBtn);

            const visibleWrap = document.createElement('label');
            visibleWrap.className = 'rd-layout-toggle';
            const visibleCheck = document.createElement('input');
            visibleCheck.type = 'checkbox'; visibleCheck.checked = column.visible; visibleCheck.title = text.visible;
            visibleCheck.addEventListener('change', () => { column.visible = visibleCheck.checked; markDesignChanged(); renderLayoutColumns(); });
            visibleWrap.append(visibleCheck, Object.assign(document.createElement('span'), { textContent: text.visible }));

            const alignSel = document.createElement('select');
            alignSel.className = 'rd-layout-select';
            alignSel.setAttribute('aria-label', text.alignment);
            ['left', 'center', 'right'].forEach((v) => { const o = document.createElement('option'); o.value = v; o.textContent = v.charAt(0).toUpperCase() + v.slice(1); o.selected = column.alignment === v; alignSel.append(o); });
            alignSel.addEventListener('change', () => { column.alignment = alignSel.value; markDesignChanged(); renderPreview(); });

            const widthSel = document.createElement('select');
            widthSel.className = 'rd-layout-select';
            widthSel.setAttribute('aria-label', text.width);
            ['auto', 'narrow', 'medium', 'wide'].forEach((v) => { const o = document.createElement('option'); o.value = v; o.textContent = v.charAt(0).toUpperCase() + v.slice(1); o.selected = column.width === v; widthSel.append(o); });
            widthSel.addEventListener('change', () => { column.width = widthSel.value; markDesignChanged(); renderPreview(); });

            const sumSel = document.createElement('select');
            sumSel.className = 'rd-layout-select';
            sumSel.setAttribute('aria-label', text.summary);
            const sumOpts = [['none', 'None'], ['count', 'Count']];
            if (column.filterKind === 'range') sumOpts.push(['sum', 'Sum'], ['average', 'Average']);
            sumOpts.forEach(([v, l]) => { const o = document.createElement('option'); o.value = v; o.textContent = l; o.selected = column.summary === v; sumSel.append(o); });
            sumSel.addEventListener('change', () => { column.summary = sumSel.value; markDesignChanged(); renderPreview(); });

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'rd-layout-remove';
            removeBtn.textContent = '\u00D7';
            removeBtn.title = text.remove_layout;
            removeBtn.setAttribute('aria-label', text.remove_layout);
            removeBtn.addEventListener('click', () => {
                designState.columns = designState.columns.filter((item) => item.key !== column.key);
                const cb = document.querySelector(`.rd-column-checkbox[value="${column.key}"]`);
                if (cb) cb.checked = false;
                markDesignChanged();
                renderLayoutColumns();
                renderSourceDetails();
            });

            [
                [text.width, widthSel],
                [text.alignment, alignSel],
                [text.summary, sumSel],
            ].forEach(([controlLabel, control]) => {
                const wrap = document.createElement('label');
                wrap.className = 'rd-layout-control';
                wrap.append(Object.assign(document.createElement('span'), { textContent: controlLabel }), control);
                controls.append(wrap);
            });
            controls.append(visibleWrap, moveGrp, removeBtn);
            card.append(identity, controls);
            layoutColumnsContainer.append(card);
        });

        layoutColumnsEmpty.hidden = designState.columns.length === 0;
        updateSelectedColumns();
        updateGroupingOptions();
        renderPreview();
    };

    const addSource = (sourceKey) => {
        if (designState.sourceKeys.has(sourceKey)) return;
        designState.sourceKeys.add(sourceKey);
        markDesignChanged();
        renderSourcePanes();
    };

    const removeSource = (sourceKey) => {
        if (!designState.sourceKeys.has(sourceKey)) return;
        designState.sourceKeys.delete(sourceKey);
        designState.columns = designState.columns.filter((c) => c.sourceKey !== sourceKey);
        markDesignChanged();
        renderSourcePanes();
    };

    const renderSelectedSourcesPane = () => {
        if (!selectedSourcesList || !selectedCount) return;
        selectedSourcesList.replaceChildren();
        designState.sourceKeys.forEach((sourceKey) => {
            const suite = sourceMetadata[designState.suite] || {};
            const source = suite.sources?.[sourceKey] || {};
            const label = source.label || sourceKey;
            const chip = document.createElement('span');
            chip.className = 'rd-source-chip';
            const info = [];
            const numColumns = Object.values(source.tables || {}).reduce((acc, t) => acc + (t.columns || []).length, 0);
            if (numColumns > 0) info.push(`${numColumns} fields`);
            chip.textContent = info.length > 0 ? `${label} (${info.join(', ')})` : label;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'rd-chip-remove';
            remove.textContent = '\u00D7';
            remove.addEventListener('click', (e) => { e.stopPropagation(); removeSource(sourceKey); });
            chip.append(remove);
            selectedSourcesList.append(chip);
        });
        if (designState.sourceKeys.size === 0) {
            selectedSourcesList.append(Object.assign(document.createElement('p'), {
                className: 'rd-empty-text',
                textContent: text.source_selected_empty,
            }));
        }
        selectedCount.textContent = designState.sourceKeys.size > 0 ? `(${designState.sourceKeys.size})` : '';
    };

    const renderAvailableSources = (autoSelect) => {
        if (!suiteSelect || !availableSourcesList) return;
        const suiteKey = designState.suite;
        const sources = sourceMetadata[suiteKey]?.sources || {};
        const searchTerm = (sourceSearchInput?.value || '').toLowerCase().trim();
        availableSourcesList.replaceChildren();
        let didAutoSelect = false;
        Object.entries(sources).forEach(([sourceKey, source]) => {
            const labelText = source.label || sourceKey;
            const moduleName = suiteKey.toLowerCase();
            if (searchTerm && !labelText.toLowerCase().includes(searchTerm) && !moduleName.includes(searchTerm)) return;
            if (designState.sourceKeys.has(sourceKey)) return;
            if (autoSelect && designState.sourceKeys.size === 0 && !searchTerm) {
                designState.sourceKeys.add(sourceKey);
                didAutoSelect = true;
                return;
            }
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'rd-source-item';
            item.dataset.key = sourceKey;
            const nameSpan = document.createElement('span');
            nameSpan.className = 'rd-source-item-name';
            nameSpan.textContent = labelText;
            item.append(nameSpan);
            const numColumns = Object.values(source.tables || {}).reduce((acc, t) => acc + (t.columns || []).length, 0);
            if (numColumns > 0) {
                const meta = document.createElement('span');
                meta.className = 'rd-source-item-meta';
                meta.textContent = `${numColumns} fields`;
                item.append(meta);
            }
            item.addEventListener('click', () => addSource(sourceKey));
            availableSourcesList.append(item);
        });
        if (availableSourcesList.childElementCount === 0) {
            availableSourcesList.append(Object.assign(document.createElement('p'), {
                className: 'rd-empty-text',
                textContent: text.source_available_empty,
            }));
        }
        renderSelectedSourcesPane();
        renderSourceDetails();
    };

    const renderSourcePanes = (autoSelect) => {
        renderAvailableSources(autoSelect);
    };

    const slugifyReportKey = (value) => String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    const buildReportDefinition = () => {
        const reportKey = designState.reportKey || slugifyReportKey(designState.reportName);
        return {
            schema_version: '1.0',
            report_key: reportKey,
            report_name: designState.reportName,
            description: designState.description,
            suite: designState.suite,
            selected_sources: Array.from(designState.sourceKeys),
            selected_fields: designState.columns.map((column) => ({
                key: column.key,
                source_key: column.sourceKey,
                table: column.table,
                column_key: column.columnKey,
                label: column.label,
                db_type: column.dbType,
                filter_kind: column.filterKind,
            })),
            context_inputs: Array.from(designState.contextInputs.entries()).map(([key, value]) => ({ key, ...value })),
            layout_configuration: {
                columns: designState.columns.map((column) => ({ ...column })),
                grouping_key: designState.grouping,
            },
            presentation_configuration: JSON.parse(JSON.stringify(designState.presentation)),
            preview_defaults: { ...designState.previewDefaults },
            created_at: '',
            updated_at: '',
        };
    };
    encodeWizardDesignState = () => {
        try {
            const draft = {
                ...buildReportDefinition(),
                __active_design_session: activeDesignSession,
                __definition_status: designState.status,
            };
            return btoa(encodeURIComponent(JSON.stringify(draft)));
        } catch (error) {
            return '';
        }
    };
    syncSavePayload = () => {
        const definition = buildReportDefinition();
        if (!designState.reportKey && definition.report_key) {
            designState.reportKey = definition.report_key;
            if (reportKeyInput) reportKeyInput.value = designState.reportKey;
        }
        if (reportDefinitionJson) reportDefinitionJson.value = JSON.stringify(definition);
        if (originalReportKeyInput) originalReportKeyInput.value = designState.originalReportKey;
        updateSaveEligibility();
        return definition;
    };

    const syncPresentationControls = () => {
        if (formatOptions) {
            formatOptions.querySelectorAll('input[type="radio"]').forEach((input) => {
                input.checked = input.value === designState.presentation.format;
                input.closest('.rd-format-option')?.classList.toggle('rd-format-active', input.checked);
            });
        }
        if (pageSizeSelect) pageSizeSelect.value = designState.presentation.pageSize;
        document.querySelectorAll('input[name="rd-orientation"]').forEach((input) => {
            input.checked = input.value === designState.presentation.orientation;
        });
        if (presentationHeader) presentationHeader.value = designState.presentation.headerText;
        if (presentationFooter) presentationFooter.value = designState.presentation.footerText;
        const headerControls = buildEl('rd-header-controls');
        headerControls?.querySelectorAll('input[type="checkbox"]').forEach((input) => {
            input.checked = Boolean(designState.presentation.headerControls[input.value]);
        });
        const footerControls = buildEl('rd-footer-controls');
        footerControls?.querySelectorAll('input[type="checkbox"]').forEach((input) => {
            input.checked = Boolean(designState.presentation.footerControls[input.value]);
        });
        Object.entries({
            marginTop: buildEl('rd-margin-top'),
            marginBottom: buildEl('rd-margin-bottom'),
            marginLeft: buildEl('rd-margin-left'),
            marginRight: buildEl('rd-margin-right'),
            headerHeight: buildEl('rd-header-height'),
            footerHeight: buildEl('rd-footer-height'),
        }).forEach(([key, input]) => {
            if (input) input.value = designState.presentation.pageControls[key] || '';
        });
        updatePageStyle();
    };

    const loadReportDefinition = (definition, definitionStatusValue = 'saved') => {
        if (!definition || typeof definition !== 'object') return;
        activeDesignSession = true;
        designState.reportKey = String(definition.report_key || '');
        designState.originalReportKey = designState.reportKey;
        designState.reportName = String(definition.report_name || '');
        designState.description = String(definition.description || '');
        designState.suite = sourceMetadata[definition.suite] ? definition.suite : '';
        if (suiteSelect) suiteSelect.value = designState.suite;
        designState.sourceKeys.clear();
        const suiteSources = sourceMetadata[designState.suite]?.sources || {};
        (definition.selected_sources || []).forEach((sourceKey) => {
            if (suiteSources[sourceKey]) designState.sourceKeys.add(sourceKey);
        });
        const layout = definition.layout_configuration || {};
        designState.columns = Array.isArray(layout.columns)
            ? layout.columns.filter((column) => column && designState.sourceKeys.has(column.sourceKey))
            : [];
        designState.grouping = typeof layout.grouping_key === 'string' ? layout.grouping_key : '';
        designState.contextInputs = new Map(
            Array.isArray(definition.context_inputs)
                ? definition.context_inputs.filter((item) => item && item.key).map((item) => [item.key, item])
                : []
        );
        designState.presentation = {
            ...designState.presentation,
            ...(definition.presentation_configuration || {}),
            headerControls: { ...designState.presentation.headerControls, ...(definition.presentation_configuration?.headerControls || {}) },
            footerControls: { ...designState.presentation.footerControls, ...(definition.presentation_configuration?.footerControls || {}) },
            pageControls: { ...designState.presentation.pageControls, ...(definition.presentation_configuration?.pageControls || {}) },
        };
        Object.assign(designState.previewDefaults, definition.preview_defaults || {});
        if (reportNameInput) reportNameInput.value = designState.reportName;
        if (reportDescriptionInput) reportDescriptionInput.value = designState.description;
        if (reportKeyInput) reportKeyInput.value = designState.reportKey;
        if (originalReportKeyInput) originalReportKeyInput.value = designState.reportKey;
        if (openReportSelect) openReportSelect.value = designState.reportKey;
        renderSourcePanes(false);
        renderLayoutColumns();
        renderReportContext();
        syncPresentationControls();
        renderPreview();
        setDefinitionStatus(definitionStatusValue);
    };
    const restorePersistedWizardDesignState = () => {
        try {
            const hashParams = new URLSearchParams((window.location.hash || '').replace(/^#/, '').replace(/^rd-workspace-content&?/, ''));
            const encodedDraft = hashParams.get('rd_design') || '';
            if (!encodedDraft) return false;
            const draft = JSON.parse(decodeURIComponent(atob(encodedDraft)));
            if (!draft || typeof draft !== 'object') return false;
            loadReportDefinition(draft, String(draft.__definition_status || 'modified'));
            activeDesignSession = Boolean(draft.__active_design_session) || activeDesignSession;
            return true;
        } catch (error) {
            return false;
        }
    };

    const resetReportDesign = () => {
        activeDesignSession = true;
        designState.reportKey = '';
        designState.originalReportKey = '';
        designState.reportName = '';
        designState.description = '';
        designState.columns = [];
        designState.contextInputs = new Map();
        designState.grouping = '';
        designState.presentation = createDefaultPresentation();
        designState.previewDefaults = createDefaultPreviewContext();
        designState.sourceKeys.clear();
        designState.suite = '';
        if (suiteSelect) suiteSelect.value = '';
        if (openReportSelect) openReportSelect.value = '';
        if (reportNameInput) reportNameInput.value = '';
        if (reportDescriptionInput) reportDescriptionInput.value = '';
        if (reportKeyInput) reportKeyInput.value = '';
        if (originalReportKeyInput) originalReportKeyInput.value = '';
        setDefinitionStatus('unsaved');
        renderSourcePanes(false);
        renderLayoutColumns();
        renderReportContext();
        syncPresentationControls();
        renderPreview();
    };

    // Initialize build workspace
    if (suiteSelect) {
        suiteSelect.addEventListener('change', () => {
            designState.suite = suiteSelect.value;
            designState.sourceKeys.clear();
            designState.columns = [];
            designState.contextInputs.clear();
            renderSourcePanes(true);
        });
        if (sourceSearchInput) {
            sourceSearchInput.addEventListener('input', () => renderAvailableSources(false));
        }
        if (groupingSelect) {
            groupingSelect.addEventListener('change', () => { designState.grouping = groupingSelect.value; renderPreview(); });
        }
        if (!suiteSelect.value && suiteSelect.options.length > 1) suiteSelect.selectedIndex = 1;
        designState.suite = suiteSelect.value;
        renderSourcePanes(true);
    }

    // Open Report handler
    if (openReportSelect) {
        openReportSelect.addEventListener('change', () => {
            const selectedKey = openReportSelect.value;
            const definition = savedDefinitions.find((item) => item.report_key === selectedKey);
            if (definition) loadReportDefinition(definition);
        });
    }

    // Create New Report handler
    if (createNewReportBtn) {
        createNewReportBtn.addEventListener('click', resetReportDesign);
    }
    if (reportNameInput) {
        reportNameInput.addEventListener('input', () => {
            designState.reportName = reportNameInput.value.trim();
            renderPreview();
        });
    }
    if (reportDescriptionInput) {
        reportDescriptionInput.addEventListener('input', () => {
            designState.description = reportDescriptionInput.value.trim();
        });
    }
    if (reportKeyInput) {
        reportKeyInput.addEventListener('input', () => {
            designState.reportKey = reportKeyInput.value.trim();
        });
    }
    if (saveReportForm) {
        saveReportForm.addEventListener('submit', (event) => {
            if (!updateSaveEligibility()) {
                event.preventDefault();
                return;
            }
            designState.reportName = reportNameInput?.value.trim() || designState.reportName;
            designState.description = reportDescriptionInput?.value.trim() || '';
            designState.reportKey = reportKeyInput?.value.trim() || designState.reportKey;
            const definition = syncSavePayload();
            if (reportKeyInput && reportKeyInput.value.trim() === '') {
                reportKeyInput.value = definition.report_key;
            }
        });
    }
    if (!buildEl('rd-save-workspace')?.hidden) syncSavePayload();
    buildEl('rd-wizard-workspaces')?.addEventListener('input', markDesignChanged);
    buildEl('rd-wizard-workspaces')?.addEventListener('change', markDesignChanged);

    // Initialize preview workspace
    if (printPreview) {
        printPreview.addEventListener('click', () => { window.print(); });
        const previewHasState = buildEl('rd-preview-has-state');
        const previewNoState = buildEl('rd-preview-no-state');
        refreshPreviewWorkspace = () => {
            const hasState = designState.columns.filter((c) => c.visible).length > 0;
            const showDesignSurface = hasState || activeDesignSession;
            if (previewHasState) previewHasState.hidden = !showDesignSurface;
            if (previewNoState) previewNoState.hidden = showDesignSurface;
            if (showDesignSurface) renderPreviewIn(printablePreview);
        };
        refreshPreviewWorkspace();
    }
    if (changeContextButton && contextEditor) {
        changeContextButton.addEventListener('click', () => {
            contextEditor.open = !contextEditor.open;
            changeContextButton.setAttribute('aria-expanded', contextEditor.open ? 'true' : 'false');
        });
        contextEditor.addEventListener('toggle', () => {
            changeContextButton.setAttribute('aria-expanded', contextEditor.open ? 'true' : 'false');
        });
    }
    if (contextDatePreset) {
        contextDatePreset.addEventListener('change', () => {
            designState.previewDefaults.datePreset = contextDatePreset.value;
            if (designState.previewDefaults.datePreset === 'current_month') {
                designState.previewDefaults.dateFrom = toDateInputValue(currentMonthStart);
                designState.previewDefaults.dateTo = toDateInputValue(currentMonthEnd);
            }
            renderReportContext();
            renderPreviewIn(printablePreview);
        });
    }
    [contextDateFrom, contextDateTo].forEach((input, index) => {
        if (!input) return;
        input.addEventListener('change', () => {
            designState.previewDefaults.datePreset = 'custom';
            if (index === 0) designState.previewDefaults.dateFrom = input.value;
            else designState.previewDefaults.dateTo = input.value;
            renderReportContext();
            renderPreviewIn(printablePreview);
        });
    });
    if (contextIncludePrint) {
        contextIncludePrint.addEventListener('change', () => {
            designState.previewDefaults.includeInPrint = contextIncludePrint.checked;
            renderPreviewIn(printablePreview);
        });
    }
    renderReportContext();

    // Initialize presentation workspace
    if (formatOptions) {
        formatOptions.querySelectorAll('input[type="radio"]').forEach((input) => {
            input.addEventListener('change', () => {
                if (input.checked) {
                    designState.presentation.format = input.value;
                    formatOptions.querySelectorAll('.rd-format-option').forEach((opt) => opt.classList.toggle('rd-format-active', opt.dataset.format === input.value));
                    renderPreview();
                }
            });
        });
    }
    if (pageSizeSelect) {
        pageSizeSelect.addEventListener('change', () => { designState.presentation.pageSize = pageSizeSelect.value; updatePageStyle(); renderPreview(); });
    }
    document.querySelectorAll('input[name="rd-orientation"]').forEach((input) => {
        input.addEventListener('change', () => { if (input.checked) { designState.presentation.orientation = input.value; updatePageStyle(); renderPreview(); } });
    });
    if (presentationHeader) {
        presentationHeader.addEventListener('input', () => { designState.presentation.headerText = presentationHeader.value; renderPreview(); });
    }
    if (presentationFooter) {
        presentationFooter.addEventListener('input', () => { designState.presentation.footerText = presentationFooter.value; renderPreview(); });
    }

    // Header controls
    const headerControls = buildEl('rd-header-controls');
    if (headerControls) {
        headerControls.querySelectorAll('input[type="checkbox"]').forEach((input) => {
            input.addEventListener('change', () => { designState.presentation.headerControls[input.value] = input.checked; renderPreview(); });
        });
    }

    // Footer controls
    const footerControls = buildEl('rd-footer-controls');
    if (footerControls) {
        footerControls.querySelectorAll('input[type="checkbox"]').forEach((input) => {
            input.addEventListener('change', () => { designState.presentation.footerControls[input.value] = input.checked; renderPreview(); });
        });
    }

    // Page controls
    const pageControls = {
        marginTop: buildEl('rd-margin-top'),
        marginBottom: buildEl('rd-margin-bottom'),
        marginLeft: buildEl('rd-margin-left'),
        marginRight: buildEl('rd-margin-right'),
        headerHeight: buildEl('rd-header-height'),
        footerHeight: buildEl('rd-footer-height'),
    };
    Object.entries(pageControls).forEach(([key, el]) => {
        if (el) el.addEventListener('input', () => { designState.presentation.pageControls[key] = el.value; updatePageStyle(); renderPreview(); });
    });
    const savedAfterSubmit = savedDefinitions.find((definition) => definition.report_key === initialSavedReportKey);
    if (savedAfterSubmit) {
        loadReportDefinition(savedAfterSubmit);
        setDefinitionStatus('saved');
    } else {
        restorePersistedWizardDesignState();
    }
    updateSaveEligibility();
})();
</script>

<style>
/* Report Designer UI Styles */
.gs-studio-tool-page {
    box-sizing: border-box;
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 20px;
}

.gs-studio-tool-header {
    margin-bottom: 24px;
}

.gs-studio-tool-header h1 {
    margin: 0 0 8px 0;
    font-size: 2em;
    color: #333;
}

.gs-studio-tool-subtitle {
    margin: 0 0 8px 0;
    color: #666;
    font-size: 1.1em;
}

.gs-studio-tool-readonly {
    margin: 0;
    color: #f57c00;
    font-size: 0.9em;
    font-weight: 500;
}

.gs-studio-tool-workspaces {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.gs-studio-tool-workspace-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    width: 100%;
    padding-bottom: 10px;
    border-bottom: 1px solid #dfe5e8;
}

.gs-studio-tool-workspace-link {
    display: inline-flex;
    flex: 1 1 180px;
    align-items: center;
    justify-content: center;
    padding: 10px 16px;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
    border: 1px solid #ddd;
    transition: all 0.2s;
}

.gs-studio-tool-workspace-link:hover {
    background: #f5f5f5;
}

.gs-studio-tool-workspace-link.active {
    background: #2e7d32;
    color: white;
    border-color: #2e7d32;
}

.gs-studio-tool-workspace-content {
    width: 100%;
    min-width: 0;
}

.gs-studio-tool-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.gs-studio-tool-card-title-row {
    margin-bottom: 12px;
}

.gs-studio-tool-card-title {
    font-size: 1.3em;
    font-weight: 600;
    color: #333;
}

.gs-studio-tool-card-purpose {
    margin: 8px 0;
    color: #666;
    line-height: 1.5;
}

/* Readiness Cards */
.rd-readiness-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.rd-readiness-card {
    padding: 16px;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.rd-readiness-card h3 {
    margin: 0 0 8px 0;
    font-size: 1.1em;
    color: #333;
}

.rd-status {
    margin: 0 0 8px 0;
    font-weight: 600;
    font-size: 0.9em;
}

.rd-status-planned .rd-status {
    color: #1976d2;
}

.rd-status-not-started .rd-status {
    color: #757575;
}

.rd-desc {
    margin: 0;
    font-size: 0.85em;
    color: #666;
    line-height: 1.4;
}

/* Collapsible Sections */
.rd-collapsible-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 16px;
}

.rd-collapsible-section summary {
    padding: 16px;
    cursor: pointer;
    font-weight: 600;
    color: #333;
    user-select: none;
}

.rd-collapsible-section summary:hover {
    background: #f5f5f5;
}

.rd-section-content {
    padding: 16px;
    border-top: 1px solid #eee;
}

.rd-governance-section {
    background: #f9f9f9;
}

/* Form Elements */
.rd-form-group {
    margin-bottom: 16px;
}

.rd-form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #333;
    font-size: 0.9em;
}

.rd-input-readonly {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f5f5f5;
    color: #666;
    font-size: 0.9em;
}

.rd-input-readonly:disabled {
    cursor: not-allowed;
}

.rd-source-model-note {
    padding: 14px 16px;
    margin-bottom: 20px;
    border-left: 4px solid #1976d2;
    background: #f3f8fd;
    color: #455a64;
}

.rd-source-model-note strong {
    color: #263238;
}

.rd-source-model-note p {
    margin: 6px 0 0;
    line-height: 1.45;
}

.rd-source-picker {
    /* suite select + search + panes container */
}

.rd-source-panes {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    align-items: start;
    margin-top: 12px;
}

.rd-source-pane {
    border: 1px solid #cfd8dc;
    border-radius: 6px;
    background: #fafafa;
    overflow: hidden;
}

.rd-source-pane-title {
    margin: 0;
    padding: 10px 14px;
    font-size: 0.88em;
    font-weight: 600;
    color: #37474f;
    background: #f0f3f5;
    border-bottom: 1px solid #cfd8dc;
}

.rd-selected-count {
    color: #78909c;
    font-weight: 400;
}

.rd-available-sources-list {
    padding: 6px;
    max-height: 280px;
    overflow-y: auto;
}

.rd-selected-sources-list {
    padding: 10px 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    min-height: 36px;
}

.rd-source-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 8px 10px;
    border: 1px solid transparent;
    border-radius: 4px;
    background: transparent;
    cursor: pointer;
    text-align: left;
    font-size: 0.88em;
    color: #263238;
    transition: background 0.1s;
}

.rd-source-item:hover {
    background: #e3f0fd;
    border-color: #bbdefb;
}

.rd-source-item:active {
    background: #c8e6fc;
}

.rd-source-item-name {
    font-weight: 500;
}

.rd-source-item-meta {
    color: #90a4ae;
    font-size: 0.82em;
    margin-left: 8px;
    white-space: nowrap;
}

.rd-source-pane-selected .rd-empty-text {
    padding: 10px 14px;
    margin: 0;
}

.rd-source-select {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #b0bec5;
    border-radius: 4px;
    background: #fff;
    color: #263238;
}

.rd-create-button {
    display: inline-block;
    padding: 9px 20px;
    border: 1px solid #1976d2;
    border-radius: 4px;
    background: #1976d2;
    color: #fff;
    font-size: 0.95em;
    font-weight: 500;
    cursor: pointer;
}

.rd-create-button:hover {
    background: #1565c0;
}

.rd-create-button:active {
    background: #0d47a1;
}

.rd-report-actions {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
}

.rd-wizard-workspace[hidden],
#rd-preview-has-state[hidden],
#rd-preview-no-state[hidden],
.rd-save-guidance[hidden] {
    display: none !important;
}

.rd-definition-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid #cfd8dc;
    border-radius: 999px;
    background: #f8fafb;
    color: #455a64;
}

.rd-definition-status[data-status="modified"] {
    border-color: #ffb74d;
    background: #fff8e1;
    color: #8d5b00;
}

.rd-definition-status[data-status="saved"] {
    border-color: #81c784;
    background: #f1f8e9;
    color: #2e7d32;
}

.rd-wizard-progress {
    display: flex;
    align-items: stretch;
    gap: 8px;
    margin-bottom: 16px;
    padding: 10px;
    border: 1px solid #d7e0e5;
    border-radius: 8px;
    background: #f8fafb;
}

.rd-wizard-step {
    display: flex;
    flex: 1 1 0;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-width: 0;
    padding: 9px 12px;
    border: 1px solid #cfd8dc;
    border-radius: 6px;
    color: #455a64;
    text-decoration: none;
    font-weight: 700;
}

.rd-wizard-step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #eceff1;
    font-size: 0.8rem;
}

.rd-wizard-step.is-current {
    border-color: #1565c0;
    background: #e3f2fd;
    color: #0d47a1;
}

.rd-wizard-step.is-current .rd-wizard-step-number {
    background: #1565c0;
    color: #fff;
}

.rd-wizard-step.is-complete {
    border-color: #81c784;
    background: #f1f8e9;
    color: #2e7d32;
}

.rd-wizard-step.is-complete .rd-wizard-step-number {
    background: #43a047;
    color: #fff;
}

.rd-wizard-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #d7e0e5;
}

.rd-wizard-back,
.rd-wizard-next {
    display: inline-flex;
    align-items: center;
    min-height: 38px;
    padding: 8px 14px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: 700;
}

.rd-wizard-back {
    color: #455a64;
}

.rd-wizard-next {
    background: #1565c0;
    color: #fff;
}

.rd-save-report-panel {
    min-width: min(100%, 360px);
    padding: 8px 12px;
    border: 1px solid #cfd8dc;
    border-radius: 6px;
    background: #fff;
}

.rd-save-report-panel > summary {
    color: #1565c0;
    cursor: pointer;
    font-weight: 700;
}

.rd-save-report-panel form {
    padding-top: 12px;
}

.rd-save-report-panel input,
.rd-save-report-panel textarea {
    width: 100%;
    box-sizing: border-box;
}

.rd-save-report-advanced {
    margin-bottom: 12px;
}

.rd-save-payload-review {
    margin: 16px 0;
    padding: 12px;
    border: 1px solid #d7e0e5;
    border-radius: 6px;
    background: #f8fafb;
}

.rd-save-payload-review h3 {
    margin: 0 0 10px;
    font-size: 1rem;
}

.rd-save-payload-review dl {
    display: grid;
    grid-template-columns: minmax(140px, auto) 1fr;
    gap: 6px 12px;
    margin: 0;
}

.rd-save-payload-review dt {
    font-weight: 700;
}

.rd-save-payload-review dd {
    margin: 0;
}

.rd-save-guidance {
    margin: 12px 0;
    color: #8d5b00;
    font-weight: 600;
}

.rd-create-button:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.rd-save-flash {
    margin-bottom: 12px;
    padding: 10px 12px;
    border-radius: 5px;
}

.rd-save-flash.is-success {
    border: 1px solid #81c784;
    background: #f1f8e9;
}

.rd-save-flash.is-error {
    border: 1px solid #ef9a9a;
    background: #fff3f3;
}

.rd-definition-diagnostics {
    display: grid;
    grid-template-columns: minmax(180px, auto) 1fr;
    gap: 8px 16px;
}

.rd-definition-diagnostics dt {
    font-weight: 700;
}

.rd-definition-diagnostics dd {
    margin: 0;
}

.rd-diagnostic-failure {
    color: #b71c1c;
}

.rd-source-search {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #cfd8dc;
    border-radius: 4px;
    background: #fff;
    font-size: 0.9em;
    box-sizing: border-box;
}

.rd-source-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 12px;
    padding: 8px 0;
}

.rd-source-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 999px;
    background: #e3f0fd;
    color: #1565c0;
    font-size: 0.82em;
    font-weight: 500;
}

.rd-chip-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #1565c0;
    font-size: 1em;
    line-height: 1;
    cursor: pointer;
    opacity: 0.6;
}

.rd-chip-remove:hover {
    opacity: 1;
    background: rgba(0,0,0,0.1);
}

.rd-chips-container {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.rd-column-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 999px;
    background: #f0f4c3;
    color: #558b2f;
    font-size: 0.82em;
    font-weight: 500;
    font-family: monospace;
}

.rd-column-chip .rd-chip-remove {
    color: #558b2f;
}

.rd-discovery-diagnostics {
    margin-top: 16px;
    color: #795548;
}

.rd-discovery-diagnostics ul {
    margin-bottom: 0;
}

/* Columns Layout */
.rd-column-groups {
    display: grid;
    gap: 10px;
}

.rd-column-group {
    border: 1px solid #dfe5e8;
    border-radius: 6px;
    background: #fbfcfc;
    overflow: hidden;
}

.rd-column-group-heading {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    cursor: pointer;
    list-style: none;
}

.rd-column-group-heading::-webkit-details-marker {
    display: none;
}

.rd-column-group-heading::after {
    content: '▾';
    color: #607d8b;
    transition: transform 0.15s ease;
}

.rd-column-group:not([open]) .rd-column-group-heading::after {
    transform: rotate(-90deg);
}

.rd-column-selected-count {
    margin-left: auto;
    color: #607d8b;
    font-size: 0.8em;
    font-weight: 500;
}

.rd-column-group-content {
    max-height: 480px;
    padding: 0 14px 14px;
    overflow: auto;
    border-top: 1px solid #e7ecef;
}

.rd-column-group-utilities {
    position: sticky;
    top: 0;
    z-index: 1;
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    padding: 10px 0;
    background: #fbfcfc;
}

.rd-column-group-utilities button {
    padding: 5px 9px;
    border: 1px solid #b0bec5;
    border-radius: 4px;
    background: #fff;
    color: #455a64;
    cursor: pointer;
}

.rd-column-table-group + .rd-column-table-group {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #e7ecef;
}

.rd-column-table-heading {
    margin-bottom: 6px;
}

.rd-column-table-heading code,
.rd-field-list code {
    color: #607d8b;
    font-size: 0.78em;
}

.rd-field-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 2px 12px;
    list-style: none;
    padding: 0;
    margin: 0;
}

.rd-field-list li {
    padding: 6px 4px;
    border-bottom: 1px solid #eee;
}

.rd-field-list li input {
    margin-right: 8px;
}

.rd-field-list label {
    display: flex;
    gap: 6px;
    align-items: center;
}

.rd-field-list label span {
    flex: 1;
}

.rd-field-classification-title {
    margin: 10px 0 6px;
    color: #455a64;
    font-size: 0.86em;
    font-weight: 700;
}

.rd-advanced-fields {
    margin-top: 10px;
    border: 1px solid #e1e7ea;
    border-radius: 5px;
    background: #fafbfc;
}

.rd-advanced-fields > summary {
    padding: 9px 12px;
    cursor: pointer;
    user-select: none;
}

.rd-advanced-fields-content {
    padding: 0 12px 10px;
}

.rd-type-badge {
    display: inline-block;
    padding: 1px 5px;
    border-radius: 3px;
    background: #eceff1;
    color: #607d8b;
    font-size: 0.72em;
    font-family: monospace;
    white-space: nowrap;
    line-height: 1.4;
}

.rd-section-divider {
    margin: 16px 0;
    border: none;
    border-top: 1px solid #e0e0e0;
}

.rd-selected-columns {
    margin-top: 18px;
    padding: 14px;
    border: 1px solid #cfd8dc;
    border-radius: 6px;
    background: #f7f9fa;
}

.rd-selected-columns h4 {
    margin: 0 0 10px;
}

.rd-selected-columns ul,
.rd-chips-container {
    margin: 0;
    padding-left: 0;
}

.rd-empty .rd-empty-text {
    color: #999;
    font-style: italic;
}

.rd-empty-text {
    color: #78909c;
    font-style: italic;
}

.rd-empty-text[hidden] {
    display: none !important;
}

.rd-source-subsection-title {
    margin: 16px 0 8px;
    font-size: 0.92em;
    color: #455a64;
    font-weight: 700;
}

.rd-source-context-inputs {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.rd-ctx-group-heading {
    padding: 6px 0;
    font-weight: 600;
    font-size: 0.85em;
    color: #607d8b;
    cursor: pointer;
    user-select: none;
}

.rd-ctx-group-advanced {
    margin-top: 4px;
    border: 1px solid #eceff1;
    border-radius: 4px;
    padding: 4px 8px;
}

.rd-ctx-group-advanced .rd-ctx-group-heading {
    font-size: 0.83em;
    color: #78909c;
}

.rd-ctx-group-content {
    padding: 2px 0 4px;
    display: flex;
    flex-direction: column;
    gap: 1px;
}

/* Layout Options */
.rd-layout-options {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.rd-layout-options h4 {
    margin: 0 0 10px;
}

.rd-layout-columns {
    display: grid;
    gap: 12px;
}

.rd-layout-compact {
    display: grid;
    gap: 8px;
}

.rd-layout-composer-item {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) minmax(420px, 1.45fr);
    gap: 12px;
    align-items: center;
    padding: 10px 12px;
    border: 1px solid #d7e0e5;
    border-radius: 6px;
    background: #fff;
}

.rd-layout-item-identity {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.rd-layout-item-label {
    min-width: 0;
    color: #263238;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rd-layout-item-meta {
    color: #607d8b;
    font-size: 0.8em;
    white-space: nowrap;
}

.rd-layout-type-badge {
    white-space: nowrap;
}

.rd-layout-item-controls {
    display: grid;
    grid-template-columns: repeat(3, minmax(96px, 1fr)) auto auto auto;
    gap: 8px;
    align-items: end;
}

.rd-layout-control {
    display: grid;
    gap: 3px;
    color: #607d8b;
    font-size: 0.76em;
    font-weight: 700;
}

.rd-layout-toggle,
.rd-layout-item-move {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-height: 32px;
}

.rd-layout-toggle {
    color: #455a64;
    font-size: 0.82em;
    font-weight: 700;
}

.rd-layout-toggle input[type="checkbox"] {
    width: 15px;
    height: 15px;
    margin: 0;
}

.rd-layout-item-move button,
.rd-layout-remove {
    min-width: 28px;
    min-height: 28px;
    padding: 2px 7px;
    border: 1px solid #cfd8dc;
    border-radius: 4px;
    background: #fff;
    color: #455a64;
    cursor: pointer;
    line-height: 1.2;
}

.rd-layout-item-move button:disabled {
    opacity: 0.35;
    cursor: default;
}

.rd-layout-remove {
    border-color: #ef9a9a;
    color: #b71c1c;
    font-weight: 700;
}

.rd-layout-composer-item .rd-layout-select {
    min-width: 0;
    padding: 5px 7px;
    font-size: 0.86em;
}

.rd-layout-control-grid label {
    display: grid;
    gap: 5px;
    color: #455a64;
    font-size: 0.82em;
}

.rd-layout-control-grid input[type="text"],
.rd-layout-select {
    width: 100%;
    padding: 7px 8px;
    border: 1px solid #cfd8dc;
    border-radius: 4px;
    background: #fff;
}

.rd-layout-control-grid .rd-layout-visible {
    display: flex;
    gap: 7px;
    align-items: center;
    padding-bottom: 8px;
}

.rd-radio-group label,
.rd-checkbox-group label {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-weight: 400;
}

.rd-radio-group label input,
.rd-checkbox-group label input {
    margin-right: 8px;
}

/* Preview */
#rd-printable-preview-workspace[hidden],
#rd-build-workspace[hidden] {
    display: none !important;
}

.rd-live-preview-summary {
    margin-top: 18px;
    padding: 18px 20px;
    border: 1px solid #dfe5e8;
    border-radius: 8px;
    background: #fff;
}

.rd-live-preview-summary-heading h3 {
    margin: 0 0 6px;
    font-size: 1.05em;
}

.rd-live-preview-summary-heading p {
    margin: 0;
    color: #607d8b;
    line-height: 1.45;
}

.rd-live-preview-summary-content {
    margin-top: 14px;
    padding: 12px 14px;
    border-left: 3px solid #2e7d32;
    background: #f6faf7;
}

.rd-live-preview-summary-content dl {
    display: grid;
    grid-template-columns: max-content 1fr;
    gap: 6px 16px;
    margin: 0;
    font-size: 0.88em;
}

.rd-live-preview-summary-content dt {
    color: #546e7a;
    font-weight: 700;
}

.rd-live-preview-summary-content dd {
    margin: 0;
}

.rd-preview-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
    padding: 12px 14px;
    border: 1px solid #dfe5e8;
    border-radius: 6px;
    background: #f8fafb;
}

.rd-preview-actions button {
    padding: 8px 14px;
    border: 1px solid #2e7d32;
    border-radius: 4px;
    background: #2e7d32;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}

.rd-preview-container {
    background: #edf0f2;
    border: 1px solid #d7dde0;
    border-radius: 8px;
    padding: 28px;
    min-height: 300px;
    overflow-x: auto;
}

.rd-printable-report {
    box-sizing: border-box;
    width: min(100%, 920px);
    min-height: 1120px;
    margin: 0 auto;
    padding: 48px 52px 36px;
    border: 1px solid #d9dfe2;
    background: #fff;
    box-shadow: 0 12px 32px rgb(38 50 56 / 12%);
    color: #263238;
}

.rd-report-header {
    padding-bottom: 22px;
    border-bottom: 2px solid #263238;
}

.rd-report-logo-mark {
    display: inline-grid;
    place-items: center;
    width: 34px;
    height: 34px;
    margin-bottom: 8px;
    border: 1px solid #90a4ae;
    border-radius: 4px;
    color: #263238;
    font-weight: 700;
}

.rd-report-company-name {
    margin: 0 0 6px;
    color: #37474f;
    font-size: 0.9em;
    font-weight: 700;
}

.rd-report-kicker {
    margin: 0 0 8px;
    color: #2e7d32;
    font-size: 0.78em;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.rd-report-header h1 {
    margin: 0;
    font-size: 2em;
    line-height: 1.15;
}

.rd-report-description {
    max-width: 680px;
    margin: 10px 0 0;
    color: #546e7a;
    line-height: 1.5;
}

.rd-report-meta {
    display: grid;
    grid-template-columns: max-content 1fr;
    gap: 6px 16px;
    margin: 20px 0 0;
    font-size: 0.86em;
}

.rd-report-meta dt,
.rd-report-filters dt {
    color: #607d8b;
    font-weight: 700;
}

.rd-report-meta dd,
.rd-report-filters dd {
    margin: 0;
}

.rd-report-filters {
    margin: 22px 0;
    padding: 14px 16px;
    border: 1px solid #dfe5e8;
    background: #f8fafb;
}

.rd-report-filters h2 {
    margin: 0 0 10px;
    font-size: 0.95em;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.rd-report-filters dl {
    display: grid;
    grid-template-columns: max-content 1fr;
    gap: 7px 18px;
    margin: 0;
    font-size: 0.86em;
}

.rd-report-filter-empty {
    margin: 0;
    color: #78909c;
    font-size: 0.86em;
}

.rd-report-body {
    min-height: 620px;
}

.rd-preview-group-section {
    margin-bottom: 24px;
    break-inside: avoid;
}

.rd-preview-group {
    margin: 0 0 8px;
    padding: 8px 0 6px;
    border-bottom: 2px solid #90a4ae;
    color: #37474f;
    font-size: 1em;
}

.rd-preview-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.rd-preview-table th,
.rd-preview-table td {
    padding: 9px 10px;
    border: 1px solid #dfe5e8;
    vertical-align: top;
}

.rd-preview-table th {
    background: #f5f7f8;
}

.rd-preview-table tfoot td {
    background: #f3f8fd;
    font-weight: 600;
}

.rd-preview-table small {
    color: #607d8b;
    font-size: 0.72em;
}

.rd-preview-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
}

.rd-preview-card {
    border: 1px solid #dfe5e8;
    border-radius: 6px;
    background: #fafbfc;
    padding: 14px;
}

.rd-preview-card dl {
    display: grid;
    grid-template-columns: minmax(92px, max-content) 1fr;
    gap: 7px 12px;
    margin: 0;
}

.rd-preview-card dt {
    color: #607d8b;
    font-weight: 700;
}

.rd-preview-card dd {
    margin: 0;
}

.rd-report-footer {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
    margin-top: 28px;
    padding-top: 14px;
    border-top: 1px solid #90a4ae;
    color: #607d8b;
    font-size: 0.78em;
}

.rd-report-footer strong {
    color: #c62828;
    text-transform: uppercase;
}

.rd-report-page-number {
    margin-left: auto;
}

.rd-report-confidential {
    color: #b71c1c;
    font-weight: 700;
}

.rd-report-signature-line {
    min-width: 150px;
    padding-top: 6px;
    border-top: 1px solid #90a4ae;
    text-align: center;
}

.rd-align-left {
    text-align: left;
}

.rd-align-center {
    text-align: center;
}

.rd-align-right {
    text-align: right;
}

.rd-width-auto {
    width: auto;
}

.rd-width-narrow {
    width: 110px;
}

.rd-width-medium {
    width: 180px;
}

.rd-width-wide {
    width: 280px;
}

.rd-preview-empty-state,
.rd-preview-state-notice {
    text-align: center;
    color: #999;
}

.rd-preview-empty-state h3,
.rd-preview-state-notice h3 {
    margin: 0 0 12px 0;
    font-size: 1.2em;
}

.rd-preview-empty-state p,
.rd-preview-state-notice p {
    margin: 0;
    font-size: 0.9em;
}

.rd-preview-state-notice {
    padding: 32px;
    border: 1px solid #dfe5e8;
    border-radius: 8px;
    background: #fff;
}

.rd-preview-state-link {
    display: inline-block;
    margin-top: 16px;
    color: #2e7d32;
    font-weight: 700;
}

/* Architecture Diagram */
.rd-arch-diagram {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    margin: 20px 0;
}

.rd-arch-box {
    padding: 16px 20px;
    border-radius: 6px;
    border: 2px solid;
    text-align: center;
    max-width: 300px;
}

.rd-arch-box h4 {
    margin: 0 0 8px 0;
    font-size: 1em;
}

.rd-arch-box p {
    margin: 0 0 8px 0;
    font-size: 0.85em;
    color: #666;
}

.rd-arch-box code {
    display: block;
    background: #f5f5f5;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8em;
    margin-top: 8px;
}

.rd-studio {
    border-color: #2e7d32;
    background: #e8f5e9;
}

.rd-platform {
    border-color: #1976d2;
    background: #e3f2fd;
}

.rd-business {
    border-color: #f57c00;
    background: #fff3e0;
}

.rd-arch-arrow {
    font-size: 1.5em;
    color: #999;
}

.rd-flow-section {
    margin-top: 20px;
    padding: 16px;
    background: #f5f5f5;
    border-radius: 6px;
}

.rd-flow-section h4 {
    margin: 0 0 8px 0;
    font-size: 1em;
    color: #333;
}

.rd-flow-section p {
    margin: 0;
    font-size: 0.9em;
    color: #666;
}

/* Status Badge */
.rd-status-badge {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 0.9em;
    margin-bottom: 12px;
}

.rd-phase-badge {
    background: #e3f2fd;
    color: #1976d2;
    border: 1px solid #1976d2;
}

@media (max-width: 760px) {
    .gs-studio-tool-workspaces {
        display: block;
    }

    .gs-studio-tool-workspace-nav {
        margin-bottom: 20px;
    }

    .rd-wizard-progress {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .rd-wizard-step {
        justify-content: flex-start;
    }

    .rd-wizard-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .rd-wizard-actions > * {
        justify-content: center;
        text-align: center;
    }

    .rd-source-panes {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .rd-layout-composer-item {
        grid-template-columns: 1fr;
        align-items: start;
    }

    .rd-layout-item-controls {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: stretch;
    }

    .rd-preview-container {
        padding: 10px;
    }

    .rd-printable-report {
        min-height: 0;
        padding: 26px 22px;
    }

    .rd-report-footer {
        grid-template-columns: 1fr;
    }

    .rd-report-page-number {
        justify-self: start;
    }
}

/* Presentation Designer Workspace */
.rd-presentation-section {
    background: #fff;
    border: 1px solid #dfe5e8;
    border-radius: 6px;
    padding: 18px 20px;
    margin-bottom: 14px;
}

.rd-presentation-section > h3 {
    margin: 0 0 14px 0;
    font-size: 0.85em;
    font-weight: 600;
    color: #455a64;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.rd-presentation-format-options {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.rd-format-option {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 14px 22px;
    border: 2px solid #dfe5e8;
    border-radius: 8px;
    cursor: pointer;
    background: #fafbfc;
    transition: border-color 0.15s, background 0.15s;
    min-width: 88px;
}

.rd-format-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.rd-format-option.rd-format-active {
    border-color: #1976d2;
    background: #e3f0fd;
}

.rd-format-icon {
    font-size: 1.4em;
    line-height: 1;
    color: #78909c;
}

.rd-format-option.rd-format-active .rd-format-icon {
    color: #1565c0;
}

.rd-presentation-page-settings {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    align-items: flex-start;
}

.rd-presentation-page-settings .rd-form-group {
    flex: 1 1 160px;
}

.rd-orientation-options {
    display: flex;
    gap: 8px;
    margin-top: 6px;
}

.rd-orientation-option {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    padding: 6px 12px;
    border: 1px solid #dfe5e8;
    border-radius: 4px;
    background: #fafbfc;
    font-size: 0.9em;
}

.rd-orientation-option:has(input:checked) {
    border-color: #1976d2;
    background: #e3f0fd;
    color: #1565c0;
}

/* Format-specific preview styles */
.rd-printable-report[data-format="list"] .rd-preview-table thead {
    display: none;
}

.rd-printable-report[data-format="list"] .rd-preview-table tbody tr {
    display: block;
    padding: 8px 0;
    border-bottom: 1px solid #e7ecef;
}

.rd-printable-report[data-format="list"] .rd-preview-table tbody td {
    display: inline;
    border: none;
    padding: 0 4px;
}

.rd-printable-report[data-format="list"] .rd-preview-table tbody td::before {
    content: attr(data-label) ' ';
    font-weight: 600;
    color: #607d8b;
}

/* Presentation header/footer in preview */
.rd-report-custom-header {
    padding: 6px 0 12px;
    color: #607d8b;
    font-size: 0.88em;
    border-bottom: 1px solid #e7ecef;
    margin-bottom: 12px;
}

.rd-report-custom-footer-text {
    font-size: 0.85em;
    color: #607d8b;
    margin-bottom: 6px;
    padding-bottom: 6px;
    border-bottom: 1px solid #e7ecef;
}

/* Presentation checkbox grid */
.rd-presentation-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 6px;
}

.rd-presentation-checkbox-option {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-size: 0.9em;
    padding: 4px 0;
}

.rd-presentation-checkbox-option input[type="checkbox"] {
    margin: 0;
}

/* Presentation page dimension controls */
.rd-presentation-page-controls {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
}

.rd-dimension-input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #cfd8dc;
    border-radius: 4px;
    font-size: 0.88em;
    font-family: inherit;
    background: #fafbfc;
}

.rd-dimension-input:focus {
    border-color: #1976d2;
    outline: none;
    background: #fff;
}

/* Organization metadata grid */
.rd-org-metadata-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
}

.rd-org-metadata-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.rd-org-metadata-label {
    font-size: 0.8em;
    font-weight: 600;
    color: #78909c;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.rd-org-metadata-value {
    font-size: 0.95em;
    color: #37474f;
}

.rd-filter-candidate {
    display: grid;
    gap: 4px;
    padding: 6px 8px;
    border: 1px solid transparent;
    border-radius: 4px;
    font-size: 0.86em;
}

.rd-filter-candidate:has(input[type="checkbox"]:checked) {
    border-color: #c8e6c9;
    background: #f1f8e9;
}

.rd-filter-candidate-heading {
    display: flex;
    gap: 6px;
    align-items: center;
    cursor: pointer;
}

.rd-filter-candidate-heading input[type="checkbox"] {
    margin: 0;
}

.rd-filter-value-controls {
    display: flex;
    gap: 4px;
    margin-top: 4px;
}

.rd-filter-value-controls input {
    flex: 1;
    min-width: 0;
    padding: 4px 6px;
    border: 1px solid #cfd8dc;
    border-radius: 3px;
    font-size: 0.9em;
}

.rd-report-context-panel {
    margin-bottom: 16px;
    padding: 18px;
    border: 1px solid #d8e0e5;
    border-radius: 8px;
    background: #fff;
}

.rd-report-context-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.rd-report-context-heading h3,
.rd-report-context-heading p {
    margin: 0;
}

.rd-report-context-heading p {
    margin-top: 4px;
    color: #60717c;
}

.rd-report-context-summary,
.rd-print-context-summary {
    display: grid;
    grid-template-columns: minmax(120px, auto) 1fr;
    gap: 6px 16px;
    margin: 16px 0 0;
}

.rd-report-context-summary dt,
.rd-print-context-summary dt {
    font-weight: 700;
}

.rd-report-context-summary dd,
.rd-print-context-summary dd {
    margin: 0;
}

.rd-context-editor {
    margin-top: 16px;
    border-top: 1px solid #e4e9ec;
    padding-top: 12px;
}

.rd-context-editor-content {
    padding-top: 12px;
}

.rd-context-date-inputs {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.rd-context-date-inputs input {
    width: 100%;
    box-sizing: border-box;
}

.rd-context-print-option {
    display: flex;
    align-items: center;
    gap: 8px;
}

.rd-filter-preview-value-note {
    margin: 4px 0 0 24px;
    color: #60717c;
    font-size: 0.84em;
}

@page {
    size: A4 portrait;
    margin: 14mm;
}

@media print {
    .rd-wizard-progress,
    .rd-wizard-actions {
        display: none !important;
    }

    body * {
        visibility: hidden !important;
    }

    #rd-printable-preview,
    #rd-printable-preview * {
        visibility: visible !important;
    }

    #rd-printable-preview {
        position: absolute;
        inset: 0;
        width: 100%;
        min-height: 0;
        padding: 0;
        border: 0;
        overflow: visible;
        background: #fff;
    }

    .rd-preview-actions,
    .rd-report-context-panel,
    .rd-context-editor,
    #rd-change-context,
    .gs-studio-tool-header,
    .gs-studio-tool-workspace-nav {
        display: none !important;
    }

    .rd-printable-report {
        width: 100%;
        min-height: 0;
        margin: 0;
        padding: 0;
        border: 0;
        box-shadow: none;
    }

    .rd-preview-group-section,
    .rd-preview-table,
    .rd-report-footer {
        break-inside: avoid;
    }

    .rd-preview-table thead {
        display: table-header-group;
    }
}
</style>
