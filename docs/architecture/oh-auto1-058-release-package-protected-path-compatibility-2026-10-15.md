# OH-AUTO1-058 — Release Package Protected-Path Compatibility

Date: 2026-10-15
Status: **Bounded REL-004 correction implemented; policy evidence complete**

## 1. Scope and safety

This slice resolves the vendor-package compatibility conflict without changing
release authority, installed identity, package publication, installation,
database state, or deployment behavior. All evidence uses temporary ZIP/source
fixtures. No database connection, SQL, migration, module activation, release
apply, or production package was used.

## 2. Exact conflict and rejection mechanism

`platform/Updates/ReleasePackageInspectionService.php::isProtectedPath()`
applies this path rule to every ZIP entry at every depth:

```text
(^|/)(phpunit\.xml[^/]*|composer\.cache)(/|$)
```

It also rejects protected prefixes such as `tests`, `storage`, `tmp`, and
`cache`, and rejects unsafe, duplicate/colliding, non-regular, or traversal
entries. Consequently:

```text
vendor/ratchet/rfc6455/phpunit.xml.dist
```

was rejected as a protected installation path even though `vendor/` is a
mandatory runtime root and this metadata is not needed by Composer's runtime
autoload path.

The inspector behavior is intentionally retained. A raw package containing
the path must still fail; allowing it in the inspector would weaken the
security boundary for packages supplied by any source.

## 3. Runtime dependency analysis

The V1 package contract requires the lockfile-resolved `vendor/` tree because
some deployments cannot run Composer. The runtime dependency evidence needed
for this correction is:

- `vendor/autoload.php` remains included;
- ordinary vendor package runtime files remain included;
- `composer.json` and `composer.lock` remain required and validated;
- vendor package test/configuration/documentation material is not required for
  Composer autoloading in the fixture model; and
- no arbitrary vendor entry is reclassified as safe.

This is a packaging-boundary decision, not a claim that every vendor file is
safe. Only named development patterns are filtered, and the post-build package
still passes the unchanged strict inspector.

## 4. Recommended V1 policy

### Build-time filtering — selected

The builder is the correct owner because it knows whether a path is being
copied from the source tree. It now excludes only vendor-owned development
patterns before inspection:

- `vendor/**/tests/**` and `vendor/**/test/**`;
- `vendor/**/docs/**` and `vendor/**/doc/**`;
- `vendor/**/examples/**` and `vendor/**/example/**`;
- `vendor/**/.github/**`;
- `vendor/bin/**` development executables/caches;
- `vendor/**/phpunit.xml*`; and
- `vendor/**/composer.cache`.

All other vendor paths remain candidates for inclusion. The existing caller
provided exclusions remain active for installation-owned roots such as
`storage/`.

### Context-aware inspector allowance — rejected for V1

The alternative would allow selected vendor paths inside the inspector. That
would make package acceptance depend on path ownership/context and would risk
allowing equivalent development or credential files in future package shapes.
It also would make raw package inspection inconsistent with the contract's
protected-path rule. It is deferred unless a future dependency proves a
specific runtime requirement that cannot be handled at build time.

## 5. Security properties preserved

- Root-level `phpunit.xml*` remains rejected.
- Protected `storage/db_config.php` remains rejected.
- Path traversal remains rejected.
- ZIP symlinks/non-regular entries remain rejected.
- Missing vendor runtime content remains rejected by required-root checks.
- Corrupt Composer metadata remains rejected.
- Duplicate/case-normalization collisions remain rejected.
- Package SHA-256 and byte-size binding remain unchanged.
- Build-time filtering does not authorize package apply or publication.

The builder's filtered output is inspected by the same
`ReleasePackageInspectionService`; filtering is not a bypass.

## 6. Implemented changes

- `platform/Updates/LocalReleaseChannelBuilderService.php`
  - added narrowly scoped vendor development-path filtering;
  - preserved caller exclusions and unchanged post-build inspection.
- `platform/Updates/tests/probe_release_package_protected_path_compatibility.php`
  - added disposable fixture coverage for valid vendor runtime files,
    vendor PHPUnit metadata, vendor cache, root PHPUnit configuration,
    protected credentials, traversal, symlinks, missing vendor files,
    corrupt Composer metadata, and checksum/size records.

No production package was built or published; the builder was exercised only
against temporary fixture directories.

## 7. Verification evidence

- REL-004 compatibility probe: **15/15 PASS**.
- Existing local release builder probe: **13/13 PASS**.
- Existing local channel preview probe: **30/30 PASS**.
- Existing apply-preparation probe: **7/7 PASS**.
- Existing release-readiness evidence probe: **10/10 PASS**.
- PHP lint: PASS for changed service and probe.
- `git diff --check`: PASS.

## 8. Remaining decisions and limits

- REL-003 migration ordering, schema-target compatibility, downgrade policy,
  and rollback remain unresolved.
- REL-001/REL-002 remain proposal-level contracts pending approval.
- Vendor dependency upgrades require rerunning package fixture and inspection
  coverage; a new runtime-required development file must not be allowed by
  changing the inspector without separate policy review.
- Database requalification, production publication, installation, and release
  apply remain separately restricted.

## Evidence

- `docs/architecture/local-release-channel-contract.md`
- `docs/architecture/deployment-update-foundation-audit.md`
- `platform/Updates/ReleasePackageInspectionService.php`
- `platform/Updates/LocalReleaseChannelBuilderService.php`
- `platform/Updates/tests/probe_local_release_channel_builder.php`
- `platform/Updates/tests/probe_local_release_channel_preview.php`
