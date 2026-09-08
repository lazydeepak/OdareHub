# Studio Capability Inventory V1

## Purpose

This inventory establishes the current Studio capability baseline before capability extraction or builder expansion.

It answers four questions:

1. Which useful capabilities already exist?
2. Where are those capabilities currently owned?
3. Which capabilities are duplicated, page-bound, incomplete, or unsafe to compose?
4. Which small extraction slices should be completed first?

This is a read-only architecture inventory. It does not authorize mutation, change routes, redesign pages, or introduce a capability framework.

---

## Governing Direction

Studio exists to diagnose and repair OdareHub and to create, inspect, edit, migrate, and delete owners and owner-owned components through governed workflows.

The target architecture is:

```text
Studio capability -> reusable service/action
Tool page         -> focused interface for one or more capabilities
Large builder     -> composition of multiple capabilities
Owner runtime     -> consumes only owner-owned output artifacts
```

A tool page is not the capability boundary. App Builder, Module Builder, View Editor, repair workflows, and other future Studio orchestrators must be able to reuse the same bounded engineering operations.

---

## Inventory Method and Scope

The V1 inventory was produced by static inspection of `main`, including:

- recursive tool manifest discovery through `StudioGovernedToolRegistryService`;
- non-placeholder and active/available tool manifests;
- the central `StudioController` service imports and orchestration surface;
- representative discovery, diagnosis, authoring, mutation, snapshot, and verification services;
- the Studio tool lifecycle architecture gate; and
- planned builder manifests that define future composition demand.

Primary evidence areas:

- `apps/Studio/Services/StudioGovernedToolRegistryService.php`
- `apps/Studio/Controllers/StudioController.php`
- `apps/Studio/Tools/**/manifest.php`
- `apps/Studio/Tools/**/Services/`
- `scripts/architecture/check_studio_tool_lifecycle_contract.sh`

This is a static baseline, not the serialized output of running the registry on an installed instance. Runtime availability and instance policy still require execution-level certification.

---

## Current Capability Portfolio

### 1. Repository Scanner

**Manifest:** `Tools/HelperTool/manifest.php`  
**Status:** available, non-placeholder, read-only  
**Primary service:** `RepoTreeScannerService`

**Current capabilities**

- repository file inventory;
- file classification and review buckets;
- owner discovery;
- owner hierarchy construction;
- controller, service, view, and route entity discovery;
- bounded file preview; and
- repository search support.

**Capability readiness:** high for read-only reuse.

**Main issue:** owner discovery now has one canonical authority, but Repository Scanner and Owner Structure still project separate compatibility schemas that can drift if not re-certified together.

**Likely consumers:** Owner Structure Scan, App Builder, Module Builder, Resource Explorer, View Editor, dependency analysis, deletion planning, and instance certification.

---

### 2. Owner Structure Scan

**Manifest:** `Tools/OwnerStructureScan/manifest.php`  
**Status:** available, non-placeholder, mixed read and guarded initialization  
**Primary services:** canonical-owner compatibility resolution, filesystem scan, artifact classification, contract diagnosis, migration planning, reference discovery, workspace initialization, deletion-stage adapters, deletion workspace presentation, and section guards.

**Current capabilities**

- consume canonical owner discovery through a compatibility adapter;
- resolve and validate a selected owner key;
- inspect owner filesystem structure;
- classify owner artifacts;
- diagnose Contract V2 state;
- discover references and dependencies;
- build migration plans;
- initialize engineering-workspace artifacts; and
- present the 14-stage owner-deletion workflow surface (impact through reference-remediation patch proposal) by composing canonical Studio deletion capabilities.

**Capability readiness:** high for diagnosis and planning; medium for mutation composition.

**Main issues**

- keeps a compatibility owner-projection layer that can drift from Repository Scanner projection rules;
- the manifest declares modification but no approval, diff, snapshot, or rollback support;
- read-only diagnosis, guarded initialization, and deletion-workspace composition are combined under one tool effect; and
- actual owner deletion execution and reference-remediation patch apply are intentionally unimplemented.

