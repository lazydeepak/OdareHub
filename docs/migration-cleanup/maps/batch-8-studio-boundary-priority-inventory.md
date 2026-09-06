# Batch 8 Studio Boundary Priority Inventory

Status: executed as read-only diagnostic and documentation only.

No files were moved, deleted, archived, or mutated. Runtime behavior and Core
behavior were not changed.

## Architecture Basis

- Studio is a governed builder tool, not the owner of runtime truth.
- Studio may create and manage app/module/view/component lifecycle artifacts,
  but owner/runtime meaning stays with the owner layer.
- Transitional hooks and bridges are allowed while ownership stabilizes.
- Cleanup and migration must wait until folder ownership, runtime-adjacent
  boundaries, and compatibility shims are explicitly classified.

## Diagnostic Script

- script: `scripts/architecture/check_studio_boundary_priority_inventory.sh`
- mode: read-only diagnostic
- current result: pass

## Direct Answers

1. Does Studio remain optional in lifecycle terms?
   - Yes. `apps/Studio/manifest.json` declares `can_disable: true` and
     `can_uninstall: true`, and `apps/Studio/bootstrap.php` is intentionally
     empty in this phase.

2. Which current Studio folders are true Studio builder infrastructure?
   - `Controllers`
   - `Routes`
   - `Services`
   - `Views`
   - `Tools`
   - `Authorization`
   - `Workflow`
   - `assets`
   - `lang`

3. Which current Studio folders are runtime-adjacent or owner-misaligned?
   - `ActionHandlers` with order-specific handlers
   - `Adapters` with ambiguous business/runtime binding
   - `Analytics`
   - `DataProviders`
   - `Repositories`
   - `Templates`

4. Which legacy Studio bridges still exist outside `apps/Studio`?
   - `/ops/gui-studio` remains routed from Platform as a compatibility bridge.
   - `/ops/design-studio` and its edit/update/status views remain owned by the
     legacy Platform/Base composition surface.

5. Which Studio services are still too large or too mixed to treat as final?
   - `GuiStudioService`
   - `StudioRuntimeBindingService`
   - `StudioGovernanceService`
   - `StudioViewIntrospectionService`
   - `StudioDependencyGraphService`
   - `StudioNavLinkingService`
   - `StudioRouteLinkingService`
   - `StudioDataContractService`

6. Which current view files are extraction candidates?
   - `Views/gui_studio.php`
   - `Views/partials/library_explorer.php`
   - `Views/partials/loaded_resource_workbench.php`
   - `Views/partials/editor_workbench_shell.php`
   - `Views/partials/apply_center.php`
   - `Views/partials/governance_apply_zone.php`
   - `Views/partials/governance_diagnostics_panel.php`
   - `Views/partials/workflow_status.php`
   - `Views/partials/mode_panel.php`
   - `Views/partials/tool_navigation.php`

7. Which tool folders represent governed builder capabilities?
   - `AppBuilder`
   - `ModuleBuilder`
   - `ViewEditor`
   - `NavMenuTool`
   - `WidgetBuilder`
   - `ReportBuilder`
   - `CssSelectorTool`
   - `DbSchemaTool`
   - `PermissionProfileTool`
   - `ThemeTool`
   - `ValidationCenter`
   - `AuditHistory`
   - `PackageTool`
   - `ResourceExplorer`

8. Which generated-app lifecycle artifacts must remain governed?
   - `storage/appstudio/apps_registry.json`
   - `storage/appstudio/generated_data`
   - `storage/appstudio/snapshots`
   - `storage/appstudio/applies`
   - `storage/appstudio/audit`
   - `storage/appstudio/packages`
   - `storage/appstudio/publish_decisions`
   - generated code under `apps/Generated`
   - published delivery output under `public/assets/apps`

## Folder Ownership Inventory

