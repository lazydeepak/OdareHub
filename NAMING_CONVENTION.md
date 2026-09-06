# Unified Naming Convention for Views, Modules, and Apps

## Hierarchy

```
SUITE/BUNDLE → APP → MODULE → VIEW_NAME → RENDERING_TYPE
    (removed)    ✓       ✓         ✓             ✓
```

## Naming Pattern

### Full Hierarchical Key
```
app.{app}.{module}.{view_name}.{rendering_type}.title
app.{app}.{module}.{view_name}.{rendering_type}.display_name
```

### Components

| Level | Examples | Format | Scope |
|-------|----------|--------|-------|
| **App** | Manufacturing, SBAIO, Platform | `app.manufacturing` | Business domain/suite |
| **Module** | daily_orders, attendance, organization | `daily_orders` | Feature area within app |
| **View Name** | index, add, edit, detail, report, leader | `index`, `order_360` | Specific page/component |
| **Rendering Type** | table, form, kpi, chart, dashboard | `table` | How content is rendered |

## Valid View Names

### Standard CRUD Views
- `index` — List/landing page (default entry point)
- `add` — Create new record form
- `edit` — Edit existing record form
- `detail` — View single record details
- `form` — Generic form (when CRUD pattern doesn't apply)

### Specialized Views
- `report` — Analytical/reporting view (often with charts/tables)
- `leader` — Operator/leader/specialist view (role-specific dashboard)
- `queue` — Work queue/task list
- `reconcile` — Data reconciliation view
- `{entity}_360` — Comprehensive/360-degree view (e.g., `part_360`, `order_360`)

### Data Exchange Views
- `import` — Data import interface
- `export` — Export data (action/confirmation page)

### Print/Document Views
- `pdf_{name}` — Printable PDF report (e.g., `pdf_production_plan`)
- `print_{name}` — Print-optimized layout (e.g., `print_next_two_weeks`)

## Valid Rendering Types

| Type | Purpose | Example Use Cases |
|------|---------|------------------|
| `table` | Data grid/list display | indexes, reports showing tabular data |
| `form` | Input/edit interface | add, edit, import views |
| `kpi` | Metric cards/indicators | dashboards, leader views, 360 views |
| `chart` | Analytics/visualization | line, pie, bar charts in reports |
| `list` | Scrollable list | work queues, activity feeds |
| `dashboard` | Multi-widget layout | operational summary, role dashboards |
| `queue` | Work queue display | task queues, approval queues |
| `report` | Formatted report layout | detailed reports, export layouts |
| `detail` | Single record view | comprehensive record page |

## Locale Key Examples

### Manufacturing > Daily Orders > Index (Table View)
```
app.manufacturing.daily_orders.index.table.title
app.manufacturing.daily_orders.index.table.display_name
```

**Locale Entry (en.php):**
```php
'app.manufacturing.daily_orders.index.table.title' => 'Daily Orders',
'app.manufacturing.daily_orders.index.table.display_name' => 'Manufacturing Daily Orders Queue',
```

### Manufacturing > Production Plans > Report (Chart View)
```
app.manufacturing.production_plans.report.chart.title
app.manufacturing.production_plans.report.chart.display_name
```

### SBAIO > Attendance > Dashboard (KPI View)
```
app.sbaio.attendance.dashboard.kpi.title
app.sbaio.attendance.dashboard.kpi.display_name
```

### Platform > Organization > Company Detail (Detail View)
```
app.platform.organization.company.detail.title
app.platform.organization.company.detail.display_name
```

## Locale File Organization

### Centralized Locale (`/app/Locale/en.php`)
Contains app/module level translations:
```php
'app.manufacturing' => [
    'daily_orders' => [
        'index' => ['table' => ['title' => 'Daily Orders', 'display_name' => 'Manufacturing Daily Orders']],
    ],
],
```

### Module-Specific Locale (`/apps/{app}/modules/{module}/lang/en.php`)
Contains detailed translations for module views:
```php
'app.manufacturing.daily_orders.index.table.title' => 'Daily Orders',
'app.manufacturing.daily_orders.index.table.display_name' => 'Manufacturing Daily Orders Queue',
'app.manufacturing.daily_orders.report.chart.title' => 'Orders Report',
'app.manufacturing.daily_orders.report.chart.display_name' => 'Manufacturing Daily Orders Analytics',
```

## Dashboard System

Dashboard blocks and cards use similar convention:

```
dashboard.blocks.{block_key}.title
dashboard.cards.{card_key}.title
```

### Examples
- `dashboard.blocks.operational_summary` → "Operational Summary"
- `dashboard.cards.approval_inbox` → "Approval Inbox"

## Resolution Flow

1. **Controller/View renders:** Uses `t('app.manufacturing.daily_orders.index.table.title')`
2. **Locale resolver:** 
   - Checks centralized locale (`/app/Locale/en.php`)
   - Falls back to module locale (`/apps/Manufacturing/modules/DailyOrders/lang/en.php`)
   - Falls back to locale function with provided fallback
3. **Widget blueprint:** Appends `.title` suffix to stored key if needed
4. **Dashboard layout:** Uses `tr()` helper to resolve block/card labels

## Implementation Checklist

- [x] Dashboard blocks (10) - use `dashboard.blocks.*` pattern
- [x] Plugin cards (10) - use `dashboard.cards.*` pattern
- [x] Manufacturing app (28 view entries across 15 modules)
- [x] Platform app (22 view entries across 2 modules)
- [x] SBAIO app (24 view entries across 11 modules)
- [ ] Generated apps - add locale entries for generated views
- [ ] Extended apps - add entries for Shell, Procurement, Studio
- [ ] Multilingual support - ja.php, ne.php translations

## Deprecation Notes

- ~~`suite.*` locale keys~~ → Now `app.*`
- ~~`admin.suite_management.*`~~ → Now `admin.app_management.*`
- Old view file `suite_management.php` → Replaced by `app_management.php`
