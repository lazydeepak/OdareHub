;(function (window, document) {
  'use strict';

  function initLibraryExplorer(context) {
    var ctx = context && typeof context === 'object' ? context : {};
    var usageLibraryStorageKey = String(ctx.usageLibraryStorageKey || '');
    var libraryItems = document.querySelectorAll('[data-library-item]');
    var libraryRecentList = document.getElementById('gs-library-recent-list');
    var libraryMostUsedList = document.getElementById('gs-library-most-used-list');
    var libraryLastEditedList = document.getElementById('gs-library-last-edited-list');
    var librarySearchResults = document.getElementById('gs-library-search-results');
    var librarySearchResultsList = document.getElementById('gs-library-search-results-list');
    var librarySearchInput = document.getElementById('gs-library-search');
    var libraryQuickLoadPanel = document.getElementById('gs-library-quick-load');
    var libraryModuleSections = document.querySelectorAll('[data-library-module-section]');
    var libraryAppSections = document.querySelectorAll('[data-library-app-section]');
    var libraryAdvancedRegistrySections = document.querySelectorAll('[data-library-advanced-registry]');
    var libraryFilterButtons = document.querySelectorAll('[data-library-filter]');
    var contentOutlineBackButton = document.querySelector('[data-gs-library-back-to-outline]');
    var libraryDetailSheet = document.getElementById('gs-library-detail-sheet');
    var libraryDetailBackdrop = document.querySelector('.library-detail-backdrop');
    var libraryDetailTitle = document.getElementById('gs-library-detail-title');
    var libraryDetailKind = document.querySelector('[data-gs-library-detail-kind]');
    var libraryDetailLabel = document.querySelector('[data-gs-library-detail-label]');
    var libraryDetailNode = document.querySelector('[data-gs-library-detail-node]');
    var libraryDetailOwner = document.querySelector('[data-gs-library-detail-owner]');
    var libraryDetailOwnerPath = document.querySelector('[data-gs-library-detail-owner-path]');
    var libraryDetailResourceType = document.querySelector('[data-gs-library-detail-resource-type]');
    var libraryToolsStatus = document.querySelector('[data-gs-library-tools-status]');
    var libraryToolsList = document.querySelector('[data-gs-library-tools-list]');
    var libraryDetailSupportNote = document.querySelector('[data-gs-library-detail-support-note]');
    var libraryDetailLoadButton = document.getElementById('gs-library-detail-load');
    var libraryDetailInspectButton = document.getElementById('gs-library-detail-inspect');
    var readRecentLibraryIds = typeof ctx.readRecentLibraryIds === 'function' ? ctx.readRecentLibraryIds : function () { return []; };
    var itemMatchesFilter = typeof ctx.itemMatchesFilter === 'function' ? ctx.itemMatchesFilter : function () { return true; };
    var resolveLibraryArtifactKind = typeof ctx.resolveLibraryArtifactKind === 'function' ? ctx.resolveLibraryArtifactKind : function () { return 'unknown'; };
    var isArtifactLoadableInEditor = typeof ctx.isArtifactLoadableInEditor === 'function' ? ctx.isArtifactLoadableInEditor : function () { return false; };
    var isMobileStudio = typeof ctx.isMobileStudio === 'function' ? ctx.isMobileStudio : function () { return false; };
    var switchMobileMode = typeof ctx.switchMobileMode === 'function' ? ctx.switchMobileMode : function () {};
    var getActiveLibraryFilter = typeof ctx.activeLibraryFilterRef === 'function' ? ctx.activeLibraryFilterRef : function () { return 'views'; };
    var setActiveLibraryFilter = typeof ctx.setActiveLibraryFilter === 'function' ? ctx.setActiveLibraryFilter : function () {};
    var renderCreateFlowGuide = typeof ctx.renderCreateFlowGuide === 'function' ? ctx.renderCreateFlowGuide : function () {};
    var strings = ctx.strings && typeof ctx.strings === 'object' ? ctx.strings : {};
    var resourceExplorerCache = {};

    function setResourceExplorerPendingState() {
      if (libraryDetailOwner) {
        libraryDetailOwner.textContent = String(strings.libraryDetailOwnerUnknown || 'Unknown owner');
      }
      if (libraryDetailOwnerPath) {
        libraryDetailOwnerPath.textContent = '...';
      }
      if (libraryDetailResourceType) {
        libraryDetailResourceType.textContent = '...';
      }
      if (libraryToolsStatus) {
        libraryToolsStatus.textContent = String(strings.libraryDetailToolsLoading || 'Loading...');
      }
      if (libraryToolsList) {
        libraryToolsList.innerHTML = '';
      }
    }

    function renderResourceExplorerData(payload) {
      var resource = payload && payload.resource && typeof payload.resource === 'object' ? payload.resource : {};
      var owner = resource.owner && typeof resource.owner === 'object' ? resource.owner : {};
      var ownerType = String(owner.type || '').trim();
      var ownerKey = String(owner.key || '').trim();
      var ownerPath = String(owner.path || '').trim();
      var resourceType = String(resource.resource_type || 'unknown').trim();
      var tools = Array.isArray(payload && payload.eligible_tools) ? payload.eligible_tools : [];

      if (libraryDetailOwner) {
        var ownerLabel = ownerType && ownerKey ? (ownerType + ':' + ownerKey) : (String(strings.libraryDetailOwnerUnknown || 'Unknown owner'));
        libraryDetailOwner.textContent = ownerLabel;
      }
      if (libraryDetailOwnerPath) {
        libraryDetailOwnerPath.textContent = ownerPath || '-';
      }
      if (libraryDetailResourceType) {
        libraryDetailResourceType.textContent = resourceType || 'unknown';
      }

      if (libraryToolsList) {
        if (!tools.length) {
          libraryToolsList.innerHTML = '';
        } else {
          libraryToolsList.innerHTML = tools.map(function (tool) {
            var name = String(tool && tool.name ? tool.name : tool.key || 'tool');
            var status = String(tool && tool.status ? tool.status : 'planned');
            return '<li><span>' +
              name.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') +
              '</span><span class="library-detail-tool-status">' +
              status.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') +
              '</span></li>';
          }).join('');
        }
      }

      if (libraryToolsStatus) {
        libraryToolsStatus.textContent = tools.length
          ? ''
          : String(strings.libraryDetailToolsEmpty || 'No eligible tools');
      }
    }

    function renderResourceExplorerUnavailable() {
      if (libraryDetailOwner) {
        libraryDetailOwner.textContent = String(strings.libraryDetailOwnerUnknown || 'Unknown owner');
      }
      if (libraryDetailOwnerPath) {
        libraryDetailOwnerPath.textContent = '-';
      }
      if (libraryDetailResourceType) {
        libraryDetailResourceType.textContent = 'unknown';
      }
      if (libraryToolsList) {
        libraryToolsList.innerHTML = '';
      }
      if (libraryToolsStatus) {
        libraryToolsStatus.textContent = String(strings.libraryDetailToolsUnavailable || 'Unavailable');
      }
    }

    function fetchAndRenderResourceExplorer(nodeId) {
      var safeNodeId = String(nodeId || '').trim();
      if (safeNodeId === '') {
        renderResourceExplorerUnavailable();
        return;
      }

      if (Object.prototype.hasOwnProperty.call(resourceExplorerCache, safeNodeId)) {
        renderResourceExplorerData(resourceExplorerCache[safeNodeId]);
        return;
      }

      setResourceExplorerPendingState();
      var endpoint = '/apps/studio/tools/resource-explorer?library_item=' + encodeURIComponent(safeNodeId);
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
          throw new Error('resource_explorer_unavailable');
        }
        resourceExplorerCache[safeNodeId] = result.payload;
        renderResourceExplorerData(result.payload);
      })
      .catch(function () {
        renderResourceExplorerUnavailable();
      });
    }

    function readLibraryUsageCounts() {
      try {
        var raw = window.localStorage ? window.localStorage.getItem(usageLibraryStorageKey) : '';
        var parsed = raw ? JSON.parse(raw) : {};
        return parsed && typeof parsed === 'object' ? parsed : {};
      } catch (error) {
        return {};
      }
    }

  function renderButtonList(targetList, ids, emptyLabel) {
    if (!targetList) {
      return;
    }
    var byId = {};
    libraryItems.forEach(function (item) {
      var nodeId = String(item.getAttribute('data-node-id') || '');
      if (nodeId !== '' && !Object.prototype.hasOwnProperty.call(byId, nodeId)) {
        byId[nodeId] = item;
      }
    });

    var html = '';
    ids.slice(0, 6).forEach(function (nodeId) {
      var item = byId[nodeId];
      if (!item) {
        return;
      }
      var kind = String(item.getAttribute('data-library-kind') || '').toLowerCase();
      if (kind !== 'views') {
        return;
      }
      var labelNode = item.querySelector('span');
      var label = labelNode ? String(labelNode.textContent || '').trim() : nodeId;
      html += '<li><button type="button" class="btn gs-load-btn" data-load-library-node data-node-id="' +
        nodeId.replace(/"/g, '&quot;') +
        '">' +
        label.replace(/</g, '&lt;').replace(/>/g, '&gt;') +
        '</button></li>';
    });

    if (html === '') {
      html = '<li class="library-recent-empty">' + emptyLabel + '</li>';
    }
    targetList.innerHTML = html;
  }

  function refreshRecentLibraryList(recentIds) {
    renderButtonList(libraryRecentList, recentIds, String(strings.libraryFilterRecent || ''));
  }

  function refreshMostUsedViewsList() {
    var usageCounts = readLibraryUsageCounts();
    var ids = Object.keys(usageCounts).sort(function (a, b) {
      return (Number(usageCounts[b] || 0) - Number(usageCounts[a] || 0));
    });
    renderButtonList(libraryMostUsedList, ids, String(strings.libraryFilterViews || ''));
  }

  function refreshLastEditedViewsList() {
    var views = Array.prototype.slice.call(libraryItems).filter(function (item) {
      return String(item.getAttribute('data-library-kind') || '').toLowerCase() === 'views';
    });
    views.sort(function (a, b) {
      var aUpdated = String(a.getAttribute('data-library-updated') || '');
      var bUpdated = String(b.getAttribute('data-library-updated') || '');
      return bUpdated.localeCompare(aUpdated);
    });
    var ids = views.map(function (item) { return String(item.getAttribute('data-node-id') || ''); });
    renderButtonList(libraryLastEditedList, ids, String(strings.libraryFilterViews || ''));
  }

  function refreshSearchResultsList(searchTerm) {
    if (!librarySearchResults || !librarySearchResultsList) {
      return;
    }
    if (searchTerm === '') {
      librarySearchResults.classList.add('is-hidden');
      librarySearchResultsList.innerHTML = '<li class="library-recent-empty">' + String(strings.librarySearchResultsEmpty || '') + '</li>';
      return;
    }

    var matches = Array.prototype.slice.call(libraryItems).filter(function (item) {
      var blob = String(item.getAttribute('data-library-search') || '').toLowerCase();
      return blob.indexOf(searchTerm) !== -1;
    });

    function escapeHtml(text) {
      return String(text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    var inspectOnlyNoteTemplate = String(strings.libraryDetailInspectOnlyNote || '');
    var html = '';
    matches.slice(0, 12).forEach(function (item) {
      var nodeId = String(item.getAttribute('data-node-id') || '');
      var artifactKind = resolveLibraryArtifactKind(item, nodeId);
      var isLoadableArtifact = isArtifactLoadableInEditor(artifactKind);
      var labelNode = item.querySelector('span');
      var label = labelNode ? String(labelNode.textContent || '').trim() : nodeId;
      if (isLoadableArtifact && artifactKind === 'app') {
        html += '<li><div class="library-item-row">' +
          '<span>' + escapeHtml(label) + '</span>' +
          '<button type="button" class="btn gs-load-btn" data-load-library-node data-node-id="' + escapeHtml(nodeId) + '">' + String(strings.libraryLoadApp || '') + '</button>' +
          '</div></li>';
      } else if (isLoadableArtifact && artifactKind === 'view') {
        html += '<li><div class="library-item-row">' +
          '<button type="button" class="btn gs-load-btn" data-load-library-node data-node-id="' + escapeHtml(nodeId) + '">' +
          escapeHtml(label) +
          '</button>' +
          '<button type="button" class="btn gs-load-btn" data-load-library-node data-load-component-mode="table" data-node-id="' + escapeHtml(nodeId) + '">' + String(strings.libraryLoadTableOnly || '') + '</button>' +
          '<button type="button" class="btn gs-load-btn" data-load-library-node data-load-component-mode="form" data-node-id="' + escapeHtml(nodeId) + '">' + String(strings.libraryLoadFormOnly || '') + '</button>' +
          '</div></li>';
      } else if (isLoadableArtifact) {
        html += '<li><div class="library-item-row">' +
          '<span>' + escapeHtml(label) + '</span>' +
          '<button type="button" class="btn gs-load-btn" data-load-library-node data-node-id="' + escapeHtml(nodeId) + '">' + String(strings.globalLibraryLoadIntoStudio || '') + '</button>' +
          '</div></li>';
      } else {
        var inspectOnlyNote = String(inspectOnlyNoteTemplate || '').replace('{kind}', artifactKind);
        html += '<li><div class="library-item-row">' +
          '<span>' + escapeHtml(label) + '</span>' +
          '<button type="button" class="btn" data-inspect-library-node data-node-id="' + escapeHtml(nodeId) + '">' + String(strings.inspect || '') + '</button>' +
          '</div><div class="muted">' + escapeHtml(inspectOnlyNote) + '</div></li>';
      }
    });

    if (html === '') {
      html = '<li class="library-recent-empty">' + String(strings.librarySearchResultsEmpty || '') + '</li>';
    }

    librarySearchResultsList.innerHTML = html;
    librarySearchResults.classList.remove('is-hidden');
  }

  function refreshLibraryPanel() {
    if (libraryItems.length === 0) {
      return;
    }

    var recentIds = readRecentLibraryIds();
    refreshRecentLibraryList(recentIds);
    refreshMostUsedViewsList();
    refreshLastEditedViewsList();
    var searchTerm = String((librarySearchInput && librarySearchInput.value) || '').trim().toLowerCase();
    refreshSearchResultsList(searchTerm);

    if (libraryQuickLoadPanel) {
      var showQuickLoad = getActiveLibraryFilter() === 'views' && searchTerm === '';
      libraryQuickLoadPanel.style.display = showQuickLoad ? '' : 'none';
    }

    libraryItems.forEach(function (item) {
      var parentAdvancedRegistry = item.closest('[data-library-advanced-registry]');

      if (searchTerm !== '') {
        var searchBlob = String(item.getAttribute('data-library-search') || '').toLowerCase();
        var matchesSearch = searchBlob.indexOf(searchTerm) !== -1;
        item.setAttribute('data-visible', matchesSearch ? '1' : '0');
        item.style.display = matchesSearch ? '' : 'none';
        return;
      }

      if (parentAdvancedRegistry && !parentAdvancedRegistry.open) {
        item.setAttribute('data-visible', '0');
        item.style.display = 'none';
        return;
      }

      var matchesFilter = itemMatchesFilter(item, recentIds);
      var showItem = matchesFilter;
      if (getActiveLibraryFilter() === 'modules') {
        showItem = false;
      }
      if (getActiveLibraryFilter() === 'apps') {
        showItem = false;
      }
      item.setAttribute('data-visible', showItem ? '1' : '0');
      item.style.display = showItem ? '' : 'none';
    });

    var appsIndexEl = document.getElementById('gs-library-apps-index');
    if (appsIndexEl) {
      var showAppsIndex = getActiveLibraryFilter() === 'apps' && searchTerm === '';
      appsIndexEl.style.display = showAppsIndex ? '' : 'none';
      if (showAppsIndex) {
        Array.prototype.forEach.call(appsIndexEl.querySelectorAll('[data-library-item]'), function (item) {
          item.style.display = '';
        });
      }
    }

    libraryModuleSections.forEach(function (section) {
      var moduleItem = section.closest('[data-library-module]');
      var hasVisibleChildren = !!(moduleItem && moduleItem.querySelector('[data-library-item][data-visible="1"]'));
      var showSection = getActiveLibraryFilter() === 'modules' || hasVisibleChildren;
      if (moduleItem) {
        moduleItem.setAttribute('data-visible', showSection ? '1' : '0');
        moduleItem.style.display = showSection ? '' : 'none';
      }
      section.open = showSection && searchTerm !== '';
    });

    libraryAppSections.forEach(function (section) {
      var appItem = section.closest('[data-library-app]');
      var hasVisibleModules = !!(appItem && appItem.querySelector('[data-library-module][data-visible="1"]'));
      if (appItem) {
        appItem.style.display = hasVisibleModules ? '' : 'none';
      }
      section.open = hasVisibleModules && searchTerm !== '';
    });
  }

  if (contentOutlineBackButton) {
    contentOutlineBackButton.addEventListener('click', function () {
      if (librarySearchInput) {
        librarySearchInput.focus();
      }
      if (librarySearchResults) {
        librarySearchResults.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } else if (librarySearchInput) {
        librarySearchInput.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      if (typeof window.renderCreateFlowGuide === 'function') {
        renderCreateFlowGuide();
      }
    });
  }

  libraryAdvancedRegistrySections.forEach(function (section) {
    section.open = false;
    section.addEventListener('toggle', function () {
      if (section.open) {
        refreshLibraryPanel();
      }
    });
  });

  libraryFilterButtons.forEach(function (button) {
    var buttonFilter = String(button.getAttribute('data-library-filter') || '');
    if (buttonFilter === getActiveLibraryFilter()) {
      button.classList.add('active');
    }
    button.addEventListener('click', function () {
      var requestedFilter = String(button.getAttribute('data-library-filter') || 'all');
      setActiveLibraryFilter(requestedFilter);
      libraryFilterButtons.forEach(function (chip) {
        chip.classList.toggle('active', chip === button);
      });
      refreshLibraryPanel();
    });
  });

  if (librarySearchInput) {
    librarySearchInput.addEventListener('input', refreshLibraryPanel);
  }

  document.addEventListener('studio-library-recent-updated', function () {
    refreshLibraryPanel();
  });

  refreshLibraryPanel();

  function closeLibraryDetailSheet() {
    if (libraryDetailSheet) {
      libraryDetailSheet.classList.remove('is-open');
      libraryDetailSheet.hidden = true;
    }
    if (libraryDetailBackdrop) {
      libraryDetailBackdrop.hidden = true;
    }
  }

  function openLibraryDetailSheet(item, nodeIdOverride) {
    if (!libraryDetailSheet || !item) {
      return;
    }
    var nodeId = String(nodeIdOverride || item.getAttribute('data-node-id') || '').trim();
    if (nodeId === '') {
      return;
    }
    var labelNode = item.querySelector('span');
    var label = labelNode ? String(labelNode.textContent || '').trim() : nodeId;
    var kind = String(item.getAttribute('data-library-kind') || getActiveLibraryFilter() || 'views');
    var artifactKind = resolveLibraryArtifactKind(item, nodeId);
    var isLoadable = isArtifactLoadableInEditor(artifactKind);
    if (libraryDetailTitle) {
      libraryDetailTitle.textContent = String(strings.libraryDetailTitle || 'Selection Detail');
    }
    if (libraryDetailLabel) {
      libraryDetailLabel.textContent = label;
    }
    if (libraryDetailKind) {
      libraryDetailKind.textContent = kind;
    }
    if (libraryDetailNode) {
      libraryDetailNode.textContent = nodeId;
    }
    if (libraryDetailLoadButton) {
      if (isLoadable) {
        libraryDetailLoadButton.hidden = false;
        libraryDetailLoadButton.removeAttribute('disabled');
        libraryDetailLoadButton.setAttribute('data-load-library-node', '1');
        libraryDetailLoadButton.setAttribute('data-node-id', nodeId);
      } else {
        libraryDetailLoadButton.hidden = true;
        libraryDetailLoadButton.setAttribute('disabled', 'disabled');
        libraryDetailLoadButton.removeAttribute('data-load-library-node');
        libraryDetailLoadButton.removeAttribute('data-node-id');
      }
    }
    if (libraryDetailSupportNote) {
      if (isLoadable) {
        libraryDetailSupportNote.hidden = true;
        libraryDetailSupportNote.textContent = '';
      } else {
        var template = String(strings.libraryDetailInspectOnlyNote || '');
        libraryDetailSupportNote.textContent = String(template || '').replace('{kind}', artifactKind);
        libraryDetailSupportNote.hidden = false;
      }
    }
    if (libraryDetailInspectButton) {
      libraryDetailInspectButton.setAttribute('data-node-id', nodeId);
    }
    if (libraryDetailBackdrop) {
      libraryDetailBackdrop.hidden = false;
    }
    libraryDetailSheet.hidden = false;
    fetchAndRenderResourceExplorer(nodeId);
    window.requestAnimationFrame(function () {
      libraryDetailSheet.classList.add('is-open');
    });
  }

  document.querySelectorAll('[data-library-detail-close]').forEach(function (button) {
    button.addEventListener('click', closeLibraryDetailSheet);
  });

  if (libraryDetailLoadButton) {
    libraryDetailLoadButton.addEventListener('click', function () {
      closeLibraryDetailSheet();
      switchMobileMode('editor');
    });
  }

  if (libraryDetailInspectButton) {
    libraryDetailInspectButton.addEventListener('click', function () {
      var nodeId = String(libraryDetailInspectButton.getAttribute('data-node-id') || '').trim();
      if (nodeId !== '') {
        window.location.href = '/apps/studio?library_item=' + encodeURIComponent(nodeId);
        return;
      }
      closeLibraryDetailSheet();
    });
  }

  document.addEventListener('click', function (event) {
    var inspectButton = event.target && event.target.closest ? event.target.closest('[data-inspect-library-node]') : null;
    if (inspectButton) {
      var inspectNodeId = String(inspectButton.getAttribute('data-node-id') || '').trim();
      if (inspectNodeId !== '') {
        event.preventDefault();
        window.location.href = '/apps/studio?library_item=' + encodeURIComponent(inspectNodeId);
      }
      return;
    }

    var loadButton = event.target && event.target.closest ? event.target.closest('[data-load-library-node]') : null;
    if (!isMobileStudio() || !loadButton || loadButton.id === 'gs-library-detail-load') {
      return;
    }
    var nodeId = String(loadButton.getAttribute('data-node-id') || '');
    var item = Array.prototype.slice.call(libraryItems).find(function (candidate) {
      return String(candidate.getAttribute('data-node-id') || '') === nodeId;
    });
    if (item) {
      event.preventDefault();
      event.stopPropagation();
      openLibraryDetailSheet(item, nodeId);
    }
  }, true);

  document.addEventListener('click', function (event) {
    var row = event.target && event.target.closest ? event.target.closest('.library-item-row') : null;
    if (!isMobileStudio() || !row || event.target.closest('[data-load-library-node]')) {
      return;
    }
    var item = row.closest('[data-library-item]');
    if (item) {
      openLibraryDetailSheet(item, item.getAttribute('data-node-id') || '');
    }
  });

  }

  window.gsInitLibraryExplorer = initLibraryExplorer;
}(window, document));
