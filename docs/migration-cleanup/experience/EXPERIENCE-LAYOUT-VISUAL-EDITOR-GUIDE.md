# Experience Layout Visual Editor - Implementation Guide

## Current Status

This guide documents the existing Access Control Experience Layout editor implementation. It is now a transitional admin/user override mechanism, not the canonical long-term experience composer.

Current architecture is defined in [docs/experience-composition-architecture-plan.md](docs/experience-composition-architecture-plan.md):

```text
App/Module-owned capability catalog
  -> ACL authorization filter
  -> Workspace Profile role-experience shaping
  -> User-specific Experience Override
  -> Runtime renderer
```

Treat `me_dashboard_blocks`, `me_plugin_cards`, `display_surfaces`, and `operator_views` as compatibility fields until they are migrated into canonical profile/override artifacts. New composer/editor workflows belong in Studio, and layout edits must never grant permissions or expose capabilities denied by ACL.

## Overview

The Experience Layout Composition System now includes a complete visual editor with drag-and-drop functionality, allowing administrators to customize each user's dashboard layout.

## Implementation Summary

### Phase 1: Dashboard Renderer ✅
**File**: `apps/Shell/Views/admin/dashboard.php`

The dashboard now respects the user's configuration stored in `me_dashboard_blocks`:
- `operational_summary` → Controls KPI + Flow + Timeline sections
- `primary_work_widgets` → Controls Modules section
- `plugin_dashboards_charts` → Controls Orders/Cockpit section

**Backwards Compatible**: If no config exists, all sections render (default behavior)

**Code Changes**:
- Added `$enabledDashboardBlocks` parsing in `context_prep.php` (flipped array for O(1) lookups)
- Added `$shouldRenderBlock()` helper in `dashboard.php`
- Wrapped 3 major sections with conditional checks

### Phase 2: Visual Grid Editor ✅
**File**: `plugins/Base/Views/ops/experience_layout.php`

Replaced the old checkbox-list UI with a modern visual editor:
- Responsive grid canvas using CSS Grid (matches dashboard layout)
- Draggable cards for each block with visual feedback
- Checkbox to enable/disable each block
- Disabled blocks shown with reduced opacity (50%)
- Drag handle indicator (⋮⋮) on each card

**Features**:
- Drag to reorder (position controls rendering order)
- Visual drop indicators during drag (line appears above/below)
- Checkbox prevents propagation of events
- Real-time update of hidden form inputs
- Preserves selected state when toggling between sections

### Phase 3: Edit/Preview Toggle ✅
**File**: `plugins/Base/Views/ops/experience_layout.php`

Two modes for users to work with configurations:

**Edit Mode** (default):
- Shows grid canvas with all available blocks
- Drag to reorder blocks
- Check/uncheck to enable/disable
- Real-time updates to hidden inputs

**Preview Mode**:
- Shows list of enabled blocks
- Displays what will render on user's dashboard
- Updates when edit mode changes
- Click "Preview" button to see before saving

## Testing Checklist

### Setup
1. Navigate to `/ops/access-control/detail?user_id=2&tab=experience`
2. Verify the form loads with the new grid editor UI
3. Check that both "Edit Mode" and "Preview" buttons are visible

### Test Case 1: Visual Editor Rendering
- [ ] Grid canvas shows all 10 dashboard blocks (Admin Dashboard Panels, Top Navigation, etc.)
- [ ] Each block displays as a card with drag handle, checkbox, and label
- [ ] All blocks are checked by default (enabled)
- [ ] Canvas uses responsive grid layout

### Test Case 2: Checkbox Toggle
- [ ] Uncheck a block → card becomes faded (opacity 50%)
- [ ] Check a block → card returns to full opacity
- [ ] Hidden input `me_dashboard_blocks_*` updates with only checked blocks
- [ ] Order is preserved when toggling checkboxes

### Test Case 3: Drag and Drop
- [ ] Click and hold on a block card (drag handle works)
- [ ] While dragging, visual indicator shows where block will be dropped
- [ ] Drop above/below other blocks to reorder
- [ ] After drop, blocks reorder smoothly
- [ ] Hidden input updates with new order
- [ ] Dragging still works after reordering multiple times

### Test Case 4: Preview Mode
- [ ] Click "Preview" button
- [ ] Preview section shows only ENABLED blocks
- [ ] Blocks are listed in order they will render
- [ ] List updates when you toggle back to Edit and uncheck blocks
- [ ] Preview is read-only (no interactions)

### Test Case 5: Save and Persistence
- [ ] Click "Save" button
- [ ] Form submits to `/ops/access-control/save-experience`
- [ ] Page reloads without errors
- [ ] Return to experience tab
- [ ] Configuration is preserved (same blocks selected/order)
- [ ] Navigate to `/me` (user dashboard)
- [ ] Dashboard only shows enabled blocks in configured order

