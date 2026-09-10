# Session A — Manifest Authority Boundary Decision (Phase 3 audited)

Status: CAN STAY AS-IS for Session A validation; REQUIRES structural follow-up before merge.

Evidence:
- File: fixture_shell_css_family_manifest.md (71 lines, read-only, reviewable)
- Path: tests/fixture-style-gate-css-ownership/ (fixture/test directory)
- Consumed by: scripts/architecture/check_shell_css_ownership.sh (line 248 filter + informational load)
- Not located at: docs/architecture/ or architecture/gate-policy/ or script-owned policy directory

Assessment:
- It IS currently acting as authoritative policy (gate passes only when manifest covers selectors).
- It is NOT in an authority-appropriate path.
- Fail-closed: YES (missing manifest = filter skips = unlisted selectors = failure).
- Arbitrary widening prevented: YES (must match exact source file + family pattern; .platform-mode-* alone does not pass without file + family).
- Compound debt visible: YES (~10 compound selectors remain countable; not suppressed).
- Every entry carries source/file/family/ownership/debt/note provenance.

Decision (recommended narrow follow-up, NOT automatic relocation):
1. Authoritative ownership/debt manifest should move to architecture-controlled path
   (e.g., docs/architecture/shell-css-ownership-manifest.md or architecture/gate-policy/)
2. Fixture directory (tests/fixture-style-gate-css-ownership/) should hold ONLY proof fixtures
   (fixture_diagnostic_inventory_allowed, fixture_fwrite_stderr_pass, etc.)
3. Gate should reference the authoritative manifest by absolute/canonical path, not a fixture
4. Hold current fixture-based mechanics for Session A only; do not treat fixture path as permanent policy

No relocation performed (per instruction: do not relocate automatically).
