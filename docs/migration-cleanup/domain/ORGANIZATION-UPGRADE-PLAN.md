# Organization Module: Production Upgrade Plan

**Status**: Ready for Production Hardening  
**Current Quality**: 3.8/5 (Mature but Incomplete)  
**Maturity Target**: L3 (Enterprise Governance)  
**Timeline**: 5 weeks (161h)  
**Date Prepared**: April 30, 2026

---

## Executive Summary

The Organization module is **well-engineered and security-conscious** but **incomplete from a business perspective**. It excels at company/branch/branding master data but lacks:
- Organization hierarchies (departments, cost centers)
- Multi-organization isolation and user assignments
- Audit trails for governance/compliance
- Bulk operations for enterprises

**Three critical fixes** (4h total) must be done before Phase 1. Then a **strategic 4-week upgrade cycle** to add enterprise features.

---

## Phase 0: CRITICAL FIXES (Immediate - 4h)

### Fix #1: Add Foreign Key Constraints (2h)

**Problem**: org_branches, org_fiscal_settings, and branding_assets lack FKs to org_companies. Deleting a company leaves orphaned records.

**Solution**: Create migration `003_add_foreign_keys.sql`

```sql
ALTER TABLE org_branches 
ADD CONSTRAINT fk_org_branches_company 
FOREIGN KEY (company_id) REFERENCES org_companies(id) 
ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE org_fiscal_settings 
ADD CONSTRAINT fk_org_fiscal_company 
FOREIGN KEY (company_id) REFERENCES org_companies(id) 
ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE branding_assets 
ADD CONSTRAINT fk_branding_assets_company 
FOREIGN KEY (company_id) REFERENCES org_companies(id) 
ON DELETE CASCADE ON UPDATE CASCADE;
```

**Files to Create**: 
- `apps/Platform/modules/Organization/migrations/003_add_foreign_keys.sql`

**Validation**:
- `php -l` all Organization files
- Run migration: `php tools/migrate.php Organization 003`
- Verify constraints: `SHOW CREATE TABLE org_branches;`

---

### Fix #2: Localize Hardcoded Error Messages (1h)

**Problem**: 5 error messages in OrganizationService are in English only. Non-English UIs show English errors.

**Errors to Fix** (OrganizationService.php):
```php
// Line 307: 'Invalid usage key.'
// Line 359: 'Cannot remove primary logo via this method.'
// Line 544: 'Company name is required.'
// Line 545: 'Company code must be required.'
// Line 546: 'Company code is reserved.'
```

**Solution**: 

1. Update `app/Locale/en.php`:
```php
'organization.error.invalid_usage_key' => 'Invalid asset usage key.',
'organization.error.cannot_remove_primary' => 'Cannot remove primary logo via this method.',
'organization.error.company_name_required' => 'Company name is required.',
'organization.error.company_code_required' => 'Company code is required.',
'organization.error.company_code_reserved' => 'Company code is reserved for system use.',
```

2. Add same keys to `app/Locale/ja.php` and `app/Locale/ne.php`

3. Update OrganizationService.php to use localization:
```php
// Before:
return ['success' => false, 'error' => 'Invalid usage key.', 'path' => null];

// After:
return ['success' => false, 'error' => (string)t('organization.error.invalid_usage_key'), 'path' => null];
```

**Files to Modify**:
- `apps/Platform/modules/Organization/Services/OrganizationService.php` (5 locations)
- `app/Locale/en.php`, `ja.php`, `ne.php` (add 5 keys each)

**Validation**: `php -l` on all modified files

---

### Fix #3: Add Return Type Hints (1h)

**Problem**: Some private methods lack explicit return types, reducing IDE assist and static analysis.

**Methods to Fix** (OrganizationService.php):
```php
// Before:
private static function inspectBrandingFile(string $filePath, array $metadata = []): array

// After (already fixed in recent commits, verify no regressions)
private static function nullIfBlank($value): ?string
private static function normalizeCode(string $code): string
```

**Solution**: Audit all private methods, add return types where missing.

**Files to Modify**:
- `apps/Platform/modules/Organization/Services/OrganizationService.php`

**Validation**: 
- Run static analyzer: `phpstan analyze apps/Platform/modules/Organization/`
- Verify 0 errors

---

## Phase 1: ENTERPRISE FOUNDATION (Weeks 1-3, 55h)

### Feature 1A: Organization Hierarchy (40h)

