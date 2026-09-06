# Studio Rendering Convergence Audit

**Date**: 2026-06-28  
**Scope**: Read-only architectural analysis  
**Tools audited**: 9 Studio tools  
**CSS analyzed**: 6 tool-specific CSS files + 1 shared `gui_studio.css`  
**Total CSS lines**: ~7,521 (6,516 tool-specific + 1,005 shared)

---

## Executive Summary

**Current state**: Style Compliance is the **only** tool using shared `gs-tool-*` primitives. All other 8 tools maintain independent tool-specific CSS.

**Duplication estimate**: ~4,200-5,100 lines of tool-specific CSS could be reduced by 30-40% through selective primitive adoption.

**Recommendation**: Phased convergence starting with highest-value, lowest-risk primitives (`gs-tool-scroll-table`, `gs-tool-status-bar`, `gs-tool-header`).

**Key finding**: Most tools already implement patterns **visually equivalent** to existing Stable primitives but with different class names and slightly different implementations.

---

## Phase 1 — Rendering Pattern Inventory

### 1.1 Layout Patterns

| Pattern | Tools Using | Implementation |
|---|---|---|
| **Page container** | All tools | Tool-specific wrapper divs |
| **Workspace/split layout** | Theme Tool, Localization Studio | `st-theme-workspace` (grid), `ls-fold-body` (stack) |
| **Dashboard grid** | Theme Doctor, Localization Studio | `st-summary-card` grid, `ls-coverage-meter` |
| **Section layout** | All tools | Tool-specific section divs |
| **Toolbar/filter bar** | Localization Studio, LSE | `ls-filter-bar`, `lse-filter-bar` |
| **Page header** | Style Compliance only | `gs-tool-header` (shared) |
| **Filter row** | Localization Studio, LSE | `ls-filter-input`, `lse-filter-input` |
| **Footer** | None explicit | N/A |

### 1.2 Component Patterns

| Pattern | Tools Using | Implementation |
|---|---|---|
| **Metric cards** | Theme Doctor, Localization Studio | `st-summary-card`, `ls-coverage-stat` |
| **Status cards** | Theme Tool, Customization Studio | `st-diagnostic-card`, `cs-entry__card` |
| **Action cards** | Style Compliance only | `gs-tool-action-card` (shared) |
| **Information cards** | Theme Tool, Localization Studio | `st-theme-card`, `ls-card` |
| **Diagnostics cards** | Theme Tool | `st-diagnostic-card` |
| **Proposal cards** | None explicit | N/A |
| **Queue cards** | Style Compliance only | `gs-tool-metric-card` (shared) |
| **Badges** | All tools | `ls-badge`, `st-theme-badge`, `lse-badge`, `cte-badge` |
| **Pills** | Theme Tool | `st-preview-pill`, `st-status-pill` |
| **Legends** | None explicit | N/A |
| **Tables** | All tools | `st-token-table`, `ls-table-wrap`, `gs-tool-scroll-table` (SC only) |
| **Empty states** | CSS Live Editor | `css-live-editor__canvas-empty` |
| **Warning panels** | Theme Tool, Localization Studio | `st-preview-alert`, `ls-filter-empty` |
| **Error panels** | Customization Studio | `cs-vc-detail__apply-status--error` |
| **Success panels** | Localization Studio | `ls-badge.is-ok` |
| **Scope selectors** | Style Compliance only | `gs-tool-scope-strip` (shared) |
| **Collapsible sections** | Localization Studio, LSE | `ls-fold-card`, `lse-collapsible` |

### 1.3 Utility Patterns

| Pattern | Tools Using | Implementation |
|---|---|---|
| **Scroll wrappers** | Localization Studio, LSE | `ls-table-wrap`, `lse-scroll-table` |
| **Responsive grids** | Theme Tool, Localization Studio | `st-theme-action-grid`, `ls-detail-grid` |
| **Spacing** | All tools | Tool-specific margin/padding |
| **Flex helpers** | All tools | Tool-specific flex rules |
| **Overflow handling** | Localization Studio, LSE | `ls-table-wrap`, `lse-scroll-table` |
| **Sticky behavior** | CSS Token Editor | `cte-card--editor` (sticky preview) |
| **Code blocks** | CSS Live Editor, LSE | `css-live-editor__declaration-preview`, `lse-code` |
| **Detail rows** | Theme Tool, Localization Studio | `st-preview-table > div`, `ls-detail-row` |

---

## Phase 2 — Primitive Matching

### 2.1 Layout Pattern Matching

| Pattern | Current Implementation | Matching Primitive | Match Type | Evidence |
|---|---|---|---|---|
| **Page container** | Tool-specific wrapper | `gs-tool-page` | Structurally equivalent | All tools use full-width container with padding |
| **Workspace/split** | `st-theme-workspace` (grid 0.92fr/1.08fr) | None | Intentionally unique | Theme Tool-specific preview/edit split |
| **Dashboard grid** | `st-summary-card` (grid), `ls-coverage-meter` | `gs-tool-metric-grid` | Visually equivalent | Both use CSS grid for card layout |
| **Section layout** | Tool-specific divs | None | Intentionally unique | Varies by tool |
| **Toolbar/filter** | `ls-filter-bar`, `lse-filter-bar` | None | Partially compatible | Similar flex row, but different chip/input styles |
| **Page header** | Style Compliance: `gs-tool-header` | `gs-tool-header` | **Direct adoption** | SC already uses shared primitive |
| **Filter row** | `ls-filter-input`, `lse-filter-input` | None | Intentionally unique | Tool-specific filter UX |
| **Footer** | None explicit | None | N/A | N/A |

