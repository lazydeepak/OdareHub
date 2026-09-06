# Localization Studio — Localization Resource Validation Contract

**Status**: Contract baseline. Diagnostic script implemented (standalone + aggregate). Read-only. No runtime changes, DB changes, UI changes, locale file edits, or translation loading changes authorized.

**Purpose**: Define locale resource ownership, validation rules, diagnostic scope, and the future governed edit workflow for Localization Studio. Formalizes what the read-only diagnostic checks and what it deliberately does not do.

---

## 1. Localization Resource Ownership

Locale files are **owner-owned** resources. Each owner declares its own translations in its own `lang/` directory.

| Owner path | Owner layer |
|---|---|
| `app/lang/{locale}.php` | Core (locked) |
| `apps/{AppName}/lang/{locale}.php` | App-level |
| `apps/{AppName}/modules/{ModuleName}/lang/{locale}.php` | Module-level |
| `apps/Studio/Tools/{ToolName}/lang/{locale}.php` | Studio tool |
| `plugins/{PluginName}/lang/{locale}.php` | Plugin |

### 1.1 Ownership rules

1. **Locale ownership follows view ownership** — the app, module, plugin, or tool that owns a view also owns that view's translation keys and values.
2. **Smallest owner wins** — module-level translations override nothing; each owner owns its own key namespace.
3. **No cross-owner key sharing** — a key defined in `apps/Manufacturing/lang/ja.php` is owned by Manufacturing and must not be referenced from Shell, Platform, or SBAIO views as if it were a shared key.
4. **Core locale is locked** — `app/lang/` must not be modified without explicit approval.

---

## 2. Shell / System-App Localization Boundaries

| Layer | Locale scope | Boundary |
|---|---|---|
| **Shell** (`apps/Shell/lang/`) | Wrapper chrome, layout primitives, shared UI components | Must not contain business-domain translations (manufacturing, SBAIO, procurement) |
| **Platform** (`apps/Platform/lang/`) | Platform governance, ACL, system tools | Must not contain app-specific translations |
| **Core** (`app/lang/`) | Technical engine messages (PdfService, DB errors) | Locked — no business content; approval required for any change |

Business app/plugin locale files must not contain Shell, Platform, or Core key prefixes.

---

## 3. Studio Role

### 3.1 Current phase (read-only)

Studio is a **read-only inspector**. It may:

- Discover locale files across all owners
- Inspect key coverage, missing locales, and cross-language parity
- Run diagnostics for naming conventions, duplicates, malformed files, and boundary violations
- Display results in the Studio UI

Studio must NOT:

- Write to any locale file
- Generate or create missing locale files
- Become a runtime source of truth for translations
- Intercept or replace runtime translation loading
- Store locale data in DB tables
- Maintain a merged/compiled locale cache as source truth
- Hardcode a language list — languages must be discovered from filesystem

### 3.2 Future governed workflow (v2+ sketch)

The intended (not implemented) workflow:

```
Analyze → Diff → Preview → Approve → Apply → Snapshot → Rollback-support
```

1. **Analyze**: Current state inspection (v1).
2. **Diff**: Show proposed changes vs current file.
3. **Preview**: Render sample UI with proposed translations.
4. **Approve**: Owner (app/module) approves the change via Studio governance.
5. **Apply**: Studio writes the approved change to the owner's locale file.
6. **Snapshot**: Backup of pre-apply state for recovery.
7. **Rollback-support**: Governance record enables revert.

Key constraints for any future apply:

- Apply must be owner-approved.
- Apply must create a backup before writing.
- Apply must never write to Core (`app/lang/`) without explicit approval.
- Apply must respect file ownership boundaries.
- Runtime must consume approved locale resources directly from owner files, never from Studio working state.

---

## 4. Read-Only Diagnostic Scope

### 4.1 Checks performed

