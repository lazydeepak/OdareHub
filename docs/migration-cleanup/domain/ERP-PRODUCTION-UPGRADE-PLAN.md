# SBAIO ERP Platform: Full Production Upgrade Plan

**Status**: Multi-app maturity assessment complete  
**Current State**: Core stable (4/5), Manufacturing functional (3/5), SBAIO/Procurement shells (2/5)  
**Target State**: All apps L3 (enterprise-ready) by end of Q2 2026  
**Total Effort**: 735-850 hours (18-21 weeks)  
**Timeline**: 16 weeks (4 phases × 4 weeks)  
**Date Prepared**: April 30, 2026

---

## Executive Summary

The SBAIO ERP system has a **solid foundation** (Core 4/5, Base Plugin 4/5) but **incomplete applications**:

| App | Quality | Completeness | Readiness | Critical Path? |
|-----|---------|--------------|-----------|----------------|
| Core | 4/5 | 78% | ✅ Production-ready | Foundation (locked) |
| Platform | 3/5 | 55% | ⚠️ Partial | Blocks all multi-site deployments |
| Manufacturing | 3/5 | 65% | ⚠️ Partial | Functional; needs polish |
| SBAIO | 2/5 | 48% | ❌ Unusable | Forms/validation missing (50h blocker) |
| Procurement | 2/5 | 35% | ❌ Shell | Workflow not implemented (75h blocker) |
| Base Plugin | 4/5 | 82% | ✅ Production-ready | Operator adapters pending |

**Bottom Line**: You can run Manufacturing today (with some UI/UX pain). But SBAIO payroll, Procurement workflows, and multi-site deployments are not production-ready. Operator workspace is 80% designed, 20% built.

**Recommended Action**: 4-phase upgrade cycle, starting **this week** with SBAIO forms (Phase 1), then Platform org hierarchy (Phase 2), then operator data (Phase 3), then Procurement (Phase 4).

---

## System Dependency Map

```
┌─────────────────────────────────────────────────────────┐
│ CORE ENGINE (LOCKED)                                    │
│ Auth, ACL, Entity Registry, Workflow, Audit, App Mgmt   │
│ Quality: 4/5 | Status: ✅ Production-ready              │
└──────────────────┬──────────────────────────────────────┘
                   │
        ┌──────────┴──────────┬─────────────┐
        ▼                     ▼             ▼
   ┌─────────────┐    ┌──────────────┐  ┌──────────┐
   │ BASE PLUGIN │    │ PLATFORM     │  │ SHELL    │
   │ 4/5 - 82%   │    │ 3/5 - 55%    │  │ NEW UI   │
   │ ✅ Ready    │    │ ⚠️  BLOCKED  │  │ 80% UI   │
   └──────┬──────┘    └──────┬───────┘  └─────┬────┘
          │                   │                │
          │ Supplies          │ Requires       │ Consumes
          │ UI surfaces       │ Org Hierarchy  │ Org data
          │ Approval workflow │                │ & adapters
          │                   │                │
        ┌─┴─────────────────┬─┴─────────────┬─┴──────────────┐
        ▼                   ▼               ▼                ▼
   ┌──────────────┐   ┌────────────────┐ ┌──────────┐   ┌──────────┐
   │MANUFACTURING │   │ SBAIO          │ │PROCURE   │   │ 3rd Party│
   │ 3/5 - 65%    │   │ 2/5 - 48%      │ │ 2/5 - 35%│   │ Integr.  │
   │ ⚠️  Partial  │   │ ❌ BLOCKED     │ │❌ BLOCKED│   │          │
   └──────┬───────┘   └────────┬───────┘ └────┬─────┘   └──────────┘
          │                    │               │
          │ Timecards          │ Expenses      │ Material plans
          │ Production data    │ Timecards     │ generate PRs
          │                    │               │
          └────────────┬───────┴───────────────┘
                       │
               Linkage needed:
               Procurement ← Manufacturing (material planning)
               SBAIO ← Manufacturing (timecards)
               Platform ← All (org hierarchy)
```

**Critical Path**: Platform (Org Hierarchy) → Manufacturing (Assembly L3) → SBAIO (Forms) → Operator Adapters

---

## Phase 1: FOUNDATION FIXES (Weeks 1-2, 105h)

### Goal
Make SBAIO and SBAIO-adjacent modules usable. Refactor shared service to reduce risk.

### 1A: Refactor UserDashboardAssignmentService (20h) 🔴 CRITICAL

