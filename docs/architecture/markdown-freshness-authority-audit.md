# Markdown Freshness & Authority Audit

Status: Docs-only classification audit. Read-only. No files moved, renamed, deleted, or archived.
Date: 2026-08-23
Scope: All project-owned markdown outside `.git/`, `vendor/`, `storage/`, and generated delivery output.

## 1. Purpose

This audit classifies repository markdown by authority level so a future reviewed archive/marking pass can act on evidence instead of guesses. It does not authorize any move, delete, rename, or archive operation. Old is not treated as useless: most historical material here is deliberate validation evidence under the existing documentation rules (`docs/CURRENT.md` → Documentation Rules).

## 2. Inventory Summary

Approximately **550 project-owned markdown files**:

| Area | Count | Dominant nature |
|---|---|---|
| `docs/architecture/` | 107 | Contracts, plans, checkpoints mixed in one folder |
| `engineering/**` | 202 | Owner workspace state (overview/rules/work/decisions) |
| `docs/migration-cleanup/` | 54 | Historical cleanup maps, batch proofs, domain reports |
| `apps/**` | 69 | Owner AGENTS.md, placeholder READMEs, tool contracts |
| `resources/themes/` | 12 | Theme layer contracts + variant READMEs |
| `audit/` | 10 | Dated verification audits |
| `docs/runtime/` (+ `operator/`) | 27 | Studio extraction/localization checkpoints, operator guides |
| `docs/contracts/erp-app-studio/` | 9 | Legacy-era manifest/view contracts |
| `docs/identity/` | 4 | Identity/terminology stabilization baseline |
| `scripts/**`, `platform/`, `packages/`, `app/`, `plugins/`, `.github/` | ~17 | Tooling contracts and owner AGENTS.md |
| Repo root | 6 | Startup authority + two large session logs |

## 3. Classification Buckets

### Bucket 1 — Startup authority

**Criteria:** Docs that define rules, current state, or routing that every agent must read before work. Short, current, and load-bearing.

**Representative files:**

- `AGENTS.md` — rules portion only (top of file; see Bucket 9 for its session-log body)
- `docs/CURRENT.md` — current-state router
- `docs/BACKLOG.md`
- `README.md`, `ARCHITECTURE.md`, `NAMING_CONVENTION.md`
- `docs/architecture/ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md`
- `docs/architecture/CORE-LOCK-POLICY.md`, `APP-CONTRACT.md`, `MODULE-CONTRACT.md`, `CLEANUP-AND-LIFECYCLE.md`, `ROUTING-STANDARD.md`, `SECURITY-POLICY.md`
- `docs/active/README.md`, `docs/discussions/README.md` — folder protocol definitions

**Risks:**

- Root `AGENTS.md` mixes ~200 lines of durable rules with ~5,700 lines / 168 dated session summaries. A new agent cannot distinguish law from history without reading everything. Already acknowledged as backlog debt.
- `ARCHITECTURE.md` still leads with "IPM ERP" product framing while `docs/identity/` establishes OdareHub terminology; terminology drift between startup docs.

**Next action:** Keep authoritative. Split the session-history block out of `AGENTS.md` only as part of the already-queued reviewed archive task (Bucket 9). No other changes needed.

### Bucket 2 — Current active task context

**Criteria:** Live implementation briefs and handoffs feeding work that is actually in progress right now.

**Representative files:**

- `docs/active/hospitality-app-foundation.md` — declared active task in `docs/CURRENT.md`
- `docs/active/hospitality-readiness.md`
- `engineering/Hospitality/work.md` — slice 1 completed 2026-08-23, sequence ongoing
- `docs/architecture/hospitality-readiness-audit.md` and `docs/architecture/app-ownership-classification-and-hospitality-readiness.md` — readiness inputs to the active brief
- `docs/architecture/shared-app-extension-readiness.md` — same-day follow-on readiness audit (2026-08-23)
- Per-owner `engineering/<owner>/work.md` sections marked *Current Focus* / *In Progress* (e.g., Shell composer decomposition)

**Risks:**

- Active briefs go stale silently after completion; `docs/active/README.md` requires manual retirement that has no enforcement.
- Hospitality context currently lives across four files (`docs/active/` x2, `docs/architecture/` x2) plus `engineering/Hospitality/`; correct per protocol, but easy to update one and miss the others.

**Next action:** Treat as highest-freshness priority. When the hospitality foundation completes, retire the briefs per `docs/active/README.md` (move durable decisions to `engineering/Hospitality/decisions.md`).

