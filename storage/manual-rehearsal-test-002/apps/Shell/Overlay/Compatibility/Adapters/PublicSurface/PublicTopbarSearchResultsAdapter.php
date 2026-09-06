<?php
declare(strict_types=1);

namespace Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface;

final class PublicTopbarSearchResultsAdapter
{
    public static function renderScript(): string
    {
        return <<<'HTML'
<script>
(function () {
  'use strict';

  window.SusankhyaOS = window.SusankhyaOS || {};
  window.SusankhyaOS.ShellOverlayAdapters = window.SusankhyaOS.ShellOverlayAdapters || {};

  function framework() {
    return window['SusankhyaOS.ShellOverlay'] || null;
  }

  window.SusankhyaOS.ShellOverlayAdapters.createPublicTopbarSearchResultsAdapter = function (options) {
    var config = options && typeof options === 'object' ? options : {};
    var overlayType = 'public_topbar_search_results';
    var instance = null;
    var openState = false;


    function open(reason, state) {
      if (openState) {
        return instance;
      }

      var fw = framework();
      if (fw && fw.manager && typeof fw.manager.open === 'function') {
        instance = fw.manager.open({
          type: overlayType,
          overlayType: overlayType,
          reason: reason || 'open',
          state: state || 'results',
          triggerId: config.triggerId || 'topbarSearchInput',
          surfaceId: config.surfaceId || 'topbarSearchResults',
          owner: 'Shell',
          isModal: false,
          preset: 'dropdown',
          escape: false,
          restoreFocus: false,
          onClose: function () {
            if (typeof config.onControllerClose === 'function') config.onControllerClose();
          }
        }) || instance;
      }

      openState = true;
      return instance;
    }

    function close(reason) {
      if (!openState && !instance) {
        return reason || 'closed';
      }

      var fw = framework();
      if (fw && fw.manager && typeof fw.manager.close === 'function' && instance && instance.id) {
        fw.manager.close(instance.id, reason || 'closed');
      } else if (fw && fw.manager && typeof fw.manager.close === 'function') {
        fw.manager.close(instance, reason || 'closed');
      }
      instance = null;
      openState = false;
      return reason || 'closed';
    }

    function syncState(state, reason) {
      var normalized = String(state || '').trim().toLowerCase();
      if (normalized === 'results' || normalized === 'empty') {
        return open(reason || normalized, normalized);
      }
      return close(reason || normalized || 'idle');
    }

    return {
      open: open,
      close: close,
      syncState: syncState
    };
  };
}());
</script>
HTML;
    }
}
