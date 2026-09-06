<?php
declare(strict_types=1);

namespace Platform\Recovery;

use ZipArchive;

final class RestoreRehearsalService
{
    /** @var callable(string,array<string,mixed>):array<string,mixed> */
    private $databaseRehearsal;

    /**
     * @param callable(string,array<string,mixed>):array<string,mixed> $databaseRehearsal
     */
    public function __construct(callable $databaseRehearsal)
    {
        $this->databaseRehearsal = $databaseRehearsal;
    }

    /**
     * @param array<string,mixed> $metadata
     * @return array<string,mixed>
     */
    public function rehearse(array $metadata, string $recoveryPointDirectory, string $isolatedTargetDirectory): array
    {
        $validation = RecoveryPointMetadataValidator::validate($metadata);
        if (!$validation['ok']) {
            return $this->state('failed_without_mutation', $validation['errors']);
        }

        if (!is_dir($isolatedTargetDirectory) || !is_writable($isolatedTargetDirectory)) {
            return $this->state('failed_without_mutation', ['Isolated rehearsal target is not writable.']);
        }

        $databasePath = $this->containedPath($recoveryPointDirectory, (string)($metadata['database']['payload']['path'] ?? ''));
        $filesystemPath = $this->containedPath($recoveryPointDirectory, (string)($metadata['filesystem']['payload']['path'] ?? ''));
        if ($databasePath === null || $filesystemPath === null) {
            return $this->state('failed_without_mutation', ['Recovery payload path is not contained.']);
        }

        if (!$this->payloadMatches($databasePath, $metadata['database']['payload'])) {
            return $this->state('failed_without_mutation', ['Database payload checksum or size mismatch.']);
        }
        if (!$this->payloadMatches($filesystemPath, $metadata['filesystem']['payload'])) {
            return $this->state('failed_without_mutation', ['Filesystem payload checksum or size mismatch.']);
        }

        $databaseResult = ($this->databaseRehearsal)($databasePath, $metadata['database']['identity']);
        if (($databaseResult['ok'] ?? false) !== true) {
            return $this->state('failed_without_mutation', ['Database rehearsal failed.'], ['database' => $databaseResult]);
        }

        $extractResult = $this->extractFilesystemArchive($filesystemPath, $isolatedTargetDirectory);
        if (($extractResult['ok'] ?? false) !== true) {
            return $this->state('failed_without_mutation', ['Filesystem rehearsal failed.'], ['filesystem' => $extractResult]);
        }

        return [
            'ok' => true,
            'final_state' => 'restored',
            'errors' => [],
            'warnings' => [],
            'database' => $databaseResult,
            'filesystem' => $extractResult,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function payloadMatches(string $path, array $payload): bool
    {
        return is_file($path)
            && (hash_file('sha256', $path) ?: '') === (string)($payload['sha256'] ?? '')
            && (filesize($path) ?: 0) === (int)($payload['size_bytes'] ?? -1);
    }

    private function containedPath(string $root, string $relativePath): ?string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        $root = rtrim($root, '/');
        $candidate = $root . '/' . $relativePath;
        $parent = realpath(dirname($candidate));
        $realRoot = realpath($root);
        if ($parent === false || $realRoot === false) {
            return null;
        }
        if ($parent !== $realRoot && !str_starts_with($parent, $realRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $candidate;
    }

    /**
     * @return array<string,mixed>
     */
    private function extractFilesystemArchive(string $zipPath, string $targetDirectory): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'errors' => ['Unable to open filesystem archive.']];
        }

        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string)$zip->getNameIndex($index);
            $normalized = trim(str_replace('\\', '/', $name), '/');
            if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, '..')) {
                $zip->close();
                return ['ok' => false, 'errors' => ['Filesystem archive contains an unsafe path.']];
            }
            $entries[] = $normalized;
        }

        if (!$zip->extractTo($targetDirectory)) {
            $zip->close();
            return ['ok' => false, 'errors' => ['Filesystem archive extraction failed.']];
        }
        $zip->close();

        return [
            'ok' => true,
            'errors' => [],
            'entry_count' => count($entries),
            'entries_sha256' => hash('sha256', implode("\n", $entries)),
            'sample_entries' => array_slice($entries, 0, 20),
        ];
    }

    /**
     * @param array<int,string> $errors
     * @param array<string,mixed> $details
     * @return array<string,mixed>
     */
    private function state(string $finalState, array $errors, array $details = []): array
    {
        return [
            'ok' => false,
            'final_state' => $finalState,
            'errors' => $errors,
            'warnings' => [],
            'details' => $details,
        ];
    }
}