**Likely consumers:** App Builder, Module Builder, owner migration, rename workflows, deletion planning, contract repair, and instance certification.

---

### 3. Resource Explorer

**Manifest:** `Tools/ResourceExplorer/manifest.php`  
**Status:** available, non-placeholder, read-only  
**Primary service:** `AppStudioRegistryService`

**Current capabilities**

- build a global library of apps, plugins, generated apps, modules, views, dashboards, routes, and navigation;
- index resources by stable node ID;
- summarize entity counts; and
- include route-runtime authority diagnostics.

**Capability readiness:** high for library and resource-discovery reuse.

**Main issue:** resource discovery overlaps owner and entity discovery from Repository Scanner but uses another independent output model.

**Likely consumers:** all builders, editors, validation workflows, dependency selection, and capability target pickers.

---

### 4. Validation Center

**Manifest:** `Tools/ValidationCenter/manifest.php`  
**Status:** available, non-placeholder, read-only  
**Primary service:** `StudioGovernanceService`

**Current capabilities**

- authoring-policy description;
- focused-start lifecycle planning;
- workflow checklist seeds;
- approval checkpoint definitions;
- preflight and quality-gate policy; and
- publish-governance support.

**Capability readiness:** medium-high as shared governance policy.

**Main issue:** the manifest presents a broad validation capability, while the underlying service combines policy descriptors, workflow seeds, audit storage, approval, and publish concerns. These should become bounded governance capabilities rather than one universal service dependency.

**Likely consumers:** every mutating capability and every composite builder.

---

### 5. Audit History

**Manifest:** `Tools/AuditHistory/manifest.php`  
**Status:** available, non-placeholder, read-only surface  
**Primary service:** `StudioGovernanceService`

**Current capabilities**

- expose Studio governance history;
- load change records and snapshots; and
- represent diff, snapshot, and rollback-related history.

**Capability readiness:** medium.

**Main issue:** history presentation and underlying provenance/snapshot operations are not declared as independent reusable capabilities.

**Likely consumers:** Change Control Center, rollback workflows, App Builder, owner migration, and repair workflows.

---

### 6. Engineering Workspaces

**Manifest:** `Tools/EngineeringWorkspaces/manifest.php`  
**Status:** available, non-placeholder, guarded mutation  
**Primary services:** hub, coverage, provisioning, coverage assignment, and content contract.

**Current capabilities**

- discover and summarize engineering workspaces;
- preview provisioning;
- apply provisioning;
- create, replace, and restore workspace artifacts;
- create missing workspaces in batch; and
- assign coverage.

**Capability readiness:** high and closest to the target governed lifecycle.

**Strength:** declares approval, owner-artifact writes, diff, snapshot, and rollback support.

**Main issue:** its lifecycle patterns are useful outside engineering workspaces but remain tool-specific rather than shared mutation primitives.

**Likely consumers:** Owner Structure Scan, owner creation, module creation, engineering governance, and App Builder.

---

### 7. Label Designer

**Manifest:** `Tools/LabelDesigner/manifest.php`  
**Status:** guarded context/template/rule authoring, non-placeholder  
**Primary services:** discovery, data-source discovery, context/template/rule create and edit, preview, metadata, duplication, diagnostics, readiness, sandbox, and runtime dry-run validation.

**Current capabilities**

- discover owner label resources;
- create and edit contexts, templates, and rules;
- duplicate resources;
- preview and render labels;
- validate runtime behavior through dry run;
- diagnose resources and readiness; and
- snapshot modifications.

**Capability readiness:** high internally; medium for external composition.

**Main issues**

- the manifest registers the central controller method rather than the actual reusable services;
- many capabilities are orchestrated directly by `StudioController`;
- owner writes do not require approval;
- rollback is not supported by the manifest.