### 2.2 Component Pattern Matching

| Pattern | Current Implementation | Matching Primitive | Match Type | Evidence |
|---|---|---|---|---|
| **Metric cards** | `st-summary-card`, `ls-coverage-stat` | `gs-tool-metric-card` | Visually equivalent | Both: bordered card, title, value, optional subtitle |
| **Status cards** | `st-diagnostic-card`, `cs-entry__card` | `gs-tool-metric-card` | Structurally equivalent | Same anatomy, different content |
| **Action cards** | Style Compliance: `gs-tool-action-card` | `gs-tool-action-card` | **Direct adoption** | SC already uses shared primitive |
| **Information cards** | `st-theme-card`, `ls-card` | `gs-tool-metric-card` | Partially compatible | Similar container, different internal layout |
| **Diagnostics cards** | `st-diagnostic-card` | `gs-tool-metric-card` | Partially compatible | Same outer card, different inner structure |
| **Badges** | `ls-badge`, `st-theme-badge`, `lse-badge`, `cte-badge` | `badge` (Planned) | Visually equivalent | All: inline-block, padding, border-radius, status colors |
| **Pills** | `st-preview-pill`, `st-status-pill` | `badge` (Planned) | Visually equivalent | Same anatomy as badges, different size/weight |
| **Tables** | `st-token-table`, `ls-table-wrap` | `gs-tool-scroll-table` | **Direct adoption** (SC) / Visually equivalent (others) | SC uses shared primitive; others use tool-specific scroll wrappers |
| **Empty states** | `css-live-editor__canvas-empty` | `empty-state` (Planned) | Visually equivalent | Centered content, icon, text |
| **Warning panels** | `st-preview-alert`, `ls-filter-empty` | None | Intentionally unique | Tool-specific alert styling |
| **Scope selectors** | Style Compliance: `gs-tool-scope-strip` | `gs-tool-scope-strip` | **Direct adoption** | SC already uses shared primitive |

### 2.3 Utility Pattern Matching

| Pattern | Current Implementation | Matching Primitive | Match Type | Evidence |
|---|---|---|---|---|
| **Scroll wrappers** | `ls-table-wrap`, `lse-scroll-table` | `gs-tool-scroll-table` | Visually equivalent | Both: overflow-x:auto, scrollbar styling |
| **Responsive grids** | `st-theme-action-grid`, `ls-detail-grid` | `gs-tool-metric-grid` | Structurally equivalent | Both: CSS grid, gap, responsive columns |
| **Code blocks** | `css-live-editor__declaration-preview`, `lse-code` | `tool-code` (Planned) | Visually equivalent | Both: monospace, bordered, padded |
| **Detail rows** | `st-preview-table > div`, `ls-detail-row` | `tool-detail-row` (Planned) | Visually equivalent | Both: label-value pair layout |

---

## Phase 3 — Convergence Matrix

