(function (window, document) {
  'use strict';

  function initLoadedResourceContextSync(defaults) {
    var resolvedDefaults = defaults && typeof defaults === 'object' ? defaults : {};
    var defaultPreviewBackend = String(resolvedDefaults.defaultPreviewBackend || '');
    var actionStateDefault = String(resolvedDefaults.actionState || '');

    function value(card, key, fallback) {
      var text = String(card && card.getAttribute(key) || '').trim();
      return text !== '' ? text : fallback;
    }

    function hasLoadedValue(valueText, notLoadedText) {
      var text = String(valueText || '').trim();
      var fallback = String(notLoadedText || '').trim();
      if (text === '') {
        return false;
      }
      if (fallback !== '' && text === fallback) {
        return false;
      }
      return true;
    }

    function activeToolCard() {
      return document.querySelector('.gs-studio-tool-card.is-preview-active[data-gs-tool-preview-trigger]')
        || document.querySelector('[data-gs-tool-preview-trigger][data-tool-id="resource_explorer"]')
        || document.querySelector('[data-gs-tool-preview-trigger]');
    }

    function syncWorkbenchContext(cardOverride) {
      var card = cardOverride || activeToolCard();
      var contexts = Array.prototype.slice.call(document.querySelectorAll('[data-gs-workbench-context]'));
      if (!card || contexts.length === 0) {
        return;
      }

      var ownerAppNode = document.querySelector('[data-gs-loaded-owner-app]');
      var moduleNode = document.querySelector('[data-gs-loaded-module]');
      var resourceTypeNode = document.querySelector('[data-gs-loaded-resource-type]');
      var resourceKeyNode = document.querySelector('[data-gs-loaded-resource-key]');

      contexts.forEach(function (contextPanel) {
        var noResourceText = String(contextPanel.getAttribute('data-gs-no-resource-text') || 'No resource loaded');
        var noToolText = String(contextPanel.getAttribute('data-gs-no-tool-text') || 'No tool selected');
        var notLoadedText = String(contextPanel.getAttribute('data-gs-not-loaded') || 'Not loaded');
        var backendPlannedText = String(contextPanel.getAttribute('data-gs-backend-planned') || 'Backend wiring: planned');
        var backendLinkedText = String(contextPanel.getAttribute('data-gs-backend-linked') || 'Backend wiring: linked page only');
        var noExecutionText = String(contextPanel.getAttribute('data-gs-no-execution') || 'No tool execution is active');

        var selectedTool = value(card, 'data-tool-name', noToolText);
        var selectedStatus = value(card, 'data-tool-status', '');
        var selectedBackend = value(card, 'data-tool-backend', defaultPreviewBackend);
        var ownerAppValue = ownerAppNode ? String(ownerAppNode.textContent || '').trim() : '';
        var moduleValue = moduleNode ? String(moduleNode.textContent || '').trim() : '';
        var resourceTypeValue = resourceTypeNode ? String(resourceTypeNode.textContent || '').trim() : '';
        var resourceKeyValue = resourceKeyNode ? String(resourceKeyNode.textContent || '').trim() : '';
        var resourceLoaded = hasLoadedValue(resourceKeyValue, notLoadedText);
        var plannedOrUnwired = selectedStatus.toLowerCase() === 'planned' || selectedBackend.toLowerCase().indexOf('not wired') !== -1;

        var fields = {
          '[data-gs-bridge-selected-tool]': selectedTool,
          '[data-gs-bridge-selected-resource]': resourceLoaded ? resourceKeyValue : noResourceText,
          '[data-gs-bridge-owner-app]': hasLoadedValue(ownerAppValue, notLoadedText) ? ownerAppValue : notLoadedText,
          '[data-gs-bridge-module]': hasLoadedValue(moduleValue, notLoadedText) ? moduleValue : notLoadedText,
          '[data-gs-bridge-resource-type]': hasLoadedValue(resourceTypeValue, notLoadedText) ? resourceTypeValue : notLoadedText,
          '[data-gs-bridge-action-state]': actionStateDefault,
          '[data-gs-bridge-backend-status]': plannedOrUnwired ? backendPlannedText : backendLinkedText,
          '[data-gs-bridge-execution-status]': noExecutionText
        };

        Object.keys(fields).forEach(function (selector) {
          var node = contextPanel.querySelector(selector);
          if (node) {
            node.textContent = fields[selector];
          }
        });
      });
    }

    function init() {
      document.addEventListener('studio-tool-preview-selected', function (event) {
        var card = event && event.detail && event.detail.card ? event.detail.card : null;
        syncWorkbenchContext(card || activeToolCard());
      });

      document.addEventListener('studio-loaded-resource-identity-updated', function () {
        syncWorkbenchContext(activeToolCard());
      });

      syncWorkbenchContext(activeToolCard());
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
      init();
    }
  }

  window.gsInitLoadedResourceContextSync = initLoadedResourceContextSync;
}(window, document));
