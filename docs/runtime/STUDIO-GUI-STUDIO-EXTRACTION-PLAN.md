# STUDIO GUI Studio Extraction Plan (Docs Only)

## Purpose

This plan defines a safe, phased extraction path for splitting [apps/Studio/Views/gui_studio.php](apps/Studio/Views/gui_studio.php) into Studio-owned partials.

This is a docs-only checkpoint. No runtime behavior, routing, controllers, localization, backend wiring, or ownership boundaries are changed here.

## Why Extraction Is Needed

1. [apps/Studio/Views/gui_studio.php](apps/Studio/Views/gui_studio.php) has grown large enough that local changes are harder to review and regression risk is rising.
2. The visual shell and workbench zones are now stable enough (checkpoint commit 705731fe) to split markup into safer, behavior-preserving units.
3. The extraction goal is risk reduction and maintainability, not new features. Every phase must preserve current behavior.

## Non-Negotiable Extraction Rules

1. Extract one partial at a time.
2. No behavior change: markup movement only.
3. Preserve existing selectors, IDs, classes, and data attributes exactly.
4. Preserve all JS hook surfaces exactly (data-gs-*, ids, role/aria wiring, event targets).
5. Preserve route/controller behavior exactly.
6. No Core, Shell, or business app/module changes.
7. No localization restart in extraction phases.
8. No backend wiring in extraction phases.
9. Keep Studio ownership explicit: Studio remains a governed worker, not business resource owner.

## Proposed Target Structure

Target folder:

apps/Studio/Views/partials/

Proposed partial files:

- studio_header.php
- loaded_resource_workbench.php
- tool_navigation.php
- library_explorer.php
- editor_workbench_shell.php
- validation_preview_zone.php
- governance_apply_zone.php
- workflow_status.php
- mode_panel.php
- tool_preview_panel.php
- apply_center.php
- rollback_panel.php

## First Safe Extraction Order

1. tool_navigation.php
2. loaded_resource_workbench.php
3. editor_workbench_shell.php
4. validation_preview_zone.php
5. governance_apply_zone.php
6. library_explorer.php
7. workflow_status.php and mode_panel.php
8. apply_center.php and rollback_panel.php

## Phase Plan

### Phase 1: tool_navigation.php

- Owner: Studio
- Source file region: top-level tab/navigation and mode/tier controls in [apps/Studio/Views/gui_studio.php](apps/Studio/Views/gui_studio.php)
- Preserve selectors/data attributes:
  - .studio-tabs
  - [data-tab]
  - #gs-tier-switcher
  - [data-tier-btn]
- Validation commands:
  - php -l apps/Studio/Views/gui_studio.php
  - bash scripts/architecture/run_architecture_gates.sh
  - bash scripts/system/check_deployment_readiness.sh
  - git diff --check
  - git status --short
- Browser routes to check:
  - /apps/studio
  - /apps/studio/library
  - /apps/studio/history
  - /apps/studio/library/module?app_key=inventory_app&module_key=parts_master
- Rollback strategy:
  - Revert only the partial include and moved markup in the same commit scope.
  - Keep selector/attribute inventory as pre/post diff checklist.

### Phase 2: loaded_resource_workbench.php

- Owner: Studio
- Source file region: Loaded Resource Identity section and clear loaded context controls in [apps/Studio/Views/gui_studio.php](apps/Studio/Views/gui_studio.php)
- Preserve selectors/data attributes:
  - [data-gs-loaded-identity-panel]
  - [data-gs-loaded-resource-key]
  - [data-gs-loaded-mode]
  - [data-gs-loaded-owner-app]
  - [data-gs-loaded-module]
  - [data-gs-loaded-resource-type]
  - [data-gs-loaded-source-path]
  - [data-gs-clear-loaded-context]
  - [data-gs-loaded-context]
- Validation commands: same command set as Phase 1
- Browser routes to check: same route set as Phase 1
- Rollback strategy:
  - Revert partial extraction for loaded-resource block only.
  - Confirm clear loaded context still functions exactly after revert.

### Phase 3: editor_workbench_shell.php

- Owner: Studio
- Source file region: main workbench body wrapper and side-stack scaffolding in [apps/Studio/Views/gui_studio.php](apps/Studio/Views/gui_studio.php)
- Preserve selectors/data attributes:
  - .gs-workbench-body
  - [data-gs-workbench-body]
  - [data-gs-workbench-load-state]
  - .gs-workbench-zones
  - .gs-workbench-side-stack
- Validation commands: same command set as Phase 1
- Browser routes to check: same route set as Phase 1
- Rollback strategy:
  - Revert shell wrapper extraction only.
  - Verify no structural break in Edit/Analyze/Changes/Apply tab rendering.

