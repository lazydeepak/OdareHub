# Studio/tools/LocalizationStudio — Decisions

## Decision Record Format

### YYYY-MM-DD — Decision title

**Decision**

**Reason**

**Impact**

**Revisit when**

---

### 2026-05-31 — Discovery-first architecture

**Decision**
Start Localization Studio as a read-only discovery/inspection tool before adding any edit/apply behavior.

**Reason**
Avoid premature write capability before understanding the full landscape of locale file ownership, patterns, and validation requirements.

**Impact**
v1 was purely read-only with coverage scan and key inspection. v2 added governed edit/apply with snapshot/rollback only after read-only validation was proven.

**Revisit when**
N/A — decision fully executed.

---

### 2026-06-05 — Canonical path `{OwnerRoot}/Resources/lang/{locale}.php`

**Decision**
Lock canonical locale file path to `{OwnerRoot}/Resources/lang/{locale}.php` with one-file-per-locale shape (`en.php`, `ja.php`, `ne.php`).

**Reason**
Standardize locale file location across all owners, enable consistent discovery and validation, and allow backward-compatible migration from legacy paths.

**Impact**
All 35 owners migrated to canonical path. Resolver supports both legacy and canonical paths during migration window. Fallback chain: requested locale → `en` → key literal.

**Revisit when**
Legacy path support can be removed once all owners have fully migrated.

---

### 2026-06-02 — Governed edit with snapshot-before-write

**Decision**
All locale file edits must create a snapshot under `storage/studio-snapshots/` before applying changes, use atomic tempfile+rename writes, and provide post-apply diagnostics.

**Reason**
Protect against data loss, enable rollback, and provide audit trail for locale file modifications.

**Impact**
Every write is snapshotted, verifiable, and revertible. Diagnostics confirm write success, file integrity, and key count.

**Revisit when**
N/A — proven pattern used by Label Designer and other Studio tools.
