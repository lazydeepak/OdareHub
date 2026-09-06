# Root Session Log Cleanup Plan

Status: Planning only. No cleanup execution is authorized by this document.
Date: 2026-08-23
Builds on: `docs/architecture/markdown-freshness-authority-audit.md` (Buckets 1 and 9)

## 1. Problem Statement

Two repo-root files mix durable authority with append-only session history:

### Root `AGENTS.md`

- ~5,982 lines total; the leading rules block is durable startup authority (Start Here routing, Core Lock Rule, wrapper confinement, CSS safety, charter links), while roughly 5,700 lines are **168 dated `## Session Summary` blocks**.
- New agents ingest months of history as if it were law; dated workarounds can be mistaken for current rules.
- Growth is structural: line 2550 states "Every task must end with: AGENT-COMPLIANCE-CHECKLIST.md", and each session appends to both root files. Archiving without changing that convention would regrow the problem.

### Root `AGENT-COMPLIANCE-CHECKLIST.md`

- Despite its name, it is a second session log (~4,857 lines). The same sessions appear in both root files (verified for the 2026-07-20 entries): duplication, not just accumulation.
- Name collision: `docs/architecture/AGENT-COMPLIANCE-CHECKLIST.md` is an unrelated 40-line blank checklist template. Same name, opposite purposes.

## 2. Safety Constraints

1. **Do not delete history.** All session summaries must be preserved verbatim somewhere reviewable.
2. **Do not break gates or probes.** Gate scripts embed literal filename strings from these files; see Section 3 before touching anything.
3. **Preserve startup routing.** Root `AGENTS.md` must remain the rules-and-startup entry point referenced by `docs/CURRENT.md`; a pointer to archived history replaces extracted blocks.
4. **Avoid mass edits.** Extraction is mechanical (move whole `## Session Summary` blocks), never rewriting summary content.
5. **No casual rename.** The root checklist filename is load-bearing in five gate scripts until they are updated in the same reviewed change.
6. **Do not rewrite historical content when marking.** Prepend status lines only.
7. **Coordinate with active work.** Hospitality implementation is live (`docs/active/hospitality-app-foundation.md`); root-file rewrites must not run concurrently with sessions that append new summaries.

## 3. Dependency Scan Plan And Findings

Stage 0 of the staged implementation repeats this scan immediately before any execution. Scan method: `rg -l 'AGENT-COMPLIANCE-CHECKLIST|AGENTS\.md'` across `scripts/`, `apps/`, `platform/`, `docs/`, `engineering/`, `.github/`, excluding `.git/`, `vendor/`, `storage/`, `node_modules/`. Findings below are the 2026-08-23 baseline.

### 3.1 Root `AGENT-COMPLIANCE-CHECKLIST.md` — hard dependencies

Five architecture gates embed the literal filename as diff-scan exclusions/allowlists. They do not read its content; they filter it out of git-diff / runtime-addition scans:

| Gate | Lines | Usage |
|---|---|---|
| `scripts/architecture/check_studio_boundary.sh` | 192, 198 | `grep -Ev 'AGENT-COMPLIANCE-CHECKLIST\|...'` exclusion |
| `scripts/architecture/check_studio_enforcement_readiness.sh` | 298, 310 | `grep -Ev` exclusion |
| `scripts/architecture/check_capability_ownership_boundaries.sh` | 198 | `grep -Ev` exclusion |
| `scripts/architecture/check_resolved_experience_truth.sh` | 237 | allowlist regex `^AGENT-COMPLIANCE-CHECKLIST\.md` |
| `scripts/architecture/check_surface_contribution_contracts.sh` | 533, 546 | allowlist regex `^AGENT-COMPLIANCE-CHECKLIST\.md` |

Consequences:

- The exact root filename must survive until these five gates are updated in the same change set.
- Extraction into `docs/history/**` is compatible with today's patterns: every gate above also excludes lines matching `docs/`, so moved-history diff lines remain excluded.
- A pure rename (without gate updates) risks false-positive failures: session summaries contain phrases like `assigned_apps`, `workspaceData`, "bypass approval" that these scanners hunt for once the filename exclusion stops matching.

One PHP runtime reference (soft):

