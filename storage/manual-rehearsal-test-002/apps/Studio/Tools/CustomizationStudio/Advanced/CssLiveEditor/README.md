# CSS Live Editor

CSS Live Editor is a Studio-owned read-only route preview foundation for a
future click-to-edit CSS inspection workflow.

## Current Architecture

- GET-only tool route: `/apps/studio/tools/customization-studio/advanced/css-live-editor`
- GET-only preview adapter: `/apps/studio/tools/customization-studio/advanced/css-live-editor/preview-frame`
- Provider-backed target catalog with eligibility, owner, source, adapter, and data-mode metadata
- Manual route input resolves only to a provider-declared target; arbitrary routes never enter the iframe
- Existing GET preview endpoint renders registered static/mock/sanitized feeds rather than redirecting to runtime routes
- Direct PHP template search scans only approved app, module, Studio tool, and
  plugin `Views` roots and identifies every target by canonical repository-relative path
- Template search matches filename, parent folder, owner, and relative path;
  result labels always include the parent folder to disambiguate duplicate basenames
- Direct PHP template feeds use development live context only; scripts,
  navigation, forms, and mutation actions remain blocked
- Real theme and shared CSS assets applied to safe fixture structure
- Preview-only theme selector sourced from Shell `ThemePreferenceService::themeChoices()`
- Theme changes update only the iframe document theme attributes and never persist
- Browser-side removal of scripts, inline event handlers, forms, navigation, and
  destructive action surfaces before preview interaction is enabled
- Read-only click inspection for tag, ID, classes, and data-attribute identity
- Matched CSS rule inspection for source tokens, computed values, consuming
  properties, registered source owners, and owner source CSS paths
- Read-only owner CSS candidate resolution after component selection, using
  canonical app/plugin source paths and class/ID selector hints without
  claiming exact selector ownership
- Conservative route-level owner and source path hints before selection
- No save or apply actions
- No POST routes
- No file or database writes
- No CSS value editing
- No runtime CSS mutation
- No customer/business rows, runtime forms, exports, downloads, or live route execution
- No connection to Visual Customizer
- No changes to CSS Token Editor or theme architecture

`cssliveditor.php` is the Studio tool shell only. It is never an editable target.
The adapter loads the selected real route; it does not copy, trim, cache, or save
PHP page clones.

The iframe omits `allow-scripts`, `allow-forms`, `allow-popups`, and
top-navigation permissions. The preview remains pointer-disabled until the tool
shell has installed the read-only capture layer.

Source inspection is diagnostic only. It uses active CSSOM selector matches and
manifest-backed stylesheet provenance. CSS controls, draft, diff, approval,
apply, and owner-artifact writes remain disabled and require separately
approved architecture work.
