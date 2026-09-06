# Studio Theme Compliance Framework

## Verification Status

**Last verified**: 2026-06-28  
**CSS source**: `apps/Studio/styles/gui_studio.css`  
**Verification method**: ripgrep selector extraction + consumer scan

---

## 1. Real Primitive Inventory (Verified)

### 1.1 Layout Primitives (6 verified)

| Primitive | CSS Exists | Consumer Count | Current Consumers | Maturity |
|---|---|---|---|---|
| `gs-tool-page` | ✅ | 1 | Style Compliance | Stable |
| `gs-tool-header` | ✅ | 1 | Style Compliance | Stable |
| `gs-tool-scroll-table` | ✅ | 4 | Style Compliance (4 files) | Stable |
| `gs-tool-metric-grid` | ✅ | 1 | Style Compliance | Stable |
| `gs-tool-scope-strip` | ✅ | 1 | Style Compliance | Stable |
| `gs-tool-scope-form` | ✅ | 1 | Style Compliance | Stable |

### 1.2 Component Primitives (10 verified)

| Primitive | CSS Exists | Consumer Count | Current Consumers | Maturity |
|---|---|---|---|---|
| `gs-tool-metric-card` | ✅ | 1 | Style Compliance | Stable |
| `gs-tool-action-card` | ✅ | 1 | Style Compliance | Stable |
| `gs-tool-action-link` | ✅ | 3 | Style Compliance (3 files) | Stable |
| `gs-tool-status-bar` | ✅ | 1 | Style Compliance | Stable |
| `gs-tool-closed-label` | ✅ | 1 | Style Compliance | Stable |
| `gs-tool-open-label` | ✅ | 1 | Style Compliance | Stable |
| `gs-tool-header-title` | ✅ | 1 | Style Compliance | Stable |
| `gs-tool-header-actions` | ✅ | 1 | Style Compliance | Stable |
| `gs-tool-scope-hint` | ✅ | 1 | Style Compliance | Stable |
| `gs-tool-nav` | ✅ | 0 | None (CSS only) | Experimental |

### 1.3 Placeholder Primitives (13 verified, CSS-only)

| Primitive | CSS Exists | Consumer Count | Current Consumers | Maturity |
|---|---|---|---|---|
| `gs-tool-placeholder` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-back` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-hero` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-eyebrow` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-badges` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-grid` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-panel` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-primary` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-actions` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-notice` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-resources` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-facts` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-migration` | ✅ | 0 | None (CSS only) | Experimental |
| `gs-tool-placeholder-panel-heading` | ✅ | 0 | None (CSS only) | Experimental |

### 1.4 Theme Consumer Primitives (0 verified)

**None exist in `gui_studio.css`**. Theme consumer primitives (`gs-tool-theme-aware-*`) are **proposed only** — not implemented.

---

## 2. Documented-vs-Actual Mismatch Table

### 2.1 Primitives in Framework Doc but NOT in CSS (Aspirational)

