# OH-AUTO1-064 — Offline Release Signature Verification

Date: 2026-10-15
Status: **Implemented and verified — production integration remains disabled**

## Scope

This phase adds a pure Platform Updates verification foundation. It does not
read packages, installed state, trust configuration, or databases; it does not
sign releases; and it does not participate in preview, apply eligibility,
installation, activation, or deployment.

## Public interface

### `ReleaseStatementCanonicalizer`

- `canonicalize(array $fields): string`
- `parse(string $bytes): array`
- `parseUtcTimestamp(string $value): ?DateTimeImmutable`

The exact V1 statement is UTF-8, LF-only, exactly one final newline, and has
this fixed order:

```text
odarehub-release-v1
product_id=<value>
release_version=<major.minor.patch>
build_id=<opaque ASCII build identifier>
lane=app
package_sha256=<64 lowercase hexadecimal characters>
package_bytes=<canonical decimal integer>
metadata_sha256=<64 lowercase hexadecimal characters>
minimum_supported_schema=<major.minor.patch>
created_at=<YYYY-MM-DDTHH:MM:SSZ>
```

Values are restricted to printable ASCII and may not contain `=`. Product and
lane are fixed to `susankhya-os` and `app`. Missing, duplicate, unexpected, or
reordered fields, invalid UTF-8, CRLF, extra newlines, alternate hash case,
noncanonical decimal values, invalid timestamps, and delimiter injection are
rejected. Parsing never normalizes malformed bytes into accepted bytes.

### `OfflineReleaseSignatureVerifier`

```php
verify(
    string $statementBytes,
    ?string $detachedSignature,
    ?string $keyId,
    array $trustedKeys,
    string $verificationTimestamp,
    ?array $artifactBinding = null
): array
```

`trustedKeys` is caller-supplied in-memory data. Each record must contain only
`key_id`, `algorithm`, `public_key`, `fingerprint`, `status`, `not_before`, and
`not_after`; use of an Ed25519 key, a SHA-256 public-key fingerprint, unique key
ID/fingerprint, and valid UTC interval is required. Revocation is checked
before signature acceptance. `artifactBinding`, when supplied, binds the
statement's package hash, metadata hash, and byte size to independently
provided evidence without reading those artifacts.

## Verification states

The verifier returns exactly one of: `verified`, `unsigned`,
`invalid_signature`, `untrusted_key`, `revoked_key`, `statement_mismatch`,
`expired_or_not_yet_valid`, or `verification_unavailable`. Results include
stable `RAV-*` diagnostics and never expose secret material.

Ed25519 verification uses PHP Sodium's detached verification primitive. Missing
Sodium, malformed key/signature lengths, malformed trust records, invalid key
windows, and invalid verification time fail closed. No private key, signing
path, shell command, network retrieval, or persistent trust store exists.

## Evidence

The deterministic fixture probe covers valid and wrong canonical messages,
modified statement/checksum bindings, invalid signatures, unknown/revoked/
expired/not-yet-valid keys, duplicate and malformed trust records, fingerprint
mismatch, noncanonical ordering, missing/duplicate fields, delimiter injection,
invalid UTF-8, wrong product/lane, unsigned input, invalid verification time,
and unavailable Sodium. It passes **24/24**.

Existing offline release probes and package behavior remain unchanged. No
production release is authenticated by this phase; the next bounded step is a
separately reviewed adapter that supplies actual package/metadata evidence to
this verifier without enabling release apply.