- `apps/Studio/Services/StudioReferenceDiscoveryClassificationTrait.php:19` classifies any path containing `AGENT-COMPLIANCE-CHECKLIST.md` as `RELEVANCE_SELF_REFERENCE` during Owner Structure reference discovery. Renaming changes how future references classify; not a gate failure, but it belongs in the same change set as any rename.

Evidence-only references (no action needed): `audit/theme-architecture-v1-completion-2026-06-05.md`, `docs/runtime/STUDIO-VISUAL-FIRST-MILESTONE-CHECKPOINT.md`, `docs/architecture/report-contribution-audit.md`, `report-validation-discovery-audit.md`, `report-runtime-discovery-audit.md`, `migration-inventory-2026-04-18.md`, `read-only-consumption-probe-contract.md`, `docs/migration-cleanup/maps/root-file-inventory.md`, `docs/migration-cleanup/maps/batch-1-docs-cleanup.md`, `docs/migration-cleanup/domain/ORGANIZATION-UPGRADE-PLAN.md`, `docs/migration-cleanup/localization/LOCALIZATION-PHASE-1-SUMMARY.md`.

### 3.2 Root `AGENTS.md` — dependencies

- **No architecture gate or probe reads or asserts on root `AGENTS.md`.** All gate references to `AGENTS.md` files target owner-level copies (`apps/*/AGENTS.md`, `$app_dir/AGENTS.md`) via existence/content checks in `check_system_app_contracts.sh:278`, `check_business_app_module_contracts.sh:403,405`, `check_studio_enforcement_readiness.sh:217-248`, and a name-exclusion at `check_capability_ownership_boundaries.sh:283`.
- Inbound role references that must keep working (file stays, role unchanged):
  - `docs/CURRENT.md` Documentation Rules — "`AGENTS.md` is for rules and startup routing."
  - `docs/BACKLOG.md` — names the root session-history block as queued debt.
  - `docs/discussions/context-file-protocol-notes.md` (two mentions).
- Line-number citations that will drift once summaries move (historical docs citing historical summaries; accept drift, never fix retroactively):
  - `docs/architecture/scan-mode-contract.md:131` — cites `AGENTS.md:485-489`
  - `docs/architecture/workspace-surface-alignment-checkpoint.md:124` — cites lines 485–489
- Internal growth drivers: the line-2550 completion convention plus 102 per-session "Files modified" bullets naming the checklist file (historical body text only).

### 3.3 Phrases / facts that must remain in place until Stage 4 completes

1. `AGENT-COMPLIANCE-CHECKLIST` as an existing repo-root filename (five gate scripts).
2. Root `AGENTS.md` existing at repo root as the startup-rules file (doc router references).
3. Owner-level `AGENTS.md` files everywhere (gate existence checks) — untouched by this plan regardless.
4. The blank template at `docs/architecture/AGENT-COMPLIANCE-CHECKLIST.md` keeps its path until collision resolution renames exactly one side deliberately (it has zero external references today).

## 4. Proposed Archive Strategy

1. Create `docs/history/session-summaries/` as the single archive home.
   - Split by source and month to stay navigable, e.g. `agents-session-log-2026-06.md`, `agents-session-log-2026-07.md`, `compliance-checklist-session-log-2026-06.md`. One file per source per month, preserving block order and content verbatim.
   - Each archive file begins with a status header: "Historical session log extracted from `<source>` on `<date>`. Evidence only. Not runtime authority."
2. Extract only complete `## Session Summary` blocks. Durable rule sections of root `AGENTS.md` never move.
3. Leave behind, at the extraction point in root `AGENTS.md`, a short pointer section ("Session history archived under `docs/history/session-summaries/` on `<date>`") and a relocated completion convention so future evidence lands in owner `work.md` / the archive instead of regrowing root files.
4. Checklist collision resolution (executed in Stage 3, after dependency confirmation):
   - **Preferred:** reduce root `AGENT-COMPLIANCE-CHECKLIST.md` to a short real compliance checklist + archive pointer, moving the log body to the archive. Gates keep working unchanged because the filename persists.
   - **Alternative (more churn):** rename the root file truthfully in the same commit that updates all five gate scripts and notes the Studio classification trait.
   - Either way, rename the blank `docs/architecture/AGENT-COMPLIANCE-CHECKLIST.md` template (e.g., `agent-compliance-template.md`) to end the collision.
