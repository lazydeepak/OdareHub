<?php
declare(strict_types=1);

namespace Apps\Shared\Parties\Services;

use App\Core\DB;

final class PartyService
{
    public const STATUSES = ['draft', 'active', 'deprecated', 'archived'];
    public const TYPES = ['person', 'organization'];

    public function create(array $data): int
    {
        $this->validateIdentityFields($data);
        $name = $this->requireString($data, 'name');
        $type = $this->requireEnum($data, 'type', self::TYPES, 'person');
        $status = $this->requireEnum($data, 'status', self::STATUSES, 'draft');
        $reference = $this->optionalString($data, 'reference');
        $category_ref = $this->optionalString($data, 'category_ref');
        $metadata_ref = $this->optionalString($data, 'metadata_ref');

        DB::query(
            'INSERT INTO shared_parties (name, type, status, reference, category_ref, metadata_ref, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$name, $type, $status, $reference, $category_ref, $metadata_ref]
        );
        $row = DB::fetchOne('SELECT party_id FROM shared_parties ORDER BY party_id DESC LIMIT 1');
        return (int)($row['party_id'] ?? 0);
    }

    public function fetchById(int $party_id): ?array
    {
        return DB::fetchOne('SELECT * FROM shared_parties WHERE party_id = ? LIMIT 1', [$party_id]) ?: null;
    }

    public function fetchByReference(string $reference): ?array
    {
        return DB::fetchOne('SELECT * FROM shared_parties WHERE reference = ? LIMIT 1', [$reference]) ?: null;
    }

    public function search(array $filters = []): array
    {
        $sql = 'SELECT * FROM shared_parties WHERE 1=1';
        $params = [];
        if (!empty($filters['type'])) {
            $sql .= ' AND type = ?'; $params[] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND status = ?'; $params[] = $filters['status'];
        }
        $sql .= ' ORDER BY party_id ASC LIMIT 500';
        return DB::fetchAll($sql, $params);
    }

    public function update(int $party_id, array $data): void
    {
        $this->validateIdentityFields($data);
        $allowed = ['name', 'type', 'status', 'reference', 'category_ref', 'metadata_ref'];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $sets[] = "{$key} = ?";
                $params[] = $data[$key];
            }
        }
        if (empty($sets)) {
            return;
        }
        $sets[] = "updated_at = NOW()";
        $sql = 'UPDATE shared_parties SET ' . implode(',', $sets) . ' WHERE party_id = ? LIMIT 1';
        $params[] = $party_id;
        DB::query($sql, $params);
    }

    public function deactivate(int $party_id): void
    {
        DB::query("UPDATE shared_parties SET status = 'deprecated', updated_at = NOW() WHERE party_id = ? LIMIT 1", [$party_id]);
    }

    public function reactivate(int $party_id): void
    {
        DB::query("UPDATE shared_parties SET status = 'active', updated_at = NOW() WHERE party_id = ? LIMIT 1", [$party_id]);
    }

    private function validateIdentityFields(array $data): void
    {
        $excluded = [
            'full_name', 'contact_name', 'customer_name', 'guest_status',
            'id_document_ref', 'note', 'producer', 'default_supplier', 'lead',
            'cycle_time', 'is_active', 'model', 'pricing', 'cost', 'tax',
            'address', 'email', 'phone', 'credit', 'stay', 'procurement',
            'member_tier', 'vendor_approval', 'supplier_payment_terms'
        ];
        foreach ($excluded as $bad) {
            if (array_key_exists($bad, $data)) {
                throw new \RuntimeException("Shared Parties: role-specific field '{$bad}' must not enter Party master.");
            }
        }
        if (empty($data['name']) || !is_string($data['name']) || mb_strlen($data['name']) === 0) {
            throw new \RuntimeException('Shared Parties: name is required and must be non-empty.');
        }
        if (mb_strlen($data['name'] ?? '') > 500) {
            throw new \RuntimeException('Shared Parties: name exceeds 500 chars.');
        }
    }

    private function requireString(array $data, string $key): string
    {
        $v = $data[$key] ?? '';
        if (!is_string($v) || mb_strlen($v) === 0) {
            throw new \RuntimeException("Shared Parties: {$key} is required.");
        }
        return $v;
    }

    private function optionalString(array $data, string $key): ?string
    {
        $v = $data[$key] ?? null;
        return ($v === null || $v === '') ? null : (string)$v;
    }

    private function requireEnum(array $data, string $key, array $allowed, string $default): string
    {
        $v = $data[$key] ?? $default;
        if (!in_array($v, $allowed, true)) {
            throw new \RuntimeException("Shared Parties: {$key} must be one of " . implode(',', $allowed));
        }
        return $v;
    }
}