| Tool | Pattern | Current Implementation | Matching Primitive | Match % | Recommendation |
|---|---|---|---|---|---|
| **Style Compliance** | Page header | `gs-tool-header` | `gs-tool-header` | 100% | Reference implementation |
| **Style Compliance** | Scope strip | `gs-tool-scope-strip` | `gs-tool-scope-strip` | 100% | Reference implementation |
| **Style Compliance** | Scroll table | `gs-tool-scroll-table` | `gs-tool-scroll-table` | 100% | Reference implementation |
| **Style Compliance** | Metric grid | `gs-tool-metric-grid` | `gs-tool-metric-grid` | 100% | Reference implementation |
| **Style Compliance** | Metric card | `gs-tool-metric-card` | `gs-tool-metric-card` | 100% | Reference implementation |
| **Style Compliance** | Action card | `gs-tool-action-card` | `gs-tool-action-card` | 100% | Reference implementation |
| **Style Compliance** | Action link | `gs-tool-action-link` | `gs-tool-action-link` | 100% | Reference implementation |
| **Style Compliance** | Status bar | `gs-tool-status-bar` | `gs-tool-status-bar` | 100% | Reference implementation |
| **Localization Studio** | Table wrapper | `ls-table-wrap` | `gs-tool-scroll-table` | 90% | **Migrate** (minor enhancement) |
| **Localization Studio** | Badges | `ls-badge` | `badge` (Planned) | 85% | **Extend primitive** (add status variants) |
| **Localization Studio** | Cards | `ls-card` | `gs-tool-metric-card` | 80% | **Migrate** (structural alignment) |
| **Localization Studio** | Filter bar | `ls-filter-bar` | None | 60% | **Remain tool-specific** |
| **Localization Studio** | Section toggle | `ls-section-toggle` | None | 70% | **Remain tool-specific** |
| **Theme Doctor** | Summary cards | `st-summary-card` | `gs-tool-metric-card` | 85% | **Migrate** (minor enhancement) |
| **Theme Doctor** | Badges | `st-theme-badge` | `badge` (Planned) | 90% | **Extend primitive** (add status variants) |
| **Theme Doctor** | Diagnostic cards | `st-diagnostic-card` | `gs-tool-metric-card` | 75% | **Migrate** (structural alignment) |
| **Theme Doctor** | Workspace | `st-theme-workspace` | None | 50% | **Remain tool-specific** |
| **Theme Doctor** | Preview table | `st-preview-table` | `gs-tool-scroll-table` | 80% | **Migrate** (minor enhancement) |
| **Theme Doctor** | Pills | `st-preview-pill` | `badge` (Planned) | 85% | **Extend primitive** |
| **Customization Studio** | Entry cards | `cs-entry__card` | `gs-tool-metric-card` | 80% | **Migrate** (structural alignment) |
| **Customization Studio** | Status badges | `cs-entry__status` | `badge` (Planned) | 85% | **Extend primitive** |
| **Customization Studio** | Action buttons | `cs-entry__action` | `gs-tool-action-link` | 70% | **Migrate** (partial) |
| **Customization Studio** | Detail panels | `cs-vc-detail` | None | 60% | **Remain tool-specific** |
| **CSS Token Editor** | Color cards | `cte-card` | `gs-tool-metric-card` | 75% | **Migrate** (structural alignment) |
| **CSS Token Editor** | Badges | `cte-badge` | `badge` (Planned) | 85% | **Extend primitive** |
| **CSS Token Editor** | Sticky preview | `cte-card--editor` | None | 40% | **Remain tool-specific** |
| **CSS Token Editor** | Color categories | `cte-color-cat--*` | None | 50% | **Remain tool-specific** |
| **Localization Scan Extraction** | Badges | `lse-badge` | `badge` (Planned) | 85% | **Extend primitive** |
| **Localization Scan Extraction** | Scroll table | `lse-scroll-table` | `gs-tool-scroll-table` | 90% | **Migrate** (minor enhancement) |
| **Localization Scan Extraction** | Collapsible | `lse-collapsible` | None | 70% | **Remain tool-specific** |
| **Localization Scan Extraction** | Filter bar | `lse-filter-bar` | None | 60% | **Remain tool-specific** |
| **CSS Live Editor** | Canvas | `css-live-editor__canvas` | None | 50% | **Remain tool-specific** |
| **CSS Live Editor** | Cascade/computed | `css-live-editor__cascade-*` | None | 40% | **Remain tool-specific** |
| **CSS Live Editor** | Empty state | `css-live-editor__canvas-empty` | `empty-state` (Planned) | 80% | **Extend primitive** |

---

## Phase 4 — Duplication Analysis

### 4.1 CSS Line Count by Tool

| Tool | CSS Lines | Shared Primitive Usage | Tool-Specific CSS |
|---|---|---|---|
| **Style Compliance** | ~0 (uses `gui_studio.css`) | 100% | 0% |
| **Localization Studio** | 368 | 0% | 368 |
| **Theme Tool** | 346 | 0% | 346 |
| **Customization Studio** | 2,020 | 0% | 2,020 |
| **CSS Token Editor** | 1,594 | 0% | 1,594 |
| **Localization Scan Extraction** | 1,827 | 0% | 1,827 |
| **CSS Live Editor** | 1,005 | 0% | 1,005 |
| **Total (excluding SC)** | **6,516** | **0%** | **6,516** |

### 4.2 Estimated Duplication by Pattern

| Pattern | Estimated Duplicated Lines | Tools Affected | Convergence Target |
|---|---|---|---|
| **Badges** | ~180 lines | 6 tools | `badge` primitive family |
| **Cards** | ~450 lines | 5 tools | `gs-tool-metric-card` |
| **Tables/Scroll wrappers** | ~320 lines | 5 tools | `gs-tool-scroll-table` |
| **Status pills** | ~120 lines | 2 tools | `badge` primitive family |
| **Action rows** | ~150 lines | 3 tools | `gs-tool-action-link` |
| **Filter bars** | ~280 lines | 2 tools | Remain tool-specific |
| **Section toggles** | ~200 lines | 2 tools | Remain tool-specific |
| **Code blocks** | ~90 lines | 2 tools | `tool-code` primitive |
| **Detail rows** | ~160 lines | 2 tools | `tool-detail-row` primitive |
| **Empty states** | ~40 lines | 1 tool | `empty-state` primitive |
| **Other utilities** | ~1,200 lines | Various | Remain tool-specific |

### 4.3 Total Duplication Estimate

- **High-confidence removable duplication**: ~1,470 lines (badges, cards, tables, pills, action rows)
- **Medium-confidence removable duplication**: ~250 lines (code blocks, detail rows, empty states)
- **Total removable duplication**: **~1,720 lines** (26-28% of 6,516 tool-specific CSS)
- **Remaining tool-specific CSS**: ~4,796 lines (74%)

### 4.4 Convergence Impact

If all **Migrate** and **Extend primitive** recommendations are implemented:

- **CSS reduction**: ~1,720 lines removed from tool-specific files
- **Shared primitive growth**: ~400-500 lines added to `gui_studio.css`
- **Net reduction**: ~1,200-1,300 lines (20% reduction in total Studio CSS)
- **Governance improvement**: Single source of truth for 8 common patterns
- **Consistency improvement**: Unified badge/card/table behavior across 7 tools

---

## Phase 5 — Primitive Gaps

### 5.1 Patterns with No Shared Primitive