**Likely consumers:** App Builder, Module Builder, component editors, product setup, and governed resource duplication.

---

### 8. Localization Studio

**Manifest:** `Tools/LocalizationStudio/manifest.php`  
**Status:** active, non-placeholder, owner-resource mutation  
**Primary services:** `LocalizationStudioDiscoveryService` and `LocalizationStudioEditService`.

**Current capabilities**

- discover locale files and group them by owner;
- calculate key coverage and missing locales;
- read and flatten locale resources;
- validate locale values;
- snapshot locale files;
- atomically write locale files;
- create missing locale files from English keys; and
- run post-apply diagnostics.

**Capability readiness:** high for a narrow mutation pilot.

**Main issues**

- discovery derives its own owner identity instead of consuming canonical owner discovery;
- mutation methods accept filesystem paths instead of a structured owner-resource target;
- write operations do not require approval;
- the manifest declares rollback support, but the inspected edit service exposes snapshot and write operations without an explicit restore operation;
- snapshots are stored beside source files in `.backups`, while other Studio workflows use different snapshot locations and contracts.

**Likely consumers:** App Builder, Module Builder, Label Designer, notification/template tools, View Editor, and migration workflows.

---

### 9. Inline Localization Correction Tool

**Manifest:** `Tools/LocalizationScanExtraction/manifest.php`  
**Status:** scanner wired, non-placeholder, mixed diagnosis and correction  
**Primary services:** scan facade, source scanner, planner, correction, apply, rollback/history services.

**Current capabilities**

- scan owner source files for translation-key usage;
- detect hardcoded text candidates;
- detect missing and possibly unused keys;
- build editor handoff URLs;
- plan inline localization migration; and
- support correction/apply workflows.

**Capability readiness:** high for read-only scanning; medium for mutation composition.

**Positive evidence:** `LocalizationScanService` already reuses `LocalizationStudioEditService`, proving that capability composition has started.

**Main issues**

- overlaps Localization Studio discovery and editing;
- the manifest lists only the scanner facade despite additional mutation services;
- modification is declared without approval, diff, or rollback support in the manifest;
- source diagnosis, migration planning, file editing, and handoff navigation are not exposed as separately declared capabilities.

**Likely consumers:** Localization Studio, App Builder, View Editor, validation, and migration assistants.

---

### 10. Report Designer

**Manifest:** `Tools/ReportDesigner/manifest.php`  
**Status:** active, non-placeholder, owner-resource authoring  
**Primary services:** source catalog, database discovery, Platform report-definition validator, and repository.

**Current capabilities**

- discover installed business suites and modules;
- discover live database tables and columns;
- infer temporary report-source mappings;
- validate report definitions; and
- save report definitions through the Platform repository.

**Capability readiness:** medium-high.

**Main issues**

- the manifest registers central controller methods for preview and save;
- source discovery is explicitly a temporary heuristic bridge;
- authoring does not require approval;
- diff and rollback are not supported;
- formal business-owner report-source provider capabilities do not yet replace database inference.

**Likely consumers:** App Builder, Module Builder, dashboard editors, reporting workspaces, and validation workflows.

---

### 11. Nav Menu Tool

**Manifest:** `Tools/NavMenuTool/manifest.php`  
**Status:** read-only canonical bridge, non-placeholder  
**Primary service:** `StudioNavLinkingService`.

**Current capabilities**

- inspect and compose navigation/linking state;
- bridge the legacy Menu Editor entry to the governed navigation composer; and
- produce change-oriented navigation information without applying changes.

**Capability readiness:** medium for read-only reuse.

**Main issue:** authoring capability remains unavailable, so App Builder cannot yet compose navigation creation through a governed mutation capability.

---

### 12. Customization Studio

**Manifest:** `Tools/CustomizationStudio/manifest.php`  
**Status:** preview composition surface, non-placeholder  
**Effect:** parent workbench; currently not a modifying capability.

**Current capabilities**