| Documented Primitive | CSS Exists | Status | Action |
|---|---|---|---|
| `badge` | ❌ | Proposed | Mark as `Planned` |
| `badge-success` | ❌ | Proposed | Mark as `Planned` |
| `badge-warning` | ❌ | Proposed | Mark as `Planned` |
| `badge-error` | ❌ | Proposed | Mark as `Planned` |
| `badge-info` | ❌ | Proposed | Mark as `Planned` |
| `empty-state` | ❌ | Proposed | Mark as `Planned` |
| `empty-state-icon` | ❌ | Proposed | Mark as `Planned` |
| `empty-state-title` | ❌ | Proposed | Mark as `Planned` |
| `empty-state-description` | ❌ | Proposed | Mark as `Planned` |
| `evidence-card` | ❌ | Proposed | Mark as `Planned` |
| `proposal-card` | ❌ | Proposed | Mark as `Planned` |
| `decision-card` | ❌ | Proposed | Mark as `Planned` |
| `finding-card` | ❌ | Proposed | Mark as `Planned` |
| `candidate-card` | ❌ | Proposed | Mark as `Planned` |
| `tool-detail-grid` | ❌ | Proposed | Mark as `Planned` |
| `tool-detail-row` | ❌ | Proposed | Mark as `Planned` |
| `tool-detail-label` | ❌ | Proposed | Mark as `Planned` |
| `tool-detail-value` | ❌ | Proposed | Mark as `Planned` |
| `tool-code` | ❌ | Proposed | Mark as `Planned` |
| `tool-inline-code` | ❌ | Proposed | Mark as `Planned` |
| `tool-divider` | ❌ | Proposed | Mark as `Planned` |
| `tool-legend` | ❌ | Proposed | Mark as `Planned` |
| `tool-legend-item` | ❌ | Proposed | Mark as `Planned` |
| `tool-legend-dot` | ❌ | Proposed | Mark as `Planned` |
| `tool-legend-line` | ❌ | Proposed | Mark as `Planned` |
| `tool-legend-text` | ❌ | Proposed | Mark as `Planned` |
| `tool-legend-swatch` | ❌ | Proposed | Mark as `Planned` |
| `tool-theme-aware` | ❌ | Proposed | Mark as `Planned` |
| `tool-theme-aware-border` | ❌ | Proposed | Mark as `Planned` |
| `tool-theme-aware-bg` | ❌ | Proposed | Mark as `Planned` |
| `tool-theme-aware-text` | ❌ | Proposed | Mark as `Planned` |
| `tool-theme-aware-muted` | ❌ | Proposed | Mark as `Planned` |
| `tool-theme-aware-link` | ❌ | Proposed | Mark as `Planned` |
| `tool-theme-aware-heading` | ❌ | Proposed | Mark as `Planned` |
| `tool-theme-aware-code` | ❌ | Proposed | Mark as `Planned` |
| `tool-theme-aware-status` | ❌ | Proposed | Mark as `Planned` |
| `tool-theme-aware-focus` | ❌ | Proposed | Mark as `Planned` |
| `tool-theme-aware-hover` | ❌ | Proposed | Mark as `Planned` |
| `tool-theme-aware-disabled` | ❌ | Proposed | Mark as `Planned` |

**Total**: 39 documented primitives do not exist in CSS.

### 2.2 Primitives in CSS but NOT Documented (Undocumented)

| Primitive | CSS Exists | Documented | Action |
|---|---|---|---|
| `gs-tool-nav` | ✅ | ❌ | Add to framework |
| `gs-tool-nav-link` | ✅ | ❌ | Add to framework |
| `gs-tool-nav-link-primary` | ✅ | ❌ | Add to framework |
| `gs-tool-placeholder-back` | ✅ | ❌ | Add to framework |
| `gs-tool-placeholder-eyebrow` | ✅ | ❌ | Add to framework |
| `gs-tool-placeholder-badges` | ✅ | ❌ | Add to framework |
| `gs-tool-placeholder-facts` | ✅ | ❌ | Add to framework |
| `gs-tool-placeholder-migration` | ✅ | ❌ | Add to framework |
| `gs-tool-placeholder-panel-heading` | ✅ | ❌ | Add to framework |

**Total**: 9 undocumented primitives exist in CSS.

### 2.3 Correctly Documented Primitives (Verified)

| Primitive | CSS Exists | Documented | Consumers | Maturity |
|---|---|---|---|---|
| `gs-tool-page` | ✅ | ✅ | 1 | Stable |
| `gs-tool-header` | ✅ | ✅ | 1 | Stable |
| `gs-tool-header-title` | ✅ | ✅ | 1 | Stable |
| `gs-tool-header-actions` | ✅ | ✅ | 1 | Stable |
| `gs-tool-scroll-table` | ✅ | ✅ | 4 | Stable |
| `gs-tool-metric-grid` | ✅ | ✅ | 1 | Stable |
| `gs-tool-metric-card` | ✅ | ✅ | 1 | Stable |
| `gs-tool-action-card` | ✅ | ✅ | 1 | Stable |
| `gs-tool-action-link` | ✅ | ✅ | 3 | Stable |
| `gs-tool-status-bar` | ✅ | ✅ | 1 | Stable |
| `gs-tool-scope-strip` | ✅ | ✅ | 1 | Stable |
| `gs-tool-scope-form` | ✅ | ✅ | 1 | Stable |
| `gs-tool-scope-hint` | ✅ | ✅ | 1 | Stable |
| `gs-tool-closed-label` | ✅ | ✅ | 1 | Stable |
| `gs-tool-open-label` | ✅ | ✅ | 1 | Stable |

