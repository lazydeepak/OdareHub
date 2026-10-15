<?php
declare(strict_types=1);

namespace Platform\Updates;

use Platform\Recovery\RecoveryPointService;
use DateTimeImmutable;
use Throwable;

final class LocalReleaseChannelPreviewService
{
    /**
     * @param array<string,mixed> $installedState
     * @param string|null $recoveryPointDirectory
     * @param array<string,mixed>|null $authenticityInput Explicit, in-memory
     *        verification inputs; no release metadata authenticity is trusted.
     * @return array<string,mixed>
     */
    public function preview(string $channelDirectory, array $installedState, ?string $recoveryPointDirectory = null, ?array $authenticityInput = null): array
    {
        $blockers = [];
        $warnings = [];
        $applyBlockers = [];

        $channel = $this->readJson($channelDirectory . '/channel.json', $blockers, 'channel');
        if ($channel === null) {
            return $this->result(false, false, $blockers, $warnings, $applyBlockers);
        }

        $this->validateChannel($channel, $blockers);
        $current = $channel['lanes']['app']['current'] ?? null;
        if (!is_array($current)) {
            $blockers[] = 'channel app lane current release is required.';
            return $this->result(false, false, $blockers, $warnings, $applyBlockers);
        }

        $metadataPath = $this->containedPath($channelDirectory, (string)($current['metadata'] ?? ''));
        $packagePath = $this->containedPath($channelDirectory, (string)($current['package'] ?? ''));
        if ($metadataPath === null || $packagePath === null) {
            $blockers[] = 'channel release paths must be contained relative paths.';
            return $this->result(false, false, $blockers, $warnings, $applyBlockers);
        }

        $metadata = $this->readJson($metadataPath, $blockers, 'release metadata');
        if ($metadata === null) {
            return $this->result(false, false, $blockers, $warnings, $applyBlockers);
        }

        $this->validateMetadata($metadata, $blockers);
        $this->validateChannelMetadataBinding($current, $metadata, $blockers);
        $this->validatePackageBytes($packagePath, $metadata['package'] ?? [], $blockers);
        $packageInspection = (new ReleasePackageInspectionService())->inspect($packagePath, $metadata);
        if (($packageInspection['ok'] ?? false) !== true) {
            foreach ((array)($packageInspection['errors'] ?? []) as $error) {
                $blockers[] = 'Release package: ' . (string)$error;
            }
        }
        $artifact = [
            'release_version' => (string)($metadata['release_version'] ?? ''),
            'build_id' => (string)($metadata['build_id'] ?? ''),
            'package_sha256' => (string)($metadata['package']['sha256'] ?? ''),
            'package_size_bytes' => (int)($metadata['package']['size_bytes'] ?? 0),
        ];
        $authenticity = $this->verifyAuthenticity(
            $packagePath,
            $metadataPath,
            $authenticityInput,
            $current
        );
        $readiness = is_array($metadata['readiness'] ?? null) ? $metadata['readiness'] : [];
        $readinessResult = (new ReleaseReadinessEvidenceService())->validate($readiness, $artifact);
        if (($readinessResult['ok'] ?? false) !== true) {
            foreach ((array)($readinessResult['errors'] ?? []) as $error) {
                $blockers[] = 'Readiness evidence: ' . (string)$error;
            }
        } elseif (($readinessResult['eligible'] ?? false) !== true) {
            $applyBlockers[] = 'Release readiness evidence is not authenticated by an authorized verifier.';
        }
        $compatibility = is_array($metadata['compatibility'] ?? null) ? $metadata['compatibility'] : [];
        $compatibilityResult = (new InstalledSystemCompatibilityPreflightService())->validate(
            $compatibility,
            $installedState,
            (string)($metadata['release_version'] ?? '')
        );
        if (($compatibilityResult['ok'] ?? false) !== true) {
            foreach ((array)($compatibilityResult['errors'] ?? []) as $error) {
                $blockers[] = 'Compatibility: ' . (string)$error;
            }
        }
        foreach ((array)($compatibilityResult['eligibility_blockers'] ?? []) as $blocker) {
            $applyBlockers[] = 'Migration compatibility: ' . (string)$blocker;
        }

        if (($metadata['migration']['requires_backup'] ?? true) === true) {
            if ($recoveryPointDirectory === null || trim($recoveryPointDirectory) === '') {
                $applyBlockers[] = 'An authoritative recovery-point directory is required before apply.';
            } else {
                $recovery = RecoveryPointService::verifyUpdateEvidence($recoveryPointDirectory, $installedState);
                if (($recovery['ok'] ?? false) !== true) {
                    $applyBlockers[] = 'Authoritative recovery evidence verification failed.';
                    foreach ((array)($recovery['errors'] ?? []) as $error) {
                        $blockers[] = 'Recovery evidence: ' . (string)$error;
                    }
                }
            }
        }

        if (($metadata['apply_eligibility']['requires_operator_approval'] ?? true) !== true) {
            $warnings[] = 'Release metadata does not require operator approval; V1 apply will still require it.';
        }

        return $this->result($blockers === [], $blockers === [] && $applyBlockers === [], $blockers, $warnings, $applyBlockers, [
            'channel' => (string)($channel['channel'] ?? ''),
            'release_version' => (string)($metadata['release_version'] ?? ''),
            'build_id' => (string)($metadata['build_id'] ?? ''),
            'package_sha256' => (string)($metadata['package']['sha256'] ?? ''),
            'package_size_bytes' => (int)($metadata['package']['size_bytes'] ?? 0),
            'readiness' => [
                'status' => $readinessResult['status'] ?? 'unknown',
                'checks' => $readinessResult['checks'] ?? [],
                'eligible' => $readinessResult['eligible'] ?? false,
            ],
            'authenticity' => $authenticity,
        ], $authenticity);
    }

