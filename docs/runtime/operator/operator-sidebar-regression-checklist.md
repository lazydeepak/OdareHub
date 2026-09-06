# Operator Sidebar Regression Checklist

Date: 2026-04-26
Owner: Shell Operator Layer

Use this checklist whenever sidebar behavior is changed in the operator surface.

## Scope

- Surface: `/u/{username}`
- Composer: `apps/Shell/Composers/OperatorSurfaceComposer.php`
- Navigation source: `contextual_sidebar` only (desktop sidebar and mobile drawer)

## Contract

1. No dual-sidebar model:
- Desktop and mobile must read from the same `contextual_sidebar.sections[].items[]` source.
- No fallback to a separate legacy hamburger data builder.

2. Desktop behavior (`window.innerWidth > 900`):
- Hamburger toggles collapsed/expanded shell state only.
- Collapsed width is deterministic (`68px`).
- Main content begins at collapsed track start (left expansion visible).
- Hover-peek opens only from explicit pointer entry on the collapsed rail.
- Leaving sidebar removes `sidebar-peek`.

3. Mobile behavior (`window.innerWidth <= 900`):
- App shell uses a single content column.
- Sidebar panel stays hidden.
- Hamburger controls drawer/backdrop only.
- Drawer item order must match desktop sidebar item order.

## Manual Test Matrix

1. Desktop expanded -> collapse:
- Click hamburger.
- Expect classes: `sidebar-collapsed`, not `sidebar-peek`.
- Expect computed shell track starts with `68px`.

2. Desktop collapsed -> hover rail:
- Move pointer into left icon rail.
- Expect `sidebar-peek` to appear.
- Move pointer out.
- Expect `sidebar-peek` to be removed.

3. Desktop collapsed -> content click:
- If expanded, clicking main content should collapse.
- If already collapsed, no side effects.

4. Mobile drawer:
- Resize to mobile.
- Click hamburger.
- Expect drawer and backdrop open.
- Click backdrop or press Escape.
- Expect both close.

5. Menu parity:
- Compare desktop `.app-sidebar .nav-item` labels and mobile `#hamburgerMenu .menu-item` labels.
- Expect same count and same order.

## Quick Console Assertions

Run in browser devtools on `/u/{username}`:

```js
(() => {
  const shell = document.getElementById('appShell');
  const sidebar = document.getElementById('contextualSidebar');
  const desktopItems = [...document.querySelectorAll('.app-sidebar .nav-item .nav-label')].map(el => el.textContent.trim());
  const mobileItems = [...document.querySelectorAll('#hamburgerMenu .menu-item .menu-item-label')].map(el => el.textContent.trim());
  return {
    shellClass: shell?.className,
    gridTemplateColumns: getComputedStyle(shell).gridTemplateColumns,
    sidebarWidth: Math.round(sidebar.getBoundingClientRect().width),
    itemParity: desktopItems.length === mobileItems.length && desktopItems.every((v, i) => v === mobileItems[i]),
    desktopCount: desktopItems.length,
    mobileCount: mobileItems.length,
  };
})();
```

## Exit Criteria

- PHP syntax clean for changed files.
- Manual matrix passes for desktop + mobile.
- Menu parity check returns true.
- No visual state where desktop sidebar and mobile drawer appear active together.
