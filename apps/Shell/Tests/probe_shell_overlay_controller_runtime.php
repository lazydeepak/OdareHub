<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/apps/Shell/Services/ShellOverlayFramework.php';

use Apps\Shell\Services\ShellOverlayFramework;

$script = ShellOverlayFramework::renderInfrastructureScript();
$script = preg_replace('/^\s*<script>\(function\(\)\{\s*/', '', $script) ?? '';
$script = preg_replace('/\s*\}\)\(\);<\/script>\s*$/', '', $script) ?? '';

$harness = <<<'JS'
global.window = global;
const attributes = {};
const properties = {};
const listeners = {};
const elements = {};
global.document = {
  activeElement: null,
  documentElement: {
    dataset: attributes,
    style: {
      setProperty: function (name, value) { properties[name] = value; },
      removeProperty: function (name) { delete properties[name]; }
    }
  },
  body: {
    style: { overflow: '' },
    classList: {
      add: function (name) { attributes.bodyClass = name; },
      remove: function () { delete attributes.bodyClass; }
    }
  },
  addEventListener: function (type, callback) { listeners[type] = callback; },
  getElementById: function (id) { return elements[id] || null; },
  querySelector: function () { return null; }
};
global.CustomEvent = function (type, options) { this.type = type; this.detail = options.detail; };
global.dispatchEvent = function () {};
JS;

$assertions = <<<'JS'
const manager = window['OdareHubOS.ShellOverlay'].manager;
function assert(condition, message) {
  if (!condition) throw new Error(message);
}
const first = manager.open({ id: 'alpha', type: 'dropdown', preset: 'dropdown' });
assert(first && first.id === 'alpha', 'open returns stable configured id');
assert(first.payload.visualScope === 'local', 'named preset resolves inside controller');
assert(attributes.shellOverlayOpen === 'true', 'open publishes the explicit open-state contract');
assert(attributes.shellOverlayScope === 'local' && attributes.bodyClass === 'has-shell-overlay-visual', 'open applies visual state directly');
assert(manager.activeCount === 1 && manager.isOpen('alpha'), 'open records one active instance');
assert(manager.open({ id: 'alpha', type: 'dropdown' }) === first, 'duplicate open is idempotent');
assert(manager.activeCount === 1, 'duplicate open does not change count');
manager.open({ id: 'beta', type: 'drawer', preset: 'drawer' });
assert(manager.activeCount === 2, 'second overlay is tracked');
assert(document.body.style.overflow === 'hidden', 'drawer preset acquires controller scroll lock');
assert(manager.closeTop() === true && !manager.isOpen('beta'), 'closeTop closes most recent overlay');
assert(document.body.style.overflow === '', 'last drawer close restores prior overflow');
assert(manager.close('missing') === false && manager.activeCount === 1, 'unknown close is a safe no-op');
assert(manager.toggle({ id: 'alpha', type: 'dropdown' }) === null, 'toggle closes an open overlay');
assert(manager.activeCount === 0, 'toggle close clears active state');
assert(!attributes.shellOverlayOpen, 'last close removes the explicit open-state contract');
assert(!attributes.shellOverlayEffect && !attributes.bodyClass, 'last close clears visual state');
const reopened = manager.toggle({ id: 'alpha', type: 'dropdown', visual: 'none' });
assert(reopened && manager.isOpen('alpha'), 'toggle opens a closed overlay');
assert(manager.close(reopened.id) === true, 'close accepts the stable controller id');
assert(manager.close(reopened.id) === false && manager.activeCount === 0, 'repeated close is idempotent');
window['OdareHubOS.ShellOverlay'].visualEffects.setStrength(55);
assert(window['OdareHubOS.ShellOverlay'].visualEffects.getState().strengthOverride === 55, 'strength control belongs to the same controller');
let opened = 0;
let closedReason = '';
let focused = 0;
elements.trigger = { focus: function () { focused++; }, contains: function () { return false; } };
elements.surface = { contains: function (target) { return target === this; } };
manager.open({ id: 'controlled', type: 'dropdown', preset: 'dropdown', triggerId: 'trigger', surfaceId: 'surface', onOpen: function () { opened++; }, onClose: function (reason) { closedReason = reason; } });
assert(opened === 1, 'controller invokes candidate DOM open hook once');
listeners.keydown({ key: 'Escape' });
assert(closedReason === 'escape' && focused === 1 && !manager.isOpen('controlled'), 'controller routes Escape and restores trigger focus');
manager.open({ id: 'outside', type: 'dropdown', preset: 'dropdown', triggerId: 'trigger', surfaceId: 'surface', onClose: function (reason) { closedReason = reason; } });
listeners.click({ target: {} });
assert(closedReason === 'outside-click' && !manager.isOpen('outside'), 'controller routes outside click to top eligible overlay');
manager.open({ id: 'drawer-one', type: 'drawer', preset: 'drawer' });
manager.open({ id: 'drawer-two', type: 'drawer', preset: 'drawer' });
manager.close('drawer-two');
assert(document.body.style.overflow === 'hidden', 'nested drawer close retains reference-counted scroll lock');
manager.close('drawer-one');
manager.open({ id: 'camera', type: 'camera', preset: 'viewport', surfaceId: 'surface', outsideSurfaceSelf: true, onClose: function (reason) { closedReason = reason; } });
listeners.click({ target: elements.surface });
assert(closedReason === 'outside-click' && !manager.isOpen('camera'), 'overlay-root self click dismisses viewport candidate');
manager.open({ id: 'unmount', type: 'drawer', preset: 'drawer' });
window['OdareHubOS.ShellOverlay'].visualEffects.reset();
assert(manager.activeCount === 0, 'controller reset unmounts all active overlay instances');
assert(!attributes.shellOverlayOpen, 'controller reset removes the explicit open-state contract');
console.log('Shell overlay controller runtime probe: 28/28 passed');
JS;

$temporary = tempnam(sys_get_temp_dir(), 'shell-overlay-controller-');
if ($temporary === false) {
    fwrite(STDERR, "Unable to create controller probe file.\n");
    exit(1);
}

file_put_contents($temporary, $harness . "\n" . $script . "\n" . $assertions . "\n");
$output = [];
$status = 1;
exec('node ' . escapeshellarg($temporary) . ' 2>&1', $output, $status);
unlink($temporary);

echo implode("\n", $output) . "\n";
exit($status);
