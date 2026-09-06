# Apply Tab Migration Report

## Scope
- Task: Studio UI Migration Step 4 (Apply Tab)
- File updated: plugins/Base/Views/ops/gui_studio.php
- Objective: Move execution and approval flow into Apply tab, enforce gates, and remove duplicate apply trigger outside tabs.

## What Was Implemented

### 1. Apply tab now owns approval + execution controls
- Added a full Apply tab section with:
  - approval reason textarea
  - confirmation checkbox
  - approval decision metadata
  - risk acknowledgment and migration override metadata
- Added Apply-only execution trigger:
  - button id: btn-apply
  - class includes: primary
  - endpoint: /ops/gui-studio/apply-snapshot

### 2. Pipeline status panel in Apply tab
- Added visible gate status indicators:
  - Compile
  - Analyze
  - Impact
  - Risk Level
- Status values render in chips (OK/Pending and risk level).

### 3. Gate enforcement before apply
- Added client-side gate enforcement for Apply submit:
  - fails when Analyze is not complete
  - fails when confirmation checkbox is not checked
  - fails when reason is empty
- Validation errors are rendered in an Apply-tab warning block.

### 4. Navigation
- Added Apply-tab back navigation button:
  - id: btn-back-changes
  - switches back to Changes tab

### 5. Legacy duplicate apply trigger neutralized
- In hidden legacy section, removed active apply-snapshot trigger and replaced with disabled button.
- Result: active apply submission now exists only in tabbed Apply flow.

## Localization
- Added new Apply-tab keys for en/ja/ne:
  - apply_tab_title, apply_tab_subtitle
  - apply_pipeline_* keys
  - apply_gate_* keys
  - apply_changes_btn, apply_no_data
  - pipeline_risk_medium
- New UI text is routed through localization helper usage.

## Validation Per Requirement

1. Apply works ONLY from Apply tab
- Verified: visible Apply Changes trigger exists in Apply tab flow.
- Verified: duplicate legacy apply trigger is disabled.

2. Apply blocked if analyze missing
- Verified by forcing analyze gate false in browser and clicking Apply:
  - error shown: Analyze has not been completed.

3. Apply blocked without confirmation
- Verified by submitting with reason filled but confirmation unchecked:
  - error shown: Confirmation is required before apply.

4. Apply blocked without reason
- Verified by clearing reason and clicking Apply:
  - form blocked (required reason enforcement).

5. Successful apply still works
- Verified submit path from Apply tab reaches apply endpoint:
  - request posted to /ops/gui-studio/apply-snapshot
  - backend continues handling publish/apply preconditions.

## Notes
- PHP syntax check passed after changes.
- Apply gate behavior is enforced in the tabbed UI where execution is now centralized.
