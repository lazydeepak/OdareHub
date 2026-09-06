# Style Compliance Views

The Style Compliance presentation layer is split into view-only components.

- `preview.php` prepares the view model from `$styleComplianceResult`, loads locale helpers, and composes the page.
- `Shared/` contains the stable page frame: header, workspace tabs, scan toolbar, result mount, and browser scripts.
- `Cockpit/` contains the current operations cockpit presentation. Keep All Owners redesign work isolated here until the production report is intentionally redesigned.
- Existing `_result_*.php` partials continue to render the production scan report.

These files must not change scanner semantics, controller behavior, routes, repair/readiness services, or source CSS outside Style Compliance.

`preview.php` currently keeps a small PHP comment block of temporary compatibility shims for legacy probe checks that still search the original file for `_result_sections.php`, readiness-request strings, and interactive CSS class names. Those comments are non-rendered and can be removed once the probes understand the componentized `Shared/` partials.

## CSS Ownership Ledger

Shared Studio tool primitives live in `apps/Studio/styles/gui_studio.css` and are consumed here with `gs-tool-*` classes:

- `gs-tool-page`: reusable Studio tool page frame, spacing, max width, and token-backed surface.
- `gs-tool-header`, `gs-tool-header-title`, `gs-tool-header-actions`: reusable tool header and action wrapping.
- `gs-tool-action-link`: reusable Studio tool header/action link treatment.
- `gs-tool-scope-strip`, `gs-tool-scope-form`, `gs-tool-scope-hint`: reusable scope/filter form row and mobile stacking behavior.
- `gs-tool-status-bar`: reusable tool safety/status message bar.
- `gs-tool-scroll-table`: reusable contained horizontal table scrolling and long-value wrapping.
- `gs-tool-closed-label`, `gs-tool-open-label`: reusable details open/closed label toggling.

Style Compliance keeps only owner-specific presentation in `apps/Studio/styles/style-compliance.css` (linked by `Shared/_page_header.php`):

- `sc-*` aliases for Style Compliance surface tokens.
- Style Compliance title mark, guarded badge, and scope hint icon.
- Finding, evidence, readiness, migration, decision, repair, and inventory report semantics.
- All Owners cockpit composition and domain-specific health/action metrics.
- Central scan aura/orbit visual identity and scan-state animation.
- Investigation summary copy/status layout that is specific to this report mount.

Ownership enforcement is validated by `scripts/architecture/check_style_compliance_css_ownership.sh`.

Temporary compatibility shim comments in `preview.php` are non-rendered and exist only for legacy probes.