**Problem**: 4,486-line monolith mixing context resolution, routing, UI config, and schema management. Used by Platform, Manufacturing, SBAIO, Shell; risky to modify.

**Solution**: Decompose into 4 focused services:

```
UserDashboardAssignmentService (EXISTING - 4,486 LOC)
  ↓ Refactor into:
  ├─ UserDashboardContextService (200 LOC) — User + org + app context resolution
  ├─ UserDashboardRouterService (300 LOC) — Route access control logic
  ├─ UserDashboardCatalogService (600 LOC) — UI dashboard/app/module catalog
  └─ UserDashboardMigrationService (200 LOC) — Schema setup/backfill
```

**Implementation**:
- Create new service files with focused responsibilities
- Migrate methods from old service with full test coverage
- Update all call sites (Platform, Manufacturing, SBAIO)
- Deprecate old service (phased removal over 2 sprints)

**Files**: 
- Create: `apps/Platform/Services/UserDashboardContextService.php` etc.
- Update: All callers across Platform, Manufacturing, SBAIO, Shell

**Testing**:
- Unit tests for each new service (50 test cases)
- Integration tests: context resolution with multi-org setup
- Regression tests: all existing dashboard calls still work

**Timeline**: 2-3 developers, 2 weeks parallel with other Phase 1 items

---

### 1B: SBAIO Form Suite Completion (50h) 🔴 CRITICAL

**Problem**: 9 out of 11 SBAIO modules have index-only views. Add/edit/detail forms missing. Validation logic absent.

**Modules to Complete**:

| Module | Missing | Effort | Blocker |
|--------|---------|--------|---------|
| Staff | Create, Edit, Detail forms | 8h | Timecard data entry |
| Attendance | Bulk entry, Approval flow UI | 10h | Leave integration |
| Timecards | Create, Edit, Submit UI | 8h | Payroll input |
| Leave | Request UI, Approval workflow | 8h | Payroll deduction |
| Payroll | Run form, Correction amendment UI | 8h | Execution |
| Schedules | Shift assignment UI | 5h | Scheduling |
| Expenses | Create, Approval UI | 3h | Report accuracy |

**Implementation**:
- Create form templates for each module (Study existing Manufacturing forms for pattern)
- Add input validation (server + client-side hints)
- Implement CSRF protection on all POST routes
- Add localization strings for all form labels/errors

**Files** (per module):
- `Views/{module}_create.php`, `{module}_edit.php`, `{module}_detail.php`
- Add routes: GET /ops/sbaio/{module}/{id}, POST /ops/sbaio/{module}/save
- Add validation in Services/{module}Service.php

**Testing**:
- Functional tests: create, edit, delete workflows
- Validation tests: required fields, format constraints, edge cases

**Timeline**: 2 developers, 3-4 weeks (can parallelize by module)

**Go/No-Go Gate**: All forms functional + validation passing before payroll execution begins

---

### 1C: Assembly Module L3 Promotion (30h)

**Problem**: Assembly routes/controllers split between app-level and module-level. Lifecycle ownership unclear. Reports not declared.

**Solution**: Promote Assembly to full L3 module ownership.

**Changes**:
- Move canonical routes from `app/Routes/assembly_*.php` → `apps/Manufacturing/modules/Assembly/routes.php`
- Verify all controller methods live in module directory
- Declare module as report provider (if any Assembly reports exist)
- Add module to Manufacturing manifest.json with dependencies
- Update Bootstrap to load Assembly before dependent modules

**Files**:
- `apps/Manufacturing/modules/Assembly/routes.php` — Consolidate all assembly routes
- `apps/Manufacturing/modules/Assembly/bootstrap.php` — Declare reports/widgets
- `apps/Manufacturing/manifest.json` — Add Assembly entry
- Remove: `app/Routes/assembly_*.php`

**Testing**:
- Route resolution tests: all assembly URLs still work
- Module lifecycle tests: assembly loads correctly, enables/disables cleanly
- Regression tests: no broken dependencies

**Timeline**: 1 developer, 2 weeks

**Go/No-Go Gate**: Assembly routes owned by module, all URLs functional, lifecycle tests pass

---

### Phase 1 Effort Summary

| Item | Effort | Owner | Dependencies |
|------|--------|-------|--------------|
| UserDashboardAssignmentService refactor | 20h | 2 devs (parallel) | None |
| SBAIO forms (Staff/Attendance/Timecards/Leave/Payroll) | 50h | 2 devs (modules in parallel) | None |
| Assembly L3 promotion | 30h | 1 dev | None |
| **Total** | **100h** | **3-4 devs** | **Parallel** |