| Folder | Decision | Why |
|---|---|---|
| `Controllers` | KEEP | Canonical HTTP entry points for Studio surfaces. |
| `Routes` | KEEP_COMPAT | Route glue is still transitional, especially `gui_studio_routes.php`. |
| `Services` | SPLIT | Currently mixed across governance, registry, execution, and runtime binding. |
| `Views` | KEEP_RESTRUCTURE | Monolithic UI logic still needs partial extraction and shell slimming. |
| `Tools` | KEEP_RESTRUCTURE | Governed tool manifests are correct, but the structure is still broad. |
| `Authorization` | KEEP | Studio access rules and permission matrix belong here for now. |
| `Workflow` | KEEP_RESTRUCTURE | Workflow is valid, but overlaps with execution/governance boundaries. |
| `assets` | KEEP | Studio-owned static assets remain under Studio ownership. |
| `lang` | KEEP | Localization resources remain Studio-owned. |
| `ActionHandlers` | INVESTIGATE | Contains business-specific handlers that likely belong to owning apps. |
| `Adapters` | INVESTIGATE | Ownership is ambiguous; some adapters look runtime/business-facing. |
| `Analytics` | MIGRATE_TO_OWNER | Analytics and KPI logic belong to the owning business surface. |
| `DataProviders` | MIGRATE_TO_OWNER | Sample/demo/business data providers are not Studio ownership truth. |
| `Repositories` | MIGRATE_TO_OWNER | Business CRUD and schema ownership should move to the owning app/module. |
| `Templates` | KEEP_RESTRUCTURE | Useful as Studio blueprints, but should not become runtime source truth. |

## Runtime-Adjacent Studio Areas

| Area | Classification | Owner boundary |
|---|---|---|
| Generated app lifecycle | Studio-owned builder action | Studio may generate/preview/apply/snapshot/rollback/publish/validate. |
| `apps/Generated` writes | Generated artifact target | Owner runtime truth remains with the generated app and registry. |
| `storage/appstudio` writes | Platform-governed builder evidence | Registry, snapshot, apply, audit, and publish records stay governed. |
| `public/assets/apps` publishing | Runtime delivery output | Delivery output only, not hand-edited source truth. |
| Route/nav/menu generation | Runtime compatibility bridge | Must remain tied to owner contracts and registry checks. |
| DB/menu interaction | Platform/runtime compatibility bridge | Requires separate source-truth classification before cleanup. |
| Business-specific handlers | Business-owner responsibility | Handlers for order/data lifecycle belong to the owner surface. |

## Legacy /ops Bridge Map

| Bridge | Classification | Why |
|---|---|---|
| `/ops/gui-studio` | Entry-only compatibility bridge | It forwards into the new Studio surface and preserves legacy access. |
| `apps/Platform/routes.php` Studio hook registration | Entry-only compatibility bridge | It conditionally exposes Studio when installed/enabled. |
| `/ops/design-studio` | Render-owning legacy bridge | It still owns a legacy composition surface outside `apps/Studio`. |
| `plugins/Base/Views/ops/design_studio.php` | Render-owning legacy bridge | It renders the legacy design-studio listing surface. |
| `plugins/Base/Views/ops/design_studio_edit.php` | Render-owning legacy bridge | It renders the legacy edit surface and mutating forms. |
| `apps/Studio/Routes/gui_studio_routes.php` | Transitional Studio bridge | It still hosts compatibility route logic and large shared workflow handlers. |

## Service Restructuring Candidates

