# Backlog: Runtime and Refactor Reports

## Scope

- Route hygiene, cleanup, refactor, coverage, dashboard analysis.

## Tasks

- [x] Create `docs/migration-cleanup/runtime/` and `docs/migration-cleanup/refactor/`.
- [x] Move grouped files in separate commits.
- [x] Re-run architecture and deployment checks after each commit.

## Output

- Moved to `docs/migration-cleanup/runtime/`:
	- `CLEANUP-REPORT.md`
	- `ROUTE-HYGIENE-REPORT.md`
	- `ROUTE-HYGIENE-COMPLETION.md`
- Moved to `docs/migration-cleanup/refactor/`:
	- `CONTROLLER-REFACTOR-REPORT.md`
	- `DASHBOARD-DECOMPOSITION-REPORT.md`
	- `DASHBOARD-SERVICE-REFACTOR-REPORT.md`
	- `CODEBASE-ANALYSIS.md`
	- `COVERAGE-AUDIT.md`

## Validation

- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`
