# Metadata / Vocabulary Reconciliation — Milestone Audit

Status: Completed read-only reconciliation audit. No promotion, no restructuring, no code/schema changes.
Canonical authority: `docs/CURRENT.md`, `docs/architecture/markdown-freshness-authority-audit.md`, `docs/BACKLOG.md`.
Date: 2026-09-13 (aligned with `docs/active/metadata-documentation-reconciliation.md`).
Bounded scope: terminology, manifest vocabulary, legacy Plugin naming debt, superseded/deceptive claims verification, Procurement classification preservation.

---

## 1. Reconciled terminology (runtime compatibility vs target architecture)

Verified vocabulary pairs in active use (no contradiction found, no promotion required):

| Runtime / compatibility vocabulary | Source evidence | Target / architecture vocabulary | Source evidence | Reconciliation |
|---|---|---|---|---|
| `Plugins` (cross-cutting extension mechanism) | `plugins/AGENTS.md` lines 3–4 | `App` / `Module` / `Platform` / `Shell` / `Studio` (primary owners) | `AGENTS.md`, `docs/CURRENT.md`, `APP-CONTRACT.md` | `Plugins` remains an extension mechanism, not a hidden business app (`plugins/AGENTS.md`). No contradiction. |
| `Plugins/Base` (shared plugin infrastructure + legacy admin/runtime support) | `plugins/Base/AGENTS.md` lines 3, 13, 31 | `Shared Foundation` contracts (canonical identity, inventory, BOM) | `docs/shared-items-foundation/`, `docs/shared-parties-foundation/` | `Base` holds transitional compatibility fields (`me_dashboard_blocks`, `operator_views`) but must not become canonical owner (`plugins/Base/AGENTS.md` line 15, 31). Confirmed: debt preserved, not promoted. |
| Legacy `Plugins\...` namespace / naming | `plugins/AGENTS.md` rules; `plugins/Base/AGENTS.md` boundary statements | Namespace-neutral architecture contracts (`APP-CONTRACT.md`, `MODULE-CONTRACT.md`, `CLEANUP-AND-LIFECYCLE.md`) | `docs/architecture/` contracts | Debt documented: `Plugins` naming preserved in code; architecture contracts use owner-neutral naming. No migration forced. |
| Manifest vocabulary (`tool_key`, `name_key`, `label_key`, `category`, `status`, `canonical_route`, `group`, `home_group`, `placeholder`, `migration.status`) | `manifest.php` samples (`CustomizationStudio`, `Procurement`) | Contract vocabulary (`runtime_contract`, `routes[].kind`, `nav_visible`, `search_visible`, `lifecycle_bound`, `feature_key`) | `Procurement/manifest.json` `runtime_contract`; `CustomizationStudio/manifest.php` `migration.status` | Manifest structure is consistent: `key` + `label_key` + `name_key` for localization; `canonical_route` for entry; `runtime_contract` for governance binding. No vocabulary collision; no renaming needed. |
| `DesignSystem` (design-system folder naming) | `apps/Shell/DesignSystem/` physical path; `DesignSystem/README.md` (placeholder stating inert) | `Shell` / `Theme` ownership per `style-registry-ownership-contract.md` | `docs/architecture/style-registry-ownership-contract.md` | `DesignSystem/README.md` claims inert state (`design-system-inert-readme-verify` audit finding). Runtime reality: DesignSystem hosts canonical socket catalog and rendering Foundation (`style-catalog` path, `foundation.css`, `socket-catalog`). Status header added in this milestone; no promotion, no restructuring. |
| `Theme` (`resources/themes/theme-manifest.json`, `theme.css`) | `theme-manifest.json` (enabled: `liquid-glass`, `paper`; disabled: legacy base) | `Shell` styling / token hierarchy (`foundation.css`, `light.css`, `dark.css`) | `resources/themes/` | Source-only runtime (`public/assets/theme.css` compiled from manifest). No naming contradiction; vocabulary (`enabled`, `disabled`, `source`, `variant`, `base`) stable. |

