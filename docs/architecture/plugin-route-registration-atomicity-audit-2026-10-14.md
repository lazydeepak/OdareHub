# OH-AUTO1-024 — Plugin Route Registration Atomicity and Rollback

Date: 2026-10-14
Status: **Reproduced; Core correction not authorized**

## Safety boundary

The investigation used `scripts/testing/probe_plugin_route_registration_atomicity.php`.
It uses an in-memory `App\Core\Router` fixture and static source inspection. It
does not instantiate `PluginManager`, activate a real plugin, connect to a
database, change lifecycle state, or modify Core.

Probe result: **16/16 assertions passed**.

## Authoritative registration path

The affected method is:

- `app/Core/PluginManager.php::loadActivePlugins()`.

Its relevant phases are:

1. `scan()` discovers manifests and `architectureReport()` validates them.
2. Active plugin names are read from `installed_plugins`; dependencies and
   architecture warnings/errors are filtered.
3. The plugin view namespace is added with `View::addNamespace()`.
4. The optional plugin `bootstrap.php` is required directly.
5. The plugin entry, normally `routes.php`, is required directly.
6. The next plugin continues only if the require returns normally.

`Router::get()` and `Router::post()` mutate the live route map immediately.
`app/Core/Router.php` exposes no remove/unregister or transaction API.

## Confirmed failure behavior

The in-memory fixture reproduced:

- successful registration of GET and POST routes;
- a route registered before an exception remains in `Router::listRoutes()`;
- routes owned by another fixture remain unchanged;
- the fixture lifecycle status remains `active` after the exception;
- a retry does not remove the stale route first;
- disabled/dependency-blocked fixtures register no routes;
- duplicate declarations silently replace the earlier handler (last write wins).

Static inspection additionally confirms the actual plugin loader contains no
local `try` boundary around `require $routes`, no broken-state transition in
that failure path, and truncates the shared `menus` table before loading active
plugins. The database-backed loader was not run under this assignment.

## Rollback and state result

For a thrown plugin bootstrap or route file:

- the exception propagates to the caller;
- routes registered earlier in that plugin remain in the live router;
- no plugin-local route rollback occurs;
- no `installed_plugins.status='broken'` transition occurs in
  `loadActivePlugins()`;
- retry can reuse or overwrite stale route keys;
- shared menu-registry mutation occurs before plugin route loading and is not
  covered by a plugin-local rollback boundary.

This is a confirmed route-registration atomicity defect in the in-memory route
semantics, with end-to-end database/plugin activation behavior still unrun.

## Smallest Core-owned correction proposal

Do not add a second route registry. Add a transaction-like registration scope
to the existing `Router` or provide an existing-router snapshot/restore
primitive, then wrap each plugin's bootstrap/routes phase in
`PluginManager::loadActivePlugins()`:

1. begin a per-plugin route-registration scope;
2. require bootstrap and routes;
3. commit the scope only if both return normally;
4. on `Throwable`, restore only routes added/replaced by that plugin, emit a
   sanitized lifecycle diagnostic, and prevent the failed plugin from being
   considered successfully loaded;
5. transition the plugin to the approved failed/broken lifecycle state through
   the authoritative lifecycle service, if that state transition is part of
   the accepted contract.

The scope must preserve routes registered by other owners and must not roll back
the whole router. A child/staging router is an alternative only if all route
registration APIs and dispatch dependencies can be safely composed; it must not
become a parallel registry.

Route rollback alone does not automatically undo bootstrap side effects such as
event-bus subscriptions, view namespaces, global functions, static caches, or
shared menu writes. Core/platform must decide whether plugin bootstrap is also
staged, whether those effects receive compensating cleanup, or whether bootstrap
must be constrained before route loading.

## Acceptance criteria for the future correction

1. Successful plugin bootstrap/routes commit all declared routes.
2. Failure after route N leaves no route introduced or replaced by that plugin.
3. Existing routes from unrelated owners remain byte/handler equivalent.
4. Retry starts from a clean plugin route state and cannot duplicate or
   resurrect stale entries.
5. Disabled plugins and missing dependencies register no executable route.
6. Duplicate declarations are rejected or handled by an explicit documented
   policy, not accidental last-write behavior.
7. Failure state and sanitized diagnostic are observable without leaking
   exception secrets.
8. Shared menus, namespaces, hooks, and event subscriptions have explicit
   consistency/cleanup evidence, or are explicitly out of the route atomicity
   contract and separately gated.
9. The test suite runs without a live installed database by using injected
   registry/lifecycle fixtures.

## Other executable surfaces potentially affected

- plugin bootstrap hooks and event-bus subscriptions;
- view namespaces added before route loading;
- shared `menus` truncation/materialization;
- global functions/constants or static caches declared by plugin files;
- lifecycle logs and status transitions;
- route diagnostics and route authorization maps derived after loading.

These are identified risks, not all individually proven failures in this
assignment.

## Remaining blocker and next task

The Core route/lifecycle owner must approve the transaction scope and failure
state semantics. No app/plugin workaround is appropriate. Next independently
executable work is OH-AUTO1-025: integration-baseline verification and the
file-level inventory of the accumulated uncommitted changes.
