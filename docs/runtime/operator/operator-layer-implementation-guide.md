# Operator Layer Implementation Guide

**Version:** 1.0  
**Last Updated:** May 2026  
**Status:** Production Ready (Phase 4 Complete)

---

## Overview

The **Operator Layer** (`/u/{operator}/*`) is a purpose-built workspace for manufacturing operators — distinct from the traditional admin/management layer (`/apps/*`). It provides streamlined, role-focused views for day-to-day operational tasks.

### Core Design Principles

1. **Data-Driven**: Views consume pure data via adapters; no UI embedding from modules
2. **Self-Contained**: All navigation stays within `/u/{operator}/*` namespace
3. **Responsive**: Works seamlessly on desktop, tablet, mobile
4. **Localized**: Full support for English, Japanese, Nepali (via `$this->tr()`)
5. **Accessible**: WCAG compliance; semantic HTML; keyboard navigation
6. **Real-Time**: Live KPI strips; graceful error handling; empty states

---

## Architecture

### Layer Stack

```
┌─────────────────────────────────────────┐
│  Operator Workspace (/u/{operator}/*)   │  ← Operator Layer
├─────────────────────────────────────────┤
│  OperatorLayerService (orchestrator)    │
├─────────────────────────────────────────┤
│  OperatorSurfaceComposer (renderer)     │
├─────────────────────────────────────────┤
│  OperatorLayerAdapters/* (data layer)   │  ← Pure data, no HTML
├─────────────────────────────────────────┤
│  App/Module Services (QcService, etc.)  │  ← Shared business logic
├─────────────────────────────────────────┤
│  Core Engine (/app/*) [LOCKED]          │  ← Never modified
└─────────────────────────────────────────┘
```

### Key Components

#### 1. **OperatorLayerService** (`apps/Shell/Services/OperatorLayerService.php`)
- **Role**: Request orchestrator for operator layer
- **Responsibility**: Route validation, auth check, context setup
- **Output**: Renders operator surface via `OperatorSurfaceComposer`

#### 2. **OperatorSurfaceComposer** (`apps/Shell/Composers/OperatorSurfaceComposer.php`)
- **Role**: Operator UI compositor
- **Responsibility**: 
  - Load all focus-specific data (calls adapters)
  - Build navigation, sidebar, header
  - Render HTML with conditional sections
  - Handle empty states and errors
- **Data**: Routes data via `buildContent()` → `renderHTML()`

#### 3. **OperatorLayerAdapters** (`apps/Shell/Services/OperatorLayerAdapters/`)
- **Role**: Pure data adapters (no HTML rendering)
- **Pattern**: Static `getData()` method returns array
- **Responsibility**: Query services, transform data, apply filters
- **Examples**:
  ```
  CoverageAdapter       → CoverageService data
  QcAdapter            → QcLeaderDashboardService::build()
  DispatchAdapter      → DispatchLeaderDashboardService::build()
  MachinesAdapter      → MachineLeaderDashboardService::build()
  AssemblyAdapter      → Direct mfg_assembly_entries queries
  MaterialsAdapter     → materials + material_ledger aggregation
  ```

#### 4. **OperatorLayerSidebarService** (`apps/Shell/Services/OperatorLayerSidebarService.php`)
- **Role**: Sidebar composition engine
- **Responsibility**: 
  - Build context-aware sidebar (My Work, Manufacturing, etc.)
  - Apply role-based visibility rules
  - Generate nav items with localized labels

#### 5. **Routes** (`apps/Shell/routes.php`)
- **Pattern**: `/u/{view}` routes normalize to `/u/{operator}/{view}`
- **Auth**: Session validation on every route
- **Redirect**: `/u/qc` → `/u/lazydeepak/qc` (username normalization)

---

## Route Map

### Primary Operator Views

| Route | Focus | Adapter | Purpose |
|-------|-------|---------|---------|
| `/u/dashboard` | `dashboard` | None | Operator home — KPIs, alerts, recent activity |
| `/u/work-entry` | `work-entry` | None | Time entry / work log |
| `/u/critical` | `critical` | None | Critical items requiring attention |
| `/u/recent` | `recent` | None | Recent activities and updates |

