# Style Compliance CSS Ownership Ledger

Status: Governance-enforced baseline for Style Compliance presentation ownership.

## Gate Evolution

`check_style_compliance_css_ownership.sh` now acts as a governance advisor, not only a binary boundary checker.

Current advisor capabilities:

- preserves hard boundary failures for ownership contract violations
- classifies findings with ownership guidance and confidence
- supports concise default output, `--verbose` output, and machine-readable `--json` output
- keeps repair/apply/backend/scanner behavior read-only and unchanged

### Phase 1: What the gate checks now

Hard boundary checks:

- inline `<style>` prevention in Style Compliance header view
- required owner/shared stylesheet links in header view
- owner stylesheet location and ownership header markers
- shared primitive usage for SC action links (`gs-tool-action-link`)
- forbidden local recreation of shared `gs-tool-*` primitives
- forbidden global `[hidden]` override in owner CSS

Advisory checks:

- generic selector heuristics in owner CSS (`grid/layout/card/panel/table/...`)
- local visual constant detection (colors, px/rem dimensions, radius, motion, z-index, breakpoints)
- allowlisted domain-style acceptance with rationale

Historical gap that is now closed:

- previous gate mostly produced pass/fail text only
- new advisor now emits: target, current owner, suggested owner, violation type, reason, recommended action, confidence

## Scope

- `apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/*`
- `apps/Studio/styles/gui_studio.css`
- `apps/Studio/styles/style-compliance.css`
- `scripts/architecture/check_style_compliance_css_ownership.sh`

## 1) Ownership Audit Summary

### Foundation (application-agnostic primitives)

Owned in shared Studio layer (`apps/Studio/styles/gui_studio.css`):

- `gs-tool-page`
- `gs-tool-header`
- `gs-tool-header-title`
- `gs-tool-header-actions`
- `gs-tool-action-link`
- `gs-tool-scope-strip`
- `gs-tool-scope-form`
- `gs-tool-scope-hint`
- `gs-tool-status-bar`
- `gs-tool-scroll-table`
- `gs-tool-closed-label` / `gs-tool-open-label`

### Studio / Shell reusable workspace rendering

Consumed by Style Compliance markup:

- page shell via `gs-tool-page`
- header chrome via `gs-tool-header*`
- toolbar scope strip via `gs-tool-scope-*`
- safety bar via `gs-tool-status-bar`
- table overflow via `gs-tool-scroll-table`

### Theme system (visual identity tokens only)

Style Compliance owner CSS consumes tokenized values:

- `var(--style-*)`
- `var(--color-*)`
- `var(--text)` / `var(--muted)` / `var(--accent)`

### Style Compliance owner CSS (domain semantics)

Owned in `apps/Studio/styles/style-compliance.css`:

- scan aura/orbit visual language
- cockpit mission/action metrics (`sc-cp-*`)
- findings/readiness/repair queue semantics (`sc-readiness-*`, `sc-queue-*`)
- domain sections (`sc-section-collapsible`, `sc-diagnostics-wrap`, `sc-action-summary`)
- inventory/proposal/evidence presentation (`sc-proposal-*`, `sc-evidence-*`, `sc-token-*`)

## 2) Ownership Changes Implemented

- Externalized Style Compliance CSS from inline `<style>` in `Views/Shared/_page_header.php` into `apps/Studio/styles/style-compliance.css`.
- Added explicit stylesheet linkage in `Views/Shared/_page_header.php`:
  - `/assets/apps/studio/styles/gui_studio.css`
  - `/assets/apps/studio/styles/style-compliance.css`
- Removed inline CSS ownership bypass path.
- Added architecture gate `scripts/architecture/check_style_compliance_css_ownership.sh` and wired it into aggregate runner order.

## 3) Shared Primitive Reuse Implemented

- Style Compliance action links now consume `gs-tool-action-link` in addition to domain classes:
  - `_result_sections.php`
  - `_result_action_summary.php`
  - `_result_decision_backlog.php`
- Kept local `sc-link-secondary` only for domain tone variation (not geometry/spacing duplication).
- Promoted repeated generic framing primitives to shared Studio CSS (`apps/Studio/styles/gui_studio.css`):
  - `gs-tool-metric-grid`
  - `gs-tool-metric-card`
  - `gs-tool-action-card`
- Style Compliance now consumes these in `_result_sections.php`, while keeping `sc-dash-*` and `sc-action-*` classes for domain semantic accents.

## 4) Remaining Owner-Specific CSS (Intentional)

Intentional local constants and visuals retained in owner CSS:

- scan-ring gradients, orbit animation, aura dots, mission-card glow
- scan-state visuals (`scanning`, `complete`, `failed`)
- cockpit-specific metric tinting and icon chroma

These remain local because they encode Style Compliance domain identity rather than reusable generic UI.

## 5) Token Compliance Findings

### Replaced / normalized in this pass

