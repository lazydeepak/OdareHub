# OH-AUTO4-PROC-004 — Procurement Organization Ownership Acceptance

**Date:** 2026-10-31
**Status:** Accepted with disposable-runtime qualification only.

## Safety boundary

Acceptance used the governed isolated-source-root runner, a fresh
runner-owned MySQL instance, generated disposable credentials, and a
loopback-only PHP HTTP server. The repository's installed configuration,
development/incident databases, production-like hosts, deployment, activation,
and production migration paths were not contacted.

The staged source manifest and disposable database configuration were checked
before and after execution. The runner stopped the database and HTTP process
and removed its temporary source/database resources.

## Migration and tenant proof

The Procurement migration was applied through `AppMigrationService` after
disposable prerequisite schema and fixture creation. The probe passed **15/15**:

- nullable `organization_id` and no ownership backfill;
- real migration application and idempotent re-run;
- organization column, index, foreign key, and exact applied ledger checksum;
- same-tenant Alice and Bob projections;
- cross-tenant, missing-context, and non-member denial;
- active membership resolution; and
- fixture cleanup.

Legacy request ownership remained `NULL` and was not exposed by the bounded
operator projection.

## Authenticated runtime proof

The real login form and CSRF flow were exercised over loopback. The acceptance
passed **18/18** assertions including:

- authenticated login form CSRF issuance;
- authenticated Alice operator request rendering for organization 11;
- exclusion of Bob's organization-12 request and the legacy `NULL` request;
- existing assignment/`procurement.view` path remained in use; and
- runner-owned HTTP/database/source cleanup.

The staged runtime logged optional-widget warnings for absent unrelated
`operator_tasks` and `audit_activity_log` tables. Procurement rendering and
all acceptance assertions passed; no unrelated schema was added.

## Validation record

```text
OH_TEST_ONLY=procurement-organization-ownership \
  bash scripts/testing/run_business_integrity_regression.sh

authorizer=PASS checks=24
procurement-organization-ownership: RESULT 18/18
cleanup=true
installed_service_preserved=true
REGRESSION RESULTS suites=4 failed=0
```

Database-free tenant contract probe: **15/15**. PHP lint and shell syntax
checks passed. No Core/Auth/ACL source was changed.

## Disposition

OH-AUTO4-PROC-004 is **accepted for the bounded Procurement request
organization-ownership contract**, qualified by disposable migration and
authenticated loopback-runtime evidence. Installed-database migration apply,
production deployment, and financial mutation activation remain outside this
acceptance.
