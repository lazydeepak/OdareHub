# Coverage Workflow Audit Report
## ERP Engine v0.4.0 IPM System

**Date:** March 27, 2026  
**Scope:** 10 modules (Parts Master, Daily Orders, Pre Orders, Production Plans, Production Entries, QC Plans, QC Entries, Dispatch Entries, Stock Ledger, Part-Machine Map)  
**Purpose:** Assess coverage calculation capability and identify gaps blocking operational usability

---

## Executive Summary

**Current State:** Coverage is **partially implemented** only for Daily Orders with a simplistic calculation.

**Coverage Status:**
- ✅ Daily Orders: Stores coverage_pct, coverage_status, shortage_qty (calculated on create/update)
- ✅ Production Plans: Has coverage_pct, shortage_qty fields (never calculated or used)
- ✅ Stock Ledger: Tracks production inflows and dispatch outflows
- ❌ **Pre-Orders:** Completely excluded from coverage calculation (forecast demand ignored)
- ❌ **QC Impact:** Pass/fail quantities not reflected in coverage updates
- ❌ **Cascade Refresh:** Coverage not recalculated when production/QC/dispatch changes
- ❌ **Time-Window Awareness:** No concept of "supply available by required_date"
- ❌ **Supply Composition:** Can't trace which orders are backed by which production plans/entries
- ❌ **Machine Capacity:** No validation that planned quantities fit machine capabilities
- ❌ **Safety Stock:** Products have no minimum buffer quantity field

**Business Impact:** Coverage reports are **stale** after day 1 and cannot be trusted for operational decisions.

---

## Data Model Audit

### 1. Products (Parts Master)

**Existing Fields:**
```
- id (PK)
- parts_name, parts_number (unique)
- model, producer
- lead (VARCHAR, unused)
- notes
- is_active
- created_at, updated_at
```

**Coverage-Relevant Fields: MISSING**
- `safety_stock` (decimal) — minimum qty to maintain
- `min_order_qty` (decimal) — smallest viable purchase/production batch
- `reorder_point` (decimal) — trigger threshold for replenishment
- `lead_time_days` (int) — supplier lead time for purchase orders
- `unit_of_measure` (string) — kg, pcs, liters, etc. (for validation)
- `planning_window_days` (int) — how many days ahead to plan (default 30?)

**Assessment:** ❌ **Not suitable for coverage defaults.** Cannot encode part-level policies for planning.

---

### 2. Daily Orders (Near-term Actual Demand)

**Existing Fields:**
```
- id (PK)
- order_date, required_date (date)
- customer_name
- product_id (FK → products)
- qty (decimal) — DEMAND
- dispatch_deadline (datetime)
- coverage_pct, coverage_status (string), shortage_qty (decimal) — COVERAGE FIELDS
- planned_supply_qty (decimal) — SUPPLY ESTIMATE
- status (Open/Closed)
- notes
```

**Current Coverage Logic:**
```php
if (qty <= 0) return [0.0, 'Low', 0.0];
pct = (planned_supply_qty / qty) * 100;
status = pct >= 100 ? 'Full' : (pct >= 70 ? 'Partial' : 'Low');
shortage = max(0, qty - planned_supply_qty);
```

**Issues:**
- `planned_supply_qty` is **manually entered**, not calculated from production_plans
- Recalculated only on order create/update, **not when supply changes**
- Thresholds (70%, 100%) are **hardcoded**, not configurable
- **No QC yield adjustment** — planned_supply_qty assumes all production passes
- **No time consideration** — treats required_date as just metadata
- **No dispatch credit** — doesn't account for already-dispatched qty

**Assessment:** ⚠️ **Partial & Stale.** Formula exists but is disconnected from actual supply pipeline.

---

### 3. Pre Orders (Forecast/Reserved Demand)