**Total**: 15 correctly documented primitives.

---

## 3. Consumer Map

### 3.1 Current Consumers (Verified)

| Tool | Primitives Used | Files |
|---|---|---|
| **Style Compliance** | `gs-tool-page`, `gs-tool-header`, `gs-tool-header-title`, `gs-tool-header-actions`, `gs-tool-scroll-table`, `gs-tool-metric-grid`, `gs-tool-metric-card`, `gs-tool-action-card`, `gs-tool-action-link`, `gs-tool-status-bar`, `gs-tool-scope-strip`, `gs-tool-scope-form`, `gs-tool-scope-hint`, `gs-tool-closed-label`, `gs-tool-open-label` | 8 |
| **Localization Studio** | None | 0 |
| **Customization Studio** | None | 0 |
| **Theme Doctor** | None | 0 |
| **Label Designer** | None | 0 |
| **Report Designer** | None | 0 |
| **CSS Token Editor** | None | 0 |

### 3.2 Adoption Status

- **Style Compliance**: Reference implementation (15 primitives, 8 files)
- **All other tools**: Zero shared primitive adoption (tool-specific CSS only)

---

## 4. Updated Maturity Labels (Evidence-Based)

### 4.1 Stable Primitives (15 total)

**Criteria**: Exists in CSS + has ≥1 real consumer + used in production

| Primitive | CSS Lines | Consumers | Evidence |
|---|---|---|---|
| `gs-tool-page` | ~20 | 1 | `preview.php:200` |
| `gs-tool-header` | ~15 | 1 | `_page_header.php:7` |
| `gs-tool-header-title` | ~10 | 1 | `_page_header.php:8` |
| `gs-tool-header-actions` | ~10 | 1 | `_page_header.php:9` |
| `gs-tool-scroll-table` | ~15 | 4 | `_result_sections.php`, `_result_diagnostics.php`, `_result_decision_backlog.php` |
| `gs-tool-metric-grid` | ~10 | 1 | `_result_sections.php:349` |
| `gs-tool-metric-card` | ~10 | 1 | `_result_sections.php:350-362` |
| `gs-tool-action-card` | ~15 | 1 | `_result_sections.php:368` |
| `gs-tool-action-link` | ~15 | 3 | `_result_sections.php`, `_result_action_summary.php`, `_result_decision_backlog.php` |
| `gs-tool-status-bar` | ~15 | 1 | `preview.php:209` |
| `gs-tool-scope-strip` | ~15 | 1 | `_toolbar.php:5` |
| `gs-tool-scope-form` | ~15 | 1 | `_toolbar.php:6` |
| `gs-tool-scope-hint` | ~10 | 1 | `_toolbar.php:30` |
| `gs-tool-closed-label` | ~5 | 1 | `_results_mount.php:12` |
| `gs-tool-open-label` | ~5 | 1 | `_results_mount.php:13` |

### 4.2 Experimental Primitives (14 total)

**Criteria**: Exists in CSS + zero consumers (CSS-only, not adopted)

| Primitive | CSS Lines | Consumers | Evidence |
|---|---|---|---|
| `gs-tool-nav` | ~10 | 0 | CSS only |
| `gs-tool-nav-link` | ~15 | 0 | CSS only |
| `gs-tool-nav-link-primary` | ~5 | 0 | CSS only |
| `gs-tool-placeholder` | ~150 | 0 | CSS only |
| `gs-tool-placeholder-back` | ~10 | 0 | CSS only |
| `gs-tool-placeholder-hero` | ~20 | 0 | CSS only |
| `gs-tool-placeholder-eyebrow` | ~10 | 0 | CSS only |
| `gs-tool-placeholder-badges` | ~10 | 0 | CSS only |
| `gs-tool-placeholder-grid` | ~10 | 0 | CSS only |
| `gs-tool-placeholder-panel` | ~15 | 0 | CSS only |
| `gs-tool-placeholder-primary` | ~5 | 0 | CSS only |
| `gs-tool-placeholder-actions` | ~20 | 0 | CSS only |
| `gs-tool-placeholder-notice` | ~10 | 0 | CSS only |
| `gs-tool-placeholder-resources` | ~10 | 0 | CSS only |
| `gs-tool-placeholder-facts` | ~15 | 0 | CSS only |
| `gs-tool-placeholder-migration` | ~10 | 0 | CSS only |
| `gs-tool-placeholder-panel-heading` | ~10 | 0 | CSS only |