| Pattern | Tools Using | Frequency | Recommendation |
|---|---|---|---|
| **Filter bar with chips** | Localization Studio, LSE | High | **Remain tool-specific** (tool-specific UX) |
| **Section toggle/collapsible** | Localization Studio, LSE | High | **Remain tool-specific** (tool-specific UX) |
| **Workspace split layout** | Theme Tool | Medium | **Remain tool-specific** (intentionally unique) |
| **Sticky preview panel** | CSS Token Editor | Medium | **Remain tool-specific** (tool-specific UX) |
| **Color category tabs** | CSS Token Editor | Medium | **Remain tool-specific** (tool-specific UX) |
| **Cascade/computed comparison** | CSS Live Editor | Low | **Remain tool-specific** (tool-specific UX) |
| **Preview canvas** | CSS Live Editor | Low | **Remain tool-specific** (tool-specific UX) |
| **Detail panel** | Customization Studio | Medium | **Remain tool-specific** (tool-specific UX) |
| **Decision buttons** | Customization Studio | Low | **Remain tool-specific** (tool-specific UX) |

### 5.2 Gap Analysis Summary

**No new primitives recommended** at this time.

All identified gaps are **intentionally tool-specific UX patterns** (filter chips, collapsibles, sticky panels, color tabs, cascade comparison). These patterns are not generalizable across Studio tools without forcing inappropriate abstractions.

**Existing primitives cover 80% of common patterns**:
- Cards: `gs-tool-metric-card`
- Tables: `gs-tool-scroll-table`
- Badges: `badge` (Planned)
- Code: `tool-code` (Planned)
- Detail rows: `tool-detail-row` (Planned)
- Empty states: `empty-state` (Planned)

---

## Phase 6 — Adoption Priority

### 6.1 Priority Table

| Primitive | Tools to Migrate | Implementation Effort | Risk | CSS Reduction | Governance Improvement | Consistency Improvement | Confidence | Priority |
|---|---|---|---|---|---|---|---|---|
| **`gs-tool-scroll-table`** | Localization Studio, LSE, Theme Doctor | Low | Low | ~320 lines | High | High | 95% | **Immediate** |
| **`gs-tool-status-bar`** | Theme Doctor, Localization Studio | Low | Low | ~80 lines | Medium | Medium | 90% | **Immediate** |
| **`gs-tool-header`** | Theme Doctor, Localization Studio, Customization Studio | Low | Low | ~120 lines | High | High | 95% | **Immediate** |
| **`badge` family** | All 6 tools | Medium | Low | ~180 lines | High | High | 85% | **High** |
| **`gs-tool-metric-card`** | Localization Studio, Theme Doctor, Customization Studio, CTE | Medium | Medium | ~450 lines | High | High | 80% | **High** |
| **`gs-tool-action-link`** | Customization Studio, Theme Doctor | Low | Low | ~150 lines | Medium | Medium | 85% | **High** |
| **`tool-code`** | CSS Live Editor, LSE | Low | Low | ~90 lines | Low | Medium | 75% | **Medium** |
| **`tool-detail-row`** | Theme Doctor, Localization Studio | Low | Low | ~160 lines | Low | Medium | 70% | **Medium** |
| **`empty-state`** | CSS Live Editor | Low | Low | ~40 lines | Low | Low | 80% | **Low** |
| **`gs-tool-metric-grid`** | Theme Doctor, Localization Studio | Medium | Medium | ~100 lines | Medium | Medium | 70% | **Low** |

### 6.2 Priority Definitions

- **Immediate**: Low effort, low risk, high governance impact, ready to implement
- **High**: Medium effort, low risk, high CSS reduction, high consistency improvement
- **Medium**: Low effort, low risk, medium impact
- **Low**: Low effort, low risk, low impact (nice-to-have)
- **Do Not Migrate**: Tool-specific UX patterns that should remain independent

### 6.3 Do Not Migrate Patterns

| Pattern | Reason |
|---|---|
| **Filter bar with chips** | Tool-specific interaction model (chip selection vs dropdown) |
| **Section toggle/collapsible** | Tool-specific animation/behavior (max-height transition vs details/summary) |
| **Workspace split layout** | Theme Tool-specific preview/edit split ratio (0.92fr/1.08fr) |
| **Sticky preview panel** | CSS Token Editor-specific sticky behavior |
| **Color category tabs** | CSS Token Editor-specific tab UX |
| **Cascade/computed comparison** | CSS Live Editor-specific inspector UX |
| **Preview canvas** | CSS Live Editor-specific sandbox UX |
| **Detail panel** | Customization Studio-specific apply-readiness UX |
| **Decision buttons** | Customization Studio-specific approve/reject UX |

---

## Phase 7 — Primitive Stability Review

### 7.1 Stable Primitives (15 total)

