<?php
declare(strict_types=1);

namespace Apps\Shell\Overlay\Compatibility\Adapters\Shared;

final class CameraScanOverlayAdapter
{
    public static function renderScript(): string
    {
        return <<<'HTML'
<script>
(function () {
  'use strict';

  window.OdareHubOS = window.OdareHubOS || {};
  window.OdareHubOS.ShellOverlayAdapters = window.OdareHubOS.ShellOverlayAdapters || {};

  function framework() {
    return window['OdareHubOS.ShellOverlay'] || null;
  }

  window.OdareHubOS.ShellOverlayAdapters.createCameraScanOverlayAdapter = function (options) {
    var config = options && typeof options === 'object' ? options : {};
    var overlayType = 'camera_scan_overlay';
    var instance = null;
    var openState = false;

    function handleControllerClose(reason) {
      instance = null;
      openState = false;
      if (typeof config.onControllerClose === 'function') config.onControllerClose(reason);
    }


    function open(reason) {
      if (openState) {
        return instance;
      }

      var fw = framework();
      if (fw && fw.manager && typeof fw.manager.open === 'function') {
        instance = fw.manager.open({
          type: overlayType,
          overlayType: overlayType,
          reason: reason || 'open',
          surfaceClass: config.surfaceClass || 'camera-scan-overlay',
          surfaceSelector: '.' + (config.surfaceClass || 'camera-scan-overlay'),
          outsideSurfaceSelf: true,
          sourceSurface: config.sourceSurface || 'unknown',
          owner: 'Shell',
          isModal: true,
          preset: 'viewport',
          onClose: handleControllerClose
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

    return {
      open: open,
      close: close
    };
  };
}());
</script>
HTML;
    }
}