**Timeline**: 2-3 weeks (all can run in parallel)

**Commits**:
- `refactor: decompose UserDashboardAssignmentService into focused services`
- `sbaio: add form suite for staff, attendance, timecards, leave, payroll`
- `manufacturing: promote Assembly module to L3 ownership (routes consolidation)`

---

## Phase 2: MULTI-SITE FOUNDATION (Weeks 3-6, 95h)

### Goal
Enable multi-site deployments via Organization Hierarchy. Enable Procurement. Stabilize Manufacturing.

### 2A: Organization Hierarchy & Departments (40h) 🔴 CRITICAL

**From**: ORGANIZATION-UPGRADE-PLAN.md Phase 1

**Dependencies**: Requires Phase 1B complete (SBAIO forms working)

**Impact**: Unblocks:
- Manufacturing: Cost center tracking for GL posting
- SBAIO: Department assignment for timecards/payroll
- Platform: Multi-site org structures

**Timeline**: 1 developer, 3 weeks

---

### 2B: Material Shortage Prediction (40h)

**Problem**: Manufacturing generates material plans but no downstream integration to Procurement. When material is short, no auto-escalation.

**Solution**: Implement demand-to-procurement flow.

**Design**:
1. Manufacturing material planner → shortage detection (allocated qty < required qty)
2. Auto-create Purchase Request in Procurement (via trigger or scheduled job)
3. Email notification to procurement manager
4. Dashboard widget: "Upcoming shortages in 7 days"

**Files**:
- `apps/Procurement/Services/AutoPRGenerationService.php` — Detect shortages, create PRs
- `apps/Manufacturing/Services/MaterialPlanningService.php` — Add shortage check
- Scheduled job: `scripts/shortage-detection-job.php` (runs daily)

**Testing**:
- Unit: shortage detection logic (various qty scenarios)
- Integration: material plan → PR creation → email sent

**Timeline**: 1 developer, 3 weeks

---

### 2C: Organization Audit Trail (15h)

**From**: ORGANIZATION-UPGRADE-PLAN.md Phase 1B

**Dependencies**: Organization Hierarchy

**Timeline**: 1 developer, 1 week

---

### Phase 2 Effort Summary

| Item | Effort | Owner | Dependencies |
|------|--------|-------|--------------|
| Organization hierarchy (40h) | 40h | 1 dev | Phase 1 ✅ |
| Material shortage prediction | 40h | 1 dev | Manufacturing baseline ✅ |
| Org audit trail | 15h | 1 dev | Org hierarchy ✅ |
| **Total** | **95h** | **3 devs** | Sequential |

**Timeline**: 4 weeks (can parallelize hierarchy + shortage with some overlap)

---

## Phase 3: OPERATOR WORKSPACE & VISIBILITY (Weeks 7-10, 160h)

### Goal
Complete operator `/u/{username}/*` workspace with real-time data adapters. Enable production visibility for shift supervisors/operators.

### 3A: Manufacturing Data Adapters (60h) 🔴 CRITICAL

**From**: Exists partially; awaiting adapter implementation

**Adapters Needed**:
1. **CoverageAdapter** (15h) — Machine utilization, queue depth, SLAs
2. **ProductionAdapter** (15h) — Active work orders, status distribution, time-in-stage
3. **QCAdapter** (15h) — QC pass/fail rates, defect categories, corrective actions needed
4. **DispatchAdapter** (15h) — Finished goods ready to ship, SLA breaches, customer delays

**Each Adapter**:
- Queries: Fetch aggregated/summary data (not raw)
- Transformations: Format for operator charts (pie, line, gauge)
- Caching: 1-5min TTL (real-time enough for operators, not overwhelming DB)
- Error handling: Graceful fallback if query fails

**Files**:
- `apps/Shell/Services/OperatorLayerAdapters/CoverageAdapter.php`
- `apps/Shell/Services/OperatorLayerAdapters/ProductionAdapter.php`
- `apps/Shell/Services/OperatorLayerAdapters/QCAdapter.php`
- `apps/Shell/Services/OperatorLayerAdapters/DispatchAdapter.php`

**Views**:
- `apps/Shell/Views/operator/dashboard.php` — Main operator dashboard (aggregates adapters)
- `apps/Shell/Views/operator/production-detail.php` — Deep dive into specific work order
- `apps/Shell/Views/operator/alerts.php` — SLA breaches, defects, exceptions