**Goal**: Support multi-level departments, regions, cost centers, and reporting structures.

**Design**:

```
org_companies (existing)
  ├─ org_hierarchy (new) — Tree structure
  │   ├─ id (PK)
  │   ├─ company_id (FK → org_companies)
  │   ├─ parent_id (FK → org_hierarchy, self-join)
  │   ├─ hierarchy_code (unique per company)
  │   ├─ hierarchy_name
  │   ├─ hierarchy_type (enum: department, region, costcenter)
  │   ├─ level (depth: 0=company, 1=dept, 2=subdept)
  │   ├─ sort_order
  │   ├─ is_active
  │   ├─ created_at, updated_at
  │   └─ Indexes: (company_id), (company_id, parent_id), (company_id, is_active)
```

**Service Methods** (add to OrganizationService):

```php
// Queries
public static function hierarchyTree(int $companyId): array
  // Returns nested array: [{id, name, type, children: [...]}]

public static function hierarchyByCode(int $companyId, string $code): array
  // Returns single hierarchy record with full path

public static function childHierarchies(int $parentId): array
  // Returns immediate children only

// CRUD
public static function saveHierarchy(int $companyId, array $input): int
  // Validates parent exists, prevents cycles, returns new ID

public static function deleteHierarchy(int $hierarchyId): bool
  // Soft-deletes; cascades to children (marks inactive)

public static function moveHierarchy(int $hierarchyId, int $newParentId): bool
  // Validates no cycles, updates tree structure
```

**Views** (new):

- `Views/hierarchy.php` — Expandable tree UI with add/edit/delete
- Reuses existing search/pagination components
- Drag-drop reordering optional (V2)

**Routes** (new):

```php
GET  /ops/organization/hierarchy              → hierarchyIndex()
POST /ops/organization/hierarchy/save         → saveHierarchy()
POST /ops/organization/hierarchy/delete       → deleteHierarchy()
POST /ops/organization/hierarchy/move         → moveHierarchy()
GET  /ops/organization/hierarchy/{id}         → hierarchyDetail()
```

**Database Migration**: `004_create_hierarchy.sql`

**Validation**:
- Cycle detection test (parent cannot be child of self)
- Tree traversal test (all nodes reachable)
- Performance test (large tree, 1000+ nodes)

**Localization** (add keys):
- `organization.hierarchy.title`
- `organization.hierarchy.department`
- `organization.hierarchy.region`
- `organization.hierarchy.costcenter`
- `organization.action.add_hierarchy`
- `organization.error.hierarchy_cycle_detected`

**Effort**: 40h
- Schema + migration: 4h
- Service methods + validation: 15h
- Controller + routes: 8h
- UI views: 10h
- Tests + docs: 3h

---

### Feature 1B: Organization Audit Trail (15h)

**Goal**: Track all changes to organization master data for compliance/debugging.

**Design**:

```
org_audit_log (new)
  ├─ id (PK, BIGINT)
  ├─ company_id
  ├─ entity_type (company, branch, fiscal, hierarchy, branding)
  ├─ entity_id
  ├─ action (create, update, delete)
  ├─ changed_fields (JSON: {field: {before, after}})
  ├─ user_id
  ├─ user_email
  ├─ ip_address
  ├─ timestamp
  └─ Index: (company_id, timestamp)
```

**Service Methods** (add):

```php
public static function logAuditChange(
    int $companyId, 
    string $entityType, 
    int $entityId, 
    string $action, 
    array $changeset
): void

public static function auditLog(
    int $companyId, 
    array $filters = []
): array
  // Returns paginated audit log with user + entity details

public static function entityChangeHistory(
    string $entityType, 
    int $entityId
): array
  // Returns all changes to a specific entity (company, branch, etc.)
```

**Integration**: Call `logAuditChange()` after each save in existing CRUD methods:

```php
// In saveCompany() after success:
self::logAuditChange(
    $companyId,
    'company',
    $companyId,
    'update',
    $changeset  // Array of what changed
);
```

**Views** (new):

- `Views/audit.php` — Table: Entity, User, Timestamp, Action, Changed Fields
- Filter by: date range, entity type, user, action

**Routes** (new):

```php
GET /ops/organization/audit           → auditIndex()
GET /ops/organization/audit/{type}/{id} → entityHistory()
```

**Database Migration**: `005_create_audit_log.sql`

