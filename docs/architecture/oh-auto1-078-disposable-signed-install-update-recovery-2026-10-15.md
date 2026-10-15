# OH-AUTO1-078 — Disposable Signed Install/Update/Recovery Proof

## Result

The first real disposable execution rehearsal passed **25/25** assertions.
Every mutable target was under one runner-owned system-temporary root. The
repository, installed ServBay MySQL process, installed database, production
trust, production identity, deployment, activation, and production signing
were not used.

## Isolation evidence

- OH-AUTO1-044 staged the source outside the repository and recorded no
  bootstrap, database connection, or SQL execution.
- MySQL 9.6 was initialized with `--no-defaults`, a unique temporary datadir,
  unique socket, and loopback-only port `3307`.
- `DisposableDbExecutionAuthorizer` independently matched the live process,
  datadir, socket, ownership, command flags, and server observations before
  any fixture SQL.
- Trust and identity stores were created under the same temporary root and
  used only their disposable provisioning/replacement seams.

## Successful signed update

The rehearsal built immutable initial and candidate ZIP/sidecar artifacts,
signed the candidate release statement with an ephemeral Ed25519 key, enrolled
the corresponding trust record through the explicit disposable seam, and ran
fresh coordinator preflight. The coordinator independently verified the exact
package, sidecar, statement, signature, trust, identity, installed state,
migration plan, compatibility, and recovery evidence.

The existing exact-step migration authority applied exactly
`Platform:oh078_001_release.sql`. Candidate package bytes supplied activation
of the release marker. Fixed-source post-update health verified release
identity, schema, migration ledger/checksum, bootstrap, application
discovery, connectivity, trust/configuration, protected-path integrity, and
unresolved state. Installation identity replacement occurred only afterward.

## Failure and recovery

A second transaction was explicitly approved and moved to `executing`. The
runner then injected a post-mutation filesystem/database failure, classified
that transaction as `recovery_required`, and restored the actual disposable
database from the runner-created compressed dump and the filesystem from the
runner-created archive. The initial identity was restored with its
fingerprint-bound replacement path. Database absence of the candidate marker,
filesystem marker restoration, identity restoration, and terminal journal
classification were all asserted.

## Authority hardening

`TrustedReleasePreflightEvidence` is the only accepted transaction evidence
type; ordinary caller arrays and fixed environment tokens cannot authorize
transaction transitions. `PostUpdateHealthVerificationResult` no longer has a
public construction factory; only `PostUpdateHealthVerifier` can issue an
opaque result through its private-constructor closure.

## Validation

- Disposable signed install/update/recovery rehearsal: **25/25**
- Transaction control regression: **21/21**
- Architecture gates: **49/49**
- Deletion gates: **28/28**
- Deployment readiness: **PASS** (read-only verify mode)
- Core lock: **PASS**
- PHP lint and `git diff --check`: **PASS**

No installed database, production SQL, production signing, deployment,
activation, or production identity/trust mutation occurred.