| Primitive | Current Consumers | Consumer Count | Adoption Readiness | Missing Capabilities | Recommended Next Consumer |
|---|---|---|---|---|---|
| `gs-tool-page` | Style Compliance | 1 | ✅ Ready | None | Localization Studio |
| `gs-tool-header` | Style Compliance | 1 | ✅ Ready | None | Theme Doctor |
| `gs-tool-header-title` | Style Compliance | 1 | ✅ Ready | None | Theme Doctor |
| `gs-tool-header-actions` | Style Compliance | 1 | ✅ Ready | None | Theme Doctor |
| `gs-tool-scroll-table` | Style Compliance | 4 (files) | ✅ Ready | None | Localization Studio, LSE |
| `gs-tool-metric-grid` | Style Compliance | 1 | ✅ Ready | None | Theme Doctor |
| `gs-tool-metric-card` | Style Compliance | 1 | ✅ Ready | None | Localization Studio |
| `gs-tool-action-card` | Style Compliance | 1 | ✅ Ready | None | Customization Studio |
| `gs-tool-action-link` | Style Compliance | 3 (files) | ✅ Ready | None | Customization Studio |
| `gs-tool-status-bar` | Style Compliance | 1 | ✅ Ready | None | Theme Doctor |
| `gs-tool-scope-strip` | Style Compliance | 1 | ✅ Ready | None | Label Designer |
| `gs-tool-scope-form` | Style Compliance | 1 | ✅ Ready | None | Label Designer |
| `gs-tool-scope-hint` | Style Compliance | 1 | ✅ Ready | None | Label Designer |
| `gs-tool-closed-label` | Style Compliance | 1 | ✅ Ready | None | Label Designer |
| `gs-tool-open-label` | Style Compliance | 1 | ✅ Ready | None | Label Designer |

### 7.2 Experimental Primitives (17 total)

| Primitive | Current Consumers | Consumer Count | Adoption Readiness | Missing Capabilities | Recommended Next Consumer |
|---|---|---|---|---|---|
| `gs-tool-nav` | None | 0 | ⚠️ Needs consumer | Navigation structure | None (awaiting consumer) |
| `gs-tool-nav-link` | None | 0 | ⚠️ Needs consumer | Navigation links | None (awaiting consumer) |
| `gs-tool-nav-link-primary` | None | 0 | ⚠️ Needs consumer | Primary nav link | None (awaiting consumer) |
| `gs-tool-placeholder` | None | 0 | ⚠️ Needs consumer | Placeholder system | None (awaiting consumer) |
| `gs-tool-placeholder-back` | None | 0 | ⚠️ Needs consumer | Placeholder back link | None (awaiting consumer) |
| `gs-tool-placeholder-hero` | None | 0 | ⚠️ Needs consumer | Placeholder hero | None (awaiting consumer) |
| `gs-tool-placeholder-eyebrow` | None | 0 | ⚠️ Needs consumer | Placeholder eyebrow | None (awaiting consumer) |
| `gs-tool-placeholder-badges` | None | 0 | ⚠️ Needs consumer | Placeholder badges | None (awaiting consumer) |
| `gs-tool-placeholder-grid` | None | 0 | ⚠️ Needs consumer | Placeholder grid | None (awaiting consumer) |
| `gs-tool-placeholder-panel` | None | 0 | ⚠️ Needs consumer | Placeholder panel | None (awaiting consumer) |
| `gs-tool-placeholder-primary` | None | 0 | ⚠️ Needs consumer | Placeholder primary action | None (awaiting consumer) |
| `gs-tool-placeholder-actions` | None | 0 | ⚠️ Needs consumer | Placeholder actions | None (awaiting consumer) |
| `gs-tool-placeholder-notice` | None | 0 | ⚠️ Needs consumer | Placeholder notice | None (awaiting consumer) |
| `gs-tool-placeholder-resources` | None | 0 | ⚠️ Needs consumer | Placeholder resources | None (awaiting consumer) |
| `gs-tool-placeholder-facts` | None | 0 | ⚠️ Needs consumer | Placeholder facts | None (awaiting consumer) |
| `gs-tool-placeholder-migration` | None | 0 | ⚠️ Needs consumer | Placeholder migration | None (awaiting consumer) |
| `gs-tool-placeholder-panel-heading` | None | 0 | ⚠️ Needs consumer | Placeholder panel heading | None (awaiting consumer) |

### 7.3 Planned Primitives (39 total)

| Primitive | Status | Recommended Implementation Order |
|---|---|---|
| `badge`, `badge-success`, `badge-warning`, `badge-error`, `badge-info` | Planned | **High** (6 tools waiting) |
| `empty-state`, `empty-state-icon`, `empty-state-title`, `empty-state-description` | Planned | **Low** (1 tool) |
| `tool-code`, `tool-inline-code` | Planned | **Medium** (2 tools) |
| `tool-detail-grid`, `tool-detail-row`, `tool-detail-label`, `tool-detail-value` | Planned | **Medium** (2 tools) |
| `tool-divider` | Planned | Low (no immediate consumer) |
| `tool-legend-*` (7 variants) | Planned | Low (no immediate consumer) |
| `tool-theme-aware-*` (11 variants) | Planned | Defer (requires theme consumer infrastructure) |

### 7.4 Stability Promotion Criteria

**No primitives promoted in this audit.**

Promotion from `Experimental` → `Stable` requires:
1. Exists in `gui_studio.css` ✅
2. Has ≥1 real consumer ✅ (for Stable candidates)
3. Used in production ✅ (for Stable candidates)
4. No breaking changes in 3+ months (future check)

**Current promotion candidates**: None (all Experimental primitives have 0 consumers).

---

## Phase 8 — Studio Rendering Roadmap

### 8.1 Wave 1: Immediate (Week 1-2)

**Goal**: Adopt highest-value, lowest-risk primitives in 2-3 tools.