| Service | Proposed group | Reason |
|---|---|---|
| `GuiStudioService` | Execution / Generation / Publishing / Snapshot | Monolithic core currently mixes many lifecycle stages. |
| `StudioGovernanceService` | Governance / Validation | Contains govern/apply/rollback related orchestration. |
| `StudioExperienceGovernanceService` | Governance | Experience shaping and access policy governance. |
| `AppStudioRegistryService` | Registry | Registry source-of-truth and enablement metadata. |
| `StudioGovernedToolRegistryService` | Registry | Tool manifest discovery and governed registration. |
| `StudioResourceTypeRegistryService` | Registry | Resource classification and lookup. |
| `StudioNavCandidateProviderService` | Composition | Candidate discovery for navigation composition. |
| `StudioNavLinkingService` | Composition | Navigation linking and generated nav wiring. |
| `StudioRouteLinkingService` | Composition | Route linking and generated route wiring. |
| `HostSurfaceContributionService` | Composition | Host surface hook contribution and entry-point surfacing. |
| `StudioViewIntrospectionService` | Analysis | View/layout inspection and contract reading. |
| `StudioDependencyGraphService` | Analysis | Dependency graph computation belongs in analysis. |
| `StudioDataContractService` | Analysis | Data contract validation and shape discovery. |
| `StudioRuntimeBindingService` | Execution | Runtime binding of handlers/adapters/data providers. |
| `StudioNotificationService` | Governance / Execution | Cross-cutting notification/reporting helper. |

## Views Extraction Candidates

| Current file | Target role | Notes |
|---|---|---|
| `Views/gui_studio.php` | Page shell only | Should become a thin host shell once partials own the UI. |
| `Views/partials/library_explorer.php` | Library panel | Needs one focused library panel boundary. |
| `Views/partials/loaded_resource_workbench.php` | Editor panel | Own the loaded resource editor surface. |
| `Views/partials/editor_workbench_shell.php` | Editor panel shell | Should stay as shell chrome only. |
| `Views/partials/workflow_status.php` | Analyze/status panel | Should present workflow state only. |
| `Views/partials/mode_panel.php` | Changes/mode panel | Should own the create/edit/upgrade mode strip. |
| `Views/partials/apply_center.php` | Apply panel | Should become apply/rollback orchestration UI. |
| `Views/partials/governance_apply_zone.php` | Apply panel | Should be merged into governed apply surface. |
| `Views/partials/governance_diagnostics_panel.php` | Analyze panel | Should remain diagnostics-only. |
| `Views/partials/tool_navigation.php` | Tool card rail | Should own the tool navigation layout. |

## Tool Boundary

| Folder | Boundary | Future shape |
|---|---|---|
| `Tools/*` | Governed builder capabilities | Each tool manifest represents an editing capability, not runtime truth. |
| `Tools/AppBuilder` | App creation | Generate app skeletons and manifests. |
| `Tools/ModuleBuilder` | Module creation | Generate module artifacts and ownership metadata. |
| `Tools/ViewEditor` | View editing | Operates on owner-owned view/layout artifacts. |
| `Tools/NavMenuTool` | Navigation editing | Edits owner navigation contributions through governance. |
| `Tools/WidgetBuilder` | Widget creation | Generates widget/card artifacts. |
| `Tools/ThemeTool` | Theme/CSS editing | Works through owner CSS handover, not global runtime truth. |
| `Tools/DbSchemaTool` | Schema editing | Must remain governed and validation-first. |

## Generated App Lifecycle Boundary

| Capability | Studio may | Studio must not |
|---|---|---|
| Generate | Create owner artifacts and generated app packages | Treat generated output as permanent runtime truth without registry evidence. |
| Preview | Show proposed changes and derived output | Bypass validation or approval gates. |
| Apply | Write governed artifacts to owner targets | Skip handover or provenance. |
| Snapshot | Record change evidence | Skip snapshot/rollback records. |
| Rollback | Restore through governance records | Directly mutate runtime without evidence. |
| Publish | Register publish decisions and delivery output | Publish without registry/policy checks. |
| Validate | Compare runtime/output against contracts | Approve unclassified or owner-misaligned output. |

## Priority Outcome

1. Keep the transitional hooks.
2. Finish runtime-adjacent separation.
3. Split services by concern.
4. Extract the remaining Studio view shell.
5. Then move cleanup/migration into the stable ownership boundary.
