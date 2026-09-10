# Manufacturing Product Extension — Shared Items Session A

## Ownership
- Shared Item identity (item_id, code_ref, name, status): Shared Items (canonical)
- Manufacturing Product extension (parts_name, parts_number, model, producer, etc.): Manufacturing module
- Reference: Manufacturing Product → item_ref → Shared Item

## Product / Part / Material Semantics (contextual, not identity duplication)

A single Shared Item may be presented to users in different Manufacturing contexts:

- Product: Standard finished-goods view (manufacturing.product.label context)
- Part: Component-level view (manufacturing.part.label context — if needed by module)
- Material: Raw/covering material view (manufacturing.material.label context — if needed)

These are display roles assigned by Manufacturing module extension, not separate canonical identities.
No separate Master table is required for Product, Part, or Material.

## Extension persistence shape

The existing `item_ref` column (INT NULL, idx) on Manufacturing `products` provides the linkage.
No duplicate identity fields (name, code, status) are stored in Manufacturing extension.
Manufacturing-specific fields remain in Manufacturing module only (migrations 001-011).

## Compatibility
- Existing Products without `item_ref`: continue functional (backward-compatible)
- Existing Product IDs and routes: unchanged
- Historical references preserved (no rename, no deletion)
- No bulk migration performed

## Not implemented (correctly deferred)
- DB schema migration beyond 012 (not required for reference)
- Inventory/session C stock linkage (needs separate session)
- BOM/recipe structures (needs separate design)
- Suite-specific extension beyond reference