**Testing**:
- Unit: Adapter data transformation (various scenarios)
- Integration: Adapter queries against production-like data
- Performance: Adapter queries < 500ms (with cache hit)

**Timeline**: 1-2 developers, 3-4 weeks (can parallelize adapters)

---

### 3B: Real-Time Dashboard Infrastructure (40h)

**Problem**: Dashboards require page refresh for new data.

**Solution**: Implement WebSocket-based push updates (optional; low priority if polling acceptable).

**Alternative (Simpler)**: AJAX polling every 10-30 seconds (low cost, sufficient for ops dashboards).

**Decision Gate**: Use AJAX polling first (simpler), migrate to WebSocket if lag becomes issue.

**Files**:
- `public/assets/operator-dashboard.js` — AJAX poller, DOM updates
- `apps/Shell/Services/DashboardDataService.php` — API endpoint for adapter data

**Timeline**: 1 developer, 2 weeks (can be deferred to Phase 4 if needed)

---

### 3C: Mobile Operator Interface (60h)

**Problem**: Operator workspace designed for desktop; mobile experience poor.

**Solution**: Responsive design for operator dashboard/details/alerts.

**Changes**:
- Media queries for phone/tablet breakpoints
- Touch-friendly buttons/charts (larger targets, swipe gestures)
- Offline support (cache critical data, sync on reconnect)
- Camera integration (QR code scanning for work order lookup)

**Files**:
- Update: `apps/Shell/Views/operator/*.php` (responsive markup)
- Add: `public/assets/mobile-operator.css`
- Add: `public/assets/mobile-operator.js` (camera, geolocation APIs)

**Timeline**: 1-2 developers, 3-4 weeks

---

### Phase 3 Effort Summary

| Item | Effort | Owner | Dependencies |
|------|--------|-------|--------------|
| Manufacturing data adapters | 60h | 2 devs (parallel) | Phase 2 ✅ |
| Real-time dashboard infrastructure | 40h | 1 dev | Adapters |
| Mobile operator interface | 60h | 2 devs (parallel) | Adapters |
| **Total** | **160h** | **3-4 devs** | Some sequential |

**Timeline**: 4 weeks (adapters + mobile parallel, then dashboard)

**Go/No-Go Gate**: All adapters functional, dashboards responsive, mobile app tested on devices

---

## Phase 4: PROCUREMENT & ADVANCED FEATURES (Weeks 11-16, 375h)

### Goal
Implement Procurement workflows, advanced Manufacturing features, compliance/reporting.

### 4A: Procurement Workflow & 3-Way Match (75h) 🔴 CRITICAL

**From**: ORGANIZATION-UPGRADE-PLAN.md

**Implementation**:
1. **PR Workflow** (35h): Approval chain, budget checks, PO generation
2. **3-Way Match** (40h): PO → Receipt → Invoice reconciliation, variance handling

**Details**: See ORGANIZATION-UPGRADE-PLAN.md Phase 4

**Timeline**: 2 developers, 3-4 weeks

---

### 4B: Handoff Board Real-Time Updates (50h)

**Problem**: Handoff board shows snapshot; doesn't reflect live workflow transitions.

**Solution**: WebSocket updates (or AJAX polling) for handoff status changes.

**Timeline**: 1 developer, 2-3 weeks

---

### 4C: Material Shortage Escalation Engine (40h)

**Extension of Phase 2B**: Auto-escalate if material not received by X days before needed.

**Timeline**: 1 developer, 2 weeks

---

### 4D: Payroll Audit Trail & Corrections (25h)

**From**: ORGANIZATION-UPGRADE-PLAN.md Phase 3

**Implementation**: Version payroll runs, track amendments, audit trail.

**Timeline**: 1 developer, 2 weeks

---

### 4E: Compliance & Regulatory Reports (50h)

**Reports Needed**:
- Labor compliance (overtime tracking, rest day violations)
- Tax reporting (withholding accuracy, deduction reconciliation)
- Inventory compliance (cycle counts, shortage justifications)

**Timeline**: 1 developer, 3 weeks

---

### 4F: Advanced Manufacturing Features (60h)

**Backlog**:
- Demand forecasting via historical orders + seasonality
- ATP (Available To Promise) — real-time customer query
- Assembly line balancing (work station task allocation)
- OEE (Overall Equipment Effectiveness) metrics

**Timeline**: 1-2 developers, 4 weeks (prioritize by customer feedback)