- group design-system, diagnostic, advanced, and effects workspaces;
- load view, component, dashboard, and theme targets; and
- provide a migration bridge for visual customization work.

**Capability readiness:** low as a capability, useful as an orchestrator.

**Main issue:** the parent and recursively registered child tools mix workbench hierarchy with capability registration. The parent should compose child capabilities, not appear as an equivalent engineering operation.

---

### 13. Theme Doctor

**Manifest:** `CustomizationStudio/Diagnose/ThemeDoctor/manifest.php`  
**Status:** read-only diagnostic, non-placeholder  
**Primary services:** analyzer and theme-registry reader, currently reached through `StudioController`.

**Current capabilities**

- inspect theme registry and appearance state;
- diagnose theme and surface issues;
- return controlled diagnostics; and
- provide read-only theme evidence.

**Capability readiness:** medium-high for read-only reuse.

**Main issue:** the manifest exposes the controller page method rather than the analyzer capability directly.

**Likely consumers:** Customization Studio, Style Compliance, App Builder appearance setup, validation, and instance certification.

---

### 14. Style Compliance

**Manifest:** `CustomizationStudio/Diagnose/StyleCompliance/manifest.php`  
**Status:** active read-only diagnosis; guarded repair executor disabled  
**Primary services:** scanner, guarded-repair contract, repair readiness, repair engine, deterministic fix-one, and presentation summary.

**Current capabilities**

- scan style sources;
- classify governance and repair decisions;
- build Theme-Aware Repair proposals;
- calculate repair readiness;
- build Shell Foundation Inventory evidence;
- prepare guarded apply preflight information; and
- expose a disabled mutation contract.

**Capability readiness:** high for diagnosis and planning; mutation not currently available.

**Strength:** the guarded repair contract is already explicitly modeled and environment-aware.

**Main issues**

- the manifest still registers a controller method alongside actual services;
- result and diagnostic models are tool-specific;
- generic style source discovery, proposal generation, readiness, and mutation should be separately composable;
- the main lifecycle gate certifies this tool deeply but does not apply equivalent portfolio-wide capability checks.

**Likely consumers:** Customization Studio, App Builder styling, Theme Doctor, validation, and repair workflows.

---

### 15. Token Impact Explorer

**Manifest:** `CustomizationStudio/Diagnose/TokenImpactExplorer/manifest.php`  
**Status:** active, non-placeholder, read-only  
**Primary service:** token impact discovery, but the manifest exposes only the controller method.

**Current capabilities**

- inspect CSS token usage and impact across CSS and views; and
- provide dependency evidence before token changes.

**Capability readiness:** medium-high after direct service registration.

**Likely consumers:** CSS Token Editor, App Builder styling, Style Compliance, Theme Doctor, and change-impact analysis.

---

### 16. CSS Live Editor

**Manifest:** `CustomizationStudio/Advanced/CssLiveEditor/manifest.php`  
**Status:** planned but non-placeholder; read-only preview implementation  
**Primary services:** target resolution, source resolution, source catalogs, preview sanitization, template targeting, and rendering.

**Current capabilities**

- resolve editable preview targets;
- catalog style, CSS, and template sources;
- sanitize preview behavior; and
- render isolated preview content.

**Capability readiness:** medium for preview composition.

**Main issue:** status says planned although substantial read-only services and routes exist. Capability truth and product readiness are not represented consistently.

**Likely consumers:** View Editor, App Builder styling, CSS Token Editor, and Customization Studio.

---

### 17. CSS Token Editor

**Manifest:** `CustomizationStudio/DesignSystem/DesignTokenEditor/manifest.php`  
**Status:** governed draft, non-placeholder, mutating  
**Primary service:** `CssTokenEditorSaveService`, with preview, verify, save, and source-snapshot controller flows.

**Current capabilities**

- load theme token targets;
- preview token changes;
- verify proposed changes;
- save token changes; and
- create source snapshots.

**Capability readiness:** medium for external composition.

**Main issues**