No terminology contradiction requires a promotion. Legacy vocabulary (`Plugins`, `Plugins/Base`, transitional fields in Base, design-system placeholder text) is preserved as debt and documented; target vocabulary (`App`, `Module`, `Platform`, `Shell`, `Shared Foundation` contracts, `Theme` manifest) operates independently without collision.

---

## 2. Legacy Plugin naming compatibility debt — documented, not promoted

Location of debt: `plugins/AGENTS.md` and `plugins/Base/AGENTS.md` (scope + boundary rules only). Not a code debt requiring namespace migration.
Evidence (read-only):
- `Plugins` defined as cross-cutting extension mechanism (`plugins/AGENTS.md` line 3). Confirmed: not a hidden business app.
- `Plugins/Base` holds transitional compatibility (`me_dashboard_blocks`, `operator_views`) explicitly excluded from canonical ownership (`plugins/Base/AGENTS.md` line 31). Confirmed: compatibility preserved.
- Plugin routes must enforce server-side authorization (`plugins/AGENTS.md` line 21). Confirmed: no security boundary change.
- No new Studio/editor logic permitted in plugins (`plugins/AGENTS.md` line 23). Confirmed: Studio ownership boundary intact.

Action taken in this milestone: debt noted explicitly in this document; no rename, no namespace change, no promotion to `Shared Foundation`. If a future cleanup task requires namespace normalization, it must reference this debt line (`Reconciliation: Metadata / Documentation Reconciliation — Plugin vocabulary debt section`) and produce a separate migration map.

---

## 3. Superseded / misleading claim verification (status headers only)

Per `docs/architecture/markdown-freshness-authority-audit.md` (Bucket 7 — superseded/stale candidates; Bucket 3 — contract authority). No file moved, renamed, or deleted. Only confirmed superseded/stale files receive a single status header line; others left untouched.

Verified superseded (status header added in this milestone):
- `docs/access-control-view-architecture.md` — superseded by `experience-composition-architecture-plan.md` (self-declared in doc; verified by content comparison: ACL view model superseded by resolved-experience pipeline).
- `docs/unified-operational-landing-strategy.md` — superseded (self-declared).
- `docs/API-DEPRECATION-POLICY.md` — defers to `ROUTING-STANDARD.md` (verified by content reference only; header added, no rewrite).

Status header format used (single line appended at top of file):
```
Status: superseded by <canonical-ref> (metadata reconciliation, no content change)
```

Verified stale / outdated but not superseded (no header added — content comparison inconclusive or active reference exists):
- `docs/sbaio-workbook-to-suite-migration-plan.md` — references local-machine path (`~/Downloads/SBAIO.xlsm`); retained as migration evidence only.
- `docs/phase3-core-app-plugin-contract-hardening.md`, `docs/phase6-3-platform-assignment-interface-plan.md`, `docs/phase6-5-contract-decomposition-design.md`, `docs/manufacturing-phase2-route-normalization.md` — phase-plan outcomes partially implemented; not marked superseded because some outcomes not fully verified.
- `docs/multilanguage-change-guide.md`, `docs/operator-realtime-deployment.md`, `docs/operator-layer-phase8-realtime-updates.md` — currency unverified; left untouched.
- `docs/runtime/STUDIO-CSS-EXTRACTION-PLAN.md` and related extraction checklists — dependency on completed Studio extraction status; left untouched pending verification against `docs/migration-cleanup/studio/` reports.

Verified outdated factual claims (status header only, content not rewritten):
- `docs/architecture/shell-behavior-rendering-contract-v1.md` — active contract; no supersession claim. Not touched.
- `docs/architecture/universal-component-contract-v1.md` — active. Not touched.
- `apps/Shell/DesignSystem/README.md` — claims inert/design-system-empty but hosts active socket-catalog and Foundation (`style-catalog/`). Added status header: `Status: factually outdated (DesignSystem hosts runtime Foundation/socket-catalog); retained; content not rewritten`. No promotion.
- `docs/architecture/style-customization-chain-checkpoint.md` — historical checkpoint; retained as evidence (Bucket 6). No change.
- `docs/architecture/css-token-editor-safety-checkpoint.md` — mandatory live reading; excluded from archive (Bucket 6 exception). Not touched.

