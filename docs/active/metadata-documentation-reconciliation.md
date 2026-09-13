# Milestone — Metadata / Documentation Reconciliation

Status: Active brief. Read-only audit. No shared/foundation promotion. No schema/runtime change.

Canonical authority for reconciliation rules:
- `docs/CURRENT.md` (current-state router)
- `docs/architecture/markdown-freshness-authority-audit.md` (classification rules, bucket definitions)
- `docs/BACKLOG.md` (documentation backlog item #4 — archive/marking pass)
- `docs/architecture/ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md` (terminology authority)
- `plugins/AGENTS.md`, `plugins/Base/AGENTS.md` (plugin vocabulary debt source)
- App/manifests (`manifest.php`) — manifest vocabulary source
- `engineering/Procurement/` — classification-uncertain reference

Bounded audit scope:
1. Reconcile stale terminology (ARCHITECTURE.md vs docs/identity/; legacy Plugin naming in plugin files vs manifest vocabulary).
2. Distinguish runtime compatibility vocabulary (`Plugins\...`, legacy `Base`, transitional fields like `me_dashboard_blocks`, `operator_views`, manifest `status`/`category`/`group`) from target architecture vocabulary (`App`, `Module`, `Platform`, `Shell`, `Studio`, `Shared Foundation` contracts, `Canonical` ownership).
3. Document legacy `Plugins\...` naming compatibility debt explicitly — source of truth: `plugins/AGENTS.md` and `plugins/Base/AGENTS.md`; debt location: transitional references in Base/Platform compatibility surfaces (per audit Bucket 8 / Bucket 4).
4. Reconcile misleading design/documentation claims (e.g., `DesignSystem/README.md` claims inert; `Shell/Style/README.md` or design-system placeholders vs runtime reality) — only add status headers (`Status: superseded` / `Status: active` / `Status: historical evidence`) without rewriting content.
5. Confirm Procurement classification remains uncertain — `engineering/Procurement/work.md` shows no verified objective, no completed evidence; `engineering/Procurement/overview.md` has empty sections. Document the uncertainty explicitly (evidence-insufficient) rather than promoting a false classification.
6. Preserve protected residual files: `docs/active/README.md`, 4 hospitality reference docs, `work/shared-parties-foundation/docs/shared-parties-foundation/canonical-party-contract.md`, `storage/` artifacts.

Exclusions:
- No Shared/Foundation promotion.
- No schema/runtime change.
- No namespace migration.
- No new services/routes/controllers.
- No app/module restructuring.
- No deployment.
- No file moves/deletions (only in-place status headers on confirmed superseded/stale candidates, per audit guardrails).

Deliverable:
Single documentation commit (`docs(architecture): reconcile metadata and vocabulary`) containing:
- Reconciliation note in `docs/architecture/metadata-vocabulary-reconciliation.md` (new, bounded).
- Status-header updates only on confirmed superseded/stale docs (per audit list: `access-control-view-architecture.md`, `unified-operational-landing-strategy.md`, `API-DEPRECATION-POLICY.md`, `shell-overlay-implementation-roadmap.md` if superseded, `DesignSystem/README.md` factual update only, executed style-chain planning docs only if confirmed superseded — no mass edit).
- Procurement classification note appended to `engineering/Procurement/work.md` (evidence-insufficient, not promoted).
- Plugin vocabulary debt note in `docs/discussions/` or `docs/architecture/` (short, durable reference, no promotion).
- No changes to `docs/active/README.md` or active hospitality docs (preserved).

Validation before commit:
- `git diff --cached --check`
- `git diff --cached --stat` confirms only expected files
- Confirm no `work/`, `storage/`, `docs/active/hospitality-*.md` changes staged
- Confirm `docs/architecture/metadata-vocabulary-reconciliation.md` does not claim shared-foundation promotion
