(function () {
  function initStudioPage() {
  var recentLibraryStorageKey = 'ops.gui_studio.library_recent_nodes';
  var usageLibraryStorageKey = 'ops.gui_studio.library_usage_counts';
  const tabButtons = document.querySelectorAll('.studio-tabs button');
  const tabPanels = document.querySelectorAll('.tab-panel');

  var librarySearchInput = document.getElementById('gs-library-search');
  var libraryFilterButtons = document.querySelectorAll('[data-library-filter]');
  var libraryItems = document.querySelectorAll('[data-library-item]');
  var libraryAppSections = document.querySelectorAll('[data-library-app-section]');
  var libraryModuleSections = document.querySelectorAll('[data-library-module-section]');
  var libraryAdvancedRegistrySections = document.querySelectorAll('[data-library-advanced-registry]');
  var libraryRecentList = document.getElementById('gs-library-recent-list');
  var libraryMostUsedList = document.getElementById('gs-library-most-used-list');
  var libraryLastEditedList = document.getElementById('gs-library-last-edited-list');
  var libraryQuickLoadPanel = document.getElementById('gs-library-quick-load');
  var librarySearchResults = document.getElementById('gs-library-search-results');
  var librarySearchResultsList = document.getElementById('gs-library-search-results-list');
  var contentOutlinePanel = document.getElementById('gs-content-outline');
  var contentOutlineSummary = document.getElementById('gs-content-outline-summary');
  var contentOutlineBody = document.getElementById('gs-content-outline-body');
  var contentOutlineBackButton = document.getElementById('gs-content-outline-back');
  var switchCreateButton = document.getElementById('gs-switch-create');
  var switchUpgradeButton = document.getElementById('gs-switch-upgrade');
  var clearLoadedContextButtons = document.querySelectorAll('[data-gs-clear-loaded-context]');

  // ── Visibility tier system ──────────────────────────────────────────────
  var TIER_STORAGE_KEY = 'ops.gui_studio.visibility_tier';
  var currentVisibilityTier = (function () {
    try { return window.localStorage ? (window.localStorage.getItem(TIER_STORAGE_KEY) || 'guided') : 'guided'; } catch (e) { return 'guided'; }
  }());

  function applyVisibilityTier(tier) {
    var validTiers = { 'simple': true, 'guided': true, 'advanced': true };
    if (!validTiers[tier]) { tier = 'guided'; }
    currentVisibilityTier = tier;
    // Update [data-tier] elements
    document.querySelectorAll('[data-tier="guided"]').forEach(function (el) {
      el.classList.toggle('tier-hidden', tier === 'simple');
    });
    document.querySelectorAll('[data-tier="advanced"]').forEach(function (el) {
      el.classList.toggle('tier-hidden', tier === 'simple' || tier === 'guided');
    });
    // Update chip active states
    document.querySelectorAll('[data-tier-btn]').forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-tier-btn') === tier);
    });
    try { if (window.localStorage) { window.localStorage.setItem(TIER_STORAGE_KEY, tier); } } catch (e) {}
  }

  // Tier chip click handler
  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-tier-btn]') : null;
    if (!btn) { return; }
    applyVisibilityTier(btn.getAttribute('data-tier-btn') || 'guided');
  });

  // Apply saved/default tier on load
  applyVisibilityTier(currentVisibilityTier);
  // ── End visibility tier system ──────────────────────────────────────────

  var createFlowPanel = document.getElementById('gs-create-flow');
  var createFlowSummary = document.getElementById('gs-create-flow-summary');
  var createFlowBody = document.getElementById('gs-create-flow-body');
  var activeLibraryFilter = 'views';
  var studioShell = document.querySelector('.studio-shell');
  var mobileModeButtons = document.querySelectorAll('[data-mobile-mode-target]');
  var libraryDetailSheet = document.getElementById('gs-library-detail-sheet');
  var libraryDetailBackdrop = document.querySelector('.library-detail-backdrop');
  var libraryDetailLabel = document.getElementById('gs-library-detail-label');
  var libraryDetailKind = document.getElementById('gs-library-detail-kind');
  var libraryDetailNode = document.getElementById('gs-library-detail-node');
  var libraryDetailSupportNote = document.getElementById('gs-library-detail-support-note');
  var libraryDetailLoadButton = document.getElementById('gs-library-detail-load');
  var libraryDetailInspectButton = document.getElementById('gs-library-detail-inspect');
  var contentOutlineLabels = window.gsContentOutlineLabels = {
    summary: <?= json_encode($gs('content_outline_summary')) ?>,
    moduleSettings: <?= json_encode($gs('content_outline_module_settings')) ?>,
    fields: <?= json_encode($gs('content_outline_fields')) ?>,
    components: <?= json_encode($gs('content_outline_components')) ?>,
    layout: <?= json_encode($gs('content_outline_layout')) ?>,
    governance: <?= json_encode($gs('content_outline_governance')) ?>,
    selectedComponent: <?= json_encode($gs('content_outline_selected_component')) ?>,
    changeSummary: <?= json_encode($gs('content_outline_change_summary')) ?>,
    migrationPlan: <?= json_encode($gs('content_outline_migration_plan')) ?>,
    impactAnalysis: <?= json_encode($gs('content_outline_impact_analysis')) ?>,
    simulationPreview: <?= json_encode($gs('content_outline_simulation_preview')) ?>,
    tableMode: <?= json_encode($gs('content_outline_table_mode')) ?>,
    tableModeDirectDb: <?= json_encode($gs('content_outline_table_mode_direct_db')) ?>,
    tableModeViewOnly: <?= json_encode($gs('content_outline_table_mode_view_only')) ?>,
    toggleDirectDb: <?= json_encode($gs('content_outline_toggle_direct_db')) ?>,
    toggleViewOnly: <?= json_encode($gs('content_outline_toggle_view_only')) ?>,
    empty: <?= json_encode($gs('content_outline_empty')) ?>
  };
  var studioModeLabels = {
    create: <?= json_encode($gs('studio_mode_create_new')) ?>,
    upgrade: <?= json_encode($gs('studio_mode_edit_existing')) ?>
  };
  var createFlowLabels = {
    summary: <?= json_encode($gs('create_flow_empty')) ?>,
    steps: <?= json_encode($gs('create_flow_steps')) ?>,
    intent: <?= json_encode($gs('create_flow_intent_label')) ?>,
    stepIntent: <?= json_encode($gs('create_flow_step_intent')) ?>,
    intentCreateApp: <?= json_encode($gs('create_intent_app')) ?>,
    intentCreateModule: <?= json_encode($gs('create_intent_module')) ?>,
    intentCreateView: <?= json_encode($gs('create_intent_view')) ?>,
    intentCreateNavigation: <?= json_encode($gs('create_intent_navigation')) ?>,
    intentCreateDashboard: <?= json_encode($gs('create_intent_dashboard')) ?>,
    module: <?= json_encode($gs('create_flow_step_module')) ?>,
    view: <?= json_encode($gs('create_flow_step_view')) ?>,
    tableMode: <?= json_encode($gs('create_flow_step_table_mode')) ?>,
    fields: <?= json_encode($gs('create_flow_step_fields')) ?>,
    layout: <?= json_encode($gs('create_flow_step_layout')) ?>,
    governance: <?= json_encode($gs('create_flow_step_governance')) ?>,
    actions: <?= json_encode($gs('create_flow_actions')) ?>,
    actionConfigureNavigation: <?= json_encode($gs('create_flow_action_configure_navigation')) ?>,
    actionSetDashboard: <?= json_encode($gs('create_flow_action_set_dashboard')) ?>,
    actionAddTable: <?= json_encode($gs('create_flow_action_add_table')) ?>,
    actionAddForm: <?= json_encode($gs('create_flow_action_add_form')) ?>,
    actionAddKpi: <?= json_encode($gs('create_flow_action_add_kpi')) ?>,
    actionAddText: <?= json_encode($gs('create_flow_action_add_text')) ?>,
    actionDirectDb: <?= json_encode($gs('create_flow_action_direct_db')) ?>,
    actionViewMode: <?= json_encode($gs('create_flow_action_view_mode')) ?>
  };
  var viewKindDisplayLabels = {
    table: <?= json_encode($gs('view_type_table')) ?>,
    form: <?= json_encode($gs('view_type_form')) ?>,
    dashboard: <?= json_encode($gs('view_type_dashboard')) ?>
  };
  var pendingLabel = <?= json_encode($gs('pending')) ?>;
  var studioToolsDefaultPreview = {
    name: <?= json_encode($gs('studio_tool_resource_explorer')) ?>,
    group: <?= json_encode($gs('studio_tools_group_explore')) ?>,
    status: <?= json_encode($gs('studio_tools_status_available')) ?>,
    purpose: <?= json_encode($gs('studio_tool_purpose_resource_explorer')) ?>,
    worksOn: <?= json_encode($gs('studio_tools_boundary_owner_resources')) ?>,
    mustNotOwn: <?= json_encode($gs('studio_tools_boundary_not_owner')) ?>,
    firstSafe: <?= json_encode($gs('studio_tools_preview_first_safe_linked')) ?>,
    backend: <?= json_encode($gs('studio_tools_preview_backend_linked')) ?>,
    defaultText: <?= json_encode($gs('studio_tools_preview_default')) ?>
  };
  if (typeof window.gsBindStudioToolPreviewPanel === 'function') {
    window.gsBindStudioToolPreviewPanel(studioToolsDefaultPreview);
  }

  function clearUpgradeBaselineState() {
    Array.prototype.forEach.call(document.querySelectorAll('#gs_se_previous_bundle'), function (input) {
      if (input) {
        input.value = '{}';
      }
    });
    window.gsLoadedArtifactContext = null;
  }

  function clearLoadedContextDraftState() {
    if (!window.localStorage) {
      return;
    }
    try {
      var rawDraft = window.localStorage.getItem('ops.gui_studio.draft');
      if (!rawDraft) {
        return;
      }
      var parsedDraft = JSON.parse(rawDraft);
      if (!parsedDraft || typeof parsedDraft !== 'object') {
        return;
      }
      delete parsedDraft.se_previous_bundle;
      parsedDraft.studio_mode = 'create_new';
      parsedDraft.saved_at = new Date().toISOString();
      window.localStorage.setItem('ops.gui_studio.draft', JSON.stringify(parsedDraft));
    } catch (error) {
      // Ignore malformed local draft payloads.
    }
  }

  function clearLoadedContextLocalPreview() {
    if (typeof setStudioMode === 'function') {
      setStudioMode('create_new', { preserveUpgradeBaseline: true });
    } else {
      Array.prototype.forEach.call(document.querySelectorAll('input[name="studio_mode"]'), function (input) {
        if (input) {
          input.value = 'create_new';
        }
      });
    }

    Array.prototype.forEach.call(document.querySelectorAll('#gs_se_previous_bundle'), function (input) {
      if (input) {
        input.value = '{}';
      }
    });
    window.gsLoadedArtifactContext = null;
    clearLoadedContextDraftState();

    if (typeof window.updateEditorSurfaceHeader === 'function') {
      window.updateEditorSurfaceHeader();
    }
    if (typeof window.renderContentOutline === 'function') {
      window.renderContentOutline();
    }
    if (typeof window.renderCreateFlowGuide === 'function') {
      window.renderCreateFlowGuide();
    }
    if (typeof window.renderSurfaceExposurePanel === 'function') {
      window.renderSurfaceExposurePanel();
    }
    document.dispatchEvent(new window.CustomEvent('studio-mode-changed', { detail: { mode: 'create_new' } }));
  }

  function normalizeLoadedArtifactKind(kind) {
    var normalized = String(kind || '').toLowerCase();
    if (normalized === 'route_file') {
      return 'route';
    }
    return normalized;
  }

  function inferLoadedContentTypeFromBundle(bundle) {
    if (!bundle || typeof bundle !== 'object') {
      return 'unknown';
    }
    var viewDef = bundle.view_definition;
    if (viewDef && typeof viewDef === 'object' && Object.keys(viewDef).length > 0) {
      var viewObj = viewDef.view && typeof viewDef.view === 'object' ? viewDef.view : {};
      var viewKind = String(viewObj.view_kind || '').toLowerCase();
      if (viewKind === 'dashboard') {
        return 'dashboard';
      }
      var securityObj = viewDef.security && typeof viewDef.security === 'object' ? viewDef.security : {};
      var directDb = !!securityObj.direct_db_mutation_allowed;
      if (directDb && viewKind === 'table') {
        return 'db_table';
      }
      return 'view';
    }

    var navDef = bundle.navigation_definition;
    if (navDef && typeof navDef === 'object') {
      var navObj = navDef.navigation;
      if (navObj && typeof navObj === 'object' && Object.keys(navObj).length > 0) {
        return 'navigation';
      }
    }

    var modManifest = bundle.module_manifest;
    if (modManifest && typeof modManifest === 'object' && Object.keys(modManifest).length > 0) {
      return 'module';
    }

    var appManifest = bundle.app_manifest;
    if (appManifest && typeof appManifest === 'object' && Object.keys(appManifest).length > 0) {
      return 'app';
    }

    return 'unknown';
  }

  function resolveContentTypeFromArtifactKind(artifactKind, bundle) {
    var normalized = normalizeLoadedArtifactKind(artifactKind);
    if (normalized === 'app') {
      return 'app';
    }
    if (normalized === 'module') {
      return 'module';
    }
    if (normalized === 'view') {
      return 'view';
    }
    if (normalized === 'dashboard') {
      return 'dashboard';
    }
    return inferLoadedContentTypeFromBundle(bundle);
  }

  function readLoadedArtifactContext() {
    var context = window.gsLoadedArtifactContext;
    return context && typeof context === 'object' ? context : null;
  }

  function writeLoadedArtifactContext(context) {
    if (!context || typeof context !== 'object') {
      window.gsLoadedArtifactContext = null;
      return null;
    }
    window.gsLoadedArtifactContext = context;
    return context;
  }

  window.gsReadLoadedArtifactContext = readLoadedArtifactContext;
  window.gsWriteLoadedArtifactContext = writeLoadedArtifactContext;
  window.gsInferLoadedContentTypeFromBundle = inferLoadedContentTypeFromBundle;
  window.gsResolveContentTypeFromArtifactKind = resolveContentTypeFromArtifactKind;

  function displayViewKindLabel(kind) {
    var normalized = String(kind || '').toLowerCase();
    if (Object.prototype.hasOwnProperty.call(viewKindDisplayLabels, normalized)) {
      return String(viewKindDisplayLabels[normalized] || normalized);
    }
    return String(kind || 'table');
  }

  function isMobileStudio() {
    return typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 768px)').matches;
  }

  function readRecentLibraryIds() {
    try {
      var raw = window.localStorage ? window.localStorage.getItem(recentLibraryStorageKey) : '';
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed.map(function (id) { return String(id || ''); }).filter(Boolean) : [];
    } catch (error) {
      return [];
    }
  }

  function itemMatchesFilter(item, recentIds) {
    var kind = String(item.getAttribute('data-library-kind') || '').toLowerCase();
    if (activeLibraryFilter === 'views') return kind === 'views';
    if (activeLibraryFilter === 'routes') return kind === 'routes';
    if (activeLibraryFilter === 'modules') return kind === 'modules';
    if (activeLibraryFilter === 'apps') return kind === 'apps';
    return true;
  }

  function normalizeLibraryArtifactKind(kind) {
    var normalized = String(kind || '').toLowerCase();
    if (normalized === 'route_file') {
      return 'route';
    }
    return normalized;
  }

  function resolveLibraryArtifactKind(item, nodeIdOverride) {
    var nodeId = String(nodeIdOverride || (item ? item.getAttribute('data-node-id') : '') || '').toLowerCase();
    if (nodeId.indexOf('nav:') === 0) {
      return 'nav';
    }
    if (nodeId.indexOf('route:') === 0 || nodeId.indexOf('route_file:') === 0) {
      return 'route';
    }
    if (nodeId.indexOf('dashboard:') === 0) {
      return 'dashboard';
    }
    if (nodeId.indexOf('view:') === 0) {
      return 'view';
    }
    if (nodeId.indexOf('module:') === 0) {
      return 'module';
    }
    if (nodeId.indexOf('app:') === 0) {
      return 'app';
    }

    var attrKind = normalizeLibraryArtifactKind(item ? item.getAttribute('data-artifact-kind') : '');
    if (attrKind !== '') {
      return attrKind;
    }

    var libraryKind = normalizeLibraryArtifactKind(item ? item.getAttribute('data-library-kind') : '');
    if (libraryKind === 'apps') {
      return 'app';
    }
    if (libraryKind === 'modules') {
      return 'module';
    }
    if (libraryKind === 'routes') {
      return 'route';
    }
    return 'view';
  }

  function isArtifactLoadableInEditor(artifactKind) {
    return ['app', 'module', 'view', 'dashboard'].indexOf(normalizeLibraryArtifactKind(artifactKind)) !== -1;
  }

  function hasLoadedBundleForUpgrade() {
    var bundleInput = document.getElementById('gs_se_previous_bundle');
    if (!bundleInput) {
      return false;
    }
    var rawValue = String(bundleInput.value || '').trim();
    if (rawValue === '' || rawValue === '{}' || rawValue === 'null') {
      return false;
    }
    try {
      var parsed = JSON.parse(rawValue);
      return !!(parsed && typeof parsed === 'object' && Object.keys(parsed).length > 0);
    } catch (error) {
      return true;
    }
  }

  var getCurrentStudioMode = window.getCurrentStudioMode = function () {
    var modeInput = document.getElementById('gs_studio_mode');
    if (!modeInput) {
      modeInput = document.querySelector('input[name="studio_mode"]');
    }
    var rawMode = String((modeInput && modeInput.value) || 'create_new');
    return rawMode === 'edit_existing' ? 'edit_existing' : 'create_new';
  };

  var isLoadedContentMode = window.isLoadedContentMode = function () {
    if (!hasLoadedBundleForUpgrade()) {
      return false;
    }
    return getCurrentStudioMode() === 'edit_existing';
  };

  /**
   * Inspect the loaded bundle and infer its primary content type.
    * Returns one of: 'app' | 'module' | 'view' | 'dashboard' | 'db_table' | 'navigation' | 'unknown'
   */
  var getLoadedBundleContentType = window.getLoadedBundleContentType = function () {
    var bundle = readLoadedBundleObject();
    var context = readLoadedArtifactContext();
    if (context && typeof context === 'object') {
      if (typeof context.contentType === 'string' && context.contentType.trim() !== '') {
        return String(context.contentType);
      }
      if (typeof context.artifactKind === 'string' && context.artifactKind.trim() !== '') {
        return resolveContentTypeFromArtifactKind(context.artifactKind, bundle || {});
      }
    }
    return inferLoadedContentTypeFromBundle(bundle || {});
  };

  function readLoadedBundleObject() {
    var previousBundleEl = document.getElementById('gs_se_previous_bundle');
    if (!previousBundleEl) {
      return null;
    }
    var raw = String(previousBundleEl.value || '').trim();
    if (!raw || raw === '{}' || raw === 'null') {
      return null;
    }
    try {
      var parsed = JSON.parse(raw);
      return (parsed && typeof parsed === 'object') ? parsed : null;
    } catch (error) {
      return null;
    }
  }

  function pickFirstNonEmpty(values) {
    var list = Array.isArray(values) ? values : [];
    for (var i = 0; i < list.length; i += 1) {
      var value = String(list[i] || '').trim();
      if (value !== '') {
        return value;
      }
    }
    return '';
  }

  function resolveLoadedItemName(contentType, bundle) {
    if (!bundle || typeof bundle !== 'object') {
      return '';
    }

    var appManifest = bundle.app_manifest && typeof bundle.app_manifest === 'object' ? bundle.app_manifest : {};
    var moduleManifest = bundle.module_manifest && typeof bundle.module_manifest === 'object' ? bundle.module_manifest : {};
    var viewDef = bundle.view_definition && typeof bundle.view_definition === 'object' ? bundle.view_definition : {};
    var viewObj = viewDef.view && typeof viewDef.view === 'object' ? viewDef.view : {};
    var navDef = bundle.navigation_definition && typeof bundle.navigation_definition === 'object' ? bundle.navigation_definition : {};
    var navObj = navDef.navigation && typeof navDef.navigation === 'object' ? navDef.navigation : {};

    if (contentType === 'app') {
      // Support both studio-format ({ app: { app_key, display_name } }) and legacy flat format
      var appInner = appManifest.app && typeof appManifest.app === 'object' ? appManifest.app : appManifest;
      return pickFirstNonEmpty([
        appInner.app_label,
        appInner.display_name,
        appInner.app_name,
        appInner.app_key,
        appManifest.app_label,
        appManifest.display_name,
        appManifest.app_name,
        appManifest.app_key
      ]);
    }
    if (contentType === 'module') {
      return pickFirstNonEmpty([
        moduleManifest.module_display_name,
        moduleManifest.display_name,
        moduleManifest.module_key,
        moduleManifest.name,
        document.getElementById('gs_se_module_display_name') ? document.getElementById('gs_se_module_display_name').value : '',
        document.getElementById('gs_se_module_key') ? document.getElementById('gs_se_module_key').value : ''
      ]);
    }
    if (contentType === 'view' || contentType === 'db_table' || contentType === 'dashboard') {
      return pickFirstNonEmpty([
        viewObj.title,
        viewObj.name,
        viewObj.key,
        viewObj.view_key,
        viewObj.route,
        viewObj.route_path,
        moduleManifest.module_display_name,
        moduleManifest.module_key
      ]);
    }
    if (contentType === 'navigation') {
      return pickFirstNonEmpty([
        navObj.label,
        navObj.key,
        navObj.target,
        document.getElementById('gs_se_nav_label') ? document.getElementById('gs_se_nav_label').value : ''
      ]);
    }

    return pickFirstNonEmpty([
      moduleManifest.module_display_name,
      moduleManifest.module_key,
      appManifest.app_label,
      appManifest.app_key
    ]);
  }

  function applyTemplate(template, params) {
    return String(template || '').replace(/\{([a-z_]+)\}/gi, function (_match, key) {
      return Object.prototype.hasOwnProperty.call(params, key) ? String(params[key] || '') : '';
    });
  }

  function normalizeCompositionState(value) {
    var state = String(value || '').toLowerCase();
    if (state === 'connected' || state === 'missing_route' || state === 'missing_nav' || state === 'nav_unknown_route' || state === 'ambiguous_conflicting' || state === 'inspect_only') {
      return state;
    }
    return 'connected';
  }

  function buildFallbackCompositionContract(bundle) {
    var sourceBundle = bundle && typeof bundle === 'object' ? bundle : {};
    var viewDef = sourceBundle.view_definition && typeof sourceBundle.view_definition === 'object' ? sourceBundle.view_definition : {};
    var viewObj = viewDef.view && typeof viewDef.view === 'object' ? viewDef.view : {};
    var navDef = sourceBundle.navigation_definition && typeof sourceBundle.navigation_definition === 'object' ? sourceBundle.navigation_definition : {};
    var navObj = navDef.navigation && typeof navDef.navigation === 'object' ? navDef.navigation : {};

    var routePath = String(viewObj.route_path || '').trim();
    var navTargetRoute = String(navObj.url || '').trim();
    var navFound = navTargetRoute !== '';
    var relationshipState = 'connected';
    var diagnostics = [];
    if (routePath === '') {
      relationshipState = 'missing_route';
      diagnostics.push('missing_route');
    }
    if (!navFound) {
      if (relationshipState === 'connected') {
        relationshipState = 'missing_nav';
      }
      diagnostics.push('missing_nav');
    }
    if (routePath === '' && navTargetRoute !== '') {
      relationshipState = 'nav_unknown_route';
      diagnostics.push('nav_unknown_route');
    }
    if (routePath !== '' && navTargetRoute !== '' && routePath !== navTargetRoute) {
      relationshipState = 'ambiguous_conflicting';
      diagnostics.push('ambiguous_conflicting');
    }
    if (diagnostics.length === 0) {
      diagnostics.push('connected');
    }

    return {
      version: 'studio.composition.v1',
      artifact_kind: 'view',
      view_exposure: {
        view_key: String(viewObj.view_key || viewObj.key || ''),
        display_label: String(viewObj.title || viewObj.name || viewObj.view_key || ''),
        owning_app: String(viewObj.app_key || ''),
        owning_module: String(viewObj.module_key || ''),
        route_path: routePath,
        route_key: ''
      },
      navigation_exposure: {
        nav_key: String(navObj.key || ''),
        label: String(navObj.label || navObj.label_key || ''),
        icon: String(navObj.icon || ''),
        order: Number(navObj.order || 10),
        target_route: navTargetRoute,
        target_view: '',
        source_app: String(navObj.owner_app || viewObj.app_key || ''),
        source_module: String(viewObj.module_key || ''),
        visibility: String(navObj.visible_if || 'always')
      },
      relationship_state: {
        state: relationshipState,
        route_status: routePath !== '' ? 'connected' : 'missing_route',
        navigation_status: navFound ? 'connected' : 'missing_nav',
        inspect_only: true,
        diagnostics: diagnostics
      },
      source: {
        kind: 'fallback',
        route_file: '',
        navigation_file: '',
        route_found: routePath !== '',
        navigation_found: navFound
      }
    };
  }

  function resolveLoadedCompositionContract(bundle) {
    var sourceBundle = bundle && typeof bundle === 'object' ? bundle : {};
    var metadata = sourceBundle.metadata && typeof sourceBundle.metadata === 'object' ? sourceBundle.metadata : {};
    var importMeta = metadata.import && typeof metadata.import === 'object' ? metadata.import : {};
    var composition = importMeta.composition && typeof importMeta.composition === 'object' ? importMeta.composition : null;
    if (composition && String(composition.version || '') === 'studio.composition.v1') {
      return composition;
    }
    return buildFallbackCompositionContract(sourceBundle);
  }

  var renderSurfaceExposurePanel = window.renderSurfaceExposurePanel = function () {
    var panel = document.getElementById('gs-surface-exposure-section');
    var summaryNode = document.getElementById('gs-surface-exposure-summary');
    var bodyNode = document.getElementById('gs-surface-exposure-body');
    var diagnosticsNode = document.getElementById('gs-surface-exposure-diagnostics');
    if (!panel || !summaryNode || !bodyNode || !diagnosticsNode) {
      return;
    }

    var loaded = isLoadedContentMode();
    var contentType = loaded ? getLoadedBundleContentType() : 'unknown';
    var viewContext = contentType === 'view' || contentType === 'dashboard' || contentType === 'db_table';
    if (!loaded || !viewContext) {
      panel.hidden = true;
      summaryNode.textContent = '';
      bodyNode.innerHTML = '';
      diagnosticsNode.innerHTML = '';
      return;
    }

    var labels = {
      summary: <?= json_encode($gs('surface_exposure_summary_label')) ?>,
      aspectView: <?= json_encode($gs('surface_exposure_aspect_view')) ?>,
      aspectRoute: <?= json_encode($gs('surface_exposure_aspect_route')) ?>,
      aspectNavigation: <?= json_encode($gs('surface_exposure_aspect_navigation')) ?>,
      aspectPolicy: <?= json_encode($gs('surface_exposure_aspect_policy')) ?>,
      none: <?= json_encode($gs('surface_exposure_value_none')) ?>,
      inspectOnly: <?= json_encode($gs('surface_exposure_policy_inspect_only')) ?>,
      states: {
        connected: <?= json_encode($gs('surface_exposure_state_connected')) ?>,
        missing_route: <?= json_encode($gs('surface_exposure_state_missing_route')) ?>,
        missing_nav: <?= json_encode($gs('surface_exposure_state_missing_nav')) ?>,
        nav_unknown_route: <?= json_encode($gs('surface_exposure_state_nav_unknown_route')) ?>,
        ambiguous_conflicting: <?= json_encode($gs('surface_exposure_state_ambiguous_conflicting')) ?>,
        inspect_only: <?= json_encode($gs('surface_exposure_state_inspect_only')) ?>
      },
      diagnostics: {
        connected: <?= json_encode($gs('surface_exposure_diag_connected')) ?>,
        missing_route: <?= json_encode($gs('surface_exposure_diag_missing_route')) ?>,
        missing_nav: <?= json_encode($gs('surface_exposure_diag_missing_nav')) ?>,
        nav_unknown_route: <?= json_encode($gs('surface_exposure_diag_nav_unknown_route')) ?>,
        ambiguous_conflicting: <?= json_encode($gs('surface_exposure_diag_ambiguous_conflicting')) ?>
      }
    };

    var contract = resolveLoadedCompositionContract(readLoadedBundleObject() || {});
    var viewExposure = contract.view_exposure && typeof contract.view_exposure === 'object' ? contract.view_exposure : {};
    var navExposure = contract.navigation_exposure && typeof contract.navigation_exposure === 'object' ? contract.navigation_exposure : {};
    var relation = contract.relationship_state && typeof contract.relationship_state === 'object' ? contract.relationship_state : {};

    var relationState = normalizeCompositionState(relation.state || 'connected');
    var routeStatus = normalizeCompositionState(relation.route_status || (String(viewExposure.route_path || '').trim() !== '' ? 'connected' : 'missing_route'));
    var navStatus = normalizeCompositionState(relation.navigation_status || ((String(navExposure.target_route || '').trim() !== '' || String(navExposure.target_view || '').trim() !== '') ? 'connected' : 'missing_nav'));

    summaryNode.textContent = String(labels.summary || 'Relationship') + ': ' + String(labels.states[relationState] || relationState);

    var viewValue = [
      String(viewExposure.view_key || '').trim(),
      String(viewExposure.owning_app || '').trim() !== '' || String(viewExposure.owning_module || '').trim() !== ''
        ? ('(' + [String(viewExposure.owning_app || '').trim(), String(viewExposure.owning_module || '').trim()].filter(Boolean).join('/') + ')')
        : ''
    ].filter(Boolean).join(' ');

    var routeValue = String(viewExposure.route_path || '').trim();
    var navValueParts = [];
    var navLabel = String(navExposure.label || '').trim();
    var navTargetRoute = String(navExposure.target_route || '').trim();
    var navTargetView = String(navExposure.target_view || '').trim();
    if (navLabel !== '') {
      navValueParts.push(navLabel);
    }
    if (navTargetRoute !== '') {
      navValueParts.push('-> ' + navTargetRoute);
    } else if (navTargetView !== '') {
      navValueParts.push('-> ' + navTargetView);
    }

    var rows = [
      {
        aspect: String(labels.aspectView || 'View'),
        value: viewValue !== '' ? viewValue : String(labels.none || 'None'),
        state: String(labels.states.connected || 'connected'),
        actionKind: ''
      },
      {
        aspect: String(labels.aspectRoute || 'Route'),
        value: routeValue !== '' ? routeValue : String(labels.none || 'None'),
        state: String(labels.states[routeStatus] || routeStatus),
        actionKind: routeStatus === 'missing_route' ? 'route_link_create' : (routeStatus === 'connected' ? 'route_link_upgrade' : '')
      },
      {
        aspect: String(labels.aspectNavigation || 'Navigation'),
        value: navValueParts.length > 0 ? navValueParts.join(' ') : String(labels.none || 'None'),
        state: String(labels.states[navStatus] || navStatus),
        actionKind: navStatus === 'missing_nav' ? 'nav_link_create' : (navStatus === 'nav_unknown_route' ? 'nav_link_update' : '')
      },
      {
        aspect: String(labels.aspectPolicy || 'Editability'),
        value: String(labels.inspectOnly || 'Inspect-only'),
        state: String(labels.states.inspect_only || 'inspect-only'),
        actionKind: ''
      }
    ];

    bodyNode.innerHTML = '';
    rows.forEach(function (row) {
      var tr = document.createElement('tr');

      var tdAspect = document.createElement('td');
      tdAspect.textContent = String(row.aspect || '');
      tr.appendChild(tdAspect);

      var tdValue = document.createElement('td');
      tdValue.textContent = String(row.value || '');
      tr.appendChild(tdValue);

      var tdState = document.createElement('td');
      var chip = document.createElement('span');
      chip.className = 'status-chip';
      chip.textContent = String(row.state || '');
      tdState.appendChild(chip);
      tr.appendChild(tdState);

      var tdAction = document.createElement('td');
      var actionKind = String(row.actionKind || '');
      if (actionKind === 'route_link_create' || actionKind === 'route_link_upgrade') {
        var actionBtn = document.createElement('button');
        actionBtn.type = 'button';
        actionBtn.className = 'gs-route-link-link-btn';
        actionBtn.textContent = actionKind === 'route_link_create'
          ? <?= json_encode($gs('route_link_open_btn')) ?>
          : <?= json_encode($gs('route_link_change_btn')) ?>;
        actionBtn.setAttribute('data-route-link-action', actionKind);
        actionBtn.setAttribute('data-route-link-current', String(viewExposure.route_path || ''));
        actionBtn.setAttribute('data-route-link-app', String(viewExposure.owning_app || ''));
        actionBtn.setAttribute('data-route-link-module', String(viewExposure.owning_module || ''));
        actionBtn.addEventListener('click', function () {
          openRouteLinkPanel(actionKind, actionBtn.getAttribute('data-route-link-current') || '', actionBtn.getAttribute('data-route-link-app') || '', actionBtn.getAttribute('data-route-link-module') || '');
        });
        tdAction.appendChild(actionBtn);
      } else if (actionKind === 'nav_link_create' || actionKind === 'nav_link_update') {
        var navActionBtn = document.createElement('button');
        navActionBtn.type = 'button';
        navActionBtn.className = 'gs-route-link-link-btn';
        navActionBtn.textContent = actionKind === 'nav_link_create'
          ? <?= json_encode($gs('nav_link_open_btn')) ?>
          : <?= json_encode($gs('nav_link_update_btn')) ?>;
        navActionBtn.setAttribute('data-nav-link-action', actionKind);
        navActionBtn.setAttribute('data-nav-link-current-url', navTargetRoute);
        navActionBtn.setAttribute('data-nav-link-app', String(viewExposure.owning_app || ''));
        navActionBtn.setAttribute('data-nav-link-module', String(viewExposure.owning_module || ''));
        navActionBtn.setAttribute('data-nav-link-route-path', String(viewExposure.route_path || ''));
        navActionBtn.setAttribute('data-nav-link-label', navLabel);
        navActionBtn.setAttribute('data-nav-link-key', String(navExposure.nav_key || ''));
        navActionBtn.addEventListener('click', function () {
          openNavLinkPanel(
            navActionBtn.getAttribute('data-nav-link-action') || '',
            navActionBtn.getAttribute('data-nav-link-current-url') || '',
            navActionBtn.getAttribute('data-nav-link-app') || '',
            navActionBtn.getAttribute('data-nav-link-module') || '',
            navActionBtn.getAttribute('data-nav-link-route-path') || '',
            navActionBtn.getAttribute('data-nav-link-label') || '',
            navActionBtn.getAttribute('data-nav-link-key') || ''
          );
        });
        tdAction.appendChild(navActionBtn);
      }
      tr.appendChild(tdAction);

      bodyNode.appendChild(tr);
    });

    diagnosticsNode.innerHTML = '';
    var diagnostics = Array.isArray(relation.diagnostics) ? relation.diagnostics : [];
    if (diagnostics.length === 0) {
      diagnostics = ['connected'];
    }
    diagnostics.forEach(function (code) {
      var normalized = normalizeCompositionState(code);
      var li = document.createElement('li');
      li.textContent = String(labels.diagnostics[normalized] || normalized);
      diagnosticsNode.appendChild(li);
    });

    panel.hidden = false;
  };

  // Phase 1E: Route Link Proposal Panel state
  var _routeLinkState = {
    open: false,
    mode: 'create',       // 'create' | 'upgrade'
    currentRoute: '',
    appKey: '',
    moduleKey: '',
    analysis: null,
    plan: null,
    fingerprint: ''
  };

  function openRouteLinkPanel(actionKind, currentRoute, appKey, moduleKey) {
    _routeLinkState.open = true;
    _routeLinkState.mode = actionKind === 'route_link_upgrade' ? 'upgrade' : 'create';
    _routeLinkState.currentRoute = String(currentRoute || '');
    _routeLinkState.appKey = String(appKey || '');
    _routeLinkState.moduleKey = String(moduleKey || '');
    _routeLinkState.analysis = null;
    _routeLinkState.plan = null;
    _routeLinkState.fingerprint = '';

    var panel = document.getElementById('gs-route-link-panel');
    var titleNode = document.getElementById('gs-route-link-panel-title');
    var subtitleNode = document.getElementById('gs-route-link-panel-subtitle');
    var upgradeAckRow = document.getElementById('gs-route-link-upgrade-ack-row');
    var upgradeAck = document.getElementById('gs-route-link-upgrade-ack');
    var inputNode = document.getElementById('gs-route-link-input');
    var changesSection = document.getElementById('gs-route-link-changes-section');
    var errorsNode = document.getElementById('gs-route-link-errors');
    var applyBtn = document.getElementById('gs-route-link-apply-btn');
    if (!panel) { return; }

    if (titleNode) {
      titleNode.textContent = _routeLinkState.mode === 'upgrade'
        ? <?= json_encode($gs('route_link_title')) ?>
        : <?= json_encode($gs('route_link_title')) ?>;
    }
    if (subtitleNode) {
      subtitleNode.textContent = _routeLinkState.mode === 'upgrade'
        ? <?= json_encode($gs('route_link_upgrade_subtitle')) ?>
        : <?= json_encode($gs('route_link_subtitle')) ?>;
    }
    if (upgradeAckRow) {
      upgradeAckRow.hidden = _routeLinkState.mode !== 'upgrade';
    }
    if (upgradeAck) {
      upgradeAck.checked = false;
    }
    if (inputNode) {
      inputNode.value = _routeLinkState.currentRoute !== '' ? _routeLinkState.currentRoute : '';
    }
    if (changesSection) { changesSection.hidden = true; }
    if (errorsNode) { errorsNode.hidden = true; errorsNode.textContent = ''; }
    if (applyBtn) { applyBtn.disabled = true; }
    panel.hidden = false;
  }

  function closeRouteLinkPanel() {
    _routeLinkState.open = false;
    var panel = document.getElementById('gs-route-link-panel');
    if (panel) { panel.hidden = true; }
  }

  function routeLinkAnalyze() {
    var inputNode = document.getElementById('gs-route-link-input');
    var upgradeAck = document.getElementById('gs-route-link-upgrade-ack');
    var analyzeBtn = document.getElementById('gs-route-link-analyze-btn');
    var errorsNode = document.getElementById('gs-route-link-errors');
    var changesSection = document.getElementById('gs-route-link-changes-section');
    var changesBody = document.getElementById('gs-route-link-changes-body');
    var gateChip = document.getElementById('gs-route-link-gate-status');
    var applyBtn = document.getElementById('gs-route-link-apply-btn');
    if (!inputNode) { return; }

    var proposedRoute = String(inputNode.value || '').trim();
    if (proposedRoute === '') {
      if (errorsNode) { errorsNode.textContent = 'Route path is required.'; errorsNode.hidden = false; }
      return;
    }

    if (analyzeBtn) { analyzeBtn.textContent = <?= json_encode($gs('route_link_analyzing')) ?>; analyzeBtn.disabled = true; }
    if (errorsNode) { errorsNode.hidden = true; errorsNode.textContent = ''; }

    var formData = new FormData();
    formData.append('csrf', String(document.querySelector('input[name="csrf"]') ? document.querySelector('input[name="csrf"]').value : ''));
    formData.append('app_key', _routeLinkState.appKey);
    formData.append('module_key', _routeLinkState.moduleKey);
    formData.append('proposed_route', proposedRoute);
    formData.append('mode', _routeLinkState.mode);
    formData.append('current_route', _routeLinkState.currentRoute);
    if (_routeLinkState.mode === 'upgrade' && upgradeAck && upgradeAck.checked) {
      formData.append('upgrade_acknowledged', '1');
    }

    fetch('/apps/studio/route-link/analyze', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (analyzeBtn) { analyzeBtn.textContent = <?= json_encode($gs('route_link_analyze_btn')) ?>; analyzeBtn.disabled = false; }
      _routeLinkState.analysis = data;
      _routeLinkState.plan = data.plan || null;
      _routeLinkState.fingerprint = String(data.fingerprint || '');

      // Show errors/warnings
      var errors = Array.isArray(data.errors) ? data.errors : [];
      var errorTexts = errors.filter(function (e) { return String(e.severity || '') !== 'warning'; }).map(function (e) { return String(e.message || e.code || ''); });
      if (errorsNode) {
        if (errorTexts.length > 0) {
          errorsNode.textContent = errorTexts.join(' ');
          errorsNode.hidden = false;
        } else {
          errorsNode.hidden = true;
          errorsNode.textContent = '';
        }
      }

      // Render changes table
      var changes = Array.isArray(data.changes) ? data.changes : [];
      if (changesBody) {
        changesBody.innerHTML = '';
        if (changes.length === 0) {
          var tr = document.createElement('tr');
          var td = document.createElement('td');
          td.colSpan = 4;
          td.textContent = <?= json_encode($gs('route_link_changes_none')) ?>;
          tr.appendChild(td);
          changesBody.appendChild(tr);
        } else {
          changes.forEach(function (ch) {
            var tr = document.createElement('tr');
            [String(ch.target_file || ''), String(ch.field || ''), String(ch.before !== null ? (ch.before || '(none)') : '(none)'), String(ch.after || '')].forEach(function (val) {
              var td = document.createElement('td');
              td.textContent = val;
              tr.appendChild(td);
            });
            changesBody.appendChild(tr);
          });
        }
      }

      // Gate status
      var gateStatus = String((data.apply_gate || {}).status || 'BLOCKED');
      var canApply = !!(data.apply_gate || {}).can_apply;
      if (gateChip) {
        gateChip.textContent = gateStatus === 'READY'
          ? <?= json_encode($gs('route_link_gate_ready')) ?>
          : <?= json_encode($gs('route_link_gate_blocked')) ?>;
        gateChip.className = 'status-chip gs-route-link-gate-chip' + (gateStatus === 'READY' ? ' badge-active' : ' badge-inactive');
      }
      if (applyBtn) { applyBtn.disabled = !canApply || _routeLinkState.fingerprint === ''; }
      if (changesSection) { changesSection.hidden = false; }
    })
    .catch(function (err) {
      if (analyzeBtn) { analyzeBtn.textContent = <?= json_encode($gs('route_link_analyze_btn')) ?>; analyzeBtn.disabled = false; }
      if (errorsNode) { errorsNode.textContent = 'Request failed. Please try again.'; errorsNode.hidden = false; }
    });
  }

  function routeLinkApply() {
    if (!_routeLinkState.plan || _routeLinkState.fingerprint === '') { return; }
    var applyBtn = document.getElementById('gs-route-link-apply-btn');
    var errorsNode = document.getElementById('gs-route-link-errors');
    var gateChip = document.getElementById('gs-route-link-gate-status');

    if (applyBtn) { applyBtn.textContent = <?= json_encode($gs('route_link_applying')) ?>; applyBtn.disabled = true; }

    var formData = new FormData();
    formData.append('csrf', String(document.querySelector('input[name="csrf"]') ? document.querySelector('input[name="csrf"]').value : ''));
    formData.append('plan', JSON.stringify(_routeLinkState.plan));
    formData.append('apply_fingerprint', _routeLinkState.fingerprint);

    fetch('/apps/studio/route-link/apply', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (applyBtn) { applyBtn.textContent = <?= json_encode($gs('route_link_apply_btn')) ?>; }
      if (data.ok) {
        if (gateChip) { gateChip.textContent = <?= json_encode($gs('route_link_success')) ?>; gateChip.className = 'status-chip gs-route-link-gate-chip badge-active'; }
        if (errorsNode) { errorsNode.hidden = true; }
        if (applyBtn) { applyBtn.disabled = true; }
        // Reload the bundle composition contract display after successful apply
        if (typeof window.renderSurfaceExposurePanel === 'function') {
          setTimeout(function () { window.renderSurfaceExposurePanel(); }, 300);
        }
      } else {
        var msg = String(data.message || data.code || <?= json_encode($gs('route_link_error_failed')) ?>);
        if (errorsNode) { errorsNode.textContent = msg; errorsNode.hidden = false; }
        if (applyBtn) { applyBtn.disabled = false; }
      }
    })
    .catch(function () {
      if (applyBtn) { applyBtn.textContent = <?= json_encode($gs('route_link_apply_btn')) ?>; applyBtn.disabled = false; }
      if (errorsNode) { errorsNode.textContent = <?= json_encode($gs('route_link_error_failed')) ?>; errorsNode.hidden = false; }
    });
  }

  // Wire route-link panel buttons
  document.addEventListener('DOMContentLoaded', function () {
    var analyzeBtn = document.getElementById('gs-route-link-analyze-btn');
    var cancelBtn = document.getElementById('gs-route-link-cancel-btn');
    var applyBtn = document.getElementById('gs-route-link-apply-btn');
    if (analyzeBtn) { analyzeBtn.addEventListener('click', routeLinkAnalyze); }
    if (cancelBtn) { cancelBtn.addEventListener('click', closeRouteLinkPanel); }
    if (applyBtn) { applyBtn.addEventListener('click', routeLinkApply); }
  });

  // Phase 1F: Nav Link Proposal Panel state
  var _navLinkState = {
    open: false,
    mode: 'create',       // 'create' | 'update'
    currentUrl: '',
    appKey: '',
    moduleKey: '',
    prefillUrl: '',
    navLabel: '',
    navKey: '',
    analysis: null,
    plan: null,
    fingerprint: ''
  };

  function openNavLinkPanel(actionKind, currentUrl, appKey, moduleKey, prefillUrl, navLabel, navKey) {
    _navLinkState.open = true;
    _navLinkState.mode = actionKind === 'nav_link_update' ? 'update' : 'create';
    _navLinkState.currentUrl = String(currentUrl || '');
    _navLinkState.appKey = String(appKey || '');
    _navLinkState.moduleKey = String(moduleKey || '');
    _navLinkState.prefillUrl = String(prefillUrl || '');
    _navLinkState.navLabel = String(navLabel || '');
    _navLinkState.navKey = String(navKey || '');
    _navLinkState.analysis = null;
    _navLinkState.plan = null;
    _navLinkState.fingerprint = '';

    var panel = document.getElementById('gs-nav-link-panel');
    var titleNode = document.getElementById('gs-nav-link-panel-title');
    var subtitleNode = document.getElementById('gs-nav-link-panel-subtitle');
    var upgradeAckRow = document.getElementById('gs-nav-link-upgrade-ack-row');
    var upgradeAck = document.getElementById('gs-nav-link-upgrade-ack');
    var urlInput = document.getElementById('gs-nav-link-url-input');
    var labelInput = document.getElementById('gs-nav-link-label-input');
    var changesSection = document.getElementById('gs-nav-link-changes-section');
    var errorsNode = document.getElementById('gs-nav-link-errors');
    var applyBtn = document.getElementById('gs-nav-link-apply-btn');
    if (!panel) { return; }

    if (titleNode) { titleNode.textContent = <?= json_encode($gs('nav_link_title')) ?>; }
    if (subtitleNode) {
      subtitleNode.textContent = _navLinkState.mode === 'update'
        ? <?= json_encode($gs('nav_link_upgrade_subtitle')) ?>
        : <?= json_encode($gs('nav_link_subtitle')) ?>;
    }
    if (upgradeAckRow) { upgradeAckRow.hidden = _navLinkState.mode !== 'update'; }
    if (upgradeAck) { upgradeAck.checked = false; }
    if (urlInput) { urlInput.value = _navLinkState.prefillUrl || _navLinkState.currentUrl || ''; }
    if (labelInput) { labelInput.value = _navLinkState.navLabel || ''; }
    if (changesSection) { changesSection.hidden = true; }
    if (errorsNode) { errorsNode.hidden = true; errorsNode.textContent = ''; }
    if (applyBtn) { applyBtn.disabled = true; }
    panel.hidden = false;
  }

  function closeNavLinkPanel() {
    _navLinkState.open = false;
    var panel = document.getElementById('gs-nav-link-panel');
    if (panel) { panel.hidden = true; }
  }

  function navLinkAnalyze() {
    var urlInput = document.getElementById('gs-nav-link-url-input');
    var labelInput = document.getElementById('gs-nav-link-label-input');
    var upgradeAck = document.getElementById('gs-nav-link-upgrade-ack');
    var analyzeBtn = document.getElementById('gs-nav-link-analyze-btn');
    var errorsNode = document.getElementById('gs-nav-link-errors');
    var changesSection = document.getElementById('gs-nav-link-changes-section');
    var changesBody = document.getElementById('gs-nav-link-changes-body');
    var gateChip = document.getElementById('gs-nav-link-gate-status');
    var applyBtn = document.getElementById('gs-nav-link-apply-btn');
    if (!urlInput) { return; }

    var proposedUrl = String(urlInput.value || '').trim();
    if (proposedUrl === '') {
      if (errorsNode) { errorsNode.textContent = 'Nav URL is required.'; errorsNode.hidden = false; }
      return;
    }

    if (analyzeBtn) { analyzeBtn.textContent = <?= json_encode($gs('nav_link_analyzing')) ?>; analyzeBtn.disabled = true; }
    if (errorsNode) { errorsNode.hidden = true; errorsNode.textContent = ''; }

    var formData = new FormData();
    formData.append('csrf', String(document.querySelector('input[name="csrf"]') ? document.querySelector('input[name="csrf"]').value : ''));
    formData.append('app_key', _navLinkState.appKey);
    formData.append('module_key', _navLinkState.moduleKey);
    formData.append('proposed_url', proposedUrl);
    formData.append('mode', _navLinkState.mode);
    formData.append('current_url', _navLinkState.currentUrl);
    formData.append('nav_label', labelInput ? String(labelInput.value || '') : _navLinkState.navLabel);
    if (_navLinkState.mode === 'update' && upgradeAck && upgradeAck.checked) {
      formData.append('upgrade_acknowledged', '1');
    }

    fetch('/apps/studio/nav-link/analyze', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (analyzeBtn) { analyzeBtn.textContent = <?= json_encode($gs('nav_link_analyze_btn')) ?>; analyzeBtn.disabled = false; }
      _navLinkState.analysis = data;
      _navLinkState.plan = data.plan || null;
      _navLinkState.fingerprint = String(data.fingerprint || '');

      var errors = Array.isArray(data.errors) ? data.errors : [];
      var errorTexts = errors.filter(function (e) { return String(e.severity || '') !== 'warning'; }).map(function (e) { return String(e.message || e.code || ''); });
      if (errorsNode) {
        if (errorTexts.length > 0) { errorsNode.textContent = errorTexts.join(' '); errorsNode.hidden = false; }
        else { errorsNode.hidden = true; errorsNode.textContent = ''; }
      }

      var changes = Array.isArray(data.changes) ? data.changes : [];
      if (changesBody) {
        changesBody.innerHTML = '';
        if (changes.length === 0) {
          var tr = document.createElement('tr');
          var td = document.createElement('td');
          td.colSpan = 4;
          td.textContent = <?= json_encode($gs('nav_link_changes_none')) ?>;
          tr.appendChild(td);
          changesBody.appendChild(tr);
        } else {
          changes.forEach(function (ch) {
            var tr = document.createElement('tr');
            [String(ch.target_file || ''), String(ch.field || ''), String(ch.before !== null ? (ch.before || '(none)') : '(none)'), String(ch.after || '')].forEach(function (val) {
              var td = document.createElement('td');
              td.textContent = val;
              tr.appendChild(td);
            });
            changesBody.appendChild(tr);
          });
        }
      }

      var gateStatus = String((data.apply_gate || {}).status || 'BLOCKED');
      var canApply = !!(data.apply_gate || {}).can_apply;
      if (gateChip) {
        gateChip.textContent = gateStatus === 'READY'
          ? <?= json_encode($gs('nav_link_gate_ready')) ?>
          : <?= json_encode($gs('nav_link_gate_blocked')) ?>;
        gateChip.className = 'status-chip gs-route-link-gate-chip' + (gateStatus === 'READY' ? ' badge-active' : ' badge-inactive');
      }
      if (applyBtn) { applyBtn.disabled = !canApply || _navLinkState.fingerprint === ''; }
      if (changesSection) { changesSection.hidden = false; }
    })
    .catch(function () {
      if (analyzeBtn) { analyzeBtn.textContent = <?= json_encode($gs('nav_link_analyze_btn')) ?>; analyzeBtn.disabled = false; }
      if (errorsNode) { errorsNode.textContent = 'Request failed. Please try again.'; errorsNode.hidden = false; }
    });
  }

  function navLinkApply() {
    if (!_navLinkState.plan || _navLinkState.fingerprint === '') { return; }
    var applyBtn = document.getElementById('gs-nav-link-apply-btn');
    var errorsNode = document.getElementById('gs-nav-link-errors');
    var gateChip = document.getElementById('gs-nav-link-gate-status');

    if (applyBtn) { applyBtn.textContent = <?= json_encode($gs('nav_link_applying')) ?>; applyBtn.disabled = true; }

    var formData = new FormData();
    formData.append('csrf', String(document.querySelector('input[name="csrf"]') ? document.querySelector('input[name="csrf"]').value : ''));
    formData.append('plan', JSON.stringify(_navLinkState.plan));
    formData.append('apply_fingerprint', _navLinkState.fingerprint);

    fetch('/apps/studio/nav-link/apply', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (applyBtn) { applyBtn.textContent = <?= json_encode($gs('nav_link_apply_btn')) ?>; }
      if (data.ok) {
        if (gateChip) { gateChip.textContent = <?= json_encode($gs('nav_link_success')) ?>; gateChip.className = 'status-chip gs-route-link-gate-chip badge-active'; }
        if (errorsNode) { errorsNode.hidden = true; }
        if (applyBtn) { applyBtn.disabled = true; }
        if (typeof window.renderSurfaceExposurePanel === 'function') {
          setTimeout(function () { window.renderSurfaceExposurePanel(); }, 300);
        }
      } else {
        var msg = String(data.message || data.code || <?= json_encode($gs('nav_link_error_failed')) ?>);
        if (errorsNode) { errorsNode.textContent = msg; errorsNode.hidden = false; }
        if (applyBtn) { applyBtn.disabled = false; }
      }
    })
    .catch(function () {
      if (applyBtn) { applyBtn.textContent = <?= json_encode($gs('nav_link_apply_btn')) ?>; applyBtn.disabled = false; }
      if (errorsNode) { errorsNode.textContent = <?= json_encode($gs('nav_link_error_failed')) ?>; errorsNode.hidden = false; }
    });
  }

  // Wire nav-link panel buttons
  document.addEventListener('DOMContentLoaded', function () {
    var analyzeBtn = document.getElementById('gs-nav-link-analyze-btn');
    var cancelBtn = document.getElementById('gs-nav-link-cancel-btn');
    var applyBtn = document.getElementById('gs-nav-link-apply-btn');
    if (analyzeBtn) { analyzeBtn.addEventListener('click', navLinkAnalyze); }
    if (cancelBtn) { cancelBtn.addEventListener('click', closeNavLinkPanel); }
    if (applyBtn) { applyBtn.addEventListener('click', navLinkApply); }
  });

  var updateEditorSurfaceHeader = window.updateEditorSurfaceHeader = function () {
    var titleNodes = document.querySelectorAll('[data-gs-editor-title]');
    var noteNodes = document.querySelectorAll('[data-gs-editor-note]');
    var contextNodes = document.querySelectorAll('[data-gs-loaded-context]');
    var editBadgeResourceTypeNodes = document.querySelectorAll('[data-gs-edit-badge-resource-type]');
    var editBadgeModeNodes = document.querySelectorAll('[data-gs-edit-badge-mode]');
    var editBadgeOwnerAppNodes = document.querySelectorAll('[data-gs-edit-badge-owner-app]');
    var editBadgeModuleNodes = document.querySelectorAll('[data-gs-edit-badge-module]');
    var identityPanels = document.querySelectorAll('[data-gs-loaded-identity-panel]');
    var workflowPanels = document.querySelectorAll('[data-gs-workflow-status-panel]');
    var modePanels = document.querySelectorAll('[data-gs-mode-panel]');
    if (!titleNodes.length && !noteNodes.length && !contextNodes.length && !editBadgeResourceTypeNodes.length && !editBadgeModeNodes.length && !editBadgeOwnerAppNodes.length && !editBadgeModuleNodes.length && !identityPanels.length && !workflowPanels.length && !modePanels.length) {
      return;
    }

    var inUpgrade = isLoadedContentMode();
    var contentType = inUpgrade ? getLoadedBundleContentType() : 'module';
    var loadedTypeLabels = {
      app: <?= json_encode($gs('loaded_type_app')) ?>,
      module: <?= json_encode($gs('loaded_type_module')) ?>,
      view: <?= json_encode($gs('loaded_type_view')) ?>,
      dashboard: <?= json_encode($gs('loaded_type_dashboard')) ?>,
      navigation: <?= json_encode($gs('loaded_type_navigation')) ?>,
      db_table: <?= json_encode($gs('loaded_type_db_table')) ?>,
      unknown: <?= json_encode($gs('loaded_type_unknown')) ?>
    };
    var loadedTypeName = String(loadedTypeLabels[contentType] || loadedTypeLabels.unknown);
    var bundle = readLoadedBundleObject();
    var itemName = resolveLoadedItemName(contentType, bundle);
    var labels = {
      titleCreate: <?= json_encode($gs('editor_title_create')) ?>,
      titleUpgrade: <?= json_encode($gs('editor_title_upgrade')) ?>,
      noteCreate: <?= json_encode($gs('editor_note_create')) ?>,
      noteUpgrade: <?= json_encode($gs('editor_note_upgrade')) ?>,
      badgeCreate: <?= json_encode($gs('editor_creating')) ?>,
      badgeUpgrade: <?= json_encode($gs('editor_editing')) ?>,
      noteUpgradeByType: {
        app: <?= json_encode($gs('editor_note_upgrade_app')) ?>,
        module: <?= json_encode($gs('editor_note_upgrade_module')) ?>,
        view: <?= json_encode($gs('editor_note_upgrade_view')) ?>,
        dashboard: <?= json_encode($gs('editor_note_upgrade_view')) ?>,
        navigation: <?= json_encode($gs('editor_note_upgrade_navigation')) ?>,
        db_table: <?= json_encode($gs('editor_note_upgrade_db_table')) ?>,
        unknown: <?= json_encode($gs('editor_note_upgrade_unknown')) ?>
      },
      loadedTypeLabel: <?= json_encode($gsBatch1('loaded_type_label')) ?>,
      contextLabel: <?= json_encode($gsBatch1('loaded_context_label')) ?>,
      contextSourceLabel: <?= json_encode($gsBatch1('loaded_context_source')) ?>,
      contextSourceLibrary: <?= json_encode($gsBatch1('loaded_context_source_library')) ?>,
      contextSourceImport: <?= json_encode($gsBatch1('loaded_context_source_import')) ?>,
      contextUnknown: <?= json_encode($gsBatch1('loaded_context_unknown')) ?>,
      appLabel: <?= json_encode($gsBatch1('loaded_owner_app_label')) ?>,
      moduleLabel: <?= json_encode($gsBatch1('loaded_module_label')) ?>,
      resourceTypeLabel: <?= json_encode($gsBatch1('loaded_resource_type_label')) ?>,
      modeLabel: <?= json_encode($gsBatch1('loaded_mode_label')) ?>,
      modeReadOnly: <?= json_encode($gsBatch1('loaded_mode_read_only')) ?>,
      modeEdit: <?= json_encode($gsBatch1('loaded_mode_edit')) ?>,
      modeCreate: <?= json_encode($gsBatch1('loaded_mode_create')) ?>,
      modeUpgrade: <?= json_encode($gsBatch1('loaded_mode_upgrade')) ?>,
      notLoaded: <?= json_encode($gs('not_loaded')) ?>,
      workflowLoadBeforeAnalysis: <?= json_encode($gsBatch1('workflow_state_load_before_analysis')) ?>,
      workflowNoDiff: <?= json_encode($gsBatch1('workflow_state_no_diff')) ?>,
      workflowNoPreview: <?= json_encode($gsBatch1('workflow_state_no_preview')) ?>,
      workflowNoApproval: <?= json_encode($gsBatch1('workflow_state_no_approval')) ?>,
      workflowNoApply: <?= json_encode($gsBatch1('workflow_state_no_apply')) ?>,
      workflowReadyReadonly: <?= json_encode($gsBatch1('workflow_state_ready_readonly')) ?>,
      workflowNoChangesStaged: <?= json_encode($gsBatch1('workflow_state_no_changes_staged')) ?>,
      workflowApplyInactive: <?= json_encode($gsBatch1('workflow_state_apply_inactive')) ?>,
      workbenchLoadEmpty: <?= json_encode($gs('content_outline_empty')) ?>,
      workbenchLoadReady: <?= json_encode($gsBatch1('workflow_state_ready_readonly')) ?>,
      modeStateActive: <?= json_encode($gsBatch1('mode_state_active')) ?>,
      modeStatePlanned: <?= json_encode($gsBatch1('mode_state_planned')) ?>,
      modeStateNotActive: <?= json_encode($gsBatch1('mode_state_not_active')) ?>,
      modeStateRequiresGovernance: <?= json_encode($gsBatch1('mode_state_requires_governance')) ?>,
      modeStateContextAvailable: <?= json_encode($gsBatch1('mode_state_context_available')) ?>
    };

    var upgradeNoteTemplate = String(labels.noteUpgradeByType[contentType] || labels.noteUpgrade);

    var titleText = inUpgrade
      ? applyTemplate(labels.titleUpgrade, { type: loadedTypeName })
      : String(labels.titleCreate || <?= json_encode($gs('form_title')) ?>);
    var noteText = inUpgrade
      ? applyTemplate(upgradeNoteTemplate, { type: loadedTypeName })
      : String(labels.noteCreate || <?= json_encode($gs('structured_editor_note')) ?>);

    if (itemName !== '') {
      noteText += ' ' + String(labels.loadedTypeLabel || 'Editing') + ': ' + itemName;
    }

    Array.prototype.forEach.call(titleNodes, function (node) {
      node.textContent = titleText;
    });
    Array.prototype.forEach.call(noteNodes, function (node) {
      node.textContent = noteText;
    });

    var loadedContext = readLoadedArtifactContext();
    var contextText = '';
    if (inUpgrade) {
      var contextParts = [];
      contextParts.push(String(labels.contextLabel || 'Loaded Context'));
      contextParts.push(String(labels.resourceTypeLabel || 'Resource Type') + ': ' + loadedTypeName);
      contextParts.push(String(labels.modeLabel || 'Mode') + ': ' + String(labels.modeUpgrade || labels.modeEdit || 'Edit'));

      var ownerApp = loadedContext && loadedContext.ownerAppKey ? String(loadedContext.ownerAppKey) : '';
      var ownerModule = loadedContext && loadedContext.ownerModuleKey ? String(loadedContext.ownerModuleKey) : '';
      if (ownerApp !== '') {
        contextParts.push(String(labels.appLabel || 'App') + ': ' + ownerApp);
      }
      if (ownerModule !== '') {
        contextParts.push(String(labels.moduleLabel || 'Module') + ': ' + ownerModule);
      }
      if (loadedContext && loadedContext.sourceType) {
        var sourceText = loadedContext.sourceType === 'import'
          ? String(labels.contextSourceImport || labels.contextUnknown || 'Unknown')
          : String(labels.contextSourceLibrary || labels.contextUnknown || 'Unknown');
        contextParts.push(String(labels.contextSourceLabel || 'Source') + ': ' + sourceText);
      }
      contextText = contextParts.join(' · ');
    } else {
      contextText = String(labels.contextLabel || 'Loaded Context')
        + ' · '
        + String(labels.modeLabel || 'Mode')
        + ': '
        + String(labels.modeReadOnly || 'Read-only');
    }

    Array.prototype.forEach.call(contextNodes, function (node) {
      if (!node) {
        return;
      }
      var visible = String(contextText || '').trim() !== '';
      node.hidden = !visible;
      node.textContent = visible ? contextText : '';
    });

    var ownerAppValue = loadedContext && loadedContext.ownerAppKey ? String(loadedContext.ownerAppKey) : '';
    var ownerModuleValue = loadedContext && loadedContext.ownerModuleKey ? String(loadedContext.ownerModuleKey) : '';
    var resourceTypeValue = inUpgrade ? loadedTypeName : '';
    var resourceKeyValue = inUpgrade
      ? String(itemName || (loadedContext && loadedContext.nodeId ? loadedContext.nodeId : '') || '')
      : '';
    var modeValue = inUpgrade
      ? String(labels.modeUpgrade || labels.modeEdit || '')
      : String(labels.modeReadOnly || '');
    var sourcePathValue = loadedContext && typeof loadedContext.sourcePath === 'string'
      ? String(loadedContext.sourcePath || '').trim()
      : '';
    var fallbackNotLoaded = String(labels.notLoaded || 'Not loaded');

    if (typeof window.gsSyncEditWorkbenchState === 'function') {
      window.gsSyncEditWorkbenchState({
        inUpgrade: inUpgrade,
        itemName: itemName,
        values: {
          ownerAppValue: ownerAppValue,
          ownerModuleValue: ownerModuleValue,
          resourceTypeValue: resourceTypeValue,
          resourceKeyValue: resourceKeyValue,
          modeValue: modeValue
        },
        strings: {
          notLoaded: String(labels.notLoaded || 'Not loaded'),
          modeReadOnly: String(labels.modeReadOnly || 'Read-only'),
          workbenchLoadEmpty: String(labels.workbenchLoadEmpty || ''),
          workbenchLoadReady: String(labels.workbenchLoadReady || '')
        }
      });
    }

    if (typeof window.gsSyncLoadedResourceIdentity === 'function') {
      window.gsSyncLoadedResourceIdentity({
        inUpgrade: inUpgrade,
        values: {
          ownerAppValue: ownerAppValue,
          ownerModuleValue: ownerModuleValue,
          resourceTypeValue: resourceTypeValue,
          resourceKeyValue: resourceKeyValue,
          modeValue: modeValue,
          sourcePathValue: sourcePathValue
        },
        strings: {
          notLoaded: fallbackNotLoaded
        }
      });
    }

    if (typeof window.gsSyncWorkflowModePanels === 'function') {
      window.gsSyncWorkflowModePanels({
        inUpgrade: inUpgrade,
        strings: {
          workflowLoadBeforeAnalysis: String(labels.workflowLoadBeforeAnalysis || 'Load a resource before analysis.'),
          workflowNoDiff: String(labels.workflowNoDiff || 'No diff is available.'),
          workflowNoPreview: String(labels.workflowNoPreview || 'No preview is active.'),
          workflowNoApproval: String(labels.workflowNoApproval || 'No approval is pending.'),
          workflowNoApply: String(labels.workflowNoApply || 'No apply action is active.'),
          workflowReadyReadonly: String(labels.workflowReadyReadonly || 'Ready for read-only inspection'),
          workflowNoChangesStaged: String(labels.workflowNoChangesStaged || 'No changes staged'),
          workflowApplyInactive: String(labels.workflowApplyInactive || 'Apply is inactive'),
          modeStateActive: String(labels.modeStateActive || 'Active'),
          modeStatePlanned: String(labels.modeStatePlanned || 'Planned'),
          modeStateNotActive: String(labels.modeStateNotActive || 'Not active'),
          modeStateRequiresGovernance: String(labels.modeStateRequiresGovernance || 'Requires governed workflow'),
          modeStateContextAvailable: String(labels.modeStateContextAvailable || 'Context available'),
          badgeUpgrade: String(labels.badgeUpgrade || ''),
          badgeCreate: String(labels.badgeCreate || '')
        }
      });
    }

    // Update breadcrumb with current content context
    var breadcrumbEl = document.querySelector('.studio-header .breadcrumb');
    if (breadcrumbEl) {
      var storedBaseLabel = String(breadcrumbEl.getAttribute('data-base-label') || '').trim();
      if (storedBaseLabel === '') {
        storedBaseLabel = String(breadcrumbEl.textContent || '').split(' › ')[0].trim() || 'Studio';
        breadcrumbEl.setAttribute('data-base-label', storedBaseLabel);
      }
      var breadcrumbBaseLabel = storedBaseLabel;
      if (inUpgrade && itemName !== '') {
        breadcrumbEl.textContent = breadcrumbBaseLabel + ' › ' + loadedTypeName + ' › ' + itemName;
      } else if (inUpgrade) {
        breadcrumbEl.textContent = breadcrumbBaseLabel + ' › ' + loadedTypeName;
      } else {
        breadcrumbEl.textContent = breadcrumbBaseLabel;
      }
    }
  };

  function setScopedControlsEnabled(root, enabled) {
    Array.prototype.forEach.call(root.querySelectorAll('input, select, textarea, button'), function (control) {
      if (!control) {
        return;
      }
      if (enabled) {
        if (control.getAttribute('data-flow-disabled') === '1') {
          control.disabled = false;
          control.removeAttribute('data-flow-disabled');
        }
        return;
      }
      if (!control.disabled) {
        control.disabled = true;
        control.setAttribute('data-flow-disabled', '1');
      }
    });
  }

  function updateFlowScopedControls(mode) {
    var normalized = mode === 'edit_existing' ? 'edit_existing' : 'create_new';
    var shell = document.querySelector('.studio-shell');
    if (shell) {
      shell.setAttribute('data-studio-flow', normalized === 'edit_existing' ? 'upgrade' : 'create');
    }
    Array.prototype.forEach.call(document.querySelectorAll('[data-flow-scope]'), function (node) {
      var scope = String(node.getAttribute('data-flow-scope') || '');
      var visible = scope === 'shared'
        || (scope === 'upgrade' && normalized === 'edit_existing')
        || (scope === 'create' && normalized !== 'edit_existing');
      node.hidden = !visible;
      setScopedControlsEnabled(node, visible);
    });
  }

  function setStudioMode(mode, options) {
    var modeOptions = options && typeof options === 'object' ? options : {};
    var preserveUpgradeBaseline = !!modeOptions.preserveUpgradeBaseline;
    var wantsUpgrade = mode === 'edit_existing';
    var normalized = wantsUpgrade && hasLoadedBundleForUpgrade() ? 'edit_existing' : 'create_new';
    if (normalized !== 'edit_existing') {
      if (!preserveUpgradeBaseline) {
        clearUpgradeBaselineState();
      }
      // Clear form inputs populated by a previously loaded upgrade bundle
      var createModeResetIds = ['gs_se_app_key', 'gs_se_app_display_name', 'gs_se_module_key', 'gs_se_module_display_name', 'gs_se_route_path'];
      createModeResetIds.forEach(function (resetId) {
        var el = document.getElementById(resetId);
        if (el) { el.value = ''; }
      });
    }
    Array.prototype.forEach.call(document.querySelectorAll('input[name="studio_mode"]'), function (input) {
      if (input) {
        input.value = normalized;
      }
    });

    var modeChip = document.getElementById('gs-studio-mode-chip');
    if (modeChip) {
      modeChip.textContent = normalized === 'edit_existing' ? studioModeLabels.upgrade : studioModeLabels.create;
    }

    Array.prototype.forEach.call(document.querySelectorAll('.gs-create-intent-control'), function (node) {
      if (node) {
        node.hidden = normalized === 'edit_existing';
      }
    });
    updateFlowScopedControls(normalized);
    updateEditorSurfaceHeader();

    if (typeof window.renderContentOutline === 'function') {
      window.renderContentOutline();
    }
    if (typeof window.renderCreateFlowGuide === 'function') {
      window.renderCreateFlowGuide();
    }
    if (typeof window.renderSurfaceExposurePanel === 'function') {
      window.renderSurfaceExposurePanel();
    }
    document.dispatchEvent(new window.CustomEvent('studio-mode-changed', { detail: { mode: normalized } }));
  }
  window.gsSetStudioMode = setStudioMode;

  function refreshStudioModeSwitch() {
    var loaded = isLoadedContentMode();
    var canUpgrade = hasLoadedBundleForUpgrade();
    updateFlowScopedControls(loaded ? 'edit_existing' : 'create_new');
    if (switchCreateButton) {
      switchCreateButton.classList.toggle('active', !loaded);
    }
    if (switchUpgradeButton) {
      switchUpgradeButton.classList.toggle('active', loaded);
      switchUpgradeButton.disabled = !canUpgrade;
    }
  }

  function scrollToEditorTarget(selector, focusSelector) {
    if (!selector) {
      return;
    }
    var target = document.querySelector(selector);
    if (!target) {
      return;
    }
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (focusSelector) {
      var focusTarget = target.querySelector(focusSelector);
      if (focusTarget && typeof focusTarget.focus === 'function') {
        window.setTimeout(function () {
          focusTarget.focus();
        }, 50);
      }
    }
  }

  function focusFieldRow(fieldKey) {
    if (!fieldKey) {
      return;
    }
    var selector = '#gs-fields-body tr[data-field-key="' + String(fieldKey).replace(/"/g, '\\"') + '"]';
    var row = document.querySelector(selector);
    if (!row) {
      scrollToEditorTarget('#gs-fields-table');
      return;
    }
    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    var input = row.querySelector('input');
    if (input && typeof input.focus === 'function') {
      window.setTimeout(function () {
        input.focus();
      }, 50);
    }
  }

  function appendOutlineGroup(container, title, items) {
    if (!container) {
      return;
    }

    var group = document.createElement('div');
    group.className = 'library-outline-group';

    var groupTitle = document.createElement('div');
    groupTitle.className = 'library-outline-group-title';
    groupTitle.textContent = title;
    group.appendChild(groupTitle);

    var list = document.createElement('ul');
    list.className = 'library-outline-list';

    if (!Array.isArray(items) || items.length === 0) {
      var empty = document.createElement('li');
      empty.className = 'library-recent-empty';
      empty.textContent = contentOutlineLabels.empty;
      list.appendChild(empty);
    } else {
      items.forEach(function (item) {
        if (!item || typeof item !== 'object') {
          return;
        }
        var li = document.createElement('li');
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn';
        button.textContent = String(item.label || item.id || '');
        if (item.active) {
          button.classList.add('is-selected');
        }
        button.addEventListener('click', function () {
          if (typeof item.onClick === 'function') {
            item.onClick();
          }
        });
        li.appendChild(button);
        list.appendChild(li);
      });
    }

    group.appendChild(list);
    container.appendChild(group);
  }

  /**
   * Build a comprehensive, type-aware outline data structure
   * from the current studio state. Returns { sections, summary }
   * where sections is an array of { title, items } groups.
   */
  function buildDynamicContentOutlineData() {
    var loaded = isLoadedContentMode();
    var labels = window.gsContentOutlineLabels || {};
    var builder = window.gsBuilderState || { layout: { items: [] }, selectedItemId: '' };
    var structured = window.gsStructuredState || { fields: [] };
    var committed = window.gsStudioState || {};

    var sections = [];
    var summary = '';

    if (!loaded) {
      return { sections: [], summary: String(labels.empty || 'Load content to see outline') };
    }

    var contentType = typeof window.getLoadedBundleContentType === 'function'
      ? window.getLoadedBundleContentType()
      : 'unknown';

    var loadedTypeLabels = {
      app:        <?= json_encode($gs('loaded_type_app')) ?>,
      module:     <?= json_encode($gs('loaded_type_module')) ?>,
      view:       <?= json_encode($gs('loaded_type_view')) ?>,
      dashboard:  <?= json_encode($gs('loaded_type_dashboard')) ?>,
      navigation: <?= json_encode($gs('loaded_type_navigation')) ?>,
      db_table:   <?= json_encode($gs('loaded_type_db_table')) ?>,
      unknown:    <?= json_encode($gs('loaded_type_unknown')) ?>
    };

    function readVisibleValue(selector, fallback) {
      var candidates = Array.prototype.slice.call(document.querySelectorAll(selector));
      var visible = candidates.find(function (node) {
        return !!(node && !node.hidden && node.offsetParent !== null);
      });
      var active = visible || candidates[0] || null;
      return String((active && active.value) || fallback || '');
    }

    var loadedTypeName = String(loadedTypeLabels[contentType] || contentType);
    var moduleDisplayName = readVisibleValue('#gs_se_module_display_name', '').trim();
    var moduleKey = readVisibleValue('#gs_se_module_key', '').trim();
    var moduleName = String(moduleDisplayName || moduleKey || '').trim();
    var viewKind = readVisibleValue('#gs_se_view_kind', 'table').trim();
    var viewKindDisplay = displayViewKindLabel(viewKind);
    var tableEditMode = readVisibleValue('#gs_se_table_edit_mode', 'view_only').toLowerCase();
    var isTableView = viewKind.toLowerCase() === 'table';
    var fieldList = Array.isArray(structured.fields) ? structured.fields : [];
    var componentList = builder.layout && Array.isArray(builder.layout.items) ? builder.layout.items : [];
    var selectedItemId = String(builder.selectedItemId || '');
    var isViewLikeType = contentType === 'view' || contentType === 'db_table' || contentType === 'dashboard';
    var shouldShowFieldCount = (contentType === 'view' || contentType === 'db_table') && viewKind.toLowerCase() !== 'dashboard';
    var shouldShowComponentCount = isViewLikeType;
    var shouldShowTableMode = contentType === 'db_table' || (contentType === 'view' && isTableView);

    // Compile summary
    summary = [
      <?= json_encode($gsBatch1('loaded_type_label')) ?> + ': ' + loadedTypeName,
      (moduleName ? (String(labels.summary || 'Current') + ': ' + moduleName) : ''),
      (shouldShowFieldCount && fieldList.length > 0) ? (String(labels.fields || 'Fields') + ': ' + String(fieldList.length)) : '',
      (shouldShowComponentCount && componentList.length > 0) ? (String(labels.components || 'Components') + ': ' + String(componentList.length)) : '',
      (shouldShowTableMode) ? (String(labels.tableMode || 'Table mode') + ': ' + (tableEditMode === 'direct_db' ? 'Direct DB' : 'View-driven')) : ''
    ].filter(function (part) {
      return String(part || '').trim() !== '';
    }).join(' · ');

    // Build type-specific outline sections
    if (contentType === 'view' || contentType === 'db_table' || contentType === 'dashboard') {
      // VIEW/TABLE: Settings, Fields, Components, Layout, Governance
      sections.push({
        title: String(labels.moduleSettings || 'Settings'),
        items: [
          {
            label: moduleName || String(labels.moduleSettings || ''),
            onClick: function () {
              var target = document.querySelector('#gs-structured-editor-form');
              if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var input = target.querySelector('#gs_se_module_key');
                if (input && typeof input.focus === 'function') {
                  window.setTimeout(function () { input.focus(); }, 50);
                }
              }
            }
          },
          {
            label: String(createFlowLabels.view || 'View type') + ': ' + viewKindDisplay,
            onClick: function () {
              var target = document.querySelector('#gs-structured-editor-form');
              if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var input = target.querySelector('#gs_se_view_kind');
                if (input && typeof input.focus === 'function') {
                  window.setTimeout(function () { input.focus(); }, 50);
                }
              }
            }
          },
          ((contentType === 'view' || contentType === 'db_table') && isTableView ? {
            label: String(labels.tableMode || 'Edit mode') + ': ' + (tableEditMode === 'direct_db' ? 'Direct DB' : 'View-driven'),
            onClick: function () {
              var target = document.querySelector('#gs-structured-editor-form');
              if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var input = target.querySelector('#gs_se_table_edit_mode');
                if (input && typeof input.focus === 'function') {
                  window.setTimeout(function () { input.focus(); }, 50);
                }
              }
            }
          } : null)
        ].filter(Boolean)
      });

      // Fields section (only if not dashboard)
      if ((contentType === 'view' || contentType === 'db_table') && viewKind.toLowerCase() !== 'dashboard' && fieldList.length > 0) {
        sections.push({
          title: String(labels.fields || 'Fields'),
          items: fieldList.filter(function (field) {
            return String((field && field.key) || '').trim() !== '';
          }).map(function (field) {
            return {
              label: String(field.key || ''),
              onClick: function () {
                var escapedKey = String(field.key || '').replace(/"/g, '\\"');
                var row = document.querySelector('#gs-fields-body tr[data-field-key="' + escapedKey + '"]');
                if (row) {
                  row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                  var input = row.querySelector('input');
                  if (input && typeof input.focus === 'function') {
                    window.setTimeout(function () { input.focus(); }, 50);
                  }
                }
              }
            };
          })
        });
      }

      // Components section
      if (componentList.length > 0) {
        sections.push({
          title: String(labels.components || 'Components'),
          items: componentList.map(function (item) {
            return {
              label: String(item.component || 'item') + ' (' + String(item.id || '') + ')',
              isActive: selectedItemId === String(item.id || ''),
              onClick: function () {
                var canvasItem = document.querySelector('#gs-layout-canvas [data-item-id="' + String(item.id || '').replace(/"/g, '\\"') + '"]');
                if (canvasItem && typeof canvasItem.click === 'function') {
                  canvasItem.click();
                }
                var target = document.querySelector('#gs-component-config');
                if (target) {
                  target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
              }
            };
          })
        });
      }

      // Layout section
      sections.push({
        title: String(labels.layout || 'Layout'),
        items: [
          {
            label: String(labels.layout || 'Visual builder'),
            onClick: function () {
              var target = document.querySelector('#gs-visual-builder');
              if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
              }
            }
          }
        ]
      });

    } else if (contentType === 'navigation') {
      // NAVIGATION: Settings, Navigation Items, Governance
      var navSection = readVisibleValue('#gs_se_nav_section', '').trim();
      var navGroup = readVisibleValue('#gs_se_nav_group', '').trim();
      var navLabel = readVisibleValue('#gs_se_nav_label', '').trim();
      var navTarget = readVisibleValue('#gs_se_nav_target', '').trim();
      var navIcon = readVisibleValue('#gs_se_nav_icon', '').trim();
      var navOrder = readVisibleValue('#gs_se_nav_order', '').trim();

      sections.push({
        title: String(labels.moduleSettings || 'Navigation Settings'),
        items: [
          (navSection ? {
            label: <?= json_encode($gs('navigation_section')) ?> + ': ' + navSection,
            onClick: function () {
              var target = document.querySelector('#gs-structured-editor-form');
              if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var input = target.querySelector('#gs_se_nav_section');
                if (input && typeof input.focus === 'function') {
                  window.setTimeout(function () { input.focus(); }, 50);
                }
              }
            }
          } : null),
          (navGroup ? {
            label: <?= json_encode($gs('navigation_group')) ?> + ': ' + navGroup,
            onClick: function () {
              var target = document.querySelector('#gs-structured-editor-form');
              if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var input = target.querySelector('#gs_se_nav_group');
                if (input && typeof input.focus === 'function') {
                  window.setTimeout(function () { input.focus(); }, 50);
                }
              }
            }
          } : null),
          (navLabel ? {
            label: <?= json_encode($gs('navigation_label')) ?> + ': ' + navLabel,
            onClick: function () {
              var target = document.querySelector('#gs-structured-editor-form');
              if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var input = target.querySelector('#gs_se_nav_label');
                if (input && typeof input.focus === 'function') {
                  window.setTimeout(function () { input.focus(); }, 50);
                }
              }
            }
          } : null),
          (navTarget ? {
            label: <?= json_encode($gs('navigation_target')) ?> + ': ' + navTarget,
            onClick: function () {
              var target = document.querySelector('#gs-structured-editor-form');
              if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var input = target.querySelector('#gs_se_nav_target');
                if (input && typeof input.focus === 'function') {
                  window.setTimeout(function () { input.focus(); }, 50);
                }
              }
            }
          } : null),
          (navIcon ? {
            label: <?= json_encode($gs('navigation_icon')) ?> + ': ' + navIcon,
            onClick: function () {
              var target = document.querySelector('#gs-structured-editor-form');
              if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var input = target.querySelector('#gs_se_nav_icon');
                if (input && typeof input.focus === 'function') {
                  window.setTimeout(function () { input.focus(); }, 50);
                }
              }
            }
          } : null),
          (navOrder ? {
            label: <?= json_encode($gs('navigation_order')) ?> + ': ' + navOrder,
            onClick: function () {
              var target = document.querySelector('#gs-structured-editor-form');
              if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var input = target.querySelector('#gs_se_nav_order');
                if (input && typeof input.focus === 'function') {
                  window.setTimeout(function () { input.focus(); }, 50);
                }
              }
            }
          } : null)
        ].filter(Boolean)
      });

    } else if (contentType === 'module') {
      sections.push({
        title: String(labels.moduleSettings || 'Settings'),
        items: [
          {
            label: moduleName || String(labels.moduleSettings || ''),
            onClick: function () {
              var target = document.querySelector('#gs-structured-editor-form');
              if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var input = target.querySelector('#gs_se_module_key');
                if (input && typeof input.focus === 'function') {
                  window.setTimeout(function () { input.focus(); }, 50);
                }
              }
            }
          }
        ]
      });
    } else if (contentType === 'app') {
      var appKey = readVisibleValue('#gs_se_app_key', '').trim();
      var appName = readVisibleValue('#gs_se_app_display_name', '').trim();
      var appDisplay = appName || appKey;
      sections.push({
        title: String(labels.moduleSettings || 'Settings'),
        items: [
          {
            label: appDisplay || String(labels.moduleSettings || ''),
            onClick: function () {
              var target = document.querySelector('#gs-structured-editor-form');
              if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var input = target.querySelector('#gs_se_app_key');
                if (input && typeof input.focus === 'function') {
                  window.setTimeout(function () { input.focus(); }, 50);
                }
              }
            }
          }
        ]
      });
    }

    // Governance section (common to all content types)
    sections.push({
      title: String(labels.governance || 'Governance'),
      items: [
        {
          label: String(labels.changeSummary || 'Changes'),
          onClick: function () {
            var target = document.querySelector('#gs-change-summary');
            if (target) {
              target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          }
        },
        {
          label: String(labels.migrationPlan || 'Migration plan'),
          onClick: function () {
            var target = document.querySelector('#gs-migration-plan');
            if (target) {
              target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          }
        },
        {
          label: String(labels.impactAnalysis || 'Impact analysis'),
          onClick: function () {
            var target = document.querySelector('#gs-impact-analysis');
            if (target) {
              target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          }
        },
        {
          label: String(labels.simulationPreview || 'Simulation'),
          onClick: function () {
            var target = document.querySelector('#gs-simulation-preview');
            if (target) {
              target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          }
        }
      ]
    });

    return { sections: sections, summary: summary };
  }

  var renderContentOutline = window.renderContentOutline = function () {
    var contentOutlinePanelNode = document.getElementById('gs-content-outline');
    var contentOutlineSummaryNode = document.getElementById('gs-content-outline-summary');
    var contentOutlineBodyNode = document.getElementById('gs-content-outline-body');
    if (!contentOutlinePanelNode || !contentOutlineBodyNode || !contentOutlineSummaryNode) {
      return;
    }

    var labels = window.gsContentOutlineLabels || {};
    var loaded = isLoadedContentMode();

    // Use the new dynamic outline data builder
    var outlineData = buildDynamicContentOutlineData();
    var sections = outlineData.sections || [];
    var summary = outlineData.summary || '';

    // Update panel visibility and summary
    contentOutlinePanelNode.classList.toggle('is-hidden', !loaded);
    contentOutlineBodyNode.innerHTML = '';
    contentOutlineSummaryNode.textContent = String(summary || labels.empty || '');

    if (!loaded) {
      return;
    }

    // Render each section with its items
    sections.forEach(function (section) {
      if (!section || typeof section !== 'object') {
        return;
      }

      var group = document.createElement('div');
      group.className = 'library-outline-group';

      var groupTitle = document.createElement('div');
      groupTitle.className = 'library-outline-group-title';
      groupTitle.textContent = String(section.title || '');
      group.appendChild(groupTitle);

      var list = document.createElement('ul');
      list.className = 'library-outline-list';

      if (!Array.isArray(section.items) || section.items.length === 0) {
        var empty = document.createElement('li');
        empty.className = 'library-recent-empty';
        empty.textContent = String(labels.empty || 'No items');
        list.appendChild(empty);
      } else {
        section.items.forEach(function (item) {
          if (!item || typeof item !== 'object') {
            return;
          }
          var li = document.createElement('li');
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'btn';
          button.textContent = String(item.label || '');
          if (item.isActive) {
            button.classList.add('is-selected');
          }
          button.addEventListener('click', function () {
            if (typeof item.onClick === 'function') {
              item.onClick();
            }
          });
          li.appendChild(button);
          list.appendChild(li);
        });
      }

      group.appendChild(list);
      contentOutlineBodyNode.appendChild(group);
    })
  };

  var renderCreateFlowGuide = window.renderCreateFlowGuide = function () {
    if (!createFlowPanel || !createFlowBody || !createFlowSummary) {
      return;
    }

    var loaded = isLoadedContentMode();
    createFlowPanel.classList.toggle('is-hidden', loaded);
    createFlowBody.innerHTML = '';

    if (loaded) {
      createFlowSummary.textContent = String(createFlowLabels.summary || '');
      return;
    }

    function readVisibleValue(selector, fallback) {
      var candidates = Array.prototype.slice.call(document.querySelectorAll(selector));
      var visible = candidates.find(function (node) {
        return !!(node && !node.hidden && node.offsetParent !== null);
      });
      var active = visible || candidates[0] || null;
      return String((active && active.value) || fallback || '');
    }

    var structured = window.gsStructuredState || { fields: [] };
    var builder = window.gsBuilderState || { layout: { items: [] } };
    var outlineLabels = window.gsContentOutlineLabels
      || (typeof contentOutlineLabels !== 'undefined' ? contentOutlineLabels : {});
    var createIntent = readVisibleValue('#gs_se_create_intent', 'create_view').trim();
    var normalizedCreateIntent = String(createIntent || 'create_view').toLowerCase();
    var createIntentLabels = {
      create_app: String(createFlowLabels.intentCreateApp || ''),
      create_module: String(createFlowLabels.intentCreateModule || ''),
      create_view: String(createFlowLabels.intentCreateView || ''),
      create_navigation: String(createFlowLabels.intentCreateNavigation || ''),
      create_dashboard: String(createFlowLabels.intentCreateDashboard || '')
    };
    if (!Object.prototype.hasOwnProperty.call(createIntentLabels, normalizedCreateIntent)) {
      normalizedCreateIntent = 'create_view';
    }
    var createIntentDisplay = String(createIntentLabels[normalizedCreateIntent] || createIntentLabels.create_view);
    var moduleKey = readVisibleValue('#gs_se_module_key', '').trim();
    var moduleDisplayName = readVisibleValue('#gs_se_module_display_name', '').trim();
    var moduleDisplay = moduleDisplayName || moduleKey;
    var viewKind = readVisibleValue('#gs_se_view_kind', 'table').trim();
    var normalizedViewKind = viewKind.toLowerCase();
    var tableEditMode = readVisibleValue('#gs_se_table_edit_mode', 'view_only').trim();
    var fieldList = Array.isArray(structured.fields) ? structured.fields : [];
    var componentList = builder.layout && Array.isArray(builder.layout.items) ? builder.layout.items : [];
    var isAppIntent = normalizedCreateIntent === 'create_app';
    var isModuleIntent = normalizedCreateIntent === 'create_module';
    var isNavigationIntent = normalizedCreateIntent === 'create_navigation';
    var isDashboardIntent = normalizedCreateIntent === 'create_dashboard';
    if (isDashboardIntent && normalizedViewKind !== 'dashboard') {
      Array.prototype.forEach.call(document.querySelectorAll('#gs_se_view_kind'), function (node) {
        if (node) {
          node.value = 'dashboard';
        }
      });
      viewKind = 'dashboard';
      normalizedViewKind = 'dashboard';
    }
    var viewKindDisplay = displayViewKindLabel(viewKind);
    var navSection = readVisibleValue('#gs_se_nav_section', 'Apps').trim();
    var navLabel = readVisibleValue('#gs_se_nav_label', '').trim();
    var navTarget = readVisibleValue('#gs_se_nav_target', '').trim();
    var requiresModule = !isNavigationIntent && !isAppIntent;
    var requiresNavigation = isNavigationIntent;
    var requiresView = !isNavigationIntent && !isDashboardIntent && !isAppIntent && !isModuleIntent;
    var isTableView = requiresView && !isDashboardIntent && normalizedViewKind === 'table';
    var requiresTableMode = isTableView;
    var requiresFields = requiresView && !isDashboardIntent && normalizedViewKind !== 'dashboard';
    var requiresLayout = !isNavigationIntent && !isAppIntent && !isModuleIntent;
    var tableModeSummaryLabel = tableEditMode === 'direct_db'
      ? String(outlineLabels.tableModeDirectDb || createFlowLabels.actionDirectDb || 'Direct DB edits')
      : String(outlineLabels.tableModeViewOnly || createFlowLabels.actionViewMode || 'View-driven edits');

    var moduleReady = !requiresModule || moduleKey !== '';
    var navigationReady = !requiresNavigation || (navSection !== '' && navLabel !== '' && navTarget !== '');
    var viewReady = !requiresView || viewKind !== '';
    var tableModeReady = !requiresTableMode || tableEditMode !== '';
    var fieldsReady = !requiresFields || fieldList.length > 0;
    var layoutReady = !requiresLayout || componentList.length > 0;
    var governanceReady = moduleReady && navigationReady && viewReady && tableModeReady && fieldsReady && layoutReady;
    var activeStep = 'intent';
    if (requiresModule && !moduleReady) {
      activeStep = 'module';
    } else if (requiresNavigation && !navigationReady) {
      activeStep = 'navigation';
    } else if (requiresView && !viewReady) {
      activeStep = 'view';
    } else if (requiresTableMode && !tableModeReady) {
      activeStep = 'table_mode';
    } else if (requiresFields && !fieldsReady) {
      activeStep = 'fields';
    } else if (requiresLayout && !layoutReady) {
      activeStep = 'layout';
    } else if (governanceReady) {
      activeStep = 'governance';
    }

    var blockingStepLabels = {
      intent: createFlowLabels.stepIntent,
      module: createFlowLabels.module,
      navigation: createFlowLabels.actionConfigureNavigation,
      view: createFlowLabels.view,
      table_mode: createFlowLabels.tableMode,
      fields: createFlowLabels.fields,
      layout: createFlowLabels.layout,
      governance: createFlowLabels.governance
    };
    var blockingStepLabel = String(blockingStepLabels[activeStep] || blockingStepLabels.intent || '');
    var summaryParts = activeStep !== 'governance' ? ['→ ' + blockingStepLabel] : [];

    createFlowSummary.textContent = (summaryParts.concat([
      String(createFlowLabels.intent || 'Creation target') + ': ' + createIntentDisplay,
      requiresModule ? (String(outlineLabels.moduleSettings || 'Module settings') + ': ' + (moduleDisplay || String(pendingLabel || '-'))) : '',
      requiresNavigation ? (String(createFlowLabels.actionConfigureNavigation || 'Configure navigation') + ': ' + (navLabel || String(pendingLabel || '-'))) : '',
      requiresNavigation ? (String(<?= json_encode($gs('navigation_target')) ?>) + ': ' + (navTarget || String(pendingLabel || '-'))) : '',
      requiresView ? (String(createFlowLabels.view || 'Choose view type') + ': ' + (viewKindDisplay || displayViewKindLabel('table'))) : '',
      isTableView ? (String(outlineLabels.tableMode || 'Table edit mode') + ': ' + tableModeSummaryLabel) : '',
      requiresFields ? (String(outlineLabels.fields || 'Fields') + ': ' + String(fieldList.length)) : '',
      requiresLayout ? (String(outlineLabels.components || 'Components') + ': ' + String(componentList.length)) : ''
    ])).filter(function (part) {
      return String(part || '').trim() !== '';
    }).join(' · ');

    appendOutlineGroup(createFlowBody, createFlowLabels.steps, [
      {
        label: createFlowLabels.stepIntent,
        active: activeStep === 'intent',
        onClick: function () { scrollToEditorTarget('#gs-structured-editor-form', '#gs_se_create_intent'); }
      },
      {
        label: createFlowLabels.module,
        active: activeStep === 'module',
        onClick: function () { scrollToEditorTarget('#gs-structured-editor-form', '#gs_se_module_key'); }
      },
      {
        label: createFlowLabels.actionConfigureNavigation,
        active: activeStep === 'navigation',
        onClick: function () { scrollToEditorTarget('#gs-structured-editor-form', '#gs_se_nav_section'); }
      },
      {
        label: createFlowLabels.view,
        active: activeStep === 'view',
        onClick: function () { scrollToEditorTarget('#gs-structured-editor-form', '#gs_se_view_kind'); }
      },
      {
        label: createFlowLabels.tableMode,
        active: requiresTableMode && activeStep === 'table_mode',
        onClick: function () { scrollToEditorTarget('#gs-structured-editor-form', '#gs_se_table_edit_mode'); }
      },
      {
        label: createFlowLabels.fields,
        active: requiresFields && activeStep === 'fields',
        onClick: function () { scrollToEditorTarget('#gs-fields-table'); }
      },
      {
        label: createFlowLabels.layout,
        active: activeStep === 'layout',
        onClick: function () { scrollToEditorTarget('#gs-visual-builder'); }
      },
      {
        label: createFlowLabels.governance,
        active: activeStep === 'governance',
        onClick: function () { scrollToEditorTarget('#gs-change-summary'); }
      }
    ].filter(function (item) {
      if (!item) return false;
      if (item.label === createFlowLabels.module) {
        return requiresModule;
      }
      if (item.label === createFlowLabels.actionConfigureNavigation) {
        return requiresNavigation;
      }
      if (item.label === createFlowLabels.view) {
        return requiresView;
      }
      if (item.label === createFlowLabels.tableMode) {
        return requiresTableMode;
      }
      if (item.label === createFlowLabels.fields) {
        return requiresFields;
      }
      if (item.label === createFlowLabels.layout) {
        return requiresLayout;
      }
      return true;
    }));

    function setVisibleControlValue(selector, value) {
      var candidates = Array.prototype.slice.call(document.querySelectorAll(selector));
      var visible = candidates.find(function (node) {
        return !!(node && !node.hidden && node.offsetParent !== null);
      });
      var active = visible || candidates[0] || null;
      if (!active || candidates.length === 0) {
        return;
      }
      var normalized = String(value || '');
      candidates.forEach(function (node) {
        if (node) {
          node.value = normalized;
        }
      });
      active.value = normalized;
      active.dispatchEvent(new window.Event('change', { bubbles: true }));
    }

    function applyCreateAction(type) {
      function scrollToDashboardTarget() {
        var builderTarget = document.querySelector('#gs-visual-builder');
        if (builderTarget && !builderTarget.hidden && builderTarget.offsetParent !== null) {
          scrollToEditorTarget('#gs-visual-builder');
          return;
        }
        scrollToEditorTarget('#gs-structured-editor-form', '#gs_se_create_intent');
      }

      var actionType = String(type || '').toLowerCase();
      if (actionType === 'navigation') {
        scrollToEditorTarget('#gs-structured-editor-form', '#gs_se_nav_section');
        return;
      }
      if (actionType === 'set_dashboard') {
        setVisibleControlValue('#gs_se_create_intent', 'create_dashboard');
        setVisibleControlValue('#gs_se_view_kind', 'dashboard');
        scrollToDashboardTarget();
        return;
      }
      if (actionType === 'table') {
        if (normalizedCreateIntent === 'create_dashboard') {
          setVisibleControlValue('#gs_se_create_intent', 'create_view');
        }
        setVisibleControlValue('#gs_se_view_kind', 'table');
        if (normalizedViewKind !== 'table') {
          setVisibleControlValue('#gs_se_table_edit_mode', 'view_only');
          scrollToEditorTarget('#gs-structured-editor-form', '#gs_se_table_edit_mode');
        } else {
          scrollToEditorTarget('#gs-fields-table');
        }
        return;
      }
      if (actionType === 'form') {
        if (normalizedCreateIntent === 'create_dashboard') {
          setVisibleControlValue('#gs_se_create_intent', 'create_view');
        }
        setVisibleControlValue('#gs_se_view_kind', 'form');
        setVisibleControlValue('#gs_se_table_edit_mode', 'view_only');
        scrollToEditorTarget('#gs-fields-table');
        return;
      }
      if (actionType === 'kpi') {
        setVisibleControlValue('#gs_se_create_intent', 'create_dashboard');
        setVisibleControlValue('#gs_se_view_kind', 'dashboard');
        scrollToDashboardTarget();
        return;
      }
      if (actionType === 'text') {
        setVisibleControlValue('#gs_se_create_intent', 'create_dashboard');
        setVisibleControlValue('#gs_se_view_kind', 'dashboard');
        scrollToDashboardTarget();
      }
    }

    var createActions = [
      {
        key: 'navigation',
        label: createFlowLabels.actionConfigureNavigation,
        onClick: function () { applyCreateAction('navigation'); }
      },
      {
        key: 'set_dashboard',
        label: createFlowLabels.actionSetDashboard,
        onClick: function () { applyCreateAction('set_dashboard'); }
      },
      {
        key: 'table',
        label: createFlowLabels.actionAddTable,
        onClick: function () { applyCreateAction('table'); }
      },
      {
        key: 'form',
        label: createFlowLabels.actionAddForm,
        onClick: function () { applyCreateAction('form'); }
      },
      {
        key: 'kpi',
        label: createFlowLabels.actionAddKpi,
        onClick: function () { applyCreateAction('kpi'); }
      },
      {
        key: 'text',
        label: createFlowLabels.actionAddText,
        onClick: function () { applyCreateAction('text'); }
      },
      {
        key: 'direct_db',
        label: createFlowLabels.actionDirectDb,
        onClick: function () {
          setVisibleControlValue('#gs_se_create_intent', 'create_view');
          setVisibleControlValue('#gs_se_view_kind', 'table');
          setVisibleControlValue('#gs_se_table_edit_mode', 'direct_db');
          scrollToEditorTarget('#gs-structured-editor-form', '#gs_se_table_edit_mode');
        }
      },
      {
        key: 'view_mode',
        label: createFlowLabels.actionViewMode,
        onClick: function () {
          setVisibleControlValue('#gs_se_create_intent', 'create_view');
          setVisibleControlValue('#gs_se_view_kind', 'table');
          setVisibleControlValue('#gs_se_table_edit_mode', 'view_only');
          scrollToEditorTarget('#gs-structured-editor-form', '#gs_se_table_edit_mode');
        }
      }
    ].filter(function (item) {
      if (!item) return false;
      if (loaded) {
        return false;
      }
      if (normalizedCreateIntent === 'create_app' || normalizedCreateIntent === 'create_module') {
        return false;
      }
      if (normalizedCreateIntent === 'create_navigation') {
        return item.key === 'navigation';
      }
      if (normalizedCreateIntent === 'create_dashboard') {
        return item.key === 'kpi' || item.key === 'text' || item.key === 'table' || item.key === 'form';
      }
      if (item.key === 'navigation') {
        return false;
      }
      if (item.key === 'set_dashboard') {
        return normalizedCreateIntent !== 'create_dashboard';
      }
      if (item.key === 'table') {
        return normalizedViewKind !== 'table';
      }
      if (item.key === 'form') {
        return normalizedViewKind !== 'form';
      }
      if (item.key === 'kpi') {
        return normalizedCreateIntent === 'create_dashboard';
      }
      if (item.key === 'direct_db') {
        return isTableView && tableEditMode !== 'direct_db';
      }
      if (item.key === 'view_mode') {
        return isTableView && tableEditMode !== 'view_only';
      }
      if (item.key === 'text') {
        return normalizedCreateIntent === 'create_dashboard';
      }
      return true;
    });

    if (createActions.length > 0) {
      appendOutlineGroup(createFlowBody, createFlowLabels.actions, createActions);
    }
  };

  if (switchCreateButton) {
    switchCreateButton.addEventListener('click', function () {
      setStudioMode('create_new', { preserveUpgradeBaseline: true });
    });
  }

  if (switchUpgradeButton) {
    switchUpgradeButton.addEventListener('click', function () {
      if (!hasLoadedBundleForUpgrade()) {
        setStudioMode('create_new');
        return;
      }
      setStudioMode('edit_existing');
    });
  }

  if (clearLoadedContextButtons && clearLoadedContextButtons.length > 0) {
    Array.prototype.forEach.call(clearLoadedContextButtons, function (button) {
      button.addEventListener('click', function (event) {
        event.preventDefault();
        clearLoadedContextLocalPreview();
      });
    });
  }

  document.addEventListener('studio-mode-changed', function () {
    refreshStudioModeSwitch();
  });

  function refreshStudioSidebarGuides() {
    if (typeof window.renderContentOutline === 'function') {
      window.renderContentOutline();
    }
    if (typeof window.renderCreateFlowGuide === 'function') {
      window.renderCreateFlowGuide();
    }
  }

  var pendingStudioSidebarRefresh = false;
  function scheduleStudioSidebarGuidesRefresh() {
    if (pendingStudioSidebarRefresh) {
      return;
    }
    pendingStudioSidebarRefresh = true;
    window.requestAnimationFrame(function () {
      pendingStudioSidebarRefresh = false;
      refreshStudioSidebarGuides();
    });
  }

  document.addEventListener('change', function (event) {
    if (!event || !event.target) {
      return;
    }
    if (event.target.id !== 'gs_se_view_kind' && event.target.id !== 'gs_se_table_edit_mode' && event.target.id !== 'gs_se_create_intent') {
      return;
    }
    scheduleStudioSidebarGuidesRefresh();
  });

  document.addEventListener('input', function (event) {
    if (!event || !event.target) {
      return;
    }
    if (event.target.id !== 'gs_se_module_key' && event.target.id !== 'gs_se_module_display_name') {
      return;
    }
    scheduleStudioSidebarGuidesRefresh();
  });

  // Listen for state changes and re-render the dynamic outline
  document.addEventListener('studio-state-committed', function (event) {
    var committed = (event && event.detail) ? event.detail : null;
    if (committed && typeof committed === 'object') {
      // State has changed; re-render the outline to reflect new structure
      scheduleStudioSidebarGuidesRefresh();
      if (typeof window.updateEditorSurfaceHeader === 'function') {
        window.updateEditorSurfaceHeader();
      }
      if (typeof window.renderSurfaceExposurePanel === 'function') {
        window.renderSurfaceExposurePanel();
      }
    }
  });

  window.gsLibraryExplorerContext = {
    usageLibraryStorageKey: usageLibraryStorageKey,
    libraryItems: libraryItems,
    libraryRecentList: libraryRecentList,
    libraryMostUsedList: libraryMostUsedList,
    libraryLastEditedList: libraryLastEditedList,
    librarySearchResults: librarySearchResults,
    librarySearchResultsList: librarySearchResultsList,
    librarySearchInput: librarySearchInput,
    libraryQuickLoadPanel: libraryQuickLoadPanel,
    libraryModuleSections: libraryModuleSections,
    libraryAppSections: libraryAppSections,
    libraryAdvancedRegistrySections: libraryAdvancedRegistrySections,
    libraryFilterButtons: libraryFilterButtons,
    contentOutlineBackButton: contentOutlineBackButton,
    libraryDetailSheet: libraryDetailSheet,
    libraryDetailBackdrop: libraryDetailBackdrop,
    libraryDetailLabel: libraryDetailLabel,
    libraryDetailKind: libraryDetailKind,
    libraryDetailNode: libraryDetailNode,
    libraryDetailSupportNote: libraryDetailSupportNote,
    libraryDetailLoadButton: libraryDetailLoadButton,
    libraryDetailInspectButton: libraryDetailInspectButton,
    readRecentLibraryIds: readRecentLibraryIds,
    itemMatchesFilter: itemMatchesFilter,
    resolveLibraryArtifactKind: resolveLibraryArtifactKind,
    isArtifactLoadableInEditor: isArtifactLoadableInEditor,
    isMobileStudio: isMobileStudio,
    switchMobileMode: switchMobileMode,
    activeLibraryFilterRef: function () { return activeLibraryFilter; },
    setActiveLibraryFilter: function (value) { activeLibraryFilter = value; },
    renderCreateFlowGuide: function () {
      if (typeof window.renderCreateFlowGuide === 'function') {
        window.renderCreateFlowGuide();
      }
    },
    strings: {
      libraryFilterRecent: <?= json_encode($gs('library_filter_recent')) ?>,
      libraryFilterViews: <?= json_encode($gs('library_filter_views')) ?>,
      librarySearchResultsEmpty: <?= json_encode($gs('library_search_results_empty')) ?>,
      libraryDetailTitle: <?= json_encode($gs('library_detail_title')) ?>,
      libraryDetailInspectOnlyNote: <?= json_encode($gs('library_detail_inspect_only_note')) ?>,
      libraryDetailOwnerUnknown: <?= json_encode($gs('library_detail_owner_unknown')) ?>,
      libraryDetailToolsLoading: <?= json_encode($gs('library_detail_tools_loading')) ?>,
      libraryDetailToolsEmpty: <?= json_encode($gs('library_detail_tools_empty')) ?>,
      libraryDetailToolsUnavailable: <?= json_encode($gs('library_detail_tools_unavailable')) ?>,
      libraryLoadApp: <?= json_encode($gs('library_load_app')) ?>,
      libraryLoadTableOnly: <?= json_encode($gs('library_load_table_only')) ?>,
      libraryLoadFormOnly: <?= json_encode($gs('library_load_form_only')) ?>,
      globalLibraryLoadIntoStudio: <?= json_encode($gs('global_library_load_into_studio')) ?>,
      inspect: <?= json_encode($gs('inspect')) ?>
    }
  };
  refreshStudioModeSwitch();
  if (isLoadedContentMode() && !readLoadedArtifactContext()) {
    var existingBundle = readLoadedBundleObject() || {};
    var existingContentType = inferLoadedContentTypeFromBundle(existingBundle);
    var existingAppManifest = existingBundle.app_manifest && typeof existingBundle.app_manifest === 'object' ? existingBundle.app_manifest : {};
    var existingAppInner = existingAppManifest.app && typeof existingAppManifest.app === 'object' ? existingAppManifest.app : existingAppManifest;
    var existingModuleManifest = existingBundle.module_manifest && typeof existingBundle.module_manifest === 'object' ? existingBundle.module_manifest : {};
    var existingModuleInner = existingModuleManifest.module && typeof existingModuleManifest.module === 'object' ? existingModuleManifest.module : existingModuleManifest;
    writeLoadedArtifactContext({
      artifactKind: 'unknown',
      contentType: existingContentType,
      sourceType: 'library',
      ownerAppKey: String(existingAppInner.app_key || existingAppManifest.app_key || '').trim(),
      ownerModuleKey: String(existingModuleInner.module_key || existingModuleManifest.module_key || '').trim(),
      nodeId: ''
    });
  }
  if (typeof window.updateEditorSurfaceHeader === 'function') {
    window.updateEditorSurfaceHeader();
  }
  if (typeof window.renderCreateFlowGuide === 'function') {
    window.renderCreateFlowGuide();
  }
  if (typeof window.renderSurfaceExposurePanel === 'function') {
    window.renderSurfaceExposurePanel();
  }
  
  function switchTab(tabName) {
    tabButtons.forEach(b => b.classList.remove('active'));
    tabPanels.forEach(p => p.classList.remove('active'));
    document.querySelector('.studio-tabs button[data-tab="' + tabName + '"]').classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
  }

  function switchMobileMode(modeName) {
    if (!studioShell) {
      return;
    }
    var normalizedMode = ['library', 'editor', 'run'].indexOf(modeName) === -1 ? 'editor' : modeName;
    studioShell.setAttribute('data-mobile-mode', normalizedMode);
    mobileModeButtons.forEach(function (button) {
      button.classList.toggle('active', String(button.getAttribute('data-mobile-mode-target') || '') === normalizedMode);
    });
    if (normalizedMode === 'editor') {
      switchTab('edit');
      if (librarySearchInput) {
        librarySearchInput.blur();
      }
    }
    if (normalizedMode === 'run') {
      switchTab('analyze');
    }
    if (normalizedMode === 'library' && librarySearchInput) {
      window.setTimeout(function () {
        librarySearchInput.focus();
      }, 80);
    }
  }

  tabButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      const tabName = this.getAttribute('data-tab');
      switchTab(tabName);
    });
  });

  // Navigation buttons between tabs
  const btnAnalyze = document.getElementById('btn-to-analyze');
  if (btnAnalyze) {
    btnAnalyze.addEventListener('click', function() {
      if (isMobileStudio()) {
        switchMobileMode('run');
      }
      switchTab('analyze');
    });
  }

  mobileModeButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      switchMobileMode(String(button.getAttribute('data-mobile-mode-target') || 'editor'));
    });
  });

  if (isMobileStudio()) {
    switchMobileMode('library');
  }

  const btnBackEdit = document.getElementById('btn-back-edit');
  if (btnBackEdit) {
    btnBackEdit.addEventListener('click', function() {
      switchTab('edit');
    });
  }

  const btnToChanges = document.getElementById('btn-to-changes');
  if (btnToChanges) {
    btnToChanges.addEventListener('click', function() {
      switchTab('changes');
    });
  }

  const btnBackAnalyze = document.getElementById('btn-back-analyze');
  if (btnBackAnalyze) {
    btnBackAnalyze.addEventListener('click', function() {
      switchTab('analyze');
    });
  }

  const btnToApply = document.getElementById('btn-to-apply');
  if (btnToApply) {
    btnToApply.addEventListener('click', function() {
      switchTab('apply');
    });
  }

  const btnBackChanges = document.getElementById('btn-back-changes');
  if (btnBackChanges) {
    btnBackChanges.addEventListener('click', function() {
      switchTab('changes');
    });
  }

  if (typeof window.gsInitApplyFormGate === 'function') {
    window.gsInitApplyFormGate({
      switchTab: switchTab,
      strings: {
        breakingChangesBlocked: <?= json_encode($gs('breaking_changes_blocked')) ?>
      }
    });
  }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStudioPage, { once: true });
  } else {
    initStudioPage();
  }
})();
