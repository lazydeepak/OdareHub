<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationScanExtraction\ValueObjects;

final class CorrectionReport
{
    public readonly string $action;
    public readonly string $ownerKey;
    public readonly string $locale;
    public readonly string $occurredAt;
    public readonly bool $success;
    public readonly int $addedCount;
    public readonly int $skippedCount;
    public readonly array $addedKeys;
    public readonly ?string $message;
    public readonly ?string $error;
    public readonly array $snapshotPaths;
    public readonly array $reScanDiagnostics;
    public readonly ?string $rollbackStatus;
    public readonly ?bool $reScanConfirmed;

    public function __construct(
        string $action,
        string $ownerKey,
        string $locale,
        bool $success,
        int $addedCount = 0,
        int $skippedCount = 0,
        array $addedKeys = [],
        ?string $message = null,
        ?string $error = null,
        array $snapshotPaths = [],
        array $reScanDiagnostics = [],
        ?string $rollbackStatus = null,
        ?bool $reScanConfirmed = null,
        ?string $occurredAt = null,
    ) {
        $this->action = $action;
        $this->ownerKey = $ownerKey;
        $this->locale = $locale;
        $this->occurredAt = $occurredAt ?? date('c');
        $this->success = $success;
        $this->addedCount = $addedCount;
        $this->skippedCount = $skippedCount;
        $this->addedKeys = $addedKeys;
        $this->message = $message;
        $this->error = $error;
        $this->snapshotPaths = $snapshotPaths;
        $this->reScanDiagnostics = $reScanDiagnostics;
        $this->rollbackStatus = $rollbackStatus;
        $this->reScanConfirmed = $reScanConfirmed;
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'owner_key' => $this->ownerKey,
            'locale' => $this->locale,
            'occurred_at' => $this->occurredAt,
            'success' => $this->success,
            'added_count' => $this->addedCount,
            'skipped_count' => $this->skippedCount,
            'added_keys' => $this->addedKeys,
            'message' => $this->message,
            'error' => $this->error,
            'snapshot_paths' => $this->snapshotPaths,
            're_scan_diagnostics' => $this->reScanDiagnostics,
            'rollback_status' => $this->rollbackStatus,
            're_scan_confirmed' => $this->reScanConfirmed,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            action: (string)($data['action'] ?? ''),
            ownerKey: (string)($data['owner_key'] ?? ''),
            locale: (string)($data['locale'] ?? 'en'),
            success: (bool)($data['success'] ?? false),
            addedCount: (int)($data['added_count'] ?? 0),
            skippedCount: (int)($data['skipped_count'] ?? 0),
            addedKeys: (array)($data['added_keys'] ?? []),
            message: isset($data['message']) ? (string)$data['message'] : null,
            error: isset($data['error']) ? (string)$data['error'] : null,
            snapshotPaths: (array)($data['snapshot_paths'] ?? []),
            reScanDiagnostics: (array)($data['re_scan_diagnostics'] ?? []),
            rollbackStatus: isset($data['rollback_status']) ? (string)$data['rollback_status'] : null,
            reScanConfirmed: isset($data['re_scan_confirmed']) ? (bool)$data['re_scan_confirmed'] : null,
            occurredAt: isset($data['occurred_at']) ? (string)$data['occurred_at'] : null,
        );
    }

    public function withRollbackStatus(string $rollbackStatus): self
    {
        return new self(
            action: $this->action,
            ownerKey: $this->ownerKey,
            locale: $this->locale,
            success: $this->success,
            addedCount: $this->addedCount,
            skippedCount: $this->skippedCount,
            addedKeys: $this->addedKeys,
            message: $this->message,
            error: $this->error,
            snapshotPaths: $this->snapshotPaths,
            reScanDiagnostics: $this->reScanDiagnostics,
            rollbackStatus: $rollbackStatus,
            reScanConfirmed: $this->reScanConfirmed,
            occurredAt: $this->occurredAt,
        );
    }
}