**Existing Fields:**
```
- id (PK)
- forecast_type (string) — Forecast/Reserved/etc.
- planning_priority (string) — High/Normal/Low
- product_id (FK → products)
- planned_qty (decimal) — FORECAST DEMAND
- balance_qty (decimal) — TODO: unclear how used
- required_date (date)
- notes
```

**Integration with Coverage: ❌ NONE**
- Pre-orders are **NOT included** in daily_orders coverage calculation
- No aggregation of pre-order demand vs production supply
- No time-window mapping to production plans

**Assessment:** ❌ **Not integrated.** Forecast demand is invisible to coverage system.

---

### 4. Production Plans (Planned Supply)

**Existing Fields:**
```
- id (PK)
- plan_date, (no required_date field!)
- machine_id (FK → machines)
- product_id (FK → products)
- planned_qty (decimal) — PLANNED SUPPLY
- sequence_no, runtime (decimal)
- status (Planned/Released/Closed)
- plan_type (Manual/Auto)
- reference_doctype, reference_name (e.g., 'DailyOrder', 'DO#123')
- coverage_pct, shortage_qty (decimal) — FIELDS PRESENT BUT UNUSED
- auto_created (bool)
- notes
```

**Issues:**
- `coverage_pct` and `shortage_qty` fields exist but **never calculated or used**
- **No production_date estimate** — plan_date is planning date, not delivery date
- **No link to QC plans** — no field indicating QC coverage
- **No yield estimate** — assumes 100% pass rate
- `reference_doctype` and `reference_name` are strings, should be FK fields

**Assessment:** ❌ **Structure exists but unused.** No backward mapping to which daily_orders this plan covers.

---

### 5. Production Entries (Actual Production)

**Existing Fields:**
```
- id (PK)
- production_date (date)
- shift (Day/Night/etc.)
- machine_id (FK → machines)
- product_id (FK → products)
- produced_qty, rejected_qty (decimal)
- good_qty (decimal) — ACTUAL USABLE SUPPLY, calculated: produced_qty - rejected_qty
- status (Draft/Posted)
- notes
```

**Integration:**
- On create/update: syncs `good_qty` as positive `PRODUCTION_IN` delta to stock_ledger_entries
- **Missing:** no link to production_plans (which plan did this fulfill?)
- **Missing:** no link to related demand

**Assessment:** ⚠️ **Partially integrated.** Correctly posts to ledger but orphaned from planning context.

---

### 6. QC Plans (QC Scheduling)

**Existing Fields:**
```
- id (PK)
- plan_date, required_date (date)
- product_id (FK → products)
- daily_order_id (FK → daily_orders)
- production_entry_id (FK → production_entries)
- planned_qty (decimal)
- estimated_time_minutes
- priority (High/Normal/Low)
- status (Open/Closed)
- notes
```

**Role:** Intermediate layer scheduling QC of production output.  
**Issues:**
- Links daily_orders → production_entries but doesn't link to production_plans
- Could be the "join point" for coverage integration but currently unused

**Assessment:** ⚠️ **Structurally sound** but role in coverage workflow unclear.

---

### 7. QC Entries (QC Results)

**Existing Fields:**
```
- id (PK)
- qc_plan_id (FK → qc_plans)
- daily_order_id, production_plan_id (FK)
- product_id (FK → products)
- qc_type (InProcess/Final/etc.)
- checked_qty, pass_qty, fail_qty (decimal)
- status (Open/Closed)
- remarks
```

**Current State:**
- Records which qty passed/failed inspection
- **NOT synced to stock ledger** — pass_qty doesn't reduce usable supply after fail adjustments
- **NOT used in coverage recalc** — failures don't trigger daily_order coverage refresh

**Assessment:** ❌ **Results not actioned.** Pass/fail data not reflected in downstream supply estimates.

---

### 8. Dispatch Entries (Fulfilled Demand)