- Repeated cockpit literals are now owner-local constants in `.sc-container`:
  - `--sc-cockpit-bg`
  - `--sc-aura-primary`
  - `--sc-aura-success`
  - `--sc-aura-warning`
  - `--sc-aura-danger`
  - `--sc-aura-info`
  - `--sc-aura-focus`
  - `--sc-aura-action`
  - `--sc-on-action`

### Remaining literal classes by classification

- Platform token usage: majority of structural values and semantic states.
- Acceptable local constants: scan aura/ring gradients and domain animation colors.
- Should become token (future, optional): repeated radii (`6px`, `8px`, `10px`) and some spacing literals where shared primitives are later generalized.
- Should migrate to Foundation/Studio (future): generic collapsible panel shell and diagnostics card framing if reused by 2+ tools.

## 6) Duplicate Rendering Detection

Current duplicate categories in owner CSS:

- panel frame pattern (`border + radius + subtle bg + shadow`) appears across multiple `sc-*` blocks
- details/summary collapsible pattern repeated
- stat-chip pattern repeated

Disposition:

- Existing shared primitive available: action links and scroll tables (adopted in this pass).
- Candidate Foundation migration: generic collapsible panel frame, generic metric-chip row.
- Candidate Studio/Shell migration: diagnostics wrapper shell and workspace tab strip shell.
- Intentionally local: scan orbit/aura visuals and cockpit mission card treatment.

## 7) Primitive Consumption Audit

Confirmed consumption:

- `gs-tool-page`
- `gs-tool-header`
- `gs-tool-header-actions`
- `gs-tool-status-bar`
- `gs-tool-scroll-table`
- `gs-tool-scope-strip`
- `gs-tool-action-link`

Still local by design:

- workspace tabs (`sc-workspace-tabs`)
- collapsible section shell (`sc-section-collapsible`)
- diagnostics shell (`sc-diagnostics-wrap`)

## 8) Scanner Rule Improvement Notes

No scanner logic changes were applied in this pass to honor no-semantics/no-backend-change constraints.

Existing read-only diagnostic coverage already present and preserved:

- foundation concerns diagnostics in Style Compliance scanner output
- evidence/proposal diagnostics surfaced in `_result_diagnostics.php`

## 9) Cross-Tool Consistency (Candidate Migrations Only)

Candidate future shared primitive opportunities (no migration done in this pass):

1. Label Designer diagnostics panels and cards (`apps/Studio/Tools/LabelDesigner/Views/preview.php`) can reuse a shared diagnostics-shell primitive.
2. Report Designer diagnostics `<details>` blocks (`apps/Studio/Tools/ReportDesigner/Views/index.php`) can reuse shared collapsible-summary treatment.
3. Style Compliance workspace tabs/collapsible wrappers can become reusable Studio tool shells once two or more tools adopt the same structure.

## 10) Governance Enforcement Added

- New gate: `scripts/architecture/check_style_compliance_css_ownership.sh`
- Gate invariants enforce:
  - no inline `<style>` in Style Compliance header
  - mandatory external owner stylesheet linkage
  - no redefinition of core `gs-tool-*` primitives in owner CSS
  - no owner-level global `[hidden]` override
  - required `gs-tool-action-link` consumption on SC next-action CTAs

## 11) Violation Taxonomy

The advisor emits the following finding types:

- `inline_css`
- `generic_layout_in_tool`
- `shared_primitive_duplicate`
- `hardcoded_theme_value`
- `hardcoded_spacing_value`
- `hardcoded_motion_value`
- `improper_owner_file`
- `missing_shared_primitive`
- `acceptable_domain_style`
- `needs_review`

Ownership layer vocabulary used by findings:

- `Foundation`
- `Studio/Shell`
- `Theme System`
- `Style Compliance Owner`
- `Unknown / Needs Review`

## 12) Allowlist Rules

Local selector allowlist is intentionally narrow and reasoned:

- `.sc-scan-*` -> Style Compliance Owner
  - Reason: scan identity and cockpit telemetry visuals
  - Review note: quarterly review for potential primitive extraction
- `.sc-readiness-*` -> Style Compliance Owner
  - Reason: guarded repair/readiness semantics
  - Review note: revisit when repair UX contract changes
- `.sc-investigation-*` -> Style Compliance Owner
  - Reason: investigation domain visualization
  - Review note: revisit during investigation IA revisions
- `.sc-repair-*` -> Style Compliance Owner
  - Reason: repair workflow semantics
  - Review note: revisit when executor behavior contract changes
- `.sc-finding-*` -> Style Compliance Owner
  - Reason: finding visualization semantics
  - Review note: revisit if shared finding primitive appears

Related domain-prefixed selectors can still be accepted as domain styles during review mode, but the allowlist is intentionally not broad wildcard suppression.

## 13) Fail vs Advisory Policy

Hard-fail findings (`severity=severe`) fail the gate:

