# Studio/tools/LocalizationScanExtraction — Decisions

## Decision Record Format

### YYYY-MM-DD — Decision title

**Decision**

**Reason**

**Impact**

**Revisit when**

---

### 2026-06-17 — Scanner-first architecture

**Decision**
Build the inline localization scanner as a read-only classification tool before adding any extraction or apply behavior.

**Reason**
Ensure classification accuracy, category coverage, and duplicate-free findings before enabling mutation workflows.

**Impact**
Scanner reached `scanner_wired` status with 8 categories and reconciliation logic. Extraction/apply was added later only after the scanner was stable and verified.

**Revisit when**
N/A — decision fully executed.

---

### 2026-06-17 — Locale externalization before extraction enablement

**Decision**
Externalize all UI-facing strings to `Resources/lang/{en,ja,ne}.php` files before enabling extraction/apply workflows.

**Reason**
Tool must be self-localized before it can reliably propose locale corrections to other owners. The inline dict pattern was temporary scaffolding.

**Impact**
148 keys per locale across en/ja/ne. Persistent safety bar added to signal extraction readiness.

**Revisit when**
N/A — locale externalization complete.

---

### 2026-06-20 — Migration planner as read-only classification

**Decision**
Implement `InlineMigrationPlannerService` as a pure read-only classifier that determines `ready_to_migrate`, `needs_review`, or `rejected` state per finding — separate from the apply engine.

**Reason**
Keep migration planning decoupled from execution so platform admins can review and approve before any writes occur. The planner is a guide, not an autorun.

**Impact**
Planner uses semantic rules + common UI text map (42 entries) for key suggestions. Apply engine enforces `ready_to_migrate` state as gate. 43/43 probe assertions pass.

**Revisit when**
If planner accuracy degrades as more owners are scanned.

---

### 2026-06-17 — English-only inline migration apply

**Decision**
Inline migration apply is restricted to English (`en`) locale only. Non-EN candidates are rejected.

**Reason**
English is the baseline locale. Automated migration to other locales risks incorrect translations. The apply engine handles source replacement + locale key addition, which is only safe for the source language.

**Impact**
Non-EN findings are classified and planned but cannot be applied through this tool. Handoff to LocalizationStudio for manual translation is recommended.

**Revisit when**
If reliable automated translation pipelines are integrated.
