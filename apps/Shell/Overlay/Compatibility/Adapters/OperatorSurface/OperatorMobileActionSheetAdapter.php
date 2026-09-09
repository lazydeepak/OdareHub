<?php
declare(strict_types=1);

namespace Apps\Shell\Overlay\Compatibility\Adapters\OperatorSurface;

final class OperatorMobileActionSheetAdapter
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

  window.OdareHubOS.ShellOverlayAdapters.createOperatorMobileActionSheetAdapter = function (options) {
    var config = options && typeof options === 'object' ? options : {};
    var overlayType = 'operator_mobile_action_sheet';
    var instance = null;
    var openState = false;

    function handleControllerClose() {
      instance = null;
      openState = false;
      if (typeof config.setLegacyOpen === 'function') config.setLegacyOpen(false);
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
          triggerId: config.triggerId || 'mobileActionBtn',
          surfaceId: config.surfaceId || 'mobileActionPanel',
          backdropId: config.backdropId || 'actionPanelBackdrop',
          owner: 'Shell',
          isModal: true,
          preset: 'drawer',
          scrollTargetSelector: config.scrollTargetSelector || '.main-content',
          onOpen: function () {
            if (typeof config.setLegacyOpen === 'function') config.setLegacyOpen(true);
          },
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
      var closed = false;
      if (fw && fw.manager && typeof fw.manager.close === 'function' && instance && instance.id) {
        closed = fw.manager.close(instance.id, reason || 'closed') === true;
      } else if (fw && fw.manager && typeof fw.manager.close === 'function') {
        closed = fw.manager.close(instance, reason || 'closed') === true;
      }
      instance = null;
      openState = false;
      if (!closed && typeof config.setLegacyOpen === 'function') config.setLegacyOpen(false);
      return reason || 'closed';
    }

    function syncState(open, reason) {
      return open === true ? openOverlay(reason) : close(reason);
    }

    function openOverlay(reason) {
      return open(reason);
    }

    return {
      open: openOverlay,
      close: close,
      syncState: syncState
    };
  };
}());
</script>
HTML;
    }
}