- most operations are exposed as controller methods;
- mutation does not require approval;
- diff and rollback are not supported;
- target-owner artifact semantics need to be explicit before App Builder can compose this safely.

---

## Non-Operational and Transitional Catalog

These manifests express future product demand but must not be treated as available capabilities.

### Planned placeholders

- App Builder;
- Module Builder;
- DB Schema Tool;
- View Editor;
- Form Builder;
- Widget Builder;
- Plugin Builder;
- Workflow Designer;
- Rule Designer;
- Route Designer;
- Permission Profile Tool;
- Notification Template Designer;
- Integration Designer;
- Import/Export Mapper;
- Package Tool;
- CSS Selector Tool; and
- Change Control Center.

Their manifests often declare intended mutation, diff, snapshot, rollback, target types, and approval requirements. These declarations describe the target lifecycle only; the registered service remains the shared placeholder controller.

### Replaced or compatibility entries

- `ReportBuilder` is replaced by `ReportDesigner` and should not become a second report-authoring capability.
- legacy navigation and Style Compliance routes remain compatibility redirects and should not regain independent logic.

---

## Capability Family Map

| Capability family | Existing implementations | Readiness | Main action |
|---|---|---:|---|
| Owner discovery | Repository Scanner, Owner Structure Scan, Resource Explorer | High but duplicated | Consolidate first |
| Resource/entity discovery | Repository Scanner, Resource Explorer | High but schema-divergent | Define shared records/adapters |
| Reference/dependency discovery | Owner Structure Scan, Token Impact Explorer, Style Compliance | High within domains | Extract bounded domain-neutral primitives where valid |
| Contract diagnosis | Owner Structure Scan, Validation Center, Theme Doctor | Medium-high | Normalize result contract after pilots |
| Locale discovery | Localization Studio, Inline Localization Correction | High but overlapping | Compose around canonical owner/resource targets |
| Safe text-resource mutation | Localization Studio, Label Designer, Engineering Workspaces | Medium and inconsistent | Prove one shared guarded mutation lifecycle |
| Snapshot/rollback | Engineering Workspaces, Localization Studio, Label Designer, Audit History | Fragmented | Consolidate after mutation pilot |
| Style diagnosis/planning | Style Compliance, Token Impact Explorer, Theme Doctor | High read-only | Register direct capabilities and compose |
| Style mutation | CSS Token Editor; disabled Style Compliance repair | Partial | Do not generalize until lifecycle parity exists |
| Report source discovery | Report Designer | Transitional | Replace heuristics with owner-published providers |
| Definition validation/storage | Report Designer and Platform Reports | Medium-high | Expose typed capability boundary |
| Governance/approval | StudioGovernanceService, Engineering Workspaces | Broad but uneven | Split bounded policy and decision capabilities |
| Runtime verification | Label dry run, report validation, token verify, post-apply locale diagnostics | Domain-specific | Establish shared verification semantics later |

---

## Portfolio Findings

### F1 — Capabilities exist, but their ownership boundary is usually the tool

Studio already contains many reusable static services. The primary problem is not absence of logic; it is that the logic is organized and declared as tool-private implementation rather than as a Studio capability portfolio.

### F2 — Owner discovery authority is canonical, but compatibility projections still diverge

`StudioOwnerDiscoveryService::discover()` is now the canonical owner authority.

`RepoTreeScannerService::discoverOwners()` and `OwnerStructureOwnerDiscoveryService::discover()/resolve()` now consume that authority through different compatibility projections.

`RepoTreeScannerService::discoverOwners()` compatibility projection exposes:

- apps;
- app modules;
- plugins;
- Platform;
- engineering workspaces; and
- owner hierarchy metadata.

`OwnerStructureOwnerDiscoveryService::discover()/resolve()` compatibility projection exposes selection resolution, key sanitization, and default-owner behavior for the Owner Structure workflow.

The implementations differ in:

- coverage;
- owner-key conventions;
- output schema;
- sorting/default behavior;
- caching;
- hierarchy representation; and
- owner-type classification.

This remains a consolidation opportunity, but canonical owner discovery itself is no longer pending work.

### F3 — Manifest `services` is not yet a capability registry

Several mature tools list `StudioController` methods as services:

- Label Designer;
- Report Designer;
- Theme Doctor;
- Token Impact Explorer;
- CSS Token Editor; and
- parts of Style Compliance and CSS Live Editor.

A controller method describes a page adapter, not an independently reusable capability.

### F4 — Mutation lifecycle truth is inconsistent

The strongest complete declaration is Engineering Workspaces: approval, owner write, diff, snapshot, and rollback are all enabled.

Other modifying tools have gaps:

- Owner Structure Scan modifies but declares no approval, diff, snapshot, or rollback;
- Label Designer writes owner artifacts without approval and without rollback;
- Localization Studio writes owner artifacts without approval and claims rollback without an explicit inspected restore operation;
- Inline Localization Correction modifies without manifest diff or rollback;
- Report Designer writes owner artifacts without approval, diff, or rollback; and
- CSS Token Editor writes owner artifacts without approval, diff, or rollback.

This does not mean those tools are unusable. It means their operations cannot yet be safely composed by a larger builder as one uniform lifecycle.

### F5 — Localization already proves composition is possible

`LocalizationScanService` imports and reuses `LocalizationStudioEditService`. This is direct evidence that a smaller tool capability can serve a larger workflow.

The next step is to make that relationship explicit through structured owner/resource inputs and capability declarations rather than path-level service coupling.

### F6 — Planned manifests are demand signals, not capability evidence

App Builder, Module Builder, DB Schema Tool, View Editor, and other placeholders declare intended effects and safety features. They must not implement private versions of these operations when development begins.

Their role is to consume the certified discovery, validation, planning, mutation, snapshot, rollback, and verification capabilities extracted from existing tools.

### F7 — The central controller remains a major composition bottleneck

`StudioController` imports and directly orchestrates a large number of tool services. This creates three risks:

- capability invocation remains tied to HTTP/page flows;
- larger builders may call controller methods or duplicate orchestration; and
- failures and result shapes remain tool-specific.

The controller should become thinner incrementally as capabilities are extracted. A large controller rewrite is not required.

### F8 — Result contracts are fragmented

Current tools return different combinations of:

- `summary`;
- `findings`;
- `diagnostics`;
- `scan_ok` or status fields;
- proposed changes;
- readiness;
- snapshots;
- changed artifacts; and
- verification results.

Do not standardize all results before the first two pilots. Use pilot evidence to define the minimum common result contract.

### F9 — Snapshot storage and rollback semantics are fragmented

Examples include:

- engineering-workspace provisioning snapshots;
- locale `.backups` beside source files;
- Label Designer snapshots under Studio storage;
- audit and publish decision storage; and
- token source snapshots.

A shared snapshot capability should follow, not precede, the narrow mutation pilot.

### F10 — Existing lifecycle gates are deep but not portfolio-wide

The Studio lifecycle gate deeply certifies selected Theme Doctor and Style Compliance behavior. Equivalent invariants are not yet applied uniformly to every active capability and mutating path.

A future gate should compare declared effects with observable services and routes, but that is not the first extraction slice.

---

## Ranked Extraction Candidates

### Priority 1 — Deletion-family aggregate certification in the top-level architecture runner

**Why first**

- all 14 canonical deletion capabilities already exist with dedicated capability gates;
- all 14 OwnerStructure deletion workspace adapters already exist with dedicated workspace gates;
- these gates were historically outside the aggregate architecture runner and needed portfolio-level certification;
- this is read-only governance integration with no runtime behavior change; and
- it certifies existing deletion-family truth before any new deletion workflow slice.

**Existing gate suites to aggregate**

- `scripts/architecture/check_studio_deletion_*_capability.sh`; and
- `scripts/architecture/check_owner_structure_deletion_*_workspace.sh`.