**Localization** (add):
- `organization.audit.title`
- `organization.audit.entity_type`
- `organization.audit.action`
- `organization.audit.changed_fields`

**Effort**: 15h
- Schema + migration: 2h
- Service logging: 5h
- Audit views: 5h
- Routes + integration: 3h

---

## Phase 2: MULTI-ORGANIZATION ISOLATION (Weeks 3-4, 50h)

### Feature 2: User-Organization Assignments

**Goal**: Allow users to be assigned to specific organizations, isolating their view of Manufacturing/SBAIO data.

**Design**:

```
user_organization_assignments (new)
  ├─ id (PK)
  ├─ user_id (FK → users)
  ├─ company_id (FK → org_companies)
  ├─ role (enum: viewer, operator, manager, admin)
  ├─ effective_from (date)
  ├─ effective_to (date, nullable)
  ├─ is_active
  ├─ created_at, updated_at
  └─ Unique: (user_id, company_id, effective_from)
```

**Service Methods** (new):

```php
public static function userOrganizations(int $userId): array
  // Returns list of companies user can access

public static function userCanAccessOrganization(int $userId, int $companyId): bool
  // Check if user has active assignment to company

public static function addUserToOrganization(
    int $userId, 
    int $companyId, 
    string $role = 'viewer'
): bool

public static function removeUserFromOrganization(int $userId, int $companyId): bool

public static function organizationMembers(int $companyId): array
  // Returns users assigned to company with their roles
```

**Views** (new):

- `Views/members.php` — List organization members, add/remove
- Table: User Email, Role, Effective Date, Status

**Routes** (new):

```php
GET  /ops/organization/{company_id}/members         → membersIndex()
POST /ops/organization/{company_id}/add-member      → addMember()
POST /ops/organization/{company_id}/remove-member   → removeMember()
```

**Integration with ACL**:

In Manufacturing and SBAIO modules, add scope check:

```php
// Before querying production plans:
$companyId = (int)($input['company_id'] ?? 0);
if (!Auth::canAccessOrganization($companyId)) {
    throw new \Exception('Access denied to organization');
}
```

**Database Migration**: `006_create_user_org_assignments.sql`

**Localization** (add):
- `organization.members.title`
- `organization.members.role`
- `organization.action.add_member`
- `organization.action.remove_member`

**Effort**: 50h
- Schema + migration: 3h
- Service methods + queries: 8h
- Views + routes: 12h
- ACL integration (Manufacturing, SBAIO): 15h
- Testing + docs: 12h

---

## Phase 3: POLISH & SCALE (Weeks 5+, 67h)

### Feature 3A: Pagination for Branches (5h)

**Scope**: Large orgs with 1000+ branches need paginated list, not all-in-memory load.

**Changes**:
- Add `listBranches(companyId, limit=50, offset=0)` overload
- Update UI: Previous/Next buttons, page indicator
- Add total count query

---

### Feature 3B: Bulk Import/Export (20h)

**Scope**: CSV upload for companies, branches, fiscal settings.

**Functionality**:
- Export: Download CSV of companies/branches/fiscal
- Import: Upload CSV, validate, preview, then commit
- Error report: Show validation failures

**Files**:
- `BulkOperationService` — Parse CSV, validate rows, batch insert
- Views: `import.php`, `export.php`

---

### Feature 3C: Widget Contributions (10h)

**Scope**: Dashboard widgets showing org overview.

**Widgets**:
- "Active Companies" (count, statuses)
- "Branch Distribution" (map or chart)
- "Fiscal Calendar" (next fiscal year start)

---

### Feature 3D: Complete Reports & Exports (15h)

**Reports**:
- Organization Readiness Report (missing fields, dependencies)
- Master Data Export (multi-sheet Excel)
- Compliance Report (field completeness check)

---

### Feature 3E: Optional: Regional/Territory Support (25h)

**Scope**: Geographic hierarchy (countries, regions, territories).

**Design**: Extend `org_hierarchy` with `territory` type, add lat/lon for mapping.

---

## Implementation Checklist

### Pre-Implementation
- [ ] Conduct code review of analysis (this document)
- [ ] Get team sign-off on Phase 0 critical fixes
- [ ] Schedule 4-week sprint

### Phase 0 (All 4 items parallel)
- [ ] Create `003_add_foreign_keys.sql`
- [ ] Localize error messages in OrganizationService.php
- [ ] Add locale keys (en, ja, ne)
- [ ] Verify return type hints (phpstan pass)
- [ ] Commit: "organization: critical fixes (FKs, localization, types)"
- [ ] Test migration: no orphaned records
- [ ] Deploy to staging

