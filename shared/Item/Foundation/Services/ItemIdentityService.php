<?php
declare(strict_types=1);

namespace Shared\Item\Foundation\Services;

use Shared\Item\Foundation\Contracts\ItemIdentityContract;

/**
 * Shared Items Foundation — Minimal Canonical Service
 * Reference contract: docs/shared-items-foundation/canonical-item-contract.md
 * Scope: identity only; no manufacturing extension; no inventory/stock; no pricing
 * Storage: isolated JSON file (test/verification) — NOT production DB
 * No consumer adoption required.
 */
class ItemIdentityService implements ItemIdentityContract
{
    private string $storePath;

    public function __construct(?string $storePath = null)
    {
        $this->storePath = $storePath ?? (getenv('SHARED_ITEMS_STORE') ?: '/tmp/shared-items-store.json');
    }

    private function load(): array
    {
        if (!file_exists($this->storePath)) {
            return [];
        }
        $raw = file_get_contents($this->storePath);
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function save(array $data): void
    {
        $dir = dirname($this->storePath);
        if (!is_dir($dir) && $dir !== '.') {
            mkdir($dir, 0777, true);
        }
        file_put_contents($this->storePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** @inheritDoc */
    public function getById(int $item_id): ?array
    {
        foreach ($this->load() as $item) {
            if (($item['item_id'] ?? 0) === $item_id) {
                return $item;
            }
        }
        return null;
    }

    /** @inheritDoc */
    public function getByCode(string $code_ref): ?array
    {
        foreach ($this->load() as $item) {
            if (($item['code_ref'] ?? '') === $code_ref) {
                return $item;
            }
        }
        return null;
    }

    /** @inheritDoc */
    public function list(?array $filters = null): array
    {
        $items = $this->load();
        if ($filters === null || $filters === []) {
            return $items;
        }
        return array_values(array_filter($items, function ($item) use ($filters) {
            foreach ($filters as $k => $v) {
                if (($item[$k] ?? null) !== $v) {
                    return false;
                }
            }
            return true;
        }));
    }

    /** @inheritDoc */
    public function create(array $fields): array
    {
        // Validation per contract: identity only; exclusions enforced
        $required = ['name', 'code_ref', 'status'];
        foreach ($required as $r) {
            if (empty($fields[$r])) {
                throw new \InvalidArgumentException("Shared Item missing required identity field: $r");
            }
        }
        $forbidden = ['model', 'producer', 'lead', 'notes', 'cycle_time', 'supply_model', 'qc_time_per_item', 'execution_planning_fields', 'processing_dispatch_mode', 'part_molds', 'machine_assignment', 'packaging_profile', 'stock_quantity', 'warehouse_id', 'price', 'cost', 'tax'];
        foreach ($forbidden as $f) {
            if (array_key_exists($f, $fields)) {
                throw new \InvalidArgumentException("Shared Item creation rejected: manufacturing/consumer-specific field '$f' must stay outside Shared Item identity");
            }
        }
        $items = $this->load();
        foreach ($items as $existing) {
            if (($existing['code_ref'] ?? '') === $fields['code_ref']) {
                throw new \InvalidArgumentException("Shared Item code_ref must be unique; duplicate: {$fields['code_ref']}");
            }
        }
        $maxId = 0;
        foreach ($items as $item) {
            $maxId = max($maxId, (int)($item['item_id'] ?? 0));
        }
        $item = [
            'item_id' => $maxId + 1,
            'code_ref' => $fields['code_ref'],
            'name' => $fields['name'],
            'status' => $fields['status'] ?? 'draft',
            'category_ref' => $fields['category_ref'] ?? null,
            'metadata_ref' => $fields['metadata_ref'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $items[] = $item;
        $this->save($items);
        return $item;
    }

    /** @inheritDoc */
    public function update(int $item_id, array $fields): array
    {
        $items = $this->load();
        foreach ($items as &$item) {
            if (($item['item_id'] ?? 0) === $item_id) {
                $allowed = ['name', 'status', 'category_ref', 'metadata_ref'];
                foreach ($fields as $k => $v) {
                    if (in_array($k, $allowed, true)) {
                        $item[$k] = $v;
                    } elseif (in_array($k, ['model', 'producer', 'lead', 'cycle_time', 'stock_quantity', 'price', 'warehouse_id'], true)) {
                        throw new \InvalidArgumentException("Shared Item update rejected: forbidden field '$k'");
                    }
                }
                $item['updated_at'] = date('Y-m-d H:i:s');
                $this->save($items);
                return $item;
            }
        }
        throw new \InvalidArgumentException("Shared Item not found: $item_id");
    }

    /** @inheritDoc */
    public function deactivate(int $item_id): array
    {
        return $this->update($item_id, ['status' => 'archived']);
    }

    /** @inheritDoc */
    public function activate(int $item_id): array
    {
        return $this->update($item_id, ['status' => 'active']);
    }

    /** @inheritDoc */
    public function itemRef(int $item_id, string $code_ref): array
    {
        $item = $this->getById($item_id);
        if ($item === null || ($item['code_ref'] ?? '') !== $code_ref) {
            throw new \InvalidArgumentException("Shared Item reference mismatch: $item_id / $code_ref");
        }
        return [
            'item_id' => $item_id,
            'code_ref' => $code_ref,
            'name' => $item['name'] ?? null,
            'ref_shape' => 'canonical-item-identity',
            'consumer_extension_ref' => null,
        ];
    }
}
