# System App Contract Enforcement

`check_system_app_contracts.sh` is a read-only architecture gate for the Shell and Platform System Apps.

It validates the stable System App contract fields that are already declared in:

```text
apps/Shell/manifest.json
apps/Platform/manifest.json
```

## Enforced Shell Contract

Shell must declare:

```text
app_key: shell
type: framework
entry: routes.php
system_app_contract.version: system-app-contract.v1
system_app_contract.owner_layer: Shell
system_app_contract.runtime_role: generic_runtime_shell
```

Shell must also declare:

```text
system_app_contract.lifecycle_policy
system_app_contract.route_policy
system_app_contract.source_of_truth_policy
ownership_contract.owns
ownership_contract.must_not_own
ownership_contract.contributes_to
ownership_contract.depends_on
```

Shell ownership boundaries must include:

```text
owns: runtime frame
owns: wrapper composition
must_not_own: Core primitives
must_not_own: business logic
must_not_own: ACL authorization policy
must_not_own: Workspace Profile policy
must_not_own: Studio drafts / runtime truth
depends_on: resolved experience contracts
```

## Enforced Platform Contract

Platform must declare:

```text
app_key: platform
type: system
entry: routes.php
system_app_contract.version: system-app-contract.v1
system_app_contract.owner_layer: Platform
system_app_contract.runtime_role: platform_governance
```

Platform must also declare:

```text
system_app_contract.lifecycle_policy
system_app_contract.route_policy
system_app_contract.source_of_truth_policy
ownership_contract.owns
ownership_contract.must_not_own
ownership_contract.contributes_to
ownership_contract.depends_on
```

Platform ownership boundaries must include:

```text
owns: platform governance
owns: System Tools entry points / diagnostics
must_not_own: Core primitives
must_not_own: Shell runtime chrome
must_not_own: app/module business workflows
must_not_own: Studio editor / runtime truth
must_not_own: direct permission bypasses
depends_on: Core auth/audit/permission primitives
```

## Lifecycle Policy

For both Shell and Platform, lifecycle policy must remain:

```text
disable: restricted_contract_only
uninstall: restricted_contract_only
export: metadata_and_assets_only
```

The legacy `can_disable`, `can_uninstall`, and `can_export` manifest booleans are treated as compatibility flags. The lifecycle policy is the governed contract.

## Route Policy

Both contracts must declare:

```text
route_policy.canonical_prefixes
route_policy.compatibility_surfaces
route_policy.must_not_reinterpret_routes: true
```

This preserves the current route model and prevents Shell or Platform from silently redefining denied route behavior.

## Validation

Run:

```bash
bash scripts/architecture/check_system_app_contracts.sh
bash scripts/architecture/run_architecture_gates.sh
bash scripts/system/check_deployment_readiness.sh
```

Expected result:

```text
RESULT: PASS
ARCHITECTURE GATES: PASS
DEPLOYMENT READINESS: PASS
```
