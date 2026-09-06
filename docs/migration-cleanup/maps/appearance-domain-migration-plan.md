# Appearance Domain Migration Plan

Status: Deferred architecture map. No item in this plan is in progress.

This map records the governed migration from legacy theme and combined-preference terminology to the structured Appearance domain. The current checkpoint implements none of these migrations.

| # | Deferred item | Status |
|---:|---|---|
| 1 | Introduce a structured Appearance value object. | Deferred |
| 2 | Add a compatibility adapter with a legacy combined-preference parser and projection. | Deferred |
| 3 | Introduce canonical structured persistence fields. | Deferred |
| 4 | Add a palette catalog and editor. | Deferred |
| 5 | Add a surface-profile catalog. | Deferred |
| 6 | Add an effect-profile catalog and persistence. | Deferred |
| 7 | Split Liquid Glass colors from effects. | Deferred |
| 8 | Split Paper colors from surface treatment. | Deferred |
| 9 | Add complete light/dark palette coverage validation. | Deferred |
| 10 | Publish structured Appearance capabilities. | Deferred |
| 11 | Migrate settings UI and browser storage. | Deferred |
| 12 | Add Appearance preset support using references rather than duplicated tokens. | Deferred |
| 13 | Rename Theme Doctor to Appearance Doctor after domain and consumer parity. | Deferred |
| 14 | Rename `ThemePreferenceService` after all consumers migrate. | Deferred |
| 15 | Retire legacy selectors, settings, attributes, terminology, and combined values after parity. | Deferred |
| 16 | Add governed preview, diff, approval, snapshot, apply, and rollback for authoring. | Deferred |

## Migration constraints

- Preserve current runtime CSS, selectors, settings, compiler behavior, persistence, browser-storage keys, and combined preference values until their dedicated migration slices reach parity.
- Do not treat Theme Doctor draft JSON as runtime Appearance truth.
- Do not begin an item merely because this map names it; each item requires a separately approved implementation slice and validation plan.
- Do not remove compatibility representations until structured producers and consumers have reached verified parity.
