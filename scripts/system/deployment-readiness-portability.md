# Deployment Readiness Portability Note

Deployment readiness should invoke registered shell tools through `bash` rather than relying on executable file mode.

Current important example:

```bash
bash scripts/architecture/run_architecture_gates.sh
```

Reason:

- Fresh checkouts, hosted panels, or transferred archives may not preserve executable bits.
- Calling through `bash` keeps readiness portable across local macOS, shared hosting shells, and CI-like environments.
- This matches the existing guidance for running `bash scripts/system/check_deployment_readiness.sh` directly.

Validation command:

```bash
bash scripts/system/check_deployment_readiness.sh
```

Portable local helper when `bash` is unavailable:

```bash
php scripts/system/check_deployment_readiness_portable.php
```

Behavior:

- When `bash` is available, the PHP helper delegates to `bash scripts/system/check_deployment_readiness.sh`.
- When `bash` is unavailable, it runs the PHP/git subset needed for local setup portability and reports shell-only diagnostics as skipped.

Expected final result:

```text
DEPLOYMENT READINESS: PASS
```