### 4.3 Planned Primitives (39 total)

**Criteria**: Documented in framework but NOT in CSS

See Section 2.1 for full list. These are aspirational and require:
1. CSS implementation in `gui_studio.css`
2. At least one consumer before promoting to `Stable`

---

## 5. Undocumented Existing Primitives (9 total)

These primitives exist in `gui_studio.css` but were missing from the framework doc:

| Primitive | Category | CSS Lines | Recommendation |
|---|---|---|---|
| `gs-tool-nav` | Navigation | ~10 | Document as `Experimental` |
| `gs-tool-nav-link` | Navigation | ~15 | Document as `Experimental` |
| `gs-tool-nav-link-primary` | Navigation | ~5 | Document as `Experimental` |
| `gs-tool-placeholder-back` | Placeholder | ~10 | Document as `Experimental` |
| `gs-tool-placeholder-eyebrow` | Placeholder | ~10 | Document as `Experimental` |
| `gs-tool-placeholder-badges` | Placeholder | ~10 | Document as `Experimental` |
| `gs-tool-placeholder-facts` | Placeholder | ~15 | Document as `Experimental` |
| `gs-tool-placeholder-migration` | Placeholder | ~10 | Document as `Experimental` |
| `gs-tool-placeholder-panel-heading` | Placeholder | ~10 | Document as `Experimental` |

---

## 6. Planned-Only Primitives (39 total)

These are documented in the framework but do not exist in CSS:

**Component primitives (14)**:
- `badge`, `badge-success`, `badge-warning`, `badge-error`, `badge-info`
- `empty-state`, `empty-state-icon`, `empty-state-title`, `empty-state-description`
- `evidence-card`, `proposal-card`, `decision-card`, `finding-card`, `candidate-card`

**Utility primitives (14)**:
- `tool-detail-grid`, `tool-detail-row`, `tool-detail-label`, `tool-detail-value`
- `tool-code`, `tool-inline-code`, `tool-divider`
- `tool-legend`, `tool-legend-item`, `tool-legend-dot`, `tool-legend-line`, `tool-legend-text`, `tool-legend-swatch`

**Theme consumer primitives (11)**:
- `tool-theme-aware`, `tool-theme-aware-border`, `tool-theme-aware-bg`, `tool-theme-aware-text`
- `tool-theme-aware-muted`, `tool-theme-aware-link`, `tool-theme-aware-heading`, `tool-theme-aware-code`
- `tool-theme-aware-status`, `tool-theme-aware-focus`, `tool-theme-aware-hover`, `tool-theme-aware-disabled`

---

## 7. Truth Table

