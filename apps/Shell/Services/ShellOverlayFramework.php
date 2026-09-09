<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

/**
 * Shell Overlay Framework — infrastructure-only.
 *
 * Phase 1 contract & primitives only.
 *
 * IMPORTANT: This file does not change runtime behavior.
 * Existing overlays continue to work as-is.
 */
final class ShellOverlayFramework
{
    public const HOST_SELECTOR = 'div.shell-overlay#shellOverlay[data-shell-overlay-surface="v1"]';

    /**
     * Return the JS bootstrap payload or tag that registers Shell overlay framework.
     *
     * Phase 1: only defines contracts and wiring points, does NOT take over existing overlays.
     *
     * @return string
     */
    public static function renderInfrastructureScript(): string
    {
        $js = self::infrastructureJs();
        return "\n<script>(function(){\n" . $js . "\n})();</script>\n";
    }

    private static function infrastructureJs(): string
    {
        // Infrastructure only: define a namespace, registry, and manager interfaces.
        // No automatic activation; no event handlers that would close existing overlays.
        return <<<'JS'
(function(){
  const NS = 'OdareHubOS.ShellOverlay';
  if (window[NS]) {
    return;
  }

  // Contract constants
  const HOST_SELECTOR = 'div.shell-overlay#shellOverlay[data-shell-overlay-surface="v1"]';

  const VISUAL_PRESETS = Object.freeze({
    none: { effect: 'none', scope: 'local', strength: 0 },
    local: { effect: 'blur-dim', scope: 'local', strength: 40 },
    page: { effect: 'blur-dim', scope: 'page', strength: 40 },
    viewport: { effect: 'blur-dim', scope: 'viewport', strength: 40 }
  });

  const DEFINITIONS = Object.freeze({
    dropdown: { backdrop: false, escape: true, outsideClick: true, scrollLock: false, restoreFocus: true, visual: 'local' },
    drawer: { backdrop: true, escape: true, outsideClick: true, scrollLock: true, restoreFocus: true, visual: 'page' },
    sidebar: { backdrop: true, escape: false, outsideClick: true, scrollLock: false, restoreFocus: true, visual: 'page' },
    viewport: { backdrop: true, escape: false, outsideClick: true, scrollLock: false, restoreFocus: true, visual: 'viewport' }
  });

  function definition(payload) {
    const name = String(payload && payload.preset ? payload.preset : '').trim();
    return Object.assign({}, DEFINITIONS[name] || DEFINITIONS.dropdown, payload || {}, { preset: name || 'dropdown' });
  }

  function boundElement(payload, idKey, selectorKey) {
    if (payload && payload[idKey]) return document.getElementById(payload[idKey]);
    return payload && payload[selectorKey] ? document.querySelector(payload[selectorKey]) : null;
  }

  function visualPolicy(payload) {
    const name = String(payload && payload.visual ? payload.visual : '').trim();
    if (name && VISUAL_PRESETS[name]) {
      return Object.assign({}, VISUAL_PRESETS[name]);
    }
    return {
      effect: payload && payload.visualEffect ? payload.visualEffect : 'none',
      scope: payload && payload.visualScope ? payload.visualScope : 'local',
      strength: payload && payload.visualStrength !== undefined ? payload.visualStrength : 0
    };
  }

  let visualStrengthOverride = null;
  let scrollLockCount = 0;
  const lockedOverflow = new Map();

  function acquireScrollLock(payload) {
    if (!payload || payload.scrollLock !== true) return;
    scrollLockCount++;
    if (scrollLockCount !== 1) return;
    const targets = [document.body];
    const target = payload.scrollTargetSelector ? document.querySelector(payload.scrollTargetSelector) : null;
    if (target && targets.indexOf(target) === -1) targets.push(target);
    targets.forEach(function (element) {
      if (!element || !element.style) return;
      lockedOverflow.set(element, element.style.overflow || '');
      element.style.overflow = 'hidden';
    });
  }

  function releaseScrollLock(payload) {
    if (!payload || payload.scrollLock !== true || scrollLockCount === 0) return;
    scrollLockCount--;
    if (scrollLockCount !== 0) return;
    lockedOverflow.forEach(function (overflow, element) { element.style.overflow = overflow; });
    lockedOverflow.clear();
  }

  function clampStrength(value) {
    const numeric = Number(value);
    return Number.isFinite(numeric) ? Math.max(0, Math.min(100, Math.round(numeric))) : 40;
  }

  function scoreVisual(policy) {
    const scopeScore = { local: 1, shell: 2, page: 3, viewport: 4 };
    const effectScore = { none: 0, dim: 1, blur: 2, 'blur-dim': 3 };
    return (scopeScore[policy.scope] || 0) * 1000 + policy.strength * 10 + (effectScore[policy.effect] || 0);
  }

  function currentVisual() {
    let selected = null;
    manager._instances.forEach(function (inst) {
      const policy = visualPolicy(inst.payload);
      policy.strength = clampStrength(visualStrengthOverride !== null ? visualStrengthOverride : policy.strength);
      if (!selected || scoreVisual(policy) >= scoreVisual(selected)) selected = policy;
    });
    return selected;
  }

  function applyVisual() {
    const root = document.documentElement;
    const body = document.body;
    if (!root || !body) return;
    if (manager.activeCount > 0) {
      root.dataset.shellOverlayOpen = 'true';
    } else {
      delete root.dataset.shellOverlayOpen;
    }
    const policy = currentVisual();
    if (!policy || policy.strength <= 0 || policy.effect === 'none') {
      delete root.dataset.shellOverlayEffect;
      delete root.dataset.shellOverlayScope;
      delete root.dataset.shellOverlayStrength;
      body.classList.remove('has-shell-overlay-visual');
      ['--shell-overlay-effect-strength', '--shell-overlay-blur-radius', '--shell-overlay-dim-opacity', '--shell-overlay-saturate'].forEach(function (name) { root.style.removeProperty(name); });
      return;
    }
    const strength = clampStrength(policy.strength);
    root.dataset.shellOverlayEffect = policy.effect;
    root.dataset.shellOverlayScope = policy.scope;
    root.dataset.shellOverlayStrength = String(strength);
    body.classList.add('has-shell-overlay-visual');
    root.style.setProperty('--shell-overlay-effect-strength', String(strength));
    root.style.setProperty('--shell-overlay-blur-radius', (Math.round((strength / 100) * 1400) / 100) + 'px');
    root.style.setProperty('--shell-overlay-dim-opacity', String(Math.round((strength / 100) * 360) / 1000));
    root.style.setProperty('--shell-overlay-saturate', String(Math.round((1 - (strength / 100) * 0.2) * 1000) / 1000));
  }

  // Minimal dynamic manager. DOM behavior remains candidate-owned during migration.
  const manager = {
    _instances: new Map(),
    _order: [],
    _lastActiveElement: null,

    get activeCount() { return this._instances.size; },

    // Phase 1: do not change scroll lock or focus automatically.
    // Provide stubs for later migration.
    open(payload) {
      // payload: { type, dom: Element|HTML, focusRootSelector?, backdropSelector?, portalId? }
      const id = String(payload && (payload.id || payload.type) ? (payload.id || payload.type) : '').trim();
      if (!id) {
        return null;
      }
      if (this._instances.has(id)) {
        return this._instances.get(id);
      }

      const configuredPayload = definition(payload || {});
      const policy = visualPolicy(configuredPayload);
      const normalizedPayload = Object.assign({}, configuredPayload, {
        visualEffect: policy.effect,
        visualScope: policy.scope,
        visualStrength: policy.strength
      });
      const inst = {
        id: id,
        type: payload && payload.type ? String(payload.type) : 'unknown',
        payload: normalizedPayload,
        rootEl: null,
        previousFocus: document.activeElement || null,
        isOpen: true,
      };

      this._instances.set(id, inst);
      this._order.push(id);
      acquireScrollLock(normalizedPayload);
      if (typeof normalizedPayload.onOpen === 'function') normalizedPayload.onOpen('open');
      applyVisual();

      // Host presence is required; but we do not mount content in phase 1.
      const host = document.querySelector(HOST_SELECTOR);
      if (host) {
        // no-op for phase 1 (infrastructure only)
      }

      // Phase 1 infrastructure only:
      // - update internal counters only
      // - emit non-intrusive events only
      // - DO NOT mutate DOM, body classes, focus, scroll, or mount/unmount

      try {
        window.dispatchEvent(new CustomEvent('shell-overlay:opened', {
          detail: {
            id: inst.id,
            type: inst.type,
            payload: inst.payload
          }
        }));
      } catch (_) {}

      return inst;
    },

    close(inst, reason) {
      // Phase 1 infrastructure only:
      // - update internal counters only
      // - emit non-intrusive events only
      // - DO NOT mutate DOM, body classes, focus, scroll, or mount/unmount

      const id = typeof inst === 'string' ? inst : (inst && inst.id ? inst.id : '');
      const active = id ? this._instances.get(id) : null;
      if (!active) {
        return false;
      }
      this._instances.delete(id);
      this._order = this._order.filter(function (activeId) { return activeId !== id; });
      active.isOpen = false;
      releaseScrollLock(active.payload);
      if (typeof active.payload.onClose === 'function') active.payload.onClose(reason || 'programmatic');
      if (active.payload.restoreFocus !== false) {
        const trigger = boundElement(active.payload, 'triggerId', 'triggerSelector');
        const target = trigger || active.previousFocus;
        if (target && typeof target.focus === 'function') target.focus();
      }
      applyVisual();

      try {
        window.dispatchEvent(new CustomEvent('shell-overlay:closed', {
          detail: {
            id: active.id,
            type: active.type,
            payload: active.payload
          }
        }));
      } catch (_) {}
      return true;
    },

    toggle(payload) {
      const id = String(payload && (payload.id || payload.type) ? (payload.id || payload.type) : '').trim();
      return this.isOpen(id) ? (this.close(id), null) : this.open(payload);
    },

    closeTop(reason, eligibility) {
      for (let index = this._order.length - 1; index >= 0; index--) {
        const id = this._order[index];
        const active = this._instances.get(id);
        if (active && (!eligibility || eligibility(active.payload))) return this.close(id, reason);
      }
      return false;
    },

    isOpen(id) {
      return this._instances.has(String(id || '').trim());
    },


    // Later phases will provide real focus/scroll/ESC management.
    focusManagement: {
      recordLastActive() {
        this._lastActiveElement = document.activeElement;
      },
      restore() {
        const el = manager._lastActiveElement;
        if (el && typeof el.focus === 'function') {
          el.focus();
        }
      }
    }
  };

  // Portal Rendering Contract stub
  function resolveHost() {
    return document.querySelector(HOST_SELECTOR);
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') manager.closeTop('escape', function (payload) { return payload.escape === true; });
  });

  document.addEventListener('click', function (event) {
    manager.closeTop('outside-click', function (payload) {
      if (payload.outsideClick !== true) return false;
      const surface = boundElement(payload, 'surfaceId', 'surfaceSelector');
      const trigger = boundElement(payload, 'triggerId', 'triggerSelector');
      const matchesTrigger = payload.triggerSelector && event.target && typeof event.target.closest === 'function'
        ? event.target.closest(payload.triggerSelector)
        : null;
      const surfaceContainsTarget = surface && surface.contains(event.target);
      const surfaceSelfDismiss = payload.outsideSurfaceSelf === true && surface === event.target;
      return (!surfaceContainsTarget || surfaceSelfDismiss) && !(trigger && trigger.contains(event.target)) && !matchesTrigger;
    });
  });

  // Public API
  window[NS] = {
    contract: {
      HOST_SELECTOR: HOST_SELECTOR,
      VISUAL_PRESETS: VISUAL_PRESETS,
      DEFINITIONS: DEFINITIONS,
    },
    manager,
    portal: {
      resolveHost,
    },
    visualEffects: {
      setStrength(value) { visualStrengthOverride = clampStrength(value); applyVisual(); },
      clearStrengthOverride() { visualStrengthOverride = null; applyVisual(); },
      getState() { return { activeCount: manager.activeCount, policy: currentVisual(), strengthOverride: visualStrengthOverride }; },
      reset() {
        manager._instances.forEach(function (inst) { releaseScrollLock(inst.payload); });
        manager._instances.clear();
        manager._order = [];
        applyVisual();
      }
    }
  };
  applyVisual();
})();
JS;
    }
}
