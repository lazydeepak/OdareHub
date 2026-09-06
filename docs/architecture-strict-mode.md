# Architecture Strict Mode

Strict mode enforces architecture-direction rules at runtime.

## Current Default

- strict_mode: ON (from storage/architecture_policy.php)
- fallback default: ON when no policy file is present
- override: ERP_ARCH_STRICT_MODE environment variable (1/true/yes/on)

## What Strict Mode Enforces

1. Plugin load blocking:
- If a plugin triggers architecture-direction warnings, it is skipped during active plugin loading.

2. Install blocking:
- If install would violate architecture-direction rules, installation is blocked with STRICT_POLICY_BLOCK.

3. Update blocking:
- If update would violate architecture-direction rules, update is blocked with STRICT_POLICY_BLOCK.

## What Can Be Blocked In Future

The following conditions are expected to be blocked in strict mode:

- A core-suite plugin depending on a manufacturing-suite plugin.
- Any future dependency-direction rule that is surfaced as runtime architecture warnings.

## Non-Goals

Strict mode does not disable dependency safety checks already enforced elsewhere (missing/unknown/inactive dependencies). Those checks remain active regardless of strict mode.

## Routing Reference

Strict mode does not define canonical business route policy.
For canonical route ownership and compatibility alias rules, follow:

- `docs/architecture/ROUTING-STANDARD.md`
