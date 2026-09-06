(function () {
  function safeParseJson(text, fallback) {
    try {
      var parsed = JSON.parse(text || '');
      return parsed && typeof parsed === 'object' ? parsed : fallback;
    } catch (error) {
      return fallback;
    }
  }

  function toKey(text) {
    return String(text || '')
      .toLowerCase()
      .replace(/-/g, '_')
      .replace(/[^a-z0-9_]+/g, '_')
      .replace(/^_+|_+$/g, '');
  }

  function toSlug(text) {
    return toKey(text).replace(/_/g, '-');
  }

  var diffPreview = document.getElementById('gs-diff-preview');
  if (diffPreview) {
    var riskFilter = diffPreview.querySelector('[data-diff-filter="risk"]');
    var ownershipFilter = diffPreview.querySelector('[data-diff-filter="ownership"]');
    var driftFilter = diffPreview.querySelector('[data-diff-filter="drift"]');
    var rows = diffPreview.querySelectorAll('[data-diff-row]');

    function applyDiffFilters() {
      var risk = riskFilter ? riskFilter.value : 'ALL';
      var ownership = ownershipFilter ? ownershipFilter.value : 'ALL';
      var drift = driftFilter ? driftFilter.value : 'ALL';

      rows.forEach(function (row, rowIndex) {
        var riskMatch = risk === 'ALL' || row.getAttribute('data-risk') === risk;
        var ownershipMatch = ownership === 'ALL' || row.getAttribute('data-ownership') === ownership;
        var driftMatch = drift === 'ALL' || row.getAttribute('data-drift') === drift;
        row.hidden = !(riskMatch && ownershipMatch && driftMatch);
      });
    }

    [riskFilter, ownershipFilter, driftFilter].forEach(function (filter) {
      if (!filter) return;
      filter.addEventListener('change', applyDiffFilters);
    });
  }

  var form = document.getElementById('gs-structured-editor-form');
  var appManifestInput = document.getElementById('gs_app_manifest');
  var moduleManifestInput = document.getElementById('gs_module_manifest');
  var viewDefinitionInput = document.getElementById('gs_view_definition');
  var navigationDefinitionInput = document.getElementById('gs_navigation_definition');

  var moduleKeyInput = document.getElementById('gs_se_module_key');
  var moduleDisplayInput = document.getElementById('gs_se_module_display_name');
  var moduleTypeInput = document.getElementById('gs_se_module_type');
  var moduleDescriptionInput = document.getElementById('gs_se_module_description');
  var routePathInput = document.getElementById('gs_se_route_path');

  var fieldsBody = document.getElementById('gs-fields-body');
  var addFieldButton = document.getElementById('gs-add-field');
  var fieldsStateInput = document.getElementById('gs_se_fields_state');

  var createIntentInput = document.getElementById('gs_se_create_intent');
  var viewKindInput = document.getElementById('gs_se_view_kind');
  var tableEditModeInput = document.getElementById('gs_se_table_edit_mode');
  var layoutCompactInput = document.getElementById('gs_se_layout_compact');
  var viewColumnsWrap = document.getElementById('gs-view-columns');
  var viewColumnsStateInput = document.getElementById('gs_se_view_columns_state');
  var layoutStateInput = document.getElementById('gs_se_layout_state');
  var layoutRelationsStateInput = document.getElementById('gs_se_layout_relations_state');
  var previewModeInput = document.getElementById('gs_se_preview_mode');
  var previousBundleInput = document.getElementById('gs_se_previous_bundle');
  var currentBundleInput = document.getElementById('gs_se_current_bundle');

  var navSectionInput = document.getElementById('gs_se_nav_section');
  var navGroupInput = document.getElementById('gs_se_nav_group');
  var navLabelInput = document.getElementById('gs_se_nav_label');
  var navTargetInput = document.getElementById('gs_se_nav_target');
  var navIconInput = document.getElementById('gs_se_nav_icon');
  var navOrderInput = document.getElementById('gs_se_nav_order');
  var navVisibleInput = document.getElementById('gs_se_nav_visible');
  var compileReasonInput = document.getElementById('gs_compile_reason');
  var compileRiskAckInput = document.getElementById('gs_compile_risk_ack');
  var migrationOverrideInput = document.getElementById('gs_migration_override');
  var migrationOverrideReasonInput = document.getElementById('gs_migration_override_reason');
  var impactConfirmationInput = document.getElementById('gs_impact_confirmation');
  var impactAcknowledgedInput = document.getElementById('gs_impact_acknowledged');
  var simulationOverrideInput = document.getElementById('gs_simulation_override');
  var simulationOverrideReasonInput = document.getElementById('gs_simulation_override_reason');

  var changeSummaryBody = document.getElementById('gs-change-summary-body');
  var changeTotalEl = document.getElementById('gs-change-total');
  var changeHighEl = document.getElementById('gs-change-high');
  var changeMediumEl = document.getElementById('gs-change-medium');
  var changeLowEl = document.getElementById('gs-change-low');
  var changeRiskNote = document.getElementById('gs-change-risk-note');
  var migrationPlanBody = document.getElementById('gs-migration-plan-body');
  var migrationWarning = document.getElementById('gs-migration-warning');
  var impactAnalysisBody = document.getElementById('gs-impact-analysis-body');
  var impactWarning = document.getElementById('gs-impact-warning');
  var simulationPreviewBody = document.getElementById('gs-simulation-preview-body');
  var simulationWarning = document.getElementById('gs-simulation-warning');
  var visualBuilderPreviewToggle = document.getElementById('gs_visual_preview_toggle');
  var layoutCanvas = document.getElementById('gs-layout-canvas');
  var componentPalette = document.getElementById('gs-component-palette');
  var addViewButton = document.getElementById('btn-add-view');
  var viewList = document.getElementById('view-list');
  var recommendedTemplatesList = document.getElementById('gs-recommended-templates');
  var allTemplatesList = document.getElementById('gs-all-templates');
  var saveTemplateButton = document.getElementById('btn-save-template');
  var userTemplatesList = document.getElementById('gs-user-templates');
  var suggestionsList = document.getElementById('gs-suggestions');
  var exportAppButton = document.getElementById('btn-export-app');
  var importAppButton = document.getElementById('btn-import-app');
  var importAppInput = document.getElementById('input-import-app');
  var layoutItemsList = document.getElementById('gs-layout-items');
  var bindingSchemaTree = document.getElementById('gs-binding-schema-tree');
  var componentConfigPanel = document.getElementById('gs-component-config');
  var layoutRelationsList = document.getElementById('gs-layout-relations');
  var addRelationButton = document.getElementById('gs-add-relation');
  var layoutWarning = document.getElementById('gs-layout-warning');
  var layoutDragState = {
    itemId: '',
    sourceRowIndex: -1,
    sourceColumnIndex: -1,
    targetRowIndex: -1,
    targetColumnIndex: -1,
    dropMode: 'row-end',
    committed: false
  };

  var studioModeInput = document.getElementById('gs_studio_mode');
  var studioModeChip = document.getElementById('gs-studio-mode-chip');
  var studioModeEditLabel = <?= json_encode($gs('studio_mode_edit_existing')) ?>;
  var tableEditModeNoteViewOnly = <?= json_encode($gs('table_edit_mode_note_view_only')) ?>;
  var tableEditModeNoteDirectDb = <?= json_encode($gs('table_edit_mode_note_direct_db')) ?>;
  var categoryFieldsLabel = <?= json_encode($gs('field_editor')) ?>;
  var categoryViewLabel = <?= json_encode($gs('view_config_editor')) ?>;
  var categoryNavLabel = <?= json_encode($gs('navigation_editor')) ?>;
  var emptyChangesLabel = <?= json_encode($gs('change_summary_empty')) ?>;
  var emptyMigrationLabel = <?= json_encode($gs('migration_empty')) ?>;
  var emptyImpactLabel = <?= json_encode($gs('impact_empty')) ?>;
  var emptySimulationLabel = <?= json_encode($gs('simulation_empty')) ?>;
  var impactLabels = {
    high: <?= json_encode($gs('impact_high')) ?>,
    medium: <?= json_encode($gs('impact_medium')) ?>,
    low: <?= json_encode($gs('impact_low')) ?>
  };
  var severityLabels = {
    high: <?= json_encode($gs('change_severity_breaking')) ?>,
    medium: <?= json_encode($gs('change_severity_additive')) ?>,
    low: <?= json_encode($gs('change_severity_safe')) ?>
  };
  var breakingChangesWarningText = <?= json_encode($gs('breaking_changes_warning')) ?>;
  var breakingChangesBlockedText = <?= json_encode($gs('breaking_changes_blocked')) ?>;
  var rollbackSnapshotNoticeText = <?= json_encode($gsBatch1('rollback_snapshot_notice')) ?>;
  var migrationStrategyLabels = {
    safe: <?= json_encode($gs('migration.strategy.safe')) ?>,
    destructive: <?= json_encode($gs('migration.strategy.destructive')) ?>,
    requires_migration: <?= json_encode($gs('migration.strategy.requires_migration')) ?>
  };
  var impactCategoryLabels = {
    field_added: <?= json_encode($gs('impact_change')) ?>,
    field_removed: <?= json_encode($gs('impact_change')) ?>,
    field_modified: <?= json_encode($gs('impact_change')) ?>,
    view_changed: <?= json_encode($gs('view_config_editor')) ?>,
    nav_changed: <?= json_encode($gs('navigation_editor')) ?>
  };
  var removeFieldLabel = <?= json_encode($gs('remove_field')) ?>;
  var simulationReasonLabels = {
    missing_required_field: <?= json_encode($gs('simulation_reason_missing_required_field')) ?>,
    filter_references_removed_field: <?= json_encode($gs('simulation_reason_filter_references_removed_field')) ?>,
    navigation_invalid_route: <?= json_encode($gs('simulation_reason_navigation_invalid_route')) ?>
  };
  var visualBuilderLabels = {
    empty: <?= json_encode($gs('visual_builder_empty')) ?>,
    overlap: <?= json_encode($gs('visual_builder_warning_overlap')) ?>,
    bounds: <?= json_encode($gs('visual_builder_warning_bounds')) ?>,
    bindingInvalid: <?= json_encode($gs('visual_builder_warning_binding_invalid')) ?>,
    move: <?= json_encode($gs('builder_item_move')) ?>,
    remove: <?= json_encode($gs('builder_item_remove')) ?>,
    previewMode: <?= json_encode($gs('visual_builder_preview_toggle')) ?>,
    templateReplaceConfirm: <?= json_encode($gs('visual_builder_template_replace_confirm')) ?>,
    templateNamePrompt: <?= json_encode($gs('visual_builder_template_name_prompt')) ?>
  };
  var visualBuilderPhaseOneLabels = {
    addComponent: <?= json_encode($gs('visual_builder_add_component')) ?>,
    preview: <?= json_encode($gs('visual_builder_preview')) ?>,
    defaultKpi: <?= json_encode($gs('visual_builder_default_kpi')) ?>,
    defaultTable: <?= json_encode($gs('visual_builder_default_table')) ?>,
    defaultForm: <?= json_encode($gs('visual_builder_default_form')) ?>,
    defaultText: <?= json_encode($gs('visual_builder_default_text')) ?>
  };
  var visualBuilderTemplateLabels = {
    metric1: <?= json_encode($gs('visual_builder_template_metric_1')) ?>,
    metric2: <?= json_encode($gs('visual_builder_template_metric_2')) ?>,
    metric3: <?= json_encode($gs('visual_builder_template_metric_3')) ?>,
    metric4: <?= json_encode($gs('visual_builder_template_metric_4')) ?>,
    recommendedTitle: <?= json_encode($gs('visual_builder_recommended_templates_title')) ?>,
    allTitle: <?= json_encode($gs('visual_builder_all_templates_title')) ?>,
    kpiGrid: <?= json_encode($gs('visual_builder_template_kpi_grid')) ?>,
    tableView: <?= json_encode($gs('visual_builder_template_table_view')) ?>,
    formEntry: <?= json_encode($gs('visual_builder_template_form_entry')) ?>
  };
  var visualBuilderSuggestionLabels = {
    title: <?= json_encode($gs('visual_builder_suggestions_title')) ?>,
    addKpi: <?= json_encode($gs('visual_builder_suggestion_add_kpi')) ?>,
    addTable: <?= json_encode($gs('visual_builder_suggestion_add_table')) ?>,
    addForm: <?= json_encode($gs('visual_builder_suggestion_add_form')) ?>,
    useTemplate: <?= json_encode($gs('visual_builder_suggestion_use_template')) ?>,
    none: <?= json_encode($gs('visual_builder_suggestion_none')) ?>
  };
  var viewManagerLabels = {
    title: <?= json_encode($gs('visual_builder_views_title')) ?>,
    addView: <?= json_encode($gs('visual_builder_add_view')) ?>,
    namePrompt: <?= json_encode($gs('visual_builder_view_name_prompt')) ?>,
    defaultViewName: <?= json_encode($gs('visual_builder_view_default_name')) ?>,
    empty: <?= json_encode($gs('visual_builder_view_none')) ?>
  };
  var packagingLabels = {
    exportApp: <?= json_encode($gs('visual_builder_export_app')) ?>,
    importApp: <?= json_encode($gs('visual_builder_import_app')) ?>,
    exportMissing: <?= json_encode($gs('visual_builder_export_missing')) ?>,
    importInvalid: <?= json_encode($gs('visual_builder_import_invalid')) ?>,
    importFailed: <?= json_encode($gs('visual_builder_import_failed')) ?>,
    importSuccess: <?= json_encode($gs('visual_builder_import_success')) ?>,
    fileName: <?= json_encode($gs('visual_builder_export_filename')) ?>
  };
  var componentLabels = {
    filter: <?= json_encode($gs('component.filter')) ?>,
    table: <?= json_encode($gs('component.table')) ?>,
    form: <?= json_encode($gs('component.form')) ?>,
    kpi_card: <?= json_encode($gs('component.kpi_card')) ?>,
    text_block: <?= json_encode($gs('component.text_block')) ?>
  };
  var componentConfigLabels = {
    itemId: <?= json_encode($gs('component_config.item_id')) ?>,
    width: <?= json_encode($gs('component_config.width')) ?>,
    widthNarrower: <?= json_encode($gs('component_config.width_narrower')) ?>,
    widthWider: <?= json_encode($gs('component_config.width_wider')) ?>,
    filterLabel: <?= json_encode($gs('component_config.filter_label')) ?>,
    filterField: <?= json_encode($gs('component_config.filter_field')) ?>,
    filterPlaceholder: <?= json_encode($gs('component_config.filter_placeholder')) ?>,
    bindingSource: <?= json_encode($gs('visual_builder_binding_source')) ?>,
    bindingPicker: <?= json_encode($gs('visual_builder_binding_picker')) ?>,
    group: <?= json_encode($gs('visual_builder_group')) ?>,
    bindingPlaceholder: <?= json_encode($gs('visual_builder_binding_placeholder')) ?>,
    tableTitle: <?= json_encode($gs('component_config.table_title')) ?>,
    tableRows: <?= json_encode($gs('component_config.table_rows')) ?>,
    tableDensity: <?= json_encode($gs('component_config.table_density')) ?>,
    formTitle: <?= json_encode($gs('component_config.form_title')) ?>,
    formSubmitLabel: <?= json_encode($gs('component_config.form_submit_label')) ?>,
    defaultSubmit: <?= json_encode($gs('component_config.default_submit')) ?>,
    formShowRequired: <?= json_encode($gs('component_config.form_show_required')) ?>,
    kpiLabel: <?= json_encode($gs('component_config.kpi_label')) ?>,
    kpiValue: <?= json_encode($gs('component_config.kpi_value')) ?>,
    kpiDelta: <?= json_encode($gs('component_config.kpi_delta')) ?>,
    textTitle: <?= json_encode($gs('component_config.text_title')) ?>,
    textBody: <?= json_encode($gs('component_config.text_body')) ?>,
    textAlign: <?= json_encode($gs('component_config.text_align')) ?>,
    optCompact: <?= json_encode($gs('component_config.option.compact')) ?>,
    optComfortable: <?= json_encode($gs('component_config.option.comfortable')) ?>,
    optLeft: <?= json_encode($gs('component_config.option.left')) ?>,
    optCenter: <?= json_encode($gs('component_config.option.center')) ?>,
    optRight: <?= json_encode($gs('component_config.option.right')) ?>,
    openView: <?= json_encode($gs('component_config.open_view')) ?>,
    openViewNone: <?= json_encode($gs('component_config.open_view_none')) ?>,
    selectHint: <?= json_encode($gs('visual_builder_select_component_hint')) ?>
  };
  var relationLabels = {
    source: <?= json_encode($gs('visual_builder_relation_source')) ?>,
    target: <?= json_encode($gs('visual_builder_relation_target')) ?>,
    type: <?= json_encode($gs('visual_builder_relation_type')) ?>,
    affectsTable: <?= json_encode($gs('visual_builder_relation_affects_table')) ?>,
    filterToTable: <?= json_encode($gs('visual_builder_relation_filter_to_table')) ?>,
    filterToKpi: <?= json_encode($gs('visual_builder_relation_filter_to_kpi')) ?>,
    formRefreshTable: <?= json_encode($gs('visual_builder_relation_form_refresh_table')) ?>,
    tableToKpiDerived: <?= json_encode($gs('visual_builder_relation_table_to_kpi_derived')) ?>,
    empty: <?= json_encode($gs('visual_builder_relation_empty')) ?>,
    up: <?= json_encode($gs('visual_builder_reorder_up')) ?>,
    down: <?= json_encode($gs('visual_builder_reorder_down')) ?>
  };
  var interactionLabels = {
    duplicate: <?= json_encode($gs('component.duplicate')) ?>,
    del: <?= json_encode($gs('component.delete')) ?>,
    addInline: <?= json_encode($gs('visual_builder_add_component_inline')) ?>,
    typeKpi: <?= json_encode($gs('component.type.kpi_card')) ?>,
    typeTable: <?= json_encode($gs('component.type.table')) ?>,
    typeForm: <?= json_encode($gs('component.type.form')) ?>,
    typeFilter: <?= json_encode($gs('component.type.filter')) ?>,
    typeText: <?= json_encode($gs('component.type.text_block')) ?>,
    feedbackAdd: <?= json_encode($gs('feedback.add')) ?>,
    feedbackMove: <?= json_encode($gs('feedback.move')) ?>,
    feedbackResize: <?= json_encode($gs('feedback.resize')) ?>,
    feedbackTemplate: <?= json_encode($gs('feedback.template')) ?>
  };
  var bindingErrorLabels = {
    invalid_path: <?= json_encode($gs('visual_builder_binding_error.invalid_path')) ?>,
    filter: <?= json_encode($gs('error.layout_binding_type_filter')) ?>,
    table: <?= json_encode($gs('visual_builder_binding_error.table')) ?>,
    form: <?= json_encode($gs('visual_builder_binding_error.form')) ?>,
    kpi: <?= json_encode($gs('visual_builder_binding_error.kpi')) ?>,
    text: <?= json_encode($gs('visual_builder_binding_error.text')) ?>
  };
  var componentOrder = ['kpi_card', 'table', 'form', 'text_block'];

  var structuredState = {
    fields: [],
    columns: []
  };
  var builderState = {
    viewKind: 'table',
    layout: {
      type: 'grid',
      columns: 12,
      rows: 'auto',
      items: [],
      relations: []
    },
    previewMode: false,
    warning: '',
    selectedItemId: '',
    bindingErrors: {},
    componentState: {}
  };
  window.gsStructuredState = structuredState;
  window.gsBuilderState = builderState;
  var multiViewState = {
    activeViewId: '',
    views: []
  };
  var multiViewStorageKey = 'ops.gui_studio.multi_views';
  var refreshStructuredEditor = null;

  if (form && appManifestInput && moduleManifestInput && viewDefinitionInput && navigationDefinitionInput) {
    function fieldMap(bundle) {
      var map = {};
      var moduleManifest = bundle.module_manifest || {};
      var viewDefinition = bundle.view_definition || {};
      var source = Array.isArray(moduleManifest.fields) ? moduleManifest.fields : (Array.isArray(viewDefinition.fields) ? viewDefinition.fields : []);
      source.forEach(function (field) {
        if (!field || typeof field !== 'object') return;
        var key = toKey(field.key || field.name || '');
        if (!key) return;
        map[key] = {
          key: key,
          type: String(field.type || 'string').toLowerCase(),
          required: !!field.required,
          default: String(field.default || '')
        };
      });
      return map;
    }

    function computeStructuredDiff(previousBundle, currentBundle) {
      var changes = [];
      var prevFields = fieldMap(previousBundle || {});
      var currFields = fieldMap(currentBundle || {});
      var fieldKeys = Object.keys(prevFields).concat(Object.keys(currFields)).filter(function (key, index, arr) {
        return arr.indexOf(key) === index;
      }).sort();

      fieldKeys.forEach(function (key) {
        var beforeField = prevFields[key];
        var afterField = currFields[key];
        var path = 'module.fields.' + key;
        if (!beforeField && afterField) {
          changes.push({ type: 'field_added', path: path, before: null, after: afterField, impact: 'low' });
          return;
        }
        if (beforeField && !afterField) {
          changes.push({ type: 'field_removed', path: path, before: beforeField, after: null, impact: 'high' });
          return;
        }
        if (!beforeField || !afterField) return;

        var impact = null;
        if (String(beforeField.type) !== String(afterField.type)) {
          impact = 'high';
        } else if (!!beforeField.required !== !!afterField.required) {
          impact = 'medium';
        } else if (JSON.stringify(beforeField) !== JSON.stringify(afterField)) {
          impact = 'low';
        }

        if (impact) {
          changes.push({ type: 'field_modified', path: path, before: beforeField, after: afterField, impact: impact });
        }
      });

      var prevView = ((previousBundle || {}).view_definition || {}).view || {};
      var currView = ((currentBundle || {}).view_definition || {}).view || {};
      if (String(prevView.view_kind || '') !== String(currView.view_kind || '')) {
        changes.push({ type: 'view_changed', path: 'view.view_kind', before: String(prevView.view_kind || ''), after: String(currView.view_kind || ''), impact: 'medium' });
      }
      if (String(prevView.route_path || '') !== String(currView.route_path || '')) {
        changes.push({ type: 'view_changed', path: 'view.route_path', before: String(prevView.route_path || ''), after: String(currView.route_path || ''), impact: 'high' });
      }

      var prevNav = ((previousBundle || {}).navigation_definition || {}).navigation || {};
      var currNav = ((currentBundle || {}).navigation_definition || {}).navigation || {};
      if (String(prevNav.url || '') !== String(currNav.url || '')) {
        changes.push({ type: 'nav_changed', path: 'navigation.url', before: String(prevNav.url || ''), after: String(currNav.url || ''), impact: 'high' });
      }
      if (String(prevNav.label || '') !== String(currNav.label || '')) {
        changes.push({ type: 'nav_changed', path: 'navigation.label', before: String(prevNav.label || ''), after: String(currNav.label || ''), impact: 'low' });
      }

      var summary = { total: changes.length, high: 0, medium: 0, low: 0 };
      changes.forEach(function (change) {
        var impact = String(change.impact || 'low').toLowerCase();
        if (!Object.prototype.hasOwnProperty.call(summary, impact)) {
          impact = 'low';
        }
        summary[impact] += 1;
      });

      return { changes: changes, summary: summary };
    }

    function buildMigrationPlan(diff) {
      var plan = [];
      var changes = diff && Array.isArray(diff.changes) ? diff.changes : [];
      changes.forEach(function (change) {
        var path = String(change.path || '');
        if (path.indexOf('module.fields.') !== 0) {
          return;
        }
        var field = path.replace('module.fields.', '');
        if (!field) return;

        if (change.type === 'field_added') {
          plan.push({ action: 'add_column', field: field, strategy: 'safe', risk: 'low' });
          return;
        }
        if (change.type === 'field_removed') {
          plan.push({ action: 'remove_column', field: field, strategy: 'destructive', risk: 'high' });
          return;
        }
        if (change.type !== 'field_modified') {
          return;
        }

        var before = change.before && typeof change.before === 'object' ? change.before : {};
        var after = change.after && typeof change.after === 'object' ? change.after : {};
        if (String(before.type || '') !== String(after.type || '')) {
          plan.push({ action: 'modify_column', field: field, strategy: 'requires_migration', risk: 'high' });
          return;
        }
        if (!!before.required !== !!after.required) {
          plan.push({ action: 'modify_column', field: field, strategy: 'safe', risk: 'medium' });
        }
      });
      return plan;
    }

    function buildDependencyGraph(bundle) {
      var moduleManifest = (bundle && bundle.module_manifest) || {};
      var viewDefinition = (bundle && bundle.view_definition) || {};
      var navigationDefinition = (bundle && bundle.navigation_definition) || {};

      var module = moduleManifest.module || {};
      var view = viewDefinition.view || {};
      var dataContract = viewDefinition.data_contract || {};
      var nav = navigationDefinition.navigation || {};

      var moduleKey = toKey(module.module_key || 'generated_module') || 'generated_module';
      var viewKey = toKey(view.view_key || 'index') || 'index';
      var viewRef = 'view:' + viewKey;
      var routePath = String(view.route_path || module.route_base || ('/apps/' + toSlug(moduleKey))).trim();
      var routeRef = 'route:' + routePath;
      var navRef = 'navigation:' + (toKey(nav.key || (moduleKey + '_nav')) || 'navigation');

      var viewFields = Array.isArray(view.fields) ? view.fields.map(toKey).filter(Boolean) : [];
      var filterFields = Array.isArray(dataContract.allowed_filters) ? dataContract.allowed_filters.map(toKey).filter(Boolean) : [];

      var fields = fieldMap(bundle || {});
      var fieldGraph = {};
      Object.keys(fields).forEach(function (fieldKey) {
        var views = viewFields.length === 0 || viewFields.indexOf(fieldKey) !== -1 ? [viewRef] : [];
        var filters = filterFields.length === 0 || filterFields.indexOf(fieldKey) !== -1 ? ['filter:search'] : [];
        var adapters = ['adapter:' + ((toKey(moduleKey).replace(/(^|_)([a-z])/g, function (_, p1, p2) {
          return p2.toUpperCase();
        }) || 'Module') + 'Adapter')];
        fieldGraph[fieldKey] = {
          views: views,
          filters: filters,
          navigation: [],
          adapters: adapters
        };
      });

      return {
        fields: fieldGraph,
        module_route_navigation: {
          module: 'module:' + moduleKey,
          route: routeRef,
          navigation_refs: [navRef]
        }
      };
    }

    function computeImpact(diff, graph) {
      var impacts = [];
      var changes = diff && Array.isArray(diff.changes) ? diff.changes : [];
      var fieldGraph = graph && graph.fields && typeof graph.fields === 'object' ? graph.fields : {};
      var navRefs = graph && graph.module_route_navigation && Array.isArray(graph.module_route_navigation.navigation_refs)
        ? graph.module_route_navigation.navigation_refs
        : [];
      var routeRef = graph && graph.module_route_navigation && graph.module_route_navigation.route
        ? String(graph.module_route_navigation.route)
        : 'route:unknown';
      var defaultViews = [];
      var defaultFilters = [];
      var defaultAdapters = [];
      Object.keys(fieldGraph).forEach(function (fieldKey) {
        var usage = fieldGraph[fieldKey] || {};
        if (Array.isArray(usage.views)) defaultViews = defaultViews.concat(usage.views);
        if (Array.isArray(usage.filters)) defaultFilters = defaultFilters.concat(usage.filters);
        if (Array.isArray(usage.adapters)) defaultAdapters = defaultAdapters.concat(usage.adapters);
      });
      defaultViews = defaultViews.filter(function (item, idx, arr) { return !!item && arr.indexOf(item) === idx; });
      defaultFilters = defaultFilters.filter(function (item, idx, arr) { return !!item && arr.indexOf(item) === idx; });
      defaultAdapters = defaultAdapters.filter(function (item, idx, arr) { return !!item && arr.indexOf(item) === idx; });

      changes.forEach(function (change) {
        var path = String(change.path || '');
        var entry = {
          change: String(change.type || ''),
          path: path,
          affects: [],
          severity: String(change.impact || 'low').toLowerCase()
        };

        if (path.indexOf('module.fields.') === 0) {
          var field = path.replace('module.fields.', '');
          var usage = fieldGraph[field] || {};
          entry.field = field;
          entry.affects = []
            .concat(Array.isArray(usage.views) ? usage.views : [])
            .concat(Array.isArray(usage.filters) ? usage.filters : [])
            .concat(Array.isArray(usage.navigation) ? usage.navigation : [])
            .concat(Array.isArray(usage.adapters) ? usage.adapters : []);
          if (entry.change === 'field_removed') {
            if (entry.affects.length === 0) {
              entry.affects = []
                .concat(defaultViews.length ? defaultViews : ['view:index'])
                .concat(defaultFilters.length ? defaultFilters : ['filter:search'])
                .concat(defaultAdapters.length ? defaultAdapters : ['adapter:ModuleAdapter']);
            }
            entry.severity = 'high';
          }
          impacts.push(entry);
          return;
        }

        if (entry.change === 'view_changed' && path === 'view.route_path') {
          entry.affects = [routeRef].concat(navRefs);
          entry.severity = 'high';
          impacts.push(entry);
          return;
        }

        if (entry.change === 'nav_changed') {
          entry.affects = navRefs.slice();
          entry.severity = path === 'navigation.url' ? 'high' : 'medium';
          impacts.push(entry);
        }
      });

      return impacts;
    }

    function simulateFutureState(previousBundle, currentBundle, diff) {
      var previousFieldMap = fieldMap(previousBundle || {});
      var currentFieldMap = fieldMap(currentBundle || {});
      var currentView = ((currentBundle || {}).view_definition || {}).view || {};
      var currentDataContract = ((currentBundle || {}).view_definition || {}).data_contract || {};
      var currentNavigation = ((currentBundle || {}).navigation_definition || {}).navigation || {};

      var previousKeys = Object.keys(previousFieldMap);
      var currentKeys = Object.keys(currentFieldMap);
      var removedFields = previousKeys.filter(function (key) { return currentKeys.indexOf(key) === -1; }).sort();
      var newFields = currentKeys.filter(function (key) { return previousKeys.indexOf(key) === -1; }).sort();

      var viewKey = toKey(currentView.view_key || 'index') || 'index';
      var routePath = String(currentView.route_path || '').trim();
      var viewFields = Array.isArray(currentView.fields) ? currentView.fields.map(toKey).filter(Boolean) : [];
      if (viewFields.length === 0) {
        viewFields = currentKeys.slice();
      }

      var viewsAfter = [{
        view: 'view:' + viewKey,
        route: routePath,
        fields: viewFields.slice()
      }];

      var brokenViews = [];
      var warnings = [];

      (Array.isArray(currentView.fields) ? currentView.fields : []).forEach(function (fieldName) {
        var field = toKey(fieldName);
        if (!field) return;
        if (!Object.prototype.hasOwnProperty.call(currentFieldMap, field)) {
          brokenViews.push({ view: 'view:' + viewKey, reason: 'missing_required_field', field: field });
          warnings.push('view_missing_field:' + field);
        }
      });

      var allowedFilters = Array.isArray(currentDataContract.allowed_filters) ? currentDataContract.allowed_filters : [];
      allowedFilters.forEach(function (fieldName) {
        var field = toKey(fieldName);
        if (!field) return;
        if (!Object.prototype.hasOwnProperty.call(currentFieldMap, field)) {
          brokenViews.push({ view: 'view:' + viewKey, reason: 'filter_references_removed_field', field: field });
          warnings.push('filter_removed_field:' + field);
        }
      });

      var navigationUrl = String(currentNavigation.url || '').trim();
      if (navigationUrl && routePath && navigationUrl !== routePath) {
        brokenViews.push({
          view: 'navigation:' + (toKey(currentNavigation.key || 'navigation') || 'navigation'),
          reason: 'navigation_invalid_route',
          expected_route: routePath,
          actual_route: navigationUrl
        });
        warnings.push('navigation_invalid_route');
      }

      (diff && Array.isArray(diff.changes) ? diff.changes : []).forEach(function (change) {
        if (!change || typeof change !== 'object') return;
        var type = String(change.type || '');
        var path = String(change.path || '');
        if (type === 'field_removed' && path.indexOf('module.fields.') === 0) {
          warnings.push('field_removed:' + path.replace('module.fields.', ''));
        }
        if (type === 'view_changed' && path === 'view.route_path') {
          warnings.push('route_changed');
        }
      });

      return {
        views_after: viewsAfter,
        broken_views: brokenViews,
        removed_fields: removedFields,
        new_fields: newFields,
        warnings: warnings.filter(function (item, idx, arr) { return arr.indexOf(item) === idx; })
      };
    }

    function renderSimulationPreview(simulation) {
      if (!simulationPreviewBody) return;
      var data = simulation && typeof simulation === 'object' ? simulation : {};
      var viewsAfter = Array.isArray(data.views_after) ? data.views_after : [];
      var brokenViews = Array.isArray(data.broken_views) ? data.broken_views : [];
      var removedFields = Array.isArray(data.removed_fields) ? data.removed_fields : [];
      var newFields = Array.isArray(data.new_fields) ? data.new_fields : [];

      if (typeof window.gsRenderSimulationPreviewRows === 'function') {
        window.gsRenderSimulationPreviewRows({
          body: simulationPreviewBody,
          viewsAfter: viewsAfter,
          brokenViews: brokenViews,
          removedFields: removedFields,
          newFields: newFields,
          simulationReasonLabels: simulationReasonLabels,
          emptySimulationLabel: emptySimulationLabel
        });
      }

      var hasBrokenViews = brokenViews.length > 0;
      if (simulationWarning) {
        simulationWarning.style.display = hasBrokenViews ? 'block' : 'none';
      }
      if (simulationOverrideInput) {
        simulationOverrideInput.required = hasBrokenViews;
      }
      if (simulationOverrideReasonInput) {
        simulationOverrideReasonInput.required = hasBrokenViews;
      }
    }

    function renderImpactAnalysis(impactRows) {
      if (!impactAnalysisBody) return;
      var rows = Array.isArray(impactRows) ? impactRows : [];
      var hasHighImpact = rows.some(function (row) {
        return String(row && row.severity || 'low').toLowerCase() === 'high';
      });

      if (typeof window.gsRenderImpactAnalysisRows === 'function') {
        window.gsRenderImpactAnalysisRows({
          body: impactAnalysisBody,
          rows: rows,
          impactLabels: impactLabels,
          impactCategoryLabels: impactCategoryLabels,
          emptyImpactLabel: emptyImpactLabel
        });
      }

      if (impactWarning) {
        impactWarning.style.display = hasHighImpact ? 'block' : 'none';
      }
      if (impactConfirmationInput) {
        impactConfirmationInput.required = hasHighImpact;
      }
      if (impactAcknowledgedInput) {
        impactAcknowledgedInput.required = hasHighImpact;
      }
    }

    function renderMigrationPlan(plan) {
      if (!migrationPlanBody) return;
      var hasDestructive = (plan || []).some(function (step) {
        return String(step.strategy || '') === 'destructive';
      });

      if (typeof window.gsRenderMigrationPlanRows === 'function') {
        window.gsRenderMigrationPlanRows({
          body: migrationPlanBody,
          plan: Array.isArray(plan) ? plan : [],
          migrationStrategyLabels: migrationStrategyLabels,
          impactLabels: impactLabels,
          emptyMigrationLabel: emptyMigrationLabel
        });
      }

      if (migrationWarning) {
        migrationWarning.style.display = hasDestructive ? 'block' : 'none';
      }
      if (migrationOverrideInput) {
        migrationOverrideInput.required = hasDestructive;
      }
      if (migrationOverrideReasonInput) {
        migrationOverrideReasonInput.required = hasDestructive;
      }
    }

    function renderChangeSummary(diff) {
      if (!changeSummaryBody) return;
      var summary = diff && diff.summary ? diff.summary : { total: 0, high: 0, medium: 0, low: 0 };
      if (changeTotalEl) changeTotalEl.textContent = String(summary.total || 0);
      if (changeHighEl) changeHighEl.textContent = String(summary.high || 0);
      if (changeMediumEl) changeMediumEl.textContent = String(summary.medium || 0);
      if (changeLowEl) changeLowEl.textContent = String(summary.low || 0);

      var changes = diff && Array.isArray(diff.changes) ? diff.changes : [];
      if (typeof window.gsRenderChangeSummaryRows === 'function') {
        window.gsRenderChangeSummaryRows({
          body: changeSummaryBody,
          changes: changes,
          impactLabels: impactLabels,
          severityLabels: severityLabels,
          categoryFieldsLabel: categoryFieldsLabel,
          categoryViewLabel: categoryViewLabel,
          categoryNavLabel: categoryNavLabel,
          emptyChangesLabel: emptyChangesLabel
        });
      }

      var requiresEscalation = changes.some(function (change) {
        return change.type === 'field_removed' || (change.type === 'view_changed' && change.path === 'view.route_path');
      });
      var hasBreakingChanges = (summary.high || 0) > 0;

      // Show/hide breaking changes banner
      var breakingBanner = document.getElementById('gs-breaking-changes-banner');
      if (breakingBanner) {
        breakingBanner.style.display = hasBreakingChanges ? 'block' : 'none';
      }

      // Show rollback snapshot notice in apply form
      var rollbackNotice = document.getElementById('gs-rollback-snapshot-notice');
      if (rollbackNotice) {
        rollbackNotice.style.display = hasBreakingChanges ? 'block' : 'none';
      }

      if (changeRiskNote) {
        changeRiskNote.style.display = requiresEscalation ? 'block' : 'none';
      }
      if (compileReasonInput) {
        compileReasonInput.required = requiresEscalation;
      }
      if (compileRiskAckInput) {
        compileRiskAckInput.required = requiresEscalation;
      }
    }

    function normalizeField(field) {
      var key = toKey(field && (field.key || field.name || ''));
      return {
        key: key,
        label: key !== '' ? key.replace(/_/g, ' ') : '',
        type: String(field && field.type || 'string').toLowerCase(),
        required: !!(field && field.required),
        default: String(field && (field.default || '')),
        options: Array.isArray(field && field.options) ? field.options.map(String) : []
      };
    }

    function defaultComponentForView(viewKind) {
      return String(viewKind || 'table').toLowerCase() === 'form' ? 'form' : 'table';
    }

    function defaultBindingForComponent(component) {
      if (component === 'filter') return 'module.rows';
      if (component === 'table') return 'module.rows';
      if (component === 'form') return 'module.fields';
      if (component === 'kpi_card') return 'module.metrics.total';
      return 'module.description';
    }

    function currentFieldKeys() {
      return structuredState.fields.map(function (field) {
        return String(field.key || '');
      }).filter(Boolean);
    }

    var componentRegistry = {
      filter: {
        requiredProps: ['label', 'field', 'placeholder'],
        defaultConfig: function () {
          return {
            label: componentLabels.filter,
            field: '',
            placeholder: 'Type to filter'
          };
        },
        configFields: function () {
          return [
            { key: 'label', label: componentConfigLabels.filterLabel, type: 'text' },
            { key: 'field', label: componentConfigLabels.filterField, type: 'text' },
            { key: 'placeholder', label: componentConfigLabels.filterPlaceholder, type: 'text' }
          ];
        },
        render: function (ctx) {
          var props = ctx.item.props || {};
          var runtimeState = ctx.runtimeState || {};
          var wrap = document.createElement('div');
          wrap.className = 'gs-card gs-form';

          var label = document.createElement('label');
          label.className = 'gs-meta';
          label.textContent = String(props.label || componentLabels.filter);
          wrap.appendChild(label);

          var rows = resolveBindingValue(ctx.item.data_binding, ctx.previewData);
          if (!Array.isArray(rows)) rows = [];
          var fieldKeys = [];
          rows.forEach(function (row) {
            if (!row || typeof row !== 'object') return;
            Object.keys(row).forEach(function (key) {
              if (fieldKeys.indexOf(key) === -1) fieldKeys.push(key);
            });
          });
          if (fieldKeys.length === 0) {
            fieldKeys = ctx.columns.slice();
          }
          if (fieldKeys.length === 0) {
            fieldKeys = currentFieldKeys();
          }

          var select = document.createElement('select');
          select.className = 'form-input';
          fieldKeys.forEach(function (key) {
            var opt = document.createElement('option');
            opt.value = key;
            opt.textContent = key;
            if (key === (runtimeState.field || props.field)) {
              opt.selected = true;
            }
            select.appendChild(opt);
          });
          select.addEventListener('change', function () {
            var next = ensureComponentState(ctx.item.id);
            next.field = select.value;
            dispatchComponentEvent(ctx.item.id, 'onSelect', {
              field: select.value,
              value: String(next.value || '')
            });
          });
          wrap.appendChild(select);

          var input = document.createElement('input');
          input.className = 'form-input';
          input.type = 'text';
          input.placeholder = String(props.placeholder || 'Type to filter');
          input.value = String(runtimeState.value || '');
          input.addEventListener('input', function () {
            var next = ensureComponentState(ctx.item.id);
            next.value = input.value;
            dispatchComponentEvent(ctx.item.id, 'onChange', {
              field: String(next.field || select.value || ''),
              value: input.value
            });
          });
          wrap.appendChild(input);

          return wrap;
        }
      },
      table: {
        requiredProps: ['title', 'sample_rows', 'density'],
        defaultConfig: function () {
          return {
            title: componentLabels.table,
            sample_rows: 3,
            density: 'comfortable'
          };
        },
        configFields: function () {
          return [
            { key: 'title', label: componentConfigLabels.tableTitle, type: 'text' },
            { key: 'sample_rows', label: componentConfigLabels.tableRows, type: 'number', min: 1, max: 8 },
            {
              key: 'density',
              label: componentConfigLabels.tableDensity,
              type: 'select',
              options: [
                { value: 'compact', label: componentConfigLabels.optCompact },
                { value: 'comfortable', label: componentConfigLabels.optComfortable }
              ]
            }
          ];
        },
        render: function (ctx) {
          var props = ctx.item.props || {};
          var runtimeState = ctx.runtimeState || {};
          var wrap = document.createElement('div');
          wrap.className = 'gs-card gs-table';
          var title = document.createElement('div');
          title.className = 'gs-title';
          title.textContent = String(props.title || componentLabels.table);
          wrap.appendChild(title);

          var table = document.createElement('table');
          table.className = 'table gs-table-preview';
          table.setAttribute('data-density', props.density === 'compact' ? 'compact' : 'comfortable');
          var tableWrapper = document.createElement('div');
          tableWrapper.className = 'gs-table-wrapper';

          var boundRows = Array.isArray(runtimeState.rows) ? runtimeState.rows : resolveBindingValue(ctx.item.data_binding, ctx.previewData);
          if (!Array.isArray(boundRows) || boundRows.length === 0) {
            boundRows = buildSampleRows(ctx.fields, Math.max(1, Math.min(8, parseInt(props.sample_rows, 10) || 3)));
          }

          var thead = document.createElement('thead');
          var trHead = document.createElement('tr');
          var firstRow = boundRows[0] && typeof boundRows[0] === 'object' ? boundRows[0] : {};
          var columns = Object.keys(firstRow).slice(0, 3);
          if (columns.length === 0) columns = ctx.columns.slice(0, 3);
          if (columns.length === 0) columns = currentFieldKeys().slice(0, 3);
          if (columns.length === 0) columns = ['key'];
          columns.forEach(function (col) {
            var th = document.createElement('th');
            th.textContent = col;
            trHead.appendChild(th);
          });
          thead.appendChild(trHead);
          table.appendChild(thead);

          var tbody = document.createElement('tbody');
          var sampleRows = Math.max(1, Math.min(8, parseInt(props.sample_rows, 10) || 3));
          for (var i = 0; i < sampleRows; i++) {
            var rowData = boundRows[i] && typeof boundRows[i] === 'object' ? boundRows[i] : {};
            var tr = document.createElement('tr');
            columns.forEach(function (col) {
              var td = document.createElement('td');
              td.textContent = String(rowData[col] || (col + '_' + String(i + 1)));
              td.className = 'gs-clickable';
              td.addEventListener('click', function () {
                var state = ensureComponentState(ctx.item.id);
                state.selected_row = rowData;
                dispatchComponentEvent(ctx.item.id, 'onSelect', { row: rowData, rowIndex: i });
              });
              tr.appendChild(td);
            });
            tbody.appendChild(tr);
          }
          table.appendChild(tbody);
          tableWrapper.appendChild(table);
          wrap.appendChild(tableWrapper);
          return wrap;
        }
      },
      form: {
        requiredProps: ['title', 'submit_label', 'show_required'],
        defaultConfig: function () {
          return {
            title: componentLabels.form,
            submit_label: componentConfigLabels.defaultSubmit,
            show_required: true
          };
        },
        configFields: function () {
          return [
            { key: 'title', label: componentConfigLabels.formTitle, type: 'text' },
            { key: 'submit_label', label: componentConfigLabels.formSubmitLabel, type: 'text' },
            { key: 'show_required', label: componentConfigLabels.formShowRequired, type: 'checkbox' }
          ];
        },
        render: function (ctx) {
          var props = ctx.item.props || {};
          var runtimeState = ctx.runtimeState || {};
          var wrap = document.createElement('div');
          wrap.className = 'gs-card gs-form';
          var title = document.createElement('div');
          title.className = 'gs-title';
          title.textContent = String(props.title || componentLabels.form);
          wrap.appendChild(title);

          var fields = resolveBindingValue(ctx.item.data_binding, ctx.previewData);
          if (!Array.isArray(fields) || fields.length === 0) {
            fields = ctx.fields.slice();
          }
          fields = fields.slice(0, 3);
          if (fields.length === 0) {
            fields = [{ key: 'name', required: true }, { key: 'status', required: false }];
          }
          fields.forEach(function (field) {
            var row = document.createElement('div');
            row.className = 'gs-input-wrap';

            var label = document.createElement('label');
            label.className = 'gs-meta';
            var marker = (props.show_required && field.required) ? ' *' : '';
            label.textContent = String(field.key || '') + marker;

            var input = document.createElement('input');
            input.className = 'form-input';
            input.type = 'text';
            input.value = String(runtimeState.values && runtimeState.values[field.key] || '');
            input.addEventListener('input', function () {
              var next = ensureComponentState(ctx.item.id);
              next.values = next.values || {};
              next.values[field.key] = input.value;
              dispatchComponentEvent(ctx.item.id, 'onChange', { field: field.key, value: input.value });
            });

            row.appendChild(label);
            row.appendChild(input);
            wrap.appendChild(row);
          });

          var submit = document.createElement('button');
          submit.type = 'button';
          submit.className = 'btn';
          submit.textContent = String(props.submit_label || componentConfigLabels.defaultSubmit);
          submit.addEventListener('click', function () {
            var state = ensureComponentState(ctx.item.id);
            state.last_submit = Date.now();
            dispatchComponentEvent(ctx.item.id, 'onClick', { values: state.values || {} });
          });
          wrap.appendChild(submit);
          return wrap;
        }
      },
      kpi_card: {
        requiredProps: ['label', 'value', 'delta'],
        defaultConfig: function () {
          return {
            label: componentLabels.kpi_card,
            value: '42',
            delta: '+4.8%'
          };
        },
        configFields: function () {
          return [
            { key: 'label', label: componentConfigLabels.kpiLabel, type: 'text' },
            { key: 'value', label: componentConfigLabels.kpiValue, type: 'text' },
            { key: 'delta', label: componentConfigLabels.kpiDelta, type: 'text' }
          ];
        },
        render: function (ctx) {
          var props = ctx.item.props || {};
          var runtimeState = ctx.runtimeState || {};
          var boundValue = resolveBindingValue(ctx.item.data_binding, ctx.previewData);
          var card = document.createElement('div');
          card.className = 'gs-card gs-kpi';

          var label = document.createElement('div');
          label.className = 'gs-title';
          label.textContent = String(props.label || componentLabels.kpi_card);

          var value = document.createElement('div');
          value.className = 'gs-value';
          var resolvedValue = (runtimeState.value !== null && typeof runtimeState.value !== 'undefined')
            ? runtimeState.value
            : ((boundValue !== null && typeof boundValue !== 'undefined') ? boundValue : (props.value || '0'));
          value.textContent = String(resolvedValue);

          var delta = document.createElement('div');
          delta.className = 'gs-meta';
          delta.textContent = String(runtimeState.delta || props.delta || '');

          card.appendChild(label);
          card.appendChild(value);
          card.appendChild(delta);
          return card;
        }
      },
      text_block: {
        requiredProps: ['title', 'body', 'align'],
        defaultConfig: function () {
          return {
            title: componentLabels.text_block,
            body: moduleDescriptionInput ? (moduleDescriptionInput.value || '') : '',
            align: 'left'
          };
        },
        configFields: function () {
          return [
            { key: 'title', label: componentConfigLabels.textTitle, type: 'text' },
            { key: 'body', label: componentConfigLabels.textBody, type: 'textarea' },
            {
              key: 'align',
              label: componentConfigLabels.textAlign,
              type: 'select',
              options: [
                { value: 'left', label: componentConfigLabels.optLeft },
                { value: 'center', label: componentConfigLabels.optCenter },
                { value: 'right', label: componentConfigLabels.optRight }
              ]
            }
          ];
        },
        render: function (ctx) {
          var props = ctx.item.props || {};
          var boundText = resolveBindingValue(ctx.item.data_binding, ctx.previewData);
          var wrap = document.createElement('div');
          wrap.className = 'gs-card gs-text';
          wrap.setAttribute('data-align', String(props.align || 'left'));

          var title = document.createElement('div');
          title.className = 'gs-title';
          title.textContent = String(props.title || componentLabels.text_block);

          var body = document.createElement('p');
          body.className = 'gs-meta';
          body.textContent = String((boundText !== null && typeof boundText !== 'undefined' && boundText !== '') ? boundText : (props.body || componentLabels.text_block));

          wrap.appendChild(title);
          wrap.appendChild(body);
          return wrap;
        }
      }
    };

    function getComponentDefinition(component) {
      return componentRegistry[component] || componentRegistry.text_block;
    }

    function normalizeComponentProps(component, rawProps) {
      var definition = getComponentDefinition(component);
      var defaults = definition.defaultConfig();
      var props = rawProps && typeof rawProps === 'object' ? rawProps : {};
      var normalized = {};
      Object.keys(defaults).forEach(function (key) {
        var fallback = defaults[key];
        var value = Object.prototype.hasOwnProperty.call(props, key) ? props[key] : fallback;
        if (typeof fallback === 'boolean') {
          normalized[key] = !!value;
          return;
        }
        if (typeof fallback === 'number') {
          normalized[key] = parseInt(value, 10);
          if (!Number.isFinite(normalized[key])) {
            normalized[key] = fallback;
          }
          return;
        }
        normalized[key] = String(value || fallback);
      });
      return normalized;
    }

    function normalizeDataBinding(component, rawBinding) {
      var binding = String(rawBinding || '').trim();
      if (!binding) {
        binding = defaultBindingForComponent(component);
      }
      return binding;
    }

    function bindingSchemaOptions() {
      return [
        { value: 'module.rows', label: 'module.rows', group: 'module', type: 'array_rows' },
        { value: 'module.fields', label: 'module.fields', group: 'module', type: 'array_fields' },
        { value: 'module.metrics.total', label: 'module.metrics.total', group: 'module.metrics', type: 'number' },
        { value: 'module.metrics.required_fields', label: 'module.metrics.required_fields', group: 'module.metrics', type: 'number' },
        { value: 'module.metrics.optional_fields', label: 'module.metrics.optional_fields', group: 'module.metrics', type: 'number' },
        { value: 'module.description', label: 'module.description', group: 'module', type: 'string' }
      ];
    }

    function bindingSchemaOptionByValue(value) {
      var target = String(value || '');
      return bindingSchemaOptions().find(function (option) {
        return option.value === target;
      }) || null;
    }

    function validateItemBinding(item) {
      if (!item || typeof item !== 'object') return 'invalid_path';
      var component = String(item.component || '');
      var path = normalizeDataBinding(component, item.data_binding);
      var schemaOption = bindingSchemaOptionByValue(path);
      if (!schemaOption) return 'invalid_path';

      if (component === 'table' && schemaOption.type !== 'array_rows') return 'table';
      if (component === 'filter' && schemaOption.type !== 'array_rows') return 'filter';
      if (component === 'form' && schemaOption.type !== 'array_fields') return 'form';
      if (component === 'kpi_card' && !(schemaOption.type === 'number' || schemaOption.type === 'string')) return 'kpi';
      if (component === 'text_block' && schemaOption.type !== 'string') return 'text';
      return '';
    }

    function recomputeBindingErrors() {
      var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
      var errors = {};
      items.forEach(function (item) {
        var errorKey = validateItemBinding(item);
        if (errorKey) {
          errors[item.id] = errorKey;
        }
      });
      builderState.bindingErrors = errors;
      return errors;
    }

    function renderBindingSchemaTree() {
      if (!bindingSchemaTree) return;
      var groups = {};
      bindingSchemaOptions().forEach(function (option) {
        var group = option.group || 'module';
        if (!groups[group]) groups[group] = [];
        groups[group].push(option);
      });

      var root = document.createElement('ul');
      root.style.margin = '0';
      root.style.paddingLeft = '1rem';

      Object.keys(groups).forEach(function (group) {
        var groupLi = document.createElement('li');
        groupLi.textContent = group;
        var child = document.createElement('ul');
        child.style.margin = '0.25rem 0';
        child.style.paddingLeft = '1rem';
        groups[group].forEach(function (option) {
          var itemLi = document.createElement('li');
          itemLi.textContent = option.label;
          child.appendChild(itemLi);
        });
        groupLi.appendChild(child);
        root.appendChild(groupLi);
      });

      bindingSchemaTree.innerHTML = '';
      bindingSchemaTree.appendChild(root);
    }

    function buildSampleRows(fields, count) {
      var rows = [];
      var sourceFields = Array.isArray(fields) ? fields : [];
      var rowCount = Math.max(1, Math.min(8, count || 3));
      for (var index = 0; index < rowCount; index++) {
        var row = {};
        sourceFields.forEach(function (field) {
          var key = String(field && field.key || '');
          if (!key) return;
          var fallback = String(field && field.default || '');
          row[key] = fallback !== '' ? fallback : (key + '_' + String(index + 1));
        });
        rows.push(row);
      }
      return rows;
    }

    function buildPreviewDataContext() {
      var fields = structuredState.fields.slice();
      var rows = buildSampleRows(fields, 4);
      var requiredCount = fields.filter(function (field) {
        return !!field.required;
      }).length;
      return {
        module: {
          fields: fields,
          rows: rows,
          metrics: {
            total: rows.length,
            required_fields: requiredCount,
            optional_fields: Math.max(0, fields.length - requiredCount)
          },
          description: moduleDescriptionInput ? String(moduleDescriptionInput.value || '') : ''
        }
      };
    }

    function ensureComponentState(itemId) {
      var key = String(itemId || '');
      if (!key) return {};
      if (!builderState.componentState[key] || typeof builderState.componentState[key] !== 'object') {
        builderState.componentState[key] = {};
      }
      return builderState.componentState[key];
    }

    function getItemById(itemId) {
      var key = String(itemId || '');
      var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
      return items.find(function (item) { return item.id === key; }) || null;
    }

    function baseRowsForItem(item, previewData) {
      var rows = resolveBindingValue(item.data_binding, previewData);
      return Array.isArray(rows) ? rows.slice() : [];
    }

    function resolveRelationType(sourceItem, targetItem, relation) {
      var rawType = toKey(relation && relation.type || '');
      if (rawType && rawType !== 'affects_table') return rawType;
      var sourceComponent = sourceItem ? String(sourceItem.component || '') : '';
      var targetComponent = targetItem ? String(targetItem.component || '') : '';
      if (sourceComponent === 'filter' && targetComponent === 'table') return 'filter_to_table';
      if (sourceComponent === 'filter' && targetComponent === 'kpi_card') return 'filter_to_kpi';
      if (sourceComponent === 'form' && targetComponent === 'table') return 'form_refresh_table';
      if (sourceComponent === 'table' && targetComponent === 'kpi_card') return 'table_to_kpi_derived';
      return 'affects_table';
    }

    function handleComponentInteraction(sourceId, targetId, relationType, payload) {
      var previewData = buildPreviewDataContext();
      var sourceItem = getItemById(sourceId);
      var targetItem = getItemById(targetId);
      if (!sourceItem || !targetItem) return false;

      var sourceState = ensureComponentState(sourceId);
      var targetState = ensureComponentState(targetId);
      var type = String(relationType || 'affects_table');
      var changed = false;

      if (type === 'filter_to_table') {
        var sourceField = String(payload && payload.field || sourceState.field || sourceItem.props.field || '');
        var sourceValue = String(payload && payload.value || sourceState.value || '').toLowerCase();
        var baseRows = baseRowsForItem(targetItem, previewData);
        var filteredRows = baseRows.filter(function (row) {
          if (!sourceField || !row || typeof row !== 'object') return true;
          return String(row[sourceField] || '').toLowerCase().indexOf(sourceValue) !== -1;
        });
        targetState.rows = filteredRows;
        targetState.last_event = 'onChange';
        changed = true;
      }

      if (type === 'filter_to_kpi') {
        var filterField = String(payload && payload.field || sourceState.field || sourceItem.props.field || '');
        var filterValue = String(payload && payload.value || sourceState.value || '').toLowerCase();
        var sourceRows = baseRowsForItem(sourceItem, previewData);
        var matchedCount = sourceRows.filter(function (row) {
          if (!filterField || !row || typeof row !== 'object') return true;
          return String(row[filterField] || '').toLowerCase().indexOf(filterValue) !== -1;
        }).length;
        targetState.value = matchedCount;
        targetState.delta = filterValue ? ('matched: ' + matchedCount) : '';
        targetState.last_event = 'onChange';
        changed = true;
      }

      if (type === 'form_refresh_table') {
        targetState.rows = baseRowsForItem(targetItem, previewData);
        targetState.refreshed_at = Date.now();
        targetState.last_event = 'onClick';
        changed = true;
      }

      if (type === 'table_to_kpi_derived') {
        var tableRows = Array.isArray(sourceState.rows) ? sourceState.rows : baseRowsForItem(sourceItem, previewData);
        targetState.value = tableRows.length;
        targetState.delta = 'rows: ' + tableRows.length;
        targetState.last_event = 'onSelect';
        changed = true;
      }

      return changed;
    }

    function dispatchComponentEvent(sourceId, eventName, payload) {
      var sourceKey = String(sourceId || '');
      if (!sourceKey) return;
      var relations = builderState.layout && Array.isArray(builderState.layout.relations) ? builderState.layout.relations : [];
      var sourceState = ensureComponentState(sourceKey);
      sourceState.last_event = String(eventName || '');
      var changed = false;

      relations.forEach(function (relation) {
        if (!relation || relation.source_id !== sourceKey) return;
        var sourceItem = getItemById(relation.source_id);
        var targetItem = getItemById(relation.target_id);
        var type = resolveRelationType(sourceItem, targetItem, relation);
        if (handleComponentInteraction(relation.source_id, relation.target_id, type, payload || {})) {
          changed = true;
        }
      });

      if (changed) {
        refreshVisualBuilder();
      }
    }

    function resolveBindingValue(path, contextValue) {
      var pathValue = String(path || '').trim();
      if (!pathValue) return null;
      var target = contextValue;
      var segments = pathValue.split('.').map(function (part) {
        return String(part || '').trim();
      }).filter(Boolean);
      for (var i = 0; i < segments.length; i++) {
        if (!target || typeof target !== 'object') {
          return null;
        }
        if (!Object.prototype.hasOwnProperty.call(target, segments[i])) {
          return null;
        }
        target = target[segments[i]];
      }
      return target;
    }

    function toPhaseOneType(component) {
      var normalized = toKey(component || '');
      if (normalized === 'kpi_card') return 'kpi';
      if (normalized === 'text_block') return 'text';
      if (normalized === 'table' || normalized === 'form') return normalized;
      return 'text';
    }

    function fromPhaseOneType(type) {
      var normalized = toKey(type || '');
      if (normalized === 'kpi') return 'kpi_card';
      if (normalized === 'text') return 'text_block';
      if (normalized === 'table' || normalized === 'form') return normalized;
      return 'text_block';
    }

    function normalizePhaseOneLayoutType(type) {
      var normalized = toKey(type || '');
      if (normalized === 'kpi' || normalized === 'table' || normalized === 'form' || normalized === 'text') {
        return normalized;
      }
      return toPhaseOneType(normalized);
    }

    function defaultLabelForPhaseOneType(type) {
      if (type === 'kpi') return visualBuilderPhaseOneLabels.defaultKpi;
      if (type === 'table') return visualBuilderPhaseOneLabels.defaultTable;
      if (type === 'form') return visualBuilderPhaseOneLabels.defaultForm;
      return visualBuilderPhaseOneLabels.defaultText;
    }

    function cloneLayoutRows(rows) {
      return (Array.isArray(rows) ? rows : []).map(function (row, rowIndex) {
        return {
          row: rowIndex + 1,
          columns: (Array.isArray(row && row.columns) ? row.columns : []).map(function (column) {
            var type = normalizePhaseOneLayoutType(column.type);
            return {
              itemId: String(column.itemId || ''),
              width: Math.max(1, Math.min(12, parseInt(column.width, 10) || 12)),
              type: type,
              label: String(column.label || defaultLabelForPhaseOneType(type))
            };
          })
        };
      });
    }

    function layoutRowWidth(row) {
      return (Array.isArray(row && row.columns) ? row.columns : []).reduce(function (sum, column) {
        return sum + (Math.max(1, Math.min(12, parseInt(column.width, 10) || 12)));
      }, 0);
    }

    function findLayoutItemLocation(rows, itemId) {
      var normalizedItemId = String(itemId || '');
      if (!normalizedItemId) return null;
      for (var rowIndex = 0; rowIndex < rows.length; rowIndex++) {
        var row = rows[rowIndex];
        for (var columnIndex = 0; columnIndex < row.columns.length; columnIndex++) {
          if (String(row.columns[columnIndex].itemId || '') === normalizedItemId) {
            return { rowIndex: rowIndex, columnIndex: columnIndex, row: row };
          }
        }
      }
      return null;
    }

    function commitLayoutRows(rows, selectedItemId) {
      var sourceItems = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
      var nextRows = cloneLayoutRows(rows);
      builderState.layout.items = itemsFromLayoutRows(nextRows, sourceItems);
      if (builderState.layout.items.length === 0) {
        builderState.layout = defaultLayoutForView(viewKindInput ? viewKindInput.value : 'table');
      }
      if (selectedItemId && builderState.layout.items.some(function (item) { return item.id === selectedItemId; })) {
        builderState.selectedItemId = String(selectedItemId);
      } else if (!builderState.selectedItemId || builderState.layout.items.every(function (item) { return item.id !== builderState.selectedItemId; })) {
        builderState.selectedItemId = builderState.layout.items[0] ? builderState.layout.items[0].id : '';
      }
      setBuilderWarning('');
      buildInternalJson();
      refreshVisualBuilder();
      return builderState.layout.items;
    }

    function showStudioToast(text) {
      var layer = document.getElementById('gs-studio-toast-layer');
      if (!layer || !text) return;
      var toast = document.createElement('div');
      toast.className = 'studio-toast';
      toast.textContent = String(text);
      layer.appendChild(toast);
      window.setTimeout(function () {
        toast.remove();
      }, 1300);
    }

    function componentTypeLabel(component) {
      var key = String(component || '').toLowerCase();
      if (key === 'kpi_card') return interactionLabels.typeKpi;
      if (key === 'table') return interactionLabels.typeTable;
      if (key === 'form') return interactionLabels.typeForm;
      if (key === 'filter') return interactionLabels.typeFilter;
      return interactionLabels.typeText;
    }

    function canPlaceLayoutItem(rows, itemId, targetRowIndex, targetColumnIndex, dropMode) {
      var workingRows = cloneLayoutRows(rows);
      var location = findLayoutItemLocation(workingRows, itemId);
      if (!location) return { valid: false, reason: 'bounds' };

      var sourceRow = workingRows[location.rowIndex];
      var movingColumn = sourceRow.columns[location.columnIndex];
      var sourceRowRemoved = false;
      sourceRow.columns.splice(location.columnIndex, 1);
      if (sourceRow.columns.length === 0) {
        workingRows.splice(location.rowIndex, 1);
        sourceRowRemoved = true;
      }

      var adjustedTargetRowIndex = Math.max(0, Math.min(targetRowIndex, workingRows.length));
      if (sourceRowRemoved && location.rowIndex < adjustedTargetRowIndex) {
        adjustedTargetRowIndex -= 1;
      }

      if (adjustedTargetRowIndex < 0) {
        adjustedTargetRowIndex = 0;
      }

      if (adjustedTargetRowIndex >= workingRows.length) {
        workingRows.push({ row: workingRows.length + 1, columns: [] });
        adjustedTargetRowIndex = workingRows.length - 1;
      }

      var targetRow = workingRows[adjustedTargetRowIndex];
      if (!targetRow) return { valid: false, reason: 'bounds' };

      var insertionIndex = dropMode === 'row-end'
        ? targetRow.columns.length
        : Math.max(0, Math.min(targetColumnIndex, targetRow.columns.length));

      if (dropMode !== 'row-end' && location.rowIndex === adjustedTargetRowIndex && location.columnIndex < insertionIndex) {
        insertionIndex -= 1;
      }

      if (insertionIndex < 0) insertionIndex = 0;
      targetRow.columns.splice(insertionIndex, 0, movingColumn);

      if (layoutRowWidth(targetRow) > 12) {
        return { valid: false, reason: 'overlap' };
      }

      return {
        valid: true,
        rows: workingRows,
        targetRowIndex: adjustedTargetRowIndex,
        insertionIndex: insertionIndex
      };
    }

    function clearLayoutDropTargets() {
      if (!layoutCanvas) return;
      layoutCanvas.querySelectorAll('.is-drop-target, .is-drop-target-valid, .is-drop-target-invalid').forEach(function (element) {
        element.classList.remove('is-drop-target', 'is-drop-target-valid', 'is-drop-target-invalid');
      });
    }

    function markLayoutDropTarget(target, isValid) {
      clearLayoutDropTargets();
      if (!target) return;
      target.classList.add('is-drop-target');
      target.classList.add(isValid ? 'is-drop-target-valid' : 'is-drop-target-invalid');
    }

    function readLayoutDropTarget(target) {
      if (!target) return null;
      return {
        rowIndex: Math.max(0, parseInt(target.getAttribute('data-drop-row-index'), 10) || 0),
        columnIndex: Math.max(0, parseInt(target.getAttribute('data-drop-column-index'), 10) || 0),
        dropMode: String(target.getAttribute('data-drop-mode') || 'row-end')
      };
    }

    function applyLayoutDrop(itemId, targetMeta) {
      var rows = cloneLayoutRows(layoutRowsFromItems(builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : []));
      var candidate = canPlaceLayoutItem(rows, itemId, targetMeta.rowIndex, targetMeta.columnIndex, targetMeta.dropMode);
      if (!candidate.valid) {
        setBuilderWarning(candidate.reason || 'overlap');
        return false;
      }
      commitLayoutRows(candidate.rows, itemId);
      showStudioToast(interactionLabels.feedbackMove);
      return true;
    }

    function layoutRowsFromItems(items) {
      var rowsByY = {};
      (Array.isArray(items) ? items : []).forEach(function (item) {
        if (!item || typeof item !== 'object') return;
        var y = Math.max(0, parseInt(item.y, 10) || 0);
        if (!rowsByY[y]) rowsByY[y] = [];
        var props = item.props && typeof item.props === 'object' ? item.props : {};
        var label = String(props.label || props.title || itemDisplayTitle(item));
        rowsByY[y].push({
          itemId: String(item.id || ''),
          x: Math.max(0, parseInt(item.x, 10) || 0),
          width: Math.max(1, Math.min(12, parseInt(item.w, 10) || 12)),
          type: toPhaseOneType(item.component),
          label: label
        });
      });

      var sortedY = Object.keys(rowsByY).map(function (value) { return parseInt(value, 10); }).sort(function (a, b) { return a - b; });
      var rows = sortedY.map(function (y, index) {
        var columns = rowsByY[y].sort(function (a, b) { return a.x - b.x; }).map(function (column) {
          return {
            itemId: column.itemId,
            width: column.width,
            type: column.type,
            label: column.label
          };
        });
        return {
          row: index + 1,
          columns: columns
        };
      });

      return rows;
    }

    function normalizeLayoutRows(rows) {
      var sourceRows = Array.isArray(rows) ? rows : [];
      var normalizedRows = [];
      sourceRows.forEach(function (row, rowIndex) {
        if (!row || typeof row !== 'object') return;
        var columns = Array.isArray(row.columns) ? row.columns : [];
        var normalizedColumns = [];
        var usedWidth = 0;
        columns.forEach(function (column) {
          if (!column || typeof column !== 'object') return;
          var type = normalizePhaseOneLayoutType(column.type || 'text');
          var width = Math.max(1, Math.min(12, parseInt(column.width, 10) || 12));
          if (usedWidth + width > 12) {
            width = Math.max(1, 12 - usedWidth);
          }
          usedWidth += width;
          normalizedColumns.push({
            width: width,
            type: type,
            label: String(column.label || defaultLabelForPhaseOneType(type))
          });
        });
        if (normalizedColumns.length === 0) {
          normalizedColumns.push({ width: 12, type: 'text', label: defaultLabelForPhaseOneType('text') });
        }
        normalizedRows.push({ row: rowIndex + 1, columns: normalizedColumns });
      });

      if (normalizedRows.length === 0) {
        normalizedRows.push({
          row: 1,
          columns: [
            { width: 6, type: 'kpi', label: defaultLabelForPhaseOneType('kpi') },
            { width: 6, type: 'table', label: defaultLabelForPhaseOneType('table') }
          ]
        });
      }

      return normalizedRows;
    }

    function itemsFromLayoutRows(rows, sourceItems) {
      var normalizedRows = cloneLayoutRows(rows);
      var sourceItemsById = {};
      (Array.isArray(sourceItems) ? sourceItems : []).forEach(function (item) {
        if (!item || typeof item !== 'object' || !item.id) return;
        sourceItemsById[String(item.id)] = item;
      });
      var items = [];
      normalizedRows.forEach(function (row, rowIndex) {
        var xCursor = 0;
        row.columns.forEach(function (column, colIndex) {
          var existingItem = column.itemId && sourceItemsById[String(column.itemId)] ? sourceItemsById[String(column.itemId)] : null;
          var component = existingItem ? String(existingItem.component || fromPhaseOneType(column.type)) : fromPhaseOneType(column.type);
          var itemId = existingItem ? String(existingItem.id || '') : toKey(component + '_' + (rowIndex + 1) + '_' + (colIndex + 1));
          if (!itemId) {
            itemId = toKey(component + '_' + (rowIndex + 1) + '_' + (colIndex + 1));
          }
          var item = existingItem ? {
            id: itemId,
            x: xCursor,
            y: rowIndex * 3,
            w: Math.max(1, Math.min(12, parseInt(column.width, 10) || 12)),
            h: Math.max(1, parseInt(existingItem.h, 10) || 3),
            group: String(existingItem.group || 'default'),
            component: component,
            props: normalizeComponentProps(component, existingItem.props || {}),
            data_binding: normalizeDataBinding(component, existingItem.data_binding || '')
          } : buildDefaultItem(component, itemId, xCursor, rowIndex * 3, column.width, 3);
          if (column.label) {
            if (component === 'kpi_card') {
              item.props.label = String(column.label);
            } else {
              item.props.title = String(column.label);
            }
          } else if (component === 'kpi_card') {
            item.props.label = String(item.props.label || defaultLabelForPhaseOneType('kpi'));
          } else {
            item.props.title = String(item.props.title || defaultLabelForPhaseOneType(toPhaseOneType(component)));
          }
          items.push(item);
          xCursor += column.width;
        });
      });
      return items;
    }

    function buildDefaultItem(component, id, x, y, w, h) {
      var item = {
        id: id,
        x: x,
        y: y,
        w: w,
        h: h,
        group: 'default',
        component: component,
        props: normalizeComponentProps(component, {}),
        data_binding: normalizeDataBinding(component, '')
      };
      if (component === 'kpi_card') {
        item.props.label = visualBuilderPhaseOneLabels.defaultKpi;
      } else if (component === 'table') {
        item.props.title = visualBuilderPhaseOneLabels.defaultTable;
      } else if (component === 'form') {
        item.props.title = visualBuilderPhaseOneLabels.defaultForm;
      } else if (component === 'text_block') {
        item.props.title = visualBuilderPhaseOneLabels.defaultText;
      }
      return item;
    }

    function defaultLayoutForView(viewKind) {
      var rows = normalizeLayoutRows([]);
      return {
        type: 'grid',
        columns: 12,
        rows: 'auto',
        relations: [],
        items: itemsFromLayoutRows(rows)
      };
    }

    function normalizeLayoutData(layout, viewKind) {
      var supportedRelationTypes = ['affects_table', 'filter_to_table', 'filter_to_kpi', 'form_refresh_table', 'table_to_kpi_derived'];
      if (Array.isArray(layout)) {
        return {
          type: 'grid',
          columns: 12,
          rows: 'auto',
          relations: [],
          items: itemsFromLayoutRows(layout)
        };
      }

      var source = layout && typeof layout === 'object' ? layout : {};
      var sourceRows = Array.isArray(source.structure) ? source.structure : (Array.isArray(source.rows_map) ? source.rows_map : []);
      if (sourceRows.length > 0) {
        return {
          type: 'grid',
          columns: 12,
          rows: 'auto',
          relations: [],
          items: itemsFromLayoutRows(sourceRows)
        };
      }

      var rawItems = Array.isArray(source.items) ? source.items : [];
      var rawRelations = Array.isArray(source.relations) ? source.relations : [];
      var items = [];
      rawItems.forEach(function (item, index) {
        if (!item || typeof item !== 'object') return;
        var component = toKey(item.component || defaultComponentForView(viewKind));
        if (componentOrder.indexOf(component) === -1) {
          component = 'text_block';
        }
        var id = toKey(item.id || (component + '_' + index));
        if (!id) return;
        var x = Math.max(0, Math.min(11, parseInt(item.x, 10) || 0));
        var y = Math.max(0, parseInt(item.y, 10) || 0);
        var w = Math.max(1, Math.min(12, parseInt(item.w, 10) || 1));
        if (x + w > 12) {
          w = 12 - x;
        }
        var h = Math.max(1, Math.min(24, parseInt(item.h, 10) || 1));
        var group = toKey(item.group || 'default') || 'default';
        items.push({
          id: id,
          x: x,
          y: y,
          w: w,
          h: h,
          group: group,
          component: component,
          props: normalizeComponentProps(component, item.props),
          data_binding: normalizeDataBinding(component, item.data_binding)
        });
      });

      if (items.length === 0) {
        return defaultLayoutForView(viewKind);
      }

      var itemIds = items.map(function (item) { return item.id; });
      var relations = [];
      rawRelations.forEach(function (relation) {
        if (!relation || typeof relation !== 'object') return;
        var sourceId = toKey(relation.source_id || '');
        var targetId = toKey(relation.target_id || '');
        var type = toKey(relation.type || 'affects_table') || 'affects_table';
        if (!sourceId || !targetId) return;
        if (itemIds.indexOf(sourceId) === -1 || itemIds.indexOf(targetId) === -1) return;
        if (supportedRelationTypes.indexOf(type) === -1) type = 'affects_table';
        relations.push({ source_id: sourceId, target_id: targetId, type: type });
      });

      return {
        type: 'grid',
        columns: 12,
        rows: 'auto',
        items: items,
        relations: relations
      };
    }

    function persistStudioDraftLocal() {
      if (!window.localStorage) return;
      var payload = {
        app_manifest: appManifestInput ? appManifestInput.value : '',
        module_manifest: moduleManifestInput ? moduleManifestInput.value : '',
        view_definition: viewDefinitionInput ? viewDefinitionInput.value : '',
        navigation_definition: navigationDefinitionInput ? navigationDefinitionInput.value : '',
        se_previous_bundle: previousBundleInput ? previousBundleInput.value : '',
        se_current_bundle: currentBundleInput ? currentBundleInput.value : '',
        studio_mode: studioModeInput ? studioModeInput.value : 'create_new',
        saved_at: new Date().toISOString()
      };
      try {
        window.localStorage.setItem('ops.gui_studio.draft', JSON.stringify(payload));
      } catch (error) {
        // Ignore storage failures and continue with in-memory state.
      }
    }

    function restoreStudioDraftLocal() {
      if (!window.localStorage) return;
      try {
        var raw = window.localStorage.getItem('ops.gui_studio.draft');
        if (!raw) return;
        var draft = JSON.parse(raw);
        if (!draft || typeof draft !== 'object') return;
        if (appManifestInput && typeof draft.app_manifest === 'string' && draft.app_manifest.trim() !== '') {
          appManifestInput.value = draft.app_manifest;
        }
        if (moduleManifestInput && typeof draft.module_manifest === 'string' && draft.module_manifest.trim() !== '') {
          moduleManifestInput.value = draft.module_manifest;
        }
        if (viewDefinitionInput && typeof draft.view_definition === 'string' && draft.view_definition.trim() !== '') {
          viewDefinitionInput.value = draft.view_definition;
        }
        if (navigationDefinitionInput && typeof draft.navigation_definition === 'string' && draft.navigation_definition.trim() !== '') {
          navigationDefinitionInput.value = draft.navigation_definition;
        }
        if (previousBundleInput && typeof draft.se_previous_bundle === 'string' && draft.se_previous_bundle.trim() !== '') {
          previousBundleInput.value = draft.se_previous_bundle;
        }
        if (currentBundleInput && typeof draft.se_current_bundle === 'string' && draft.se_current_bundle.trim() !== '') {
          currentBundleInput.value = draft.se_current_bundle;
        }
        if (studioModeInput && typeof draft.studio_mode === 'string' && draft.studio_mode.trim() !== '') {
          studioModeInput.value = draft.studio_mode === 'edit_existing' ? 'edit_existing' : 'create_new';
        }
      } catch (error) {
        // Ignore malformed local draft payloads.
      }
    }

    function cloneMultiViewLayout(layout) {
      try {
        return JSON.parse(JSON.stringify(layout && typeof layout === 'object' ? layout : {}));
      } catch (error) {
        return {};
      }
    }

    function makeMultiViewId() {
      return 'view_' + Date.now() + '_' + Math.floor(Math.random() * 10000);
    }

    function normalizeMultiViewEntry(entry, index) {
      var fallbackKind = String(builderState.viewKind || (viewKindInput && viewKindInput.value) || 'table');
      var fallbackName = viewManagerLabels.defaultViewName + ' ' + String(index + 1);
      var viewKind = String(entry && entry.view_kind || fallbackKind || 'table');
      return {
        id: String(entry && entry.id || makeMultiViewId()),
        name: String(entry && entry.name || fallbackName),
        view_kind: viewKind,
        preview_mode: !!(entry && entry.preview_mode),
        layout: normalizeLayoutData(cloneMultiViewLayout(entry && entry.layout), viewKind),
        links: (entry && entry.links && typeof entry.links === 'object')
          ? Object.keys(entry.links).reduce(function (memo, key) {
              var itemId = String(key || '').trim();
              var targetViewId = String(entry.links[key] || '').trim();
              if (itemId && targetViewId) {
                memo[itemId] = targetViewId;
              }
              return memo;
            }, {})
          : {}
      };
    }

    function persistMultiViewStateLocal() {
      if (!window.localStorage) return;
      try {
        window.localStorage.setItem(multiViewStorageKey, JSON.stringify({
          activeViewId: String(multiViewState.activeViewId || ''),
          views: multiViewState.views.map(function (entry) {
            return {
              id: entry.id,
              name: entry.name,
              view_kind: entry.view_kind,
              preview_mode: !!entry.preview_mode,
              layout: cloneMultiViewLayout(entry.layout),
              links: entry.links && typeof entry.links === 'object' ? Object.assign({}, entry.links) : {}
            };
          })
        }));
      } catch (error) {
        // Ignore storage failures and continue with in-memory state.
      }
    }

    function loadMultiViewStateLocal() {
      if (!window.localStorage) return null;
      try {
        var raw = window.localStorage.getItem(multiViewStorageKey);
        if (!raw) return null;
        var parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') return null;
        var views = Array.isArray(parsed.views)
          ? parsed.views.map(function (entry, index) { return normalizeMultiViewEntry(entry, index); })
          : [];
        return {
          activeViewId: String(parsed.activeViewId || ''),
          views: views
        };
      } catch (error) {
        return null;
      }
    }

    function activeMultiViewEntry() {
      var activeId = String(multiViewState.activeViewId || '');
      return multiViewState.views.find(function (entry) { return entry.id === activeId; }) || null;
    }

    function saveActiveMultiViewSnapshot() {
      var active = activeMultiViewEntry();
      if (!active) return;
      active.layout = cloneMultiViewLayout(builderState.layout);
      active.view_kind = String(viewKindInput && viewKindInput.value || builderState.viewKind || 'table');
      active.preview_mode = !!builderState.previewMode;
      if (!active.links || typeof active.links !== 'object') {
        active.links = {};
      }
      persistMultiViewStateLocal();
    }

    function applyMultiViewEntryToBuilder(entry) {
      if (!entry || typeof entry !== 'object') return;
      var viewKind = String(entry.view_kind || 'table');
      builderState.layout = normalizeLayoutData(cloneMultiViewLayout(entry.layout), viewKind);
      builderState.viewKind = viewKind;
      builderState.previewMode = !!entry.preview_mode;
      builderState.warning = '';
      builderState.componentState = {};
      builderState.selectedItemId = builderState.layout.items[0] ? builderState.layout.items[0].id : '';
      if (viewKindInput) {
        viewKindInput.value = viewKind;
      }
      if (visualBuilderPreviewToggle) {
        visualBuilderPreviewToggle.checked = !!builderState.previewMode;
      }
      setBuilderWarning('');
    }

    function switchActiveMultiView(viewId) {
      var targetId = String(viewId || '');
      if (!targetId) return;
      var currentId = String(multiViewState.activeViewId || '');
      if (currentId === targetId) return;

      saveActiveMultiViewSnapshot();
      var targetEntry = multiViewState.views.find(function (entry) { return entry.id === targetId; });
      if (!targetEntry) return;

      multiViewState.activeViewId = targetEntry.id;
      applyMultiViewEntryToBuilder(targetEntry);
      persistMultiViewStateLocal();
      buildInternalJson();
      renderViewManagerPanel();
    }

    function addMultiView() {
      if (typeof window.prompt !== 'function') return;
      var requested = window.prompt(viewManagerLabels.namePrompt, '');
      if (requested === null) return;
      var name = String(requested || '').trim();
      if (!name) {
        name = viewManagerLabels.defaultViewName + ' ' + String(multiViewState.views.length + 1);
      }

      saveActiveMultiViewSnapshot();
      var viewKind = String(viewKindInput && viewKindInput.value || builderState.viewKind || 'table');
      var nextEntry = normalizeMultiViewEntry({
        id: makeMultiViewId(),
        name: name,
        view_kind: viewKind,
        preview_mode: !!builderState.previewMode,
        layout: defaultLayoutForView(viewKind)
      }, multiViewState.views.length);
      multiViewState.views.push(nextEntry);
      multiViewState.activeViewId = nextEntry.id;
      applyMultiViewEntryToBuilder(nextEntry);
      persistMultiViewStateLocal();
      buildInternalJson();
      renderViewManagerPanel();
    }

    function loadMultiViewsFromViewDefinitionInput() {
      var parsed = safeParseJson(viewDefinitionInput ? viewDefinitionInput.value : '', {});
      var studioViews = parsed && typeof parsed === 'object' ? parsed.studio_views : null;
      if (!studioViews || typeof studioViews !== 'object' || !Array.isArray(studioViews.views)) {
        return null;
      }
      var views = studioViews.views.map(function (entry, index) {
        return normalizeMultiViewEntry(entry, index);
      });
      if (views.length === 0) return null;
      return {
        activeViewId: String(studioViews.active_view_id || ''),
        views: views
      };
    }

    function ensureMultiViewStateInitialized() {
      var stored = loadMultiViewStateLocal() || loadMultiViewsFromViewDefinitionInput();
      if (stored && Array.isArray(stored.views) && stored.views.length > 0) {
        multiViewState.views = stored.views;
        var hasActive = stored.views.some(function (entry) { return entry.id === stored.activeViewId; });
        multiViewState.activeViewId = hasActive ? stored.activeViewId : stored.views[0].id;
      } else {
        var initialKind = String(builderState.viewKind || (viewKindInput && viewKindInput.value) || 'table');
        var initialName = String(moduleDisplayInput && moduleDisplayInput.value || '').trim() || (viewManagerLabels.defaultViewName + ' 1');
        var initialEntry = normalizeMultiViewEntry({
          id: makeMultiViewId(),
          name: initialName,
          view_kind: initialKind,
          preview_mode: !!builderState.previewMode,
          layout: cloneMultiViewLayout(builderState.layout)
        }, 0);
        multiViewState.views = [initialEntry];
        multiViewState.activeViewId = initialEntry.id;
        persistMultiViewStateLocal();
      }

      var active = activeMultiViewEntry();
      if (active) {
        applyMultiViewEntryToBuilder(active);
      }
    }

    function availableLinkedViews() {
      var activeId = String(multiViewState.activeViewId || '');
      return multiViewState.views.filter(function (entry) {
        return entry.id !== activeId;
      });
    }

    function updateSelectedItemOpenView(targetViewId) {
      var item = selectedLayoutItem();
      if (!item) return;
      var active = activeMultiViewEntry();
      if (!active) return;
      if (!active.links || typeof active.links !== 'object') {
        active.links = {};
      }
      var linkedId = String(targetViewId || '');
      if (linkedId) {
        active.links[item.id] = linkedId;
      } else {
        delete active.links[item.id];
      }
      persistMultiViewStateLocal();
      buildInternalJson();
      renderLayoutCanvas();
      renderLayoutItemsList();
    }

    function renderViewManagerPanel() {
      if (addViewButton) {
        addViewButton.onclick = function () {
          addMultiView();
        };
      }
      if (!viewList) return;
      viewList.innerHTML = '';
      if (!Array.isArray(multiViewState.views) || multiViewState.views.length === 0) {
        var emptyRow = document.createElement('li');
        emptyRow.className = 'muted';
        emptyRow.textContent = viewManagerLabels.empty;
        viewList.appendChild(emptyRow);
        return;
      }
      multiViewState.views.forEach(function (entry) {
        var row = document.createElement('li');
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn';
        if (entry.id === multiViewState.activeViewId) {
          button.className += ' primary';
        }
        button.textContent = String(entry.name || entry.id);
        button.setAttribute('data-view-id', String(entry.id || ''));
        button.onclick = function () {
          switchActiveMultiView(entry.id);
        };
        row.appendChild(button);
        viewList.appendChild(row);
      });
    }

    function cloneVisualBuilderTemplateItems(items) {
      try {
        return JSON.parse(JSON.stringify(Array.isArray(items) ? items : []));
      } catch (error) {
        return [];
      }
    }

    function loadVisualBuilderUserTemplates() {
      if (!window.localStorage) return [];
      try {
        var raw = window.localStorage.getItem('ops.gui_studio.templates');
        if (!raw) return [];
        var templates = JSON.parse(raw);
        if (!Array.isArray(templates)) return [];
        return templates.filter(function (template) {
          return template
            && typeof template === 'object'
            && typeof template.name === 'string'
            && template.name.trim() !== ''
            && Array.isArray(template.items)
            && template.items.length > 0;
        }).map(function (template) {
          return {
            name: String(template.name || '').trim(),
            items: cloneVisualBuilderTemplateItems(template.items),
            saved_at: String(template.saved_at || '')
          };
        });
      } catch (error) {
        return [];
      }
    }

    function clonePackagePayload(value, fallback) {
      try {
        return JSON.parse(JSON.stringify(value));
      } catch (error) {
        return fallback;
      }
    }

    function collectStudioViewsForBundle() {
      saveActiveMultiViewSnapshot();
      return {
        active_view_id: String(multiViewState.activeViewId || ''),
        views: (Array.isArray(multiViewState.views) ? multiViewState.views : []).map(function (entry) {
          return {
            id: String(entry.id || ''),
            name: String(entry.name || ''),
            view_kind: String(entry.view_kind || 'table'),
            preview_mode: !!entry.preview_mode,
            layout: cloneMultiViewLayout(entry.layout),
            links: entry.links && typeof entry.links === 'object' ? Object.assign({}, entry.links) : {}
          };
        })
      };
    }

    function buildAppBundlePayload() {
      buildInternalJson();
      var appManifest = safeParseJson(appManifestInput ? appManifestInput.value : '', {});
      var moduleManifest = safeParseJson(moduleManifestInput ? moduleManifestInput.value : '', {});
      var viewDefinition = safeParseJson(viewDefinitionInput ? viewDefinitionInput.value : '', {});
      var navigationDefinition = safeParseJson(navigationDefinitionInput ? navigationDefinitionInput.value : '', {});
      var studioViews = viewDefinition && typeof viewDefinition === 'object' && viewDefinition.studio_views
        ? clonePackagePayload(viewDefinition.studio_views, null)
        : null;
      if (!studioViews || typeof studioViews !== 'object' || !Array.isArray(studioViews.views) || studioViews.views.length === 0) {
        studioViews = collectStudioViewsForBundle();
      }
      viewDefinition.studio_views = studioViews;
      return {
        app_manifest: clonePackagePayload(appManifest, {}),
        module_manifest: clonePackagePayload(moduleManifest, {}),
        view_definition: clonePackagePayload(viewDefinition, {}),
        navigation_definition: clonePackagePayload(navigationDefinition, {}),
        studio_views: clonePackagePayload(studioViews, { active_view_id: '', views: [] })
      };
    }

    function exportAppBundle() {
      var payload = buildAppBundlePayload();
      if (!payload || !payload.view_definition || !payload.module_manifest || !payload.navigation_definition) {
        if (typeof window.alert === 'function') {
          window.alert(packagingLabels.exportMissing);
        }
        return;
      }
      var serialized = JSON.stringify(payload, null, 2);
      var blob = new Blob([serialized], { type: 'application/json;charset=utf-8' });
      var url = window.URL.createObjectURL(blob);
      var link = document.createElement('a');
      link.href = url;
      link.download = packagingLabels.fileName || 'sample_app_bundle.json';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.setTimeout(function () {
        window.URL.revokeObjectURL(url);
      }, 0);
    }

    function applyImportedAppBundle(payload) {
      if (!payload || typeof payload !== 'object') {
        throw new Error('invalid_bundle');
      }
      if (!payload.app_manifest || !payload.module_manifest || !payload.view_definition || !payload.navigation_definition) {
        throw new Error('invalid_bundle');
      }

      var bundle = {
        app_manifest: clonePackagePayload(payload.app_manifest, {}),
        module_manifest: clonePackagePayload(payload.module_manifest, {}),
        view_definition: clonePackagePayload(payload.view_definition, {}),
        navigation_definition: clonePackagePayload(payload.navigation_definition, {})
      };
      var studioViews = payload.studio_views && typeof payload.studio_views === 'object'
        ? clonePackagePayload(payload.studio_views, null)
        : null;
      if (!studioViews && bundle.view_definition && typeof bundle.view_definition === 'object' && bundle.view_definition.studio_views) {
        studioViews = clonePackagePayload(bundle.view_definition.studio_views, null);
      }
      if (studioViews && (!Array.isArray(studioViews.views) || studioViews.views.length === 0)) {
        throw new Error('invalid_bundle');
      }
      if (studioViews) {
        bundle.view_definition.studio_views = studioViews;
      }

      loadBundleIntoEditor(bundle);
    }

    function importAppBundleFromFile(file) {
      if (!file) return;
      var reader = new window.FileReader();
      reader.onload = function () {
        try {
          var parsed = safeParseJson(String(reader.result || ''), null);
          if (!parsed) {
            throw new Error('invalid_bundle');
          }
          applyImportedAppBundle(parsed);
          if (typeof window.alert === 'function') {
            window.alert(packagingLabels.importSuccess);
          }
        } catch (error) {
          if (typeof window.alert === 'function') {
            window.alert(error && error.message === 'invalid_bundle' ? packagingLabels.importInvalid : packagingLabels.importFailed);
          }
        }
      };
      reader.onerror = function () {
        if (typeof window.alert === 'function') {
          window.alert(packagingLabels.importFailed);
        }
      };
      reader.readAsText(file);
    }

    function persistVisualBuilderUserTemplates(templates) {
      if (!window.localStorage) return;
      try {
        window.localStorage.setItem('ops.gui_studio.templates', JSON.stringify(Array.isArray(templates) ? templates : []));
      } catch (error) {
        // Ignore storage failures and continue with in-memory state.
      }
    }

    function setBuilderWarning(key) {
      builderState.warning = key || '';
      if (!layoutWarning) return;
      var text = '';
      if (builderState.warning === 'overlap') text = visualBuilderLabels.overlap;
      if (builderState.warning === 'bounds') text = visualBuilderLabels.bounds;
      if (builderState.warning === 'binding_invalid') text = visualBuilderLabels.bindingInvalid;
      layoutWarning.textContent = text;
      layoutWarning.style.display = text ? 'block' : 'none';
    }

    function itemCells(item) {
      var cells = [];
      for (var yy = item.y; yy < item.y + item.h; yy++) {
        for (var xx = item.x; xx < item.x + item.w; xx++) {
          cells.push(yy + ':' + xx);
        }
      }
      return cells;
    }

    function isOutOfBounds(item) {
      return item.x < 0 || item.y < 0 || item.w < 1 || item.h < 1 || item.x + item.w > 12;
    }

    function hasLayoutConflict(candidate, ignoreId) {
      if (isOutOfBounds(candidate)) {
        return 'bounds';
      }
      var occupied = {};
      var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
      items.forEach(function (item) {
        if (!item || typeof item !== 'object') return;
        if (ignoreId && item.id === ignoreId) return;
        itemCells(item).forEach(function (cell) {
          occupied[cell] = true;
        });
      });
      var cells = itemCells(candidate);
      for (var i = 0; i < cells.length; i++) {
        if (occupied[cells[i]]) {
          return 'overlap';
        }
      }
      return '';
    }

    function getCanvasGridMetrics() {
      if (!layoutCanvas) {
        return { cellWidth: 1, rowHeight: 56 };
      }
      var width = Math.max(240, layoutCanvas.clientWidth || 240);
      return {
        cellWidth: width / 12,
        rowHeight: 56
      };
    }

    function itemDisplayTitle(item) {
      var component = String(item && item.component || 'text_block');
      return (componentLabels[component] || component) + ' (' + String(item && item.id || '') + ')';
    }

    function selectedLayoutItem() {
      var selectedId = String(builderState.selectedItemId || '');
      if (!selectedId) return null;
      var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
      return items.find(function (item) {
        return item.id === selectedId;
      }) || null;
    }

    function selectLayoutItem(itemId) {
      builderState.selectedItemId = String(itemId || '');
      refreshVisualBuilder();
    }

    function removeLayoutItem(itemId) {
      builderState.layout.items = builderState.layout.items.filter(function (item) {
        return item.id !== itemId;
      });
      if (builderState.componentState && builderState.componentState[itemId]) {
        delete builderState.componentState[itemId];
      }
      builderState.layout.relations = (builderState.layout.relations || []).filter(function (relation) {
        return relation.source_id !== itemId && relation.target_id !== itemId;
      });
      if (builderState.layout.items.length === 0) {
        builderState.layout = defaultLayoutForView(viewKindInput ? viewKindInput.value : 'table');
      }
      if (builderState.selectedItemId === itemId) {
        builderState.selectedItemId = builderState.layout.items[0] ? builderState.layout.items[0].id : '';
      }
      setBuilderWarning('');
      buildInternalJson();
    }

    function duplicateLayoutItem(itemId) {
      var source = getItemById(itemId);
      if (!source) return;
      var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
      var rows = cloneLayoutRows(layoutRowsFromItems(items));
      var location = findLayoutItemLocation(rows, itemId);
      if (!location) return;

      var sourceColumn = location.row.columns[location.columnIndex];
      location.row.columns.splice(location.columnIndex + 1, 0, {
        width: sourceColumn.width,
        type: sourceColumn.type,
        label: sourceColumn.label
      });
      if (layoutRowWidth(location.row) > 12) {
        location.row.columns.splice(location.columnIndex + 1, 1);
        rows.splice(location.rowIndex + 1, 0, {
          row: rows.length + 1,
          columns: [{ width: sourceColumn.width, type: sourceColumn.type, label: sourceColumn.label }]
        });
      }

      var nextItems = itemsFromLayoutRows(rows, items);
      builderState.layout.items = nextItems;
      builderState.selectedItemId = nextItems[nextItems.length - 1] ? nextItems[nextItems.length - 1].id : source.id;
      buildInternalJson();
      showStudioToast(interactionLabels.feedbackAdd);
    }

    function addLayoutItemInRow(rowIndex, componentType) {
      var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
      var rows = cloneLayoutRows(layoutRowsFromItems(items));
      var targetIndex = Math.max(0, Math.min(rows.length - 1, parseInt(rowIndex, 10) || 0));
      if (rows.length === 0) {
        rows.push({ row: 1, columns: [] });
        targetIndex = 0;
      }
      var targetRow = rows[targetIndex];
      var type = String(componentType || 'text');
      var width = type === 'kpi' || type === 'table' ? 6 : 4;

      if (layoutRowWidth(targetRow) + width > 12) {
        rows.splice(targetIndex + 1, 0, {
          row: rows.length + 1,
          columns: [{ width: width, type: type, label: defaultLabelForPhaseOneType(type) }]
        });
      } else {
        targetRow.columns.push({ width: width, type: type, label: defaultLabelForPhaseOneType(type) });
      }

      var nextItems = itemsFromLayoutRows(rows, items);
      builderState.layout.items = nextItems;
      builderState.selectedItemId = nextItems[nextItems.length - 1] ? nextItems[nextItems.length - 1].id : '';
      buildInternalJson();
      showStudioToast(interactionLabels.feedbackAdd);
    }

    function moveLayoutItemOrder(itemId, direction) {
      var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
      var rows = cloneLayoutRows(layoutRowsFromItems(items));
      var location = findLayoutItemLocation(rows, itemId);
      if (!location) return;
      var row = location.row;
      var nextIndex = direction === 'up' ? location.columnIndex - 1 : location.columnIndex + 1;
      if (nextIndex < 0 || nextIndex >= row.columns.length) return;
      var movedColumn = row.columns.splice(location.columnIndex, 1)[0];
      if (location.columnIndex < nextIndex) {
        nextIndex -= 1;
      }
      row.columns.splice(nextIndex, 0, movedColumn);
      commitLayoutRows(rows, itemId);
      showStudioToast(interactionLabels.feedbackMove);
    }

    function moveLayoutItem(itemId, nextX, nextY) {
      var index = builderState.layout.items.findIndex(function (item) { return item.id === itemId; });
      if (index === -1) return;
      var item = builderState.layout.items[index];
      var candidate = {
        id: item.id,
        x: Math.max(0, nextX),
        y: Math.max(0, nextY),
        w: item.w,
        h: item.h,
        group: item.group || 'default',
        component: item.component,
        props: item.props,
        data_binding: item.data_binding
      };
      var conflict = hasLayoutConflict(candidate, item.id);
      if (conflict) {
        setBuilderWarning(conflict);
        return;
      }
      builderState.layout.items[index] = candidate;
      setBuilderWarning('');
      buildInternalJson();
    }

    function resizeLayoutItem(itemId, nextW, nextH) {
      var index = builderState.layout.items.findIndex(function (item) { return item.id === itemId; });
      if (index === -1) return;
      var item = builderState.layout.items[index];
      var width = Math.max(1, Math.min(12 - item.x, nextW));
      var height = Math.max(1, Math.min(24, nextH));
      var candidate = {
        id: item.id,
        x: item.x,
        y: item.y,
        w: width,
        h: height,
        group: item.group || 'default',
        component: item.component,
        props: item.props,
        data_binding: item.data_binding
      };
      var conflict = hasLayoutConflict(candidate, item.id);
      if (conflict) {
        setBuilderWarning(conflict);
        return;
      }
      builderState.layout.items[index] = candidate;
      setBuilderWarning('');
      buildInternalJson();
    }

    function addLayoutItem(component, x, y) {
      var phaseOneType = toPhaseOneType(fromPhaseOneType(component));
      var rows = cloneLayoutRows(layoutRowsFromItems(builderState.layout.items || []));
      var preferredWidth = phaseOneType === 'kpi' || phaseOneType === 'table' ? 6 : 12;
      if (rows.length === 0) {
        rows.push({ row: 1, columns: [] });
      }
      var targetRow = rows[rows.length - 1];
      var usedWidth = (targetRow.columns || []).reduce(function (sum, col) {
        return sum + (parseInt(col.width, 10) || 0);
      }, 0);
      if (usedWidth + preferredWidth > 12) {
        targetRow = { row: rows.length + 1, columns: [] };
        rows.push(targetRow);
      }
      targetRow.columns.push({
        width: preferredWidth,
        type: phaseOneType,
        label: defaultLabelForPhaseOneType(phaseOneType)
      });
      var nextItems = itemsFromLayoutRows(rows, builderState.layout.items || []);
      builderState.layout.items = nextItems;
      builderState.selectedItemId = nextItems.length > 0 ? nextItems[nextItems.length - 1].id : '';
      setBuilderWarning('');
      buildInternalJson();
      refreshVisualBuilder();
      showStudioToast(interactionLabels.feedbackAdd);
      return true;
    }

    function applyVisualBuilderTemplateItems(items) {
      var nextItems = cloneVisualBuilderTemplateItems(items);
      if (nextItems.length === 0) return;
      builderState.layout.items = nextItems;
      builderState.layout.relations = [];
      builderState.selectedItemId = builderState.layout.items[0] ? builderState.layout.items[0].id : '';
      setBuilderWarning('');
      buildInternalJson();
      refreshVisualBuilder();
      showStudioToast(interactionLabels.feedbackTemplate);
    }

    function visualBuilderTemplates() {
      return [
        {
          key: 'kpi_grid',
          label: visualBuilderTemplateLabels.kpiGrid,
          type: 'dashboard',
          moduleTypes: ['analytics', 'reporting', 'monitoring', 'operations'],
          preferredViewKinds: ['dashboard', 'board', 'overview'],
          preferredComponents: ['kpi_card', 'filter'],
          items: [
            { type: 'kpi', width: 3, label: visualBuilderTemplateLabels.metric1 },
            { type: 'kpi', width: 3, label: visualBuilderTemplateLabels.metric2 },
            { type: 'kpi', width: 3, label: visualBuilderTemplateLabels.metric3 },
            { type: 'kpi', width: 3, label: visualBuilderTemplateLabels.metric4 }
          ]
        },
        {
          key: 'table_view',
          label: visualBuilderTemplateLabels.tableView,
          type: 'list',
          moduleTypes: ['data', 'inventory', 'operations', 'catalog'],
          preferredViewKinds: ['data', 'table', 'list'],
          preferredComponents: ['table', 'filter'],
          items: [
            { type: 'table', width: 12, label: visualBuilderPhaseOneLabels.defaultTable }
          ]
        },
        {
          key: 'form_entry',
          label: visualBuilderTemplateLabels.formEntry,
          type: 'input',
          moduleTypes: ['workflow', 'operations', 'entry', 'form'],
          preferredViewKinds: ['form', 'input', 'entry', 'edit'],
          preferredComponents: ['form', 'text_block'],
          items: [
            { type: 'form', width: 12, label: visualBuilderPhaseOneLabels.defaultForm }
          ]
        }
      ];
    }

    function getVisualBuilderContext() {
      var moduleType = String(moduleTypeInput && moduleTypeInput.value || '').trim().toLowerCase();
      var viewKind = String(viewKindInput && viewKindInput.value || builderState.viewKind || '').trim().toLowerCase();
      var componentUsage = {};
      (builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : []).forEach(function (item) {
        var component = String(item && item.component || '').trim().toLowerCase();
        if (!component) return;
        componentUsage[component] = (componentUsage[component] || 0) + 1;
      });
      return {
        moduleType: moduleType,
        viewKind: viewKind,
        componentUsage: componentUsage
      };
    }

    function scoreVisualBuilderTemplate(template, context) {
      var score = 0;
      var viewKind = String(context && context.viewKind || '');
      var moduleType = String(context && context.moduleType || '');
      var usage = context && context.componentUsage && typeof context.componentUsage === 'object'
        ? context.componentUsage
        : {};

      if (Array.isArray(template.preferredViewKinds) && template.preferredViewKinds.indexOf(viewKind) !== -1) {
        score += 5;
      }
      if (viewKind === 'dashboard' && template.type === 'dashboard') {
        score += 4;
      }
      if ((viewKind === 'data' || viewKind === 'table' || viewKind === 'list') && template.type === 'list') {
        score += 4;
      }
      if ((viewKind === 'form' || viewKind === 'input' || viewKind === 'entry' || viewKind === 'edit') && template.type === 'input') {
        score += 4;
      }
      if (Array.isArray(template.moduleTypes) && template.moduleTypes.indexOf(moduleType) !== -1) {
        score += 2;
      }
      if (Array.isArray(template.preferredComponents)) {
        template.preferredComponents.forEach(function (component) {
          score += usage[String(component || '').toLowerCase()] || 0;
        });
      }
      return score;
    }

    function orderedVisualBuilderTemplates() {
      var context = getVisualBuilderContext();
      return visualBuilderTemplates().map(function (template, index) {
        return Object.assign({}, template, {
          score: scoreVisualBuilderTemplate(template, context),
          orderIndex: index
        });
      }).sort(function (left, right) {
        if (right.score !== left.score) return right.score - left.score;
        return left.orderIndex - right.orderIndex;
      });
    }

    function renderVisualBuilderSystemTemplateButtons(container, templates) {
      if (!container) return;
      container.innerHTML = '';
      templates.forEach(function (template) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn';
        button.textContent = template.label;
        button.setAttribute('data-template', template.key);
        button.onclick = function () {
          applyLayoutTemplate(template.key);
        };
        container.appendChild(button);
      });
    }

    function visualBuilderSuggestionState() {
      var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
      var counts = {
        total: items.length,
        table: 0,
        form: 0,
        kpi_card: 0,
        filter: 0,
        text_block: 0
      };

      items.forEach(function (item) {
        var component = String(item && item.component || '').trim().toLowerCase();
        if (!component) return;
        if (!Object.prototype.hasOwnProperty.call(counts, component)) {
          counts[component] = 0;
        }
        counts[component] += 1;
      });

      return counts;
    }

    function visualBuilderSuggestions() {
      var counts = visualBuilderSuggestionState();
      var suggestions = [];

      if (counts.total === 0) {
        suggestions.push({ key: 'use_template', label: visualBuilderSuggestionLabels.useTemplate });
        return suggestions;
      }

      if (counts.table > 0 && counts.kpi_card === 0) {
        suggestions.push({ key: 'add_kpi', label: visualBuilderSuggestionLabels.addKpi });
      }
      if (counts.form === 0) {
        suggestions.push({ key: 'add_form', label: visualBuilderSuggestionLabels.addForm });
      }
      if (counts.kpi_card >= 3 && counts.table === 0) {
        suggestions.push({ key: 'add_table', label: visualBuilderSuggestionLabels.addTable });
      }
      if (counts.total === 1 && counts.table === 1 && counts.kpi_card === 0) {
        suggestions.push({ key: 'add_kpi', label: visualBuilderSuggestionLabels.addKpi });
      }

      return suggestions.filter(function (suggestion, index, list) {
        return list.findIndex(function (candidate) { return candidate.key === suggestion.key; }) === index;
      });
    }

    function applyVisualBuilderSuggestion(key) {
      var suggestionKey = String(key || '');
      if (suggestionKey === 'add_kpi') {
        addLayoutItem('kpi', 0, 0);
        return;
      }
      if (suggestionKey === 'add_table') {
        addLayoutItem('table', 0, 0);
        return;
      }
      if (suggestionKey === 'add_form') {
        addLayoutItem('form', 0, 0);
        return;
      }
      if (suggestionKey === 'use_template') {
        var recommended = orderedVisualBuilderTemplates();
        if (recommended.length > 0) {
          applyLayoutTemplate(recommended[0].key);
        }
      }
    }

    function readVisibleValue(selector, fallback) {
      var candidates = Array.prototype.slice.call(document.querySelectorAll(selector));
      var visible = candidates.find(function (node) {
        return !!(node && !node.hidden && node.offsetParent !== null);
      });
      var active = visible || candidates[0] || null;
      return String((active && active.value) || fallback || '');
    }

    function setVisibleControlValue(selector, value) {
      var candidates = Array.prototype.slice.call(document.querySelectorAll(selector));
      var visible = candidates.find(function (node) {
        return !!(node && !node.hidden && node.offsetParent !== null);
      });
      var active = visible || candidates[0] || null;
      if (!active) {
        return;
      }
      active.value = String(value || '');
      active.dispatchEvent(new window.Event('change', { bubbles: true }));
    }

    function readVisibleValue(selector, fallback) {
      var candidates = Array.prototype.slice.call(document.querySelectorAll(selector));
      var visible = candidates.find(function (node) {
        return !!(node && !node.hidden && node.offsetParent !== null);
      });
      var active = visible || candidates[0] || null;
      return String((active && active.value) || fallback || '');
    }

    function setVisibleControlValue(selector, value) {
      var candidates = Array.prototype.slice.call(document.querySelectorAll(selector));
      var visible = candidates.find(function (node) {
        return !!(node && !node.hidden && node.offsetParent !== null);
      });
      var active = visible || candidates[0] || null;
      if (!active) {
        return;
      }
      active.value = String(value || '');
      active.dispatchEvent(new window.Event('change', { bubbles: true }));
    }

    function renderSmartSuggestions() {
      if (!suggestionsList) return;
      suggestionsList.innerHTML = '';
      var suggestions = visualBuilderSuggestions();
      if (suggestions.length === 0) {
        var empty = document.createElement('div');
        empty.className = 'muted';
        empty.textContent = visualBuilderSuggestionLabels.none;
        suggestionsList.appendChild(empty);
        return;
      }

      suggestions.forEach(function (suggestion) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn';
        button.textContent = suggestion.label;
        button.setAttribute('data-suggestion', suggestion.key);
        button.onclick = function () {
          applyVisualBuilderSuggestion(suggestion.key);
        };
        suggestionsList.appendChild(button);
      });
    }

    function applyLayoutTemplate(templateKey) {
      var template = visualBuilderTemplates().find(function (entry) {
        return entry.key === String(templateKey || '');
      });
      if (!template || !Array.isArray(template.items) || template.items.length === 0) return;
      if (typeof window.confirm === 'function' && !window.confirm(visualBuilderLabels.templateReplaceConfirm)) {
        return;
      }

      var rows = [{
        row: 1,
        columns: template.items.map(function (item) {
          return {
            width: Math.max(1, Math.min(12, parseInt(item.width, 10) || 12)),
            type: String(item.type || 'text'),
            label: String(item.label || defaultLabelForPhaseOneType(String(item.type || 'text')))
          };
        })
      }];

      applyVisualBuilderTemplateItems(itemsFromLayoutRows(rows, []));
    }

    function applyUserLayoutTemplate(index) {
      var templates = loadVisualBuilderUserTemplates();
      var template = templates[Math.max(0, parseInt(index, 10) || 0)];
      if (!template || !Array.isArray(template.items) || template.items.length === 0) return;
      if (typeof window.confirm === 'function' && !window.confirm(visualBuilderLabels.templateReplaceConfirm)) {
        return;
      }
      applyVisualBuilderTemplateItems(template.items);
    }

    function saveCurrentLayoutAsTemplate() {
      if (typeof window.prompt !== 'function') return;
      var items = cloneVisualBuilderTemplateItems(builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : []);
      if (items.length === 0) return;
      var templateName = window.prompt(visualBuilderLabels.templateNamePrompt, '');
      if (templateName === null) return;
      templateName = String(templateName || '').trim();
      if (!templateName) return;

      var templates = loadVisualBuilderUserTemplates();
      var nextTemplate = {
        name: templateName,
        items: items,
        saved_at: new Date().toISOString()
      };
      var existingIndex = templates.findIndex(function (template) {
        return String(template.name || '') === templateName;
      });

      if (existingIndex >= 0) {
        templates[existingIndex] = nextTemplate;
      } else {
        templates.unshift(nextTemplate);
      }

      persistVisualBuilderUserTemplates(templates);
      renderTemplatePanel();
    }

    function renderPreviewContent(item) {
      var shell = document.createElement('div');
      shell.style.fontSize = '12px';
      shell.style.opacity = '0.92';
      if (!builderState.previewMode) {
        shell.textContent = itemDisplayTitle(item);
        return shell;
      }

      var definition = getComponentDefinition(String(item.component || 'text_block'));
      var runtimeState = ensureComponentState(item.id);
      var node = definition.render({
        item: item,
        fields: structuredState.fields.slice(),
        columns: structuredState.columns.slice(),
        previewData: buildPreviewDataContext(),
        runtimeState: runtimeState,
        moduleDescription: moduleDescriptionInput ? moduleDescriptionInput.value : ''
      });
      shell.appendChild(node);
      return shell;
    }

    function updateSelectedItemConfig(key, value) {
      var item = selectedLayoutItem();
      if (!item) return;
      item.props = normalizeComponentProps(item.component, Object.assign({}, item.props || {}, { [key]: value }));
      buildInternalJson();
      renderLayoutCanvas();
      renderLayoutItemsList();
    }

    function updateSelectedItemBinding(value) {
      var item = selectedLayoutItem();
      if (!item) return;
      item.data_binding = normalizeDataBinding(item.component, value);
      buildInternalJson();
    }

    var layoutWidthSteps = [3, 4, 6, 12];

    function normalizeLayoutWidth(width) {
      var numericWidth = Math.max(1, Math.min(12, parseInt(width, 10) || 12));
      var closestWidth = layoutWidthSteps[0];
      var closestDistance = Math.abs(closestWidth - numericWidth);
      layoutWidthSteps.forEach(function (stepWidth) {
        var distance = Math.abs(stepWidth - numericWidth);
        if (distance < closestDistance) {
          closestWidth = stepWidth;
          closestDistance = distance;
        }
      });
      return closestWidth;
    }

    function nextLayoutWidth(width, direction) {
      var normalizedWidth = normalizeLayoutWidth(width);
      var index = layoutWidthSteps.indexOf(normalizedWidth);
      if (index === -1) return normalizedWidth;
      if (direction === 'narrower') {
        return index > 0 ? layoutWidthSteps[index - 1] : normalizedWidth;
      }
      return index < layoutWidthSteps.length - 1 ? layoutWidthSteps[index + 1] : normalizedWidth;
    }

    function resizeSelectedLayoutItemWidth(direction) {
      var item = selectedLayoutItem();
      if (!item) return;
      var currentWidth = Math.max(1, Math.min(12, parseInt(item.w, 10) || 12));
      var nextWidth = nextLayoutWidth(currentWidth, direction);
      if (nextWidth === currentWidth) return;

      var rows = cloneLayoutRows(layoutRowsFromItems(builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : []));
      var location = findLayoutItemLocation(rows, item.id);
      if (!location) return;

      var row = rows[location.rowIndex];
      var column = row.columns[location.columnIndex];
      column.width = nextWidth;

      if (layoutRowWidth(row) <= 12) {
        commitLayoutRows(rows, item.id);
        showStudioToast(interactionLabels.feedbackResize);
        return;
      }

      row.columns.splice(location.columnIndex, 1);
      if (row.columns.length === 0) {
        rows.splice(location.rowIndex, 1);
      }

      var searchIndex = row.columns.length === 0 ? location.rowIndex : location.rowIndex + 1;
      var targetIndex = -1;
      for (var rowIndex = searchIndex; rowIndex < rows.length; rowIndex++) {
        if (layoutRowWidth(rows[rowIndex]) + nextWidth <= 12) {
          targetIndex = rowIndex;
          break;
        }
      }
      if (targetIndex === -1) {
        rows.push({ row: rows.length + 1, columns: [] });
        targetIndex = rows.length - 1;
      }

      rows[targetIndex].columns.push(column);
      commitLayoutRows(rows, item.id);
      showStudioToast(interactionLabels.feedbackResize);
    }

    function renderComponentConfigPanel() {
      if (!componentConfigPanel) return;
      componentConfigPanel.innerHTML = '';
      var item = selectedLayoutItem();
      if (!item) {
        var empty = document.createElement('div');
        empty.className = 'muted';
        empty.textContent = componentConfigLabels.selectHint;
        componentConfigPanel.appendChild(empty);
        return;
      }

      var itemMeta = document.createElement('div');
      itemMeta.className = 'note';
      itemMeta.textContent = componentConfigLabels.itemId + ': ' + item.id;
      componentConfigPanel.appendChild(itemMeta);

      var widthLabel = document.createElement('label');
      widthLabel.className = 'form-label';
      widthLabel.textContent = componentConfigLabels.width;
      var widthControls = document.createElement('div');
      widthControls.className = 'component-width-controls';

      var narrowButton = document.createElement('button');
      narrowButton.type = 'button';
      narrowButton.className = 'btn';
      narrowButton.textContent = '−';
      narrowButton.title = componentConfigLabels.widthNarrower;
      narrowButton.addEventListener('click', function () {
        resizeSelectedLayoutItemWidth('narrower');
      });

      var widthValue = document.createElement('span');
      widthValue.className = 'component-width-value';
      widthValue.textContent = String(Math.max(1, Math.min(12, parseInt(item.w, 10) || 12))) + ' / 12';

      var wideButton = document.createElement('button');
      wideButton.type = 'button';
      wideButton.className = 'btn';
      wideButton.textContent = '+';
      wideButton.title = componentConfigLabels.widthWider;
      wideButton.addEventListener('click', function () {
        resizeSelectedLayoutItemWidth('wider');
      });

      var currentWidthIndex = layoutWidthSteps.indexOf(normalizeLayoutWidth(item.w));
      narrowButton.disabled = currentWidthIndex <= 0;
      wideButton.disabled = currentWidthIndex >= layoutWidthSteps.length - 1;

      widthControls.appendChild(narrowButton);
      widthControls.appendChild(widthValue);
      widthControls.appendChild(wideButton);
      widthLabel.appendChild(widthControls);
      componentConfigPanel.appendChild(widthLabel);

      var groupLabel = document.createElement('label');
      groupLabel.className = 'form-label';
      groupLabel.textContent = componentConfigLabels.group;
      var groupInput = document.createElement('input');
      groupInput.className = 'form-input';
      groupInput.type = 'text';
      groupInput.value = String(item.group || 'default');
      groupInput.addEventListener('input', function () {
        item.group = toKey(groupInput.value || 'default') || 'default';
        buildInternalJson();
      });
      groupLabel.appendChild(groupInput);
      componentConfigPanel.appendChild(groupLabel);

      var linkedViews = availableLinkedViews();
      if (linkedViews.length > 0) {
        var openViewLabel = document.createElement('label');
        openViewLabel.className = 'form-label';
        openViewLabel.textContent = componentConfigLabels.openView;
        var openViewSelect = document.createElement('select');
        openViewSelect.className = 'form-input';
        var noneOption = document.createElement('option');
        noneOption.value = '';
        noneOption.textContent = componentConfigLabels.openViewNone;
        openViewSelect.appendChild(noneOption);
        linkedViews.forEach(function (entry) {
          var option = document.createElement('option');
          option.value = entry.id;
          option.textContent = String(entry.name || entry.id);
          openViewSelect.appendChild(option);
        });
        var activeView = activeMultiViewEntry();
        var linkedTargetViewId = activeView && activeView.links && typeof activeView.links === 'object'
          ? String(activeView.links[item.id] || '')
          : '';
        openViewSelect.value = linkedTargetViewId;
        openViewSelect.addEventListener('change', function () {
          updateSelectedItemOpenView(openViewSelect.value);
        });
        openViewLabel.appendChild(openViewSelect);
        componentConfigPanel.appendChild(openViewLabel);
      }

      var definition = getComponentDefinition(item.component);
      var fields = definition.configFields();
      fields.forEach(function (fieldDef) {
        var label = document.createElement('label');
        label.className = 'form-label';
        label.textContent = String(fieldDef.label || fieldDef.key);

        var input = null;
        var currentValue = (item.props || {})[fieldDef.key];
        if (fieldDef.type === 'textarea') {
          input = document.createElement('textarea');
          input.className = 'form-input';
          input.rows = 3;
          input.value = String(currentValue || '');
          input.addEventListener('input', function () {
            updateSelectedItemConfig(fieldDef.key, input.value);
          });
        } else if (fieldDef.type === 'checkbox') {
          input = document.createElement('input');
          input.type = 'checkbox';
          input.checked = !!currentValue;
          input.addEventListener('change', function () {
            updateSelectedItemConfig(fieldDef.key, input.checked);
          });
        } else if (fieldDef.type === 'select') {
          input = document.createElement('select');
          input.className = 'form-input';
          (Array.isArray(fieldDef.options) ? fieldDef.options : []).forEach(function (optionDef) {
            var opt = document.createElement('option');
            opt.value = String(optionDef.value || '');
            opt.textContent = String(optionDef.label || optionDef.value || '');
            if (opt.value === String(currentValue || '')) {
              opt.selected = true;
            }
            input.appendChild(opt);
          });
          input.addEventListener('change', function () {
            updateSelectedItemConfig(fieldDef.key, input.value);
          });
        } else {
          input = document.createElement('input');
          input.className = 'form-input';
          input.type = fieldDef.type === 'number' ? 'number' : 'text';
          if (fieldDef.type === 'number') {
            if (typeof fieldDef.min === 'number') input.min = String(fieldDef.min);
            if (typeof fieldDef.max === 'number') input.max = String(fieldDef.max);
          }
          input.value = String(typeof currentValue === 'undefined' ? '' : currentValue);
          input.addEventListener('input', function () {
            var nextValue = fieldDef.type === 'number' ? parseInt(input.value, 10) : input.value;
            updateSelectedItemConfig(fieldDef.key, nextValue);
          });
        }

        label.appendChild(input);
        componentConfigPanel.appendChild(label);
      });

      var bindingErrorKey = (builderState.bindingErrors || {})[item.id] || '';
      if (bindingErrorKey) {
        var bindingError = document.createElement('div');
        bindingError.className = 'note warning';
        bindingError.textContent = bindingErrorLabels[bindingErrorKey] || bindingErrorLabels.invalid_path;
        componentConfigPanel.appendChild(bindingError);
      }

      var bindingLabel = document.createElement('label');
      bindingLabel.className = 'form-label';
      bindingLabel.textContent = componentConfigLabels.bindingPicker;
      var bindingSelect = document.createElement('select');
      bindingSelect.className = 'form-input';
      var grouped = {};
      bindingSchemaOptions().forEach(function (option) {
        var group = option.group || 'module';
        if (!grouped[group]) grouped[group] = [];
        grouped[group].push(option);
      });
      Object.keys(grouped).forEach(function (groupName) {
        var optGroup = document.createElement('optgroup');
        optGroup.label = groupName;
        grouped[groupName].forEach(function (option) {
          var opt = document.createElement('option');
          opt.value = option.value;
          opt.textContent = option.label;
          if (option.value === String(item.data_binding || '')) {
            opt.selected = true;
          }
          optGroup.appendChild(opt);
        });
        bindingSelect.appendChild(optGroup);
      });
      bindingSelect.addEventListener('change', function () {
        updateSelectedItemBinding(bindingSelect.value);
      });
      bindingLabel.appendChild(bindingSelect);
      componentConfigPanel.appendChild(bindingLabel);

      var bindingPath = document.createElement('div');
      bindingPath.className = 'note';
      bindingPath.textContent = componentConfigLabels.bindingSource + ': ' + String(item.data_binding || '');
      componentConfigPanel.appendChild(bindingPath);
    }

    function bindDragAndResize(block, item) {
      return;
      var header = block.querySelector('[data-layout-drag]');
      var handle = block.querySelector('[data-layout-resize]');
      if (header) {
        header.addEventListener('mousedown', function (event) {
          event.preventDefault();
          var start = { x: event.clientX, y: event.clientY };
          var original = { x: item.x, y: item.y };
          var metrics = getCanvasGridMetrics();
          function onMove(moveEvent) {
            var dx = moveEvent.clientX - start.x;
            var dy = moveEvent.clientY - start.y;
            var nextX = original.x + Math.round(dx / metrics.cellWidth);
            var nextY = original.y + Math.round(dy / metrics.rowHeight);
            moveLayoutItem(item.id, nextX, nextY);
          }
          function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
          }
          document.addEventListener('mousemove', onMove);
          document.addEventListener('mouseup', onUp);
        });
      }
      if (handle) {
        handle.addEventListener('mousedown', function (event) {
          event.preventDefault();
          var start = { x: event.clientX, y: event.clientY };
          var original = { w: item.w, h: item.h };
          var metrics = getCanvasGridMetrics();
          function onMove(moveEvent) {
            var dx = moveEvent.clientX - start.x;
            var dy = moveEvent.clientY - start.y;
            var nextW = original.w + Math.round(dx / metrics.cellWidth);
            var nextH = original.h + Math.round(dy / metrics.rowHeight);
            resizeLayoutItem(item.id, nextW, nextH);
          }
          function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
          }
          document.addEventListener('mousemove', onMove);
          document.addEventListener('mouseup', onUp);
        });
      }
    }

    function renderLayoutCanvas() {
      if (!layoutCanvas) return;
      var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
      var rows = layoutRowsFromItems(items);
      layoutCanvas.innerHTML = '';

      if (rows.length === 0) {
        clearLayoutDropTargets();
        var empty = document.createElement('div');
        empty.className = 'muted';
        empty.style.padding = '16px';
        empty.textContent = visualBuilderLabels.empty;
        layoutCanvas.appendChild(empty);
        return;
      }

      rows.forEach(function (row) {
        var rowEl = document.createElement('div');
        rowEl.className = 'grid-row';
        rowEl.setAttribute('data-drop-row-index', String(row.row - 1));
        rowEl.setAttribute('data-drop-column-index', String(row.columns.length));
        rowEl.setAttribute('data-drop-mode', 'row-end');

        rowEl.addEventListener('dragover', function (event) {
          if (!layoutDragState.itemId) return;
          var targetMeta = readLayoutDropTarget(rowEl);
          var candidate = canPlaceLayoutItem(
            cloneLayoutRows(layoutRowsFromItems(builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [])),
            layoutDragState.itemId,
            targetMeta.rowIndex,
            targetMeta.columnIndex,
            targetMeta.dropMode
          );
          if (!candidate.valid) {
            markLayoutDropTarget(rowEl, false);
            return;
          }
          event.preventDefault();
          layoutDragState.targetRowIndex = targetMeta.rowIndex;
          layoutDragState.targetColumnIndex = targetMeta.columnIndex;
          layoutDragState.dropMode = targetMeta.dropMode;
          markLayoutDropTarget(rowEl, true);
        });

        rowEl.addEventListener('drop', function (event) {
          if (!layoutDragState.itemId) return;
          event.preventDefault();
          layoutDragState.committed = true;
          applyLayoutDrop(layoutDragState.itemId, readLayoutDropTarget(rowEl));
          clearLayoutDropTargets();
        });

        rowEl.addEventListener('dragleave', function (event) {
          if (event.target === rowEl) {
            clearLayoutDropTargets();
          }
        });

        row.columns.forEach(function (column, columnIndex) {
          var width = Math.max(1, Math.min(12, parseInt(column.width, 10) || 12));
          var colEl = document.createElement('div');
          colEl.className = 'grid-col col-' + String(width);
          colEl.setAttribute('data-drop-row-index', String(row.row - 1));
          colEl.setAttribute('data-drop-column-index', String(columnIndex));
          colEl.setAttribute('data-drop-mode', 'before-column');

          colEl.addEventListener('dragover', function (event) {
            if (!layoutDragState.itemId) return;
            var targetMeta = readLayoutDropTarget(colEl);
            var candidate = canPlaceLayoutItem(
              cloneLayoutRows(layoutRowsFromItems(builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [])),
              layoutDragState.itemId,
              targetMeta.rowIndex,
              targetMeta.columnIndex,
              targetMeta.dropMode
            );
            if (!candidate.valid) {
              markLayoutDropTarget(colEl, false);
              return;
            }
            event.preventDefault();
            event.stopPropagation();
            layoutDragState.targetRowIndex = targetMeta.rowIndex;
            layoutDragState.targetColumnIndex = targetMeta.columnIndex;
            layoutDragState.dropMode = targetMeta.dropMode;
            markLayoutDropTarget(colEl, true);
          });

          colEl.addEventListener('drop', function (event) {
            if (!layoutDragState.itemId) return;
            event.preventDefault();
            event.stopPropagation();
            layoutDragState.committed = true;
            applyLayoutDrop(layoutDragState.itemId, readLayoutDropTarget(colEl));
            clearLayoutDropTargets();
          });

          colEl.addEventListener('dragleave', function (event) {
            if (event.target === colEl) {
              clearLayoutDropTargets();
            }
          });

          var componentEl = document.createElement('div');
          componentEl.className = 'component component-' + String(column.type || 'text');
          componentEl.setAttribute('draggable', 'true');
          componentEl.setAttribute('data-item-id', String(column.itemId || ''));

          componentEl.addEventListener('dragstart', function (event) {
            if (!column.itemId) {
              event.preventDefault();
              return;
            }
            layoutDragState.itemId = String(column.itemId);
            layoutDragState.sourceRowIndex = row.row - 1;
            layoutDragState.sourceColumnIndex = columnIndex;
            layoutDragState.targetRowIndex = row.row - 1;
            layoutDragState.targetColumnIndex = columnIndex;
            layoutDragState.dropMode = 'before-column';
            layoutDragState.committed = false;
            componentEl.classList.add('is-dragging');
            if (event.dataTransfer) {
              event.dataTransfer.effectAllowed = 'move';
              event.dataTransfer.setData('text/plain', String(column.itemId));
            }
          });

          componentEl.addEventListener('dragend', function () {
            if (!layoutDragState.committed && layoutDragState.itemId && layoutDragState.targetRowIndex >= 0) {
              applyLayoutDrop(layoutDragState.itemId, {
                rowIndex: layoutDragState.targetRowIndex,
                columnIndex: layoutDragState.targetColumnIndex,
                dropMode: layoutDragState.dropMode
              });
            }
            componentEl.classList.remove('is-dragging');
            layoutDragState.itemId = '';
            layoutDragState.sourceRowIndex = -1;
            layoutDragState.sourceColumnIndex = -1;
            layoutDragState.targetRowIndex = -1;
            layoutDragState.targetColumnIndex = -1;
            layoutDragState.dropMode = 'row-end';
            layoutDragState.committed = false;
            clearLayoutDropTargets();
          });

          var headerEl = document.createElement('div');
          headerEl.className = 'component-header';

          var headerTitle = document.createElement('span');
          headerTitle.textContent = String(column.label || defaultLabelForPhaseOneType(column.type || 'text'));

          var headerMeta = document.createElement('span');
          var typeTag = document.createElement('span');
          typeTag.className = 'component-type-tag';
          typeTag.textContent = componentTypeLabel(column.type || 'text');

          var widthTag = document.createElement('span');
          widthTag.className = 'component-width-label';
          widthTag.textContent = componentConfigLabels.width + ': ' + String(width);

          headerMeta.appendChild(typeTag);
          headerMeta.appendChild(widthTag);
          headerEl.appendChild(headerTitle);
          headerEl.appendChild(headerMeta);

          var bodyEl = document.createElement('div');
          bodyEl.className = 'component-body';
          bodyEl.textContent = visualBuilderPhaseOneLabels.preview;

          componentEl.appendChild(headerEl);
          componentEl.appendChild(bodyEl);

          var actionsEl = document.createElement('div');
          actionsEl.className = 'component-inline-actions';

          var duplicateButton = document.createElement('button');
          duplicateButton.type = 'button';
          duplicateButton.className = 'btn';
          duplicateButton.textContent = interactionLabels.duplicate;
          duplicateButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (!column.itemId) return;
            duplicateLayoutItem(column.itemId);
            refreshVisualBuilder();
          });

          var deleteButton = document.createElement('button');
          deleteButton.type = 'button';
          deleteButton.className = 'btn danger';
          deleteButton.textContent = interactionLabels.del;
          deleteButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (!column.itemId) return;
            removeLayoutItem(column.itemId);
            refreshVisualBuilder();
          });

          actionsEl.appendChild(duplicateButton);
          actionsEl.appendChild(deleteButton);
          componentEl.appendChild(actionsEl);

          if (column.itemId) {
            componentEl.style.cursor = 'pointer';
            if (builderState.selectedItemId === column.itemId) {
              componentEl.classList.add('is-selected');
            }
            componentEl.addEventListener('click', function () {
              selectLayoutItem(column.itemId);
            });
          }

          colEl.appendChild(componentEl);
          rowEl.appendChild(colEl);
        });

        var rowAddWrap = document.createElement('div');
        rowAddWrap.className = 'component-inline-actions';
        rowAddWrap.style.justifyContent = 'flex-start';
        rowAddWrap.style.marginTop = '0.4rem';

        var rowAddButton = document.createElement('button');
        rowAddButton.type = 'button';
        rowAddButton.className = 'btn btn-primary-action';
        rowAddButton.textContent = interactionLabels.addInline;
        rowAddButton.addEventListener('click', function (event) {
          event.preventDefault();
          addLayoutItemInRow(rowIndex, 'text');
          refreshVisualBuilder();
        });

        rowAddWrap.appendChild(rowAddButton);
        rowEl.appendChild(rowAddWrap);

        layoutCanvas.appendChild(rowEl);
      });
    }

    function renderLayoutItemsList() {
      if (!layoutItemsList) return;
      layoutItemsList.innerHTML = '';
      var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
      var grouped = {};
      items.forEach(function (item) {
        var key = item.group || 'default';
        if (!grouped[key]) grouped[key] = [];
        grouped[key].push(item);
      });
      Object.keys(grouped).sort().forEach(function (groupName) {
        var groupHeader = document.createElement('div');
        groupHeader.className = 'muted';
        groupHeader.textContent = componentConfigLabels.group + ': ' + groupName;
        layoutItemsList.appendChild(groupHeader);
        grouped[groupName].forEach(function (item) {
        var bindingErrorKey = (builderState.bindingErrors || {})[item.id] || '';
        var row = document.createElement('div');
        row.className = 'note';
        var suffix = bindingErrorKey ? ' • ' + (bindingErrorLabels[bindingErrorKey] || bindingErrorLabels.invalid_path) : '';
        row.textContent = itemDisplayTitle(item) + suffix;
        row.style.cursor = 'pointer';
        if (builderState.selectedItemId === item.id) {
          row.classList.add('is-selected');
        }
        if (bindingErrorKey) {
          row.style.borderColor = 'var(--danger-color)';
          row.style.background = 'var(--danger-bg)';
        }
        row.addEventListener('click', function () {
          selectLayoutItem(item.id);
        });

        var actionWrap = document.createElement('div');
        actionWrap.style.marginTop = '0.35rem';
        actionWrap.style.display = 'flex';
        actionWrap.style.gap = '0.35rem';

        var upButton = document.createElement('button');
        upButton.type = 'button';
        upButton.className = 'btn';
        upButton.textContent = relationLabels.up;
        upButton.addEventListener('click', function (event) {
          event.stopPropagation();
          moveLayoutItemOrder(item.id, 'up');
          refreshVisualBuilder();
        });

        var downButton = document.createElement('button');
        downButton.type = 'button';
        downButton.className = 'btn';
        downButton.textContent = relationLabels.down;
        downButton.addEventListener('click', function (event) {
          event.stopPropagation();
          moveLayoutItemOrder(item.id, 'down');
          refreshVisualBuilder();
        });

        actionWrap.appendChild(upButton);
        actionWrap.appendChild(downButton);
        row.appendChild(actionWrap);

        layoutItemsList.appendChild(row);
        });
      });
      if (items.length === 0) {
        var empty = document.createElement('div');
        empty.className = 'muted';
        empty.textContent = visualBuilderLabels.empty;
        layoutItemsList.appendChild(empty);
      }
    }

    function normalizeRelation(input) {
      if (!input || typeof input !== 'object') return null;
      var supportedRelationTypes = ['affects_table', 'filter_to_table', 'filter_to_kpi', 'form_refresh_table', 'table_to_kpi_derived'];
      var sourceId = toKey(input.source_id || '');
      var targetId = toKey(input.target_id || '');
      var type = toKey(input.type || 'affects_table') || 'affects_table';
      if (!sourceId || !targetId) return null;
      if (supportedRelationTypes.indexOf(type) === -1) type = 'affects_table';
      return { source_id: sourceId, target_id: targetId, type: type };
    }

    function updateRelationAt(index, partial) {
      if (!builderState.layout || !Array.isArray(builderState.layout.relations)) return;
      if (index < 0 || index >= builderState.layout.relations.length) return;
      var next = Object.assign({}, builderState.layout.relations[index], partial || {});
      var normalized = normalizeRelation(next);
      if (!normalized) return;
      builderState.layout.relations[index] = normalized;
      buildInternalJson();
      renderRelationsEditor();
    }

    function renderRelationsEditor() {
      if (!layoutRelationsList) return;
      layoutRelationsList.innerHTML = '';
      var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
      var ids = items.map(function (item) { return item.id; });
      var relations = builderState.layout && Array.isArray(builderState.layout.relations) ? builderState.layout.relations : [];
      if (relations.length === 0) {
        var empty = document.createElement('div');
        empty.className = 'muted';
        empty.textContent = relationLabels.empty;
        layoutRelationsList.appendChild(empty);
        return;
      }
      relations.forEach(function (relation, index) {
        var row = document.createElement('div');
        row.className = 'note';

        var sourceLabel = document.createElement('label');
        sourceLabel.className = 'form-label';
        sourceLabel.textContent = relationLabels.source;
        var sourceSelect = document.createElement('select');
        sourceSelect.className = 'form-input';
        ids.forEach(function (itemId) {
          var opt = document.createElement('option');
          opt.value = itemId;
          opt.textContent = itemId;
          if (itemId === relation.source_id) opt.selected = true;
          sourceSelect.appendChild(opt);
        });
        sourceSelect.addEventListener('change', function () {
          updateRelationAt(index, { source_id: sourceSelect.value });
        });
        sourceLabel.appendChild(sourceSelect);
        row.appendChild(sourceLabel);

        var targetLabel = document.createElement('label');
        targetLabel.className = 'form-label';
        targetLabel.textContent = relationLabels.target;
        var targetSelect = document.createElement('select');
        targetSelect.className = 'form-input';
        ids.forEach(function (itemId) {
          var opt = document.createElement('option');
          opt.value = itemId;
          opt.textContent = itemId;
          if (itemId === relation.target_id) opt.selected = true;
          targetSelect.appendChild(opt);
        });
        targetSelect.addEventListener('change', function () {
          updateRelationAt(index, { target_id: targetSelect.value });
        });
        targetLabel.appendChild(targetSelect);
        row.appendChild(targetLabel);

        var typeLabel = document.createElement('label');
        typeLabel.className = 'form-label';
        typeLabel.textContent = relationLabels.type;
        var typeSelect = document.createElement('select');
        typeSelect.className = 'form-input';
        [
          { value: 'affects_table', label: relationLabels.affectsTable },
          { value: 'filter_to_table', label: relationLabels.filterToTable },
          { value: 'filter_to_kpi', label: relationLabels.filterToKpi },
          { value: 'form_refresh_table', label: relationLabels.formRefreshTable },
          { value: 'table_to_kpi_derived', label: relationLabels.tableToKpiDerived }
        ].forEach(function (typeDef) {
          var opt = document.createElement('option');
          opt.value = typeDef.value;
          opt.textContent = typeDef.label;
          if (typeDef.value === relation.type) opt.selected = true;
          typeSelect.appendChild(opt);
        });
        typeSelect.addEventListener('change', function () {
          updateRelationAt(index, { type: typeSelect.value });
        });
        typeLabel.appendChild(typeSelect);
        row.appendChild(typeLabel);

        var removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn danger';
        removeButton.textContent = visualBuilderLabels.remove;
        removeButton.addEventListener('click', function () {
          builderState.layout.relations.splice(index, 1);
          buildInternalJson();
          renderRelationsEditor();
        });
        row.appendChild(removeButton);

        layoutRelationsList.appendChild(row);
      });
    }

    function renderComponentPalette() {
      if (!componentPalette) return;
      var paletteButtons = componentPalette.querySelectorAll('button[data-type]');
      paletteButtons.forEach(function (button) {
        button.onclick = function () {
          var type = String(button.getAttribute('data-type') || 'text');
          addLayoutItem(type, 0, 0);
        };
      });
    }

    function renderTemplatePanel() {
      var orderedTemplates = orderedVisualBuilderTemplates();
      var recommendedTemplates = orderedTemplates.filter(function (template) {
        return template.score > 0;
      });

      renderVisualBuilderSystemTemplateButtons(recommendedTemplatesList, recommendedTemplates);
      renderVisualBuilderSystemTemplateButtons(allTemplatesList, orderedTemplates);

      if (saveTemplateButton) {
        saveTemplateButton.onclick = function () {
          saveCurrentLayoutAsTemplate();
        };
      }

      if (!userTemplatesList) return;
      userTemplatesList.innerHTML = '';
      loadVisualBuilderUserTemplates().forEach(function (template, index) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn';
        button.textContent = template.name;
        button.setAttribute('data-user-template-index', String(index));
        button.onclick = function () {
          applyUserLayoutTemplate(index);
        };
        userTemplatesList.appendChild(button);
      });
    }

    function refreshVisualBuilder() {
      var bindingErrors = recomputeBindingErrors();
      renderViewManagerPanel();
      renderTemplatePanel();
      renderSmartSuggestions();
      renderComponentPalette();
      renderLayoutCanvas();
      renderLayoutItemsList();
      renderBindingSchemaTree();
      renderComponentConfigPanel();
      renderRelationsEditor();
      if (typeof renderContentOutline === 'function') {
        renderContentOutline();
      }
      if (Object.keys(bindingErrors).length > 0) {
        setBuilderWarning('binding_invalid');
      } else if (builderState.warning === 'binding_invalid') {
        setBuilderWarning('');
      }
      setBuilderWarning(builderState.warning);
      if (visualBuilderPreviewToggle) {
        visualBuilderPreviewToggle.checked = !!builderState.previewMode;
      }
    }

    function readStructuredFromJson() {
      var appManifest = safeParseJson(appManifestInput.value, {});
      var moduleManifest = safeParseJson(moduleManifestInput.value, {});
      var viewDefinition = safeParseJson(viewDefinitionInput.value, {});
      var navigationDefinition = safeParseJson(navigationDefinitionInput.value, {});

      var module = moduleManifest.module || {};
      var view = viewDefinition.view || {};
      var layout = viewDefinition.layout || {};
      var navigation = navigationDefinition.navigation || {};

      moduleKeyInput.value = String(module.module_key || view.module_key || '');
      moduleDisplayInput.value = String(module.display_name || '');
      moduleTypeInput.value = String(module.module_type || 'crud');
      moduleDescriptionInput.value = String(module.description || '');
      routePathInput.value = String(view.route_path || module.route_base || '');

      var sourceFields = [];
      if (Array.isArray(moduleManifest.fields)) {
        sourceFields = moduleManifest.fields;
      } else if (Array.isArray(viewDefinition.fields)) {
        sourceFields = viewDefinition.fields;
      } else if (Array.isArray(view.fields)) {
        sourceFields = view.fields.map(function (key) { return { key: key, type: 'string' }; });
      }
      structuredState.fields = sourceFields.map(normalizeField).filter(function (field) {
        return field.key !== '';
      });

      viewKindInput.value = String(view.view_kind || layout.kind || 'table');
      var security = viewDefinition.security && typeof viewDefinition.security === 'object' ? viewDefinition.security : {};
      var initialTableEditMode = !!security.direct_db_mutation_allowed ? 'direct_db' : 'view_only';
      Array.prototype.forEach.call(document.querySelectorAll('#gs_se_table_edit_mode'), function (modeNode) {
        modeNode.value = initialTableEditMode;
      });
      layoutCompactInput.checked = !!layout.compact;
      structuredState.columns = Array.isArray(view.fields)
        ? view.fields.map(function (col) { return toKey(col); }).filter(Boolean)
        : structuredState.fields.map(function (field) { return field.key; });

      builderState.layout = normalizeLayoutData(layout, String(view.view_kind || 'table'));
      builderState.viewKind = String(view.view_kind || layout.kind || 'table');
      builderState.previewMode = !!layout.preview_mode;
      builderState.warning = '';
      builderState.componentState = {};
      builderState.selectedItemId = builderState.layout.items[0] ? builderState.layout.items[0].id : '';

      navSectionInput.value = String(navigation.section || 'Apps');
      navGroupInput.value = String(navigation.group || 'Apps');
      navLabelInput.value = String(navigation.label || module.display_name || '');
      navTargetInput.value = String(navigation.url || view.route_path || routePathInput.value || '');
      navIconInput.value = String(navigation.icon || '');
      navOrderInput.value = String(Number.isFinite(Number(navigation.order)) ? Number(navigation.order) : 10);
      navVisibleInput.checked = String(navigation.visible_if || '').toLowerCase() !== 'never';

      var appData = appManifest.app || {};
      if (!routePathInput.value) {
        var appSlug = toSlug(appData.app_key || 'generated_app');
        var modSlug = toSlug(moduleKeyInput.value || 'generated_module');
        routePathInput.value = '/apps/' + appSlug + '/' + modSlug;
      }

      // Populate app settings fields when bundle contains app manifest data
      var appKeyInput = document.getElementById('gs_se_app_key');
      var appDisplayNameInput = document.getElementById('gs_se_app_display_name');
      var appTypeInput = document.getElementById('gs_se_app_type');
      var appVersionInput = document.getElementById('gs_se_app_version');
      if (appData && appData.app_key) {
        if (appKeyInput) { appKeyInput.value = String(appData.app_key || ''); }
        if (appDisplayNameInput) { appDisplayNameInput.value = String(appData.display_name || ''); }
        if (appTypeInput) { appTypeInput.value = String(appData.type || 'business'); }
        if (appVersionInput) { appVersionInput.value = String(appManifest.version || appData.version || ''); }
      } else {
        if (appKeyInput) { appKeyInput.value = ''; }
        if (appDisplayNameInput) { appDisplayNameInput.value = ''; }
        if (appTypeInput) { appTypeInput.value = 'business'; }
        if (appVersionInput) { appVersionInput.value = ''; }
      }
    }

    function renderColumns() {
      if (!viewColumnsWrap) return;
      viewColumnsWrap.innerHTML = '';
      structuredState.fields.forEach(function (field) {
        var wrap = document.createElement('label');
        wrap.className = 'form-label';
        wrap.style.display = 'flex';
        wrap.style.gap = '0.35rem';
        wrap.style.alignItems = 'center';

        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.value = field.key;
        cb.checked = structuredState.columns.indexOf(field.key) !== -1;
        cb.addEventListener('change', function () {
          if (cb.checked) {
            if (structuredState.columns.indexOf(field.key) === -1) {
              structuredState.columns.push(field.key);
            }
          } else {
            structuredState.columns = structuredState.columns.filter(function (key) { return key !== field.key; });
          }
          updateHiddenStates();
          buildInternalJson();
        });

        wrap.appendChild(cb);
        wrap.appendChild(document.createTextNode(field.key));
        viewColumnsWrap.appendChild(wrap);
      });
    }

    function currentViewKindValue() {
      var candidates = Array.prototype.slice.call(document.querySelectorAll('#gs_se_view_kind'));
      var visible = candidates.find(function (node) {
        return !!(node && !node.hidden && node.offsetParent !== null);
      });
      var active = visible || candidates[0] || viewKindInput || null;
      return String((active && active.value) || 'table');
    }

    function currentTableEditModeValue() {
      var candidates = Array.prototype.slice.call(document.querySelectorAll('#gs_se_table_edit_mode'));
      var visible = candidates.find(function (node) {
        return !!(node && !node.hidden && node.offsetParent !== null);
      });
      var active = visible || candidates[0] || tableEditModeInput || null;
      var value = String((active && active.value) || 'view_only');
      return value === 'direct_db' ? 'direct_db' : 'view_only';
    }

    function currentCreateIntentValue() {
      var candidates = Array.prototype.slice.call(document.querySelectorAll('#gs_se_create_intent'));
      var visible = candidates.find(function (node) {
        return !!(node && !node.hidden && node.offsetParent !== null);
      });
      var active = visible || candidates[0] || createIntentInput || null;
      return String((active && active.value) || 'create_view').toLowerCase();
    }

    function currentModuleTypeValue() {
      var candidates = Array.prototype.slice.call(document.querySelectorAll('#gs_se_module_type'));
      var visible = candidates.find(function (node) {
        return !!(node && !node.hidden && node.offsetParent !== null);
      });
      var active = visible || candidates[0] || moduleTypeInput || null;
      return String((active && active.value) || 'crud').toLowerCase();
    }

    var dashboardIntentActive = false;
    var previousModuleTypeBeforeDashboardIntent = 'crud';
    var previousViewKindBeforeDashboardIntent = 'table';

    function applyIntentDrivenDefaults() {
      var createIntent = currentCreateIntentValue();
      var moduleType = currentModuleTypeValue();
      var viewKind = currentViewKindValue().toLowerCase();
      if (createIntent === 'create_dashboard') {
        if (!dashboardIntentActive) {
          previousModuleTypeBeforeDashboardIntent = moduleType || 'crud';
          previousViewKindBeforeDashboardIntent = viewKind || 'table';
          dashboardIntentActive = true;
        }
        syncDuplicateSelectValues('#gs_se_module_type', 'dashboard');
        syncDuplicateSelectValues('#gs_se_view_kind', 'dashboard');
        return;
      }
      if (dashboardIntentActive) {
        if (moduleType === 'dashboard') {
          var restoredModuleType = previousModuleTypeBeforeDashboardIntent === 'dashboard'
            ? 'crud'
            : previousModuleTypeBeforeDashboardIntent;
          syncDuplicateSelectValues('#gs_se_module_type', restoredModuleType || 'crud');
        }
        if (viewKind === 'dashboard') {
          var restoredViewKind = previousViewKindBeforeDashboardIntent || 'table';
          syncDuplicateSelectValues('#gs_se_view_kind', restoredViewKind);
        }
      }
      dashboardIntentActive = false;
    }

    function syncDuplicateSelectValues(selector, value, sourceNode) {
      Array.prototype.forEach.call(document.querySelectorAll(selector), function (node) {
        if (!node || node === sourceNode) {
          return;
        }
        node.value = String(value || '');
      });
    }

    var lastContentAwareViewKind = currentViewKindValue();
    var lastContentAwareVisibilitySignature = '';

    function syncContentAwareEditorVisibility(force) {
      applyIntentDrivenDefaults();
      var viewKind = currentViewKindValue().toLowerCase();
      var createIntent = currentCreateIntentValue();
      var tableEditMode = currentTableEditModeValue();
      var showCreateIntent = !(typeof window.isLoadedContentMode === 'function' && window.isLoadedContentMode());
      var visibilitySignature = [createIntent, viewKind, tableEditMode, showCreateIntent ? '1' : '0'].join('|');
      if (!force && visibilitySignature === lastContentAwareVisibilitySignature) {
        return;
      }
      lastContentAwareVisibilitySignature = visibilitySignature;
      var nextKind = currentViewKindValue();
      if (nextKind !== lastContentAwareViewKind) {
        lastContentAwareViewKind = nextKind;
        if (nextKind !== builderState.viewKind) {
          builderState.viewKind = nextKind;
          builderState.layout = defaultLayoutForView(nextKind);
          builderState.componentState = {};
          builderState.selectedItemId = builderState.layout.items[0] ? builderState.layout.items[0].id : '';
          setBuilderWarning('');
        }
      }
      updateContentAwareEditorVisibility();
    }

    function updateContentAwareEditorVisibility() {
      var viewKind = currentViewKindValue().toLowerCase();
      var createIntent = currentCreateIntentValue();
      var tableEditMode = currentTableEditModeValue();
      var loadedMode = typeof window.isLoadedContentMode === 'function' && window.isLoadedContentMode();

      var showCreateIntent, showViewKind, showModuleDetails, showFieldEditors, showTableEditMode, showRoutePath, showNavigationEditor, showVisualBuilder, showSurfaceExposure;

      if (loadedMode) {
        // Type-aware dispatch: derive panel visibility from the loaded bundle's content type
        var loadedType = typeof window.getLoadedBundleContentType === 'function'
          ? window.getLoadedBundleContentType()
          : 'unknown';
        showCreateIntent = false;
        showViewKind      = (loadedType === 'view' || loadedType === 'db_table');
        showModuleDetails = (loadedType === 'view' || loadedType === 'db_table' || loadedType === 'module' || loadedType === 'dashboard');
        showFieldEditors  = (loadedType === 'view' || loadedType === 'db_table') && (viewKind === 'table' || viewKind === 'form');
        showTableEditMode = (loadedType === 'view' || loadedType === 'db_table') && (viewKind === 'table');
        showRoutePath     = (loadedType === 'module' || loadedType === 'view' || loadedType === 'db_table' || loadedType === 'dashboard');
        showNavigationEditor = loadedType === 'navigation';
        showVisualBuilder = (loadedType === 'view' || loadedType === 'db_table' || loadedType === 'dashboard');
        showSurfaceExposure = (loadedType === 'view' || loadedType === 'db_table' || loadedType === 'dashboard');
        if (loadedType === 'dashboard' && viewKind !== 'dashboard' && typeof syncDuplicateSelectValues === 'function') {
          syncDuplicateSelectValues('#gs_se_view_kind', 'dashboard');
        }
        // Sync create_intent select to match the loaded type (so outline stays coherent)
        var intentForType = {
          app: 'create_app',
          module: 'create_module',
          navigation: 'create_navigation',
          db_table: 'create_view',
          dashboard: 'create_dashboard',
          view: 'create_view',
          unknown: 'create_view'
        };
        var targetIntent = intentForType[loadedType] || 'create_view';
        if (createIntent !== targetIntent) {
          if (typeof syncDuplicateSelectValues === 'function') {
            syncDuplicateSelectValues('#gs_se_create_intent', targetIntent);
          }
        }
      } else {
        var isAppIntent        = createIntent === 'create_app';
        var isModuleIntent     = createIntent === 'create_module';
        var isNavigationIntent = createIntent === 'create_navigation';
        var isDashboardIntent  = createIntent === 'create_dashboard';
        showCreateIntent  = true;
        showViewKind      = !isNavigationIntent && !isDashboardIntent && !isAppIntent && !isModuleIntent;
        showModuleDetails = !isNavigationIntent && !isAppIntent;
        showFieldEditors  = showViewKind && (viewKind === 'table' || viewKind === 'form');
        showTableEditMode = showViewKind && viewKind === 'table';
        showRoutePath     = !isNavigationIntent && !isAppIntent && !isModuleIntent;
        showNavigationEditor = isNavigationIntent;
        showVisualBuilder = !isNavigationIntent && !isAppIntent && !isModuleIntent;
        showSurfaceExposure = false;
      }

      var showAppSettings = (typeof loadedType !== 'undefined' && loadedType === 'app') ||
        (!loadedMode && createIntent === 'create_app');

      var hideViewOnlyControls = showTableEditMode && tableEditMode === 'direct_db';

      Array.prototype.forEach.call(document.querySelectorAll('.gs-app-settings-control'), function (node) {
        node.hidden = !showAppSettings;
      });

      Array.prototype.forEach.call(document.querySelectorAll('.gs-create-intent-control'), function (node) {
        node.hidden = !showCreateIntent;
      });

      Array.prototype.forEach.call(document.querySelectorAll('.gs-view-kind-control'), function (node) {
        node.hidden = !showViewKind;
      });

      Array.prototype.forEach.call(document.querySelectorAll('.gs-module-details-control'), function (node) {
        node.hidden = !showModuleDetails;
      });

      Array.prototype.forEach.call(document.querySelectorAll('.gs-module-settings-control'), function (node) {
        node.hidden = !showModuleDetails;
      });

      Array.prototype.forEach.call(document.querySelectorAll('.gs-route-path-control'), function (node) {
        node.hidden = !showRoutePath;
      });

      Array.prototype.forEach.call(document.querySelectorAll('.gs-navigation-control'), function (node) {
        node.hidden = !showNavigationEditor;
      });

      Array.prototype.forEach.call(document.querySelectorAll('.gs-surface-exposure-control'), function (node) {
        node.hidden = !showSurfaceExposure;
      });

      Array.prototype.forEach.call(document.querySelectorAll('.gs-view-layout-control'), function (node) {
        node.hidden = !showVisualBuilder || showTableEditMode;
      });

      var approvalMetaSection = document.getElementById('gs-approval-meta-section');
      if (approvalMetaSection) {
        approvalMetaSection.hidden = showTableEditMode;
      }

      Array.prototype.forEach.call(document.querySelectorAll('#gs-fields-table'), function (table) {
        var fieldsWrap = table ? table.closest('.table-wrap') : null;
        var fieldsHeading = fieldsWrap && fieldsWrap.previousElementSibling && fieldsWrap.previousElementSibling.tagName === 'H4' ? fieldsWrap.previousElementSibling : null;
        if (fieldsHeading) {
          fieldsHeading.hidden = !showFieldEditors;
        }
        if (fieldsWrap) {
          fieldsWrap.hidden = !showFieldEditors;
        }
      });

      Array.prototype.forEach.call(document.querySelectorAll('#gs-add-field'), function (button) {
        button.hidden = !showFieldEditors;
      });

      Array.prototype.forEach.call(document.querySelectorAll('#gs-view-columns'), function (columnsNode) {
        var columnsBlock = columnsNode ? columnsNode.closest('.ui-block') : null;
        if (columnsBlock) {
          columnsBlock.hidden = !showFieldEditors || hideViewOnlyControls;
        }
      });

      Array.prototype.forEach.call(document.querySelectorAll('.gs-table-edit-mode-control'), function (node) {
        node.hidden = !showTableEditMode;
      });

      Array.prototype.forEach.call(document.querySelectorAll('.gs-table-edit-mode-note'), function (noteNode) {
        if (!noteNode) {
          return;
        }
        noteNode.hidden = !showTableEditMode;
        noteNode.textContent = tableEditMode === 'direct_db'
          ? String(tableEditModeNoteDirectDb || '')
          : String(tableEditModeNoteViewOnly || '');
      });

      // Show or hide the direct DB editor panel
      var dbEditorPanel = document.getElementById('gs-db-editor');
      if (dbEditorPanel) {
        var showDbEditor = showTableEditMode && tableEditMode === 'direct_db';
        dbEditorPanel.classList.toggle('is-hidden', !showDbEditor);
        if (showDbEditor) {
          window.gsDbEditorActivate && window.gsDbEditorActivate();
        }
      }

      if (typeof window.renderSurfaceExposurePanel === 'function') {
        window.renderSurfaceExposurePanel();
      }
    }

    // --- Direct DB Editor ---
    (function () {
      var dbEditorLoaded = false;
      var dbCurrentAppKey = '';
      var dbCurrentTableType = 'orders';
      var dbSchema = [];
      var dbRows = [];

      var dbLabels = {
        loading: <?= json_encode($gs('db_editor_loading')) ?>,
        loadFailed: <?= json_encode($gs('db_editor_load_failed')) ?>,
        empty: <?= json_encode($gs('db_editor_empty')) ?>,
        noApp: <?= json_encode($gs('db_editor_no_app')) ?>,
        save: <?= json_encode($gs('db_editor_save_row')) ?>,
        cancel: <?= json_encode($gs('db_editor_cancel')) ?>,
        del: <?= json_encode($gs('db_editor_delete_row')) ?>,
        confirmDel: <?= json_encode($gs('db_editor_confirm_delete')) ?>,
        addRow: <?= json_encode($gs('db_editor_add_row')) ?>
      };

      var READ_ONLY_COLS = { id: true, created_at: true };
      var ALLOWED_TYPES = { orders: true, parts: true };

      function getEditorAppKey() {
        var input = document.getElementById('gs_app_manifest');
        if (!input) { return ''; }
        try {
          var parsed = JSON.parse(input.value || '{}');
          var app = parsed && parsed.app && typeof parsed.app === 'object' ? parsed.app : {};
          return String(app.app_key || parsed.app_key || '').trim();
        } catch (e) {
          return '';
        }
      }

      function setDbStatus(msg, cls) {
        var el = document.getElementById('gs-db-editor-status');
        if (!el) { return; }
        el.textContent = String(msg || '');
        el.className = 'note' + (cls ? ' ' + cls : '');
        el.classList.toggle('is-hidden', !msg);
      }

      function renderDbSchema() {
        var body = document.getElementById('gs-db-schema-body');
        var wrap = document.getElementById('gs-db-schema-wrap');
        if (!body || !wrap) { return; }
        if (!dbSchema.length) { wrap.classList.add('is-hidden'); return; }
        wrap.classList.remove('is-hidden');
        body.innerHTML = '';
        dbSchema.forEach(function (col) {
          var tr = document.createElement('tr');
          var tdName = document.createElement('td');
          tdName.textContent = String(col.Field || col.field || '');
          var tdType = document.createElement('td');
          tdType.textContent = String(col.Type || col.type || '');
          var tdNull = document.createElement('td');
          tdNull.textContent = String(col.Null || col.nullable || '');
          tr.appendChild(tdName);
          tr.appendChild(tdType);
          tr.appendChild(tdNull);
          body.appendChild(tr);
        });
      }

      function renderDbRows() {
        var headRow = document.getElementById('gs-db-rows-head-row');
        var body = document.getElementById('gs-db-rows-body');
        var wrap = document.getElementById('gs-db-rows-wrap');
        if (!headRow || !body || !wrap) { return; }
        var editableCols = dbSchema
          .map(function (c) { return String(c.Field || c.field || ''); })
          .filter(function (name) { return !!name && !READ_ONLY_COLS[name]; });
        var allCols = dbSchema.map(function (c) { return String(c.Field || c.field || ''); });
        wrap.classList.remove('is-hidden');
        headRow.innerHTML = '';
        allCols.forEach(function (name) {
          var th = document.createElement('th');
          th.textContent = name;
          headRow.appendChild(th);
        });
        var thAct = document.createElement('th');
        headRow.appendChild(thAct);

        body.innerHTML = '';
        if (!dbRows.length) {
          var emptyTr = document.createElement('tr');
          var emptyTd = document.createElement('td');
          emptyTd.colSpan = allCols.length + 1;
          emptyTd.textContent = dbLabels.empty;
          emptyTr.appendChild(emptyTd);
          body.appendChild(emptyTr);
          return;
        }
        dbRows.forEach(function (row) {
          var tr = document.createElement('tr');
          var rowId = String(row.id || '');
          allCols.forEach(function (name) {
            var td = document.createElement('td');
            td.textContent = String(row[name] != null ? row[name] : '');
            tr.appendChild(td);
          });
          var tdAct = document.createElement('td');
          var editBtn = document.createElement('button');
          editBtn.type = 'button';
          editBtn.className = 'btn';
          editBtn.textContent = dbLabels.save;
          editBtn.addEventListener('click', function () {
            startEditRow(tr, row, editableCols, allCols, rowId);
          });
          var delBtn = document.createElement('button');
          delBtn.type = 'button';
          delBtn.className = 'btn danger';
          delBtn.textContent = dbLabels.del;
          delBtn.addEventListener('click', function () {
            if (!window.confirm(dbLabels.confirmDel)) { return; }
            deleteDbRow(rowId);
          });
          tdAct.appendChild(editBtn);
          tdAct.appendChild(delBtn);
          tr.appendChild(tdAct);
          body.appendChild(tr);
        });
      }

      function startEditRow(tr, row, editableCols, allCols, rowId) {
        var colIndex = 0;
        var inputs = {};
        Array.prototype.forEach.call(tr.querySelectorAll('td'), function (td, i) {
          var colName = allCols[i];
          if (!colName) { return; }
          if (READ_ONLY_COLS[colName]) { return; }
          td.innerHTML = '';
          var inp = document.createElement('input');
          inp.className = 'form-input';
          inp.value = String(row[colName] != null ? row[colName] : '');
          td.appendChild(inp);
          inputs[colName] = inp;
        });
        var lastTd = tr.lastElementChild;
        lastTd.innerHTML = '';
        var saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'btn btn-primary';
        saveBtn.textContent = dbLabels.save;
        saveBtn.addEventListener('click', function () {
          var data = { id: rowId };
          Object.keys(inputs).forEach(function (k) { data[k] = inputs[k].value; });
          saveDbRow(data, function () { loadDbData(); });
        });
        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn';
        cancelBtn.textContent = dbLabels.cancel;
        cancelBtn.addEventListener('click', function () { renderDbRows(); });
        lastTd.appendChild(saveBtn);
        lastTd.appendChild(cancelBtn);
      }

      function loadDbData() {
        var appKey = getEditorAppKey();
        var tableType = (document.getElementById('gs_db_table_type') || {}).value || 'orders';
        if (!appKey) {
          setDbStatus(dbLabels.noApp, 'warning');
          return;
        }
        setDbStatus(dbLabels.loading, '');
        dbCurrentAppKey = appKey;
        dbCurrentTableType = tableType;
        fetch('/apps/studio/db-rows?app_key=' + encodeURIComponent(appKey) + '&table_type=' + encodeURIComponent(tableType), {
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data || data.error) {
            setDbStatus(String((data && data.error) || dbLabels.loadFailed), 'warning');
            return;
          }
          dbSchema = Array.isArray(data.schema) ? data.schema : [];
          dbRows = Array.isArray(data.rows) ? data.rows : [];
          setDbStatus('', '');
          renderDbSchema();
          renderDbRows();
        })
        .catch(function () {
          setDbStatus(dbLabels.loadFailed, 'warning');
        });
      }

      function saveDbRow(rowData, onDone) {
        var csrfInput = document.getElementById('gs_db_editor_csrf');
        var csrf = csrfInput ? csrfInput.value : '';
        var body = 'csrf=' + encodeURIComponent(csrf)
          + '&app_key=' + encodeURIComponent(dbCurrentAppKey)
          + '&table_type=' + encodeURIComponent(dbCurrentTableType)
          + '&row_json=' + encodeURIComponent(JSON.stringify(rowData));
        fetch('/apps/studio/db-rows/save', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
          body: body
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.ok) {
            onDone && onDone();
          } else {
            setDbStatus(String((data && data.error) || dbLabels.loadFailed), 'warning');
          }
        })
        .catch(function () { setDbStatus(dbLabels.loadFailed, 'warning'); });
      }

      function deleteDbRow(rowId) {
        var csrfInput = document.getElementById('gs_db_editor_csrf');
        var csrf = csrfInput ? csrfInput.value : '';
        var body = 'csrf=' + encodeURIComponent(csrf)
          + '&app_key=' + encodeURIComponent(dbCurrentAppKey)
          + '&table_type=' + encodeURIComponent(dbCurrentTableType)
          + '&row_id=' + encodeURIComponent(rowId)
          + '&action=delete';
        fetch('/apps/studio/db-rows/save', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
          body: body
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.ok) {
            loadDbData();
          } else {
            setDbStatus(String((data && data.error) || dbLabels.loadFailed), 'warning');
          }
        })
        .catch(function () { setDbStatus(dbLabels.loadFailed, 'warning'); });
      }

      window.gsDbEditorActivate = function () {
        if (dbEditorLoaded) { return; }
        dbEditorLoaded = true;
        loadDbData();
      };

      var refreshBtn = document.getElementById('gs-db-refresh');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
          dbEditorLoaded = false;
          loadDbData();
          dbEditorLoaded = true;
        });
      }

      var tableTypeSelect = document.getElementById('gs_db_table_type');
      if (tableTypeSelect) {
        tableTypeSelect.addEventListener('change', function () {
          dbEditorLoaded = false;
          window.gsDbEditorActivate && window.gsDbEditorActivate();
        });
      }

      var addRowBtn = document.getElementById('gs-db-add-row');
      if (addRowBtn) {
        addRowBtn.addEventListener('click', function () {
          var editableCols = dbSchema
            .map(function (c) { return String(c.Field || c.field || ''); })
            .filter(function (name) { return !!name && !READ_ONLY_COLS[name]; });
          if (!editableCols.length) { return; }
          var body = document.getElementById('gs-db-rows-body');
          if (!body) { return; }
          var allCols = dbSchema.map(function (c) { return String(c.Field || c.field || ''); });
          var tr = document.createElement('tr');
          var inputs = {};
          allCols.forEach(function (name) {
            var td = document.createElement('td');
            if (!READ_ONLY_COLS[name]) {
              var inp = document.createElement('input');
              inp.className = 'form-input';
              inp.value = '';
              inp.placeholder = name;
              td.appendChild(inp);
              inputs[name] = inp;
            }
            tr.appendChild(td);
          });
          var tdAct = document.createElement('td');
          var saveBtn = document.createElement('button');
          saveBtn.type = 'button';
          saveBtn.className = 'btn btn-primary';
          saveBtn.textContent = dbLabels.save;
          saveBtn.addEventListener('click', function () {
            var data = {};
            Object.keys(inputs).forEach(function (k) { data[k] = inputs[k].value; });
            saveDbRow(data, function () { dbEditorLoaded = false; loadDbData(); dbEditorLoaded = true; });
          });
          var cancelBtn = document.createElement('button');
          cancelBtn.type = 'button';
          cancelBtn.className = 'btn';
          cancelBtn.textContent = dbLabels.cancel;
          cancelBtn.addEventListener('click', function () { tr.remove(); });
          tdAct.appendChild(saveBtn);
          tdAct.appendChild(cancelBtn);
          tr.appendChild(tdAct);
          body.insertBefore(tr, body.firstChild);
        });
      }
    }());
    // --- End Direct DB Editor ---
    function renderFields() {
      if (!fieldsBody) return;
      fieldsBody.innerHTML = '';

      structuredState.fields.forEach(function (field, index) {
        var tr = document.createElement('tr');
        tr.setAttribute('data-field-key', field.key);

        var tdName = document.createElement('td');
        var inputName = document.createElement('input');
        inputName.className = 'form-input';
        inputName.value = field.key;
        inputName.addEventListener('input', function () {
          structuredState.fields[index].key = toKey(inputName.value);
          tr.setAttribute('data-field-key', structuredState.fields[index].key);
          renderColumns();
          updateHiddenStates();
          buildInternalJson();
        });
        tdName.appendChild(inputName);

        var tdType = document.createElement('td');
        var typeSel = document.createElement('select');
        typeSel.className = 'form-input';
        ['string', 'int', 'float', 'bool', 'date', 'select'].forEach(function (opt) {
          var option = document.createElement('option');
          option.value = opt;
          option.textContent = opt;
          if (opt === field.type) option.selected = true;
          typeSel.appendChild(option);
        });
        typeSel.addEventListener('change', function () {
          structuredState.fields[index].type = typeSel.value;
          buildInternalJson();
        });
        tdType.appendChild(typeSel);

        var tdRequired = document.createElement('td');
        var reqCb = document.createElement('input');
        reqCb.type = 'checkbox';
        reqCb.checked = !!field.required;
        reqCb.addEventListener('change', function () {
          structuredState.fields[index].required = reqCb.checked;
          buildInternalJson();
        });
        tdRequired.appendChild(reqCb);

        var tdDefault = document.createElement('td');
        var inputDefault = document.createElement('input');
        inputDefault.className = 'form-input';
        inputDefault.value = String(field.default || '');
        inputDefault.addEventListener('input', function () {
          structuredState.fields[index].default = inputDefault.value;
          buildInternalJson();
        });
        tdDefault.appendChild(inputDefault);

        var tdAction = document.createElement('td');
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn danger';
        removeBtn.textContent = removeFieldLabel;
        removeBtn.addEventListener('click', function () {
          var removed = structuredState.fields[index] && structuredState.fields[index].key;
          structuredState.fields.splice(index, 1);
          if (removed) {
            structuredState.columns = structuredState.columns.filter(function (key) { return key !== removed; });
          }
          renderFields();
          renderColumns();
          updateHiddenStates();
          buildInternalJson();
        });
        tdAction.appendChild(removeBtn);

        tr.appendChild(tdName);
        tr.appendChild(tdType);
        tr.appendChild(tdRequired);
        tr.appendChild(tdDefault);
        tr.appendChild(tdAction);
        fieldsBody.appendChild(tr);
      });
    }

    function updateHiddenStates() {
      if (fieldsStateInput) {
        fieldsStateInput.value = JSON.stringify(structuredState.fields);
      }
      if (viewColumnsStateInput) {
        viewColumnsStateInput.value = JSON.stringify(structuredState.columns);
      }
      if (layoutStateInput) {
        var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
        layoutStateInput.value = JSON.stringify(items);
      }
      if (layoutRelationsStateInput) {
        var relations = builderState.layout && Array.isArray(builderState.layout.relations) ? builderState.layout.relations : [];
        layoutRelationsStateInput.value = JSON.stringify(relations);
      }
      if (previewModeInput) {
        previewModeInput.value = builderState.previewMode ? '1' : '0';
      }
    }

    var studioStateRevision = 0;
    function commitStudioState(nextState, options) {
      var commitOptions = options && typeof options === 'object' ? options : {};
      var previousState = window.gsStudioState && typeof window.gsStudioState === 'object'
        ? window.gsStudioState
        : {};
      var modeValue = studioModeInput ? String(studioModeInput.value || 'create_new') : 'create_new';
      var contentType = typeof window.getLoadedBundleContentType === 'function'
        ? window.getLoadedBundleContentType()
        : 'unknown';
      window.gsStudioState = Object.assign({}, previousState, nextState || {}, {
        revision: ++studioStateRevision,
        mode: modeValue === 'edit_existing' ? 'edit_existing' : 'create_new',
        loaded: typeof window.isLoadedContentMode === 'function' && window.isLoadedContentMode(),
        content_type: contentType,
        updated_at: new Date().toISOString()
      });

      var committed = window.gsStudioState;
      updateHiddenStates();
      renderChangeSummary(committed.diff || committed.change_intelligence || {});
      renderMigrationPlan(committed.migration_plan || []);
      renderImpactAnalysis(committed.impact_analysis || []);
      renderSimulationPreview(committed.simulation_preview || {});
      if (!commitOptions.skipVisualBuilder) {
        refreshVisualBuilder();
      }
      updateContentAwareEditorVisibility();
      if (typeof renderContentOutline === 'function') {
        renderContentOutline();
      }
      if (typeof window.renderCreateFlowGuide === 'function') {
        window.renderCreateFlowGuide();
      }
      document.dispatchEvent(new window.CustomEvent('studio-state-committed', { detail: committed }));
    }
    window.gsCommitStudioState = commitStudioState;

    function buildInternalJson() {
      saveActiveMultiViewSnapshot();
      var hasUpgradeBaselineForBuild = false;
      if (previousBundleInput) {
        var previousRaw = String(previousBundleInput.value || '').trim();
        if (previousRaw !== '' && previousRaw !== '{}' && previousRaw !== 'null') {
          try {
            var previousParsed = JSON.parse(previousRaw);
            hasUpgradeBaselineForBuild = !!(previousParsed && typeof previousParsed === 'object' && Object.keys(previousParsed).length > 0);
          } catch (error) {
            hasUpgradeBaselineForBuild = true;
          }
        }
      }
      if (
        previousBundleInput
        && hasUpgradeBaselineForBuild
        && studioModeInput
        && String(studioModeInput.value || '') !== 'edit_existing'
      ) {
        if (typeof window.gsSetStudioMode === 'function') {
          window.gsSetStudioMode('edit_existing', { preserveUpgradeBaseline: true });
        } else {
          Array.prototype.forEach.call(document.querySelectorAll('input[name="studio_mode"]'), function (input) {
            if (input) {
              input.value = 'edit_existing';
            }
          });
        }
      }
      var appManifest = safeParseJson(appManifestInput.value, {});
      var appData = appManifest.app || {};
      var appKey = toKey(appData.app_key || 'generated_app') || 'generated_app';
      var moduleKey = toKey(moduleKeyInput.value || 'generated_module') || 'generated_module';
      var appSlug = toSlug(appKey);

      var routePath = routePathInput.value.trim();
      if (!routePath) {
        routePath = '/apps/' + appSlug + '/' + toSlug(moduleKey);
      }

      var fields = structuredState.fields.filter(function (field) {
        return field.key !== '';
      }).map(function (field) {
        return {
          key: field.key,
          label: field.key.replace(/_/g, ' '),
          type: field.type,
          required: !!field.required,
          default: String(field.default || '')
        };
      });
      var columns = structuredState.columns.filter(function (key) {
        return fields.some(function (field) { return field.key === key; });
      });
      if (columns.length === 0) {
        columns = fields.map(function (field) { return field.key; });
      }
      var viewKindValue = currentViewKindValue();
      var directDbAllowed = viewKindValue.toLowerCase() === 'table' && currentTableEditModeValue() === 'direct_db';

      var moduleManifest = {
        schema_version: 'studio.module-manifest.v1',
        status: 'draft',
        module: {
          app_key: appKey,
          module_key: moduleKey,
          display_name: moduleDisplayInput.value.trim() || moduleKey,
          description: moduleDescriptionInput.value.trim() || ('studio.' + moduleKey + '.description'),
          module_type: moduleTypeInput.value || 'crud',
          target_maturity_level: 'L1',
          declared_capabilities: [],
          route_base: routePath
        },
        fields: fields
      };

      var viewDefinition = {
        schema_version: 'studio.view-manifest.v1',
        status: 'draft',
        view: {
          app_key: appKey,
          module_key: moduleKey,
          view_key: 'index',
          view_kind: currentViewKindValue(),
          route_path: routePath,
          surface: 'admin',
          wrapper: 'admin',
          required_role: 'platform_admin',
          title_key: 'studio.' + moduleKey + '.index.title',
          description_key: 'studio.' + moduleKey + '.index.description',
          fields: columns
        },
        layout: {
          kind: viewKindValue,
          type: 'grid',
          columns: 12,
          rows: 'auto',
          items: builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [],
          structure: layoutRowsFromItems(builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : []),
          relations: builderState.layout && Array.isArray(builderState.layout.relations) ? builderState.layout.relations : [],
          preview_mode: !!builderState.previewMode,
          uses_shared_tokens: true,
          local_style_system: false,
          compact: !!layoutCompactInput.checked
        },
        component_registry: {
          version: 'visual-builder.v1',
          components: Object.keys(componentRegistry).map(function (key) {
            var definition = componentRegistry[key];
            return {
              key: key,
              required_props: Array.isArray(definition.requiredProps) ? definition.requiredProps.slice() : [],
              default_config: definition.defaultConfig()
            };
          })
        },
        component_bindings: (builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : []).map(function (item) {
          return {
            item_id: item.id,
            component: item.component,
            data_binding: item.data_binding || defaultBindingForComponent(item.component)
          };
        }),
        data_contract: {
          adapter_class: '',
          allowed_filters: [],
          required_fields: columns,
          empty_state_key: 'studio.' + moduleKey + '.index.empty'
        },
        security: {
          requires_auth: true,
          required_role: 'platform_admin',
          allowed_roles: ['platform_admin'],
          csrf_for_mutations: true,
          direct_db_mutation_allowed: directDbAllowed
        },
        fields: fields,
        audit: {
          draft_id: '',
          created_by: '',
          created_at: ''
        },
        studio_views: {
          active_view_id: String(multiViewState.activeViewId || ''),
          views: (Array.isArray(multiViewState.views) ? multiViewState.views : []).map(function (entry) {
            return {
              id: String(entry.id || ''),
              name: String(entry.name || ''),
              view_kind: String(entry.view_kind || 'table'),
              layout: cloneMultiViewLayout(entry.layout),
              links: entry.links && typeof entry.links === 'object' ? Object.assign({}, entry.links) : {}
            };
          })
        }
      };

      var navLabel = navLabelInput.value.trim() || moduleDisplayInput.value.trim() || moduleKey;
      var navTarget = navTargetInput && String(navTargetInput.value || '').trim() !== ''
        ? String(navTargetInput.value || '').trim()
        : routePath;
      var navOrder = Math.max(0, parseInt(String(navOrderInput && navOrderInput.value || '10'), 10) || 10);
      var navVisible = !!(navVisibleInput && navVisibleInput.checked);
      var navigationDefinition = {
        schema_version: 'studio.navigation-manifest.v1',
        status: 'draft',
        navigation: {
          scope: 'admin',
          owner_app: appKey,
          key: toKey(appKey + '_' + moduleKey),
          label_key: 'studio.' + moduleKey + '.nav',
          label: navLabel,
          url: navTarget,
          icon: String(navIconInput && navIconInput.value || '').trim(),
          section: navSectionInput.value || 'Apps',
          group: navGroupInput.value.trim() || 'Apps',
          order: navOrder,
          priority: navOrder * 10,
          visible_if: navVisible ? 'role_platform_admin_or_sysadmin' : 'never'
        },
        active_patterns: {
          exact: [navTarget],
          prefix: ['/apps/' + appSlug]
        },
        governance: {
          wrapper_confinement_valid: true,
          no_cross_layer_escape: true,
          publish_locked: true
        }
      };

      moduleManifestInput.value = JSON.stringify(moduleManifest, null, 2);
      viewDefinitionInput.value = JSON.stringify(viewDefinition, null, 2);
      navigationDefinitionInput.value = JSON.stringify(navigationDefinition, null, 2);

      var currentBundle = {
        app_manifest: safeParseJson(appManifestInput.value, {}),
        module_manifest: moduleManifest,
        view_definition: viewDefinition,
        navigation_definition: navigationDefinition
      };
      if (currentBundleInput) {
        currentBundleInput.value = JSON.stringify(currentBundle);
      }
      persistStudioDraftLocal();

      var previousBundle = safeParseJson(previousBundleInput ? previousBundleInput.value : '', currentBundle);
      var diff = computeStructuredDiff(previousBundle, currentBundle);
      var dependencyGraph = buildDependencyGraph(currentBundle);
      var impactAnalysis = computeImpact(diff, dependencyGraph);
      var simulationPreview = simulateFutureState(previousBundle, currentBundle, diff);
      commitStudioState({
        previous_bundle: previousBundle,
        current_bundle: currentBundle,
        diff: diff,
        change_intelligence: diff,
        dependency_graph: dependencyGraph,
        impact_analysis: impactAnalysis,
        simulation_preview: simulationPreview,
        migration_plan: buildMigrationPlan(diff)
      });
      lastContentAwareViewKind = currentViewKindValue();
    }

    function initializeStructuredEditor() {
      restoreStudioDraftLocal();
      readStructuredFromJson();
      ensureMultiViewStateInitialized();
      renderFields();
      renderColumns();
      syncContentAwareEditorVisibility();
      buildInternalJson();
    }

    refreshStructuredEditor = function () {
      readStructuredFromJson();
      ensureMultiViewStateInitialized();
      renderFields();
      renderColumns();
      syncContentAwareEditorVisibility();
      buildInternalJson();
    };

    [moduleKeyInput, moduleDisplayInput, moduleTypeInput, moduleDescriptionInput, routePathInput, createIntentInput, viewKindInput, layoutCompactInput, navSectionInput, navGroupInput, navLabelInput, navTargetInput, navIconInput, navOrderInput, navVisibleInput]
      .forEach(function (control) {
        if (!control) return;
        var eventName = (control.type === 'checkbox' || control.tagName === 'SELECT') ? 'change' : 'input';
        control.addEventListener(eventName, function () {
          buildInternalJson();
        });
      });

    Array.prototype.forEach.call(document.querySelectorAll('#gs_se_create_intent'), function (intentNode) {
      intentNode.addEventListener('change', function () {
        var intentValue = String(intentNode.value || 'create_view');
        syncDuplicateSelectValues('#gs_se_create_intent', intentValue, intentNode);
        applyIntentDrivenDefaults();
        syncContentAwareEditorVisibility();
        buildInternalJson();
      });
    });

    Array.prototype.forEach.call(document.querySelectorAll('#gs_se_view_kind'), function (viewKindNode) {
      viewKindNode.addEventListener('change', function () {
        syncDuplicateSelectValues('#gs_se_view_kind', viewKindNode.value, viewKindNode);
        syncContentAwareEditorVisibility();
        buildInternalJson();
      });
    });

    Array.prototype.forEach.call(document.querySelectorAll('#gs_se_table_edit_mode'), function (modeNode) {
      modeNode.addEventListener('change', function () {
        syncDuplicateSelectValues('#gs_se_table_edit_mode', modeNode.value, modeNode);
        updateContentAwareEditorVisibility();
        buildInternalJson();
      });
    });

    document.addEventListener('change', function (event) {
      if (!event || !event.target) {
        return;
      }
      if (event.target.id === 'gs_se_view_kind') {
        syncDuplicateSelectValues('#gs_se_view_kind', event.target.value, event.target);
        syncContentAwareEditorVisibility();
        buildInternalJson();
        return;
      }
      if (event.target.id === 'gs_se_create_intent') {
        var createIntentValue = String(event.target.value || 'create_view');
        syncDuplicateSelectValues('#gs_se_create_intent', createIntentValue, event.target);
        applyIntentDrivenDefaults();
        syncContentAwareEditorVisibility();
        buildInternalJson();
        return;
      }
      if (event.target.id === 'gs_se_table_edit_mode') {
        syncDuplicateSelectValues('#gs_se_table_edit_mode', event.target.value, event.target);
        updateContentAwareEditorVisibility();
        buildInternalJson();
      }
    });

    window.setInterval(function () {
      syncContentAwareEditorVisibility();
    }, 1000);

    if (addFieldButton) {
      addFieldButton.addEventListener('click', function () {
        structuredState.fields.push({ key: '', label: '', type: 'string', required: false, default: '', options: [] });
        renderFields();
        renderColumns();
        buildInternalJson();
      });
    }

    if (visualBuilderPreviewToggle) {
      visualBuilderPreviewToggle.addEventListener('change', function () {
        builderState.previewMode = !!visualBuilderPreviewToggle.checked;
        buildInternalJson();
      });
    }

    if (addRelationButton) {
      addRelationButton.addEventListener('click', function () {
        var items = builderState.layout && Array.isArray(builderState.layout.items) ? builderState.layout.items : [];
        if (items.length < 2) return;
        var relationType = 'affects_table';
        var sourceComponent = String(items[0].component || '');
        var targetComponent = String(items[1].component || '');
        if (sourceComponent === 'filter' && targetComponent === 'table') relationType = 'filter_to_table';
        if (sourceComponent === 'filter' && targetComponent === 'kpi_card') relationType = 'filter_to_kpi';
        if (sourceComponent === 'form' && targetComponent === 'table') relationType = 'form_refresh_table';
        if (sourceComponent === 'table' && targetComponent === 'kpi_card') relationType = 'table_to_kpi_derived';
        var relation = {
          source_id: items[0].id,
          target_id: items[1].id,
          type: relationType
        };
        builderState.layout.relations = builderState.layout.relations || [];
        builderState.layout.relations.push(relation);
        buildInternalJson();
      });
    }

    if (exportAppButton) {
      exportAppButton.addEventListener('click', function () {
        exportAppBundle();
      });
    }

    if (importAppButton && importAppInput) {
      importAppButton.addEventListener('click', function () {
        importAppInput.value = '';
        importAppInput.click();
      });
      importAppInput.addEventListener('change', function () {
        var file = importAppInput.files && importAppInput.files[0] ? importAppInput.files[0] : null;
        if (!file) return;
        importAppBundleFromFile(file);
      });
    }

    if (layoutCanvas) {
      layoutCanvas.setAttribute('data-phase', 'static-grid');
    }

    form.addEventListener('submit', function (event) {
      buildInternalJson();
      if (Object.keys(builderState.bindingErrors || {}).length > 0) {
        event.preventDefault();
        setBuilderWarning('binding_invalid');
        refreshVisualBuilder();
      }
    });

    initializeStructuredEditor();
  }

  var loadButtons = document.querySelectorAll('[data-load-module]');
  if ((loadButtons.length === 0 && document.querySelectorAll('[data-load-library-node]').length === 0) || typeof window.fetch !== 'function') {
    return;
  }

  var studioModeInput = document.getElementById('gs_studio_mode');
  var studioModeChip = document.getElementById('gs-studio-mode-chip');
  var loadingLabel = <?= json_encode($gs('library_loading')) ?>;
  var loadFailedLabel = <?= json_encode($gs('library_load_failed')) ?>;
  var loadFailedWithReasonLabel = <?= json_encode($gs('library_load_failed_with_reason')) ?>;
  var loadUnsupportedKindLabel = <?= json_encode($gs('library_load_unsupported_kind')) ?>;
  var loadInvalidResponseLabel = <?= json_encode($gs('library_load_invalid_response')) ?>;
  var partialImportLabel = <?= json_encode($gs('library_import_partial')) ?>;
  var importCompleteLabel = <?= json_encode($gs('library_import_complete')) ?>;

  function applyMessageTemplate(template, params) {
    return String(template || '').replace(/\{([a-z_]+)\}/gi, function (_match, key) {
      return Object.prototype.hasOwnProperty.call(params, key) ? String(params[key] || '') : '';
    });
  }

  function normalizeArtifactKind(kind) {
    var normalized = String(kind || '').toLowerCase();
    if (normalized === 'route_file') {
      return 'route';
    }
    return normalized;
  }

  function classifyLoadSource(payload) {
    if (!payload || typeof payload !== 'object') {
      return 'import';
    }
    if (payload.selected_id) {
      return 'library';
    }
    if (payload.partial_import || payload.bundle) {
      return 'import';
    }
    return 'library';
  }

  function parseLibraryNodeId(nodeId) {
    var raw = String(nodeId || '').trim();
    if (raw === '') {
      return { artifactKind: 'unknown', appKey: '', moduleKey: '' };
    }
    var parts = raw.split(':');
    var artifactKind = normalizeArtifactKind(parts[0] || 'unknown');
    var appKey = '';
    var moduleKey = '';

    if (parts.length >= 3 && parts[1] === 'apps') {
      appKey = String(parts[2] || '').trim();
    }
    if (artifactKind === 'module' && parts.length >= 4) {
      moduleKey = String(parts[3] || '').trim();
    }

    return {
      artifactKind: artifactKind,
      appKey: appKey,
      moduleKey: moduleKey
    };
  }

  function deriveLoadedArtifactContext(bundle, payload, fallbackNodeId, forcedArtifactKind) {
    var sourcePayload = payload && payload.selected_source && typeof payload.selected_source === 'object'
      ? payload.selected_source
      : {};
    var nodeId = String(fallbackNodeId || (payload && payload.selected_id ? payload.selected_id : sourcePayload.node_id) || '').trim();
    var parsedNode = parseLibraryNodeId(nodeId);
    var forcedKind = normalizeArtifactKind(forcedArtifactKind || 'unknown');
    var payloadArtifactKind = normalizeArtifactKind(payload && payload.artifact_kind ? payload.artifact_kind : sourcePayload.artifact_kind || 'unknown');
    var artifactKind = forcedKind !== 'unknown'
      ? forcedKind
      : (parsedNode.artifactKind !== 'unknown' ? parsedNode.artifactKind : payloadArtifactKind);
    var resolveContentType = typeof window.gsResolveContentTypeFromArtifactKind === 'function'
      ? window.gsResolveContentTypeFromArtifactKind
      : function (_kind, fallbackBundle) { return 'unknown'; };
    var inferContentType = typeof window.gsInferLoadedContentTypeFromBundle === 'function'
      ? window.gsInferLoadedContentTypeFromBundle
      : function (_bundle) { return 'unknown'; };
    var contentType = resolveContentType(artifactKind, bundle || {});
    if (contentType === 'unknown') {
      contentType = inferContentType(bundle || {});
    }

    var appManifest = bundle && bundle.app_manifest && typeof bundle.app_manifest === 'object' ? bundle.app_manifest : {};
    var appInner = appManifest.app && typeof appManifest.app === 'object' ? appManifest.app : appManifest;
    var moduleManifest = bundle && bundle.module_manifest && typeof bundle.module_manifest === 'object' ? bundle.module_manifest : {};
    var moduleInner = moduleManifest.module && typeof moduleManifest.module === 'object' ? moduleManifest.module : moduleManifest;
    var sourceType = classifyLoadSource(payload);

    return {
      artifactKind: artifactKind,
      contentType: contentType,
      sourceType: sourceType,
      ownerAppKey: String(sourcePayload.app_key || parsedNode.appKey || appInner.app_key || appManifest.app_key || '').trim(),
      ownerModuleKey: String(sourcePayload.module_key || parsedNode.moduleKey || moduleInner.module_key || moduleManifest.module_key || '').trim(),
      sourcePath: String(sourcePayload.source_path || sourcePayload.path || sourcePayload.file_path || '').trim(),
      nodeId: nodeId
    };
  }

  function resolveLoadFailureMessage(result, fallbackCode) {
    var payload = result && result.payload && typeof result.payload === 'object' ? result.payload : null;
    if (!payload) {
      return loadInvalidResponseLabel || loadFailedLabel;
    }

    var code = String(payload.code || fallbackCode || 'load_failed');
    var artifactKind = normalizeArtifactKind(payload.artifact_kind || 'unknown');
    var unsupportedKinds = ['route', 'nav'];
    if (code === 'unsupported_library_node' || unsupportedKinds.indexOf(artifactKind) !== -1) {
      return applyMessageTemplate(loadUnsupportedKindLabel || loadFailedLabel, { kind: artifactKind || 'unknown' });
    }

    var reason = String(payload.message || code || fallbackCode || 'load_failed');
    return applyMessageTemplate(loadFailedWithReasonLabel || loadFailedLabel, { reason: reason });
  }

  function normalizeComponentMode(mode) {
    var normalized = String(mode || '').toLowerCase();
    return normalized === 'table' || normalized === 'form' ? normalized : '';
  }

  function projectBundleToComponentMode(bundle, mode) {
    var componentMode = normalizeComponentMode(mode);
    if (!bundle || typeof bundle !== 'object' || componentMode === '') {
      return bundle;
    }

    var cloned = null;
    try {
      cloned = JSON.parse(JSON.stringify(bundle));
    } catch (error) {
      return bundle;
    }

    cloned.view_definition = cloned.view_definition && typeof cloned.view_definition === 'object' ? cloned.view_definition : {};
    cloned.view_definition.view = cloned.view_definition.view && typeof cloned.view_definition.view === 'object' ? cloned.view_definition.view : {};
    var layout = cloned.view_definition.layout && typeof cloned.view_definition.layout === 'object' ? cloned.view_definition.layout : {};
    var items = Array.isArray(layout.items) ? layout.items.filter(function (item) { return item && typeof item === 'object'; }) : [];

    var filteredItems = items
      .filter(function (item) {
        return String(item.component || '') === componentMode;
      })
      .map(function (item, index) {
        var id = toKey(item.id || (componentMode + '_' + String(index + 1))) || (componentMode + '_' + String(index + 1));
        return {
          id: id,
          component: componentMode,
          group: toKey(item.group || 'default') || 'default',
          x: 0,
          y: Math.max(0, index * (componentMode === 'table' ? 6 : 4)),
          w: 12,
          h: Math.max(2, parseInt(item.h, 10) || (componentMode === 'table' ? 6 : 4)),
          props: normalizeComponentProps(componentMode, item.props || {}),
          data_binding: normalizeDataBinding(componentMode, item.data_binding)
        };
      });

    if (filteredItems.length === 0) {
      filteredItems = [{
        id: componentMode + '_1',
        component: componentMode,
        group: 'default',
        x: 0,
        y: 0,
        w: 12,
        h: componentMode === 'table' ? 6 : 4,
        props: normalizeComponentProps(componentMode, {}),
        data_binding: normalizeDataBinding(componentMode, '')
      }];
    }

    var itemIdMap = {};
    filteredItems.forEach(function (item) {
      itemIdMap[item.id] = true;
    });

    var relations = Array.isArray(layout.relations)
      ? layout.relations.filter(function (relation) {
          var sourceId = String(relation && relation.source_id || '');
          var targetId = String(relation && relation.target_id || '');
          return !!(itemIdMap[sourceId] && itemIdMap[targetId]);
        })
      : [];

    cloned.view_definition.layout = {
      kind: componentMode,
      type: 'grid',
      columns: 12,
      rows: 'auto',
      items: filteredItems,
      relations: relations,
      uses_shared_tokens: true,
      local_style_system: false
    };

    cloned.view_definition.view.view_kind = componentMode;
    cloned.view_definition.component_bindings = filteredItems.map(function (item) {
      return {
        item_id: item.id,
        component: item.component,
        data_binding: item.data_binding
      };
    });

    return cloned;
  }

  function setButtonLoading(button, loading) {
    if (!button) return;
    if (loading) {
      button.setAttribute('disabled', 'disabled');
      button.setAttribute('data-label', button.textContent || '');
      button.textContent = loadingLabel;
      return;
    }
    button.removeAttribute('disabled');
    var label = button.getAttribute('data-label') || '';
    if (label !== '') {
      button.textContent = label;
    }
  }

  function loadBundleIntoEditor(bundle, payload, fallbackNodeId, forcedArtifactKind) {
    if (!bundle || typeof bundle !== 'object') {
      throw new Error('invalid_bundle');
    }
    var loadedContext = deriveLoadedArtifactContext(bundle, payload && typeof payload === 'object' ? payload : {}, fallbackNodeId || '', forcedArtifactKind || '');
    if (typeof window.gsWriteLoadedArtifactContext === 'function') {
      window.gsWriteLoadedArtifactContext(loadedContext);
    } else {
      window.gsLoadedArtifactContext = loadedContext;
    }
    if (appManifestInput) appManifestInput.value = JSON.stringify(bundle.app_manifest || {}, null, 2);
    if (moduleManifestInput) moduleManifestInput.value = JSON.stringify(bundle.module_manifest || {}, null, 2);
    if (viewDefinitionInput) viewDefinitionInput.value = JSON.stringify(bundle.view_definition || {}, null, 2);
    if (navigationDefinitionInput) navigationDefinitionInput.value = JSON.stringify(bundle.navigation_definition || {}, null, 2);
    if (previousBundleInput) {
      previousBundleInput.value = JSON.stringify(bundle || {});
    }
    if (typeof setStudioMode === 'function') {
      setStudioMode('edit_existing', { preserveUpgradeBaseline: true });
    } else {
      if (studioModeInput) studioModeInput.value = 'edit_existing';
      if (studioModeChip) studioModeChip.textContent = studioModeEditLabel;
      document.dispatchEvent(new window.CustomEvent('studio-mode-changed', { detail: { mode: 'edit_existing' } }));
    }
    if (typeof refreshStructuredEditor === 'function') {
      refreshStructuredEditor();
    }
    if (moduleKeyInput) {
      moduleKeyInput.scrollIntoView({ behavior: 'smooth', block: 'start' });
      moduleKeyInput.focus();
    }
    if (typeof window.renderContentOutline === 'function') {
      window.renderContentOutline();
    }
    if (typeof window.renderCreateFlowGuide === 'function') {
      window.renderCreateFlowGuide();
    }
  }

  function enforceComponentModeInEditor(mode) {
    var componentMode = normalizeComponentMode(mode);
    if (componentMode === '' || !appManifestInput || !moduleManifestInput || !viewDefinitionInput || !navigationDefinitionInput) {
      return;
    }

    var currentBundle = {
      app_manifest: safeParseJson(appManifestInput.value, {}),
      module_manifest: safeParseJson(moduleManifestInput.value, {}),
      view_definition: safeParseJson(viewDefinitionInput.value, {}),
      navigation_definition: safeParseJson(navigationDefinitionInput.value, {})
    };

    var nextBundle = projectBundleToComponentMode(currentBundle, componentMode);
    if (!nextBundle || typeof nextBundle !== 'object') {
      return;
    }

    appManifestInput.value = JSON.stringify(nextBundle.app_manifest || {}, null, 2);
    moduleManifestInput.value = JSON.stringify(nextBundle.module_manifest || {}, null, 2);
    viewDefinitionInput.value = JSON.stringify(nextBundle.view_definition || {}, null, 2);
    navigationDefinitionInput.value = JSON.stringify(nextBundle.navigation_definition || {}, null, 2);

    if (previousBundleInput && !(typeof window.isLoadedContentMode === 'function' && window.isLoadedContentMode())) {
      previousBundleInput.value = '{}';
    }
    if (typeof refreshStructuredEditor === 'function') {
      refreshStructuredEditor();
    }
  }

  function enforceComponentModeOnBuilder(mode) {
    var componentMode = normalizeComponentMode(mode);
    if (componentMode === '') {
      return;
    }
    if (!builderState || !builderState.layout || !Array.isArray(builderState.layout.items)) {
      return;
    }

    var filteredItems = builderState.layout.items
      .filter(function (item) {
        return item && typeof item === 'object' && String(item.component || '') === componentMode;
      })
      .map(function (item, index) {
        return {
          id: toKey(item.id || (componentMode + '_' + String(index + 1))) || (componentMode + '_' + String(index + 1)),
          x: 0,
          y: Math.max(0, index * (componentMode === 'table' ? 6 : 4)),
          w: 12,
          h: Math.max(2, parseInt(item.h, 10) || (componentMode === 'table' ? 6 : 4)),
          group: toKey(item.group || 'default') || 'default',
          component: componentMode,
          props: normalizeComponentProps(componentMode, item.props || {}),
          data_binding: normalizeDataBinding(componentMode, item.data_binding || '')
        };
      });

    if (filteredItems.length === 0) {
      filteredItems = [buildDefaultItem(componentMode, componentMode + '_1', 0, 0, 12, componentMode === 'table' ? 6 : 4)];
    }

    builderState.layout.items = filteredItems;
    builderState.layout.relations = [];
    builderState.viewKind = componentMode;
    builderState.selectedItemId = filteredItems[0] ? filteredItems[0].id : '';

    if (viewKindInput) {
      viewKindInput.value = componentMode;
    }
    if (moduleTypeInput && componentMode === 'table') {
      moduleTypeInput.value = 'crud';
    }
    if (moduleTypeInput && componentMode === 'form') {
      moduleTypeInput.value = 'crud';
    }

    buildInternalJson();
    refreshVisualBuilder();
  }

  loadButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      var appKey = button.getAttribute('data-app-key') || '';
      var moduleKey = button.getAttribute('data-module-key') || '';
      if (appKey === '' || moduleKey === '') {
        return;
      }

      setButtonLoading(button, true);
      var endpoint = '/apps/studio/import-view?format=json&app_key=' + encodeURIComponent(appKey) + '&module_key=' + encodeURIComponent(moduleKey);
      window.fetch(endpoint, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
      .then(function (response) {
        return response.json().then(function (payload) {
          return { ok: response.ok, payload: payload };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.payload || result.payload.ok !== true) {
          throw new Error(resolveLoadFailureMessage(result, 'module_load_failed'));
        }
        loadBundleIntoEditor(result.payload.bundle || {}, result.payload || {});
        if (result.payload && result.payload.partial_import) {
          window.alert(partialImportLabel);
        } else {
          window.alert(importCompleteLabel);
        }
      })
      .catch(function (error) {
        var message = error && error.message ? error.message : loadFailedLabel;
        window.alert(message);
      })
      .finally(function () {
        setButtonLoading(button, false);
      });
    });
  });

  document.addEventListener('click', function (event) {
    var button = event.target && event.target.closest ? event.target.closest('[data-load-library-node]') : null;
    if (!button) {
      return;
    }
    event.preventDefault();
      var requestedComponentMode = normalizeComponentMode(button.getAttribute('data-load-component-mode') || '');
      var nodeId = button.getAttribute('data-node-id') || '';
      if (nodeId === '') {
        return;
      }

      try {
        var rawRecent = window.localStorage ? window.localStorage.getItem('ops.gui_studio.library_recent_nodes') : '';
        var recentIds = rawRecent ? JSON.parse(rawRecent) : [];
        if (!Array.isArray(recentIds)) {
          recentIds = [];
        }
        recentIds = recentIds.map(function (id) { return String(id || ''); }).filter(Boolean);
        recentIds = recentIds.filter(function (id) { return id !== nodeId; });
        recentIds.unshift(nodeId);
        if (recentIds.length > 12) {
          recentIds = recentIds.slice(0, 12);
        }
        if (window.localStorage) {
          window.localStorage.setItem('ops.gui_studio.library_recent_nodes', JSON.stringify(recentIds));

          var usageRaw = window.localStorage.getItem(usageLibraryStorageKey);
          var usageCounts = usageRaw ? JSON.parse(usageRaw) : {};
          if (!usageCounts || typeof usageCounts !== 'object') {
            usageCounts = {};
          }
          usageCounts[nodeId] = Number(usageCounts[nodeId] || 0) + 1;
          window.localStorage.setItem(usageLibraryStorageKey, JSON.stringify(usageCounts));
        }
        if (typeof document !== 'undefined' && typeof window.CustomEvent === 'function') {
          document.dispatchEvent(new window.CustomEvent('studio-library-recent-updated', { detail: { nodeId: nodeId } }));
        }
      } catch (error) {
        // Keep loading flow resilient if localStorage is unavailable.
      }

      setButtonLoading(button, true);
      var endpoint = '/apps/studio/library/load?format=json&node_id=' + encodeURIComponent(nodeId);
      window.fetch(endpoint, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
      .then(function (response) {
        return response.json().then(function (payload) {
          return { ok: response.ok, payload: payload };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.payload || result.payload.ok !== true) {
          throw new Error(resolveLoadFailureMessage(result, 'node_load_failed'));
        }
        var nextBundle = projectBundleToComponentMode(result.payload.bundle || {}, requestedComponentMode);
        var forcedKind = normalizeArtifactKind(button.getAttribute('data-artifact-kind') || '');
        if (forcedKind === 'unknown') {
          var parentItem = button.closest ? button.closest('[data-library-item]') : null;
          forcedKind = normalizeArtifactKind(parentItem && parentItem.getAttribute ? parentItem.getAttribute('data-artifact-kind') : 'unknown');
        }
        loadBundleIntoEditor(nextBundle || {}, result.payload || {}, nodeId, forcedKind);
        enforceComponentModeInEditor(requestedComponentMode);
        enforceComponentModeOnBuilder(requestedComponentMode);
        if (result.payload && result.payload.partial_import) {
          window.alert(partialImportLabel);
        } else {
          window.alert(importCompleteLabel);
        }
      })
      .catch(function (error) {
        var message = error && error.message ? error.message : loadFailedLabel;
        window.alert(message);
      })
      .finally(function () {
        setButtonLoading(button, false);
      });
  });
})();
