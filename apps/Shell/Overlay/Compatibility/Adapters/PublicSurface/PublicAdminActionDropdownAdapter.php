<?php
declare(strict_types=1);

namespace Apps\Shell\Overlay\Compatibility\Adapters\PublicSurface;

final class PublicAdminActionDropdownAdapter
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

  window.OdareHubOS.ShellOverlayAdapters.createPublicAdminActionDropdownAdapter = function (options) {
    var config = options && typeof options === 'object' ? options : {};
    var overlayType = 'public_admin_action_dropdown';
    var instance = null;

    function legacySetOpen(open) {
      if (typeof config.setLegacyOpen === 'function') {
        config.setLegacyOpen(open === true);
      }
    }


    function open(reason) {

      var fw = framework();
      if (fw && fw.manager && typeof fw.manager.open === 'function') {
        instance = fw.manager.open({
          type: overlayType,
          overlayType: overlayType,
          reason: reason || 'open',
          triggerId: config.triggerId || 'topbarActionBtn',
          surfaceId: config.surfaceId || 'topbarActionDropdown',
          owner: 'Shell',
          isModal: false,
          preset: 'dropdown',
          onOpen: function () { legacySetOpen(true); },
          onClose: function () { legacySetOpen(false); }
        }) || instance;
      }

      return instance;
    }

    function close(reason) {
      var fw = framework();
      var closed = false;
      if (fw && fw.manager && typeof fw.manager.close === 'function' && instance && instance.id) {
        closed = fw.manager.close(instance.id, reason || 'closed') === true;
      } else if (fw && fw.manager && typeof fw.manager.close === 'function') {
        closed = fw.manager.close(instance, reason || 'closed') === true;
      }
      instance = null;
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
