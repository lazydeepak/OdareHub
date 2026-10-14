# OH-AUTO1-062 — Production Composer Autoload Portability

Date: 2026-10-15
Status: **Autoload correction implemented; full dev-package pruning remains policy-gated**

## Scope and safety

The release builder now prepares a disposable source copy before Composer is
run. Composer is never run in the repository or installed application. The
preparation uses:

```text
composer dump-autoload --no-dev --no-scripts --no-plugins --no-interaction
```

with `COMPOSER_DISABLE_NETWORK=1`, from the runner-owned staging directory.
The strict package inspector is unchanged. No database, SQL, migration,
bootstrap, installation, activation, publication, or deployment operation was
performed.

## Before and after

OH-AUTO1-061 found 147 unique missing vendor autoload targets, represented by
295 duplicated classmap/static references. The missing targets belonged to
PHPUnit and code-coverage development material; the root `Tests\\` mapping was
also present through `autoload-dev`.

After isolated production autoload generation:

- missing production autoload targets: **0**;
- root `Tests\\` mapping: **absent**;
- PHPUnit/code-coverage targets in production autoload metadata: **absent**;
- all PSR-4, classmap, and autoload-file targets: contained and present;
- repository `composer.json` and `composer.lock`: unchanged.

## Actual-source package proof

The real repository source was staged with the OH-AUTO1-044 runner and the
additional `public/`, `resources/`, and `bin/` runtime roots. The corrected
builder produced a disposable package with:

- source snapshot input: **5,413 files**;
- package files: **5,263**;
- ZIP entries including internal metadata: **5,264**;
- package size: **26,074,112 bytes**;
- package SHA-256:
  `df103e5c1d1778cad0b3a71eea23006b48adf7049083e85125ee82a465ae1a2e`;
- strict inspector: **PASS**;
- sidecar/channel checksum and size binding: **PASS**;
- extracted-package Composer closure: **0 missing targets**.

The package retained runtime dependency material, including Dompdf, and the
generated runtime Composer loader. No temporary or repository path was written
by the Composer step.

## Deliberately unresolved policy boundary

`dump-autoload --no-dev` changes autoload metadata; it does not remove the
development packages already present in an installed vendor tree. The package
therefore does not claim a fully pruned development dependency closure. A full
prune would require an approved Composer installation/metadata policy (or an
equivalent governed production dependency materialization step). This slice
does not run `composer install`, rewrite generated Composer inventory files, or
silently invent that policy.

The current correction therefore closes the broken autoload boundary while
leaving the narrower dev-package distribution decision explicit for release
authority approval.

## Validation

- Production Composer portability probe: **16/16**
- REL-004 protected-path compatibility: **15/15**
- Local release builder: **13/13**
- Local release preview: **30/30**
- Local apply preparation: **7/7**
- Release readiness evidence collector: **19/19**
- Isolated source-root runner: **17/17**
- PHP lint and `git diff --check`: **PASS**