### Bucket 3 — Official architecture contracts

**Criteria:** Cross-owner rules that constrain code even when they are not startup reading. Usually named `*-contract.md`, or referenced as mandatory from AGENTS/gates.

**Representative files:**

- Style chain: `style-registry-ownership-contract.md`, `shell-style-socket-contract.md`, `style-rendering-contract.md`, `resolved-style-consumer-contract.md`, `registry-read-contract.md`, `platform-style-consumption-surface-contract.md`, `shell-consumption-contract.md`, `runtime-style-application-contract.md`, `shell-insertion-planning-contract.md`, `theme-source-compilation-migration.md`, `appearance-domain-contract.md`, `universal-component-contract-v1.md`, `shell-behavior-rendering-contract-v1.md`
- Label family (11 contracts): `label-resource-contract.md` … `label-runtime-handoff-contract.md`
- Report family: `report-resource-contract.md`, `report-runtime-contract.md`, `report-validation-contract.md`
- Studio governance: `studio-operating-contract.md`, `customization-studio-operating-contract.md`, `studio-tool-lifecycle-contract.md`, `studio-change-lifecycle-apply-contract.md`, `studio-customization-tools-contract.md`
- Experience composition: `experience-composition-architecture-plan.md` (implemented map + bridges), `owner-capability-catalog-contract.md`, `surface-contribution-contract.md`, `resolved-runtime-contract-pipeline.md`
- Localization: `localization-file-structure-v1-contract.md`, `localization-studio-v2-edit-apply-contract.md`
- Outside `docs/architecture/`: `resources/themes/contracts/*` (4), `scripts/architecture/gate-runner-contract.md`, `core-lock-gate-contract.md`, `system-app-contract-enforcement.md`, `platform/Engineering/EngineeringWorkspaceAgentProtocol.md`, `apps/Shell/Overlay/OverlayContract.md`, `docs/widget-contribution-runtime-contract.md`, `docs/resolved-experience-contract.md`

**Risks:**

- **Authority signal erosion**: `docs/architecture/` holds 107 files where contracts, one-time plans, checkpoints, and audits sit side by side with no status convention. Nothing marks which contracts are active law vs executed planning artifacts.
- Naming collision: `docs/architecture/AGENT-COMPLIANCE-CHECKLIST.md` is a blank 40-line template while root `AGENT-COMPLIANCE-CHECKLIST.md` is a 4,857-line session log — same name, opposite purposes.
- Contract sprawl: the style-chain alone has ~12 sequential contracts; later ones supersede phases of earlier ones without always stating it (e.g., `rendered-admin-proof-checkpoint-contract.md` freezes what planning contracts planned).

**Next action:** Keep all as authority. In a future low-risk pass, add a one-line status header (`Status: active contract` vs `Status: executed plan, retained as evidence`) to `docs/architecture/` files during ordinary touched-file edits rather than a mass edit.

### Bucket 4 — Owner workspace state

**Criteria:** `engineering/<owner>/{overview,rules,work,decisions}.md` and owner-level `AGENTS.md` files. Authoritative for their owner, current by convention, self-correcting via work logs.

**Representative files:**

- `engineering/**` (202 files): Shell, Platform, Studio, Manufacturing (+ modules), SBAIO, Plugin, Packages, Hospitality
- Owner AGENTS.md: `apps/{Shell,Platform,Studio,Manufacturing,SBAIO,Hospitality}/AGENTS.md`, `apps/*/modules/AGENTS.md`, `plugins/Base/AGENTS.md`, `packages/AGENTS.md`, `app/AGENTS.md`
- `apps/Studio/STUDIO-CHARTER.md`, `STUDIO-CAPABILITY-INVENTORY.md` — Studio-owned governance docs

**Risks:**

- `engineering/Shell/work.md` (261 lines) accumulates slice history; "Current Focus" can lag behind reality when sessions skip updates (root `AGENTS.md` shows work newer than some workspace entries).
- Workspace files duplicate session-summary content that also lands in root `AGENTS.md` / checklist — three places record the same evidence.

**Next action:** Keep. Enforce the existing rule that completed-session evidence goes into owner `work.md` so root logs can eventually shrink instead of grow.

### Bucket 5 — Discussion/tentative notes

**Criteria:** Non-official planning notes preserved before graduation. Allowed to be uncertain; never authority for code.

**Representative files:**

