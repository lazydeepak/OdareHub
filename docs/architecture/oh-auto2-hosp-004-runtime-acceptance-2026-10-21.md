# OH-AUTO2-HOSP-004 — Hospitality Runtime Acceptance

**Date:** 2026-10-21
**Baseline:** `907bbc39818fd87920a402dd530cc6498f94cbbe`
**Status:** Development-only disposable runtime acceptance completed; no
production database or deployment operation occurred.

## Environment identity

The configured repository target was **not used**: `storage/db_config.php`
points to `127.0.0.1:3306`, database `os.susankhya.com`, user `root`, and no
server was listening. That target was treated as unidentified/installed-like.

Acceptance used a fresh runner-owned disposable MySQL 9.6 instance:

| Field | Value |
|---|---|
| Host | `127.0.0.1` |
| Port | `57938` |
| Database | `oh_test_hosp004_b40822f79c2d` |
| User | generated test-only `h004_*` identity |
| Source root | `/private/var/folders/_w/4wgfpvdd1_55pjmgt2yt99740000gn/T/hosp004.A4Q9TU/source-workspace/source-root` |
| Server | Homebrew MySQL 9.6, `--no-defaults`, temporary datadir/socket, X Plugin disabled |
| Network | loopback-only classic listener; disposable process attestation passed **24 checks** |

The source root was staged outside the repository. The actual repository and
configured database were not used as the application root or DB target.

## Backup and fixture lifecycle

After fresh disposable database creation, before importing application schema
or creating test fixtures, an empty-target logical backup was captured:

```text
Path: /private/var/folders/_w/4wgfpvdd1_55pjmgt2yt99740000gn/T/hosp004-pre-fixture-empty.sql
SHA-256: 453fa444ff8fa55a5e2f7d4f8b6fe44073262f607b3f9ac8b895d1fcf866f93a
Bytes: 1647
```

The sanctioned `tools/restore/live-schema.sql` core schema was then loaded;
Hospitality migrations were applied through the real `AppMigrationService`.
All generated guests, rooms, reservations, folios, charges, and migration
fixtures were confined to the disposable database. The MySQL process,
datadir, socket, staged source root, and database were destroyed through
runner-owned cleanup. Post-run verification confirmed the disposable root and
port were gone; the configured installed target was never contacted.

## Runtime results

- Hospitality reservation lifecycle probe: **27/27**, including route
  registration, real migrations, creation, invalid input, inactive guest/room
  rejection, overlap rejection, adjacent-range acceptance, lifecycle
  timestamps, terminal-state guards, and cleanup.
- Hospitality Front Desk and local folio probe: **26/26**, including route
  registration, CSRF/manage guard evidence, Arrivals/In-house classification,
  check-in/out, folio idempotency, charge validation, totals, voiding, folio
  closure, and cleanup.
- Real reservation-lock concurrency probe: **3/3** — exactly one overlapping
  create committed, one returned `HOSPITALITY_RES_ROOM_CONFLICT`, and one
  active reservation remained durable.
- Two independent PHP reservation creators: **PASS** — returned IDs `2` and
  `3` were distinct and each matched the exact row and unique note created by
  its own process.

No application defect was independently reproduced in this runtime scope, so
no Hospitality production code was changed.

## Authorization and HTTP boundary

The runtime probes verified route registration and the existing server-side
CSRF/manage guard contracts. The offline HOSP-003 behavioral probe remains the
evidence for exact operator action dispatch, own-handle confinement, invalid
identity rejection, and one-time localized confirmation (**57/57**).

Authenticated browser/HTTP acceptance was not executed: this environment had no
approved local Hospitality HTTP executor or authenticated browser target. No
HTTP bypass, authorization bypass, or production-like target was introduced.

## Evidence files

The disposable-runner output was retained outside the repository:

- `/private/var/folders/_w/4wgfpvdd1_55pjmgt2yt99740000gn/T/hosp004-runtime-report.json`
- `/private/var/folders/_w/4wgfpvdd1_55pjmgt2yt99740000gn/T/hosp004-slice6-last.log`
- `/private/var/folders/_w/4wgfpvdd1_55pjmgt2yt99740000gn/T/hosp004-slice7-last.log`
- `/private/var/folders/_w/4wgfpvdd1_55pjmgt2yt99740000gn/T/hosp004-concurrency-last.log`

No production deployment, installed database mutation, Core change, schema
repository change, Manufacturing/Material operation, or SBAIO incident work
was performed.
