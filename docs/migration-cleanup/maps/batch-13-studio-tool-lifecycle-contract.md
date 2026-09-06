# Batch 13 Studio Tool Lifecycle Contract

Status: documentation and read-only diagnostic only.

No files were moved.
No runtime behavior was changed.
No Core files were edited.
No Studio features were built.

Authority:

- Batch 8 Studio boundary priority inventory
- Batch 9 Studio runtime-adjacent separation map
- Batch 10 Studio host-link hardening plan
- Batch 11 optional host-link hardening implementation

## Objective

Define a clear lifecycle contract for Studio Tools so tool-level enablement and governance are explicit while preserving the Business App and Module ownership model.

## Contract Outcomes

1. Terminology is explicit and consistent:
   - Business Apps have Modules.
   - Studio has Tools.
   - Studio Tools may have module-like lifecycle.
   - Tools are governed workers, not runtime business owners.
2. Tool manifest baseline fields are standardized.
3. Instance-level tool policy model is documented.
4. Role, permission, environment, risk, execution guard, and visibility gates are documented.
5. No hidden runtime truth rule is explicit and enforceable.

## Deliverables

- Architecture contract:
  - `docs/architecture/studio-tool-lifecycle-contract.md`
- Batch map:
  - `docs/migration-cleanup/maps/batch-13-studio-tool-lifecycle-contract.md`
- Read-only diagnostic:
  - `scripts/architecture/check_studio_tool_lifecycle_contract.sh`

## Required Manifest Fields Captured

- tool_key
- label
- category
- risk_level
- default_enabled
- can_disable
- required_permissions
- allowed_environments
- routes
- views
- services
- owner = studio

## Required Instance Policy Captured

```yaml
studio_tools:
  view_editor: enabled
  menu_editor: enabled
  theme_tool: enabled
  app_builder: enabled # read-only placeholder
  module_builder: enabled # read-only placeholder
  db_schema_tool: enabled # read-only placeholder
```

Enabled placeholders expose tool identity, intended entity lifecycle, ownership boundaries, and governance readiness only. They do not authorize artifact mutation.

## Guard Contract Captured

1. Tool enabled state gate
2. Role/permission gate
3. Environment gate
4. Risk-level gate
5. Execution guard requirement
6. UI visibility requirement
7. No hidden runtime truth rule

## Validation

- `git diff --check`
- `bash scripts/architecture/check_studio_tool_lifecycle_contract.sh`
- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`

## Priority Outcome

1. Studio Tool lifecycle semantics are contract-first, explicit, and auditable.
2. Studio remains a governed worker surface, not runtime business owner truth.
3. Future tool-level lifecycle controls can be implemented without violating owner boundaries.
