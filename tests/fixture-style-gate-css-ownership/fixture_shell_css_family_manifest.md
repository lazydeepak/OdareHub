# Shell CSS Ownership / Debt Manifest — Session A audit-derived (read-only, reviewable)
# Source: /tmp/css_selector_audit.md (121 selectors, 0 violations)
# Rule: only selectors in this manifest may appear in apps/Shell/styles/*.css.
# Compound selectors exceeding simple prefix must remain countable as debt.
# Any selector not listed here = potential leakage (must fail gate).

## shell-layout.css — Shell platform/admin environment (permanent)
- source: apps/Shell/styles/shell-layout.css
- family: platform-mode
- patterns: ^\.platform-mode-(indicator|indicator-dot|indicator--production|indicator--development|indicator--demo|indicator:hover)$
- ownership: Shell
- debt: none
- note: environment indicator; not app-specific

## shell-admin.css — Shell admin dashboard wrapper (permanent)
- source: apps/Shell/styles/shell-admin.css
- family: platform-mode-option
- patterns: ^\.platform-mode-option-label$
- ownership: Shell
- debt: none
- family: role-dashboard
- patterns: ^\.role-dashboard-(hero|role-badge|mode-badge|accent--platform|accent--app-admin|accent--my-work|accent--production|accent--assembly|accent--qc|accent--dispatch)$
- ownership: Shell (admin role badges; assembly/qc/dispatch = labeling only)
- debt: none
- family: home-portal-admin
- patterns: ^\.home-portal-(app-grid|empty)$
- ownership: Shell
- debt: none

## shell-components.css — Shell shared component library (permanent + compound debt)
- source: apps/Shell/styles/shell-components.css
- family: qr-simple
- patterns: ^\.qr-(screen-only|print-meta|code|number|name|details|detail|detail-line|sheet|preview-stage|preview-stack)$
- ownership: Shell
- debt: simple (.qr-*) = none; compound (.qr-preview-stage etc) = DOCUMENTED (must count)
- family: home-portal-components
- patterns: ^\.home-portal-(hero|title|subtitle|section-head|app-grid|app-head|app-title-row|app-title|app-tagline|app-group|app-cta-wrap|app-empty|app-icon)$
- ownership: Shell
- debt: none

## shell-forms.css — Shell form/page design (permanent + compound debt)
- source: apps/Shell/styles/shell-forms.css
- family: timecard-simple
- patterns: ^\.timecard-(page--document|doc-bg|doc-panel|doc-border|doc-border-strong|doc-heading|doc-text|doc-muted|doc-rule|toolbar|toolbar-actions|filter-grid|filter-grid label|filter-grid select|filter-grid input|header-item-label|summary-label|table-wrap|table)$
- ownership: Shell
- debt: compound (.timecard-toolbar etc) = DOCUMENTED (must count)
- family: home-portal-forms
- patterns: ^\.home-portal-(app-summary|group-label)$
- ownership: Shell
- debt: none
- family: qr-form
- patterns: ^\.qr-(sheet-page|title|model|detail-label|side-fields|code img)$
- ownership: Shell
- debt: compound = DOCUMENTED (must count, .qr-sheet-page etc)

### shell-surfaces.css — Shell shared surface components (permanent + compound debt)
- source: apps/Shell/styles/shell-surfaces.css
- family: qr-surface
- patterns: ^\.qr-sheet-card$, ^\.qr-[a-z-]*-card$
- ownership: Shell
- debt: compound (.qr-sheet-card etc) = DOCUMENTED
- family: timecard-surface
- patterns: ^\.timecard-card$, ^\.timecard-card--[a-z-]*$, ^\.timecard-document-header$, ^\.timecard-filter-grid$, ^\.timecard-page--document\.timecard-card$
- ownership: Shell
- debt: compound = DOCUMENTED

## Gate behavior
- Any selector in listed source + matching listed family pattern = allowed (counted as legitimate)
- Any compound selector in listed source that requires compound recognition beyond simple prefix = counted as DOCUMENTED DEBT (visible, countable, must not exceed 15 total)
- Any selector matching risky-prefix pattern but NOT in manifest = leakage (fail)
- Any selector in Shell CSS that uses manufacturing/app/module business selectors (.mfg-*, .manufacturing-*, .product-card, .coverage-kpi, etc.) = real violation (fail, regardless of manifest)
