(function(){
  const previewTab = document.querySelector('[data-cte-live-preview-tab]');
  const previewTokens = document.querySelector('[data-cte-preview-tokens]');
  const connectionStatus = document.querySelector('[data-cte-preview-connection]');
  if (!previewTab || !previewTokens) return;

  function applyTokens(tokens) {
    if (!tokens || typeof tokens !== 'object' || Array.isArray(tokens)) return;

    var cssText = '';
    for (var key in tokens) {
      if (!Object.prototype.hasOwnProperty.call(tokens, key)) continue;
      if (!/^[a-zA-Z0-9_-]+$/.test(key)) continue;
      var value = String(tokens[key] || '');
      if (value === '' || /[{};]/.test(value)) continue;
      cssText += '  --' + key + ': ' + value + ';\n';
    }

    previewTokens.textContent = '[data-cte-preview-shell] {\n' + cssText + '}';
    if (connectionStatus) {
      connectionStatus.textContent = connectionStatus.getAttribute('data-connected-label') || 'Live';
      connectionStatus.classList.add('is-ok');
    }
  }

  window.addEventListener('message', function(event) {
    if (event.origin !== window.location.origin) return;
    if (!window.opener || event.source !== window.opener) return;
    if (!event.data || event.data.type !== 'cte-preview-tokens') return;
    applyTokens(event.data.tokens);
  });

  if (window.opener && !window.opener.closed) {
    window.opener.postMessage({ type: 'cte-preview-ready' }, window.location.origin);
  }
})();