| Primitive | Exists in CSS | Documented | Maturity | Consumers | Migration Candidates |
|---|---|---|---|---|---|
| `gs-tool-page` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-header` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-header-title` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-header-actions` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-scroll-table` | ✅ | ✅ | Stable | 4 | None |
| `gs-tool-metric-grid` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-metric-card` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-action-card` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-action-link` | ✅ | ✅ | Stable | 3 | None |
| `gs-tool-status-bar` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-scope-strip` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-scope-form` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-scope-hint` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-closed-label` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-open-label` | ✅ | ✅ | Stable | 1 | None |
| `gs-tool-nav` | ✅ | ❌ | Experimental | 0 | Document |
| `gs-tool-nav-link` | ✅ | ❌ | Experimental | 0 | Document |
| `gs-tool-nav-link-primary` | ✅ | ❌ | Experimental | 0 | Document |
| `gs-tool-placeholder` | ✅ | ✅ | Experimental | 0 | None |
| `gs-tool-placeholder-back` | ✅ | ❌ | Experimental | 0 | Document |
| `gs-tool-placeholder-hero` | ✅ | ✅ | Experimental | 0 | None |
| `gs-tool-placeholder-eyebrow` | ✅ | ❌ | Experimental | 0 | Document |
| `gs-tool-placeholder-badges` | ✅ | ❌ | Experimental | 0 | Document |
| `gs-tool-placeholder-grid` | ✅ | ✅ | Experimental | 0 | None |
| `gs-tool-placeholder-panel` | ✅ | ✅ | Experimental | 0 | None |
| `gs-tool-placeholder-primary` | ✅ | ✅ | Experimental | 0 | None |
| `gs-tool-placeholder-actions` | ✅ | ✅ | Experimental | 0 | None |
| `gs-tool-placeholder-notice` | ✅ | ✅ | Experimental | 0 | None |
| `gs-tool-placeholder-resources` | ✅ | ✅ | Experimental | 0 | None |
| `gs-tool-placeholder-facts` | ✅ | ❌ | Experimental | 0 | Document |
| `gs-tool-placeholder-migration` | ✅ | ❌ | Experimental | 0 | Document |
| `gs-tool-placeholder-panel-heading` | ✅ | ❌ | Experimental | 0 | Document |
| `badge` (all variants) | ❌ | ✅ | Planned | 0 | Implement CSS |
| `empty-state` (all variants) | ❌ | ✅ | Planned | 0 | Implement CSS |
| `evidence-card` | ❌ | ✅ | Planned | 0 | Implement CSS |
| `proposal-card` | ❌ | ✅ | Planned | 0 | Implement CSS |
| `decision-card` | ❌ | ✅ | Planned | 0 | Implement CSS |
| `finding-card` | ❌ | ✅ | Planned | 0 | Implement CSS |
| `candidate-card` | ❌ | ✅ | Planned | 0 | Implement CSS |
| `tool-detail-*` (4 variants) | ❌ | ✅ | Planned | 0 | Implement CSS |
| `tool-code` / `tool-inline-code` | ❌ | ✅ | Planned | 0 | Implement CSS |
| `tool-divider` | ❌ | ✅ | Planned | 0 | Implement CSS |
| `tool-legend-*` (7 variants) | ❌ | ✅ | Planned | 0 | Implement CSS |
| `tool-theme-aware-*` (11 variants) | ❌ | ✅ | Planned | 0 | Implement CSS |

---

## 8. Corrected Framework Summary

### 8.1 Actual State

- **Total primitives in CSS**: 32
- **Total documented primitives**: 47
- **Correctly documented**: 15
- **Undocumented in CSS**: 9
- **Aspirational (not in CSS)**: 39
- **Stable (CSS + consumers)**: 15
- **Experimental (CSS only, no consumers)**: 17
- **Planned (documented only)**: 39

### 8.2 Adoption Reality

- **Style Compliance**: 15/32 primitives adopted (47%)
- **All other tools**: 0/32 primitives adopted (0%)
- **Placeholder primitives**: 0 consumers (CSS-only, for future placeholder system)
- **Theme consumer primitives**: 0 implemented (proposed only)

### 8.3 Key Findings

1. **Framework was aspirational, not descriptive** — 39 of 47 documented primitives do not exist in CSS
2. **Style Compliance is the ONLY adopter** — no other tool uses shared primitives
3. **Placeholder system is CSS-only** — 14 placeholder primitives exist but have zero consumers
4. **Theme consumer primitives are unimplemented** — the `tool-theme-aware-*` family is proposed only
5. **Highest-value primitives are already shared** — `gs-tool-scroll-table`, `gs-tool-metric-grid`, `gs-tool-action-card`, `gs-tool-status-bar`, `gs-tool-scope-strip` are proven in Style Compliance

---

## 9. Corrected Migration Roadmap

### Phase 1: Document Reality (Week 1)

- [ ] Update framework doc to mark 39 primitives as `Planned`
- [ ] Add 9 undocumented primitives to framework
- [ ] Correct maturity labels to evidence-based values
- [ ] Publish truth table as canonical reference

### Phase 2: Low-Hanging Fruit (Week 2-3)

- [ ] Adopt `gs-tool-scroll-table` in Localization Studio (4 tables)
- [ ] Adopt `gs-tool-scroll-table` in Customization Studio (2 tables)
- [ ] Adopt `gs-tool-status-bar` in Theme Doctor (1 status bar)
- [ ] Adopt `gs-tool-scope-strip` + `gs-tool-scope-form` in Label Designer (1 filter form)