---

### Phase 4 Effort Summary

| Item | Effort | Owner | Dependencies |
|------|--------|-------|--------------|
| Procurement workflow + 3-way match | 75h | 2 devs (parallel) | Phase 1 ✅ |
| Handoff board real-time | 50h | 1 dev | Phase 3 ✅ |
| Material escalation engine | 40h | 1 dev | Phase 2 ✅ |
| Payroll audit trail | 25h | 1 dev | Phase 1 ✅ |
| Compliance reports | 50h | 1 dev | All phases ✅ |
| Advanced manufacturing | 60h | 1-2 devs | Phase 2 ✅ |
| **Total** | **300h** | **4-5 devs** | Most parallel |

**Timeline**: 6 weeks (can overlap earlier phases)

---

## Master Roadmap: 16-Week Timeline

```
WEEK:  1  2  3  4  5  6  7  8  9 10 11 12 13 14 15 16

PHASE 1: FOUNDATION (Weeks 1-2)
├─ UserDashboardAssignmentService refactor    [██]
├─ SBAIO forms (parallel modules)             [████]
└─ Assembly L3 promotion                      [████]

PHASE 2: MULTI-SITE (Weeks 3-6)
├─ Organization hierarchy                           [████]
├─ Material shortage prediction                     [████]
└─ Org audit trail                                  [██]

PHASE 3: OPERATOR WORKSPACE (Weeks 7-10)
├─ Manufacturing data adapters                            [████]
├─ Real-time dashboard infrastructure                     [████]
└─ Mobile operator interface                             [████]

PHASE 4: ADVANCED (Weeks 11-16)
├─ Procurement workflows + 3-way match                        [████]
├─ Handoff board real-time                                    [██]
├─ Payroll audit trail                                        [██]
├─ Compliance reports                                         [████]
└─ Advanced manufacturing (forecasting, ATP, OEE)             [████]

GATES:
├─ Phase 1 → Go/No-Go: SBAIO forms working, no regressions
├─ Phase 2 → Go/No-Go: Org hierarchy stable, PRs auto-creating
├─ Phase 3 → Go/No-Go: Operator dashboards responsive, mobile tested
└─ Phase 4 → Go/No-Go: Procurement workflows approved, payroll audit clean
```

---

## Resource Allocation

### Recommended Team (4-6 developers)

**Phase 1** (2-3 weeks, 3-4 devs):
- **Dev A**: UserDashboardAssignmentService refactor (20h)
- **Dev B**: SBAIO Staff + Attendance forms (18h)
- **Dev C**: SBAIO Timecards + Leave forms (16h)
- **Dev D**: SBAIO Payroll forms + Assembly L3 (22h)

**Phase 2** (4 weeks, 3 devs):
- **Dev A**: Organization Hierarchy (40h)
- **Dev B**: Material Shortage Prediction (40h)
- **Dev C**: Org Audit Trail (15h)

**Phase 3** (4 weeks, 3-4 devs):
- **Dev A + B**: Manufacturing Adapters (60h, parallel)
- **Dev C**: Real-Time Dashboard Infrastructure (40h)
- **Dev D**: Mobile Operator Interface (60h, parallel)

**Phase 4** (6 weeks, 4-5 devs):
- **Dev A + B**: Procurement Workflows (75h, parallel)
- **Dev C**: Handoff Board + Escalation (90h)
- **Dev D**: Payroll Audit + Compliance (75h)
- **Dev E**: Advanced Manufacturing (60h, if available)

---

## Production Go/No-Go Criteria

### Pre-Phase 1 Deployment
- [ ] All core tests passing (lint, unit, integration)
- [ ] Zero breaking changes to existing routes
- [ ] UserDashboardAssignmentService refactor fully backward compatible

### Pre-Phase 2 Deployment
- [ ] SBAIO forms tested with real data
- [ ] Assembly module routes consolidated, all URLs functional
- [ ] No performance regression vs baseline

### Pre-Phase 3 Deployment
- [ ] Organization Hierarchy queries < 100ms (depth 5+)
- [ ] Material shortage prediction running daily without errors
- [ ] No orphaned org_branches/fiscal_settings records

### Pre-Phase 4 Deployment
- [ ] Operator dashboards load < 2 seconds (with data adapter cache)
- [ ] Mobile interface responsive on phone/tablet
- [ ] No data corruption in adapter transformations

