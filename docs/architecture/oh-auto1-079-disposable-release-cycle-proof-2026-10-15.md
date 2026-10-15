# OH-AUTO1-079 — Complete Disposable Release-Cycle Proof

## Result

OH-AUTO1-078 was corrected in place and the bounded disposable proof now
passes **34/34** runtime assertions. The existing real disposable signed
installation/update/recovery path remains in use; no simulation replaced the
database, filesystem, migration, identity, or trust operations.

## Corrections

### Verified pre-update restore

`RestoreRehearsalService` now requires the database callback to return explicit
`verified=true`. The rehearsal callback decompresses the runner-created dump,
strips only disposable-server-incompatible GTID/binlog session directives,
imports the SQL through the attested socket, and queries the separate restore
database. It verifies the complete baseline table set, product setting,
application version, migration name/checksum, and schema identity before
`rehearsal.json` is written.

Filesystem rehearsal now hashes every non-directory ZIP entry after extraction,
rejects symlinks, and returns complete content evidence rather than only an
entry count.

### Isolation ordering

The runner chooses a free loopback port, starts MySQL with `--no-defaults`,
socket-only command configuration plus an explicit loopback port, disables
the X plugin, and runs `DisposableDbExecutionAuthorizer` before any
`CREATE DATABASE`, user, grant, or application SQL. Cleanup terminates the
attested process and removes runner-owned temporary roots on success and
failure.

### Runtime health observations

The nine health checks now read the runner-owned installation and disposable
database: runtime release identity, latest schema snapshot, migration rows and
checksums, Composer/bootstrap availability, installed application registry and
filesystem, live connectivity, trust/identity stores, protected configuration
hash, and unresolved migration/recovery state. No expected observation is
used as a substitute for a source read.

### Coordinator authority

The execution boundary invokes the authoritative coordinator again immediately
before transaction preparation and compares the candidate evidence fingerprint
to that fresh result. A fully populated public-factory evidence array with a
forged diagnostic is rejected at that boundary.

### Completion ordering

`DisposableReleaseTransactionService::complete()` accepts an optional identity
finalizer and journals `completed` only after the finalizer returns verified
read-back evidence. A fault injected at identity finalization leaves the
transaction `recovery_required`; the rehearsal also preserves and explicitly
cleans the resulting interrupted identity-write evidence before controlled
restore.

### Complete recovery

Recovery restores the actual compressed database dump and verifies baseline
tables and observations. It then replaces every backed-up application/runtime
root (`app`, `apps`, `platform`, `plugins`, `public`, `resources`, `vendor`,
`bin`, and Composer metadata), while preserving protected configuration and
installation-owned trust. Candidate-only migration files are proven absent.
Actual baseline post-restore health passes before the candidate migration and
activation are re-applied for the successful transaction completion.

## Executed evidence

- Complete disposable signed cycle: **34/34**
- Transaction control regression: **23/23**
- Restore rehearsal probe: **10/10**
- Architecture gates: **49/49**
- Deletion gates: **28/28**
- Deployment readiness: **PASS** in read-only verify mode
- Core lock, PHP lint, and diff hygiene: **PASS**

No installed database, production SQL, deployment, production signing, or
Core modification occurred. Unrelated lifecycle logs and PHPUnit cache remain
uncommitted and preserved.