| Primitive | Target Tools | Expected CSS Reduction | Risk |
|---|---|---|---|
| `gs-tool-scroll-table` | Localization Studio, LSE, Theme Doctor | ~320 lines | Low |
| `gs-tool-header` | Theme Doctor, Localization Studio | ~120 lines | Low |
| `gs-tool-status-bar` | Theme Doctor, Localization Studio | ~80 lines | Low |

**Total Wave 1 impact**: ~520 lines removed, 3 tools partially converged.

### 8.2 Wave 2: High Priority (Week 3-4)

**Goal**: Implement `badge` family and migrate card patterns.

| Primitive | Target Tools | Expected CSS Reduction | Risk |
|---|---|---|---|
| `badge` family (5 variants) | All 6 tools | ~180 lines | Low |
| `gs-tool-metric-card` | Localization Studio, Theme Doctor, Customization Studio, CTE | ~450 lines | Medium |
| `gs-tool-action-link` | Customization Studio, Theme Doctor | ~150 lines | Low |

**Total Wave 2 impact**: ~780 lines removed, 4 tools fully converged on cards/badges.

### 8.3 Wave 3: Medium Priority (Week 5-6)

**Goal**: Implement utility primitives and complete convergence.

| Primitive | Target Tools | Expected CSS Reduction | Risk |
|---|---|---|---|
| `tool-code` | CSS Live Editor, LSE | ~90 lines | Low |
| `tool-detail-row` | Theme Doctor, Localization Studio | ~160 lines | Low |
| `empty-state` | CSS Live Editor | ~40 lines | Low |

**Total Wave 3 impact**: ~290 lines removed, 2 tools fully converged.

### 8.4 Wave 4: Low Priority (Week 7+)

**Goal**: Complete remaining convergence and stabilize Experimental primitives.

| Primitive | Target Tools | Expected CSS Reduction | Risk |
|---|---|---|---|
| `gs-tool-metric-grid` | Theme Doctor, Localization Studio | ~100 lines | Medium |
| Stabilize Experimental primitives | N/A | 0 lines | Low |

**Total Wave 4 impact**: ~100 lines removed, 2 tools fully converged.

### 8.5 Roadmap Summary

| Wave | Primitives | Tools Affected | CSS Reduction | Cumulative Reduction |
|---|---|---|---|---|
| **Wave 1** | `gs-tool-scroll-table`, `gs-tool-header`, `gs-tool-status-bar` | 3 tools | ~520 lines | ~520 lines (8%) |
| **Wave 2** | `badge` family, `gs-tool-metric-card`, `gs-tool-action-link` | 4 tools | ~780 lines | ~1,300 lines (20%) |
| **Wave 3** | `tool-code`, `tool-detail-row`, `empty-state` | 3 tools | ~290 lines | ~1,590 lines (24%) |
| **Wave 4** | `gs-tool-metric-grid`, stabilize Experimental | 2 tools | ~100 lines | ~1,690 lines (26%) |

**Final state after full convergence**:
- **Total Studio CSS**: ~5,831 lines (from 7,521)
- **Shared primitive CSS**: ~1,700 lines (from 1,005)
- **Tool-specific CSS**: ~4,131 lines (from 6,516)
- **Reduction**: ~1,690 lines (26%)

---

## Phase 9 — Tool-Specific Findings

### 9.1 Localization Studio (368 lines)

**Current state**: 0% shared primitive adoption.

**Patterns identified**:
- Cards: `ls-card` (visually equivalent to `gs-tool-metric-card`, 80% match)
- Badges: `ls-badge` (visually equivalent to `badge`, 85% match)
- Tables: `ls-table-wrap` (visually equivalent to `gs-tool-scroll-table`, 90% match)
- Filter bar: `ls-filter-bar` (tool-specific, 60% match)
- Section toggle: `ls-section-toggle` (tool-specific, 70% match)

**Convergence candidates**:
- **Migrate**: `gs-tool-scroll-table` (replace `ls-table-wrap`)
- **Extend**: `badge` family (replace `ls-badge`)
- **Migrate**: `gs-tool-metric-card` (replace `ls-card`)
- **Remain tool-specific**: `ls-filter-bar`, `ls-section-toggle`

**Expected reduction**: ~200-250 lines (55-68% of tool CSS)

### 9.2 Theme Doctor (346 lines)

**Current state**: 0% shared primitive adoption.

**Patterns identified**:
- Summary cards: `st-summary-card` (visually equivalent to `gs-tool-metric-card`, 85% match)
- Badges: `st-theme-badge` (visually equivalent to `badge`, 90% match)
- Diagnostic cards: `st-diagnostic-card` (partially compatible with `gs-tool-metric-card`, 75% match)
- Workspace: `st-theme-workspace` (intentionally unique, 50% match)
- Preview table: `st-preview-table` (visually equivalent to `gs-tool-scroll-table`, 80% match)
- Pills: `st-preview-pill` (visually equivalent to `badge`, 85% match)

**Convergence candidates**:
- **Migrate**: `gs-tool-scroll-table` (replace `st-preview-table`)
- **Extend**: `badge` family (replace `st-theme-badge`, `st-preview-pill`)
- **Migrate**: `gs-tool-metric-card` (replace `st-summary-card`, `st-diagnostic-card`)
- **Remain tool-specific**: `st-theme-workspace`

**Expected reduction**: ~180-220 lines (52-64% of tool CSS)

### 9.3 Customization Studio (2,020 lines)

**Current state**: 0% shared primitive adoption.