### Phase 3: Medium Complexity (Week 4-5)

- [ ] Adopt `gs-tool-metric-grid` + `gs-tool-metric-card` in Localization Studio dashboard
- [ ] Adopt `gs-tool-action-card` + `gs-tool-action-link` in Customization Studio
- [ ] Implement `badge` family (4 variants) for status indicators
- [ ] Implement `tool-detail-*` family for key-value displays

### Phase 4: Complex Migration (Week 6+)

- [ ] Implement `empty-state` family for no-data scenarios
- [ ] Implement `tool-theme-aware-*` family for theme-responsive components
- [ ] Implement `tool-legend-*` family for chart/table legends
- [ ] Full primitive adoption audit across all tools

---

## 10. Validation

```bash
# Primitive extraction from CSS
rg -n "\.gs-tool-[a-zA-Z0-9_-]+" apps/Studio/styles/gui_studio.css | sed 's/.*\.\(gs-tool-[a-zA-Z0-9_-]*\).*/\1/' | sort -u
# Result: 32 primitives

# Consumer scan
rg -l "gs-tool-" apps/Studio/Tools -S
# Result: Style Compliance only (8 files)

# No runtime changes
git diff --check  # ✅ clean
```

---

## 11. Recommendations

1. **Do not promote any primitive to `Stable` unless it exists in CSS AND has ≥1 real consumer**
2. **Mark all 39 aspirational primitives as `Planned` in framework doc**
3. **Add 9 undocumented primitives to framework doc**
4. **Style Compliance is the only reference implementation** — do not claim cross-tool adoption
5. **Placeholder primitives are CSS-only infrastructure** — document as `Experimental` with zero consumers
6. **Theme consumer primitives require implementation before adoption** — currently `Planned`

---

## Appendix A: Verified CSS Selectors

```
.gs-tool-page
.gs-tool-header
.gs-tool-header-title
.gs-tool-header-actions
.gs-tool-action-link
.gs-tool-scope-strip
.gs-tool-scope-form
.gs-tool-scope-hint
.gs-tool-status-bar
.gs-tool-scroll-table
.gs-tool-metric-grid
.gs-tool-metric-card
.gs-tool-action-card
.gs-tool-closed-label
.gs-tool-open-label
.gs-tool-nav
.gs-tool-nav-link
.gs-tool-nav-link-primary
.gs-tool-placeholder
.gs-tool-placeholder-back
.gs-tool-placeholder-hero
.gs-tool-placeholder-eyebrow
.gs-tool-placeholder-badges
.gs-tool-placeholder-grid
.gs-tool-placeholder-panel
.gs-tool-placeholder-primary
.gs-tool-placeholder-actions
.gs-tool-placeholder-notice
.gs-tool-placeholder-resources
.gs-tool-placeholder-facts
.gs-tool-placeholder-migration
.gs-tool-placeholder-panel-heading
```

## Appendix B: Verified Consumers

```
apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_sections.php
apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_diagnostics.php
apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_decision_backlog.php
apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_result_action_summary.php
apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/Shared/_page_header.php
apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/Shared/_toolbar.php
apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/Shared/_results_mount.php
apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/preview.php
```

**Total**: 8 files, all in Style Compliance tool.

---

## Appendix C: Zero-Consumer Primitives (CSS-Only)

These 17 primitives exist in `gui_studio.css` but are not consumed by any tool:

```
gs-tool-nav
gs-tool-nav-link
gs-tool-nav-link-primary
gs-tool-placeholder
gs-tool-placeholder-back
gs-tool-placeholder-hero
gs-tool-placeholder-eyebrow
gs-tool-placeholder-badges
gs-tool-placeholder-grid
gs-tool-placeholder-panel
gs-tool-placeholder-primary
gs-tool-placeholder-actions
gs-tool-placeholder-notice
gs-tool-placeholder-resources
gs-tool-placeholder-facts
gs-tool-placeholder-migration
gs-tool-placeholder-panel-heading
```

**Classification**: `Experimental` — CSS exists but no production adoption.

---

**Document Status**: Verified against actual CSS. All maturity labels are evidence-based. No aspirational primitives are marked as `Stable`.