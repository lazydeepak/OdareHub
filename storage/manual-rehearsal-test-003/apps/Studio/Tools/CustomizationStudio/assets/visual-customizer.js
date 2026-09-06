(function () {
  'use strict';

  var EXPERIMENT_SOCKET_ID = 'radius.scale';

  var fixtureMetadata = window.CustomizationStudioVisualCustomizerFixtureMetadata || {};
  var socketBrowserMetadata = window.CustomizationStudioVisualCustomizerAllSockets || {};
  var fixtures = Array.isArray(fixtureMetadata.fixtures) ? fixtureMetadata.fixtures : [];
  var socketCatalogs = Array.isArray(fixtureMetadata.socket_catalogs) ? fixtureMetadata.socket_catalogs : [];
  var allSocketRows = Array.isArray(socketBrowserMetadata.sockets) ? socketBrowserMetadata.sockets : [];
  var allSocketCatalogs = socketBrowserMetadata.catalogs && typeof socketBrowserMetadata.catalogs === 'object'
    ? socketBrowserMetadata.catalogs
    : {};
  var i18n = fixtureMetadata.i18n && typeof fixtureMetadata.i18n === 'object' ? fixtureMetadata.i18n : {};
  var noSocketSelectedText = typeof i18n.socket_detail_empty === 'string' && i18n.socket_detail_empty !== ''
    ? i18n.socket_detail_empty
    : 'No socket selected';
  var noAdvancedTokenText = typeof i18n.socket_no_advanced_token === 'string' && i18n.socket_no_advanced_token !== ''
    ? i18n.socket_no_advanced_token
    : 'No advanced token declared yet.';
  var currentValueNotConnectedText = typeof i18n.socket_current_value_not_connected === 'string' && i18n.socket_current_value_not_connected !== ''
    ? i18n.socket_current_value_not_connected
    : 'Not connected yet';
  var proposedValueNoDraftText = typeof i18n.socket_proposed_value_none === 'string' && i18n.socket_proposed_value_none !== ''
    ? i18n.socket_proposed_value_none
    : 'No draft yet';
  var localDraftEmptyText = typeof i18n.socket_local_draft_empty === 'string' && i18n.socket_local_draft_empty !== ''
    ? i18n.socket_local_draft_empty
    : 'No local draft yet.';
  var localDraftSavedText = typeof i18n.socket_local_draft_saved === 'string' && i18n.socket_local_draft_saved !== ''
    ? i18n.socket_local_draft_saved
    : 'Local draft saved for Corner scale.';
  var localDraftRuntimeNoteText = typeof i18n.socket_local_draft_runtime_note === 'string' && i18n.socket_local_draft_runtime_note !== ''
    ? i18n.socket_local_draft_runtime_note
    : 'This does not change the live system.';
  var resetStatusLocalDraftText = typeof i18n.socket_reset_status_local_draft === 'string' && i18n.socket_reset_status_local_draft !== ''
    ? i18n.socket_reset_status_local_draft
    : 'Studio-local draft only';
  var readonlyExperimentEnabledText = typeof i18n.socket_editable_safe_control_only === 'string' && i18n.socket_editable_safe_control_only !== ''
    ? i18n.socket_editable_safe_control_only
    : 'Draft edit enabled for Corner scale only';
  var draftDiffNoChangeText = typeof i18n.draft_diff_no_change === 'string' && i18n.draft_diff_no_change !== ''
    ? i18n.draft_diff_no_change
    : 'No draft change';
  var draftDiffChangedPrefixText = typeof i18n.draft_diff_changed_prefix === 'string' && i18n.draft_diff_changed_prefix !== ''
    ? i18n.draft_diff_changed_prefix
    : 'Corner scale changed from soft to';
  var draftDiffImpactPreviewOnlyText = typeof i18n.draft_diff_impact_preview_only === 'string' && i18n.draft_diff_impact_preview_only !== ''
    ? i18n.draft_diff_impact_preview_only
    : 'Studio preview only';
  var draftDiffApplyDisabledText = typeof i18n.draft_diff_apply_disabled === 'string' && i18n.draft_diff_apply_disabled !== ''
    ? i18n.draft_diff_apply_disabled
    : 'Disabled';
  var draftDiffSourceStudioLocalText = typeof i18n.draft_diff_source_studio_local === 'string' && i18n.draft_diff_source_studio_local !== ''
    ? i18n.draft_diff_source_studio_local
    : 'Studio-local draft';
  var draftDiffRuntimeNotAppliedText = typeof i18n.draft_diff_runtime_not_applied === 'string' && i18n.draft_diff_runtime_not_applied !== ''
    ? i18n.draft_diff_runtime_not_applied
    : 'not applied';
  var validationResult = fixtureMetadata.validation_result && typeof fixtureMetadata.validation_result === 'object'
    ? fixtureMetadata.validation_result
    : { valid: false, status: 'not_eligible', checks: {}, errors: [], warnings: [] };
  var readinessRequestEligibleText = typeof i18n.readiness_request_eligible === 'string' && i18n.readiness_request_eligible !== ''
    ? i18n.readiness_request_eligible
    : 'Request Approval: Eligible';
  var readinessRequestNotEligibleText = typeof i18n.readiness_request_not_eligible === 'string' && i18n.readiness_request_not_eligible !== ''
    ? i18n.readiness_request_not_eligible
    : 'Request Approval: Not eligible';
  var approvalRequestCreateText = typeof i18n.approval_request_create === 'string' && i18n.approval_request_create !== ''
    ? i18n.approval_request_create
    : 'Request Approval';
  var approvalRequestPendingText = typeof i18n.approval_request_pending === 'string' && i18n.approval_request_pending !== ''
    ? i18n.approval_request_pending
    : 'Request: Pending review';
  var approvalRequestAlreadyPendingText = typeof i18n.approval_request_already_pending === 'string' && i18n.approval_request_already_pending !== ''
    ? i18n.approval_request_already_pending
    : 'Request already pending';
  var approvalRequestCreatingText = typeof i18n.approval_request_creating === 'string' && i18n.approval_request_creating !== ''
    ? i18n.approval_request_creating
    : 'Requesting\u2026';
  var approvalRequestFailedText = typeof i18n.approval_request_failed === 'string' && i18n.approval_request_failed !== ''
    ? i18n.approval_request_failed
    : 'Request creation failed';
  var requestApprovalState = 'idle';
  var persistedDraft = fixtureMetadata.persisted_draft && typeof fixtureMetadata.persisted_draft === 'object'
    ? fixtureMetadata.persisted_draft
    : {};
  var draftUpdateEndpoint = typeof fixtureMetadata.draft_update_endpoint === 'string'
    ? fixtureMetadata.draft_update_endpoint
    : '';
  var csrfToken = typeof fixtureMetadata.csrf === 'string' ? fixtureMetadata.csrf : '';
  var recheckReadinessEndpoint = typeof fixtureMetadata.recheck_readiness_endpoint === 'string'
    ? fixtureMetadata.recheck_readiness_endpoint
    : '';
  var createApprovalRequestEndpoint = typeof fixtureMetadata.create_approval_request_endpoint === 'string'
    ? fixtureMetadata.create_approval_request_endpoint
    : '';
  var fixtureById = {};
  var socketCatalogById = {};
  var index = 0;
  while (index < fixtures.length) {
    var item = fixtures[index] || {};
    var id = typeof item.id === 'string' ? item.id : '';
    if (id !== '') {
      fixtureById[id] = item;
    }
    index += 1;
  }

  index = 0;
  while (index < socketCatalogs.length) {
    var socketCatalog = socketCatalogs[index] || {};
    var catalogId = typeof socketCatalog.id === 'string' ? socketCatalog.id : '';
    if (catalogId !== '') {
      socketCatalogById[catalogId] = socketCatalog;
    }
    index += 1;
  }

  var selector = document.getElementById('cs-vc-fixture-selector');
  var socketSelector = document.getElementById('cs-vc-socket-selector');
  var titleEl = document.getElementById('cs-vc-fixture-title');
  var descriptionEl = document.getElementById('cs-vc-fixture-description');
  var socketTitleEl = document.getElementById('cs-vc-socket-title');
  var socketDescriptionEl = document.getElementById('cs-vc-socket-description');
  var socketCountEl = document.getElementById('cs-vc-socket-count');
  var socketListEl = document.getElementById('cs-vc-socket-list');
  var socketDetailLabelEl = document.getElementById('cs-vc-socket-detail-label');
  var socketDetailDescriptionEl = document.getElementById('cs-vc-socket-detail-description');
  var socketDetailCategoryEl = document.getElementById('cs-vc-socket-detail-category');
  var socketDetailScopeEl = document.getElementById('cs-vc-socket-detail-scope');
  var socketDetailValueTypeEl = document.getElementById('cs-vc-socket-detail-value-type');
  var socketDetailSimpleControlsEl = document.getElementById('cs-vc-socket-detail-simple-controls');
  var socketDetailAdvancedTokenEl = document.getElementById('cs-vc-socket-detail-advanced-token');
  var socketDetailDefaultValueEl = document.getElementById('cs-vc-socket-detail-default-value');
  var socketSafetyDefaultValueEl = document.getElementById('cs-vc-socket-safety-default-value');
  var socketDetailCurrentValueEl = document.getElementById('cs-vc-socket-detail-current-value');
  var socketSafetyProposedValueEl = document.getElementById('cs-vc-socket-safety-proposed-value');
  var socketLocalDraftStatusEl = document.getElementById('cs-vc-socket-local-draft-status');
  var lifecycleDraftStatusEl = document.getElementById('cs-vc-lifecycle-draft-status');
  var localDraftMessageEl = document.getElementById('cs-vc-local-draft-message');
  var socketSafetyResetStatusEl = document.getElementById('cs-vc-socket-reset-status');
  var draftDiffSummaryEl = document.getElementById('cs-vc-draft-diff-summary');
  var draftDiffDefaultEl = document.getElementById('cs-vc-draft-diff-default');
  var draftDiffCurrentEl = document.getElementById('cs-vc-draft-diff-current');
  var draftDiffProposedEl = document.getElementById('cs-vc-draft-diff-proposed');
  var draftDiffImpactEl = document.getElementById('cs-vc-draft-diff-impact');
  var draftDiffApplyEl = document.getElementById('cs-vc-draft-diff-apply');
  var draftDiffSocketEl = document.getElementById('cs-vc-draft-diff-socket');
  var draftDiffTokenEl = document.getElementById('cs-vc-draft-diff-token');
  var draftDiffSourceEl = document.getElementById('cs-vc-draft-diff-source');
  var draftDiffRuntimeEl = document.getElementById('cs-vc-draft-diff-runtime');
  var resetControlButton = document.getElementById('cs-vc-reset-control');
  var resetSectionButton = document.getElementById('cs-vc-reset-section');
  var discardDraftButton = document.getElementById('cs-vc-discard-draft');
  var restoreLastApprovedButton = document.getElementById('cs-vc-restore-last-approved');
  var readonlyPillEl = document.getElementById('cs-vc-readonly-pill');
  var relationshipFixtureToCatalogsEl = document.getElementById('cs-vc-rel-fixture-to-catalogs');
  var relationshipCatalogToFixturesEl = document.getElementById('cs-vc-rel-catalog-to-fixtures');
  var fixtureOutlineListEl = document.getElementById('cs-vc-fixture-outline-list');
  var canvasFixtureRootEl = document.getElementById('cs-vc-fixture-canvas');
  var canvasTitleEl = document.getElementById('cs-vc-canvas-title');
  var canvasHintEl = document.getElementById('cs-vc-canvas-hint');
  var canvasScaffoldEl = document.getElementById('cs-vc-canvas-scaffold');
  var canvasOutlineListEl = document.getElementById('cs-vc-canvas-outline-list');
  var readinessStatusEl = document.getElementById('cs-vc-readiness-status');
  var recheckReadinessButtonEl = document.getElementById('cs-vc-recheck-readiness');
  var requestApprovalButtonEl = document.getElementById('cs-vc-create-approval-request');
  var fixtureCardNodes = document.querySelectorAll('[data-fixture-card-id]');
  var socketBrowserSearchEl = document.getElementById('cs-vc-socket-browser-search');
  var socketBrowserCatalogEl = document.getElementById('cs-vc-socket-browser-catalog');
  var socketBrowserCategoryEl = document.getElementById('cs-vc-socket-browser-category');
  var socketBrowserTypeEl = document.getElementById('cs-vc-socket-browser-type');
  var socketBrowserListEl = document.getElementById('cs-vc-socket-browser-list');
  var socketBrowserDetailBodyEl = document.getElementById('cs-vc-socket-browser-detail-body');
  var socketBrowserVisibleCountEl = document.getElementById('cs-vc-socket-browser-count-val');
  var socketBrowserTotalCountEl = document.getElementById('cs-vc-socket-browser-total-val');
  var socketBrowserCatalogTotalEl = document.getElementById('cs-vc-socket-browser-catalog-total');
  var socketBrowserCategoryTotalEl = document.getElementById('cs-vc-socket-browser-category-total');
  var socketBrowserTypeTotalEl = document.getElementById('cs-vc-socket-browser-type-total');
  var selectedSocketId = '';
  var selectedFixtureId = '';
  var selectedSocket = null;
  var readonlyNotEditableText = readonlyPillEl && typeof readonlyPillEl.textContent === 'string'
    ? readonlyPillEl.textContent
    : 'Not editable yet';
  var resetStatusDisabledText = socketSafetyResetStatusEl && typeof socketSafetyResetStatusEl.textContent === 'string'
    ? socketSafetyResetStatusEl.textContent
    : 'Disabled until editing is enabled';
  var localDraftState = {
    draft_id: 'visual-customizer-local-preview',
    status: 'local_preview_only',
    scope: 'studio_customization_preview',
    values: {}
  };

  var textFor = function (key, fallback) {
    return typeof i18n[key] === 'string' && i18n[key] !== '' ? i18n[key] : fallback;
  };

  var appendText = function (parent, tagName, className, text) {
    var node = document.createElement(tagName);
    if (className !== '') {
      node.className = className;
    }
    node.textContent = text;
    parent.appendChild(node);
    return node;
  };

  var appendCode = function (parent, text) {
    var code = document.createElement('code');
    code.textContent = text;
    parent.appendChild(code);
    return code;
  };

  var buildCountMap = function (rows, key) {
    var counts = {};
    var rowIndex = 0;
    while (rowIndex < rows.length) {
      var row = rows[rowIndex] || {};
      var value = typeof row[key] === 'string' ? row[key] : '';
      if (value !== '') {
        counts[value] = (counts[value] || 0) + 1;
      }
      rowIndex += 1;
    }

    return counts;
  };

  var appendFilterOptions = function (selectEl, counts, labelLookup) {
    if (!selectEl) {
      return;
    }

    var values = Object.keys(counts).sort();
    var valueIndex = 0;
    while (valueIndex < values.length) {
      var value = values[valueIndex];
      var option = document.createElement('option');
      option.value = value;
      option.textContent = (labelLookup && labelLookup[value] ? labelLookup[value] : value) + ' (' + counts[value] + ')';
      selectEl.appendChild(option);
      valueIndex += 1;
    }
  };

  var getSocketBrowserMatches = function () {
    var query = socketBrowserSearchEl ? socketBrowserSearchEl.value.toLowerCase().trim() : '';
    var catalogId = socketBrowserCatalogEl ? socketBrowserCatalogEl.value : '';
    var category = socketBrowserCategoryEl ? socketBrowserCategoryEl.value : '';
    var valueType = socketBrowserTypeEl ? socketBrowserTypeEl.value : '';
    var matches = [];
    var rowIndex = 0;

    while (rowIndex < allSocketRows.length) {
      var socket = allSocketRows[rowIndex] || {};
      if (catalogId !== '' && socket.catalog_id !== catalogId) {
        rowIndex += 1;
        continue;
      }
      if (category !== '' && socket.category !== category) {
        rowIndex += 1;
        continue;
      }
      if (valueType !== '' && socket.value_type !== valueType) {
        rowIndex += 1;
        continue;
      }
      if (query !== '') {
        var haystack = [
          socket.socket_id || '',
          socket.label || '',
          socket.description || '',
          socket.category || '',
          socket.scope || '',
          socket.value_type || '',
          socket.catalog_name || ''
        ].join(' ').toLowerCase();
        if (haystack.indexOf(query) === -1) {
          rowIndex += 1;
          continue;
        }
      }
      matches.push(socket);
      rowIndex += 1;
    }

    return matches;
  };

  var setSocketBrowserActiveItem = function (socketId) {
    if (!socketBrowserListEl) {
      return;
    }

    var items = socketBrowserListEl.querySelectorAll('.cs-vc__socket-browser-item');
    var itemIndex = 0;
    while (itemIndex < items.length) {
      var item = items[itemIndex];
      var selected = item.getAttribute('data-socket-id') === socketId;
      item.classList.toggle('is-active', selected);
      item.setAttribute('aria-selected', selected ? 'true' : 'false');
      itemIndex += 1;
    }
  };

  var renderSocketBrowserDetail = function (socket) {
    if (!socketBrowserDetailBodyEl) {
      return;
    }

    while (socketBrowserDetailBodyEl.firstChild) {
      socketBrowserDetailBodyEl.removeChild(socketBrowserDetailBodyEl.firstChild);
    }

    if (!socket || typeof socket !== 'object') {
      appendText(socketBrowserDetailBodyEl, 'p', 'cs-vc__socket-browser-detail-empty', textFor('socket_browse_detail_none', 'Select a socket to see details.'));
      return;
    }

    var status = appendText(socketBrowserDetailBodyEl, 'p', 'cs-vc__socket-browser-detail-status', textFor('socket_browse_status_inline', 'Catalog only - not consumed'));
    status.setAttribute('aria-label', textFor('socket_browse_status_row', 'Runtime status'));

    var dl = document.createElement('dl');
    dl.className = 'cs-vc__socket-browser-detail-dl';
    socketBrowserDetailBodyEl.appendChild(dl);

    var addRow = function (label, value, asCode) {
      var dt = document.createElement('dt');
      dt.textContent = label;
      var dd = document.createElement('dd');
      if (asCode) {
        appendCode(dd, value);
      } else {
        dd.textContent = value;
      }
      dl.appendChild(dt);
      dl.appendChild(dd);
    };

    addRow(textFor('socket_browse_socket', 'Socket'), socket.socket_id || '', true);
    addRow(textFor('socket_browse_label', 'Label'), socket.label || '', false);
    addRow(textFor('socket_browse_description', 'Description'), socket.description || textFor('socket_browse_not_declared', 'Not declared'), false);
    addRow(textFor('socket_browse_category', 'Category'), socket.category || textFor('socket_browse_not_declared', 'Not declared'), false);
    addRow(textFor('socket_browse_scope', 'Scope'), socket.scope || textFor('socket_browse_not_declared', 'Not declared'), false);
    addRow(textFor('socket_browse_type', 'Type'), socket.value_type || textFor('socket_browse_not_declared', 'Not declared'), false);
    addRow(textFor('socket_browse_default_value', 'Default value'), socket.default_value || textFor('socket_browse_not_declared', 'Not declared'), true);
    addRow(textFor('socket_browse_advanced_token', 'Advanced token'), socket.advanced_token || textFor('socket_browse_not_declared', 'Not declared'), true);

    var controls = Array.isArray(socket.simple_controls) ? socket.simple_controls : [];
    addRow(textFor('socket_browse_simple_controls', 'Simple controls'), controls.length > 0 ? controls.join(', ') : textFor('socket_browse_not_declared', 'Not declared'), false);
    addRow(textFor('socket_browse_catalog', 'Catalog'), (socket.catalog_name || socket.catalog_id || '') + (socket.catalog_id ? ' (' + socket.catalog_id + ')' : ''), false);
    addRow(textFor('socket_browse_status_row', 'Runtime status'), textFor('socket_browse_status_inline', 'Catalog only - not consumed'), false);

    appendText(socketBrowserDetailBodyEl, 'p', 'cs-vc__socket-browser-readonly-note', textFor('socket_browse_readonly_metadata', 'Read-only metadata'));
  };

  var renderSocketBrowserList = function () {
    if (!socketBrowserListEl) {
      return;
    }

    while (socketBrowserListEl.firstChild) {
      socketBrowserListEl.removeChild(socketBrowserListEl.firstChild);
    }

    var matches = getSocketBrowserMatches();
    if (socketBrowserVisibleCountEl) {
      socketBrowserVisibleCountEl.textContent = String(matches.length);
    }

    if (matches.length === 0) {
      var emptyItem = document.createElement('li');
      emptyItem.className = 'cs-vc__socket-browser-empty';
      appendText(emptyItem, 'p', '', textFor('socket_browse_none', 'No sockets match your filters.'));
      appendText(emptyItem, 'p', 'cs-vc__socket-browser-empty-suggestion', textFor('socket_browse_none_suggestion', 'Try clearing some filters to see more results.'));
      socketBrowserListEl.appendChild(emptyItem);
      renderSocketBrowserDetail(null);
      return;
    }

    var matchIndex = 0;
    while (matchIndex < matches.length) {
      var socket = matches[matchIndex] || {};
      var item = document.createElement('li');
      item.setAttribute('role', 'option');
      item.setAttribute('aria-selected', 'false');

      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'cs-vc__socket-browser-item';
      button.setAttribute('data-socket-id', socket.socket_id || '');
      button.setAttribute('aria-selected', 'false');

      appendText(button, 'span', 'cs-vc__socket-browser-item-label', (socket.label || socket.socket_id || '') + ' (' + (socket.socket_id || '') + ')');
      appendText(button, 'span', 'cs-vc__socket-browser-item-meta', (socket.category || '-') + ' / ' + (socket.value_type || '-'));
      appendText(button, 'span', 'cs-vc__socket-browser-item-catalog', textFor('socket_browse_catalog', 'Catalog') + ': ' + (socket.catalog_name || socket.catalog_id || ''));
      appendText(button, 'span', 'cs-vc__socket-browser-item-status', textFor('socket_browse_status_inline', 'Catalog only - not consumed'));
      button.addEventListener('click', (function (selectedSocket) {
        return function () {
          renderSocketBrowserDetail(selectedSocket);
          setSocketBrowserActiveItem(selectedSocket.socket_id || '');
        };
      }(socket)));

      item.appendChild(button);
      socketBrowserListEl.appendChild(item);
      matchIndex += 1;
    }
  };

  var initializeSocketBrowser = function () {
    if (!socketBrowserListEl) {
      return;
    }

    var catalogCounts = buildCountMap(allSocketRows, 'catalog_id');
    var categoryCounts = buildCountMap(allSocketRows, 'category');
    var valueTypeCounts = buildCountMap(allSocketRows, 'value_type');
    var catalogLabels = {};
    var catalogIds = Object.keys(allSocketCatalogs);
    var catalogIndex = 0;

    while (catalogIndex < catalogIds.length) {
      var catalogId = catalogIds[catalogIndex];
      var catalog = allSocketCatalogs[catalogId] || {};
      catalogLabels[catalogId] = typeof catalog.name === 'string' && catalog.name !== '' ? catalog.name : catalogId;
      catalogIndex += 1;
    }

    if (socketBrowserTotalCountEl) {
      socketBrowserTotalCountEl.textContent = String(allSocketRows.length);
    }
    if (socketBrowserCatalogTotalEl) {
      socketBrowserCatalogTotalEl.textContent = String(Object.keys(catalogCounts).length);
    }
    if (socketBrowserCategoryTotalEl) {
      socketBrowserCategoryTotalEl.textContent = String(Object.keys(categoryCounts).length);
    }
    if (socketBrowserTypeTotalEl) {
      socketBrowserTypeTotalEl.textContent = String(Object.keys(valueTypeCounts).length);
    }

    appendFilterOptions(socketBrowserCatalogEl, catalogCounts, catalogLabels);
    appendFilterOptions(socketBrowserCategoryEl, categoryCounts, null);
    appendFilterOptions(socketBrowserTypeEl, valueTypeCounts, null);

    if (socketBrowserSearchEl) {
      socketBrowserSearchEl.addEventListener('input', renderSocketBrowserList);
    }
    if (socketBrowserCatalogEl) {
      socketBrowserCatalogEl.addEventListener('change', renderSocketBrowserList);
    }
    if (socketBrowserCategoryEl) {
      socketBrowserCategoryEl.addEventListener('change', renderSocketBrowserList);
    }
    if (socketBrowserTypeEl) {
      socketBrowserTypeEl.addEventListener('change', renderSocketBrowserList);
    }

    renderSocketBrowserList();
    renderSocketBrowserDetail(null);
  };

  if (persistedDraft && typeof persistedDraft === 'object' && persistedDraft.values && typeof persistedDraft.values === 'object') {
    localDraftState.values = persistedDraft.values;
  }

  var persistDraftAction = function (action, socket, payload) {
    if (draftUpdateEndpoint === '' || csrfToken === '') {
      return;
    }

    var socketId = socket && typeof socket.id === 'string' ? socket.id : '';
    if (socketId !== EXPERIMENT_SOCKET_ID) {
      return;
    }

    var requestBody = {
      csrf: csrfToken,
      action: action,
      socket_id: socketId,
      default_value: typeof socket.default_value === 'string' ? socket.default_value : ''
    };
    if (payload && typeof payload === 'object' && typeof payload.proposed_value === 'string') {
      requestBody.proposed_value = payload.proposed_value;
    }

    fetch(draftUpdateEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(requestBody)
    }).catch(function () {
      return null;
    });
  };

  var getProposedValue = function (socketId) {
    if (!socketId || typeof socketId !== 'string') {
      return null;
    }

    if (socketId !== EXPERIMENT_SOCKET_ID) {
      return null;
    }

    if (!Object.prototype.hasOwnProperty.call(localDraftState.values, socketId)) {
      return null;
    }

    var row = localDraftState.values[socketId] || {};
    if (!Object.prototype.hasOwnProperty.call(row, 'proposed_value')) {
      return null;
    }

    return typeof row.proposed_value === 'string' && row.proposed_value !== ''
      ? row.proposed_value
      : null;
  };

  var getExperimentSocket = function () {
    var catalogIndex = 0;
    while (catalogIndex < socketCatalogs.length) {
      var catalog = socketCatalogs[catalogIndex] || {};
      var sockets = Array.isArray(catalog.sockets) ? catalog.sockets : [];
      var socketIndex = 0;
      while (socketIndex < sockets.length) {
        var socket = sockets[socketIndex] || {};
        if (typeof socket.id === 'string' && socket.id === EXPERIMENT_SOCKET_ID) {
          return socket;
        }
        socketIndex += 1;
      }
      catalogIndex += 1;
    }

    return null;
  };

  var updateDraftDiffPreview = function () {
    var socket = getExperimentSocket();
    var defaultValue = socket && typeof socket.default_value === 'string' && socket.default_value !== ''
      ? socket.default_value
      : 'soft';
    var token = socket && typeof socket.advanced_token === 'string' && socket.advanced_token !== ''
      ? socket.advanced_token
      : '--radius-scale';
    var proposedValue = getProposedValue(EXPERIMENT_SOCKET_ID);

    if (draftDiffSummaryEl) {
      draftDiffSummaryEl.textContent = proposedValue !== null
        ? draftDiffChangedPrefixText + ' ' + proposedValue
        : draftDiffNoChangeText;
    }

    if (draftDiffDefaultEl) {
      draftDiffDefaultEl.textContent = defaultValue;
    }

    if (draftDiffCurrentEl) {
      draftDiffCurrentEl.textContent = currentValueNotConnectedText;
    }

    if (draftDiffProposedEl) {
      draftDiffProposedEl.textContent = proposedValue !== null
        ? proposedValue
        : proposedValueNoDraftText;
    }

    if (draftDiffImpactEl) {
      draftDiffImpactEl.textContent = draftDiffImpactPreviewOnlyText;
    }

    if (draftDiffApplyEl) {
      draftDiffApplyEl.textContent = draftDiffApplyDisabledText;
    }

    if (draftDiffSocketEl) {
      draftDiffSocketEl.textContent = EXPERIMENT_SOCKET_ID;
    }

    if (draftDiffTokenEl) {
      draftDiffTokenEl.textContent = token;
    }

    if (draftDiffSourceEl) {
      draftDiffSourceEl.textContent = draftDiffSourceStudioLocalText;
    }

    if (draftDiffRuntimeEl) {
      draftDiffRuntimeEl.textContent = draftDiffRuntimeNotAppliedText;
    }
  };

  var updateReadinessPanel = function () {
    var checks = validationResult.checks && typeof validationResult.checks === 'object'
      ? validationResult.checks
      : {};

    var checkKeys = Object.keys(checks);
    var checkIndex = 0;
    while (checkIndex < checkKeys.length) {
      var key = checkKeys[checkIndex];
      var passed = checks[key] === true;
      var item = document.querySelector('[data-check-key="' + key + '"]');
      if (item) {
        var icon = item.querySelector('.cs-vc__readiness-icon');
        if (passed) {
          item.classList.add('cs-vc__readiness-item--pass');
          if (icon) {
            icon.textContent = '\u2713';
          }
        } else {
          item.classList.remove('cs-vc__readiness-item--pass');
          if (icon) {
            icon.textContent = '\u2717';
          }
        }
      }
      checkIndex += 1;
    }

    if (readinessStatusEl) {
      var eligible = validationResult.valid === true;
      readinessStatusEl.textContent = eligible
        ? readinessRequestEligibleText
        : readinessRequestNotEligibleText;
      readinessStatusEl.classList.remove('is-eligible', 'is-not-eligible');
      readinessStatusEl.classList.add(eligible ? 'is-eligible' : 'is-not-eligible');
    }

    if (requestApprovalButtonEl) {
      if (requestApprovalState === 'pending' || requestApprovalState === 'already_pending') {
        requestApprovalButtonEl.disabled = true;
      } else {
        var canRequest = validationResult.valid === true;
        requestApprovalButtonEl.disabled = !canRequest;
      }
    }
  };

  var recheckReadiness = function () {
    if (recheckReadinessEndpoint === '') {
      return;
    }

    if (recheckReadinessButtonEl) {
      recheckReadinessButtonEl.disabled = true;
      recheckReadinessButtonEl.textContent = 'Rechecking\u2026';
    }

    var requestBody = {
      csrf: csrfToken
    };

    fetch(recheckReadinessEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(requestBody)
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('Recheck failed');
      }
      return response.json();
    }).then(function (data) {
      if (data && typeof data === 'object' && typeof data.valid === 'boolean') {
        validationResult = data;
        updateReadinessPanel();
      }
    }).catch(function () {
      return null;
    }).then(function () {
      if (recheckReadinessButtonEl) {
        recheckReadinessButtonEl.disabled = false;
        recheckReadinessButtonEl.textContent = 'Recheck readiness';
      }
    });
  };

  var createApprovalRequest = function () {
    if (createApprovalRequestEndpoint === '') {
      return;
    }

    if (requestApprovalButtonEl) {
      requestApprovalButtonEl.disabled = true;
      requestApprovalButtonEl.textContent = approvalRequestCreatingText;
    }

    var requestBody = {
      csrf: csrfToken
    };

    fetch(createApprovalRequestEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(requestBody)
    }).then(function (response) {
      if (response.status === 201) {
        return response.json().then(function (data) {
          requestApprovalState = 'pending';
          if (requestApprovalButtonEl) {
            requestApprovalButtonEl.disabled = true;
            requestApprovalButtonEl.textContent = approvalRequestPendingText;
          }
          if (readinessStatusEl) {
            readinessStatusEl.textContent = approvalRequestPendingText;
            readinessStatusEl.classList.remove('is-eligible', 'is-not-eligible');
            readinessStatusEl.classList.add('is-eligible');
          }
        });
      } else if (response.status === 409) {
        return response.json().then(function (data) {
          requestApprovalState = 'already_pending';
          if (requestApprovalButtonEl) {
            requestApprovalButtonEl.disabled = true;
            requestApprovalButtonEl.textContent = approvalRequestAlreadyPendingText;
          }
          if (readinessStatusEl) {
            readinessStatusEl.textContent = approvalRequestAlreadyPendingText;
            readinessStatusEl.classList.remove('is-eligible', 'is-not-eligible');
            readinessStatusEl.classList.add('is-eligible');
          }
        });
      } else {
        throw new Error('Request creation failed');
      }
    }).catch(function () {
      if (requestApprovalButtonEl) {
        requestApprovalButtonEl.disabled = false;
        requestApprovalButtonEl.textContent = approvalRequestFailedText;
      }
    });
  };

  var getRadiusDraftStatusText = function () {
    return getProposedValue(EXPERIMENT_SOCKET_ID) !== null
      ? localDraftSavedText
      : localDraftEmptyText;
  };

  var updateLocalDraftStatus = function (socketId) {
    var hasRadiusDraft = getProposedValue(EXPERIMENT_SOCKET_ID) !== null;
    var isEditableSocket = socketId === EXPERIMENT_SOCKET_ID;
    var selectedHasDraft = isEditableSocket && hasRadiusDraft;
    var selectedStatusText = selectedHasDraft ? localDraftSavedText : localDraftEmptyText;

    if (lifecycleDraftStatusEl) {
      lifecycleDraftStatusEl.textContent = getRadiusDraftStatusText();
    }

    if (socketLocalDraftStatusEl) {
      socketLocalDraftStatusEl.textContent = selectedStatusText;
    }

    if (localDraftMessageEl) {
      localDraftMessageEl.textContent = selectedHasDraft
        ? localDraftSavedText + ' ' + localDraftRuntimeNoteText
        : localDraftEmptyText;
    }

    updateReadinessPanel();
  };

  var setProposedValue = function (socket, value) {
    if (!socket || typeof socket !== 'object') {
      return;
    }

    var socketId = typeof socket.id === 'string' ? socket.id : '';
    if (socketId !== EXPERIMENT_SOCKET_ID) {
      return;
    }

    var defaultValue = typeof socket.default_value === 'string' && socket.default_value !== ''
      ? socket.default_value
      : '';

    localDraftState.values[socketId] = {
      default_value: defaultValue,
      current_value: null,
      proposed_value: value,
      source: 'studio_local_draft'
    };
  };

  var clearProposedValue = function (socket) {
    if (!socket || typeof socket !== 'object') {
      return;
    }

    var socketId = typeof socket.id === 'string' ? socket.id : '';
    if (socketId !== EXPERIMENT_SOCKET_ID) {
      return;
    }

    if (Object.prototype.hasOwnProperty.call(localDraftState.values, socketId)) {
      delete localDraftState.values[socketId];
    }
  };

  var resetProposedToBaseline = function (socket) {
    if (!socket || typeof socket !== 'object') {
      return;
    }

    var socketId = typeof socket.id === 'string' ? socket.id : '';
    if (socketId !== EXPERIMENT_SOCKET_ID) {
      return;
    }

    var defaultValue = typeof socket.default_value === 'string' && socket.default_value !== ''
      ? socket.default_value
      : null;

    if (defaultValue === null) {
      clearProposedValue(socket);
      return;
    }

    setProposedValue(socket, defaultValue);
  };

  var getControlOptions = function (socket) {
    if (!socket || typeof socket !== 'object') {
      return [];
    }

    var controls = Array.isArray(socket.simple_controls) ? socket.simple_controls : [];
    var allowedValues = Array.isArray(socket.allowed_values) ? socket.allowed_values : [];
    var options = [];

    var idx = 0;
    while (idx < controls.length) {
      var label = typeof controls[idx] === 'string' ? controls[idx] : '';
      if (label !== '') {
        var value = typeof allowedValues[idx] === 'string' && allowedValues[idx] !== ''
          ? allowedValues[idx]
          : label.toLowerCase().replace(/\s+/g, '-');
        options.push({ label: label, value: value });
      }
      idx += 1;
    }

    return options;
  };

  var updateDraftActionStates = function (socket) {
    var socketId = socket && typeof socket.id === 'string' ? socket.id : '';
    var isEditableSocket = socketId === EXPERIMENT_SOCKET_ID;
    var hasDraft = isEditableSocket && getProposedValue(socketId) !== null;

    if (socketSafetyResetStatusEl) {
      socketSafetyResetStatusEl.textContent = isEditableSocket
        ? resetStatusLocalDraftText
        : resetStatusDisabledText;
    }

    if (resetControlButton) {
      resetControlButton.disabled = !hasDraft;
    }

    if (resetSectionButton) {
      resetSectionButton.disabled = !hasDraft;
    }

    if (discardDraftButton) {
      discardDraftButton.disabled = !hasDraft;
    }

    if (restoreLastApprovedButton) {
      restoreLastApprovedButton.disabled = true;
    }

    if (readonlyPillEl) {
      readonlyPillEl.textContent = isEditableSocket
        ? readonlyExperimentEnabledText
        : readonlyNotEditableText;
    }
  };

  var fixtureScaffoldTemplates = {
    'playground-preview': ['cards block', 'buttons row', 'form fields', 'table rows', 'chart block'],
    'shell-preview': ['header bar', 'sidebar nav', 'content wrapper', 'notifications rail', 'footer bar'],
    'component-preview': ['button set', 'card stack', 'input row', 'badge strip', 'chip/tag row'],
    'app-module-preview': ['module header', 'data table', 'detail panel', 'filters row', 'actions bar'],
    'responsive-preview': ['desktop frame', 'tablet frame', 'mobile frame'],
    'state-preview': ['default state', 'hover state', 'disabled state', 'error state', 'loading state'],
    'visualization-preview': ['chart area', 'legend block', 'tooltip block', 'axis labels'],
    'diagram-preview': ['node cluster', 'connector lines', 'flow lane', 'decision node'],
    'workflow-preview': ['task card', 'handoff lane', 'approval card', 'SLA indicator'],
    'print-export-preview': ['report header', 'PDF header', 'report table', 'label block'],
    'accessibility-preview': ['focus ring example', 'contrast panel', 'readable text block', 'keyboard highlight']
  };

  var renderRelationshipList = function (listEl, entries) {
    if (!listEl) {
      return;
    }

    while (listEl.firstChild) {
      listEl.removeChild(listEl.firstChild);
    }

    if (!Array.isArray(entries) || entries.length === 0) {
      var noneItem = document.createElement('li');
      noneItem.textContent = 'No related entries found';
      listEl.appendChild(noneItem);
      return;
    }

    var listIndex = 0;
    while (listIndex < entries.length) {
      var entry = entries[listIndex] || {};
      var label = typeof entry.label === 'string' && entry.label !== '' ? entry.label : '';
      var id = typeof entry.id === 'string' && entry.id !== '' ? entry.id : '';
      var listItem = document.createElement('li');
      listItem.textContent = label + (id !== '' ? ' (' + id + ')' : '');
      listEl.appendChild(listItem);
      listIndex += 1;
    }
  };

  var updateFixtureOutline = function (fixture) {
    if (!fixtureOutlineListEl) {
      return;
    }

    while (fixtureOutlineListEl.firstChild) {
      fixtureOutlineListEl.removeChild(fixtureOutlineListEl.firstChild);
    }

    if (!fixture || typeof fixture !== 'object') {
      var emptyItem = document.createElement('li');
      emptyItem.textContent = 'No preview outline sections found';
      fixtureOutlineListEl.appendChild(emptyItem);
      return;
    }

    var sections = Array.isArray(fixture.outline_sections) ? fixture.outline_sections : [];
    if (sections.length === 0) {
      var noneItem = document.createElement('li');
      noneItem.textContent = 'No preview outline sections found';
      fixtureOutlineListEl.appendChild(noneItem);
      return;
    }

    var sectionIndex = 0;
    while (sectionIndex < sections.length) {
      var sectionText = typeof sections[sectionIndex] === 'string' ? sections[sectionIndex] : '';
      if (sectionText !== '') {
        var sectionItem = document.createElement('li');
        sectionItem.textContent = sectionText;
        fixtureOutlineListEl.appendChild(sectionItem);
      }
      sectionIndex += 1;
    }
  };

  var updateCanvasOutline = function (fixture) {
    if (!canvasOutlineListEl) {
      return;
    }

    while (canvasOutlineListEl.firstChild) {
      canvasOutlineListEl.removeChild(canvasOutlineListEl.firstChild);
    }

    if (!fixture || typeof fixture !== 'object') {
      var emptyItem = document.createElement('li');
      emptyItem.textContent = 'No preview outline sections found';
      canvasOutlineListEl.appendChild(emptyItem);
      return;
    }

    var sections = Array.isArray(fixture.outline_sections) ? fixture.outline_sections : [];
    if (sections.length === 0) {
      var noneItem = document.createElement('li');
      noneItem.textContent = 'No preview outline sections found';
      canvasOutlineListEl.appendChild(noneItem);
      return;
    }

    var sectionIndex = 0;
    while (sectionIndex < sections.length) {
      var sectionText = typeof sections[sectionIndex] === 'string' ? sections[sectionIndex] : '';
      if (sectionText !== '') {
        var sectionItem = document.createElement('li');
        sectionItem.textContent = sectionText;
        canvasOutlineListEl.appendChild(sectionItem);
      }
      sectionIndex += 1;
    }
  };

  var updateFixtureCanvasPlaceholder = function (fixture, fixtureId) {
    if (!canvasScaffoldEl || !canvasTitleEl) {
      return;
    }

    var normalizedId = typeof fixtureId === 'string' ? fixtureId : '';
    var fixtureName = normalizedId;
    var fixtureDescription = 'Static placeholder layout by fixture type. No rendering engine is active.';
    if (fixture && typeof fixture === 'object') {
      fixtureName = typeof fixture.name === 'string' && fixture.name !== '' ? fixture.name : normalizedId;
      fixtureDescription = typeof fixture.description === 'string' && fixture.description !== ''
        ? fixture.description
        : fixtureDescription;
    }

    canvasTitleEl.textContent = fixtureName;
    if (canvasHintEl) {
      canvasHintEl.textContent = fixtureDescription;
    }

    if (canvasFixtureRootEl) {
      canvasFixtureRootEl.setAttribute('data-fixture-id', normalizedId);
    }
    canvasScaffoldEl.setAttribute('data-layout', normalizedId);

    while (canvasScaffoldEl.firstChild) {
      canvasScaffoldEl.removeChild(canvasScaffoldEl.firstChild);
    }

    var scaffold = fixtureScaffoldTemplates[normalizedId];
    if (!Array.isArray(scaffold) || scaffold.length === 0) {
      scaffold = ['static preview placeholder'];
    }

    var blockIndex = 0;
    while (blockIndex < scaffold.length) {
      var blockText = typeof scaffold[blockIndex] === 'string' ? scaffold[blockIndex] : 'static preview placeholder';
      var block = document.createElement('div');
      block.className = 'cs-vc__scaffold-block';
      block.textContent = blockText + ' - static preview placeholder';
      canvasScaffoldEl.appendChild(block);
      blockIndex += 1;
    }

    updateCanvasOutline(fixture);
  };

  var updateFixtureRelationships = function (fixture) {
    if (!fixture || typeof fixture !== 'object') {
      renderRelationshipList(relationshipFixtureToCatalogsEl, []);
      return;
    }

    var relatedCatalogs = Array.isArray(fixture.related_socket_catalogs)
      ? fixture.related_socket_catalogs
      : [];
    var entries = [];
    var relIndex = 0;
    while (relIndex < relatedCatalogs.length) {
      var catalogId = typeof relatedCatalogs[relIndex] === 'string' ? relatedCatalogs[relIndex] : '';
      if (catalogId !== '') {
        var catalog = socketCatalogById[catalogId] || {};
        entries.push({
          id: catalogId,
          label: typeof catalog.name === 'string' && catalog.name !== '' ? catalog.name : catalogId
        });
      }
      relIndex += 1;
    }

    renderRelationshipList(relationshipFixtureToCatalogsEl, entries);
  };

  var updateCatalogRelationships = function (catalogId) {
    if (typeof catalogId !== 'string' || catalogId === '') {
      renderRelationshipList(relationshipCatalogToFixturesEl, []);
      return;
    }

    var entries = [];
    var fixtureIds = Object.keys(fixtureById);
    var fixtureIndex = 0;
    while (fixtureIndex < fixtureIds.length) {
      var fixtureId = fixtureIds[fixtureIndex];
      var fixture = fixtureById[fixtureId] || {};
      var relatedCatalogs = Array.isArray(fixture.related_socket_catalogs)
        ? fixture.related_socket_catalogs
        : [];

      var relIndex = 0;
      var matchesCatalog = false;
      while (relIndex < relatedCatalogs.length) {
        if (relatedCatalogs[relIndex] === catalogId) {
          matchesCatalog = true;
          break;
        }
        relIndex += 1;
      }

      if (matchesCatalog) {
        entries.push({
          id: fixtureId,
          label: typeof fixture.name === 'string' && fixture.name !== '' ? fixture.name : fixtureId
        });
      }

      fixtureIndex += 1;
    }

    renderRelationshipList(relationshipCatalogToFixturesEl, entries);
  };

  var updateSocketDetail = function (socket) {
    if (!socketDetailLabelEl || !socketDetailDescriptionEl || !socketDetailCategoryEl || !socketDetailScopeEl || !socketDetailValueTypeEl || !socketDetailSimpleControlsEl || !socketDetailAdvancedTokenEl || !socketDetailDefaultValueEl || !socketSafetyDefaultValueEl || !socketDetailCurrentValueEl || !socketSafetyProposedValueEl) {
      return;
    }

    if (!socket || typeof socket !== 'object') {
      selectedSocket = null;
      socketDetailLabelEl.textContent = noSocketSelectedText;
      socketDetailDescriptionEl.textContent = '-';
      socketDetailCategoryEl.textContent = '-';
      socketDetailScopeEl.textContent = '-';
      socketDetailValueTypeEl.textContent = '-';
      while (socketDetailSimpleControlsEl.firstChild) {
        socketDetailSimpleControlsEl.removeChild(socketDetailSimpleControlsEl.firstChild);
      }
      var emptySimpleControlChip = document.createElement('button');
      emptySimpleControlChip.type = 'button';
      emptySimpleControlChip.disabled = true;
      emptySimpleControlChip.textContent = noSocketSelectedText;
      socketDetailSimpleControlsEl.appendChild(emptySimpleControlChip);
      socketDetailAdvancedTokenEl.textContent = noAdvancedTokenText;
      socketDetailDefaultValueEl.textContent = '-';
      socketSafetyDefaultValueEl.textContent = '-';
      socketDetailCurrentValueEl.textContent = currentValueNotConnectedText;
      socketSafetyProposedValueEl.textContent = proposedValueNoDraftText;
      updateLocalDraftStatus('');
      updateDraftActionStates(null);
      updateDraftDiffPreview();
      return;
    }

    selectedSocket = socket;
    var simpleControls = Array.isArray(socket.simple_controls) ? socket.simple_controls : [];
    var socketId = typeof socket.id === 'string' ? socket.id : '';
    var isEditableSocket = socketId === EXPERIMENT_SOCKET_ID;
    var controlOptions = getControlOptions(socket);
    var proposedValue = getProposedValue(socketId);
    socketDetailLabelEl.textContent = typeof socket.label === 'string' && socket.label !== '' ? socket.label : noSocketSelectedText;
    socketDetailDescriptionEl.textContent = typeof socket.description === 'string' && socket.description !== '' ? socket.description : '-';
    socketDetailCategoryEl.textContent = typeof socket.category === 'string' && socket.category !== '' ? socket.category : '-';
    socketDetailScopeEl.textContent = typeof socket.scope === 'string' && socket.scope !== '' ? socket.scope : '-';
    socketDetailValueTypeEl.textContent = typeof socket.value_type === 'string' && socket.value_type !== '' ? socket.value_type : '-';
    while (socketDetailSimpleControlsEl.firstChild) {
      socketDetailSimpleControlsEl.removeChild(socketDetailSimpleControlsEl.firstChild);
    }
    if (simpleControls.length === 0) {
      var noControlChip = document.createElement('button');
      noControlChip.type = 'button';
      noControlChip.disabled = true;
      noControlChip.textContent = 'No simple controls';
      socketDetailSimpleControlsEl.appendChild(noControlChip);
    } else {
      var controlIndex = 0;
      while (controlIndex < controlOptions.length) {
        var option = controlOptions[controlIndex] || {};
        if (option.label) {
          var controlChip = document.createElement('button');
          controlChip.type = 'button';
          controlChip.textContent = option.label;

          if (isEditableSocket) {
            controlChip.classList.add('is-editable');
            controlChip.disabled = false;
            if (proposedValue !== null && proposedValue === option.value) {
              controlChip.classList.add('is-active');
            }
            controlChip.addEventListener('click', (function (selectedOptionValue) {
              return function () {
                if (!selectedSocket || selectedSocket.id !== EXPERIMENT_SOCKET_ID) {
                  return;
                }

                setProposedValue(selectedSocket, selectedOptionValue);
                persistDraftAction('set', selectedSocket, { proposed_value: selectedOptionValue });
                updateSocketDetail(selectedSocket);
              };
            }(option.value)));
          } else {
            controlChip.disabled = true;
          }

          socketDetailSimpleControlsEl.appendChild(controlChip);
        }
        controlIndex += 1;
      }
    }
    socketDetailAdvancedTokenEl.textContent = typeof socket.advanced_token === 'string' && socket.advanced_token !== '' ? socket.advanced_token : noAdvancedTokenText;
    var defaultValue = typeof socket.default_value === 'string' && socket.default_value !== '' ? socket.default_value : '-';
    socketDetailDefaultValueEl.textContent = defaultValue;
    socketSafetyDefaultValueEl.textContent = defaultValue;
    socketDetailCurrentValueEl.textContent = currentValueNotConnectedText;
    socketSafetyProposedValueEl.textContent = proposedValue !== null ? proposedValue : proposedValueNoDraftText;
    updateLocalDraftStatus(socketId);
    updateDraftActionStates(socket);
    updateDraftDiffPreview();
  };

  var bindDraftActionHandlers = function () {
    if (resetControlButton) {
      resetControlButton.addEventListener('click', function () {
        if (!selectedSocket || selectedSocket.id !== EXPERIMENT_SOCKET_ID) {
          return;
        }

        resetProposedToBaseline(selectedSocket);
        persistDraftAction('reset', selectedSocket, { proposed_value: typeof selectedSocket.default_value === 'string' ? selectedSocket.default_value : 'soft' });
        updateSocketDetail(selectedSocket);
      });
    }

    if (resetSectionButton) {
      resetSectionButton.addEventListener('click', function () {
        if (!selectedSocket || selectedSocket.id !== EXPERIMENT_SOCKET_ID) {
          return;
        }

        resetProposedToBaseline(selectedSocket);
        persistDraftAction('reset', selectedSocket, { proposed_value: typeof selectedSocket.default_value === 'string' ? selectedSocket.default_value : 'soft' });
        updateSocketDetail(selectedSocket);
      });
    }

    if (discardDraftButton) {
      discardDraftButton.addEventListener('click', function () {
        if (!selectedSocket || selectedSocket.id !== EXPERIMENT_SOCKET_ID) {
          return;
        }

        clearProposedValue(selectedSocket);
        persistDraftAction('discard', selectedSocket, {});
        updateSocketDetail(selectedSocket);
      });
    }
  };

  var highlightActiveSocket = function (socketId) {
    if (!socketListEl) {
      return;
    }

    var buttons = socketListEl.querySelectorAll('.cs-vc__socket-item');
    var itemIndex = 0;
    while (itemIndex < buttons.length) {
      var button = buttons[itemIndex];
      if (!button || !button.classList) {
        itemIndex += 1;
        continue;
      }

      if (button.getAttribute('data-socket-id') === socketId) {
        button.classList.add('is-active');
      } else {
        button.classList.remove('is-active');
      }
      itemIndex += 1;
    }
  };

  var bindSocketListHandlers = function (socketRows) {
    if (!socketListEl) {
      return;
    }

    var buttonNodes = socketListEl.querySelectorAll('.cs-vc__socket-item');
    var buttonIndex = 0;
    while (buttonIndex < buttonNodes.length) {
      var itemButton = buttonNodes[buttonIndex];
      if (itemButton) {
        itemButton.addEventListener('click', function (event) {
          var clicked = event && event.currentTarget ? event.currentTarget : null;
          var socketId = clicked && typeof clicked.getAttribute === 'function'
            ? clicked.getAttribute('data-socket-id') || ''
            : '';
          if (socketId === '') {
            return;
          }

          var rowIndex = 0;
          var match = null;
          while (rowIndex < socketRows.length) {
            var row = socketRows[rowIndex] || {};
            if (typeof row.id === 'string' && row.id === socketId) {
              match = row;
              break;
            }
            rowIndex += 1;
          }

          selectedSocketId = socketId;
          highlightActiveSocket(socketId);
          updateSocketDetail(match);
        });
      }
      buttonIndex += 1;
    }
  };

  var updateFixtureSummary = function (fixtureId) {
    if (!titleEl || !descriptionEl) {
      return;
    }

    if (!Object.prototype.hasOwnProperty.call(fixtureById, fixtureId)) {
      return;
    }

    var selected = fixtureById[fixtureId] || {};
    selectedFixtureId = fixtureId;
    var cardIndex = 0;
    while (cardIndex < fixtureCardNodes.length) {
      var fixtureCard = fixtureCardNodes[cardIndex];
      if (fixtureCard && fixtureCard.classList) {
        if (fixtureCard.getAttribute('data-fixture-card-id') === fixtureId) {
          fixtureCard.classList.add('is-active');
        } else {
          fixtureCard.classList.remove('is-active');
        }
      }
      cardIndex += 1;
    }
    titleEl.textContent = typeof selected.name === 'string' && selected.name !== ''
      ? selected.name
      : fixtureId;
    descriptionEl.textContent = typeof selected.description === 'string' && selected.description !== ''
      ? selected.description
      : 'Selection updates this title/description only. Real rendering remains disabled.';
    updateFixtureCanvasPlaceholder(selected, fixtureId);
    updateFixtureOutline(selected);
    updateFixtureRelationships(selected);

    if (!socketSelector || socketSelector.disabled) {
      return;
    }

    var relatedCatalogs = Array.isArray(selected.related_socket_catalogs)
      ? selected.related_socket_catalogs
      : [];
    if (relatedCatalogs.length === 0) {
      return;
    }

    var relatedCatalogId = typeof relatedCatalogs[0] === 'string' ? relatedCatalogs[0] : '';
    if (relatedCatalogId === '') {
      return;
    }

    var option = socketSelector.querySelector('option[value="' + relatedCatalogId + '"]');
    if (!option) {
      return;
    }

    socketSelector.value = relatedCatalogId;
    updateSocketSummary(relatedCatalogId);
  };

  var updateSocketSummary = function (catalogId) {
    if (!socketTitleEl || !socketDescriptionEl || !socketCountEl || !socketListEl) {
      return;
    }

    if (!Object.prototype.hasOwnProperty.call(socketCatalogById, catalogId)) {
      return;
    }

    var selected = socketCatalogById[catalogId] || {};
    socketTitleEl.textContent = typeof selected.name === 'string' && selected.name !== ''
      ? selected.name
      : catalogId;
    socketDescriptionEl.textContent = typeof selected.description === 'string' && selected.description !== ''
      ? selected.description
      : 'Selection updates this socket catalog summary only. No value editing or runtime consumption.';
    socketCountEl.textContent = String(typeof selected.socket_count === 'number' ? selected.socket_count : 0);
    updateCatalogRelationships(catalogId);

    while (socketListEl.firstChild) {
      socketListEl.removeChild(socketListEl.firstChild);
    }

    var sockets = Array.isArray(selected.sockets) ? selected.sockets : [];
    if (sockets.length === 0) {
      var emptyItem = document.createElement('li');
      emptyItem.textContent = 'No socket catalogs available';
      socketListEl.appendChild(emptyItem);
      selectedSocketId = '';
      updateSocketDetail(null);
      return;
    }

    var listIndex = 0;
    while (listIndex < sockets.length) {
      var socketRow = sockets[listIndex] || {};
      var socketId = typeof socketRow.id === 'string' ? socketRow.id : '';
      var socketLabel = typeof socketRow.label === 'string' && socketRow.label !== ''
        ? socketRow.label
        : socketId;

      var listItem = document.createElement('li');
      var socketButton = document.createElement('button');
      socketButton.type = 'button';
      socketButton.className = 'cs-vc__socket-item';
      socketButton.setAttribute('data-socket-id', socketId);
      socketButton.textContent = socketLabel + (socketId !== '' ? ' (' + socketId + ')' : '');
      listItem.appendChild(socketButton);
      socketListEl.appendChild(listItem);
      listIndex += 1;
    }

    bindSocketListHandlers(sockets);

    var preferredSocketId = selectedSocketId;
    if (preferredSocketId === '') {
      preferredSocketId = typeof sockets[0].id === 'string' ? sockets[0].id : '';
    }

    var socketRowIndex = 0;
    var selectedSocket = null;
    while (socketRowIndex < sockets.length) {
      var socketRow = sockets[socketRowIndex] || {};
      if (typeof socketRow.id === 'string' && socketRow.id === preferredSocketId) {
        selectedSocket = socketRow;
        break;
      }
      socketRowIndex += 1;
    }

    if (!selectedSocket && sockets.length > 0) {
      selectedSocket = sockets[0];
      preferredSocketId = typeof selectedSocket.id === 'string' ? selectedSocket.id : '';
    }

    selectedSocketId = preferredSocketId;
    highlightActiveSocket(preferredSocketId);
    updateSocketDetail(selectedSocket);
  };

  if (selector && !selector.disabled) {
    selector.addEventListener('change', function (event) {
      var value = event && event.target && typeof event.target.value === 'string'
        ? event.target.value
        : '';
      updateFixtureSummary(value);
    });

    var defaultFixtureId = typeof fixtureMetadata.default_fixture_id === 'string'
      ? fixtureMetadata.default_fixture_id
      : '';
    var selectedFixtureId = selector.value || defaultFixtureId;
    updateFixtureSummary(selectedFixtureId);
  }

  if (socketSelector && !socketSelector.disabled) {
    socketSelector.addEventListener('change', function (event) {
      var value = event && event.target && typeof event.target.value === 'string'
        ? event.target.value
        : '';
      updateSocketSummary(value);
    });

    var defaultSocketCatalogId = typeof fixtureMetadata.default_socket_catalog_id === 'string'
      ? fixtureMetadata.default_socket_catalog_id
      : '';
    var selectedSocketCatalogId = socketSelector.value || defaultSocketCatalogId;
    updateSocketSummary(selectedSocketCatalogId);
  }

  bindDraftActionHandlers();

  if (recheckReadinessButtonEl) {
    recheckReadinessButtonEl.addEventListener('click', function () {
      recheckReadiness();
    });
  }

  if (requestApprovalButtonEl) {
    requestApprovalButtonEl.addEventListener('click', function () {
      createApprovalRequest();
    });
  }

  updateLocalDraftStatus(selectedSocketId);
  updateDraftDiffPreview();
  initializeSocketBrowser();

  window.CustomizationStudioVisualCustomizerSkeleton = {
    runtimeStatus: 'preview_skeleton_only_not_connected',
    actionsEnabled: false,
    draftWritesEnabled: false,
    draftPersistenceEnabled: draftUpdateEndpoint !== '',
    registryWritesEnabled: false,
    shellConnectionEnabled: false,
    localDraftMode: 'client_only_radius_scale',
    editableSocketIds: [EXPERIMENT_SOCKET_ID],
    fixtureSelectionEnabled: !!(selector && !selector.disabled),
    socketCatalogSelectionEnabled: !!(socketSelector && !socketSelector.disabled)
  };
}());
