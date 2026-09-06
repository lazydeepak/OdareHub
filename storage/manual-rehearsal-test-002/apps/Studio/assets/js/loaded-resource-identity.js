(function (window, document) {
  'use strict';

  function syncLoadedResourceIdentity(context) {
    var ctx = context && typeof context === 'object' ? context : {};
    var strings = ctx.strings && typeof ctx.strings === 'object' ? ctx.strings : {};
    var values = ctx.values && typeof ctx.values === 'object' ? ctx.values : {};
    var fallbackNotLoaded = String(strings.notLoaded || 'Not loaded');

    var ownerAppValue = String(values.ownerAppValue || '');
    var ownerModuleValue = String(values.ownerModuleValue || '');
    var resourceTypeValue = String(values.resourceTypeValue || '');
    var resourceKeyValue = String(values.resourceKeyValue || '');
    var modeValue = String(values.modeValue || '');
    var sourcePathValue = String(values.sourcePathValue || '');
    var identityPanels = document.querySelectorAll('[data-gs-loaded-identity-panel]');

    Array.prototype.forEach.call(identityPanels, function (panel) {
      if (!panel) {
        return;
      }
      var ownerAppNode = panel.querySelector('[data-gs-loaded-owner-app]');
      var ownerModuleNode = panel.querySelector('[data-gs-loaded-module]');
      var resourceTypeNode = panel.querySelector('[data-gs-loaded-resource-type]');
      var resourceKeyNode = panel.querySelector('[data-gs-loaded-resource-key]');
      var modeNode = panel.querySelector('[data-gs-loaded-mode]');
      var sourcePathNode = panel.querySelector('[data-gs-loaded-source-path]');
      var sourcePathRow = panel.querySelector('[data-gs-loaded-source-path-row]');

      if (ownerAppNode) {
        ownerAppNode.textContent = ownerAppValue !== '' ? ownerAppValue : fallbackNotLoaded;
      }
      if (ownerModuleNode) {
        ownerModuleNode.textContent = ownerModuleValue !== '' ? ownerModuleValue : fallbackNotLoaded;
      }
      if (resourceTypeNode) {
        resourceTypeNode.textContent = resourceTypeValue !== '' ? resourceTypeValue : fallbackNotLoaded;
      }
      if (resourceKeyNode) {
        resourceKeyNode.textContent = resourceKeyValue !== '' ? resourceKeyValue : fallbackNotLoaded;
      }
      if (modeNode) {
        modeNode.textContent = modeValue !== '' ? modeValue : fallbackNotLoaded;
      }
      if (sourcePathNode) {
        sourcePathNode.textContent = sourcePathValue !== '' ? sourcePathValue : fallbackNotLoaded;
      }
      if (sourcePathRow) {
        sourcePathRow.hidden = sourcePathValue === '';
      }
    });

    document.dispatchEvent(new window.CustomEvent('studio-loaded-resource-identity-updated', {
      detail: {
        ownerApp: ownerAppValue,
        module: ownerModuleValue,
        resourceType: resourceTypeValue,
        resourceKey: resourceKeyValue,
        mode: modeValue,
        sourcePath: sourcePathValue,
        isLoaded: !!ctx.inUpgrade
      }
    }));
  }

  window.gsSyncLoadedResourceIdentity = syncLoadedResourceIdentity;
}(window, document));