| # | Check | Method | Severity |
|---|---|---|---|
| 1 | **File validity** | PHP `include` returns array | Error |
| 2 | **Duplicate keys within file** | Parse array, detect repeat keys | Warning |
| 3 | **Unsupported locale codes** | Filename must be `en.php`, `ja.php`, or `ne.php` | Error |
| 4 | **Key naming convention** | Keys match `[a-z0-9._]+` | Warning |
| 5 | **Cross-locale key parity** | Owner's keys consistent across en/ja/ne | Warning |
| 6 | **Owner/path boundary** | File inside expected owner directory | Error |
| 7 | **Dangerous values** | No PHP code, superglobals, or `exec`/`eval` strings | Warning |
| 8 | **Empty files** | Array is non-empty or intentionally documented | Info |

### 4.2 Checks deliberately excluded

| Not checked | Rationale |
|---|---|
| Translation accuracy/quality | Requires human review — not automatable |
| Unused keys (orphaned) | Requires source-code scan; out of scope for this diagnostic |
| Value placeholder mismatch | Requires semantic understanding of templates |
| HTML/JS injection beyond literal `exec`/`eval` | Responsibility of the rendering layer, not locale file content rules |

### 4.3 Per-owner summary

The diagnostic produces per-owner output:

```
Owner [Manufacturing]:
  Files found: en, ja, ne
  Total keys: 142
  Duplicates: 0
  Convention violations: 2 (keys with uppercase: "User.Name")
  Missing keys: ja(5), ne(12)
  Warnings: 1 (empty key value in ne: "manufacturing.welcome")
```

### 4.4 Runtime behavior

The diagnostic is:
- **Read-only** — never writes to any file, DB, or cache
- **Deterministic** — same locale files produce same output
- **Safe** — uses `@include` for PHP files to suppress execution errors; no mutation
- **Fast** — single pass through each file

---

## 5. Diagnostic Script

Location: `scripts/architecture/check_localization_resource_diagnostics.sh`

The script is wired into the aggregate architecture gate runner at position 13 (after `check_localization_studio_boundaries.sh`, before `check_customization_studio_boundaries.sh`).

### 5.1 Gate runner position rationale

| Position | Why here |
|---|---|
| After `check_localization_studio_boundaries.sh` | The implementation-boundary gate validates Studio invariants first. This diagnostic validates locale file content — a natural extension of the same ownership domain. |
| Before `check_customization_studio_boundaries.sh` | Content validation precedes customization tooling boundaries. Locale file integrity must be established before downstream Studio tooling is checked. |

### 5.2 Exit behavior

- Exits 0 (PASS) when all files are valid with no errors
- Exits 1 (FAIL) when any error-severity issue is found
- Warnings do not cause failure but are reported

---

## 6. Cross-Contract Alignment

| Contract | Alignment |
|---|---|
| **Localization File Structure v1 Contract** (`docs/architecture/localization-file-structure-v1-contract.md`) | Locks the canonical future locale path (`{OwnerRoot}/Resources/lang/{locale}.php`), fallback chain, compatibility-window policy, and phased owner-by-owner migration before any path migration begins. |
| **Localization Studio v1 Foundation** (`docs/architecture/localization-studio-v1-foundation.md`) | This contract formalizes the validation rules sketched in Section 7 of the v1 foundation doc. Ownership model, discovery patterns, and forbidden shortcuts are preserved. |
| **Localization Studio Boundary Gate** (`scripts/architecture/check_localization_studio_boundaries.sh`) | The boundary gate validates Studio implementation invariants (no save/apply/write). This contract adds locale content validation. The two are complementary — one checks the tool, the other checks the data. |
| **STUDIO-OPERATING-CONTRACT.md** | Studio remains a governed worker. The diagnostic runs in the architecture gate suite, not as a runtime service. Studio never becomes source of truth for translations. |

---

## 7. Validation

Run:

```bash
bash scripts/architecture/check_localization_resource_diagnostics.sh
bash scripts/architecture/run_architecture_gates.sh
```

Expected:

```
ARCHITECTURE GATES: PASS
```

---

## 8. Non-Goals

This contract does not authorize:

- Translation editing or generation
- Locale file creation
- Save/apply endpoints
- DB tables for translation storage
- Runtime translation loading changes
- Core locale file modifications
- Language list hardcoding in Studio code
- Merged/compiled locale bundles
- Any change that makes Studio a runtime dependency
