;(function (window, document) {
  'use strict';

  function syncEditWorkbenchState(context) {
    var ctx = context && typeof context === 'object' ? context : {};
    var strings = ctx.strings && typeof ctx.strings === 'object' ? ctx.strings : {};
    var values = ctx.values && typeof ctx.values === 'object' ? ctx.values : {};
    var itemName = String(ctx.itemName || '');
    var inUpgrade = !!ctx.inUpgrade;

    var editBadgeResourceTypeNodes = document.querySelectorAll('[data-gs-edit-badge-resource-type]');
    var editBadgeModeNodes = document.querySelectorAll('[data-gs-edit-badge-mode]');
    var editBadgeOwnerAppNodes = document.querySelectorAll('[data-gs-edit-badge-owner-app]');
    var editBadgeModuleNodes = document.querySelectorAll('[data-gs-edit-badge-module]');
    var workbenchLoadStateNodes = document.querySelectorAll('[data-gs-workbench-load-state]');
    var workbenchBodyNodes = document.querySelectorAll('[data-gs-workbench-body]');

    if (!editBadgeResourceTypeNodes.length && !editBadgeModeNodes.length && !editBadgeOwnerAppNodes.length && !editBadgeModuleNodes.length && !workbenchLoadStateNodes.length && !workbenchBodyNodes.length) {
      return;
    }

    var fallbackNotLoaded = String(strings.notLoaded || 'Not loaded');
    var badgeResourceTypeValue = String(values.resourceTypeValue || '') !== '' ? String(values.resourceTypeValue) : fallbackNotLoaded;
    var badgeModeValue = String(values.modeValue || '') !== '' ? String(values.modeValue) : fallbackNotLoaded;
    var badgeOwnerAppValue = String(values.ownerAppValue || '') !== '' ? String(values.ownerAppValue) : fallbackNotLoaded;
    var badgeModuleValue = String(values.ownerModuleValue || '') !== '' ? String(values.ownerModuleValue) : fallbackNotLoaded;

    function updateEditBadgeState(nodes, value, opts) {
      var options = opts && typeof opts === 'object' ? opts : {};
      var isNotLoaded = String(value || '') === fallbackNotLoaded;
      var isReadOnly = !isNotLoaded && !!options.readOnly;
      Array.prototype.forEach.call(nodes, function (node) {
        if (!node) {
          return;
        }
        var badge = node.closest ? node.closest('.gs-edit-context-badge') : null;
        if (!badge || !badge.classList) {
          return;
        }
        badge.classList.toggle('is-loaded', !isNotLoaded && !isReadOnly);
        badge.classList.toggle('is-not-loaded', isNotLoaded);
        badge.classList.toggle('is-read-only', isReadOnly);
      });
    }

    Array.prototype.forEach.call(editBadgeResourceTypeNodes, function (node) {
      if (node) {
        node.textContent = badgeResourceTypeValue;
      }
    });
    updateEditBadgeState(editBadgeResourceTypeNodes, badgeResourceTypeValue);

    Array.prototype.forEach.call(editBadgeModeNodes, function (node) {
      if (node) {
        node.textContent = badgeModeValue;
      }
    });
    updateEditBadgeState(editBadgeModeNodes, badgeModeValue, {
      readOnly: badgeModeValue === String(strings.modeReadOnly || '')
    });

    Array.prototype.forEach.call(editBadgeOwnerAppNodes, function (node) {
      if (node) {
        node.textContent = badgeOwnerAppValue;
      }
    });
    updateEditBadgeState(editBadgeOwnerAppNodes, badgeOwnerAppValue);

    Array.prototype.forEach.call(editBadgeModuleNodes, function (node) {
      if (node) {
        node.textContent = badgeModuleValue;
      }
    });
    updateEditBadgeState(editBadgeModuleNodes, badgeModuleValue);

    var loadStateText = String(strings.workbenchLoadEmpty || '');
    if (inUpgrade) {
      var loadHintResource = String(values.resourceKeyValue || itemName || '').trim();
      loadStateText = String(strings.workbenchLoadReady || '');
      if (loadHintResource !== '') {
        loadStateText += ' · ' + loadHintResource;
      }
    }

    Array.prototype.forEach.call(workbenchLoadStateNodes, function (node) {
      if (!node) {
        return;
      }
      node.textContent = loadStateText;
    });

    Array.prototype.forEach.call(workbenchBodyNodes, function (node) {
      if (!node || !node.classList) {
        return;
      }
      if (inUpgrade) {
        node.classList.add('is-loaded');
      } else {
        node.classList.remove('is-loaded');
      }
    });
  }

  window.gsSyncEditWorkbenchState = syncEditWorkbenchState;
}(window, document));