    /**
     * @param array<int,string> $blockers
     * @return array<string,mixed>|null
     */
    private function readJson(string $path, array &$blockers, string $label): ?array
    {
        if (!is_file($path)) {
            $blockers[] = $label . ' file is missing.';
            return null;
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded)) {
            $blockers[] = $label . ' JSON is invalid.';
            return null;
        }

        return $decoded;
    }

    /** @param array<string,mixed> $channel @param array<int,string> $blockers */
    private function validateChannel(array $channel, array &$blockers): void
    {
        if (($channel['schema_version'] ?? null) !== 'susankhya.local-channel.v1') {
            $blockers[] = 'channel schema_version is invalid.';
        }
        if (($channel['product_id'] ?? null) !== 'susankhya-os') {
            $blockers[] = 'channel product_id is invalid.';
        }
        $this->validTimestamp($channel['generated_at'] ?? null, 'channel generated_at', $blockers);
    }

    /** @param array<string,mixed> $metadata @param array<int,string> $blockers */
    private function validateMetadata(array $metadata, array &$blockers): void
    {
        foreach (['schema_version', 'product_id', 'product_name', 'release_version', 'build_id', 'channel', 'lane', 'created_at'] as $field) {
            if (!is_string($metadata[$field] ?? null) || trim((string)$metadata[$field]) === '') {
                $blockers[] = 'release metadata ' . $field . ' is required.';
            }
        }
        if (($metadata['schema_version'] ?? null) !== 'susankhya.release.v1') {
            $blockers[] = 'release metadata schema_version is invalid.';
        }
        if (($metadata['product_id'] ?? null) !== 'susankhya-os') {
            $blockers[] = 'release metadata product_id is invalid.';
        }
        if (($metadata['lane'] ?? null) !== 'app') {
            $blockers[] = 'V1 only supports the app lane.';
        }
        $this->validTimestamp($metadata['created_at'] ?? null, 'release metadata created_at', $blockers);
        if (!is_array($metadata['package'] ?? null)) {
            $blockers[] = 'release metadata package object is required.';
        }
        if (!is_array($metadata['compatibility'] ?? null)) {
            $blockers[] = 'release metadata compatibility object is required.';
        }
        if (!is_array($metadata['readiness'] ?? null)) {
            $blockers[] = 'release metadata readiness object is required.';
        }
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $metadata
     * @param array<int,string> $blockers
     */
    private function validateChannelMetadataBinding(array $current, array $metadata, array &$blockers): void
    {
        foreach (['release_version', 'build_id'] as $field) {
            if (($current[$field] ?? null) !== ($metadata[$field] ?? null)) {
                $blockers[] = 'channel and metadata ' . $field . ' differ.';
            }
        }

        $package = is_array($metadata['package'] ?? null) ? $metadata['package'] : [];
        if (($current['sha256'] ?? null) !== ($package['sha256'] ?? null)) {
            $blockers[] = 'channel and metadata package sha256 differ.';
        }
        if ((int)($current['size_bytes'] ?? -1) !== (int)($package['size_bytes'] ?? -2)) {
            $blockers[] = 'channel and metadata package size differ.';
        }
    }

    /** @param array<string,mixed> $package @param array<int,string> $blockers */
    private function validatePackageBytes(string $packagePath, array $package, array &$blockers): void
    {
        if (!is_file($packagePath)) {
            $blockers[] = 'release package file is missing.';
            return;
        }

        $sha = (string)($package['sha256'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) {
            $blockers[] = 'release package sha256 format is invalid.';
            return;
        }
        if ((hash_file('sha256', $packagePath) ?: '') !== $sha) {
            $blockers[] = 'release package sha256 mismatch.';
        }
        if ((filesize($packagePath) ?: 0) !== (int)($package['size_bytes'] ?? -1)) {
            $blockers[] = 'release package size mismatch.';
        }
    }

    /** @param array<int,string> $blockers */
    private function validTimestamp(mixed $value, string $label, array &$blockers): void
    {
        if (!is_string($value) || trim($value) === '') {
            $blockers[] = $label . ' is required.';
            return;
        }
        try {
            new DateTimeImmutable($value);
        } catch (Throwable) {
            $blockers[] = $label . ' must be a valid timestamp.';
        }
    }

    private function containedPath(string $root, string $relativePath): ?string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || str_starts_with($relativePath, '/') || str_contains($relativePath, '..')) {
            return null;
        }

        return rtrim($root, '/') . '/' . $relativePath;
    }

    /**
     * @param array<int,string> $blockers
     * @param array<int,string> $warnings
     * @param array<int,string> $applyBlockers
     * @param array<string,mixed> $release
     * @param array<string,mixed>|null $authenticity
     * @return array<string,mixed>
     */
    private function result(bool $ok, bool $applyEligible, array $blockers, array $warnings, array $applyBlockers, array $release = [], ?array $authenticity = null): array
    {
        $authenticity ??= $this->authenticityUnavailable();
        return [
            'ok' => $ok,
            'apply_eligible' => $applyEligible,
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_unique($warnings)),
            'apply_blockers' => array_values(array_unique($applyBlockers)),
            'release' => $release,
            'authenticity' => $authenticity,
        ];
    }

    /**
     * Verify caller-supplied detached-signature evidence against the exact
     * package and sidecar bytes. This is diagnostic-only: it never changes
     * readiness eligibility or apply authority.
     *
     * @param array<string,mixed>|null $input
     * @param array<string,mixed> $channelRecord
     * @return array<string,mixed>
     */
    private function verifyAuthenticity(string $packagePath, string $metadataPath, ?array $input, array $channelRecord): array
    {
        if ($input === null) {
            return $this->authenticityUnavailable(
                'RCP-AUTH-INPUTS',
                'Detached signature verification inputs were not supplied.'
            );
        }

        foreach (['statement_bytes', 'detached_signature', 'key_id', 'trusted_keys', 'verification_timestamp'] as $field) {
            if (!array_key_exists($field, $input)) {
                return $this->authenticityUnavailable('RCP-AUTH-INPUTS', 'Detached signature verification input ' . $field . ' is missing.');
            }
        }
        if (!is_string($input['statement_bytes'])
            || !($input['detached_signature'] === null || is_string($input['detached_signature']))
            || !($input['key_id'] === null || is_string($input['key_id']))
            || !is_array($input['trusted_keys'])
            || !is_string($input['verification_timestamp'])) {
            return $this->authenticityUnavailable('RCP-AUTH-INPUTS', 'Detached signature verification inputs have invalid types.');
        }

        $verification = (new OfflineReleaseArtifactVerifier())->verify(
            $packagePath,
            $metadataPath,
            $input['statement_bytes'],
            $input['detached_signature'],
            $input['key_id'],
            $input['trusted_keys'],
            $input['verification_timestamp'],
            $channelRecord
        );

        return [
            'state' => $verification['state'] ?? OfflineReleaseSignatureVerifier::VERIFICATION_UNAVAILABLE,
            'verified' => ($verification['verified'] ?? false) === true,
            'key_id' => $verification['key_id'] ?? $input['key_id'],
            'diagnostics' => $verification['diagnostics'] ?? [],
            'artifact' => $verification['artifact'] ?? null,
        ];
    }

    /** @return array<string,mixed> */
    private function authenticityUnavailable(string $code = 'RCP-AUTH-UNAVAILABLE', string $message = 'Release authenticity verification is unavailable.'): array
    {
        return [
            'state' => OfflineReleaseSignatureVerifier::VERIFICATION_UNAVAILABLE,
            'verified' => false,
            'key_id' => null,
            'diagnostics' => [['code' => $code, 'message' => $message]],
            'artifact' => null,
        ];
    }
}
