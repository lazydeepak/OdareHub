# Registry Read Boundary Gate Update Plan

**Status:** Architecture plan. No gate modification authorized. No implementation authorized.

**Date:** 2026-06-11

**Builds on:**
- [Registry Read Contract](registry-read-contract.md) — ApprovedStyleReaderContract, adapter design, Phase 1 scope, diagnostics, gate relaxation rules
- [ResolvedStyleConsumer Contract](resolved-style-consumer-contract.md) — consumer ownership, inputs/outputs, read-only guarantees, Part M
- [ResolvedStyleConsumer Registry Read Planning](resolved-style-consumer-registry-read-planning.md) — use-case analysis, Part G gate changes specified, Part H risk assessment
- [Read-Only Consumption Probe Contract](read-only-consumption-probe-contract.md) — probe diagnostics and readiness validation
- [Platform Style Consumer Boundary Gate](check_platform_style_consumer_boundary.sh) — current 14-invariant boundary gate (this plan's subject)

---

## Part A — Current Gate Review

### File

```
scripts/architecture/check_platform_style_consumer_boundary.sh
```

### Invariant Groups (10 groups, 14+ invariants)

| # | Group | Lines | Invariants | Must Remain? |
|---|---|---|---|---|
| 1 | Required file exists | 148-149 | 1 | ✅ YES |
| 2 | Placeholder remains disabled | 152-173 | 3 | ✅ YES |
| 3 | No Studio coupling | 175-184 | 1 | ✅ YES |
| 4 | No Shell coupling | 186-195 | 1 | ✅ YES |
| 5 | No filesystem writes | 197-206 | 1 | ✅ YES |
| 6 | No shell/compiler calls | 208-217 | 1 | ✅ YES |
| 7 | No route registration | 219-228 | 1 | ✅ YES |
| 8 | No DB/HTTP side effects | 230-239 | 1 | ✅ YES |
| 9 | No draft/approval access | 241-255 | 2 | ✅ YES |
| 10 | Registry path blocked | 257-271 | 2 | ⚠️ PARTIAL — these 2 invariants are the relaxation target |

### Group 10 — The Two Blocking Invariants (Lines 257-271)

```bash
# Line 261-263 — Blocks ALL registry storage path references
check_no_matches \
    "must not read registry storage paths" \
    'storage/platform/style-registry|approved-values' \
    "${platform_style_files[@]}"

# Line 265-268 — Blocks ALL ApprovedStyleRegistry and getValue references
check_no_matches \
    "must not import or call ApprovedStyleRegistry" \
    'ApprovedStyleRegistry|getValue\s*\(' \
    "${platform_style_files[@]}"
```

These two invariants use a blanket block pattern. They block:
- `storage/platform/style-registry` — any reference to the registry storage root
- `approved-values` — any reference to the approved-values subdirectory
- `ApprovedStyleRegistry` — any import, type-hint, or string reference to the registry class
- `getValue(` — any call to the getValue method, even through an adapter

### Files Scanned

The gate discovers files dynamically:

```bash
find platform/Style -type f \( -name '*.php' -o -name '*.json' \) -print 2>/dev/null
```

This means when new files are added under `platform/Style/` (e.g., `Contracts/`, `Adapters/`), they are automatically included in all scans. The gate does not use a hardcoded allowlist of files — all scans use `${platform_style_files[@]}`.

**Implication for relaxation:** The two blocking patterns cannot be relaxed globally. They must be replaced with per-file or per-directory targeted rules, because the adapter (in `platform/Style/Adapters/`) must be allowed to reference `ApprovedStyleRegistry` and `getValue()` while all other files under `platform/Style/` must continue to be blocked.

---

## Part B — Relaxation Target

### Future Allowed Access Pattern

The only authorized registry read access is through the segregated read-only contract:

```php
namespace Platform\Style\Contracts;

interface ApprovedStyleReaderContract
{
    /** Read a single approved value. Returns null on missing/unreachable. */
    public function readValue(string $socketKey): ?string;

    /** Quick reachability check for registry storage. Side-effect free. */
    public function isReachable(): bool;
}
```

### Classes in the Allowed Chain

| Class | Path | Role |
|---|---|---|
| `ApprovedStyleReaderContract` | `platform/Style/Contracts/ApprovedStyleReaderContract.php` | Read-only interface. May only declare `readValue` and `isReachable`. Must not contain `setValue`, `isWritable`, or any write/governance method. |
| `ApprovedStyleReaderAdapter` | `platform/Style/Adapters/ApprovedStyleReaderAdapter.php` | Implements the contract. Wraps `ApprovedStyleRegistry::getValue()`. The single bridge to `apps/Platform/StyleRegistry/`. Must not expose write methods. |
| `ResolvedStyleConsumer` | `platform/Style/ResolvedStyleConsumer.php` | Depends on `ApprovedStyleReaderContract` only. Must not import `ApprovedStyleRegistry` directly. |

### What Gets Unblocked

After relaxation:

```
Allow in adapter only:
  ApprovedStyleRegistry (import/type-hint)
  getValue(             (call through registry)
  storage/platform/style-registry (path reference)
  approved-values       (path reference)

Allow everywhere:
  ApprovedStyleReaderContract (interface import)
  readValue(            (contract method call)
  isReachable(          (contract method call)

Still blocked everywhere (including adapter):
  setValue(
  isWritable(
```

---

## Part C — Still Forbidden

The following patterns must remain permanently blocked in ALL files under `platform/Style/`. No relaxation is authorized for any of these.

### Write Operations

```
file_put_contents(          — filesystem write
fwrite(                     — filesystem write
mkdir(                      — directory creation
unlink(                     — file deletion
rename(                     — file rename/move
chmod(                      — permissions change
chown(                      — ownership change
copy(                       — file copy
rmdir(                      — directory removal
```

### Shell/Compiler Execution

```
exec(                       — arbitrary command execution
shell_exec(                 — shell command execution
proc_open(                  — process execution
system(                     — command execution
passthru(                   — command execution
compile_theme_sources       — theme compiler call
ThemeCompiler               — theme compiler class reference
```

### Governance/Mutation (Registry Level)

```
setValue(                   — registry write
isWritable(                 — registry governance check
deleteValue(                — registry delete
```

### Studio Coupling

```
Apps\Studio                  — Studio namespace import
apps/Studio                  — Studio path reference
StudioController             — Studio controller reference
CustomizationStudio          — Studio tool reference
VisualCustomizer             — Studio tool reference
CssTokenEditor               — Studio tool reference
ThemeTool                    — Studio tool reference
```

### Shell Coupling

```
Apps\Shell                   — Shell namespace import (non-comment)
apps/Shell/styles            — Shell CSS path reference
ShellStyleService            — Shell service reference
ShellRuntime                 — Shell runtime reference
ThemePreferenceService       — Shell theme service reference
```

### Route Registration

```
Route::                      — route registration
routes\.php                  — route file reference
\bPOST\b                     — POST route method
GET route                    — GET route registration
```

### DB/HTTP Side Effects

```
PDO                          — database connection
mysqli                       — database connection
curl_                        — HTTP client
file_get_contents(\s*\(['"]https?://  — HTTP fetch
stream_context_create        — stream context for HTTP
```

### Draft/Approval Access

```
storage/studio/customization — Studio draft storage path
studio_draft                 — draft reference
visual-customizer.*draft     — Visual Customizer draft reference
approval-request             — approval artifact reference
ApprovalRequest              — approval request class reference
pending_approval             — approval state reference
studio_visual_customizer_requests — approval DB table reference
```

### Runtime Consumption Enablement

```
isRuntimeConsumptionEnabled.*return.*true   — must return false
runtimeConsumptionEnabled\s*=\s*true        — must not be set to true
runtime_consumption_enabled'\s*=>\s*true    — must not report true
```

### Registry Unauthorized Access

```
ApprovedStyleRegistry        — blocked in ALL files EXCEPT ApprovedStyleReaderAdapter
getValue(                    — blocked in ALL files EXCEPT ApprovedStyleReaderAdapter
storage/platform/style-registry — blocked in ALL files EXCEPT ApprovedStyleReaderAdapter
approved-values              — blocked in ALL files EXCEPT ApprovedStyleReaderAdapter
```

---

## Part D — Exact Gate Change Proposal

### Current Block (Lines 257-271)

```bash
echo ""
echo "== Registry path access still blocked =="
if [[ "${#platform_style_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "must not read registry storage paths" \
    'storage/platform/style-registry|approved-values' \
    "${platform_style_files[@]}"

  check_no_matches \
    "must not import or call ApprovedStyleRegistry" \
    'ApprovedStyleRegistry|getValue\s*\(' \
    "${platform_style_files[@]}"
else
  warn "no platform/Style/ files to scan for registry access"
fi
```

### Proposed Future Block (Replacement)

The two blanket invariants must be replaced with four targeted invariants:

```bash
echo ""
echo "== Registry read path — adapter-only exceptions =="

# Determine adapter file path
adapter_file="platform/Style/Adapters/ApprovedStyleReaderAdapter.php"
contract_file="platform/Style/Contracts/ApprovedStyleReaderContract.php"

echo "  adapter exception file: $adapter_file"
echo "  contract exception file: $contract_file"

#
# Invariant 1: Only the adapter may reference ApprovedStyleRegistry or getValue
#
# Rationale: The adapter wraps ApprovedStyleRegistry::getValue() behind the
# read-only contract. No other file under platform/Style/ may reference these.
#
non_adapter_files=()
for f in "${platform_style_files[@]}"; do
  if [[ "$f" != "$adapter_file" ]]; then
    non_adapter_files+=("$f")
  fi
done

if [[ "${#non_adapter_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "only adapter may reference ApprovedStyleRegistry (non-adapter files blocked)" \
    'ApprovedStyleRegistry|getValue\s*\(' \
    "${non_adapter_files[@]}"
else
  ok "no non-adapter files to scan for ApprovedStyleRegistry"
fi

#
# Invariant 2: Only the adapter may reference registry storage paths
#
# Rationale: Storage path references (storage/platform/style-registry,
# approved-values) belong to the adapter's path confinement logic.
# No other file should know or depend on the storage layout.
#
if [[ "${#non_adapter_files[@]}" -gt 0 ]]; then
  check_no_matches \
    "only adapter may reference registry storage paths (non-adapter files blocked)" \
    'storage/platform/style-registry|approved-values' \
    "${non_adapter_files[@]}"
else
  ok "no non-adapter files to scan for registry storage paths"
fi

#
# Invariant 3: Adapter must not call setValue or isWritable
#
# Rationale: The adapter is the sole bridge to ApprovedStyleRegistry.
# It must only call getValue(). Write and governance methods must be
# blocked even in the adapter.
#
if [[ -f "$adapter_file" ]]; then
  check_no_matches \
    "adapter must not call setValue" \
    'setValue\s*\(' \
    "$adapter_file"

  check_no_matches \
    "adapter must not call isWritable" \
    'isWritable\s*\(' \
    "$adapter_file"
else
  warn "adapter file not yet created; setValue/isWritable checks deferred"
fi

#
# Invariant 4: Consumer must not import ApprovedStyleRegistry directly
#
# Rationale: ResolvedStyleConsumer must depend only on the interface
# (ApprovedStyleReaderContract), not the implementation.
# The adapter is the only allowed consumer of ApprovedStyleRegistry.
#
consumer_file="platform/Style/ResolvedStyleConsumer.php"
if [[ -f "$consumer_file" ]]; then
  check_no_matches \
    "consumer must not import ApprovedStyleRegistry directly" \
    'use Apps\\Platform\\StyleRegistry' \
    "$consumer_file"

  check_no_matches \
    "consumer must not call getValue directly" \
    'getValue\s*\(' \
    "$consumer_file"
else
  warn "consumer file missing; direct-import checks deferred"
fi

#
# Invariant 5: Reader contract file must define only read methods
#
# Rationale: The contract file must never add setValue, isWritable,
# or any write/governance method.
#
if [[ -f "$contract_file" ]]; then
  check_no_matches \
    "reader contract must not contain setValue" \
    'setValue' \
    "$contract_file"

  check_no_matches \
    "reader contract must not contain isWritable" \
    'isWritable' \
    "$contract_file"

  check_no_matches \
    "reader contract must not contain deleteValue" \
    'deleteValue' \
    "$contract_file"
else
  warn "contract file not yet created; contract purity checks deferred"
fi
```

### Summary of Changes

| Current (2 invariants) | Future (5+ invariants) |
|---|---|
| `'storage/platform/style-registry\|approved-values'` — blanket block | Split: blocked in non-adapter files, allowed in adapter |
| `'ApprovedStyleRegistry\|getValue\('` — blanket block | Split: blocked in non-adapter files, allowed in adapter + blocked in consumer |
| — | NEW: `setValue/` blocked in adapter |
| — | NEW: `isWritable/` blocked in adapter |
| — | NEW: `ApprovedStyleRegistry` blocked in consumer |
| — | NEW: `getValue/` blocked in consumer |
| — | NEW: `setValue/isWritable/deleteValue` blocked in contract file |

---

## Part E — File Scope Rules

### Rule: Only the Adapter May Cross the Namespace Boundary

```
platform/Style/ResolvedStyleConsumer.php
    ✓ MAY import: Platform\Style\Contracts\ApprovedStyleReaderContract
    ✓ MAY call:   $this->reader->readValue(), $this->reader->isReachable()
    ✗ MUST NOT:  import Apps\Platform\StyleRegistry
    ✗ MUST NOT:  call getValue() directly
    ✗ MUST NOT:  reference storage/platform/style-registry
    ✗ MUST NOT:  reference approved-values
    ✗ MUST NOT:  import or reference ApprovedStyleRegistry

platform/Style/Contracts/ApprovedStyleReaderContract.php
    ✓ MAY define: readValue(string $socketKey): ?string
    ✓ MAY define: isReachable(): bool
    ✗ MUST NOT:  define setValue() or any write method
    ✗ MUST NOT:  define isWritable() or any governance method
    ✗ MUST NOT:  define deleteValue() or any destructive method
    ✗ MUST NOT:  import any Apps namespace
    ✗ MUST NOT:  reference storage paths

platform/Style/Adapters/ApprovedStyleReaderAdapter.php
    ✓ MAY import: Platform\Style\Contracts\ApprovedStyleReaderContract
    ✓ MAY import: Apps\Platform\StyleRegistry\Services\ApprovedStyleRegistry
    ✓ MAY call:   $registry->getValue()
    ✓ MAY reference: storage/platform/style-registry/approved-values/
    ✓ MAY use:   is_dir(), file_get_contents() for path-confinement checking
    ✗ MUST NOT: call $registry->setValue()
    ✗ MUST NOT: call $registry->isWritable()
    ✗ MUST NOT: call $registry->deleteValue()
    ✗ MUST NOT: import any Apps\Studio namespace
    ✗ MUST NOT: import any Apps\Shell namespace
    ✗ MUST NOT: call exec(), shell_exec(), proc_open(), system(), passthru()
    ✗ MUST NOT: register routes
    ✗ MUST NOT: access database
    ✗ MUST NOT: perform HTTP requests
    ✗ MUST NOT: write files (file_put_contents, fwrite, mkdir, etc.)
```

### Directory-Level Enforcement

```
platform/Style/Contracts/
    Entire directory: NO Apps imports allowed (not even adapter exceptions)
    Gate scan: apply all blocks including ApprovedStyleRegistry

platform/Style/Adapters/
    Only ApprovedStyleReaderAdapter.php gets the adapter exception.
    Any other file in Adapters/ is blocked from ALL registry references.

platform/Style/
    All other files: no registry references, no adapter exceptions.
```

---

## Part F — Runtime Consumption Flag

### Current Behavior

The gate already enforces three separate checks for the runtime consumption flag (lines 152-173):

```bash
# Check 1: isRuntimeConsumptionEnabled() must not return true
grep -q 'isRuntimeConsumptionEnabled.*return.*true' "$consumer_file"

# Check 2: runtimeConsumptionEnabled property must not be true
grep -Eq '\$runtimeConsumptionEnabled\s*=\s*true' "$consumer_file"

# Check 3: diagnostics must not report runtime_consumption_enabled as true
grep -Eq "'runtime_consumption_enabled'\s*=>\s*true" "$consumer_file"
```

### Core Principle

**Registry read is NOT runtime consumption.** Reading an approved value from the registry is a diagnostic/read-only operation. Runtime consumption means Shell applying those values as CSS variables, data attributes, or style sockets. These are two distinct gates:

```
Registry read gate:       ApprovedStyleReaderContract → readValue() → ?string
Runtime consumption gate: Shell reads resolved values → applies to DOM → visual change
```

### These Checks Must Remain UNCHANGED

Even after the gate is relaxed for registry reads, the three runtime consumption flag checks must remain exactly as they are:

1. `isRuntimeConsumptionEnabled()` must return `false` (or not be overridden to `true`)
2. `$runtimeConsumptionEnabled` must not be set to `true`
3. `diagnostics['runtime_consumption_enabled']` must be `false`

### Rationale

```text
Registry read (Phase 2)       ≠  Shell consumption (Phase 3+)

Phase 2: Read values, store in memory, report in diagnostics.
         Zero visual impact. Zero DOM changes. Zero CSS injection.

Phase 3+: Shell reads resolved values from consumer.
          Maps values to CSS variables or data attributes.
          Visual changes visible in all surfaces.

Phase 2 is authorized by Registry Read Contract.
Phase 3 requires a separate Shell Integration Contract.
The runtime_consumption_enabled flag is the Phase 3 gate.
It must not be opened by Phase 2.
```

### Recommended Gate Enforcement Pattern

The existing three checks at lines 152-173 are sufficient and correct. No change needed. The checks apply to `ResolvedStyleConsumer.php` regardless of whether registry reads are implemented. The consumer's `diagnostics()` output must continue to show `runtime_consumption_enabled: false` even when `readValue()` returns a valid value.

### Implementation Guidance for Future Consumer Work

When implementing registry reads in the consumer:

```php
// CORRECT — runtime consumption stays disabled
public function isRuntimeConsumptionEnabled(): bool
{
    return false;  // Phase 2: registry reads only, no Shell consumption
}

public function readValue(string $socketKey): ?string
{
    if (!$this->reader->isReachable()) {
        $this->addDiagnostic('RSC-C004', 'FAIL', 'Reader unavailable');
        return null;
    }
    $value = $this->reader->readValue($socketKey);
    if ($value !== null) {
        $this->addDiagnostic('RSC-C001', 'PASS', 'Reader reachable');
        $this->addDiagnostic('RSC-C002', 'PASS', "Value found: $value");
    } else {
        $this->addDiagnostic('RSC-C003', 'WARN', 'Value absent');
    }
    return $value;
}
```

---

## Part G — Diagnostics Requirements

### Diagnostic Code Mapping

The Registry Read Contract defines the full RSC-C* family (13 codes). For the boundary gate, only core reachability and read diagnostics need gate-level validation. The remaining codes (catalog alignment, fallback, multiple values, etc.) are implementation-level and do not require gate invariants.

### Core Diagnostics (Gate-Enforced)

| Code | Severity | Condition | Gate Invariant |
|---|---|---|---|
| RSC-C001 | PASS | Reader reachable — `isReachable()` confirmed storage exists | Gate must not block `isReachable()` calls through the contract |
| RSC-C002 | PASS | Value found — `readValue()` returned non-null | Gate must not block `readValue()` calls through the contract |
| RSC-C003 | WARN | Value absent — `readValue()` returned null (no value stored) | No gate invariant needed; null return is implementation behavior |
| RSC-C004 | FAIL | Reader unavailable — `isReachable()` returned false or storage inaccessible | Gate must verify consumer handles null reader gracefully |
| RSC-C005 | FAIL | Unauthorized access blocked — adapter path confinement prevented traversal | Gate must verify adapter has path confinement logic |

### Extended Diagnostics (Not Gate-Enforced)

These codes are implementation-level and do not require gate invariants. They are documented in the Registry Read Contract (Part G) and may be validated by the probe script:

```
RSC-C101 WARN  Socket not in catalog
RSC-C102 WARN  Value differs from catalog default
RSC-C103 WARN  Fallback used
RSC-C104 WARN  Multiple values found (possible corruption)
RSC-C202 FAIL  Approved value invalid per catalog type constraint
RSC-C301 ERROR Reader service not initialized
RSC-C302 ERROR Read contract violation (write attempt detected)
RSC-C303 ERROR Schema version mismatch
```

### Diagnostic Shape (Reference)

Each diagnostic must include:

```json
{
  "code": "RSC-C001",
  "severity": "PASS",
  "message": "Registry reader reachable and operational",
  "context": {
    "storageRoot": "storage/platform/style-registry/approved-values",
    "reachable": true
  }
}
```

### Gate Invariant for Diagnostics

The gate should add one invariant for diagnostics completeness once the contract and adapter exist:

```bash
# Verify consumer diagnostics include reachability check
check_no_matches \
    "consumer diagnostics must report reader reachability (RSC-C001)" \
    'reader_reachable|RSC-C001|readerReachable' \
    "$consumer_file"
# Expected: PASS (diagnostic is present)
# This is a positive check — different from the block-only pattern.
```

This positive check ensures the consumer always reports reader reachability status in its diagnostics output. It prevents a situation where registry reads are implemented but the diagnostic layer does not reflect reader health.

---

## Part H — Implementation Sequence

### Authorized Sequence

The sequence has 6 ordered steps. Each step authorizes the next. No step may be skipped.

```
Step 1 — Gate Update Plan (THIS DOCUMENT)
    Create architecture plan for gate relaxation.
    No gate changes. No implementation.
    Status: ✅ THIS DOCUMENT

Step 2 — Gate Update (Boundary Gate Relaxation)
    Modify check_platform_style_consumer_boundary.sh per Part D.
    Replace 2 blanket invariants with 5+ targeted invariants.
    All existing non-registry invariants unchanged.
    Runtime consumption flag checks unchanged.
    Gate must pass BEFORE any contract/adapter/consumer work.

Step 3 — ApprovedStyleReaderContract
    Create platform/Style/Contracts/ApprovedStyleReaderContract.php.
    Define: readValue(string $socketKey): ?string
    Define: isReachable(): bool
    No write/governance methods permitted.
    Gate must pass after creation.

Step 4 — ApprovedStyleReaderAdapter
    Create platform/Style/Adapters/ApprovedStyleReaderAdapter.php.
    Implement ApprovedStyleReaderContract.
    Wrap ApprovedStyleRegistry::getValue() behind read-only interface.
    Enforce path confinement.
    No setValue/isWritable exposure.
    Gate must pass after creation.
    Probe must pass (scripts/platform/probe_resolved_style_consumer.php).

Step 5 — ResolvedStyleConsumer Constructor Injection
    Update platform/Style/ResolvedStyleConsumer.php.
    Add constructor parameter: ApprovedStyleReaderContract $reader.
    Implement readValue() and isReachable() delegating to reader.
    Keep isRuntimeConsumptionEnabled() returning false.
    Keep diagnostics reporting runtime_consumption_enabled: false.
    Gate must pass after update.
    Probe must pass.

Step 6 — Diagnostics and Probe Update
    Ensure consumer diagnostics include RSC-C* codes (Part G).
    Update probe script if needed to test consumer read path.
    Verify all gates pass.
    Verify probe passes all 5+ scenarios.
```

### Dependencies

```
Step 2 has no code dependency — gate can be updated now.
Step 3 depends on Step 2 (gate must pass first).
Step 4 depends on Step 3 (contract must exist).
Step 5 depends on Step 4 (adapter must exist).
Step 6 depends on Step 5 (consumer must have reads).
```

### What Each Step Authorizes

| Step | Authorizes |
|---|---|
| 1 | Planning only (this document) |
| 2 | Gate script modification only |
| 3 | Read-only interface creation only |
| 4 | Read-only adapter creation only |
| 5 | Consumer constructor injection only |
| 6 | Diagnostics + probe updates only |

### What No Step Authorizes

```text
Shell integration
Runtime CSS injection
data-attribute rendering
Visual Customizer expansion
CTE governance changes
Multi-socket expansion
Theme source reads
Studio draft reads
```

---

## Part I — Validation Plan

### Pre-Validation (This Plan)

```bash
git diff --check
```

### Step 2 — Gate Update Validation

```bash
# 1. Gate passes with no files under platform/Style/
#    (contract, adapter, consumer not yet created)
bash scripts/architecture/check_platform_style_consumer_boundary.sh
# Expected: PASS (graceful handling of missing files)

# 2. Aggregate gates still pass
bash scripts/architecture/run_architecture_gates.sh
# Expected: PASS (pre-existing unrelated failures documented)

# 3. No whitespace errors
git diff --check
# Expected: no output
```

### Step 3 — Contract Creation Validation

```bash
# 1. Contract file exists
ls platform/Style/Contracts/ApprovedStyleReaderContract.php
# Expected: file exists

# 2. PHP lint
php -l platform/Style/Contracts/ApprovedStyleReaderContract.php
# Expected: No syntax errors

# 3. Gate passes with contract file present
bash scripts/architecture/check_platform_style_consumer_boundary.sh
# Expected: PASS (contract has no forbidden patterns)

# 4. Contract purity check (no write methods)
grep -E 'setValue|isWritable|deleteValue' platform/Style/Contracts/ApprovedStyleReaderContract.php
# Expected: no matches

# 5. No whitespace errors
git diff --check
```

### Step 4 — Adapter Creation Validation

```bash
# 1. Adapter file exists
ls platform/Style/Adapters/ApprovedStyleReaderAdapter.php
# Expected: file exists

# 2. PHP lint
php -l platform/Style/Adapters/ApprovedStyleReaderAdapter.php
# Expected: No syntax errors

# 3. Gate passes with adapter — adapter exceptions allowed
bash scripts/architecture/check_platform_style_consumer_boundary.sh
# Expected: PASS (adapter references ApprovedStyleRegistry/getValue but is exempted)

# 4. Adapter has no write methods
grep -E 'setValue\s*\(|isWritable\s*\(' platform/Style/Adapters/ApprovedStyleReaderAdapter.php
# Expected: no matches

# 5. Adapter is the ONLY file referencing ApprovedStyleRegistry outside Contracts/
grep -rn 'ApprovedStyleRegistry\|getValue' platform/Style/ --include='*.php' | grep -v 'Contracts/ApprovedStyleReaderContract.php' | grep -v 'Services/ApprovedStyleReaderAdapter.php' | grep -v 'ResolvedStyleConsumer.php'
# Expected: no matches (only adapter and contract reference it)

# 6. Probe passes with adapter
php scripts/platform/probe_resolved_style_consumer.php
# Expected: PASS (5/5 scenarios)

# 7. No whitespace errors
git diff --check
```

### Step 5 — Consumer Injection Validation

```bash
# 1. PHP lint consumer
php -l platform/Style/ResolvedStyleConsumer.php
# Expected: No syntax errors

# 2. Consumer does not import ApprovedStyleRegistry directly
grep -E 'use Apps\\\\Platform\\\\StyleRegistry' platform/Style/ResolvedStyleConsumer.php
# Expected: no matches

# 3. Consumer imports ApprovedStyleReaderContract
grep -E 'use Platform\\\\Style\\\\Contracts\\\\ApprovedStyleReaderContract' platform/Style/ResolvedStyleConsumer.php
# Expected: 1 match

# 4. Consumer still has runtime consumption disabled
grep -E 'isRuntimeConsumptionEnabled.*return.*true' platform/Style/ResolvedStyleConsumer.php
# Expected: no matches

# 5. Gate passes
bash scripts/architecture/check_platform_style_consumer_boundary.sh
# Expected: PASS

# 6. Probe passes
php scripts/platform/probe_resolved_style_consumer.php
# Expected: PASS (5/5 scenarios)

# 7. Aggregate gates pass
bash scripts/architecture/run_architecture_gates.sh
# Expected: PASS (pre-existing unrelated failures documented)

# 8. No whitespace errors
git diff --check
```

### Step 6 — Diagnostics Update Validation

```bash
# 1. Consumer diagnostics include reader reachability
grep -E '(RSC-C00[1-5]|reader_reachable|readerReachable|reader_unavailable)' platform/Style/ResolvedStyleConsumer.php
# Expected: at least 2 matches (reachable + unavailable)

# 2. Gate passes
bash scripts/architecture/check_platform_style_consumer_boundary.sh

# 3. Full regression
bash scripts/architecture/run_architecture_gates.sh
bash scripts/system/check_deployment_readiness.sh

# 4. No whitespace
git diff --check
```

### Continuous Validation

After each step, the following must always pass:

```bash
# 1. Consumer boundary gate (this plan's subject)
bash scripts/architecture/check_platform_style_consumer_boundary.sh

# 2. Probe boundary gate (guardian of probe contract)
bash scripts/architecture/check_read_only_consumption_probe_boundaries.sh

# 3. Aggregate gates (full system architecture invariants)
bash scripts/architecture/run_architecture_gates.sh

# 4. PHP lint (all changed files)
php -l platform/Style/ResolvedStyleConsumer.php
php -l platform/Style/Contracts/ApprovedStyleReaderContract.php
php -l platform/Style/Adapters/ApprovedStyleReaderAdapter.php

# 5. Whitespace hygiene
git diff --check

# 6. Probe functional test
php scripts/platform/probe_resolved_style_consumer.php
```

---

## Part J — Risk Review

### Risk 1 — Adapter Exception Creates a Hole

| Dimension | Value |
|---|---|
| **Description** | The adapter is the only file exempted from the ApprovedStyleRegistry block. If a future developer adds code to the adapter that exposes write methods or bypasses path confinement, the gate would not catch it at the directory level. |
| **Severity** | Medium |
| **Likelihood** | Low |
| **Mitigation** | Three separate adapter-specific invariants (setValue blocked, isWritable blocked, path patterns restricted). Code review required for any adapter change. The adapter file is small by design (~60-80 lines). |
| **Gate coverage** | ✅ setValue( check on adapter file. ✅ isWritable( check on adapter file. |

### Risk 2 — Consumer Directly Imports Registry

| Dimension | Value |
|---|---|
| **Description** | After relaxation, the consumer might be tempted to import ApprovedStyleRegistry directly instead of going through the contract + adapter. |
| **Severity** | High |
| **Likelihood** | Low |
| **Mitigation** | Explicit invariant: `use Apps\\Platform\\StyleRegistry` blocked in consumer file. Consumer may only import `ApprovedStyleReaderContract`. |
| **Gate coverage** | ✅ `use Apps\\Platform\\StyleRegistry` blocked in consumer. ✅ `getValue` blocked in consumer. |

### Risk 3 — Non-Adapter Files Gain Registry Access

| Dimension | Value |
|---|---|
| **Description** | New files added under `platform/Style/` (not contracts, not adapter, not consumer) might match the exemption pattern or bypass via a different namespace path. |
| **Severity** | Medium |
| **Likelihood** | Low |
| **Mitigation** | The gate blocks ApprovedStyleRegistry/getValue in ALL files EXCEPT the adapter auto-detected path. New files are automatically added to `${platform_style_files[@]}` by the dynamic `find` scan. |
| **Gate coverage** | ✅ Dynamic file discovery — no file can escape scanning. ✅ Non-adapter filter removes only adapter from the block. |

### Risk 4 — Storage Path Leaks Outside Adapter

| Dimension | Value |
|---|---|
| **Description** | `storage/platform/style-registry` or `approved-values` strings appear in non-adapter files (comments, error messages, diagnostic strings). |
| **Severity** | Low |
| **Likelihood** | Medium |
| **Mitigation** | Storage paths are blocked in ALL non-adapter files. The consumer must not know or reference the storage layout. |
| **Gate coverage** | ✅ `storage/platform/style-registry|approved-values` blocked in non-adapter files. |

### Risk 5 — Runtime Consumption Flag Accidentally Enabled

| Dimension | Value |
|---|---|
| **Description** | During consumer constructor injection or readValue implementation, a developer accidentally sets `$runtimeConsumptionEnabled = true`. |
| **Severity** | High |
| **Likelihood** | Low |
| **Mitigation** | Three separate gate checks for the flag remain unchanged. Registry read implementation must not touch these checks. |
| **Gate coverage** | ✅ Three independent grep checks (lines 152-173). All remain unchanged. |

---

## Deliverable Summary

### 1. Plan Path

```
docs/architecture/registry-read-boundary-gate-update-plan.md
```

### 2. Current Gate Groups Reviewed

10 invariant groups reviewed (Part A). Groups 1-9 (file exists, disabled state, no Studio, no Shell, no writes, no shell/compiler, no routes, no DB/HTTP, no draft/approval) must remain unchanged. Group 10 (registry path blocked, 2 invariants) is the relaxation target.

### 3. Exact Relaxation Proposal

Replace 2 blanket invariants with 5+ targeted invariants (Part D):

| Current (blocked everywhere) | Future | Scope |
|---|---|---|
| `'storage/platform/style-registry\|approved-values'` | Blocked in non-adapter files, allowed in adapter | Per-file exception |
| `'ApprovedStyleRegistry\|getValue\('` | Blocked in non-adapter + consumer, allowed in adapter | Per-file exception |
| — | NEW: `setValue(` blocked in adapter | Adapter-specific |
| — | NEW: `isWritable(` blocked in adapter | Adapter-specific |
| — | NEW: `use Apps\\Platform\\StyleRegistry` blocked in consumer | Consumer-specific |
| — | NEW: `getValue(` blocked in consumer | Consumer-specific |
| — | NEW: `setValue/isWritable/deleteValue` blocked in contract | Contract purity |

### 4. File Scope Rules

Eight rules across four files (Part E):
- **Consumer**: contract import only, no registry, no storage paths, no getValue
- **Contract**: read methods only, no write/governance methods, no Apps imports
- **Adapter**: sole bridge, may import registry and call getValue, must not call setValue/isWritable, must enforce path confinement
- **All other platform/Style/ files**: no registry access whatsoever

### 5. Still-Forbidden Patterns

22 pattern groups remain permanently forbidden (Part C):

| Category | Patterns |
|---|---|
| Write | file_put_contents, fwrite, mkdir, unlink, rename, chmod, chown, copy, rmdir |
| Shell | exec, shell_exec, proc_open, system, passthru, compile_theme_sources, ThemeCompiler |
| Registry write | setValue, isWritable, deleteValue |
| Studio | Apps\Studio, apps/Studio, StudioController, CustomizationStudio, VisualCustomizer, CssTokenEditor, ThemeTool |
| Shell | Apps\Shell, apps/Shell/styles, ShellStyleService, ShellRuntime, ThemePreferenceService |
| Routes | Route::, routes.php, POST, GET route |
| DB/HTTP | PDO, mysqli, curl_, file_get_contents(URL), stream_context_create |
| Draft/approval | storage/studio/customization, studio_draft, visual-customizer.*draft, approval-request, ApprovalRequest, pending_approval, studio_visual_customizer_requests |
| Runtime enable | isRuntimeConsumptionEnabled.*return.*true, runtimeConsumptionEnabled=true, runtime_consumption_enabled=>true |

### 6. Runtime Consumption Flag Rule

Three existing flag checks (Part F) remain unchanged:
- `isRuntimeConsumptionEnabled()` must return `false`
- `$runtimeConsumptionEnabled` must not be `true`
- `diagnostics['runtime_consumption_enabled']` must be `false`

Registry read is not runtime consumption. The flag is the Shell integration gate. It must not be opened by registry-read Phase 2.

### 7. Diagnostics Plan

5 core RSC-C codes (Part G) with gate-level validation:

| Code | Severity | Meaning | Gate Role |
|---|---|---|---|
| RSC-C001 | PASS | Reader reachable | Verify consumer reports it |
| RSC-C002 | PASS | Value found | Verify readValue returns data |
| RSC-C003 | WARN | Value absent | Implementation detail (no gate invariant) |
| RSC-C004 | FAIL | Reader unavailable | Verify consumer handles gracefully |
| RSC-C005 | FAIL | Unauthorized access blocked | Verify adapter has confinement |

8 extended RSC-C codes documented in Registry Read Contract (Part G), not gate-enforced.

### 8. Implementation Sequence

6-step ordered sequence (Part H):

```
Step 1 — Gate Update Plan         (✅ THIS DOCUMENT)
Step 2 — Gate Update              (modify check_platform_style_consumer_boundary.sh)
Step 3 — ApprovedStyleReaderContract (create interface)
Step 4 — ApprovedStyleReaderAdapter  (create adapter)
Step 5 — Consumer Constructor Injection (wire consumer)
Step 6 — Diagnostics & Probe Update   (finalize)
```

Each step authorizes only itself. No step may be skipped. Shell integration is not authorized by any step.

### 9. Risks and Mitigations

5 risks assessed (Part J), all Medium or Low severity with specific gate-invariant mitigations:

| Risk | Severity | Primary Mitigation |
|---|---|---|
| Adapter exception creates hole | Medium | 3 adapter-specific invariants + code review |
| Consumer imports registry directly | High | Explicit consumer import/block invariant |
| Non-adapter files gain access | Medium | Dynamic file discovery catches all new files |
| Storage path leaks outside adapter | Low | Path patterns blocked in all non-adapter files |
| Runtime consumption flag enabled | High | 3 independent unchanged checks |

### Recommended Next Slice

```
Registry Read Boundary Gate Update
```

Not registry-read implementation.

The next slice modifies `check_platform_style_consumer_boundary.sh` per Part D, replacing the 2 blanket invariants with 5+ targeted invariants. The gate must pass before any contract, adapter, or consumer work begins.

The 6-step implementation sequence (Part H) must be followed in order. Each step requires explicit authorization.
