# Session D — Shared Apps / Suite Core / Suite Extension Contract
Status: Planning / contract definition. No Manufacturing restructuring executed. No loader created. No extraction implemented.
Built on: `audit/session-manufacturing-decomposition/` (Session C — read-only Manufacturing decomposition audit; branch `session-c-manufacturing-decomposition`; commit `e96fa25`).

Key corrections applied:
- Parties: adoption (not extraction) — Manufacturing has zero direct Parties imports; safe contract adoption only.
- Products: Manufacturing Core (not Shared App promotion) — core identity; 11 ALTER migrations; all Manufacturing depends on it.
- Materials: Manufacturing Core; ambiguous `required_tables` (`materials`, `material_ledger`, etc.) requires clarification before any promotion.
- First adoption: Parties.
- First true extraction: NONE YET.
- First Extension separation: Manufacturing Supply (`MANUFACTURING modules/Supply` — `service_only`, L0, 3 files) as stand-in (`ownership_type: app_extension`, `extension_of: inventory`, `extension_key: manufacturing.supply_extension`).
- Dependency model: acyclic (Shared App → Core/Platform only; Core → Extension must be disable-safe; Extension → Shared App only through read-only adapter contracts; no `extensions/` loader; no `suite` loader).
