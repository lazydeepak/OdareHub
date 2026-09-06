# Theme Architecture Contracts (V1)

This folder documents the three-layer contract boundaries for Theme Architecture V1.

## Layer Documents

| Document | Layer | Role |
|---|---|---|
| `source-layer-contract.md` | Source | Canonical authoring home for theme values |
| `compile-publish-layer-contract.md` | Compile/Publish | Deterministic merge/validation/publish from source to runtime artifact |
| `runtime-layer-contract.md` | Runtime | Consumption of compiled variables by Shell/setup/apps |

## Contract Diagram

```
Source Layer                          Compile/Publish Layer            Runtime Layer
(resources/themes/**)                 (scripts/assets/                 (public/assets/theme.css +
  foundation/   ──►                    compile_theme_sources.php)       Shell/app component CSS)
  semantic/     ──►                  Read manifest ──►                  ──► Runtime loads
  variants/     ──►                  Validate sources ──►                    compiled artifact
  theme-manifest.json ──►            Resolve order ──►                 ──► Components consume
                                    Concatenate ──►                     var(--token)
                                    Write artifact ──►
```

## Key Rule

Each layer must remain in its own boundary:

- Source files are not compiled runtime output.
- Compiler does not own theme values.
- Runtime does not own source truth or editing workflows.

## Migration Note

These contracts are documented for governance. Current implementation may have overlapping responsibilities during the migration process. Each migration slice should reduce overlap and enforce boundaries more strictly.
