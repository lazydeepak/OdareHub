<?php
declare(strict_types=1);

namespace Platform\Updates;

use ZipArchive;

final class LocalReleaseApplyPreparationService
{
    /** @var array<int,string> */
    private array $blockedPayloadPrefixes = ['storage', 'packages', '.git', 'node_modules', 'tests'];

    /**
     * @param array<string,mixed> $installedState
     * @param array<string,mixed> $recoveryEvidence
     * @return array<string,mixed>
     */
    public function prepare(string $channelDirectory, string $stagingDirectory, array $installedState, array $recoveryEvidence): array
    {
        $preview = (new LocalReleaseChannelPreviewService())->preview($channelDirectory, $installedState, $recoveryEvidence);
        if (($preview['apply_eligible'] ?? false) !== true) {
            return $this->failure('Channel preview is not apply-eligible.', ['preview' => $preview]);
        }

        $stagingDirectory = rtrim($stagingDirectory, '/');
        if (!is_dir($stagingDirectory) || !is_writable($stagingDirectory)) {
            return $this->failure('Apply staging directory must already exist and be writable.');
        }
        if (!$this->directoryIsEmpty($stagingDirectory)) {
            return $this->failure('Apply staging directory must be empty.');
        }

        $release = $this->resolveRelease($channelDirectory);
        if (($release['ok'] ?? false) !== true) {
            return $release;
        }

        $packagePath = (string)$release['package_path'];
        $inspection = $this->inspectPackage($packagePath);
        if (($inspection['ok'] ?? false) !== true) {
            return $inspection;
        }

        $payloadDirectory = sys_get_temp_dir() . '/susankhya-local-apply-payload-' . bin2hex(random_bytes(8));
        if (!mkdir($payloadDirectory, 0775, true)) {
            return $this->failure('Unable to create apply payload staging directory.');
        }

        $zip = new ZipArchive();
        if ($zip->open($packagePath) !== true) {
            return $this->failure('Unable to open release package.');
        }
        if (!$zip->extractTo($payloadDirectory)) {
            $zip->close();
            return $this->failure('Unable to extract release package into apply staging.');
        }
        $zip->close();

        $plan = [
            'schema_version' => 'susankhya.local-apply-plan.v1',
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'final_state' => 'prepared_without_live_mutation',
            'channel_dir' => $channelDirectory,
            'staging_dir' => $stagingDirectory,
            'payload_dir' => $payloadDirectory,
            'release' => $preview['release'] ?? [],
            'package' => [
                'path' => $packagePath,
                'entry_count' => $inspection['entry_count'],
                'entries_sha256' => $inspection['entries_sha256'],
                'sample_entries' => $inspection['sample_entries'],
            ],
            'preserve_paths' => ['storage', 'packages'],
            'live_mutation_performed' => false,
            'next_required_gate' => 'controlled_local_apply_requires_operator_approval_and_post_apply_verification',
        ];

        $planPath = $stagingDirectory . '/apply-plan.json';
        $encoded = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || file_put_contents($planPath, $encoded . "\n") === false) {
            return $this->failure('Unable to write apply preparation plan.');
        }

        return [
            'ok' => true,
            'errors' => [],
            'warnings' => [],
            'final_state' => 'prepared_without_live_mutation',
            'apply_plan_path' => $planPath,
            'payload_dir' => $payloadDirectory,
            'release' => $preview['release'] ?? [],
            'package' => [
                'entry_count' => $inspection['entry_count'],
                'entries_sha256' => $inspection['entries_sha256'],
            ],
            'live_mutation_performed' => false,
        ];
    }

    /** @return array<string,mixed> */
    private function resolveRelease(string $channelDirectory): array
    {
        $channelPath = rtrim($channelDirectory, '/') . '/channel.json';
        $channel = is_file($channelPath) ? json_decode((string)file_get_contents($channelPath), true) : null;
        if (!is_array($channel)) {
            return $this->failure('Channel JSON is unavailable.');
        }

        $current = $channel['lanes']['app']['current'] ?? null;
        if (!is_array($current)) {
            return $this->failure('Channel app lane current release is unavailable.');
        }

        $packagePath = $this->containedPath($channelDirectory, (string)($current['package'] ?? ''));
        if ($packagePath === null) {
            return $this->failure('Channel package path is not contained.');
        }

        return ['ok' => true, 'package_path' => $packagePath];
    }

    /** @return array<string,mixed> */
    private function inspectPackage(string $packagePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($packagePath) !== true) {
            return $this->failure('Unable to open release package.');
        }

        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string)$zip->getNameIndex($index);
            $normalized = trim(str_replace('\\', '/', $name), '/');
            if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, '..')) {
                $zip->close();
                return $this->failure('Release package contains an unsafe path.');
            }
            if ($this->isBlockedPayloadPath($normalized)) {
                $zip->close();
                return $this->failure('Release package contains a preserved or blocked path: ' . $normalized);
            }
            $entries[] = $normalized;
        }
        $zip->close();

        sort($entries);

        return [
            'ok' => true,
            'entry_count' => count($entries),
            'entries_sha256' => hash('sha256', implode("\n", $entries)),
            'sample_entries' => array_slice($entries, 0, 20),
        ];
    }

    private function containedPath(string $root, string $relativePath): ?string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || str_starts_with($relativePath, '/') || str_contains($relativePath, '..')) {
            return null;
        }

        return rtrim($root, '/') . '/' . $relativePath;
    }

    private function directoryIsEmpty(string $path): bool
    {
        $items = scandir($path);
        if ($items === false) {
            return false;
        }

        return array_values(array_diff($items, ['.', '..'])) === [];
    }

    private function isBlockedPayloadPath(string $relativePath): bool
    {
        foreach ($this->blockedPayloadPrefixes as $prefix) {
            if ($relativePath === $prefix || str_starts_with($relativePath, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $details */
    private function failure(string $message, array $details = []): array
    {
        return [
            'ok' => false,
            'errors' => [$message],
            'warnings' => [],
            'details' => $details,
        ];
    }
}
