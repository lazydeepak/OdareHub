# OH-AUTO2-111 — Material Aggregate Authorization Containment

**Date:** 2026-10-16
**Status:** Contained

OH-AUTO2-110 established that the Material Management aggregate could expose
whole-domain counts when a user had module access but restricted operational
scope. This milestone removes the unsafe `/me` hook and trusted capability
descriptor from `apps/Manufacturing/manifest.json`.

The extracted provider source remains in the repository as inactive source for
traceability. It is no longer registered as a host hook, has no capability
descriptor, and therefore fails the existing Platform admission lookup before
provider file loading or callback invocation. `summaryReadOnly()` remains
unchanged and ordinary Material Management consumers retain their behavior.

The provider inventory verifies:

- six navigation descriptors remain;
- `manufacturing.me.material_summary` is absent;
- the suppressed provider class is not loaded;
- unrestricted and restricted `/me` registry fixtures both emit no material
  aggregate;
- existing navigation ordering and output remain functional;
- non-`/me` host behavior remains unchanged.

Focused results: registry containment/inventory **11/11** and inactive-source
descriptor proof **17/17**. Existing Material read safety remains **18/18**;
authorization, containment, and generic admission remain **12/12**, **15/15**,
and **9/9**.

No database connection, SQL, schema, route, browser, activation, deployment,
Core, or record-scope redesign operation was performed.