5. `docs/migration-cleanup/maps/` receives a batch map documenting the extraction (per established cleanup-workspace practice).

## 5. Staged Implementation

Each stage is a separate reviewed change set with its own validation; later stages execute only if earlier ones pass cleanly.

**Stage 0 — Reference scan only.** Re-run Section 3 greps immediately before execution; fail closed if any new consumer appeared. Record findings in the batch map. No file changes beyond the map.

**Stage 1 — Status headers/pointers without moving.** Prepend short status headers to both root files: session history noted, canonical future location `docs/history/session-summaries/`, pointer to this plan. No moves, no deletions. Gates unaffected: filename unchanged and headers introduce no scanner-trigger phrases.

**Stage 2 — Extract session summaries to archive.** Create `docs/history/session-summaries/`, copy all blocks verbatim by source/month, then remove them from the root files leaving only the pointer section. Run full grep re-verification (Section 7) plus the aggregate gate runner inside the same change set.

**Stage 3 — Shrink startup files.** Root `AGENTS.md` retains durable rules + Start Here + history pointer; relocate the "Every task must end with..." convention to point at owner `work.md` evidence plus the archive path. Execute the chosen Section 4.4 collision option.

**Stage 4 — Update gates/docs if needed.** Only under the rename option: update five gate-script strings, note `StudioReferenceDiscoveryClassificationTrait.php`, refresh `docs/migration-cleanup/maps/root-file-inventory.md`, adjust `docs/CURRENT.md` Documentation Rules wording if behavior changed, and close the matching `docs/BACKLOG.md` items.

## 6. Risk List

| Risk | Mitigation |
|---|---|
| Broken agent startup instructions | Root `AGENTS.md` keeps rules + Start Here; pointer replaces history; `docs/CURRENT.md` routing unchanged |
| Broken architecture gates | Filename frozen through Stage 2; gate edits only in Stage 4, same commit as any rename |
| False-positive gate failures on moved text | Archive under `docs/history/**` matches existing `docs/` exclusions in all five scanning gates |
| Lost historical evidence | Copy-first verbatim extraction; root deletion only after archive verification; batch map records block/byte counts |
| Drifting line-number citations | Two known citations accepted as drifted historical docs; documented here, never rewritten |
| Regrowth of root logs | The completion convention itself is relocated in Stage 3; otherwise the problem returns |
| Merge conflicts with active Hospitality work | Do not execute while a Hospitality session is appending summaries; sequence around `engineering/Hospitality/work.md` milestones |
| Studio tool classification shift | Trait reference documented; relevant only under the rename option |

## 7. Validation Plan

Per stage:

1. `git diff --check` clean.
2. Grep reference checks against the Section 3 baseline:
   - `rg -l 'AGENT-COMPLIANCE-CHECKLIST' scripts/ apps/ platform/ docs/ engineering/ .github/`
   - `rg -n 'AGENT-COMPLIANCE' scripts/architecture/*.sh` — exactly the five known sites after any gate edit
   - Confirm no gate/probe asserts on root `AGENTS.md` content before and after.
3. After Stages 2–4 (authoritative docs changed): run `scripts/architecture/run_architecture_gates.sh`; prefer the full `bash scripts/system/check_deployment_readiness.sh` entry point. Zero new failures required.
4. Spot-read the first ~60 lines of post-cleanup root `AGENTS.md` to confirm startup routing survives.
5. Archive integrity: extracted-block count equals removed-block count (168 expected at the 2026-08-23 snapshot), byte counts recorded in the batch map.

## 8. Non-Goals

- No cleanup execution in this task — planning only.
- No moves, deletes, renames, or archives of any file.
- No edits to root `AGENTS.md`, root `AGENT-COMPLIANCE-CHECKLIST.md`, or `docs/architecture/AGENT-COMPLIANCE-CHECKLIST.md`.
- No edits to PHP/runtime/Core/loader/Shell files.
- No rewriting or summarizing of historical session content.
- No changes to owner-level workspace files or active Hospitality docs.

