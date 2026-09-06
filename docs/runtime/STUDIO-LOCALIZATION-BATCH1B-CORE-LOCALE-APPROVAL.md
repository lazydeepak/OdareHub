# Studio Localization Batch 1b Core Locale Approval (Docs-Only)

Date: 2026-05-23
Status: Approval request prepared
Approval state: Pending explicit user approval
Scope: Documentation only. No runtime implementation.

## 1. Purpose

Request approval for a minimal Core-scope locale data update:

- Add 7 global locale keys to existing global dictionaries.
- Target keys are needed by Studio Batch 1 fallback wiring already completed in commit c550cf9f.

## 2. Proposed Files

The future approved implementation would touch only:

- app/Locale/en.php
- app/Locale/ja.php
- app/Locale/ne.php

## 3. Proposed Keys

Add the following keys under the existing ops.gui_studio namespace:

- ops.gui_studio.studio_tools_title
- ops.gui_studio.studio_tools_helper
- ops.gui_studio.loaded_identity_title
- ops.gui_studio.workflow_status_title
- ops.gui_studio.mode_panel_title
- ops.gui_studio.clear_loaded_context_action
- ops.gui_studio.clear_loaded_context_helper

## 4. Why Core Touch Is Justified

- Active runtime localization path is global (app/Locale dictionaries).
- No app-scoped Studio lang loader is part of the active helper/render path.
- This is a data-only locale expansion in existing global dictionaries.
- No helper, loader, platform contract, or architecture behavior change is proposed.

## 5. Risk Assessment

Risk level: Low, if implementation is limited to adding only these keys.

- No runtime behavior change expected because Studio view-side fallback is already in place.
- If global keys are absent or malformed, existing inline fallback still protects rendering.

## 6. Hard Limits For Future Approved Implementation

Implementation must obey all limits below:

- Only add the 7 specified locale keys.
- Do not change helpers.
- Do not change localization loader behavior.
- Do not change routes.
- Do not change controller or view behavior.
- Do not make unrelated locale edits.
- Do not change DB/migrations, permissions, or business behavior.

## 7. Validation Required For Future Approved Implementation

Run all checks after key addition:

```bash
/opt/homebrew/bin/php -l app/Locale/en.php
/opt/homebrew/bin/php -l app/Locale/ja.php
/opt/homebrew/bin/php -l app/Locale/ne.php
/opt/homebrew/bin/php -l apps/Studio/Views/gui_studio.php
/opt/homebrew/bin/php -l apps/Studio/Views/gui_studio_history.php
bash scripts/architecture/run_architecture_gates.sh
bash scripts/system/check_deployment_readiness.sh
git diff --check
```

Browser checks required:

- /apps/studio
- /apps/studio/library
- /apps/studio/history

## 8. Approval Status

Pending explicit user approval before any Core-scope locale file edit.

No Core files were modified in this checkpoint.