### Manufacturing Execution

| Route | Focus | Adapter | Purpose |
|-------|-------|---------|---------|
| `/u/production` | `production` | ProductionService | Production plan/entry view |
| `/u/processing` | `processing` | ProcessingService | Processing queue/status |
| `/u/preparation` | `preparation` | PreparationService | Preparation readiness |
| `/u/dispatch` | `dispatch` | DispatchService | Dispatch execution |
| `/u/dispatch/detail` | `dispatch-detail` | DispatchService | Dispatch detail/tracking |

### Phase 3: Coverage Management

| Route | Focus | Adapter | Purpose |
|-------|-------|---------|---------|
| `/u/coverage` | `coverage` | `CoverageAdapter` | Demand/supply coverage analysis |

### Phase 4: Tier 2 Manufacturing

| Route | Focus | Adapter | Purpose |
|-------|-------|---------|---------|
| `/u/qc` | `qc` | `QcAdapter` | Quality control status (urgent, pending, failed) |
| `/u/machines` | `machines` | `MachinesAdapter` | Machine workboard (running, delayed, queue) |
| `/u/assembly` | `assembly` | `AssemblyAdapter` | Assembly execution (today's entries, progress) |
| `/u/materials` | `materials` | `MaterialsAdapter` | Material management (low stock, critical shortage, orders) |

### Data Views

| Route | Focus | Purpose |
|-------|-------|---------|
| `/u/parts` | `parts` | Products/parts inventory lookup |
| `/u/parts/detail` | `parts-detail` | Part-specific detail view |

### Settings

| Route | Focus | Purpose |
|-------|-------|---------|
| `/u/account` | `account` | User profile, preferences, assignments |

---

## Data Flow

### Example: QC Operator Views `/u/qc`

```
1. HTTP GET /u/qc
   ↓
2. Route handler (routes.php)
   - Normalize username
   - Session check
   - Redirect to /u/{operator}/qc
   ↓
3. OperatorLayerService::render()
   - Build context: $context = ['focus' => 'qc', ...]
   - Instantiate OperatorSurfaceComposer
   ↓
4. OperatorSurfaceComposer::buildContent()
   - Call buildQcFocusData()
   ↓
5. QcAdapter::getData()
   - Call QcLeaderDashboardService::build(['date' => today])
   - Return array: ['kpi' => [...], 'run_now' => [...], ...]
   ↓
6. OperatorSurfaceComposer::renderHTML()
   - $isQcFocus = true
   - Render KPI strip, tables, error handling
   - Output full HTML page
```

### Adapter Contract

Every adapter must return an array with these keys:

```php
[
    'kpi'        => [...],        // KPI card values
    '{table1}'   => [...],        // Table 1 data
    '{table2}'   => [...],        // Table 2 data
    'empty'      => bool,         // No data for today
    'error'      => string,       // Error message if applicable
    'today'      => 'Y-m-d',      // Date context (optional)
]
```

**Example from QcAdapter:**
```php
return [
    'kpi' => [
        'urgent' => 5,
        'run_now' => 12,
        'pending_qc' => 8,
        'failed_recheck' => 2,
        'ready_dispatch' => 45,
        'overdue' => 1,
    ],
    'run_now' => [...],           // 20-item array
    'pending_qc' => [...],
    'failed_recheck' => [...],
    'ready_dispatch' => [...],
    'sla' => [...],
    'empty' => false,
    'error' => '',
    'today' => date('Y-m-d'),
];
```

---

## Localization

### Translation Strategy

All user-facing strings go through `$this->tr()`:

```php
// ✅ Correct
$this->tr('operator.qc.page_title', 'Quality Control')

// ❌ Incorrect
'Quality Control'  // Hardcoded
```

### Locale File Structure

**File**: `app/Locale/{en|ja|ne}.php`

**Namespace**: `operator.*`

```php
'operator.sidebar.qc' => 'Quality Control',
'operator.qc.page_title' => 'Quality Control',
'operator.qc.open_full' => 'Full QC Log',
'operator.qc.empty' => 'No QC entries found for today.',
'operator.qc.kpi.urgent' => 'Urgent',
'operator.qc.kpi.run_now' => 'Run Now',
// ... etc
```

### Current Language Support

- ✅ English (en.php)
- ✅ Japanese (ja.php)
- ✅ Nepali (ne.php)

---

## Styling & Responsive Design

### CSS Strategy

- **Theme tokens**: `var(--surface)`, `var(--border-soft)`, `var(--text-main)`, etc.
- **No local styles**: All styling via global `public/assets/app.css`
- **Responsive**: Mobile-first; breakpoints at 768px (tablet), 1024px (desktop)

### Key Classes

```css
.coverage-focus-section    /* Section container */
.coverage-kpi-strip        /* KPI card group */
.coverage-kpi-card         /* Individual KPI card */
.coverage-kpi-card--warn   /* Warning state (orange) */
.coverage-kpi-card--danger /* Danger state (red) */
.coverage-orders-table     /* Data table */
.coverage-orders-row       /* Table row */
```

### Mobile Responsiveness Checklist

- [ ] KPI cards stack vertically on mobile
- [ ] Tables collapse to card view or horizontal scroll on mobile
- [ ] Sidebar becomes drawer on mobile (<768px)
- [ ] Touch-friendly button sizes (44px minimum)
- [ ] No horizontal overflow

---

## Error Handling & Empty States

### Adapter Error Pattern

Every adapter should catch exceptions and return error state:

```php
try {
    $data = ...fetch data...
    return [
        'kpi' => [...],
        'empty' => count($data) === 0,
        'error' => '',
    ];
} catch (\Exception $e) {
    return [
        'kpi' => [],
        'empty' => true,
        'error' => $e->getMessage(),
    ];
}
```

### View Error Display

```php
<?php if ($error !== ''): ?>
    <p class="coverage-focus-error"><?php echo htmlspecialchars($error); ?></p>
<?php elseif ($empty): ?>
    <p class="coverage-focus-empty"><?php echo htmlspecialchars($this->tr('operator.qc.empty', '...')); ?></p>
<?php else: ?>
    <!-- Render data -->
<?php endif; ?>
```

---

## Security Considerations

### Server-Side

1. **Session validation** on every route
2. **Username normalization** to prevent injection
3. **All output through `htmlspecialchars()`** — no raw HTML
4. **Parameterized queries** in adapters
5. **No raw `$_GET` echoing** — always sanitize

### Client-Side

1. **No inline JavaScript** (scoped JS only)
2. **No eval()** — strict mode
3. **CSRF tokens** for form submissions
4. **No localStorage** of sensitive data

---

## Performance Optimization

### Current Approach

- **Query caching**: Short-lived cache for adapter queries (future)
- **Lazy loading**: Focus sections render on-demand (not yet implemented)
- **CDN assets**: Chart.js loaded from jsDelivr CDN
- **Minimal payloads**: Only fetch today's data (adaptive date window)

### Future Optimizations

1. **Query result caching** (Redis, Memcached)
2. **Lazy-load focus sections** — only render on `/u/{op}/{view}`
3. **Pagination** for large tables
4. **Background refresh** of KPI data
5. **WebSocket push** for real-time alerts

---

## Testing Strategy

### Smoke Tests

```bash
# All routes return 200 with valid session
for view in qc machines assembly materials; do
  curl -L -b session.cookie http://localhost:8000/u/$view
done
```

### Integration Tests

- [ ] Create comprehensive test suite for all operator routes
- [ ] Verify adapter data freshness
- [ ] Test error handling (DB down, null queries)
- [ ] Test localization (all 3 languages)
- [ ] Test mobile responsiveness

### End-to-End Tests

- [ ] Operator login → dashboard → navigate all views → logout
- [ ] Verify sidebar navigation matches routes
- [ ] Verify KPI values update in real-time
- [ ] Verify search/filter work on parts table

---

## Extension Points

### Adding a New Operator View

**Step 1**: Create adapter in `apps/Shell/Services/OperatorLayerAdapters/`

```php
namespace Apps\Shell\Services\OperatorLayerAdapters;

final class MyViewAdapter
{
    public static function getData(): array
    {
        try {
            // Fetch and transform data
            return [
                'kpi' => [...],
                'data1' => [...],
                'data2' => [...],
                'empty' => false,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return ['kpi' => [], 'empty' => true, 'error' => $e->getMessage()];
        }
    }
}
```

**Step 2**: Add to OperatorSurfaceComposer

```php
use Apps\Shell\Services\OperatorLayerAdapters\MyViewAdapter;

public function buildContent(): array
{
    return [
        // ... existing
        'myview_focus' => $this->buildMyViewFocusData(),
    ];
}

private function buildMyViewFocusData(): array
{
    return MyViewAdapter::getData();
}
```

**Step 3**: Add focus variables and HTML in `renderHTML()`

```php
$isMyViewFocus = ($focus === 'myview');

// In gate condition
$this->context['showDashboard'] = !$isMyViewFocus && !$isQcFocus && ... ;

// In HTML
<?php if ($isMyViewFocus): ?>
    <section class="myview-focus-section">
        <!-- Render myview data -->
    </section>
<?php endif; ?>
```

**Step 4**: Add route in `apps/Shell/routes.php`

```php
$router->get('/u/myview', function () {
    // ... standard pattern ...
    OperatorLayerService::render($normalized, array_merge($_GET, ['focus' => 'myview']), '/u/' . $normalized . '/myview');
    return null;
});
```

**Step 5**: Add sidebar nav item in `OperatorLayerSidebarService`

```php
['icon' => '🔧', 'label' => $this->tr('operator.sidebar.myview', 'My View'), 'route' => '/u/{user}/myview', 'badge' => null],
```

**Step 6**: Add locale keys in `app/Locale/{en|ja|ne}.php`

```php
'operator.sidebar.myview' => 'My View',
'operator.myview.page_title' => 'My View Title',
'operator.myview.empty' => 'No data for today.',
// ... etc
```

---

## Known Limitations & Future Work

### Current Limitations

1. **No real-time updates** — Page refresh required to see latest KPIs
2. **No operator shift handoff** — No notes between shifts
3. **No operator preferences** — Can't customize view settings
4. **No operator alerts** — No push notifications for critical events
5. **No operator search history** — Can't access recent searches
6. **No offline mode** — Requires live connection

### Future Roadmap

- **Phase 5**: Add operator notifications/alerts widget
- **Phase 6**: Add operator shift handoff notes
- **Phase 7**: Add operator preferences (view settings, refresh rate, etc.)
- **Phase 8**: Add WebSocket real-time KPI updates
- **Phase 9**: Add offline mode with IndexedDB cache

---

## Troubleshooting

### Route Not Found (404)

- ✅ Check `/u` route normalization logic in `routes.php`
- ✅ Verify focus slug in KNOWN_VIEW_SLUGS comment
- ✅ Check URL spelling (lowercase, hyphens not underscores)

### Adapter Returns Empty

- ✅ Check date filter (adapters use `today` by default)
- ✅ Verify DB queries are not filtered too aggressively
- ✅ Check adapter error handling is working

### Localization Keys Missing

- ✅ Check `app/Locale/{lang}.php` for required keys
- ✅ Verify fallback text is provided in `$this->tr()` call
- ✅ Run locale files through PHP lint

### Mobile Layout Broken

- ✅ Check CSS media queries in `app.css`
- ✅ Verify no hardcoded pixel widths in HTML
- ✅ Test on actual mobile device (not just Chrome DevTools)

---

## Contact & Support

- **Architecture Owner**: [To be filled]
- **Questions**: See AGENTS.md and linked architecture docs
- **Bug Reports**: GitHub Issues [repo link]
- **Enhancements**: TODO.md and Phase roadmap

---

**End of Document**