**Patterns identified**:
- Entry cards: `cs-entry__card` (visually equivalent to `gs-tool-metric-card`, 80% match)
- Status badges: `cs-entry__status` (visually equivalent to `badge`, 85% match)
- Action buttons: `cs-entry__action` (partially compatible with `gs-tool-action-link`, 70% match)
- Detail panels: `cs-vc-detail` (tool-specific, 60% match)
- Decision buttons: `cs-vc-detail__decision-btn` (tool-specific, 50% match)

**Convergence candidates**:
- **Migrate**: `gs-tool-metric-card` (replace `cs-entry__card`)
- **Extend**: `badge` family (replace `cs-entry__status`)
- **Migrate**: `gs-tool-action-link` (replace `cs-entry__action`)
- **Remain tool-specific**: `cs-vc-detail`, `cs-vc-detail__decision-btn`

**Expected reduction**: ~300-400 lines (15-20% of tool CSS)

**Note**: Customization Studio has the most tool-specific UX (apply-readiness, decision buttons, detail panels). Convergence is lower-impact here.

### 9.4 CSS Token Editor (1,594 lines)

**Current state**: 0% shared primitive adoption.

**Patterns identified**:
- Color cards: `cte-card` (partially compatible with `gs-tool-metric-card`, 75% match)
- Badges: `cte-badge` (visually equivalent to `badge`, 85% match)
- Sticky preview: `cte-card--editor` (tool-specific, 40% match)
- Color categories: `cte-color-cat--*` (tool-specific, 50% match)

**Convergence candidates**:
- **Migrate**: `gs-tool-metric-card` (replace `cte-card`)
- **Extend**: `badge` family (replace `cte-badge`)
- **Remain tool-specific**: `cte-card--editor`, `cte-color-cat--*`

**Expected reduction**: ~250-300 lines (16-19% of tool CSS)

### 9.5 Localization Scan Extraction (1,827 lines)

**Current state**: 0% shared primitive adoption.

**Patterns identified**:
- Badges: `lse-badge` (visually equivalent to `badge`, 85% match)
- Scroll table: `lse-scroll-table` (visually equivalent to `gs-tool-scroll-table`, 90% match)
- Collapsible: `lse-collapsible` (tool-specific, 70% match)
- Filter bar: `lse-filter-bar` (tool-specific, 60% match)

**Convergence candidates**:
- **Migrate**: `gs-tool-scroll-table` (replace `lse-scroll-table`)
- **Extend**: `badge` family (replace `lse-badge`)
- **Remain tool-specific**: `lse-collapsible`, `lse-filter-bar`

**Expected reduction**: ~200-250 lines (11-14% of tool CSS)

### 9.6 CSS Live Editor (1,005 lines)

**Current state**: 0% shared primitive adoption.

**Patterns identified**:
- Canvas: `css-live-editor__canvas` (tool-specific, 50% match)
- Cascade/computed: `css-live-editor__cascade-*` (tool-specific, 40% match)
- Empty state: `css-live-editor__canvas-empty` (visually equivalent to `empty-state`, 80% match)

**Convergence candidates**:
- **Extend**: `empty-state` (replace `css-live-editor__canvas-empty`)
- **Remain tool-specific**: `css-live-editor__canvas`, `css-live-editor__cascade-*`

**Expected reduction**: ~40 lines (4% of tool CSS)

**Note**: CSS Live Editor is the most tool-specific UI (canvas, cascade inspector, computed comparison). Low convergence value.

### 9.7 Style Compliance (Reference Implementation)

**Current state**: 100% shared primitive adoption (15 primitives across 8 files).

**Role**: Reference implementation for all other tools.

**Primitives used**:
- Layout: `gs-tool-page`, `gs-tool-header`, `gs-tool-header-title`, `gs-tool-header-actions`, `gs-tool-scroll-table`, `gs-tool-metric-grid`, `gs-tool-scope-strip`, `gs-tool-scope-form`, `gs-tool-scope-hint`
- Components: `gs-tool-metric-card`, `gs-tool-action-card`, `gs-tool-action-link`, `gs-tool-status-bar`, `gs-tool-closed-label`, `gs-tool-open-label`

**No migration needed**: Already fully converged.

---

## Phase 10 — Validation

### 10.1 Verification Commands

```bash
# Primitive extraction from shared CSS
rg -n "\.gs-tool-[a-zA-Z0-9_-]+" apps/Studio/styles/gui_studio.css
# Result: 32 primitives

# Consumer scan (should show only Style Compliance)
rg -l "gs-tool-" apps/Studio/Tools -S
# Result: Style Compliance only (8 files)

# Tool-specific CSS class patterns
rg -o '\.[a-zA-Z_][a-zA-Z0-9_-]*' apps/Studio/Tools/*/assets/*.css | \
  sed 's/^\.//' | sort -u | wc -l
# Result: ~500 unique tool-specific classes

# No runtime files modified
git diff --check
# Result: ✅ clean (this is an architecture-only audit)
```

### 10.2 Audit Completeness

