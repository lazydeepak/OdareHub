(function () {
  'use strict';

  var placeholder = document.querySelector('[data-css-live-editor-placeholder]');
  if (!placeholder) {
    return;
  }

  var policyNode = document.currentScript;
  var policy = {};
  var frame = placeholder.querySelector('[data-css-live-editor-preview-frame]');
  var stage = placeholder.querySelector('[data-css-live-editor-preview-stage]');
  var status = placeholder.querySelector('[data-css-live-editor-preview-status]');
  var selected = placeholder.querySelector('[data-css-live-editor-selected]');
  var selectedTag = placeholder.querySelector('[data-css-live-editor-selected-tag]');
  var selectedId = placeholder.querySelector('[data-css-live-editor-selected-id]');
  var selectedClasses = placeholder.querySelector('[data-css-live-editor-selected-classes]');
  var selectedFeed = placeholder.querySelector('[data-css-live-editor-selected-feed]');
  var selectedSource = placeholder.querySelector('[data-css-live-editor-selected-source]');
  var owner = placeholder.querySelector('[data-css-live-editor-owner]');
  var sourceTarget = placeholder.querySelector('[data-css-live-editor-source-target]');
  var cssTarget = placeholder.querySelector('[data-css-live-editor-css-target]');
  var tokenEmpty = placeholder.querySelector('[data-css-live-editor-token-empty]');
  var tokenList = placeholder.querySelector('[data-css-live-editor-token-list]');
  var styleSources = {};
  var sourceResolution = { targets: {}, sources: {} };
  var resolutionStatus = placeholder.querySelector('[data-css-live-editor-resolution-status]');
  var resolutionCandidates = placeholder.querySelector('[data-css-live-editor-resolution-candidates]');
  var resolutionSelectors = placeholder.querySelector('[data-css-live-editor-resolution-selectors]');
  var labels = {
    token: policyNode.getAttribute('data-token-name-label') || 'Token',
    value: policyNode.getAttribute('data-token-value-label') || 'Resolved value',
    property: policyNode.getAttribute('data-token-property-label') || 'CSS property',
    owner: policyNode.getAttribute('data-token-owner-label') || 'Source owner',
    source: policyNode.getAttribute('data-token-source-label') || 'Source CSS'
  };
  var cascadeWinnerLabel = policyNode.getAttribute('data-cascade-winner') || 'Likely winning declaration';
  var cascadeCandidateLabel = policyNode.getAttribute('data-cascade-candidate-label') || 'Candidate declaration';
  var cascadeConservativeLabel = policyNode.getAttribute('data-cascade-conservative') || '';
  var cascadeNoDeclarationLabel = policyNode.getAttribute('data-cascade-no-declaration') || "No declaration sources match this element's identity.";
  var cascadeStatusLabel = policyNode.getAttribute('data-cascade-status') || 'Cascade preview only \u00B7 editing disabled';
  var cascadeSelectorLabel = policyNode.getAttribute('data-cascade-selector-label') || 'Selector';
  var cascadeSourceLabel = policyNode.getAttribute('data-cascade-source-label') || 'Source';
  var cascadeDeclaredLabel = policyNode.getAttribute('data-cascade-declared-label') || 'Declared';
  var cascadeComputedLabel = policyNode.getAttribute('data-cascade-computed-label') || 'Computed';
  var cascadeExactMatchLabel = policyNode.getAttribute('data-cascade-exact-match') || 'Declared value matches computed';
  var cascadeDiffersLabel = policyNode.getAttribute('data-cascade-differs') || 'Declared value differs from computed';
  var cascadeCSSVarNote = policyNode.getAttribute('data-cascade-css-var-note') || 'Resolved by browser cascade';
  var cascadeSelectorNoMatchLabel = policyNode.getAttribute('data-cascade-selector-no-match') || 'Selector does not match selected element';
  var cascadeVarCandidateLabel = policyNode.getAttribute('data-cascade-var-candidate-label') || 'Variable candidate \u00B7 winner not proven';
  var cascadeNoMatchLabel = policyNode.getAttribute('data-cascade-no-match-label') || 'No matching approved source declaration found for selected element.';
  var computedStyleAllowlist = [
    'color', 'background-color', 'border-color', 'font-size',
    'font-weight', 'line-height', 'padding', 'margin',
    'border-radius', 'box-shadow', 'opacity', 'display', 'gap'
  ];
  var themeSelect = placeholder.querySelector('[data-css-live-editor-preview-theme]');
  var targetSelect = placeholder.querySelector('[data-css-live-editor-target-select]');
  var templateSelect = placeholder.querySelector('[data-css-live-editor-template-select]');
  var templateSearch = placeholder.querySelector('[data-css-live-editor-template-search]');
  var routeFields = placeholder.querySelector('[data-css-live-editor-route-fields]');
  var templateFields = placeholder.querySelector('[data-css-live-editor-template-fields]');
  var providerMode = placeholder.querySelector('[data-css-live-editor-mode-provider]');
  var templateMode = placeholder.querySelector('[data-css-live-editor-mode-template]');
  var targetRouteInput = placeholder.querySelector('input[name="target"]');
  var targets = [];
  var templateTargets = [];
  var targetById = {};
  var metadata = {
    target: placeholder.querySelector('[data-css-live-editor-meta-target]'),
    owner: placeholder.querySelector('[data-css-live-editor-meta-owner]'),
    source: placeholder.querySelector('[data-css-live-editor-meta-source]'),
    sourceLabel: placeholder.querySelector('[data-css-live-editor-meta-source-label]'),
    adapter: placeholder.querySelector('[data-css-live-editor-meta-adapter]'),
    mode: placeholder.querySelector('[data-css-live-editor-meta-mode]'),
    theme: placeholder.querySelector('[data-css-live-editor-meta-theme]')
  };
  var nodeSequence = 0;
  var clearHighlightBtn = placeholder.querySelector('[data-css-live-editor-clear-highlight]');
  var selectorPreviewBar = placeholder.querySelector('[data-css-live-editor-selector-preview-bar]');
  var selectorStatusEl = placeholder.querySelector('[data-css-live-editor-selector-status]');
  var activeHighlightSelector = '';
  var declarationPreviewSection = placeholder.querySelector('[data-css-live-editor-declaration-preview]');
  var declarationPreviewContent = placeholder.querySelector('[data-css-live-editor-declaration-content]');
  var computedComparisonSection = placeholder.querySelector('[data-css-live-editor-computed-comparison]');
  var computedComparisonContent = placeholder.querySelector('[data-css-live-editor-computed-content]');
  var cascadeSection = placeholder.querySelector('[data-css-live-editor-cascade]');
  var cascadeContent = placeholder.querySelector('[data-css-live-editor-cascade-content]');

  try {
    policy = JSON.parse(policyNode.getAttribute('data-css-live-editor-sanitizer') || '{}');
  } catch (error) {
    policy = {};
  }
  try {
    styleSources = JSON.parse(policyNode.getAttribute('data-css-live-editor-style-sources') || '{}');
  } catch (error) {
    styleSources = {};
  }
  try {
    sourceResolution = JSON.parse(policyNode.getAttribute('data-css-live-editor-source-resolution') || '{}');
  } catch (error) {
    sourceResolution = { targets: {}, sources: {} };
  }

  try {
    targets = JSON.parse(policyNode.getAttribute('data-css-live-editor-targets') || '[]');
  } catch (error) {
    targets = [];
  }
  targets.forEach(function (target) {
    if (target && typeof target.id === 'string') {
      targetById[target.id] = target;
    }
  });
  try {
    templateTargets = JSON.parse(policyNode.getAttribute('data-css-live-editor-template-targets') || '[]');
  } catch (error) {
    templateTargets = [];
  }
  templateTargets.forEach(function (target) {
    if (target && typeof target.id === 'string') {
      targetById[target.id] = target;
    }
  });

  function setStatus(state, message) {
    if (stage) {
      stage.classList.remove('is-ready', 'is-failed');
      stage.classList.add('is-' + state);
    }
    if (status) {
      status.textContent = message;
    }
  }

  function queryAll(documentNode, selectors) {
    if (!Array.isArray(selectors) || selectors.length === 0) {
      return [];
    }

    try {
      return Array.prototype.slice.call(documentNode.querySelectorAll(selectors.join(',')));
    } catch (error) {
      return [];
    }
  }

  function removeExecutableNodes(documentNode) {
    queryAll(documentNode, policy.remove_selectors).forEach(function (element) {
      element.remove();
    });
  }

  function stripEventAttributes(element) {
    Array.prototype.slice.call(element.attributes || []).forEach(function (attribute) {
      if (attribute.name.toLowerCase().indexOf('on') === 0) {
        element.removeAttribute(attribute.name);
      }
    });
  }

  function disableActions(documentNode) {
    queryAll(documentNode, policy.block_navigation_selectors).forEach(function (element) {
      (policy.blocked_url_attributes || []).forEach(function (attribute) {
        element.removeAttribute(attribute);
      });
      element.setAttribute('aria-disabled', 'true');
      element.setAttribute('tabindex', '-1');
    });

    queryAll(documentNode, policy.disable_selectors).forEach(function (element) {
      if (element.tagName === 'BUTTON') {
        element.setAttribute('type', 'button');
      }
      if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
        element.setAttribute('readonly', 'readonly');
      }
      element.setAttribute('aria-disabled', 'true');
      element.setAttribute('tabindex', '-1');
    });

    queryAll(documentNode, policy.hide_selectors).forEach(function (element) {
      element.setAttribute('hidden', 'hidden');
      element.setAttribute('aria-hidden', 'true');
    });
  }

  function addInspectorAttributes(documentNode) {
    var attributeName = policy.inspector_attribute || 'data-css-live-editor-node-id';

    Array.prototype.slice.call(documentNode.querySelectorAll('*')).forEach(function (element) {
      stripEventAttributes(element);
      nodeSequence += 1;
      element.setAttribute(attributeName, String(nodeSequence));
    });

    var style = documentNode.createElement('style');
    style.setAttribute('data-css-live-editor-inspector-style', '');
    style.textContent = '[' + attributeName + '].css-live-editor-selected {' +
      'outline: 2px solid #2563eb !important;' +
      'outline-offset: 2px !important;' +
      'cursor: crosshair !important;' +
    '}[' + attributeName + '].css-live-editor-highlighted {' +
      'outline: 2px solid #f59e0b !important;' +
      'outline-offset: 2px !important;' +
      'background: rgba(245, 158, 11, 0.08) !important;' +
    '}';
    (documentNode.head || documentNode.documentElement).appendChild(style);
  }

  function describeElement(element) {
    var description = element.tagName.toLowerCase();
    var classes = Array.prototype.slice.call(element.classList || []).filter(function (className) {
      return className !== 'css-live-editor-selected';
    }).slice(0, 3);
    var dataAttributes = Array.prototype.slice.call(element.attributes || []).filter(function (attribute) {
      return attribute.name.indexOf('data-') === 0
        && attribute.name !== (policy.inspector_attribute || 'data-css-live-editor-node-id');
    }).slice(0, 2);

    if (element.id) {
      description += '#' + element.id;
    }
    if (classes.length > 0) {
      description += '.' + classes.join('.');
    }
    dataAttributes.forEach(function (attribute) {
      description += '[' + attribute.name + ']';
    });

    return description;
  }

  function elementIdentity(element) {
    return {
      tag: element.tagName.toLowerCase(),
      id: element.id || '',
      classes: Array.prototype.slice.call(element.classList || []).filter(function (className) {
        return className !== 'css-live-editor-selected';
      })
    };
  }

  function escapePattern(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function selectorMatchesIdentity(selector, identity) {
    var classMatch = identity.classes.some(function (className) {
      return new RegExp('\\.' + escapePattern(className) + '(?![a-zA-Z0-9_-])').test(selector);
    });
    var idMatch = identity.id !== ''
      && new RegExp('#' + escapePattern(identity.id) + '(?![a-zA-Z0-9_-])').test(selector);
    return { matched: classMatch || idMatch, classMatch: classMatch };
  }

  function appendResolutionItem(container, label, value, code) {
    var item = document.createElement('div');
    var badge = document.createElement('span');
    var content = code ? document.createElement('code') : document.createElement('strong');
    badge.textContent = label;
    content.textContent = value;
    item.appendChild(badge);
    item.appendChild(content);
    container.appendChild(item);
  }

  function countSelectorMatches(selector, documentNode) {
    if (!documentNode || !selector) return -1;
    try {
      return documentNode.querySelectorAll(selector).length;
    } catch (e) {
      return -1;
    }
  }

  function appendSelectorChip(container, match, documentNode) {
    var chip = document.createElement('button');
    chip.className = 'css-live-editor__selector-chip';
    chip.setAttribute('type', 'button');
    chip.setAttribute('data-selector', match.selector);
    chip.setAttribute('data-class-match', match.classMatch ? '1' : '0');

    var code = document.createElement('code');
    code.textContent = match.selector;
    chip.appendChild(code);

    if (documentNode) {
      var count = countSelectorMatches(match.selector, documentNode);
      var badge = document.createElement('span');
      badge.className = 'css-live-editor__selector-chip-count';
      if (count < 0) {
        badge.textContent = policyNode.getAttribute('data-selector-unsafe') || 'Selector cannot be previewed safely';
        badge.classList.add('css-live-editor__selector-unsafe');
        chip.setAttribute('data-unsafe', '1');
      } else if (count > 20) {
        badge.textContent = policyNode.getAttribute('data-broad-selector') || 'Broad selector';
        badge.classList.add('css-live-editor__selector-chip-broad');
        chip.setAttribute('data-match-count', String(count));
      } else {
        var label = count === 1
          ? (policyNode.getAttribute('data-match-label') || 'match')
          : (policyNode.getAttribute('data-matches-label') || 'matches');
        badge.textContent = count + ' ' + label;
        chip.setAttribute('data-match-count', String(count));
      }
      chip.appendChild(badge);
    }

    container.appendChild(chip);
  }

  function highlightSelectorInFrame(selector) {
    clearHighlight();
    if (!frame) return;
    try {
      var doc = frame.contentDocument;
      if (!doc) return;
      var elements = Array.prototype.slice.call(doc.querySelectorAll(selector));
      elements.forEach(function (el) {
        el.classList.add('css-live-editor-highlighted');
      });
      activeHighlightSelector = selector;
      if (clearHighlightBtn) clearHighlightBtn.hidden = false;
      if (selectorPreviewBar) selectorPreviewBar.hidden = false;
      if (selectorStatusEl) {
        selectorStatusEl.textContent = policyNode.getAttribute('data-selector-preview-status') || 'Selector preview only \u00B7 editing disabled';
      }
    } catch (e) {
      // Unsafe selector — highlight not possible
    }
  }

  function clearHighlight() {
    if (!frame) return;
    try {
      var doc = frame.contentDocument;
      if (!doc) return;
      var highlighted = doc.querySelectorAll('.css-live-editor-highlighted');
      Array.prototype.forEach.call(highlighted, function (el) {
        el.classList.remove('css-live-editor-highlighted');
      });
    } catch (e) {
      // ignore
    }
    activeHighlightSelector = '';
    var activeChips = placeholder.querySelectorAll('.css-live-editor__selector-chip.is-active');
    Array.prototype.forEach.call(activeChips, function (chip) {
      chip.classList.remove('is-active');
    });
    if (clearHighlightBtn) clearHighlightBtn.hidden = true;
    if (selectorPreviewBar) selectorPreviewBar.hidden = true;
  }

  function findDeclarationsBySelector(selector) {
    var results = [];
    var paths = Object.keys(sourceResolution.sources || {});
    paths.forEach(function (path) {
      var source = sourceResolution.sources[path];
      if (!source || !Array.isArray(source.selectors)) return;
      source.selectors.forEach(function (entry) {
        var entrySelector = typeof entry === 'string' ? entry : (entry.selector || '');
        var entryDeclaration = typeof entry === 'string' ? '' : (entry.declaration || '');
        if (entrySelector === selector && entryDeclaration !== '') {
          results.push({ path: path, declaration: entryDeclaration });
        }
      });
    });
    return results;
  }

  function clearDeclarationPreview() {
    if (declarationPreviewSection) declarationPreviewSection.hidden = true;
    if (declarationPreviewContent) declarationPreviewContent.textContent = '';
    clearComputedComparison();
  }

  function renderDeclarationPreview(selector) {
    if (!declarationPreviewSection || !declarationPreviewContent) return;
    var results = findDeclarationsBySelector(selector);

    declarationPreviewSection.hidden = false;
    declarationPreviewContent.textContent = '';

    if (results.length === 0) {
      var unresolved = document.createElement('p');
      unresolved.className = 'css-live-editor__declaration-unresolved';
      unresolved.textContent = policyNode.getAttribute('data-declaration-unresolved') || 'Declaration block not resolved from approved CSS sources.';
      declarationPreviewContent.appendChild(unresolved);
      return;
    }

    var seenPaths = {};
    results.forEach(function (result) {
      if (!seenPaths[result.path]) {
        seenPaths[result.path] = true;
        var sourceLabel = document.createElement('div');
        sourceLabel.className = 'css-live-editor__declaration-source';
        sourceLabel.innerHTML = '<strong>' + (policyNode.getAttribute('data-declaration-source-label') || 'Source file') + ':</strong> <code>' + escapeHtml(result.path) + '</code>';
        declarationPreviewContent.appendChild(sourceLabel);
      }
    });

    var selectorLabel = document.createElement('div');
    selectorLabel.className = 'css-live-editor__declaration-selector';
    var showSelector = selector;
    if (showSelector.length > 80) {
      showSelector = showSelector.slice(0, 80) + '…';
    }
    selectorLabel.innerHTML = '<strong>' + (policyNode.getAttribute('data-declaration-selector-label') || 'Selector') + ':</strong> <code>' + escapeHtml(showSelector) + '</code>';
    declarationPreviewContent.appendChild(selectorLabel);

    results.forEach(function (result) {
      var pre = document.createElement('pre');
      pre.className = 'css-live-editor__declaration-block';
      pre.textContent = result.declaration;
      declarationPreviewContent.appendChild(pre);
    });

    var statusLine = document.createElement('p');
    statusLine.className = 'css-live-editor__declaration-status';
    statusLine.textContent = policyNode.getAttribute('data-declaration-status') || 'Declaration preview only \u00B7 editing disabled';
    declarationPreviewContent.appendChild(statusLine);
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  function getSelectedOrHighlightedElement() {
    if (!frame) return null;
    try {
      var doc = frame.contentDocument;
      if (!doc) return null;
      var el = doc.querySelector('.css-live-editor-selected');
      if (!el) {
        el = doc.querySelector('.css-live-editor-highlighted');
      }
      return el;
    } catch (e) {
      return null;
    }
  }

  function collectComputedStyles(element) {
    var styles = {};
    if (!element) return styles;
    try {
      var computed = element.ownerDocument.defaultView.getComputedStyle(element);
      computedStyleAllowlist.forEach(function (prop) {
        styles[prop] = computed.getPropertyValue(prop);
      });
    } catch (e) {
    }
    return styles;
  }

  function parseDeclaredDeclaration(selector) {
    var results = findDeclarationsBySelector(selector);
    var props = {};
    results.forEach(function (result) {
      var lines = result.declaration.split(';');
      lines.forEach(function (line) {
        var trimmed = line.trim();
        if (!trimmed) return;
        var colonIndex = trimmed.indexOf(':');
        if (colonIndex < 1) return;
        var prop = trimmed.slice(0, colonIndex).trim();
        var val = trimmed.slice(colonIndex + 1).trim();
        if (prop && val) {
          props[prop] = val;
        }
      });
    });
    return props;
  }

  function isCSSVar(value) {
    return /var\(\s*--/.test(value);
  }

  function clearComputedComparison() {
    if (computedComparisonSection) computedComparisonSection.hidden = true;
    if (computedComparisonContent) computedComparisonContent.textContent = '';
    clearCascade();
  }

  function clearCascade() {
    if (cascadeSection) cascadeSection.hidden = true;
    if (cascadeContent) cascadeContent.textContent = '';
  }

  function renderComputedComparison(computedStyles, declaredProps) {
    if (!computedComparisonSection || !computedComparisonContent) return;

    computedComparisonSection.hidden = false;
    computedComparisonContent.textContent = '';

    var hasDeclaration = declaredProps && Object.keys(declaredProps).length > 0;

    if (!hasDeclaration) {
      var header = document.createElement('p');
      header.className = 'css-live-editor__computed-header';
      header.textContent = policyNode.getAttribute('data-computed-no-declaration') || 'Computed values for selected element';
      computedComparisonContent.appendChild(header);
    }

    var renderedAny = false;
    computedStyleAllowlist.forEach(function (prop) {
      var computedValue = computedStyles[prop];
      if (computedValue === undefined || computedValue === null) return;
      renderedAny = true;

      var row = document.createElement('div');
      row.className = 'css-live-editor__computed-row';

      var propName = document.createElement('code');
      propName.className = 'css-live-editor__computed-prop';
      propName.textContent = prop;
      row.appendChild(propName);

      if (hasDeclaration && declaredProps[prop] !== undefined) {
        var declaredVal = document.createElement('span');
        declaredVal.className = 'css-live-editor__computed-declared';
        declaredVal.textContent = declaredProps[prop];
        row.appendChild(declaredVal);

        var computedVal = document.createElement('span');
        computedVal.className = 'css-live-editor__computed-value';
        computedVal.textContent = computedValue;
        row.appendChild(computedVal);

        var status = document.createElement('span');
        status.className = 'css-live-editor__computed-status';
        if (isCSSVar(declaredProps[prop])) {
          status.textContent = policyNode.getAttribute('data-computed-resolved-label') || 'Resolved by browser cascade';
        } else if (declaredProps[prop] === computedValue) {
          status.textContent = policyNode.getAttribute('data-computed-matched-label') || 'Declared value matches computed';
        } else {
          status.textContent = policyNode.getAttribute('data-computed-differs-label') || 'Declared value differs from computed';
        }
        row.appendChild(status);
      } else {
        var val = document.createElement('span');
        val.className = 'css-live-editor__computed-value';
        val.textContent = computedValue;
        row.appendChild(val);
      }

      computedComparisonContent.appendChild(row);
    });

    if (!renderedAny) {
      var empty = document.createElement('p');
      empty.className = 'css-live-editor__computed-empty';
      empty.textContent = policyNode.getAttribute('data-computed-empty') || 'No computed values available for this element.';
      computedComparisonContent.appendChild(empty);
    }

    var statusLine = document.createElement('p');
    statusLine.className = 'css-live-editor__computed-status-line';
    statusLine.textContent = policyNode.getAttribute('data-computed-status') || 'Computed preview only \u00B7 editing disabled';
    computedComparisonContent.appendChild(statusLine);
  }

  function selectorMatchesElement(selectorText, element) {
    try {
      return element && element.matches && element.matches(selectorText);
    } catch (e) {
      return false;
    }
  }

  function collectCascadeCandidates(identity, element) {
    var candidates = {};
    var target = targetById[frame ? (frame.getAttribute('data-target-id') || '') : ''];
    var targetResolution = target && sourceResolution.targets
      ? sourceResolution.targets[target.id]
      : null;
    var candidatePaths = targetResolution && Array.isArray(targetResolution.candidates)
      ? targetResolution.candidates
      : [];

    if (!targetResolution || candidatePaths.length === 0) return candidates;

    candidatePaths.forEach(function (path) {
      var source = sourceResolution.sources && sourceResolution.sources[path]
        ? sourceResolution.sources[path]
        : { selectors: [] };

      Array.prototype.slice.call(source.selectors || []).forEach(function (entry) {
        var selectorText = typeof entry === 'string' ? entry : (entry.selector || '');
        var declaration = typeof entry === 'string' ? '' : (entry.declaration || '');
        if (!declaration) return;

        var idMatch = selectorMatchesIdentity(selectorText, identity);
        if (!idMatch.matched) return;

        var domMatches = element ? selectorMatchesElement(selectorText, element) : false;

        var lines = declaration.split(';');
        lines.forEach(function (line) {
          var trimmed = line.trim();
          if (!trimmed) return;
          var colonIndex = trimmed.indexOf(':');
          if (colonIndex < 1) return;
          var prop = trimmed.slice(0, colonIndex).trim();
          var val = trimmed.slice(colonIndex + 1).trim();
          if (!prop || !val) return;
          if (!candidates[prop]) {
            candidates[prop] = [];
          }
          var isDuplicate = candidates[prop].some(function (existing) {
            return existing.selector === selectorText && existing.path === path && existing.value === val;
          });
          if (!isDuplicate) {
            candidates[prop].push({
              selector: selectorText,
              path: path,
              value: val,
              declaration: declaration,
              domMatches: domMatches
            });
          }
        });
      });
    });

    return candidates;
  }

  function renderCascadeExplanation(computedStyles) {
    if (!cascadeSection || !cascadeContent) return;

    cascadeSection.hidden = false;
    cascadeContent.textContent = '';

    var matchedEl = getSelectedOrHighlightedElement();
    if (!matchedEl) {
      var emptyMsg = document.createElement('p');
      emptyMsg.className = 'css-live-editor__cascade-empty';
      emptyMsg.textContent = cascadeNoDeclarationLabel;
      cascadeContent.appendChild(emptyMsg);
      return;
    }

    var identity = elementIdentity(matchedEl);
    var candidates = collectCascadeCandidates(identity, matchedEl);
    var propsInAllowlist = computedStyleAllowlist.filter(function (prop) {
      return candidates[prop] && candidates[prop].length > 0;
    });

    var anyDomMatch = false;
    computedStyleAllowlist.forEach(function (prop) {
      var list = candidates[prop] || [];
      list.forEach(function (c) {
        if (c.domMatches) anyDomMatch = true;
      });
    });

    if (!anyDomMatch) {
      if (Object.keys(candidates).length > 0) {
        var noMatchMsg = document.createElement('p');
        noMatchMsg.className = 'css-live-editor__cascade-empty';
        noMatchMsg.textContent = cascadeNoMatchLabel;
        cascadeContent.appendChild(noMatchMsg);
      } else {
        var noCandidates = document.createElement('p');
        noCandidates.className = 'css-live-editor__cascade-empty';
        noCandidates.textContent = cascadeNoDeclarationLabel;
        cascadeContent.appendChild(noCandidates);
      }
      var statusEnd = document.createElement('p');
      statusEnd.className = 'css-live-editor__cascade-status-line';
      statusEnd.textContent = cascadeStatusLabel;
      cascadeContent.appendChild(statusEnd);
      return;
    }

    propsInAllowlist.forEach(function (prop) {
      var propCandidates = candidates[prop];
      var computedValue = computedStyles[prop];

      var group = document.createElement('div');
      group.className = 'css-live-editor__cascade-prop-group';

      var propName = document.createElement('code');
      propName.className = 'css-live-editor__cascade-prop-name';
      propName.textContent = prop;
      group.appendChild(propName);

      var winnerIndex = -1;
      propCandidates.forEach(function (c, idx) {
        if (c.value === computedValue && c.domMatches) {
          winnerIndex = idx;
        }
      });
      if (winnerIndex === -1) {
        propCandidates.forEach(function (c, idx) {
          if (isCSSVar(c.value) && c.domMatches) {
            winnerIndex = idx;
          }
        });
      }

      propCandidates.forEach(function (c, idx) {
        var card = document.createElement('div');
        card.className = 'css-live-editor__cascade-candidate';
        if (idx === winnerIndex && c.domMatches) {
          card.classList.add('is-winner');
          var winnerBadge = document.createElement('span');
          winnerBadge.className = 'css-live-editor__cascade-winner-badge';
          winnerBadge.textContent = cascadeWinnerLabel;
          card.appendChild(winnerBadge);
        } else if (c.domMatches) {
          var candidateBadge = document.createElement('span');
          candidateBadge.className = 'css-live-editor__cascade-winner-badge';
          candidateBadge.textContent = cascadeCandidateLabel;
          card.appendChild(candidateBadge);
        }

        var detailSelector = document.createElement('div');
        detailSelector.className = 'css-live-editor__cascade-detail';
        detailSelector.textContent = cascadeSelectorLabel + ': ';
        var codeSelector = document.createElement('code');
        codeSelector.textContent = c.selector;
        detailSelector.appendChild(codeSelector);
        card.appendChild(detailSelector);

        var detailSource = document.createElement('div');
        detailSource.className = 'css-live-editor__cascade-detail';
        detailSource.textContent = cascadeSourceLabel + ': ';
        var codeSource = document.createElement('code');
        codeSource.textContent = c.path;
        detailSource.appendChild(codeSource);
        card.appendChild(detailSource);

        var detailDeclared = document.createElement('div');
        detailDeclared.className = 'css-live-editor__cascade-detail';
        detailDeclared.textContent = cascadeDeclaredLabel + ': ';
        var codeDeclared = document.createElement('code');
        codeDeclared.textContent = c.value;
        detailDeclared.appendChild(codeDeclared);
        card.appendChild(detailDeclared);

        if (computedValue !== undefined) {
          var detailComputed = document.createElement('div');
          detailComputed.className = 'css-live-editor__cascade-detail';
          detailComputed.textContent = cascadeComputedLabel + ': ';
          var codeComputed = document.createElement('code');
          codeComputed.textContent = computedValue;
          detailComputed.appendChild(codeComputed);
          card.appendChild(detailComputed);
        }

        var statusEl = document.createElement('span');
        statusEl.className = 'css-live-editor__cascade-st';
        if (!c.domMatches && isCSSVar(c.value)) {
          statusEl.textContent = cascadeVarCandidateLabel;
        } else if (!c.domMatches) {
          statusEl.textContent = cascadeSelectorNoMatchLabel;
        } else if (isCSSVar(c.value)) {
          statusEl.textContent = cascadeCSSVarNote;
        } else if (c.value === computedValue) {
          statusEl.textContent = cascadeExactMatchLabel;
        } else {
          statusEl.textContent = cascadeDiffersLabel;
        }
        card.appendChild(statusEl);

        group.appendChild(card);
      });

      cascadeContent.appendChild(group);
    });

    var conservativeNote = document.createElement('p');
    conservativeNote.className = 'css-live-editor__cascade-conservative';
    conservativeNote.textContent = cascadeConservativeLabel;
    cascadeContent.appendChild(conservativeNote);

    var statusLine = document.createElement('p');
    statusLine.className = 'css-live-editor__cascade-status-line';
    statusLine.textContent = cascadeStatusLabel;
    cascadeContent.appendChild(statusLine);
  }

  function clearResolution(message) {
    if (resolutionStatus) resolutionStatus.textContent = message;
    if (resolutionCandidates) resolutionCandidates.textContent = message;
    if (resolutionSelectors) resolutionSelectors.textContent = message;
    if (cssTarget) cssTarget.textContent = message;
    setPanelBadge('selectors', 0);
    setPanelBadge('tokens', 0);
    setPanelBadge('declaration', 0);
    setPanelBadge('computed', 0);
    setPanelBadge('cascade', 0);
    clearHighlight();
    clearDeclarationPreview();
  }

  function renderSourceResolution(target, identity, documentNode) {
    var noneMessage = policyNode.getAttribute('data-resolution-none') || '';
    var targetResolution = target && sourceResolution.targets
      ? sourceResolution.targets[target.id]
      : null;
    var candidatePaths = targetResolution && Array.isArray(targetResolution.candidates)
      ? targetResolution.candidates
      : [];

    if (!targetResolution || candidatePaths.length === 0) {
      clearResolution(noneMessage);
      return;
    }

    var matches = [];
    if (resolutionCandidates) resolutionCandidates.textContent = '';
    if (resolutionSelectors) resolutionSelectors.textContent = '';

    candidatePaths.forEach(function (path) {
      var source = sourceResolution.sources && sourceResolution.sources[path]
        ? sourceResolution.sources[path]
        : { selectors: [] };
      var sourceMatches = [];
      Array.prototype.slice.call(source.selectors || []).forEach(function (entry) {
        var selectorText = typeof entry === 'string' ? entry : (entry.selector || '');
        var match = selectorMatchesIdentity(selectorText, identity);
        if (match.matched) {
          sourceMatches.push({ selector: selectorText, classMatch: match.classMatch, path: path });
          matches.push({ selector: selectorText, classMatch: match.classMatch, path: path });
        }
      });

      if (resolutionCandidates) {
        appendResolutionItem(
          resolutionCandidates,
          sourceMatches.length > 0
            ? (policyNode.getAttribute('data-resolution-class-match') || '')
            : (policyNode.getAttribute('data-resolution-owner-stylesheet') || ''),
          path,
          true
        );
      }
    });

    if (matches.length > 0 && resolutionSelectors) {
      resolutionSelectors.textContent = '';
      matches.slice(0, 30).forEach(function (match) {
        appendSelectorChip(resolutionSelectors, match, documentNode);
      });
    } else if (resolutionSelectors) {
      resolutionSelectors.textContent = noneMessage;
    }

    if (resolutionStatus) {
      resolutionStatus.textContent = policyNode.getAttribute('data-resolution-found') || '';
    }
    if (cssTarget) {
      cssTarget.textContent = candidatePaths[0];
    }
  }

  function updateSelectionMetadata(target, element, documentNode) {
    var identity = elementIdentity(element);
    if (selected) selected.textContent = describeElement(element);
    if (selectedTag) selectedTag.textContent = identity.tag;
    if (selectedId) selectedId.textContent = identity.id || 'None';
    if (selectedClasses) selectedClasses.textContent = identity.classes.length > 0 ? identity.classes.join(' ') : 'None';
    if (selectedFeed) selectedFeed.textContent = target ? (target.adapter_name || target.target_type || '') : '';
    if (selectedSource) selectedSource.textContent = target ? (target.source_hint || target.route || '') : '';
    if (owner) owner.textContent = target ? (target.owner || 'Unknown') : 'Unknown';
    if (sourceTarget) sourceTarget.textContent = target ? (target.source_hint || target.route || '') : '';
    renderSourceResolution(target, identity, documentNode);
  }

  function stylesheetPath(stylesheet) {
    if (!stylesheet || !stylesheet.href) {
      return '';
    }
    try {
      return new URL(stylesheet.href, window.location.href).pathname;
    } catch (error) {
      return '';
    }
  }

  function sourceForStylesheet(stylesheet) {
    var path = stylesheetPath(stylesheet);
    var registered = path && styleSources[path] ? styleSources[path] : null;
    return {
      owner: registered && registered.owner ? registered.owner : 'Unknown',
      source: registered && registered.source ? registered.source : (path || 'Inline stylesheet'),
      target: path
    };
  }

  function selectorMatches(element, selectorText) {
    if (!selectorText) {
      return false;
    }
    try {
      return element.matches(selectorText);
    } catch (error) {
      return false;
    }
  }

  function tokenNames(value) {
    var names = [];
    var pattern = /var\(\s*(--[a-z0-9_-]+)/gi;
    var match;
    while ((match = pattern.exec(value)) !== null) {
      if (names.indexOf(match[1]) === -1) {
        names.push(match[1]);
      }
    }
    return names;
  }

  function collectRuleTokens(ruleList, element, stylesheet, records, seen) {
    Array.prototype.slice.call(ruleList || []).forEach(function (rule) {
      if (rule.type === 1 && selectorMatches(element, rule.selectorText)) {
        var source = sourceForStylesheet(stylesheet);
        Array.prototype.slice.call(rule.style || []).forEach(function (property) {
          var declaredValue = rule.style.getPropertyValue(property);
          var names = property.indexOf('--') === 0 ? [property] : tokenNames(declaredValue);
          names.forEach(function (name) {
            var key = [name, property, source.source].join('|');
            if (seen[key]) {
              return;
            }
            seen[key] = true;
            records.push({
              token: name,
              value: getComputedStyle(element).getPropertyValue(name).trim() || declaredValue.trim(),
              property: property,
              owner: source.owner,
              source: source.source,
              target: source.target
            });
          });
        });
      }

      if (rule.cssRules) {
        collectRuleTokens(rule.cssRules, element, stylesheet, records, seen);
      }
    });
  }

  function inspectTokens(documentNode, element) {
    var records = [];
    var seen = {};
    Array.prototype.slice.call(documentNode.styleSheets || []).forEach(function (stylesheet) {
      try {
        collectRuleTokens(stylesheet.cssRules, element, stylesheet, records, seen);
      } catch (error) {
        // Cross-origin or inaccessible stylesheets are excluded from inspection.
      }
    });
    return records;
  }

  function appendTokenField(documentNode, list, label, value, code) {
    var row = documentNode.createElement('div');
    var term = documentNode.createElement('dt');
    var detail = documentNode.createElement('dd');
    term.textContent = label;
    if (code) {
      var codeNode = documentNode.createElement('code');
      codeNode.textContent = value;
      detail.appendChild(codeNode);
    } else {
      detail.textContent = value;
    }
    row.appendChild(term);
    row.appendChild(detail);
    list.appendChild(row);
  }

  function renderTokens(records) {
    if (!tokenList || !tokenEmpty) {
      return;
    }
    tokenList.textContent = '';
    if (records.length > 0) {
      tokenEmpty.hidden = true;
      tokenEmpty.textContent = '';
    } else {
      tokenEmpty.hidden = false;
    }
    tokenList.hidden = records.length === 0;

    records.forEach(function (record) {
      var article = document.createElement('article');
      var details = document.createElement('dl');
      article.className = 'css-live-editor__token';
      appendTokenField(document, details, labels.token, record.token, true);
      appendTokenField(document, details, labels.value, record.value || 'Unresolved', true);
      appendTokenField(document, details, labels.property, record.property, true);
      appendTokenField(document, details, labels.owner, record.owner, false);
      appendTokenField(document, details, labels.source, record.source, true);
      article.appendChild(details);
      tokenList.appendChild(article);
    });

  }

  function installReadOnlyCapture(documentNode) {
    documentNode.addEventListener('submit', function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }, true);

    documentNode.addEventListener('keydown', function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }, true);

    documentNode.addEventListener('click', function (event) {
      var element = event.target && event.target.nodeType === 1 ? event.target : null;
      event.preventDefault();
      event.stopImmediatePropagation();
      if (!element) {
        return;
      }

      var previous = documentNode.querySelector('.css-live-editor-selected');
      if (previous) {
        previous.classList.remove('css-live-editor-selected');
      }
      element.classList.add('css-live-editor-selected');
      var target = targetById[frame ? (frame.getAttribute('data-target-id') || '') : ''];
      updateSelectionMetadata(target || null, element, documentNode);
      renderTokens(inspectTokens(documentNode, element));
      var computedStyles = collectComputedStyles(element);
      renderComputedComparison(computedStyles, null);
      renderCascadeExplanation(computedStyles);
    }, true);
  }

  function applyPreviewTheme(targetDocument, preference) {
    var normalizedPreference = String(preference || '').toLowerCase().trim();
    var separatorIndex = normalizedPreference.indexOf('-');
    if (separatorIndex < 1 || separatorIndex === normalizedPreference.length - 1) {
      return;
    }

    var mode = normalizedPreference.slice(0, separatorIndex);
    var colorStyle = normalizedPreference.slice(separatorIndex + 1);
    if (mode !== 'system' && mode !== 'dark' && mode !== 'light') {
      return;
    }

    var effectiveTheme = mode;
    if (mode === 'system') {
      effectiveTheme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
    }

    targetDocument.documentElement.setAttribute('data-theme-preference', normalizedPreference);
    targetDocument.documentElement.setAttribute('data-theme-mode', mode);
    targetDocument.documentElement.setAttribute('data-theme', effectiveTheme);
    targetDocument.documentElement.setAttribute('data-color-style', colorStyle);
  }

  function updateMetadata(target, themePreference) {
    if (!target) {
      return;
    }
    if (metadata.target) metadata.target.textContent = target.title || target.id || '';
    if (metadata.owner) metadata.owner.textContent = target.owner || '';
    if (metadata.source) metadata.source.textContent = target.source_hint || target.route || '';
    if (metadata.sourceLabel) {
      metadata.sourceLabel.textContent = target.target_type === 'php_template'
        ? (policyNode.getAttribute('data-template-source-label') || 'Template file')
        : (policyNode.getAttribute('data-provider-source-label') || 'Route / source');
    }
    if (metadata.adapter) metadata.adapter.textContent = target.adapter_name || '';
    if (metadata.mode) metadata.mode.textContent = target.data_mode || '';
    if (metadata.theme) metadata.theme.textContent = themePreference || '';
  }

  function setTargetMode(mode) {
    var templateActive = mode === 'php_template';
    if (routeFields) routeFields.hidden = templateActive;
    if (templateFields) templateFields.hidden = !templateActive;
    if (providerMode) providerMode.checked = !templateActive;
    if (templateMode) templateMode.checked = templateActive;
  }

  function filterTemplateOptions() {
    if (!templateSelect) {
      return;
    }
    var query = String(templateSearch ? templateSearch.value : '').toLowerCase().trim();
    Array.prototype.forEach.call(templateSelect.options, function (option, index) {
      if (index === 0) {
        option.hidden = false;
        return;
      }
      var searchable = option.getAttribute('data-template-search') || '';
      option.hidden = query !== '' && searchable.indexOf(query) === -1;
    });
    if (templateSelect.selectedOptions.length && templateSelect.selectedOptions[0].hidden) {
      templateSelect.value = '';
    }
  }

  function loadPreviewFeed(target) {
    if (!frame || !target || !target.eligible || !target.adapter_id) {
      return;
    }

    var feedUrl = placeholder.getAttribute('data-preview-feed-url') || '';
    if (!feedUrl) {
      return;
    }

    var themePreference = themeSelect ? themeSelect.value : '';
    var params = new URLSearchParams();
    params.set('target_id', target.id);
    params.set('feed', target.adapter_id);
    params.set('theme', themePreference);
    frame.style.pointerEvents = 'none';
    clearResolution(policyNode.getAttribute('data-resolution-none') || '');
    setStatus('loading', placeholder.getAttribute('data-preview-loading') || '');
    frame.setAttribute('data-target-id', target.id);
    frame.setAttribute('src', feedUrl + '?' + params.toString());
    updateMetadata(target, themePreference);
  }

  function prepareTargetFrame(targetFrame) {
    try {
      var targetDocument = targetFrame.contentDocument;
      if (!targetDocument || !targetDocument.documentElement) {
        throw new Error('Target document unavailable');
      }
      if (targetDocument.documentElement.hasAttribute('data-css-live-editor-prepared')) {
        targetFrame.style.pointerEvents = 'auto';
        return;
      }

      removeExecutableNodes(targetDocument);
      disableActions(targetDocument);
      addInspectorAttributes(targetDocument);
      installReadOnlyCapture(targetDocument);
      if (themeSelect) {
        applyPreviewTheme(targetDocument, themeSelect.value);
      }
      targetDocument.documentElement.setAttribute('data-css-live-editor-prepared', 'true');
      targetFrame.style.pointerEvents = 'auto';
      var selectedTarget = targetById[targetFrame.getAttribute('data-target-id') || ''];
      setStatus(
        'ready',
        selectedTarget && selectedTarget.target_type === 'php_template'
          ? (policyNode.getAttribute('data-template-canvas-status') || '')
          : (placeholder.getAttribute('data-preview-ready') || '')
      );
    } catch (error) {
      targetFrame.style.pointerEvents = 'none';
      setStatus('failed', placeholder.getAttribute('data-preview-failed') || '');
    }
  }

  if (themeSelect && !themeSelect.disabled) {
    var runtimePreference = document.documentElement.getAttribute('data-theme-preference') || '';
    var runtimeOptionAvailable = Array.prototype.some.call(themeSelect.options, function (option) {
      return option.value === runtimePreference;
    });
    if (runtimePreference && runtimeOptionAvailable) {
      themeSelect.value = runtimePreference;
    }

    themeSelect.addEventListener('change', function () {
      var target = targetSelect ? targetById[targetSelect.value] : null;
      if (target) {
        loadPreviewFeed(target);
      } else if (frame && frame.contentDocument) {
        applyPreviewTheme(frame.contentDocument, themeSelect.value);
      }
    });
  }

  if (targetSelect) {
    targetSelect.addEventListener('change', function () {
      var target = targetById[targetSelect.value];
      if (!target || !target.eligible) {
        return;
      }
      if (targetRouteInput) {
        targetRouteInput.value = target.route || '';
      }
      if (templateSelect) {
        templateSelect.value = '';
      }
      setTargetMode('provider');
      loadPreviewFeed(target);
    });
  }

  if (providerMode) {
    providerMode.addEventListener('change', function () {
      if (providerMode.checked) setTargetMode('provider');
    });
  }
  if (templateMode) {
    templateMode.addEventListener('change', function () {
      if (templateMode.checked) setTargetMode('php_template');
    });
  }
  if (templateSearch) {
    templateSearch.addEventListener('input', filterTemplateOptions);
  }
  if (templateSelect) {
    templateSelect.addEventListener('change', function () {
      var target = targetById[templateSelect.value];
      if (!target || !target.eligible) {
        return;
      }
      if (targetSelect) {
        targetSelect.selectedIndex = -1;
      }
      if (targetRouteInput) {
        targetRouteInput.value = '';
      }
      setTargetMode('php_template');
      loadPreviewFeed(target);
    });
  }

  if (frame) {
    frame.addEventListener('load', function () {
      prepareTargetFrame(frame);
    });
    if (
      frame.contentDocument
      && frame.contentDocument.readyState === 'complete'
      && frame.contentWindow
      && frame.contentWindow.location.href !== 'about:blank'
    ) {
      prepareTargetFrame(frame);
    }
  }

  if (resolutionSelectors) {
    resolutionSelectors.addEventListener('click', function (event) {
      var chip = event.target && event.target.closest
        ? event.target.closest('.css-live-editor__selector-chip')
        : null;
      if (!chip || chip.getAttribute('data-unsafe') === '1') return;
      var selector = chip.getAttribute('data-selector');
      if (!selector) return;
      var activeChips = resolutionSelectors.querySelectorAll('.css-live-editor__selector-chip.is-active');
      Array.prototype.forEach.call(activeChips, function (c) {
        c.classList.remove('is-active');
      });
      chip.classList.add('is-active');
      highlightSelectorInFrame(selector);
      renderDeclarationPreview(selector);
      openPanel('declaration');
      var matchedEl = getSelectedOrHighlightedElement();
      var computedStyles = matchedEl ? collectComputedStyles(matchedEl) : {};
      renderComputedComparison(computedStyles, parseDeclaredDeclaration(selector));
      openPanel('computed');
      renderCascadeExplanation(computedStyles);
      openPanel('cascade');
    });
  }

  if (clearHighlightBtn) {
    clearHighlightBtn.addEventListener('click', function () {
      clearHighlight();
    });
  }

  var panelWrappers = placeholder.querySelectorAll('[data-csl-panel-wrapper]');

  function openPanel(panelName) {
    var header = placeholder.querySelector('[data-csl-panel="' + panelName + '"]');
    var body = placeholder.querySelector('[data-csl-panel-body="' + panelName + '"]');
    if (header) header.setAttribute('aria-expanded', 'true');
    if (body) body.hidden = false;
  }

  function closePanel(panelName) {
    var header = placeholder.querySelector('[data-csl-panel="' + panelName + '"]');
    var body = placeholder.querySelector('[data-csl-panel-body="' + panelName + '"]');
    if (header) header.setAttribute('aria-expanded', 'false');
    if (body) body.hidden = true;
  }

  function setPanelBadge(panelName, count) {
    var badge = placeholder.querySelector('[data-csl-panel-badge="' + panelName + '"]');
    if (!badge) return;
    if (count > 0) {
      badge.textContent = String(count);
      badge.hidden = false;
    } else {
      badge.hidden = true;
    }
  }

  Array.prototype.forEach.call(panelWrappers, function (wrapper) {
    var header = wrapper.querySelector('.css-live-editor__panel-header');
    var body = wrapper.querySelector('.css-live-editor__panel-body');
    if (!header || !body) return;
    header.addEventListener('click', function () {
      var expanded = header.getAttribute('aria-expanded') === 'true';
      header.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      body.hidden = expanded;
    });
  });

  var originalRenderTokens = renderTokens;
  renderTokens = function (records) {
    originalRenderTokens(records);
    setPanelBadge('tokens', records.length);
  };

  var originalRenderDeclarationPreview = renderDeclarationPreview;
  renderDeclarationPreview = function (selector) {
    originalRenderDeclarationPreview(selector);
    var count = declarationPreviewContent
      ? declarationPreviewContent.querySelectorAll('.css-live-editor__declaration-block').length
      : 0;
    setPanelBadge('declaration', count);
  };

  var originalRenderComputedComparison = renderComputedComparison;
  renderComputedComparison = function (computedStyles, declaredProps) {
    originalRenderComputedComparison(computedStyles, declaredProps);
    var count = computedComparisonContent
      ? computedComparisonContent.querySelectorAll('.css-live-editor__computed-row').length
      : 0;
    setPanelBadge('computed', count);
  };

  var originalRenderCascadeExplanation = renderCascadeExplanation;
  renderCascadeExplanation = function (computedStyles) {
    originalRenderCascadeExplanation(computedStyles);
    var count = cascadeContent
      ? cascadeContent.querySelectorAll('.css-live-editor__cascade-prop-group').length
      : 0;
    setPanelBadge('cascade', count);
  };

  var originalRenderSourceResolution = renderSourceResolution;
  renderSourceResolution = function (target, identity, documentNode) {
    originalRenderSourceResolution(target, identity, documentNode);
    var chipCount = resolutionSelectors
      ? resolutionSelectors.querySelectorAll('.css-live-editor__selector-chip').length
      : 0;
    setPanelBadge('selectors', chipCount);
  };

  placeholder.setAttribute('data-placeholder-ready', 'true');
}());