- `inline_css`
- `improper_owner_file`
- `missing_shared_primitive`
- `shared_primitive_duplicate`
- `generic_layout_in_tool` when it is a hard contract break (for example global `[hidden]` override)
- `hardcoded_theme_value` when detected in generic selector context that should consume Theme System tokens

## 14) Theme-Compliant Color Governance

Color governance is strict for `apps/Studio/styles/style-compliance.css`.

Allowed sources:

- token-backed aliases in `.sc-container` such as `--sc-* : var(--style-*)`, `var(--color-*)`, `var(--text)`, `var(--muted)`, `var(--accent)`, `var(--bg)`, `var(--card)`
- token-backed `color-mix(...)` expressions where all color inputs are token vars
- `transparent` and `currentColor` where compositing/inheritance semantics are intended

Forbidden sources:

- hex literals (`#rgb`, `#rrggbb`, `#rrggbbaa`) in semantic declarations
- `rgb(...)`, `rgba(...)`, `hsl(...)`, `hsla(...)` in semantic declarations
- hardcoded color literals in `.sc-container` alias declarations (`--sc-*`)

Gate behavior:

- severe (hard fail): any hardcoded literal color in semantic declarations or `--sc-*` alias values
- advisory: token-backed `color-mix(...)`, `transparent`, and `currentColor`
- accepted: `--sc-*` aliases mapped directly to approved theme/system tokens

Alias mapping expectation:

- local aliases are permitted only as a readability layer over approved token families
- aliases must never become a second hardcoded palette
- status semantics must map through existing token families (`--color-success-*`, `--color-warning-*`, `--color-danger-*`) instead of custom literal values

Advisory findings do not fail by default:

- `generic_layout_in_tool` (migration suggestion)
- `hardcoded_spacing_value`
- `hardcoded_motion_value`
- `needs_review`

Accepted domain findings are informational and optionally hidden in concise mode:

- `acceptable_domain_style`

## 13.1) Needs-Review Decisions (Pass 2)

The previous 7 `needs_review` findings were manually triaged and resolved as follows:

| Finding | Decision | Rationale | Outcome |
|---|---|---|---|
| `line 224 (@media breakpoint)` | Mark as advisory debt | Breakpoint is known migration debt pending shared breakpoint contract; not a boundary violation | `review -> advisory` |
| `line 228 (@media breakpoint)` | Mark as advisory debt | Same as above | `review -> advisory` |
| `line 428 (@media breakpoint)` | Mark as advisory debt | Same as above | `review -> advisory` |
| `line 432 (@media breakpoint)` | Mark as advisory debt | Same as above | `review -> advisory` |
| `.sc-scan-stage .sc-cp-monitor` z-index | Keep local as domain style | Scan cockpit layering is domain-specific and tied to orbit/monitor composition | `review -> accepted` |
| `.sc-scan-orbit` z-index | Keep local as domain style | Orbit layering is part of Style Compliance scan identity | `review -> accepted` |
| `.sc-scan-orbit-inner` z-index | Keep local as domain style | Inner-ring stacking belongs to scan identity composition | `review -> accepted` |

Decision rule implemented in advisor:

- `@media ... px` findings are advisory migration debt (not needs-review blockers).
- `z-index` in allowlisted scan-domain selectors is accepted as owner-local scan composition.
- non-allowlisted `z-index` findings remain `needs_review`.

This keeps hard boundary enforcement strict while reducing triage noise for known, intentional domain composition.

## 14) Output Modes

### Default (concise)

- shows severe findings and a bounded set of advisories
- hides `acceptable_domain_style` entries unless useful for triage

### Verbose

`bash scripts/architecture/check_style_compliance_css_ownership.sh --verbose`

- emits full advisory stream including accepted domain styles

### JSON

`bash scripts/architecture/check_style_compliance_css_ownership.sh --json`

- emits machine-readable findings array with summary counters
- each finding includes:
  - `type`
  - `selector` (or file/line target)
  - `current_owner`
  - `suggested_owner`
  - `reason`
  - `recommended_action`
  - `confidence`
  - `severity`

## 15) Future Agent Guidance

When this gate reports findings:

- treat `severity=severe` as ownership blockers
- treat advisory findings as migration candidates, not immediate runtime changes
- do not change scanner semantics, repair behavior, async scan APIs, or guarded-apply behavior as part of ownership cleanup
- prefer token/primitive consumption when obvious and existing; otherwise classify as `needs_review`

## Final Assessment

Style Compliance now conforms to the intended CSS ownership architecture at governance baseline:

- generic primitive ownership is explicit and consumed from shared Studio CSS
- domain visuals remain isolated in owner-prefixed CSS
- inline style bypass is removed
- architecture gate prevents regression of these boundaries
- advisor triage now distinguishes true boundary blockers from intentional scan-domain layering and known breakpoint migration debt