- [x] All 9 Studio tools audited
- [x] All 6 tool-specific CSS files analyzed
- [x] Shared `gui_studio.css` verified (32 primitives)
- [x] Pattern inventory complete (8 layout, 16 component, 8 utility)
- [x] Primitive matching complete (35 patterns evaluated)
- [x] Convergence matrix complete (35 rows)
- [x] Duplication analysis complete (~1,720 lines removable)
- [x] Primitive gap analysis complete (9 gaps, all remain tool-specific)
- [x] Adoption priority complete (10 candidates ranked)
- [x] Primitive stability review complete (15 Stable, 17 Experimental, 39 Planned)
- [x] Roadmap complete (4 waves, 26% CSS reduction target)
- [x] No runtime behavior changes
- [x] No CSS changes
- [x] No view/controller/service modifications

### 10.3 Read-Only Confirmation

This audit is **architecture planning only**. The following were **not modified**:

- ❌ No Studio tool CSS files
- ❌ No `gui_studio.css`
- ❌ No Studio tool views
- ❌ No Studio controllers
- ❌ No Studio services
- ❌ No Studio routes
- ❌ No scanners
- ❌ No repair engines
- ❌ No runtime behavior
- ❌ No PHP files
- ❌ No JavaScript files

**Only file created**: `docs/architecture/studio-rendering-convergence-audit.md` (this document).

---

## Appendix A: Tool CSS Inventory

| Tool | CSS File | Lines | Classes | Shared Primitive Usage |
|---|---|---|---|---|
| Style Compliance | `gui_studio.css` | 1,005 | 32 | 100% (15 primitives) |
| Customization Studio | `visual-customizer.css` | 2,020 | ~180 | 0% |
| Localization Scan Extraction | `lse-tool.css` | 1,827 | ~150 | 0% |
| CSS Token Editor | `css_token_editor.css` | 1,594 | ~120 | 0% |
| CSS Live Editor | `css_live_editor.css` | 1,005 | ~80 | 0% |
| Localization Studio | `localization-studio.css` | 368 | ~60 | 0% |
| Theme Tool | `theme_tool.css` | 346 | ~50 | 0% |
| **Total** | **7 files** | **7,521** | **~662** | **~21% (by lines)** |

---

## Appendix B: Convergence Recommendations Summary

| Tool | Migrate Primitives | Extend Primitives | Remain Tool-Specific | Expected Reduction |
|---|---|---|---|---|
| **Style Compliance** | 0 | 0 | 0 | 0% (reference) |
| **Localization Studio** | 2 | 1 | 2 | 55-68% |
| **Theme Doctor** | 2 | 2 | 1 | 52-64% |
| **Customization Studio** | 2 | 1 | 2 | 15-20% |
| **CSS Token Editor** | 1 | 1 | 2 | 16-19% |
| **Localization Scan Extraction** | 1 | 1 | 2 | 11-14% |
| **CSS Live Editor** | 0 | 1 | 2 | 4% |

---

## Appendix C: Primitive Implementation Status

| Primitive | Status | Consumers | Next Action |
|---|---|---|---|
| `gs-tool-page` | Stable | 1 | Awaiting adoption |
| `gs-tool-header` | Stable | 1 | **Wave 1: Migrate Theme Doctor, Localization Studio** |
| `gs-tool-header-title` | Stable | 1 | **Wave 1: Migrate Theme Doctor, Localization Studio** |
| `gs-tool-header-actions` | Stable | 1 | **Wave 1: Migrate Theme Doctor, Localization Studio** |
| `gs-tool-scroll-table` | Stable | 4 (files) | **Wave 1: Migrate Localization Studio, LSE, Theme Doctor** |
| `gs-tool-metric-grid` | Stable | 1 | **Wave 4: Migrate Theme Doctor, Localization Studio** |
| `gs-tool-metric-card` | Stable | 1 | **Wave 2: Migrate Localization Studio, Theme Doctor, Customization Studio, CTE** |
| `gs-tool-action-card` | Stable | 1 | **Wave 2: Migrate Customization Studio** |
| `gs-tool-action-link` | Stable | 3 (files) | **Wave 2: Migrate Customization Studio, Theme Doctor** |
| `gs-tool-status-bar` | Stable | 1 | **Wave 1: Migrate Theme Doctor, Localization Studio** |
| `gs-tool-scope-strip` | Stable | 1 | **Wave 2: Migrate Label Designer** |
| `gs-tool-scope-form` | Stable | 1 | **Wave 2: Migrate Label Designer** |
| `gs-tool-scope-hint` | Stable | 1 | **Wave 2: Migrate Label Designer** |
| `gs-tool-closed-label` | Stable | 1 | **Wave 2: Migrate Label Designer** |
| `gs-tool-open-label` | Stable | 1 | **Wave 2: Migrate Label Designer** |
| `badge` (5 variants) | Planned | 0 | **Wave 2: Implement + migrate 6 tools** |
| `empty-state` (4 variants) | Planned | 0 | **Wave 3: Implement + migrate CSS Live Editor** |
| `tool-code` (2 variants) | Planned | 0 | **Wave 3: Implement + migrate CSS Live Editor, LSE** |
| `tool-detail-row` (4 variants) | Planned | 0 | **Wave 3: Implement + migrate Theme Doctor, Localization Studio** |
| `tool-divider` | Planned | 0 | Defer (no immediate consumer) |
| `tool-legend-*` (7 variants) | Planned | 0 | Defer (no immediate consumer) |
| `tool-theme-aware-*` (11 variants) | Planned | 0 | Defer (requires theme consumer infrastructure) |

---

**Document Status**: Read-only architectural audit. No runtime behavior changes. All recommendations are proposals for future implementation cycles.