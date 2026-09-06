# Repository Scanner - Decisions

## Decision Log

### 2026-07-03 — Repository Scanner becomes the repository truth foundation

**Status:** Accepted

**Context**

Multiple engineering tools independently scan different parts of the repository, resulting in duplicated scanning logic, inconsistent repository understanding, and fragmented engineering workflows.

**Decision**

Repository Scanner becomes the canonical provider of repository truth for Susankhya OS.

Repository Scanner is responsible only for discovering and exposing repository truth through deterministic, read-only inspection.

Higher-level engineering tools consume repository truth rather than independently rediscovering repository state whenever practical.

**Consequences**

* Repository scanning becomes a shared platform capability.
* Future engineering tools build on common repository truth.
* Repository Scanner remains independent from diagnosis, repair, and automation.
* Engineering consistency improves as duplicate scanning logic is reduced.

**Evidence**

Engineering Workspace architecture discussions and Repository Scanner workspace definition.

---

### 2026-07-03 — Separate discovery from diagnosis

**Status:** Accepted

**Context**

Combining repository discovery with diagnosis, repair, and workflow logic creates overly complex tools and unclear responsibilities.

**Decision**

Engineering responsibilities are separated into distinct layers:

* Repository Scanner — discovers repository truth.
* Repository Doctor — diagnoses repository health.
* Refactor Planner — proposes engineering changes.
* Creation and Upgrade workflows — perform guided engineering operations.

**Consequences**

Each engineering tool has a clear responsibility and can evolve independently while sharing a common repository truth foundation.

**Evidence**

Repository Scanner workspace architecture discussions.

---

### 2026-07-04 — Owner Lens separation from scanner logic

**Status:** Accepted

**Context**

Owner discovery logic was embedded inside the scanner service but needed view-level filtering and cross-owner navigation.

**Decision**

Owner discovery and owner-key assignment remain in `RepoTreeScannerService` as deterministic scanner logic. Owner Lens UI (filter, summary cards, tree `data-owner` attributes) is a view-level concern rendered in `preview.php`. Owner stats are computed by the scanner but consumed by the view.

**Consequences**

* Scanner logic stays deterministic and reusable.
* View can evolve independently (new filter modes, card layouts) without changing scanner output.
* Probe tests cover both scanner output (owner keys, stats, hierarchy) and view rendering (HTML markers).

**Evidence**

Helper Tool Owner Lens V1 implementation — 25 probe assertions covering scanner and view.

---

### Future Decisions

Record only significant architectural decisions that change the long-term direction, responsibilities, or engineering model of Repository Scanner.

Do not record implementation details, bug fixes, or routine development progress.
