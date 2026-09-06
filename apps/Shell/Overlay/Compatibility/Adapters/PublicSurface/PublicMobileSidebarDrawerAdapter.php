<?php
declare(strict_types=1);

namespace Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface;

final class PublicMobileSidebarDrawerAdapter
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

  window.SusankhyaOS.ShellOverlayAdapters.createPublicMobileSidebarDrawerAdapter = function (options) {
    var config = options && typeof options === 'object' ? options : {};
    var overlayType = 'public_mobile_sidebar_drawer';
    var instance = null;
    var openState = false;

    function legacySetOpen(open) {
      if (typeof config.setLegacyOpen === 'function') {
        config.setLegacyOpen(open === true);
      }
    }

    function handleControllerClose() {
      instance = null;
      openState = false;
      legacySetOpen(false);
    }


    function open(reason) {
      if (openState) {
        legacySetOpen(true);
        return instance;
      }

      var fw = framework();
      if (fw && fw.manager && typeof fw.manager.open === 'function') {
        instance = fw.manager.open({
          type: overlayType,
          overlayType: overlayType,
          reason: reason || 'open',
          triggerSelector: config.triggerSelector || '[data-sidebar-toggle]',
          surfaceSelector: config.surfaceSelector || '.layout-sidebar',
          backdropId: config.backdropId || 'sidebarBackdrop',
          owner: 'Shell',
          isModal: true,
          preset: 'sidebar',
          onOpen: function () { legacySetOpen(true); },
          onClose: handleControllerClose
        }) || instance;
      }

      openState = true;
      return instance;
    }

    function close(reason) {
      if (!openState && !instance) {
        legacySetOpen(false);
        return reason || 'closed';
      }

      var fw = framework();
      var closed = false;
      if (fw && fw.manager && typeof fw.manager.close === 'function' && instance && instance.id) {
        closed = fw.manager.close(instance.id, reason || 'closed') === true;
      } else if (fw && fw.manager && typeof fw.manager.close === 'function') {
        closed = fw.manager.close(instance, reason || 'closed') === true;
      }
      instance = null;
      openState = false;
      if (!closed) legacySetOpen(false);
      return reason || 'closed';
    }

    function setOpen(open, reason) {
      return open === true ? openOverlay(reason) : close(reason);
    }

    function openOverlay(reason) {
      return open(reason);
    }

    return {
      open: openOverlay,
      close: close,
      setOpen: setOpen
    };
  };
}());
</script>
HTML;
    }
}