- `docs/discussions/context-file-protocol-notes.md`
- `docs/discussions/hospitality-suite-preimplementation-notes.md`
- `docs/discussions/README.md` (protocol definition — also Bucket 1)

**Risks:**

- Graduation is untracked: `hospitality-suite-preimplementation-notes.md` predates the now-official hospitality brief and readiness audits; parts have almost certainly graduated without the note being marked.

**Next action:** Keep. On next touch, add a graduation note pointing at the successor docs. Do not delete.

### Bucket 6 — Historical evidence/checkpoints

**Criteria:** Dated proof of what was done and validated. Keep permanently as evidence; never runtime authority.

**Representative files:**

- `audit/*.md` (10 files, all dated 2026-06-05): theme architecture verification, styling ownership boundary, CTE source-mode migration, shell rendering gate recovery
- `docs/architecture/audit/shell-behavior-rendering-v1-foundation-completion-2026-06-05.md`
- Checkpoints in `docs/architecture/`: `localization-studio-v1-checkpoint.md`, `localization-studio-v1-mvp-checkpoint.md`, `visual-customizer-v1-foundation-checkpoint.md`, `workspace-surface-alignment-checkpoint.md`, `label-designer-phase1-runtime-proof-completion-checkpoint.md`, `rendered-admin-proof-checkpoint-contract.md`, `system-tools-readiness-compliance.md`, `migration-inventory-2026-04-18.md`, `shell-runtime-normalization-2026-06-05.md`, `style-customization-chain-checkpoint.md`
- Exception — active despite the name: `css-token-editor-safety-checkpoint.md` is mandatory pre-change reading for CTE work per root `AGENTS.md`. It belongs to Buckets 1/3, not here.
- `docs/runtime/` Studio extraction/localization checkpoints (`STUDIO-GUI-STUDIO-EXTRACTION-CHECKPOINT.md`, `STUDIO-LOCALIZATION-MIGRATION-CHECKPOINT-BATCH*.md`, `RUNTIME-SMOKE-TEST-CHECKPOINT.md`, etc.)
- `docs/migration-cleanup/maps/batch-*` proofs, `phases/move-plan.md`, and report groups (`studio/`, `experience/`, `runtime/`, `refactor/`, `domain/`, `localization/`)
- Loose completion reports: `docs/phase8-complete.md`, `docs/admin-dashboard-production-ready.md`, `docs/studio-hardening-report-2026-05.md`, `docs/manufacturing-app-boundary-migration.md`
- Executed plans retained as evidence: `docs/architecture/rendered-admin-proof-planning-contract.md`, `resolved-style-consumer-reader-injection-plan.md`, `registry-read-boundary-gate-update-plan.md`, `label-designer-phase1-runtime-proof-plan.md`, `apps/Shell/DesignSystem/Contracts/shell-overlay-implementation-roadmap.md` (self-marked "Implementation complete")

**Risks:**

- Checkpoint-named files hide at least one live-safety doc (`css-token-editor-safety-checkpoint.md`); filename alone is not an authority signal.
- `docs/migration-cleanup/README.md` says "existing root files are not moved yet," but many listed moves have since happened — the workspace's own status text is aging.

**Next action:** Keep in place. No action until the reviewed archive pass; these are exactly the files the existing rules say to preserve.

### Bucket 7 — Superseded/stale candidates (review before any archive)

**Criteria:** Self-declared superseded, replaced by a newer contract covering the same ground, or describing a program state that no longer exists. **Nothing here may be archived without the reviewed migration map required by `docs/CURRENT.md`.**

**Candidates, strongest first:**

