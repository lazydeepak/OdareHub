# OH-AUTO2-HOSP-005 — Isolated Authenticated HTTP Acceptance

**Date:** 2026-10-21
**Status:** Completed in a development-only disposable environment.

## Environment boundary

The configured repository target (`127.0.0.1:3306`, database
`os.susankhya.com`, user `root`) was not contacted. Acceptance used a fresh
runner-owned MySQL instance with a temporary datadir and socket, generated
database credentials, a staged source root outside the repository, and a
loopback-only PHP built-in HTTP server. The disposable server and database
were destroyed by owned cleanup after the run.

The empty-target backup was captured before schema and fixture mutation:

```text
SHA-256: 1abe6c2c0a4c66e552cd91da702556329645bb2bb8e28085167e73e37daa1bd7
Bytes: 1507
```

## HTTP acceptance

The unattended `curl` run passed **20/20** checks:

- unauthenticated operator GET redirects to `/login`;
- generated manage identity logs in with a real session and receives a real
  operator CSRF token;
- invalid CSRF returns HTTP `419` with the localized invalid-token response;
- authorized reservation creation redirects to the jailed workspace, emits a
  one-time creation confirmation, and persists one reservation;
- authorized check-in and check-out both return to the jailed workspace, with
  final reservation status `checked_out`;
- read-only identity receives the operator surface without the create form and
  cannot mutate reservation count;
- unassigned identity is denied; cross-handle POST is redirected to the
  authenticated manage user's dashboard; and display-only identity is routed
  to `/displays/user/hospdisplay`.

Database postconditions matched the HTTP flow: reservation ID `1` was the
created row and its final status was `checked_out`.

## Independently reproduced defect and correction

The first authenticated GET reproduced a fatal Hospitality-owned view defect:
`apps/Hospitality/Views/operator/hospitality.php` called `$this->tr()` even
though the Shell focus-view composer includes the app view from a static
composer scope. The staged server reported `Using $this when not in object
context`.

The view now owns a local translation closure backed by the global `t()`
helper and fallback interpolation, and all former `$this->tr()` calls use that
closure. No Shell or Core change was needed. The clean rerun passed 20/20 with
no fatal response.

## Safety and non-actions

No production-like database, installed storage, deployment, activation, public
listener, Core file, schema repository file, or unrelated domain was changed.
The unrelated pre-existing Procurement backup, lifecycle logs, and PHPUnit
cache were preserved.
