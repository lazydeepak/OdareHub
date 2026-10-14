# OH-AUTO1-063 — Immutable Release Artifact Protection

Date: 2026-10-15
Status: **Implemented and verified**

## Finding

The local release builder opened the identity-derived ZIP path with
`ZipArchive::OVERWRITE`. A second build could therefore replace the payload
under the same release version/build identity, and normalized build IDs could
collide on the same filename.

## Correction

The builder now:

1. checks package and sidecar identity paths before preparation;
2. rejects occupied or incomplete artifact records;
3. validates existing channel references instead of assuming they are safe to
   replace;
4. builds and inspects the ZIP under a runner-owned temporary directory while
   retaining the final package basename;
5. finalizes the package and sidecar with non-overwriting hard-link commits;
6. writes channel metadata through a temporary JSON file and atomic rename only
   after package and sidecar finalization succeeds; and
7. removes all builder-owned temporary files/directories on success or failure.

The strict package inspector and release identity format are unchanged.

## Regression evidence

The disposable immutability probe passed **13/13**, covering:

- first successful build;
- same-identity replacement refusal;
- repeated identity refusal;
- package, sidecar, and channel hash preservation;
- normalized build-ID collision refusal;
- sidecar-only incomplete record refusal and preservation;
- inspection failure refusal;
- failed-build channel preservation; and
- temporary artifact cleanup.

Existing release probes remain passing: REL-004 **15/15**, builder **13/13**,
preview **30/30**, apply preparation **7/7**, readiness evidence **19/19**,
and production Composer portability **16/16**. Architecture gates remain
**49/49** and read-only deployment readiness remains **PASS**.

No database, SQL, migration, installation, activation, publication, deployment,
signing, or Core operation occurred.