**Existing Fields:**
```
- id (PK)
- dispatch_date (date)
- daily_order_id, production_plan_id, production_entry_id, qc_entry_id (FK, all nullable)
- product_id (FK → products)
- dispatchable_qty (decimal) — DEMAND FULFILLED
- destination (warehouse/customer)
- dispatch_type (Regular/Express/etc.)
- dispatch_status (Ready/Dispatched/Cancelled)
- remarks
```

**Integration:**
- On create: syncs `dispatchable_qty` as negative `DISPATCH_OUT` delta to stock_ledger_entries
- Could link back to daily_orders to mark qty fulfilled, but doesn't

**Assessment:** ⚠️ **Ledger-connected** but doesn't update source demand records (daily_orders.coverage_pct).

---

### 9. Stock Ledger Entries (Source of Truth for Available Stock)

**Existing Fields:**
```
- id (PK)
- product_id (FK → products)
- movement_type (ENUM): PRODUCTION_IN, DISPATCH_OUT, IN, OUT, ADJUST
- qty_delta (decimal, signed)
- balance_after (decimal) — NEVER POPULATED (always 0)
- reference_no, source_module (ProductionEntries, DispatchEntries, etc.), source_id
- notes
- created_at, updated_at
```

**Current Queries:**
```sql
SELECT COALESCE(SUM(qty_delta), 0) AS balance 
FROM stock_ledger_entries 
WHERE product_id = ?
```