1. `docs/access-control-view-architecture.md` — explicitly "superseded … by experience-composition-architecture-plan.md"
2. `docs/unified-operational-landing-strategy.md` — explicitly "historical … superseded"
3. `docs/API-DEPRECATION-POLICY.md` — self-defers to `ROUTING-STANDARD.md`
4. `apps/Shell/DesignSystem/Contracts/shell-overlay-implementation-roadmap.md` — roadmap for a program self-declared complete (2026-07-13); superseded by the minimal-controller contract set
5. `apps/Shell/DesignSystem/README.md` — describes DesignSystem as "inert… nothing consumed", but DesignSystem now hosts the canonical socket catalog and rendering Foundation; factually outdated
6. `docs/sbaio-workbook-to-suite-migration-plan.md` — one-time migration input referencing a local-machine path (`~/Downloads/SBAIO.xlsm`)
7. `docs/phase3-core-app-plugin-contract-hardening.md`, `docs/phase6-3-platform-assignment-interface-plan.md`, `docs/phase6-5-contract-decomposition-design.md`, `docs/manufacturing-phase2-route-normalization.md` — old phase plans whose outcomes landed
8. `docs/architecture/registry-read-boundary-gate-update-plan.md`, `resolved-style-consumer-reader-injection-plan.md`, `resolved-style-consumer-registry-read-planning.md` — planning chains fully executed by implemented gates/code
9. `docs/runtime/STUDIO-CSS-EXTRACTION-PLAN.md`, `STUDIO-JS-EXTRACTION-CHECKPOINT.md`, `STUDIO-GUI-STUDIO-EXTRACTION-{PLAN,CHECKPOINT}.md`, `STUDIO-TOOL-SEPARATION-PLAN.md` — if extraction batches are confirmed complete in `docs/migration-cleanup/studio/` reports
10. `docs/multilanguage-change-guide.md`, `docs/operator-realtime-deployment.md`, `docs/operator-layer-phase8-realtime-updates.md` — verify currency against localization-file-structure-v1 and current realtime deployment before judging

**Risks:**

- Some "stale" docs are still cited by gates/probes (gate scripts grep for doc existence and phrases). Archiving without checking `scripts/architecture/*` references will break gates.
- Superseding docs do not consistently back-link to what they replace, so staleness is discoverable only by content comparison.

**Next action:** Freeze this list. Any archive/marking work must (a) grep `scripts/`, gates, and probes for references first, (b) produce a per-file reviewed migration map, (c) prefer adding a `Superseded by X` header over moving files.

### Bucket 8 — Placeholder/future scaffolding docs

**Criteria:** Directory READMEs declaring intentional emptiness that guards a designed future shape. Keep only while the guarded future work is still intended.

**Representative files (~40):**

- `apps/Platform/StyleRegistry/{Services,Diagnostics,Resources,Contracts}/README.md` — real registry contract exists; placeholders partially overtaken
- `apps/Studio/Tools/CustomizationStudio/**/README.md` — Services, Views, Controllers, assets, Resources/socket-catalog, SubTools/{AdvancedCssTool,ComponentStyleEditor,LayoutStyleEditor,MotionStyleEditor,PrintStyleEditor,ThemeManager,VisualCustomizer,VisualizationStyleEditor}, Advanced/CssLiveEditor
- `apps/Shell/DesignSystem/{Services,Views,Diagnostics,Resources,Resources/socket-catalog}/README.md`
- `resources/themes/{foundation,semantic,variants/*}/README.md`
- `apps/Studio/Tools/CustomizationStudio/Contracts/visual-customizer-*-plan.md` (12 planning docs guarding disabled mutation capabilities — apply/approval/persistence remain intentionally off)

**Risks:**

- Several placeholders describe a world that changed (DesignSystem inert; StyleRegistry "no consumption connected" while read-side adapters/diagnostics now exist).
- The visual-customizer plan cluster guards genuinely deferred work — deleting would lose intent; keeping without status markers makes them look stale.

**Next action:** Keep. Refresh factually-wrong placeholder text opportunistically when the owning area is next touched. Do not mass-edit.

### Bucket 9 — Duplicate/session-log candidates

**Criteria:** Content that duplicates another doc's purpose, or append-only session history living in a rules/authority file.

**Representative files:**

1. `AGENTS.md` — 168 `## Session Summary` blocks (~5,700 of 5,982 lines) duplicating evidence that also belongs in owner `work.md`. Backlog item already exists for this.
2. `AGENT-COMPLIANCE-CHECKLIST.md` (root, 4,857 lines) — despite the name it is a second session log; the same sessions appear in both root files (verified: the 2026-07-20 search-provider entry exists in each). True duplication, not just accumulation.
3. `docs/architecture/AGENT-COMPLIANCE-CHECKLIST.md` — unrelated 40-line blank template colliding with the root log's name.
4. `MIGRATION-CLEANUP-INDEX.md` — root pointer index whose content overlaps `docs/migration-cleanup/README.md`.

**Risks:**

- Highest agent-context cost in the repo: every session appends to two root files, and agents ingest both on startup.
- Name collision invites agents to edit the wrong checklist.

**Next action:** Fold into the single queued docs-cleanup task: extract session history from both root files into one reviewed historical archive (e.g., under `docs/history/` or `docs/migration-cleanup/`), leave pointers behind, and resolve the checklist naming collision. Requires explicit approval as a dedicated docs task — not drive-by editing.

