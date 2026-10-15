# OH-AUTO1-080 — Disposable Release-Cycle Safety Closure

## Result

The four OH-AUTO1-079 acceptance gaps are corrected. The final disposable
signed installation/update/recovery rehearsal passed **49/49** assertions.
No installed database, production SQL, real installation, deployment,
production signing, or Core operation was used.

## Corrections and evidence

### Isolation before SQL

`run_disposable_signed_release_recovery.php` now performs an explicit negative
attestation using a refused socket, then performs the full process/datadir/
socket/network/configuration attestation before the first `CREATE DATABASE`,
`CREATE USER`, `GRANT`, or application SQL. The rehearsal reports both:

- `PASS attestation refusal blocks SQL authorization`
- `PASS independent MySQL process/socket/datadir attestation passes before SQL`

MySQL initialization remains filesystem-only inside the disposable resource
root. The server uses `--no-defaults`, an attested socket, loopback binding,
and a dynamic disposable port.

### Mandatory identity finalization

`DisposableReleaseTransactionService::complete()` now requires a finalizer
callable. Completion validates structured candidate-bound identity evidence,
including build, release version, package SHA-256, release statement,
installation ID, target schema, matching identity/read-back records, and
matching read-back fingerprints. A missing finalizer, malformed evidence, or
finalization exception cannot journal `completed`; it journals
`recovery_required`.

The transaction probe passes **24/24**, including explicit no-finalizer and
identity-finalization-failure negatives.

### Complete database and filesystem recovery

Failure recovery now verifies baseline table inventory, product setting,
application version/build manifest, migration name/status/checksum, schema
identity/checksum, and absence of the candidate-only table. The complete
replaceable filesystem inventory is compared after copying for `app`, `apps`,
`platform`, `plugins`, `public`, `resources`, `vendor`, and `bin`, plus
`composer.json` and `composer.lock` hashes. Protected configuration and
installation-owned trust remain preserved.

The rehearsal reports baseline row/checksum assertions and complete inventory
assertions, then re-runs the candidate migration, reactivates candidate files,
re-verifies health, and commits the candidate identity.

### Failure cleanup

The runner no longer exits from its exception handler. It records a controlled
exit status, always executes `finally` cleanup, terminates the owned MySQL
process, removes the source workspace and rehearsal workspace, and only then
exits.

With `OH_AUTO1_080_INJECT_AFTER_SQL=1`, the actual runner produced exit status
2 after SQL mutation and reported:

```text
workspace=absent source_workspace=absent mysql_process=absent
```

No `o78-*` or `o78s-*` temporary roots remained after the cleanup test.

## Executed verification

- Complete disposable signed cycle: **49/49**
- Transaction controls: **24/24**
- Restore rehearsal: **10/10**
- Governed exact-step migration suite: **4 suites passed**, including **13/13** exact-step assertions
- Coordinator preflight: **26/26**
- Trust: **28/28**
- Identity persistence: **33/33**
- Migration compatibility/governance: **16/16**, **18/18**
- Recovery provider/point/backup probes: **13/13**, **9/9**, **19/19**
- Signature/artifact/readiness probes: **24/24**, **18/18**, **10/10**, **19/19**
- Architecture gates: **49/49**
- Deletion gates: **28/28**
- Deployment readiness: **PASS** in read-only verify mode
- Core lock, PHP lint, and diff hygiene: **PASS**

The non-production release-cycle proof is closed. Production readiness,
installed execution, and production update authority remain separate blocked or
human-gated work.
