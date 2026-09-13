# Future Architecture Planning — Ground-State Preparation

Session: OdareHub Future Architecture Planning — ground-state prep for A-D pressure-test workstreams
Canonical authority: `docs/architecture/odarehub-future-architecture-planning-brief.md`
Canonical commits: `085664a` (freeze brief), `4b0fcbc` (route workers)
Date: 2026-09-13

## 1. Branch / HEAD / working-tree status

- Current branch: `main`
- Current HEAD: `12f97b3` `feat(manufacturing): product item_ref assignment (transparent opaque-INT reference)`
- The canonical brief and A-D workstream files exist ONLY on branch:
  `lazydeepak-future-architecture-planning-brief` (tip `4b0fcbc`), which is `12f97b3` + `085664a` + `4b0fcbc`.
- `origin/main` does not yet contain the canonical brief / routing commits (not merged, not pushed).
- Working-tree modifications (pre-existing, preserved):
  - `apps/Manufacturing/modules/Products/Views/part_360.php` (item_ref display line) — unrelated, untouched.
- Untracked (preserved):
  - `docs/active/future-architecture-planning-manufacturing-phase3-evidence.md`
  - `storage/logs/app-lifecycle.log`, `storage/tmp/`, `work/` (Shared Parties Session B handoff)

## 2. Canonical brief readable + authoritative

- `git cat-file -e 4b0fcbc:docs/architecture/odarehub-future-architecture-planning-brief.md` OK (1206 lines).
- Not present in `main` working tree (`docs/architecture/odarehub-future-architecture-planning-brief.md` missing on main).
- Locally verified file extracted to /tmp for reading.

## 3. Worker-routing + A-D briefs present

- All present on `4b0fcbc`:
  - `docs/active/future-architecture-workstream-a.md`
  - `docs/active/future-architecture-workstream-b.md`
  - `docs/active/future-architecture-workstream-c.md`
  - `docs/active/future-architecture-workstream-d.md`
  - `docs/active/README.md` (routing index)
  - `docs/CURRENT.md`, `AGENTS.md`, `ARCHITECTURE.md`, `README.md`, owner `AGENTS.md` (Manufacturing/Hospitality/Platform) updated with routing.
- Status: all workstream briefs set to "Active pressure-test brief", canonical authority pointed at the brief.

## 4. No stale planning doc contradicts canonical brief

- `docs/active/future-architecture-planning-manufacturing-phase3-evidence.md` (previous session's Phase 3 evidence)
  was RE-ANNOTATED as SUPERSEDED-as-conclusion / evidence-only, subordinate to the canonical brief,
  so it cannot misroute workers. Evidence content retained.
- `engineering/Manufacturing/work.md` reconciled to canonical routing (brief subordination + evidence-only entry).
- No other stale docs found that contradict the brief.

## 5. A-D writable/report boundaries

- A: Identity, Context, Time, Governance — report to workstream-a doc.
- B: Domain Ownership, Extensions, Evolution — report to workstream-b doc.
- C: Transactions, Integrity, Failure — report to workstream-c doc.
- D: Composition, UX, Search, Scale, Operability — report to workstream-d doc.
- Boundaries are non-overlapping (concerns listed in each brief are distinct; shared gauntlet scenarios are
  analyzed from each workstream's own perspective).

## 6. Workers inspect broadly, write only to designated report files

- Each workstream brief designates its own output (the workstream doc + canonical brief deliverables).
- Confirmed no instruction lets a workstream write outside its report area.

## 7. No Shared/Foundation code/schema/storage/runtime/migration/feature changes allowed

- Enforced by workstream briefs' "Boundaries" sections (all four).
- No implementation authorized. Ground-prep made doc-only changes only.

## 8. Read-only probes/scans + documentation analysis allowed

- Workstream briefs permit analysis, concrete state transitions, example records, resolution flows.
- No runtime mutation.

## 9. Synthesis remains planner decision

- Workstream briefs record findings; workers do not vote on final architecture.
- Canonical brief explicitly keeps synthesis with planning session.

## BLOCKERS / decision required from user

1. **Branch vs main:** Canonical brief + A-D briefs + routing exist only on
   `lazydeepak-future-architecture-planning-brief`, not on `main`/`origin/main`.
   A-D workers must run from that branch (or the routing must be ported to main by the user/planner).
   Not merged here (no destructive/merge git actions per constraints).
2. **Manufacturing evidence interpretation:** retained as evidence-only; already re-annotated. No further action needed from workers.

## Next step (no action taken this session)

On user confirmation of branch strategy: run A-D pressure-test workstreams on
`lazydeepak-future-architecture-planning-brief`, each writing only to its workstream report file
and the common gauntlet deliverables — no Shared/Foundation change.