### Phase 4: validation_preview_zone.php

- Owner: Studio
- Source file region: Preview / Validation lane in [apps/Studio/Views/gui_studio.php](apps/Studio/Views/gui_studio.php)
- Preserve selectors/data attributes:
  - [data-gs-workbench-zone="preview"]
  - [data-gs-studio-tool-preview]
  - data-gs-tool-preview-* fields
  - .gs-preview-zone-stack
  - [data-gs-workbench-context]
  - data-gs-bridge-* fields
- Validation commands: same command set as Phase 1
- Browser routes to check: same route set as Phase 1
- Rollback strategy:
  - Revert preview zone partial only.
  - Confirm tool preview panel and context bridge updates still bind.

### Phase 5: governance_apply_zone.php

- Owner: Studio
- Source file region: Governance / Apply lane container in [apps/Studio/Views/gui_studio.php](apps/Studio/Views/gui_studio.php)
- Preserve selectors/data attributes:
  - [data-gs-workbench-zone="governance"]
  - .gs-governance-lanes
  - .gs-governance-lane-stack
  - .gs-governance-readiness-list
- Validation commands: same command set as Phase 1
- Browser routes to check: same route set as Phase 1
- Rollback strategy:
  - Revert governance lane partial only.
  - Verify apply-readiness placeholders remain visual-only and unchanged.

### Phase 6: library_explorer.php

- Owner: Studio
- Source file region: library explorer panels and list controls in [apps/Studio/Views/gui_studio.php](apps/Studio/Views/gui_studio.php)
- Preserve selectors/data attributes:
  - #gs-library-search
  - [data-library-filter]
  - [data-library-kind]
  - [data-node-id]
  - [data-load-node]
  - [data-library-detail-close]
- Validation commands: same command set as Phase 1
- Browser routes to check: same route set as Phase 1
- Rollback strategy:
  - Revert library partial extraction only.
  - Confirm load into Studio and inspect flows remain unchanged.

### Phase 7: workflow_status.php and mode_panel.php

- Owner: Studio
- Source file region: workflow status and mode panel blocks in [apps/Studio/Views/gui_studio.php](apps/Studio/Views/gui_studio.php)
- Preserve selectors/data attributes:
  - [data-gs-workflow-status-panel]
  - [data-gs-workflow-analyze]
  - [data-gs-workflow-changes]
  - [data-gs-workflow-preview]
  - [data-gs-workflow-approval]
  - [data-gs-workflow-apply]
  - [data-gs-mode-panel]
  - [data-gs-mode-chip]
  - [data-gs-mode-state]
- Validation commands: same command set as Phase 1
- Browser routes to check: same route set as Phase 1
- Rollback strategy:
  - Revert one panel at a time if needed.
  - Re-check JS-driven status/mode updates after each revert.

### Phase 8: apply_center.php and rollback_panel.php

- Owner: Studio
- Source file region: apply and rollback rendering blocks in [apps/Studio/Views/gui_studio.php](apps/Studio/Views/gui_studio.php)
- Preserve selectors/data attributes:
  - #gs-apply-form
  - apply and rollback action/status identifiers currently consumed by JS
  - route-link/nav-link panel selectors under Studio zone controls
- Validation commands: same command set as Phase 1
- Browser routes to check: same route set as Phase 1
- Rollback strategy:
  - Revert apply/rollback partial extraction together if coupling causes risk.
  - Confirm no change in governed apply/rollback behavior after revert.

## Explicitly Out Of Scope During Extraction

1. Do not create Studio Tool backend modules yet.
2. Do not split JS until markup partials are stable.
3. Do not move CSS to shared Shell/Core ownership.
4. Do not create hidden resource ownership paths.
5. Do not make tools own business resources.
6. Do not resume localization migration during this extraction track.
7. Do not add backend wiring while markup is being extracted.

## Validation Baseline For Every Extraction Commit

Run all commands below in every extraction phase commit:

- bash scripts/architecture/run_architecture_gates.sh
- bash scripts/system/check_deployment_readiness.sh
- git diff --check
- git status --short

Also run route checks in browser (authenticated):

- /apps/studio
- /apps/studio/library
- /apps/studio/history
- /apps/studio/library/module?app_key=inventory_app&module_key=parts_master

## Future Direction After Partial Extraction Stabilizes

1. Consider Studio tool-level ownership under apps/Studio/Tools/* only after markup partial extraction is complete and stable.
2. Keep tools as governed workers that inspect/prepare/apply through controlled workflows.
3. Do not transfer business resource ownership to Studio tools.
4. Keep backend wiring and localization as separate, explicitly approved tracks after extraction stability.