### Bucket 10 — Ignored docs (excluded from repo context)

**Criteria:** Generated, vendored, machine-local, or external-tooling content that is not project documentation truth.

**Scope:**

- `vendor/**`, `storage/**` — excluded by constraint (dependency and runtime data)
- `node_modules/**` if present
- Generated delivery output under `public/assets/**` (no tracked markdown expected; never a source of truth)
- `.github/instructions/ui-localization-theme.instructions.md`, `.github/skills/ipm-erp-implementation/SKILL.md` — external coding-agent tooling config; repo-tracked but tool-owned, not architecture authority
- OS/editor junk (`.DS_Store` class files) — none tracked as md

**Risks:** Low. Only risk is treating tool-config instructions as project law.

**Next action:** None.

## 4. Special-Attention Findings

| Item | Finding | Bucket |
|---|---|---|
| Root `AGENTS.md` | Rules are sound; 95% of the file is session history. Split queued in BACKLOG. | 1 + 9 |
| `AGENT-COMPLIANCE-CHECKLIST.md` | Misnamed session log; duplicates `AGENTS.md` summaries; collides with the architecture template of the same name. | 9 |
| `MIGRATION-CLEANUP-INDEX.md` | Redundant root pointer; folder README covers the same links. | 6/9 |
| `docs/migration-cleanup/` | Valuable evidence; internal status text ("not moved yet") is outdated. | 6 |
| `docs/runtime/` | Mostly completed Studio checkpoints; operator guides are semi-active reference. | 6 |
| `audit/` | Clean, uniformly dated, purely evidentiary. | 6 |
| `*checkpoint*` in docs/architecture | Mostly historical; **one exception**: `css-token-editor-safety-checkpoint.md` is live mandatory safety reading — never archive. | 6 (1 exception) |
| `*readiness*` in docs/architecture | Mixed: `hospitality-readiness-audit.md` + `shared-app-extension-readiness.md` feed the active task; `label-designer-implementation-readiness-audit.md`, `universal-component-contract-readiness-audit.md`, `view-composition-contract-readiness-audit.md` are consumed inputs to finished foundations. | 2/6 |
| Placeholder READMEs (Studio/Shell/Platform) | ~40 files; several factually outdated about their own areas; guard real deferred work. | 8 |
| Hospitality docs | Current and consistent across `docs/active/`, `docs/architecture/`, `engineering/Hospitality/`, `apps/Hospitality/AGENTS.md`. Highest-freshness tier. | 2 |

## 5. Highest-Risk Stale-Doc Candidates

Ranked by potential to mislead an agent:

1. **Root `AGENTS.md` session-history block** — largest context-pollution source; agents may treat dated workarounds as current rules.
2. **Root `AGENT-COMPLIANCE-CHECKLIST.md`** — misnamed log, duplicates #1, and collides with `docs/architecture/AGENT-COMPLIANCE-CHECKLIST.md`.
3. **`apps/Shell/DesignSystem/README.md`** — claims the tree is inert while it now carries runtime-referenced socket catalogs and rendering Foundation.
4. **`docs/access-control-view-architecture.md`** — superseded but still discoverable as a top-level doc without following its own supersede note.
5. **Executed style-chain planning docs** (`registry-read-boundary-gate-update-plan.md`, `resolved-style-consumer-reader-injection-plan.md`) — describe gate states that have since changed; risk of re-planning finished work.
6. **`docs/API-DEPRECATION-POLICY.md`** — defers to `ROUTING-STANDARD.md` but could still be quoted as policy.

## 6. Guardrails For The Future Archive Pass

- One reviewed migration map per batch, per `docs/CURRENT.md` Working Safety and `CLEANUP-AND-LIFECYCLE.md`.
- Grep `scripts/architecture/`, gate scripts, and probes for doc-path/doc-phrase references before touching anything — gates assert on doc existence and content.
- Prefer in-place status headers (`Superseded by …`, `Historical evidence`) over file moves; moves only for the root session-log extraction.
- Never touch `css-token-editor-safety-checkpoint.md` in an archive sweep.
- Do not rewrite historical content when marking; prepend status only.

## 7. Routing

- Follow-up work item recorded in `docs/BACKLOG.md` (Documentation Backlog).
- Current-state router updated in `docs/CURRENT.md` Context Routing.