### Phase 1 (Weeks 1-3)
- [ ] **Hierarchy**:
  - [ ] Create `004_create_hierarchy.sql`
  - [ ] Implement OrganizationService hierarchy methods
  - [ ] Add OrganizationController hierarchy routes
  - [ ] Build tree UI in `hierarchy.php`
  - [ ] Add localization keys
  - [ ] Test cycle detection, tree traversal
  - [ ] Commit: "organization: add hierarchy/department support"

- [ ] **Audit Trail**:
  - [ ] Create `005_create_audit_log.sql`
  - [ ] Implement OrganizationService audit methods
  - [ ] Integrate audit logging into all CRUD operations
  - [ ] Build audit UI in `audit.php`
  - [ ] Add localization keys
  - [ ] Commit: "organization: add audit trail tracking"

### Phase 2 (Weeks 3-4)
- [ ] Create `006_create_user_org_assignments.sql`
- [ ] Implement user-org methods in OrganizationService
- [ ] Build members UI and routes
- [ ] Integrate ACL checks in Manufacturing/SBAIO
- [ ] Add localization keys
- [ ] Test access isolation
- [ ] Commit: "organization: multi-org isolation via user assignments"

### Phase 3 (Weeks 5+)
- [ ] Pagination: Update listBranches(), add UI controls
- [ ] Bulk ops: Create import/export handlers
- [ ] Widgets: Add dashboard contributions
- [ ] Reports: Complete report implementations
- [ ] Commit: "organization: pagination, bulk ops, widgets, reports"

### Testing & Validation
- [ ] All PHP files: `php -l` (lint)
- [ ] Static analysis: `phpstan analyze apps/Platform/modules/Organization/`
- [ ] Unit tests: Service methods (hierarchy, audit, user-org)
- [ ] Integration tests: Data isolation, cascading deletes
- [ ] Performance tests: Tree queries (1000+ nodes), pagination
- [ ] Security tests: ACL enforcement, SQL injection patterns
- [ ] Localization tests: All strings in 3 languages
- [ ] Compliance checklist: Document in AGENT-COMPLIANCE-CHECKLIST.md

### Documentation & Handoff
- [ ] API documentation (new service methods)
- [ ] Database schema diagrams (hierarchy, audit, assignments)
- [ ] User guide: How to use hierarchy, audit, multi-org
- [ ] Admin guide: Migration steps, backfill strategies
- [ ] Architecture decision records (ADRs) for design choices

---

## Risk Assessment & Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|-----------|
| Foreign key cascade deletes orphan records | Low | High | Test cascades thoroughly; backup before deploy |
| Hierarchy cycles in production | Medium | High | Implement cycle detection; periodic validation job |
| User-org assignments break existing queries | High | High | Gradual rollout; feature flag for ACL integration |
| Performance regression with audit log writes | Medium | Medium | Async audit logging; batch inserts |
| Localization keys missing in production | Medium | Low | Automated key audit; translation checklist |

---

## Success Metrics

After completion of all phases:

| Metric | Current | Target |
|--------|---------|--------|
| Module Quality Score | 3.8/5 | 4.5/5 |
| Feature Completeness | 40% | 85% |
| Foreign Keys | 0 | 3 ✅ |
| Localization Coverage | 90% | 100% ✅ |
| Code Type Hints | 95% | 100% ✅ |
| Test Coverage | — | >80% |
| Performance: Branch list (1000 items) | — | <500ms |
| Audit entries logged per operation | 0 | 1 ✅ |

---

## Next Steps

1. **Review this plan** with team leads (Platform, Manufacturing, SBAIO)
2. **Approve Phase 0** critical fixes (4h, should be done this week)
3. **Schedule Phase 1** sprint (55h, weeks 1-3 after Phase 0)
4. **Identify owner** for user-org scoping in Phase 2 (affects Manufacturing/SBAIO)
5. **Set up testing environment** with test data (1000+ orgs/branches for perf testing)

---

## Related Documents

- `ORGANIZATION-MODULE-ANALYSIS.md` — Detailed analysis
- `apps/Platform/modules/Organization/` — Module source
- `AGENT-COMPLIANCE-CHECKLIST.md` — Compliance tracking
