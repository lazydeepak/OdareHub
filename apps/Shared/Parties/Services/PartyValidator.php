<?php
declare(strict_types=1);

namespace Apps\Shared\Parties\Services;

final class PartyValidator
{
    public const EXCLUDED_ROLE_FIELDS = [
        'full_name', 'contact_name', 'customer_name', 'guest_status',
        'id_document_ref', 'note', 'producer', 'default_supplier', 'lead',
        'cycle_time', 'is_active', 'model', 'pricing', 'cost', 'tax',
        'address', 'email', 'phone', 'credit', 'stay', 'procurement',
        'member_tier', 'vendor_approval', 'supplier_payment_terms',
        'part_name', 'parts_number', 'group_name', 'department',
    ];

    public function validateIdentity(array $data): void
    {
        $service = new PartyService();
        $service->create($data); // validates exclusions and required fields
    }

    public function isRoleFieldPresent(array $data): bool
    {
        foreach (self::EXCLUDED_ROLE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                return true;
            }
        }
        return false;
    }
}
