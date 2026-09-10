# CSS Selector Audit for check_shell_css_ownership.sh (121 selectors)

## Method
Read-only inventory of failing selectors grouped by source file, family, and subsystem.
No allowlist changes made. Classification only.

## By Source File

### apps/Shell/styles/shell-layout.css (~6 selectors)
- `.platform-mode-indicator` (line 64) + hover/dot/production/development/demo variants (65-69)
- Family: platform mode environment indicator
- Owning subsystem: Shell (platform/admin environment display)
- Classification: LEGITIMATE SHELL OWNERSHIP (Shell platform feature for admin users)
- Documented debt: No (not in allowlist; should be allowed if gate correct)
- Root cause: Gate's risky-prefix list includes "platform", but `.platform-mode-*` is a Shell-wide platform indicator, not app-specific.

### apps/Shell/styles/shell-admin.css (~19 selectors)
- `.platform-mode-option-label` (line 18)
- `.role-dashboard-hero`, `.role-dashboard-role-badge`, `.role-dashboard-mode-badge` (line 19, compressed)
- `.role-dashboard-accent--platform`, `--app-admin`, `--my-work`, `--production`, `--assembly`, `--qc`, `--dispatch` (line 19)
- `.home-portal-app-grid` (line 167), `.home-portal-empty` (168)
- Family: role dashboard accent badges for admin interface; home portal layouts
- Owning subsystem: Shell (admin dashboard / platform admin interface)
- Classification: LEGITIMATE SHELL OWNERSHIP (admin dashboard is Shell-owned wrapper chrome)
- Note: `.role-dashboard-accent--assembly/--qc/--dispatch` use manufacturing/qc/dispatch terms in ROLE BADGE context for admin interface — this is correct admin feature labeling, not Manufacturing business CSS leakage.
- Documented debt: No

### apps/Shell/styles/shell-components.css (~30+ selectors)
- `.qr-screen-only`, `.qr-print-meta`, `.qr-preview-stage`, `.qr-preview-stack`, `.qr-sheet`, `.qr-number`, `.qr-name`, `.qr-details`, `.qr-detail`, `.qr-detail-line`, `.qr-code` + variants (lines ~1376-1398)
- `.home-portal-hero`, `-title`, `-subtitle`, `-section-head`, `-app-grid`, `-app-head`, `-app-title-row`, `-app-title`, `-app-tagline`, `-app-group`, `-app-cta-wrap`, `-app-empty`, `-app-icon` (lines ~430-456, ~1344)
- Family: QR code print components + home portal grid components
- Owning subsystem: Shell (shared component library for QR/print/home)
- Classification: LEGITIMATE SHELL OWNERSHIP + DOCUMENTED TEMPORARY DEBT (QR is explicitly allowed via `.qr-` allowlist, but compound selectors like `.qr-preview-stage` exceed simple prefix match)
- Note: The allowlist uses `\.qr-` which matches the prefix but gate may not fully allow all compound `.qr-*` selectors.

### apps/Shell/styles/shell-forms.css (~50+ selectors)
- `.home-portal-app-summary`, `-group-label` (line 242)
- `.qr-sheet-page`, `.qr-title`, `.qr-model`, `.qr-detail-label`, `.qr-side-fields`, `.qr-code` + variants (lines 776-802, 802+)
- `.timecard-page--document`, `-doc-bg`, `-doc-panel`, `-doc-border`, `-doc-border-strong`, `-doc-heading`, `-doc-text`, `-doc-muted`, `-doc-rule` (lines 802-812)
- `.timecard-toolbar`, `-filter-grid`, `-summary-grid`, `-toolbar-actions`, `-filter-grid label/select/input`, `-header-item-label`, `-summary-label`, `-table-wrap`, `-table` (lines 820-867)
- Family: Form/page components for home portal, QR sheets, timecards
- Owning subsystem: Shell (form/page design system components)
- Classification: LEGITIMATE SHELL OWNERSHIP + DOCUMENTED TEMPORARY DEBT (timecard allowed via `.timecard-`, QR partly allowed, home-portal not in allowlist)

### Total Classification (mutually exclusive categories, no overlap)
- LEGITIMATE SHELL OWNERSHIP: ~95 selectors (permanently owned Shell/platform/admin/shared features: all .platform-mode-*, all .role-dashboard-*, all .home-portal-*, simple .qr-/* and .timecard-/* where prefix-only match sufficient)
- DOCUMENTED TEMPORARY DEBT: ~10 selectors (compound variants exceeding simple prefix: .qr-preview-stage/.qr-preview-stack/.qr-sheet-page, .timecard-toolbar/.timecard-filter-grid, .home-portal-app-summary — remain countable, not swallowed)
- REAL OWNERSHIP VIOLATION: 0 (no .mfg-*, .manufacturing-*, or app-module business selectors in Shell CSS; .assembly/--qc/--dispatch are role-badge labels, not Manufacturing CSS)
- UNCLEAR: 0
- Overlap resolved: every compound selector assigned to exactly one category (debt if it requires compound recognition beyond simple prefix; legitimate if simple prefix sufficient)

## Conclusion for CSS gate
The 121 selectors are overwhelmingly legitimate Shell/platform features:
- `.platform-mode-*` = Shell platform environment indicator
- `.role-dashboard-*` = Shell admin dashboard (includes manufacturing/qc/dispatch as role labels, correct use)
- `.home-portal-*` = Shell home portal component
- `.qr-*` / `.timecard-*` = Shell shared print/timecard components (mostly allowed, some compound variants exceed allowlist)
- No manufacturing-specific CSS (like `.mfg-product-card`) leaked into Shell styles.

The gate's allowlist needs strengthening for compound selectors (`.qr-preview-stage`, `.timecard-toolbar`) and home-portal family, NOT for the core families themselves.
