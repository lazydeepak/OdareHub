# OH-AUTO1-053 — Aggregate Architecture Gate Reliability

Date: 2026-10-15
Status: **Complete; non-Core verification correction applied**

## Diagnosis

The complete architecture runner contains 49 top-level gates. Its final
Studio deletion-family gate contains 14 capability gates and 14 workspace
gates. Those gates are a dependency chain: each child invokes its immediate
predecessor and its matching capability gate.

The aggregate suite was also iterating every ancestor explicitly. This caused
the same read-only probes and PHP lint checks to execute repeatedly. The
deletion-family suite took approximately 320 seconds in the initial bounded
run, exceeding the earlier 120-second command bound; no assertion failed and
no operation hung.

## Correction

`check_studio_deletion_family_capability_suite.sh` now:

- retains the complete 14-capability and 14-workspace gate declarations;
- statically verifies the dependency and capability-chain links;
- invokes only the terminal workspace gate, whose dependency chain reaches all
  28 checks;
- fails closed if a chain link or matching capability gate is removed.

The correction changes orchestration only. It does not weaken, skip, or alter
any deletion-family assertion.

## Evidence

- Original complete architecture run: `ARCHITECTURE GATES: PASS`.
- Original deletion-family duration: approximately 320 seconds;
  `DELETION FAMILY GATES: PASS (28/28)`.
- Optimized deletion-family suite: `DELETION FAMILY GATES: PASS (28/28)` in
  approximately 43 seconds.
- Definitive top-level timing: 49/49 gates passed in 477.573 seconds.
- Slowest top-level gate: deletion-family suite, 320.057 seconds before the
  correction.
- No database connection, SQL execution, migration, activation, deployment,
  or Core modification occurred.

## Remaining verification boundary

The deployment-readiness wrapper was not executed independently because it
invokes registered CSS publishers with `--apply`; that is a mutating stage and
is outside this assignment's no-deployment/no-apply boundary. The complete
read-only architecture suite is independently certified.
