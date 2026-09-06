# Batch 14 Studio Customization Tool Boundary Contract

Status: documentation and read-only diagnostic only.

No files were moved.
No runtime behavior was changed.
No Core files were edited.
No Studio features were built.

Authority:

- Batch 13 Studio Tool Lifecycle Contract

## Objective

Define customization-tool boundaries so Studio tools remain governed workers, while System Tools, Shell, Platform, public delivery output, and storage evidence each retain clear ownership.

## Deliverables

- Architecture contract:
  - `docs/architecture/studio-customization-tools-contract.md`
- Batch 14 map:
  - `docs/migration-cleanup/maps/batch-14-studio-customization-tools-contract.md`
- Read-only diagnostic:
  - `scripts/architecture/check_studio_customization_tools_contract.sh`

## Defined Contract Areas

1. Customization tools are Studio Tools, not System Tools.
2. System Tools validate/rebuild/repair/audit customization output.
3. Shell renders approved resolved theme/menu contracts.
4. Platform controls permissions and instance policy.
5. Public assets are delivery output, not source truth.
6. Storage may hold snapshots/audit/history, not hidden design truth.

## Future Studio Customization Tools Defined

- ThemeTool
- CssTool
- MenuEditor
- BrandingTool
- FontTool

Per tool, the contract defines:

- owner = studio
- editable resources
- forbidden resources
- risk level
- default enabled policy
- required permission
- output contract
- required validation
- rollback/snapshot requirement

## Specific Answers Captured

1. Which customization resources belong to Studio tools?
2. Which validation/rebuild actions belong to System Tools?
3. Which runtime rendering responsibilities belong to Shell?
4. Which authorization/policy responsibilities belong to Platform?
5. How do app/module-owned CSS and menu resources stay owner-owned?
6. Why public/assets must not become source truth?
7. What is the first safe implementation step after this contract?

## Validation

- `git diff --check`
- `bash scripts/architecture/check_studio_customization_tools_contract.sh`
- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`

## Priority Outcome

1. Customization-tool boundaries are explicit and auditable.
2. Runtime ownership and rendering remain with owner contracts and Shell runtime consumers.
3. System Tools remain the validation/rebuild/repair authority for customization output integrity.
