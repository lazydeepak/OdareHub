# Compile/Publish Layer Contract

## Role

The compile/publish layer reads source files + manifest metadata and deterministically produces the runtime artifact. It is the bridge between the source layer and the runtime consumption layer.

## Implementation

Current implementation: `scripts/assets/compile_theme_sources.php`.

Pipeline:

1. Read `resources/themes/theme-manifest.json` for source declarations.
2. Validate manifest structure and source file existence.
3. Resolve ordering: base (foundation) → mode (light/dark) → style (liquid-glass/paper) → custom.
4. Concatenate enabled source files in resolved order.
5. Write compiled output to `public/assets/theme.css`.

## Contract Rules

1. Compilation must be deterministic — same source inputs must produce identical output.
2. Compilation must not insert generated timestamps or non-deterministic markers.
3. Compilation must respect `enabled: false` entries in manifest — disabled sources are skipped.
4. Compilation must validate source file existence before reading.
5. Compilation must fail early on parse errors in source files.
6. Compilation must preserve source file order as declared by manifest resolution order.
7. Compilation must not modify source files.

## Runtime Recognition

`public/index.php` performs mtime-based invalidation checks:

- Theme manifest `mtime`
- Enabled source file `mtime`
- Compiler script `mtime`
- Legacy bridge file `mtime` (if enabled)

If any input is newer than `public/assets/theme.css`, compilation is triggered on the next request.

## Migration Compatibility

When `legacy_base.enabled` is `true`, the compiler may read a legacy bridge file and include it. This is a temporary compatibility mechanism during source theme migration. Once all tokens are in source files, legacy bridge must be set to `enabled: false`.

## Non-Goals

- The compiler does not validate token values or UI semantics.
- The compiler does not enforce naming conventions.
- The compiler does not generate theme variants dynamically — all variants must be declared as source files.
- The compiler does not perform CSS optimization or minification (defer to CDN/proxy layer if needed).

## Future Migration Notes

- When source files are restructured into subdirectories, the compiler must be updated to read from the new paths.
- The manifest `sources` array must be updated to point to structured paths before subdirectory migration is applied.
- Consider adding a `--validate-only` flag for CI use without file write.
