(function (window, document) {
  'use strict';

  function bindStudioToolPreviewPanel(studioToolsDefaultPreview) {
    var defaults = studioToolsDefaultPreview && typeof studioToolsDefaultPreview === 'object'
      ? studioToolsDefaultPreview
      : {
          name: '',
          group: '',
          status: '',
          purpose: '',
          worksOn: '',
          mustNotOwn: '',
          firstSafe: '',
          backend: '',
          defaultText: ''
        };

    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-gs-tool-preview-trigger]'));
    var previewPanels = Array.prototype.slice.call(document.querySelectorAll('[data-gs-studio-tool-preview]'));
    if (cards.length === 0 || previewPanels.length === 0) {
      return;
    }

    function textFromCard(card, key, fallback) {
      var value = String(card.getAttribute(key) || '').trim();
      return value !== '' ? value : fallback;
    }

    function applyPreview(panel, data, showDefaultHint) {
      var map = {
        '[data-gs-tool-preview-name]': data.name,
        '[data-gs-tool-preview-group]': data.group,
        '[data-gs-tool-preview-status]': data.status,
        '[data-gs-tool-preview-purpose]': data.purpose,
        '[data-gs-tool-preview-works-on]': data.worksOn,
        '[data-gs-tool-preview-must-not-own]': data.mustNotOwn,
        '[data-gs-tool-preview-first-safe]': data.firstSafe,
        '[data-gs-tool-preview-backend]': data.backend
      };
      Object.keys(map).forEach(function (selector) {
        var node = panel.querySelector(selector);
        if (node) {
          node.textContent = map[selector];
        }
      });
      var defaultHint = panel.querySelector('[data-gs-tool-preview-default]');
      if (defaultHint) {
        defaultHint.hidden = !showDefaultHint;
      }
    }

    function setActiveCard(card) {
      cards.forEach(function (node) {
        var isActive = node === card;
        node.classList.toggle('is-preview-active', isActive);
        if (isActive) {
          node.setAttribute('aria-current', 'true');
        } else {
          node.removeAttribute('aria-current');
        }
      });

      var preview = {
        name: textFromCard(card, 'data-tool-name', defaults.name),
        group: textFromCard(card, 'data-tool-group', defaults.group),
        status: textFromCard(card, 'data-tool-status', defaults.status),
        purpose: textFromCard(card, 'data-tool-purpose', defaults.defaultText),
        worksOn: textFromCard(card, 'data-tool-works-on', defaults.worksOn),
        mustNotOwn: textFromCard(card, 'data-tool-must-not-own', defaults.mustNotOwn),
        firstSafe: textFromCard(card, 'data-tool-first-safe', defaults.firstSafe),
        backend: textFromCard(card, 'data-tool-backend', defaults.backend)
      };

      previewPanels.forEach(function (panel) {
        applyPreview(panel, preview, false);
      });

      document.dispatchEvent(new window.CustomEvent('studio-tool-preview-selected', {
        detail: {
          card: card,
          toolId: textFromCard(card, 'data-tool-id', '')
        }
      }));
    }

    var defaultCard = document.querySelector('[data-gs-tool-preview-trigger][data-tool-id="resource_explorer"]') || cards[0];
    if (defaultCard) {
      setActiveCard(defaultCard);
    } else {
      previewPanels.forEach(function (panel) {
        applyPreview(panel, {
          name: defaults.name,
          group: defaults.group,
          status: defaults.status,
          purpose: defaults.defaultText,
          worksOn: defaults.worksOn,
          mustNotOwn: defaults.mustNotOwn,
          firstSafe: defaults.firstSafe,
          backend: defaults.backend
        }, true);
      });
    }

    cards.forEach(function (card) {
      card.addEventListener('click', function () {
        setActiveCard(card);
      });
      card.addEventListener('focus', function () {
        setActiveCard(card);
      });
      card.addEventListener('keydown', function (event) {
        if ((event.key === 'Enter' || event.key === ' ') && card.tagName !== 'A') {
          event.preventDefault();
          setActiveCard(card);
        }
      });
    });
  }

  window.gsBindStudioToolPreviewPanel = bindStudioToolPreviewPanel;
}(window, document));
