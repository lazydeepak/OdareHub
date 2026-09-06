# Studio/tools/CustomizationStudio

## Purpose

Customization Studio is Studio's governed workspace for discovering, diagnosing, previewing, authoring, and eventually migrating Susankhya OS styling and Appearance resources.

It consolidates existing theme, CSS token, socket, selector, effect, and visual-customization capabilities without creating competing sources of truth. Its capabilities must be reusable by larger Studio workflows rather than being confined to individual tool pages.

## Target State

Provide one simple Studio entry point where an authorized operator can:

- inspect the current styling state across themes, owners, Shell surfaces, catalogs, and runtime artifacts;
- understand each tool's truthful capabilities and blockers;
- preview and validate proposed changes;
- invoke governed draft, approval, snapshot, apply, verification, and rollback capabilities where implemented;
- migrate legacy styling sources into canonical feature management incrementally;
- expose the same capabilities to larger tools such as App Creator.

The target state is reached through bottom-up consolidation of proven capabilities, not a parallel rewrite.

## Responsibilities

- Maintain a manifest-driven inventory of all Customization Studio subtools.
- Discover canonical Shell DesignSystem socket catalogs.
- Present truthful capability state for inspect, preview, draft, validate, approve, apply, snapshot, rollback, source writes, registry writes, and runtime impact.
- Diagnose style compliance, ownership, token usage, theme health, and effect readiness.
- Author governed theme-token changes through approved tool boundaries.
- Preserve snapshots, provenance, validation, and runtime verification for mutation paths.
- Prepare reusable Studio services and adapters from existing working implementations.
- Coordinate future styling migration without becoming a second styling runtime.

## Boundaries

- Studio owns authoring, diagnostics, governance, and migration coordination.
- Shell owns runtime rendering and the effective Appearance state of Shell surfaces.
- Platform owns approved style-registry storage and runtime consumption abstractions.
- Business owners retain ownership of their own CSS and styling artifacts.
- Tool manifests are the source of truth for advertised capability state.
- Placeholder or disabled tools must not advertise executable mutation.
- Runtime style-consumption, application, Shell insertion, and rendered proof remain disabled until separately approved.
- Studio drafts and diagnostic inventories are not runtime truth.
- Generated assets are delivery output, not authoring truth.

## Canonical Source Areas

- `apps/Studio/Tools/CustomizationStudio`
- `engineering/Studio/tools/CustomizationStudio`
- `apps/Shell/DesignSystem/Resources/socket-catalog`
- `resources/themes`
- `apps/Platform/StyleRegistry`
- `platform/Style`

## Dependencies

- Studio governed-tool registry and tool manifests.
- Shell DesignSystem socket catalogs.
- Theme source files and deterministic theme compilation.
- Platform Style Registry contracts for approved socket values.
- Existing Style Compliance, Visual Customizer, CSS Token Editor, Token Impact Explorer, Theme Doctor, CSS Live Editor, and Special Effects services.

Dependencies must be consumed through their approved ownership boundaries. Customization Studio must not make Shell or Platform depend on Studio implementation details.

## Related Workspaces

- `engineering/Studio`
- `engineering/Shell`
- Appearance and style architecture contracts under `docs/architecture`
- Platform Style Registry and runtime-consumption boundaries
- Owner Structure Scan for owner-artifact migration and governance

## Non-goals

- Replacing all existing styling systems in one rewrite.
- Enabling runtime style consumption as part of preparation work.
- Allowing arbitrary browser-generated CSS or executable code.
- Moving styling ownership away from Shell, Platform, or business owners.
- Treating Studio drafts, previews, or catalogs as active runtime truth.
- Expanding disabled mutation capabilities merely to make the UI appear complete.
- Maintaining a second hardcoded capability matrix alongside tool manifests.

## Existing Verified Notes

### Tool Groups

All tools live under `apps/Studio/Tools/CustomizationStudio/`. Verified groups include Visual Customizer, Theme Doctor, Style Compliance, Token Impact Explorer, CSS Selector Inspector, Design Token Editor, CSS Live Editor, and Special Effects.

### Canonical Routes

All customization routes are under `/apps/studio/tools/customization-studio/`. Legacy standalone route paths redirect to canonical Customization Studio paths.

### Capability Inventory

`CustomizationStudioCapabilityInventory` reads the registered subtool manifests and provides the landing workspace with a truthful consolidated capability view. It does not execute mutations and does not replace tool-specific operational services.

### Canonical Socket Catalog

The active Shell socket catalog is:

`apps/Shell/DesignSystem/Resources/socket-catalog/`

The former `apps/Shell/Style/Resources/socket-catalog/` path is obsolete and must not be used by active code, probes, or gates.

### Owner Boundaries

Customization Studio is owned by Studio, not by Shell, Platform, or any business app. CSS Token Editor currently writes to `resources/themes/` through its existing guarded save path. Other tools remain read-only or disabled unless their manifest explicitly and truthfully enables mutation.
