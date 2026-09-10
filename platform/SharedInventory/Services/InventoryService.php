<?php
declare(strict_types=1);

namespace Platform\SharedInventory\Services;

use Platform\SharedInventory\Contracts\InventoryContract;
use Platform\SharedInventory\Adapters\SharedItemAdapter;

final class InventoryService implements InventoryContract
{
    private SharedItemAdapter $adapter;
    private array $journal = [];
    private int $nextId = 1;

    public function __construct()
    {
        $this->adapter = new SharedItemAdapter();
    }

    public function recordMovement(
        string $warehouseRef,
        ?string $locationRef,
        int $itemId,
        ?string $codeRef,
        string $movementType,
        float $qtyDelta,
        ?string $sourceDomain,
        ?string $sourceTransactionRef,
        ?string $referenceNo,
        ?string $notes,
        bool $checkIdempotency = true
    ): array {
        if ($itemId <= 0 && (empty($codeRef) || strlen($codeRef) > 255)) {
            return ['ok' => false, 'reason' => 'invalid_item_ref', 'item_id' => $itemId];
        }
        if (!$this->adapter->exists($itemId, $codeRef)) {
            return ['ok' => false, 'reason' => 'item_ref_not_reachable', 'item_id' => $itemId];
        }
        $allowedTypes = ['IN', 'OUT', 'TRANSFER', 'ADJUST'];
        if (!in_array($movementType, $allowedTypes, true)) {
            return ['ok' => false, 'reason' => 'invalid_movement_type', 'movement_type' => $movementType];
        }
        $id = $this->nextId++;
        $record = [
            'movement_id' => $id,
            'warehouse_ref' => $warehouseRef,
            'location_ref' => $locationRef,
            'item_ref' => $itemId,
            'code_ref' => $codeRef,
            'movement_type' => $movementType,
            'qty_delta' => $qtyDelta,
            'source_domain' => $sourceDomain,
            'source_transaction_ref' => $sourceTransactionRef,
            'reference_no' => $referenceNo,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($checkIdempotency) {
            $existing = $this->findByIdempotency($record);
            if ($existing !== null) {
                return ['ok' => true, 'idempotent' => true, 'movement_id' => $existing['movement_id'], 'note' => 'duplicate prevented'];
            }
        }
        $this->journal[] = $record;
        return ['ok' => true, 'movement_id' => $id, 'record' => $record];
    }

    public function getMovements(?int $itemId, ?string $codeRef, ?string $warehouseRef, ?string $sourceDomain): array
    {
        return array_values(array_filter($this->journal, function (array $r) use ($itemId, $codeRef, $warehouseRef, $sourceDomain): bool {
            if ($itemId !== null && ($r['item_ref'] ?? null) !== $itemId) return false;
            if ($codeRef !== null && ($r['code_ref'] ?? null) !== $codeRef) return false;
            if ($warehouseRef !== null && ($r['warehouse_ref'] ?? null) !== $warehouseRef) return false;
            if ($sourceDomain !== null && ($r['source_domain'] ?? null) !== $sourceDomain) return false;
            return true;
        }));
    }

    public function deriveBalance(?int $itemId, ?string $codeRef, ?string $warehouseRef): float
    {
        $rows = $this->getMovements($itemId, $codeRef, $warehouseRef, null);
        $ordered = $rows;
        usort($ordered, static function (array $a, array $b): int {
            $t = strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
            return $t !== 0 ? $t : ($a['movement_id'] ?? 0) <=> ($b['movement_id'] ?? 0);
        });
        $balance = 0.0;
        foreach ($ordered as $row) {
            $balance += (float)($row['qty_delta'] ?? 0.0);
        }
        return $balance;
    }

    public function validateItemRef(int $itemId, ?string $codeRef): bool
    {
        return ($itemId > 0) && (($codeRef === null) || (is_string($codeRef) && strlen($codeRef) <= 255));
    }

    public function applyAdjustment(
        int $itemId,
        ?string $codeRef,
        string $warehouseRef,
        float $beforeQty,
        float $afterQty,
        ?string $reasonRef,
        ?string $authorRef,
        ?string $notes
    ): array {
        $delta = $afterQty - $beforeQty;
        return $this->recordMovement(
            $warehouseRef,
            null,
            $itemId,
            $codeRef,
            'ADJUST',
            $delta,
            'INVENTORY',
            null,
            $reasonRef ?? '',
            $notes ?? '',
            true
        );
    }

    private function findByIdempotency(array $record): ?array
    {
        foreach ($this->journal as $existing) {
            if (($existing['item_ref'] ?? null) === ($record['item_ref'] ?? null)
                && ($existing['warehouse_ref'] ?? null) === ($record['warehouse_ref'] ?? null)
                && ($existing['movement_type'] ?? null) === ($record['movement_type'] ?? null)
                && ($existing['source_domain'] ?? null) === ($record['source_domain'] ?? null)
                && ($existing['source_transaction_ref'] ?? null) === ($record['source_transaction_ref'] ?? null)) {
                return $existing;
            }
        }
        return null;
    }
}