### Test Case 6: Responsive Layout
- [ ] Resize browser window
- [ ] Grid canvas adapts to screen size (CSS Grid responsive)
- [ ] Cards remain visible and draggable at small sizes
- [ ] No layout breaks or overflow

### Test Case 7: Multiple Users
- [ ] Configure user_id=2 with one set of blocks
- [ ] Navigate to user_id=3 experience tab
- [ ] Different blocks are selected/ordered
- [ ] Each user's config is independent

## Data Structure

### Database Storage
```
user_dashboard_assignments table:
- me_dashboard_blocks: CSV string, e.g., "operational_summary,primary_work_widgets"
- me_plugin_cards: CSV string, e.g., "manufacturing_portal,daily_orders"
```

### PHP Service
```
UserDashboardAssignmentService:
- resolveMeDashboardBlocks($csv) → array of block keys
- resolveMePluginCards($csv) → array of card keys
- normalizeMeDashboardBlocksForAccountType() → filtered by role
- normalizeMePluginCardsForContext() → filtered by role/app
```

### View Context
```
AdminSurfaceComposer passes to view:
- $me_dashboard_blocks → array of enabled block keys
- $me_plugin_cards → array of enabled card keys
```

## Key Files Modified

```
apps/Shell/Views/admin/context_prep.php
├── Line 19: Parse me_dashboard_blocks to $enabledDashboardBlocks
└── Line 20: Parse me_plugin_cards to $enabledPluginCards

apps/Shell/Views/admin/dashboard.php
├── Line 9: Added $shouldRenderBlock() helper function
├── Line 28: Wrap hero/KPI/timeline with 'operational_summary' check
├── Line 461: Wrap modules with 'primary_work_widgets' check
└── Line 501: Wrap cockpit/orders with 'plugin_dashboards_charts' check

plugins/Base/Views/ops/experience_layout.php
├── Entire file: Replaced old checkbox UI with visual grid editor
├── CSS: Grid canvas styling, card styling, drag indicators
└── JS: Drag-and-drop logic, checkbox handling, mode toggle
```

## CSS Classes for Styling

```
.experience-grid-canvas        /* Main grid container */
.experience-block-card         /* Individual block card */
.experience-block-card.disabled /* When checkbox unchecked */
.experience-block-card.dragging /* While dragging */
.experience-block-card.drag-over-before /* Drop indicator (above) */
.experience-block-card.drag-over-after  /* Drop indicator (below) */
.block-drag-handle            /* Drag handle element (⋮⋮) */
.block-checkbox               /* Checkbox input */
.block-label                  /* Block name text */
```

## Common Issues and Solutions

### Issue: Blocks not rendering on dashboard
**Solution**: Verify `enabledDashboardBlocks` is populated in context_prep.php. Check that `me_dashboard_blocks` config in database is not empty.

### Issue: Drag-and-drop not working
**Solution**: Ensure `draggable="true"` is set on cards. Check browser console for JS errors. Verify event handlers are attached to correct elements.

### Issue: Hidden inputs not updating
**Solution**: Check `updateHiddenInputs()` function is called after drag/check operations. Verify HTML element IDs match between HTML and JS.

### Issue: Preview mode shows wrong blocks
**Solution**: Ensure `updatePreview()` is called when switching modes and when checking/unchecking blocks. Check that blockLabels is populated correctly from PHP.

## Future Enhancements

1. **Persistence**: Auto-save configuration without explicit save button
2. **Preview Enhancement**: Show actual dashboard preview using iframe or AJAX
3. **Block Descriptions**: Add tooltips explaining what each block does
4. **Templates**: Save/load preset configurations
5. **Undo/Redo**: Allow reverting changes
6. **Drag from Available Pool**: Add section of unselected blocks to drag from
7. **Mobile Touch Support**: Enhance touch events for mobile devices

## Browser Compatibility

- Chrome/Edge: Full support (CSS Grid + Drag API)
- Firefox: Full support
- Safari: Full support
- Mobile browsers: Basic support (limited drag-and-drop UX)

## Performance Considerations

- Grid rendering: O(n) where n = number of blocks (10-30 blocks)
- Drag operations: O(1) lookups using flipped array
- Hidden input updates: Occurs on drag end + checkbox change (not on every drag-over)
- Total blocks + cards: ~30 items, negligible performance impact

## Security Notes

- All block keys validated against ME_DASHBOARD_BLOCKS constant
- User role filtering applied during normalization
- CSRF token required for form submission
- Hidden inputs are read-only (no direct user modification)
