<?php
declare(strict_types=1);

return [
    'app_key' => 'shared_parties',
    'app_name' => 'Shared Parties Foundation',
    'type' => 'shared_app',
    'version' => '1.0.0',
    'description' => 'Canonical shared Party identity; references consumer roles (Customer, Supplier, Guest, Member, Vendor, Contact) via party_ref only.',
    'package_type' => 'bundle',
    'owner_app' => 'shared_parties',
    'consumers' => ['hospitality', 'sbaio', 'manufacturing', 'procurement'],
    'scoped_by_company' => false,
    'enabled' => true,
    'manifest_version' => '1.0',
    'capabilities' => ['party_identity', 'party_read', 'party_reference'],
    'requires' => [],
    'default_enabled' => true,
    'supports_snapshot' => false,
    'supports_rollback' => false,
    'can_modify' => false,
];