**Required behavior after aggregation**

- every existing deletion stage gate remains unchanged;
- aggregate execution fails if any underlying deletion gate fails; and
- failure output points to the exact stage gate that failed.

### Priority 2 — Reference and Dependency Discovery Capability

Start with Owner Structure Scan reference discovery. Preserve domain-specific analyzers such as token impact, but establish common target, evidence, and reference-record semantics.

**Likely consumers:** rename, migration, deletion, App Builder collision analysis, Module Builder, and Change Control Center.

### Priority 3 — Narrow Owner Text-Resource Mutation Capability

Use locale-file editing as the pilot because it already has:

- path guards;
- validation;
- atomic temporary-file replacement;
- snapshot creation; and
- post-apply diagnostics.

The pilot must add explicit lifecycle context, structured target identity, diff, approval decision input, verification, and restore behavior without broadening to arbitrary files.

### Priority 4 — Minimum Capability Result Contract

Derive the contract from the owner discovery and locale mutation pilots. Do not design a universal result model before those pilots exist.

Minimum likely fields:

```text
status
summary
diagnostics
findings
proposed_changes
changed_artifacts
next_action
snapshot_reference
verification
provenance
```

### Priority 5 — Manifest and Contract Validation Capability

Extract reusable validation needed by App Builder and Module Builder:

- owner-key validity;
- manifest validity;
- route ownership;
- required locale resources;
- lifecycle declarations; and
- collision checks.

### Priority 6 — Runtime Consumption Verification

Unify the concept of verifying that an applied owner artifact is registered, reachable, consumed, and operational while retaining domain-specific validators.

### Priority 7 — Report Source Provider Capability

Replace Report Designer's temporary schema inference with business-owner-published source providers that can be consumed by Report Designer and App Builder.

---

## Next Achievable Slice

# Slice 3 — OwnerStructure deletion manifest/lifecycle truth alignment

## Goal

Align OwnerStructure deletion manifest lifecycle flags and declared mutation capabilities with the now-certified staged deletion checkpoints.

## Scope

The alignment slice must:

- preserve the certified deletion-family aggregate gate;
- keep all 28 underlying deletion stage gates unchanged;
- reconcile declared lifecycle flags with actual staged POST checkpoints; and
- avoid route/UI/controller/service/runtime behavior changes.

## Compatibility requirements

- all 28 deletion stage gates remain preserved and independently runnable;
- no route or UI changes;
- no change to owner filesystem conventions;
- no new mutation;
- no runtime dependency on Studio; and
- no duplication of stage-level assertions.

## Suggested capability boundary

Use manifest/lifecycle declaration updates only, with no new capability framework and no runtime behavior expansion.

## Validation

Validate:

- shell syntax of changed scripts;
- all 14 canonical deletion capability gates;
- all 14 owner-structure deletion workspace gates;
- the deletion-family aggregate gate; and
- the top-level architecture runner.

## Definition of done

- all 28 deletion stage gates remain individually runnable and unchanged;
- aggregate architecture runner keeps deletion-family certification intact;
- owner deletion execution remains unimplemented;
- reference-remediation patch apply remains unimplemented; and
- architecture gates pass.

---

## Following Slices

1. **Slice 2:** deletion-family aggregate gate integration (completed).
2. **Slice 3:** owner-structure manifest/lifecycle truth alignment for deletion-stage mutation checkpoints.
3. **Slice 4:** canonical deletion execution capability planning (contract-only, no execution route).
4. **Slice 5:** reference-remediation patch apply planning (contract-only, no apply route).
5. **Slice 6:** adapter consolidation review for thin owner-structure pass-through layers.

---

## Decision

Do not begin owner deletion execution or reference-remediation patch apply implementation yet.

Deletion-family aggregate certification is complete. Next, align lifecycle declarations before deciding whether execution/apply should be introduced as new explicit capabilities.
