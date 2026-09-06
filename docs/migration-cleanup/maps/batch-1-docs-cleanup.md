# Batch 1 Docs Cleanup Proof

Status: executed.

Scope: docs-only operator documentation slice.

## Moved Documents

### Operator layer architecture

- old path: `docs/operator-layer-architecture.md`
- new path: `docs/runtime/operator/operator-layer-architecture.md`
- correct owner/location: historical operator runtime architecture belongs with
  runtime operator documentation.
- grep/reference impact: `AGENTS.md` and
  `docs/migration-cleanup/maps/folder-walkthrough.md`.
- references updated: yes.
- scripts/gates old-path reference: none found under `scripts`, `.github`, or
  `etc`.
- skipped references: none.

### Operator layer implementation guide

- old path: `docs/operator-layer-implementation-guide.md`
- new path: `docs/runtime/operator/operator-layer-implementation-guide.md`
- correct owner/location: operator runtime implementation guide belongs with
  runtime operator documentation.
- grep/reference impact: `AGENTS.md` and
  `docs/migration-cleanup/studio/CHANGES-TAB-MIGRATION-REPORT.md`.
- references updated: yes.
- scripts/gates old-path reference: none found under `scripts`, `.github`, or
  `etc`.
- skipped references: none.

### Operator layer testing guide

- old path: `docs/operator-layer-testing-guide.md`
- new path: `docs/runtime/operator/operator-layer-testing-guide.md`
- correct owner/location: operator runtime testing guide belongs with runtime
  operator documentation.
- grep/reference impact: `AGENTS.md` and historical path mentions in
  `AGENT-COMPLIANCE-CHECKLIST.md`.
- references updated: yes.
- scripts/gates old-path reference: none found under `scripts`, `.github`, or
  `etc`.
- skipped references: none.

### Operator sidebar regression checklist

- old path: `docs/operator-sidebar-regression-checklist.md`
- new path: `docs/runtime/operator/operator-sidebar-regression-checklist.md`
- correct owner/location: operator-specific regression checklist belongs with
  runtime operator documentation.
- grep/reference impact: no references found.
- references updated: none required.
- scripts/gates old-path reference: none found under `scripts`, `.github`, or
  `etc`.
- skipped references: none.

### Operator workspace user guide

- old path: `docs/operator-workspace-user-guide.md`
- new path: `docs/runtime/operator/operator-workspace-user-guide.md`
- correct owner/location: operator workspace user guide belongs with runtime
  operator documentation.
- grep/reference impact: `AGENTS.md` and
  `docs/operator-realtime-deployment.md`.
- references updated: yes.
- scripts/gates old-path reference: none found under `scripts`, `.github`, or
  `etc`.
- skipped references: none.

## Skipped Candidates

- `docs/operator-realtime-deployment.md`: referenced by
  `etc/systemd/operator-realtime.service`; moving would require a non-doc
  service file update, so it was skipped.
- `docs/unified-operational-landing-strategy.md`: referenced by
  `.github/skills/ipm-erp-implementation/SKILL.md`; moving would cross into
  tool/skill path updates, so it was skipped.
- `docs/operator-layer-phase8-realtime-updates.md`: kept with the skipped
  realtime deployment guide for a later realtime-doc batch.
- `docs/phase8-complete.md`: kept with the skipped realtime deployment guide
  for a later realtime-doc batch.

## Validation

Validation is recorded in `AGENT-COMPLIANCE-CHECKLIST.md` for this batch.
