<script>
(function () {
  var tableEditModeNoteViewOnly = <?= json_encode($gs('table_edit_mode_note_view_only')) ?>;
  var tableEditModeNoteDirectDb = <?= json_encode($gs('table_edit_mode_note_direct_db')) ?>;

  function currentVisibleViewKind() {
    var candidates = Array.prototype.slice.call(document.querySelectorAll('#gs_se_view_kind'));
    var visible = candidates.find(function (node) {
      return !!(node && !node.hidden && node.offsetParent !== null);
    });
    var active = visible || candidates[0] || null;
    return String((active && active.value) || 'table').toLowerCase();
  }

  function currentVisibleTableEditMode() {
    var candidates = Array.prototype.slice.call(document.querySelectorAll('#gs_se_table_edit_mode'));
    var visible = candidates.find(function (node) {
      return !!(node && !node.hidden && node.offsetParent !== null);
    });
    var active = visible || candidates[0] || null;
    var mode = String((active && active.value) || 'view_only').toLowerCase();
    return mode === 'direct_db' ? 'direct_db' : 'view_only';
  }

  function currentVisibleCreateIntent() {
    var candidates = Array.prototype.slice.call(document.querySelectorAll('#gs_se_create_intent'));
    var visible = candidates.find(function (node) {
      return !!(node && !node.hidden && node.offsetParent !== null);
    });
    var active = visible || candidates[0] || null;
    return String((active && active.value) || 'create_view').toLowerCase();
  }

  function currentVisibleModuleType() {
    var candidates = Array.prototype.slice.call(document.querySelectorAll('#gs_se_module_type'));
    var visible = candidates.find(function (node) {
      return !!(node && !node.hidden && node.offsetParent !== null);
    });
    var active = visible || candidates[0] || null;
    return String((active && active.value) || 'crud').toLowerCase();
  }

  var dashboardIntentActive = false;
  var previousModuleTypeBeforeDashboardIntent = 'crud';
  var previousViewKindBeforeDashboardIntent = 'table';

  function syncSelectGroupValue(selector, value) {
    var normalized = String(value || '');
    Array.prototype.forEach.call(document.querySelectorAll(selector), function (node) {
      if (!node || node.value === normalized) {
        return;
      }
      node.value = normalized;
    });
  }

  function enforceIntentDrivenDefaults() {
    var createIntent = currentVisibleCreateIntent();
    var moduleType = currentVisibleModuleType();
    var viewKind = currentVisibleViewKind();
    if (createIntent === 'create_dashboard') {
      if (!dashboardIntentActive) {
        previousModuleTypeBeforeDashboardIntent = moduleType || 'crud';
        previousViewKindBeforeDashboardIntent = viewKind || 'table';
        dashboardIntentActive = true;
      }
      syncSelectGroupValue('#gs_se_view_kind', 'dashboard');
      syncSelectGroupValue('#gs_se_module_type', 'dashboard');
      return;
    }
    if (dashboardIntentActive) {
      if (moduleType === 'dashboard') {
        var restoredModuleType = previousModuleTypeBeforeDashboardIntent === 'dashboard'
          ? 'crud'
          : previousModuleTypeBeforeDashboardIntent;
        syncSelectGroupValue('#gs_se_module_type', restoredModuleType || 'crud');
      }
      if (viewKind === 'dashboard') {
        var restoredViewKind = previousViewKindBeforeDashboardIntent || 'table';
        syncSelectGroupValue('#gs_se_view_kind', restoredViewKind);
      }
    }
    dashboardIntentActive = false;
  }

  function syncDirectDbMutationFlag() {
    var allowDirectDb = currentVisibleViewKind() === 'table' && currentVisibleTableEditMode() === 'direct_db';
    Array.prototype.forEach.call(document.querySelectorAll('#gs_view_definition'), function (node) {
      if (!node) {
        return;
      }
      try {
        var parsed = JSON.parse(String(node.value || '{}'));
        if (!parsed || typeof parsed !== 'object') {
          return;
        }
        var security = parsed.security && typeof parsed.security === 'object' ? parsed.security : {};
        if (!!security.direct_db_mutation_allowed === allowDirectDb) {
          return;
        }
        security.direct_db_mutation_allowed = allowDirectDb;
        parsed.security = security;
        node.value = JSON.stringify(parsed, null, 2);
      } catch (error) {
        // Keep runtime sync resilient while editor JSON is incomplete.
      }
    });
  }

  var lastLegacyFieldVisibilitySignature = '';

  function applyContentAwareFieldVisibility(force) {
    enforceIntentDrivenDefaults();
    var viewKind = currentVisibleViewKind();
    var createIntent = currentVisibleCreateIntent();
    var isAppIntent = createIntent === 'create_app';
    var isModuleIntent = createIntent === 'create_module';
    var isNavigationIntent = createIntent === 'create_navigation';
    var isDashboardIntent = createIntent === 'create_dashboard';
    var tableEditMode = currentVisibleTableEditMode();
    var loadedMode = typeof window.isLoadedContentMode === 'function' && window.isLoadedContentMode();
    var showCreateIntent = !loadedMode;
    var showViewKind = !isNavigationIntent && !isDashboardIntent && !isAppIntent && !isModuleIntent;
    var showModuleDetails = !isNavigationIntent && !isAppIntent && !loadedMode;
    var showFieldEditors = showViewKind && (viewKind === 'table' || viewKind === 'form');
    var showTableEditMode = showViewKind && viewKind === 'table';
    var showRoutePath = !isNavigationIntent && !isAppIntent && !isModuleIntent && !loadedMode;
    var hideViewOnlyControls = showTableEditMode && tableEditMode === 'direct_db';
    var visibilitySignature = [createIntent, viewKind, tableEditMode, showCreateIntent ? '1' : '0'].join('|');
    if (!force && visibilitySignature === lastLegacyFieldVisibilitySignature) {
      syncDirectDbMutationFlag();
      return;
    }
    lastLegacyFieldVisibilitySignature = visibilitySignature;

    Array.prototype.forEach.call(document.querySelectorAll('.gs-create-intent-control'), function (node) {
      node.hidden = !showCreateIntent;
    });

    Array.prototype.forEach.call(document.querySelectorAll('.gs-view-kind-control'), function (node) {
      node.hidden = !showViewKind;
    });

    Array.prototype.forEach.call(document.querySelectorAll('.gs-module-details-control'), function (node) {
      node.hidden = !showModuleDetails;
    });

    Array.prototype.forEach.call(document.querySelectorAll('.gs-route-path-control'), function (node) {
      node.hidden = !showRoutePath;
    });

    Array.prototype.forEach.call(document.querySelectorAll('#gs-fields-table'), function (table) {
      var fieldsWrap = table ? table.closest('.table-wrap') : null;
      var fieldsHeading = fieldsWrap && fieldsWrap.previousElementSibling && fieldsWrap.previousElementSibling.tagName === 'H4'
        ? fieldsWrap.previousElementSibling
        : null;
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

    Array.prototype.forEach.call(document.querySelectorAll('.gs-view-layout-control'), function (node) {
      node.hidden = showTableEditMode;
    });

    var approvalMetaSection = document.getElementById('gs-approval-meta-section');
    if (approvalMetaSection) {
      approvalMetaSection.hidden = showTableEditMode;
    }

    syncDirectDbMutationFlag();
  }

  document.addEventListener('change', function (event) {
    if (!event || !event.target) {
      return;
    }
    if (event.target.id !== 'gs_se_view_kind' && event.target.id !== 'gs_se_table_edit_mode' && event.target.id !== 'gs_se_create_intent') {
      return;
    }
    applyContentAwareFieldVisibility();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyContentAwareFieldVisibility, { once: true });
  } else {
    applyContentAwareFieldVisibility();
  }

  window.setInterval(applyContentAwareFieldVisibility, 1000);
})();
</script>
