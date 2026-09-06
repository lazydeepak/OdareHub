# Shell Rendering Gate Recovery — 2026-06-05

## 1. Failing invariant

Failing check in `scripts/architecture/check_shell_rendering_contract.sh`:

- Section: `checking overlay ownership (no new overlay behavior outside Shell CSS)`
- Invariant reported: `fail: cannot find overlay class definitions in Shell CSS`

## 2. Root cause

The failure was a false negative in the gate fallback path when `rg` is unavailable.

Observed environment behavior:

- `rg` is not available in terminal PATH (`no_rg`).
- Gate falls back to `grep` with `SEARCH_ARGS=(-RIn)`.
- Overlay ownership checks pass regex patterns that require extended regex features such as alternation and grouping (for example `\.(shell-overlay|avatar-backdrop|action-panel-backdrop)\b|\bshell-overlay\b`).
- Without `-E`, `grep` treats those patterns as basic regex, so matching fails even though selectors exist.

Verified selector presence before fix:

- `apps/Shell/styles/operator.css` contains `.shell-overlay`, `.avatar-backdrop`, `.action-panel-backdrop`.
- `apps/Shell/styles/components.css` contains `.shell-overlay`.

Conclusion:

- Shell implementation was complete for this invariant.
- Gate fallback regex mode was incorrect.

## 3. Fix applied

Minimal gate-only fix:

- Updated grep fallback args in `scripts/architecture/check_shell_rendering_contract.sh`:
  - from: `SEARCH_ARGS=(-RIn)`
  - to: `SEARCH_ARGS=(-RInE)`

No runtime rendering behavior changed.
No overlay feature, layout, breakpoint, theme, Studio, or View Composition changes were made.

## 4. Validation evidence

1. Bash syntax
- `bash -n scripts/architecture/check_shell_rendering_contract.sh` -> PASS

2. Direct shell rendering gate
- `/bin/bash scripts/architecture/check_shell_rendering_contract.sh` -> PASS

3. Aggregate architecture gates
- `/bin/bash scripts/architecture/run_architecture_gates.sh` -> PASS

4. Deployment readiness
- `/bin/bash scripts/system/check_deployment_readiness.sh` -> PASS

5. Diff hygiene
- `git diff --check` -> PASS

6. PHP lint
- Not applicable (no PHP files changed).

7. JS syntax
- Not applicable (no JS files changed).
