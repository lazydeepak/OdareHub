# Susankhya OS Architecture Charter v1

Status: Active architecture guidance for all platform and agent work.

Purpose: Keep the team focused on permanent platform architecture quality and prevent drift into sample-app polishing.

## 1. Five Big Boxes

1. Core = locked platform law.
2. Shell = generic runtime house/wrapper.
3. Apps = business-owned rooms/resources.
4. Studio = governed construction worker/tool.
5. System Tools = governed maintenance/security/operations worker inside each customer instance.

## 2. Non-Negotiable Laws

1. Core is locked.
2. Shell is generic.
3. Apps own business logic.
4. Apps contribute; Shell composes.
5. ACL authorizes only.
6. Workspace Profile shapes experience.
7. Runtime consumes resolved/compiled contracts.
8. Studio edits but does not own runtime truth.
9. System Tools maintain/validate/repair but do not bypass governance.
10. No hidden duplicate source of truth.

### Core Ownership Scope

Core is platform law and primitives only. Core owns low-level runtime primitives such as bootstrap, routing primitives, registry primitives, auth/permission primitives, migration primitives, and base contracts.

Core must not absorb:

- Shell runtime chrome/composition ownership
- Platform governance ownership
- Studio authoring/workflow ownership
- app/module business logic
- app/module business routes, views, CSS, or capability semantics

Core changes are exceptional and require explicit approval.

## 3. Current Project Focus

1. Susankhya OS is the product.
2. ERP/Manufacturing/SBAIO/Payroll are sample/reference apps.
3. Do not polish sample apps unless the issue exposes a platform architecture problem.
4. Current priority is architecture perfection.

## 4. Studio Model

1. Studio is not an owner.
2. Studio is a governed worker hired to edit owner-owned resources.
3. Studio owns tasks, drafts, diffs, previews, snapshots, undo/redo, approvals, and handover records only.
4. Runtime must not consume Studio drafts as source of truth.

## 5. System Tools Model

1. System Tools are built into each customer instance.
2. System Tools are separate from Studio by purpose and UI, not necessarily by installation.
3. System Tools validate, maintain, diagnose, upgrade, repair, secure, package, backup, recover, and monitor the instance.
4. System Tools are role-gated, permission-gated, risk-gated, audited, and may be edition/tier controlled.
5. Dangerous System Tools require dry-run, diff, preview, approval, snapshot, rollback plan, and audit log.

## 6. Architecture Gate Checklist

Architecture gates are read-only System Tools foundation. They validate architecture boundaries without changing runtime behavior or runtime state.

1. Core lock change scope.
2. Shell and Platform system-app contract baselines.
3. Shell CSS and ownership boundaries.
4. Operator wrapper confinement.
5. Display readonly confinement.
6. Admin canonical route behavior.
7. Studio ownership and dependency boundaries.
8. Resolved-experience single source of truth.
9. Surface contribution contract boundaries.
10. Migration-debt regression coverage for new runtime diff additions.
11. Capability ownership boundaries (QR/PDF/package).
12. Business App and Module ownership contract baseline.

The aggregate gate runner is:

```bash
scripts/architecture/run_architecture_gates.sh
```

It currently runs:

1. `scripts/architecture/check_core_lock_scope.sh`
2. `scripts/architecture/check_system_app_contracts.sh`
3. `scripts/architecture/check_shell_css_ownership.sh`
4. `scripts/architecture/check_operator_confinement.sh`
5. `scripts/architecture/check_display_readonly.sh`
6. `scripts/architecture/check_admin_route_contract.sh`
7. `scripts/architecture/check_studio_boundary.sh`
8. `scripts/architecture/check_resolved_experience_truth.sh`
9. `scripts/architecture/check_surface_contribution_contracts.sh`
10. `scripts/architecture/check_migration_debt_regressions.sh`
11. `scripts/architecture/check_capability_ownership_boundaries.sh`
12. `scripts/architecture/check_business_app_module_contracts.sh`

## 7. Agent Operating Rule

Before changing code, every agent must report:

1. Which architecture law is broken.
2. Which owner owns the fix.
3. Why it is not sample-app polish.
4. What validation will prove it.

Before architecture-related runtime edits, every agent must run:

```bash
scripts/architecture/run_architecture_gates.sh
```

If gates pass, do not invent architecture patches. If gates fail, fix only the failing architecture rule.

For every failed gate that leads to a fix, report:

1. Failed gate/script.
2. Architecture law broken.
3. Owner layer responsible for the fix.
4. Validation that proves the fix.
5. Commit hash after push.

## Implementation Note

This charter is architecture guidance only. It does not authorize runtime feature work by itself.

For System App, Plugin, Package, QR, and PDF boundary examples, see:

- `docs/architecture/system-app-plugin-package-boundaries.md`
- `docs/architecture/surface-contribution-contract.md`
- `docs/architecture/resolved-runtime-contract-pipeline.md`
- `docs/architecture/studio-operating-contract.md`
- `docs/architecture/studio-resource-registry-baseline.md`
- `docs/architecture/studio-change-lifecycle-apply-contract.md`
- `docs/architecture/studio-change-record-schema-baseline.md`
- `docs/architecture/studio-approval-risk-validation-policy.md`