**Issues:**
- `balance_after` field exists but **never maintained** — recalculation on every query (expensive)
- No filtering for status (e.g., only count PRODUCTION_IN that are "Posted"?)
- No time-window range queries (this time period's inflows/outflows)
- No distinction between reserved vs available stock

**Assessment:** ⚠️ **Works but not optimized.** Query load grows with ledger size. No time-phased visibility.

---

### 10. Part-Machine Map (Supply Feasibility)

**Existing Fields:**
```
- id (PK)
- product_id (FK → products)
- machine_id (FK → machines)
- is_active (bool)
- notes
```

**Current Use:** Ensures only mapped part-machine combinations can create production plans.

**Missing Fields:**
- `qty_per_cycle` (decimal) — units produced per production run
- `cycle_time_minutes` (int) — duration of one run
- `changeover_time_minutes` (int) — time to switch to this part
- `max_daily_capacity` (decimal) — max qty this machine can produce per day for this part
- `min_batch_qty` (decimal) — smallest viable batch for this part on this machine

**Assessment:** ❌ **No capacity data.** Cannot validate if planned_qty is feasible or suggest alternatives.

---

## Coverage Calculation Gap Analysis

### What Should Happen (Ideal)

**For a Daily Order:**
1. Capture demand: qty, required_date, customer
2. Find all production_plans with matching product_id and plan_date ≤ required_date
3. For each plan, check status:
   - Planned → assume 0% available (not started)
   - Released → assume 50% available (in progress)
   - Closed → assume 100% available (if passed QC)
4. For Closed plans, lookup related qc_entries: reduce supply by fail_qty
5. For Closed plans, lookup related dispatch_entries: reduce supply by dispatch_qty
6. **Usable Supply = SUM(plan.planned_qty * status_factor * (1 - fail%)) - dispatch_qty**
7. **Coverage = usable_supply / demand_qty**
8. Recalculate whenever: production_entry posted, qc_entry closed, dispatch_entry created

**For Pre-Orders:**  
1. Capture forecast: planned_qty, required_date, priority
2. Reserve production capacity based on priority (high pre-orders take slots before lower)
3. Auto-generate or adjust production_plans to cover forecast
4. Track pre-order fulfillment (how much allocated to this forecast vs other dailyorders)

**For Production Plans:**
1. Check machine capacity: `plan.planned_qty ≤ machine.max_daily_capacity`
2. Verify required_date feasibility using part_machine_map.cycle_time
3. Estimate actual good_qty: `planned_qty * (100% - typical_fail_rate)`
4. Show coverage_pct = sum of related daily_orders it fulfills

**For Stock Ledger:**
1. Populate `balance_after` on each insert (running balance)
2. Support queries like: "what's the stock at end of today?" (time-point balance)
3. Support queries like: "how many IN and OUT between dates?" (time-window summary)

### What Currently Happens

| Step | Ideal | Current | Gap |
|------|-------|---------|-----|
| 1. Capture demand | ✅ | ✅ daily_orders.qty | ⚠️ pre_orders.planned_qty ignored |
| 2. Find production plans | ✅ | ❌ | Must do manually |
| 3. Factor plan status | ✅ | ❌ | Uses hardcoded % |
| 4. Apply QC yield | ✅ | ❌ | Assumes 100% pass |
| 5. Apply dispatch credit | ✅ | ❌ | Not calculated |
| 6. Calculate supply | ✅ | ✅ (but static) | planned_supply_qty must be manually entered |
| 7. Publish coverage | ✅ | ✅ (once) | Not refreshed |
| 8. Auto-refresh on changes | ✅ | ❌ | No triggers or event handlers |
| 9. Validate capacity | ✅ | ❌ | No machine capacity data |
| 10. Time-window queries | ✅ | ❌ | No support |

---

## Proposed Coverage Data Model

### Minimum Fields to Add

#### 1. Products Table

```sql
ALTER TABLE products ADD COLUMN IF NOT EXISTS (
  safety_stock DECIMAL(14,2) DEFAULT 0 COMMENT 'Min inventory to maintain',
  min_order_qty DECIMAL(14,2) DEFAULT 1 COMMENT 'Smallest viable batch',
  reorder_point DECIMAL(14,2) DEFAULT 0 COMMENT 'Trigger level for replenishment',
  lead_time_days INT DEFAULT 0 COMMENT 'Supplier lead time (days)',
  unit_of_measure VARCHAR(20) COMMENT 'pcs, kg, liters, etc.',
  planning_window_days INT DEFAULT 30 COMMENT 'Look-ahead forecast window',
  typical_fail_rate DECIMAL(5,2) DEFAULT 0 COMMENT 'Expected QC fail % (0-100)'
);
```

#### 2. Part-Machine Map

```sql
ALTER TABLE part_machine_map ADD COLUMN IF NOT EXISTS (
  qty_per_cycle DECIMAL(14,2) DEFAULT 1 COMMENT 'Units per production run',
  cycle_time_minutes INT DEFAULT 60 COMMENT 'Duration of one cycle',
  changeover_time_minutes INT DEFAULT 0 COMMENT 'Time to switch to this part',
  max_daily_capacity DECIMAL(14,2) COMMENT 'Max daily output of this part on this machine',
  min_batch_qty DECIMAL(14,2) DEFAULT 1 COMMENT 'Smallest batch for this part-machine combo'
);
```

#### 3. Daily Orders Table

```sql
ALTER TABLE daily_orders ADD COLUMN IF NOT EXISTS (
  -- Coverage tracking fields
  coverage_calc_timestamp TIMESTAMP NULL COMMENT 'When coverage was last calculated',
  usable_supply_qty DECIMAL(14,2) DEFAULT 0 COMMENT 'Calculated supply sum',
  
  -- Links to actual supply
  production_plan_ids JSON COMMENT 'Array of production_plan IDs backing this order',
  qc_pass_qty DECIMAL(14,2) DEFAULT 0 COMMENT 'QC pass qty from linked entries',
  dispatched_qty DECIMAL(14,2) DEFAULT 0 COMMENT 'Already dispatched qty'
);
```

#### 4. Production Plans Table

```sql
ALTER TABLE production_plans ADD COLUMN IF NOT EXISTS (
  required_date DATE COMMENT 'When supply must be ready (not just plan_date)',
  estimated_yield_pct DECIMAL(5,2) COMMENT 'Expected pass rate %',
  coverage_calc_timestamp TIMESTAMP NULL COMMENT 'When coverage was last calculated'
);
```

#### 5. New Table: Coverage_Log (Audit Trail)

```sql
CREATE TABLE IF NOT EXISTS coverage_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  daily_order_id INT NOT NULL,
  coverage_pct DECIMAL(5,2),
  coverage_status VARCHAR(20),
  shortage_qty DECIMAL(14,2),
  usable_supply_qty DECIMAL(14,2),
  production_plan_count INT,
  qc_impact DECIMAL(14,2),
  dispatch_credit DECIMAL(14,2),
  calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  notes TEXT,
  FOREIGN KEY (daily_order_id) REFERENCES daily_orders(id),
  KEY idx_daily_order_date (daily_order_id, calculated_at)
);
```

---

## Coverage Calculation Formulas

### Formula 1: Usable Supply for a Daily Order

```
usable_supply_qty = SUM over all linked production_plans {
  plan.planned_qty 
  × status_factor[plan.status]      // Planned=0%, Released=50%, Closed=100%
  × (1 - qc_fail_rate / 100)        // Adjust for QC failures
  - dispatch_qty_already_allocated  // Subtract already dispatched
}

WHERE plan.product_id = daily_order.product_id
  AND plan.plan_date <= daily_order.required_date
  AND plan.status IN ('Planned', 'Released', 'Closed')
```

**Status Factors:**
- `Planned` → 0% (not started, don't count)
- `Released` → 50% (in progress, risk of failure, partial credit)
- `Closed` → 100% (production complete, count full quantity before QC)

**QC Failure Rate:**
- Use actual: qc_entries.fail_qty / qc_entries.checked_qty
- Or estimate: products.typical_fail_rate if no QC entry yet

### Formula 2: Coverage Percentage

```
coverage_pct = MIN(100, (usable_supply_qty / daily_order.qty) × 100)
```

### Formula 3: Coverage Status

```
coverage_status = CASE
  WHEN coverage_pct >= 100    THEN 'Covered'
  WHEN coverage_pct >= 70     THEN 'PartialCovered'
  WHEN coverage_pct >= 30     THEN 'AtRisk'
  ELSE 'Uncovered'
END
```

(Update thresholds from hardcoded to configurable)

### Formula 4: Shortage Quantity

```
shortage_qty = MAX(0, daily_order.qty - usable_supply_qty)
```

### Formula 5: Machine Capacity Check (for Production Plans)

```
is_feasible = 
  plan.planned_qty <= part_machine_map.max_daily_capacity
  AND 
  plan.planned_qty >= part_machine_map.min_batch_qty
```

---

## UI & Workflow Recommendations

### 1. Daily Orders List View

**Current columns:**
- ID, Order Date, Customer, Part, Qty, Planned Qty, Coverage, Shortage, Status

**Recommended additions:**
- **Supply Breakdown** (expanding row): shows which prod plans contribute how much
- **Last Recalc** timestamp: when was coverage last updated
- **Age indicator**: if coverage >24h old, warn with yellow badge
- **Recalc button** (per row): manual refresh if needed

### 2. Daily Orders Detail View

**Add section: "Supply Pipeline"**
```
Production Plans backing this order:
┌─ Plan ID 101: 320 qty, status: Planned (0% credit)
├─ Plan ID 102: 280 qty, status: Released (50% credit → 140 credit)
└─ Plan ID 103: 180 qty, status: Closed (100% credit)
    └─ QC Entry: 173 pass, 7 fail (95.9% yield)
    └─ Dispatch: 150 already sent
    └─ Available for this order: 23

Total usable supply: 163 qty
Coverage: 32.6% (UNCOVERED — 337 short)
```

**Buttons:**
- "Recalculate coverage" (refreshes calculation immediately)
- "View related production plans"
- "View QC results"
- "View dispatch history"

### 3. Production Plans List View

**Add columns:**
- Planned Qty, Good Qty Estimate*, Status, Coverage %
- *Good Qty = Planned × (1 - Fail%)

**Add filter:**
- "Show plans with <70% demand coverage" (identify risk)
- "Show plans past required_date" (urgent)

### 4. Pre-Orders Integration

**New section in Daily Orders workflow:**

"Forecast availability:" Shows competing pre-orders for same part and how they rank (priority-based allocation).

**Example:**
```
Daily Order #1: 500 qty, priority: Normal
├─ Production Plan #101 → 320 qty reserved
├─ Production Plan #102 → 175 qty (75% of 280) — rest goes to Pre-Order #5
└─ Overall coverage: 62%

Pre-Order #5: 400 qty forecast, priority: High
├─ Production Plan #102 → 105 qty (25% of 280)
├─ Production Plan #104 → 280 qty (full)
└─ Overall coverage: 96%
```

### 5. Production Entries Create/Update

**Show real-time impact:**
```
Good Qty: 146 units
Estimated QC Pass (95%): 139 units
Current coverage impact on order #23: 52% → 68% ✅ COVERED
```

### 6. QC Entries Create/Update

**Show immediate cascading effect:**
```
Pass: 136 qty, Fail: 4 qty
Expected yield drop: 3% 
Impact on order #23 coverage: 68% → 65% ⚠️ still OK
Impact on order #24 coverage: 110% → 87% ⚠️ NOW PARTIAL
Impact on order #25 coverage: 45% → 12% 🔴 NOW CRITICAL
```

### 7. New Dashboard Card: Coverage Summary

```
Coverage Health — Last 24h

┌─────────────────┐
│ Covered       │ 12 orders (48%)
│ Partial       │  8 orders (32%)
│ At Risk       │  3 orders (12%)
│ Uncovered     │  2 orders ( 8%)
└─────────────────┘

⚠️ 3 orders with stale coverage (>6h old)
🔴 5 orders at risk of missing required_date
```

---

## Implementation Roadmap (Phase 1: Minimum Viable)

### Sprint 1: Data Model & Table Migrations
- [ ] Add fields to products, part_machine_map
- [ ] Add fields to daily_orders
- [ ] Create coverage_log table
- [ ] Update bootstrap.php in Products, PartMachineMap

**Effort:** 2-3 hours  
**Risk:** Low (additive only)

### Sprint 2: Coverage Calculation Service
- [ ] Create `App\Core\CoverageService` class
  - `calculateForDailyOrder(int $id): array` → returns [pct, status, shortage, usable_supply]
  - `refreshAllStaleOrders(int $maxAgeMinutes): int` → returns count refreshed
  - `captureAuditLog(int $dailyOrderId, array $calc): void`
- [ ] Implement formulas 1-4 (above)

**Effort:** 4-6 hours  
**Risk:** Medium (correctness of logic)

### Sprint 3: Event Hooks & Refresh Triggers
- [ ] ProductionEntries.create → call CoverageService::refresh() on related daily_orders
- [ ] ProductionEntries.update → call CoverageService::refresh()
- [ ] QCEntries.create/update → call CoverageService::refresh()
- [ ] DispatchEntries.create → call CoverageService::refresh()

**Effort:** 2-3 hours  
**Risk:** Medium (transaction safety)

### Sprint 4: UI Updates (Phase 1)
- [ ] DailyOrders.index → add "Stale" badge, "Recalc" button
- [ ] DailyOrders.edit → add Supply Pipeline section
- [ ] Add dashboard coverage summary card

**Effort:** 3-4 hours  
**Risk:** Low (UI only)

### Sprint 5: Pre-Orders Lite Integration
- [ ] Sum pre-order forecast demand per product
- [ ] Show in daily_orders list: "Forecast competing demand: 400 qty"
- [ ] Add note to Daily Orders detail: "Also see Pre-Orders for this part"

**Effort:** 2 hours  
**Risk:** Low (read-only, no business logic yet)

**Total Phase 1 Effort:** ~15-18 hours  
**Timeline:** ~5 days (single developer)

---

## Quick Wins (Immediate, <2h each)

1. **Add "Last Recalc" timestamp display** to daily_orders list/detail (shows data freshness)
2. **Add manual "Recalculate Coverage" button** (admin can refresh on demand)
3. **Add "is_stale" badge** (coverage >24h old)
4. **Show planned_supply_qty breakdown** in daily_orders detail view from demo data
5. **Add pre_orders count filter** to part-machine-map search ("parts with >1 pre-order")

---

## Open Questions for Clarification

1. **Pre-Order Allocation Strategy:** When a Production Plan produces output but both Daily Orders and Pre-Orders compete for it:
   - Should high-priority pre-orders always get first allocation?
   - Or should higher-priority daily orders win?
   - Or should it follow FIFO by required_date?

2. **QC Timing:** 
   - Does QC happen BEFORE dispatch (i.e., only pass_qty can be dispatched)?
   - Or can dispatch proceed pending QC results?
   - Current schema suggests QC can link to dispatch_entries, implying they're sequential.

3. **Machine Capacity Data:**
   - Should max_daily_capacity be stored in part_machine_map or a separate MachineCapacity table per date?
   - Can capacity vary by season/shift?

4. **Safety Stock Policy:**
   - Should safety stock reduce available qty for dispatch (FIFO: serve orders first, keep reserve)?
   - Or should dispatch ignore safety stock and always respect it?

5. **Coverage Reporting:**
   - Do you want a "Coverage Forecast" report (projected coverage 7 days out)?
   - Should coverage reports filter by customer, machine, or date range?

---

## Conclusion

**Current Capability:** Coverage is a **labeling system in Daily Orders**, not an **operational metric**.

**To Make It Operational Requires:**
1. ✅ **Schema additions** (fields for capacity, yield, time-windows)
2. ❌ **Calculation service** (currently missing — inline logic in controller only)
3. ❌ **Event-driven refresh** (currently never recalculates after creation)
4. ❌ **Time-window awareness** (currently static, not dynamic)
5. ❌ **Pre-order integration** (currently ignored)
6. ❌ **Machine capacity validation** (currently not checked)

**Minimal implementation** (Sprint 1-2 above) would bring coverage to **usable baseline** (~70% operational value) in ~10 hours and enable:
- Accurate daily order status
- Real-time supply pipeline visibility
- Automatic alerts on demand at-risk
- Foundation for forecast-to-order allocation

**Recommend:** Start with Phase 1 to validate workflows, then expand to pre-orders and capacity planning in Phase 2.

---

## Appendix: Reference Data Flows

### Current State Flow

```
Daily Order Created
  ↓
User manually enters planned_supply_qty
  ↓
Coverage calculated: (planned_supply_qty / qty) * 100
  ↓
Coverage_pct, coverage_status stored (STATIC)
  ↓
Production Entry Posted
  ↓ [NO CONNECTION]
  ↓
Stock Ledger Updated (+good_qty)
  ↓ [NO CONNECTION]
  ↓
Daily Order Coverage STALE ❌
```

### Proposed Flow

```
Daily Order Created
  ↓
Production Plan Created → production_order spans multiple plans if needed
  ↓
CoverageService.calculateForDailyOrder() triggered
  ↓
  ├─ Query related production_plans
  ├─ Apply status factors
  ├─ Query QC entries, apply yield
  ├─ Query dispatch entries, apply credit
  ├─ SUM = usable_supply_qty
  └─ Store + audit_log

Daily Order Coverage Updated ✅
  ↓
(Production Entry Posted or QC Entry Finalized)
  ↓
CoverageService.refresh() triggered (via event hook)
  ↓
Coverage recalculated ✅
  ↓
Newsletter/Alert: "Order #23 now 68% covered" (was 52%)
```

---

**End of Audit Report**