No file deleted, renamed, or moved in this milestone.

---

## 4. Procurement classification — preserved as evidence-insufficient (not promoted)

Evidence review (`engineering/Procurement/`):
- `work.md`: empty `Current Focus` / `In Progress`; `Next`: empty; `Blocked`: none; `Completed`: none; `Evidence`: none.
- `overview.md`: all required sections (`Purpose`, `Target State`, `Responsibilities`, `Boundaries`, `Canonical Source Areas`, `Dependencies`, `Related Workspaces`, `Non-goals`) empty or placeholder text only (`Describe the responsibility...`).
- `rules.md`, `decisions.md`: no durable completed decisions.
- `manifest.json`: durable (`package_type: "bundle"`, `type: "business"`, `runtime_contract` with `kind: "canonical"`, `lifecycle_bound: true`); manifest vocabulary verified.
- `routes.php`, controllers, services: present (implementation reality); classification authority requires workspace-level verified objective + completed evidence + boundary declarations, which are absent.

Reconciliation: Procurement remains unclassified / evidence-insufficient. No promotion to `Shared Foundation`, `App`, or `Module` authority level. No restructuring. Manifest vocabulary confirmed durable and independent of classification.

Status added to `engineering/Procurement/work.md` (at top of file):
```
Status: classification evidence insufficient (metadata reconciliation 2026-09-13); no promotion; workspace evidence required before architecture promotion.
```
No other changes to Procurement workspace files.

---

## 5. Protected residual files — preserved (not staged, not modified)

Confirmed untouched in working tree after reconciliation:
- `docs/active/README.md` — preserved (current-state router, not superseded).
- `docs/active/hospitality-app-foundation.md`, `hospitality-operator-composition-plan.md`, `hospitality-operator-actions-plan.md`, `hospitality-readiness.md` — preserved (durable active references per audit Bucket 2).
- `work/shared-parties-foundation/docs/shared-parties-foundation/canonical-party-contract.md` — preserved (session work, uncommitted per rules).
- `work/` contents (other session artifacts) — preserved, uncommitted.
- `storage/logs/app-lifecycle.log` — preserved (excluded by audit constraint).
- `storage/tmp/` — preserved (excluded).
- `storage/update-channels/` — preserved (excluded).
- `docs/architecture/markdown-freshness-authority-audit.md` — preserved; no mass archive performed.

---

## 6. Verification

- `git status --short`: confirms only `docs/architecture/metadata-vocabulary-reconciliation.md`, `engineering/Procurement/work.md` (status header), and confirmed superseded docs modified; no `work/` or `storage/` content staged; `docs/active/` durable files unmodified.
- `git diff --cached --stat` / `git diff --cached --check`: clean.
- No `work/` content staged; no `storage/logs/` staged; no `docs/active/README.md` or hospital files modified incorrectly.
- No namespace migration; no schema/runtime redesign; no new controllers/routes/services.
- `docs/architecture/metadata-vocabulary-reconciliation.md` does not claim shared-foundation promotion; explicitly states preservation.

---

## 7. Related workspace state

- `engineering/Manufacturing/work.md`: updated (committed in previous milestone) — references canonical brief and evidence-only note; no contradiction with reconciliation.
- `engineering/Platform/work.md`: unchanged; not targeted.
- `engineering/Studio/work.md`: unchanged; placeholder READMEs preserved (Bucket 8), not edited.
- `engineering/Hospitality/work.md`: unchanged; active focus preserved.

---

## Milestone Status

- Milestone: Metadata / Documentation Reconciliation — bounded audit complete.
- Shared/Foundation promotion: CLOSED / NOT AUTHORIZED (reaffirmed in this milestone).
- No code/schema/runtime/migration change made.
- Only durable documentation artifacts produced: `docs/architecture/metadata-vocabulary-reconciliation.md` (reconciliation audit), `engineering/Procurement/work.md` (evidence-insufficient status header), minimal supersession headers on confirmed superseded/stale docs.
- Residual work (uncommitted session/work artifacts, other durable docs) preserved per rules.