### Production Gate (Post-Phase 4)
- [ ] All modules at L3 maturity: 80%+ features complete
- [ ] Test coverage > 60% (up from current 4.3%)
- [ ] Zero outstanding security issues (pen test clean)
- [ ] 99% route coverage (ACL + CSRF on all mutations)
- [ ] 100% UI localization (all strings in locale files)
- [ ] Performance: 95p latency < 2 seconds for all user actions
- [ ] Audit trail operational (all changes logged)
- [ ] Operator workspace: 10+ dashboards, all real-time

---

## Success Metrics

### By End of Phase 4

| Metric | Current | Target |
|--------|---------|--------|
| **System Quality Average** | 3.0/5 | 4.0/5 |
| **Feature Completeness** | 55% | 85% |
| **Production Readiness** | 40% | 95% |
| **Test Coverage** | 4.3% | 65% |
| **Localization** | 85% | 100% |
| **Type Hints** | 75% | 95% |
| **Routes Secured** | 92% | 100% |
| **Response Time (p95)** | 3s | <2s |

### Per-Module

| App | Current | Target | Effort |
|-----|---------|--------|--------|
| Core | 4/5 | 4/5 (locked) | 0h |
| Platform | 3/5 | 4/5 | 95h |
| Manufacturing | 3/5 | 4/5 | 70h |
| SBAIO | 2/5 | 3.5/5 | 75h |
| Procurement | 2/5 | 3.5/5 | 75h |
| Base Plugin | 4/5 | 4.5/5 | 60h |
| Shell (Operator) | 2/5 | 4/5 | 160h |
| **Total** | **3.0/5** | **4.0/5** | **735h** |

---

## Risk Management

### Top 3 Risks & Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|-----------|
| SBAIO forms regression breaks payroll processing | High | High | Comprehensive test suite before payroll cutover; phase in by department |
| Organization hierarchy cycles cause infinite loops | Medium | High | Cycle detection algorithm + nightly validation job |
| Mobile interface doesn't support offline QR scanning | Medium | Medium | Prototype offline cache + sync before full rollout; feature-flag mobile if issues |

### Dependency Chain Risks

**Critical Path**: Platform Org Hierarchy → Manufacturing L3 → Operator Adapters

- If Org Hierarchy delayed, all Phase 3+ work waits
- Mitigation: Assign dedicated resources; don't context-switch

---

## Budget & Timeline Summary

| Phase | Duration | Effort | Team Size | Cost (@ $150/h) |
|-------|----------|--------|-----------|-----------------|
| Phase 1 | 2-3 weeks | 100h | 3-4 devs | ~$15,000 |
| Phase 2 | 4 weeks | 95h | 3 devs | ~$14,250 |
| Phase 3 | 4 weeks | 160h | 3-4 devs | ~$24,000 |
| Phase 4 | 6 weeks | 300h | 4-5 devs | ~$45,000 |
| **Total** | **16 weeks** | **655h** | **~4 devs avg** | **~$98,250** |

*(Estimates: 8h/day, 5 days/week, $150/h contractor rate)*

---

## Next Steps

1. **This Week**:
   - [ ] Review this plan with tech leads (Platform, Manufacturing, SBAIO)
   - [ ] Confirm Phase 1 priorities with stakeholders
   - [ ] Allocate dev resources for Phase 1 (3-4 developers)

2. **Week 1-2**:
   - [ ] Begin UserDashboardAssignmentService refactor (parallel)
   - [ ] Begin SBAIO form builds (parallel)
   - [ ] Begin Assembly L3 promotion (parallel)

3. **End of Week 2**:
   - [ ] Phase 1 Go/No-Go gate: All deliverables merged, tested, no regressions
   - [ ] Deploy to staging
   - [ ] Begin Phase 2 (Org Hierarchy, Material Shortage)

4. **Ongoing**:
   - [ ] Weekly standup on critical path (Org Hierarchy → Operator Adapters)
   - [ ] Monthly production readiness review
   - [ ] Track test coverage trend (goal: +5% per week)

---

## Related Documents

- **ORGANIZATION-MODULE-ANALYSIS.md** — Detailed Organization module assessment
- **ORGANIZATION-UPGRADE-PLAN.md** — Organization module-specific roadmap (subset of this plan)
- **AGENTS.md** — Architecture governance rules (Core lock, app boundaries)
- **ARCHITECTURE.md** — System design and module lifecycle definitions

---

**Questions?** This plan is ready for stakeholder review and team planning sessions. Can adjust phases, effort estimates, or priorities based on business priorities or resource availability.
