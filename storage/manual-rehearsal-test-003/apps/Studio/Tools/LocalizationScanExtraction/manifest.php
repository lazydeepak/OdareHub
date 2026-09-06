<?php
return [
    'tool_key' => 'localization_scan_extraction',
    'key' => 'localization_scan_extraction',
    'label' => 'Inline Localization Correction Tool',
    'name' => 'Inline Localization Correction Tool',
    'category' => 'inspection',
    'home_group' => 'design_content',
    'canonical_route' => '/apps/studio/tools/localization-scan-extraction',
    'placeholder' => false,
    'risk_level' => 'low',
    'default_enabled' => true,
    'can_disable' => true,
    'required_permissions' => ['studio.tools.localization_scan_extraction.use'],
    'allowed_environments' => ['local', 'staging', 'production'],
    'routes' => [
        '/apps/studio/tools/localization-scan-extraction',
    ],
    'views' => [
        'apps/Studio/Tools/LocalizationScanExtraction/Views/preview.php',
    ],
    'services' => [
        'Apps\\Studio\\Tools\\LocalizationScanExtraction\\Services\\LocalizationScanExtractionScanner',
    ],
    'owner' => 'studio',
    'status' => 'scanner_wired',
    'can_load' => [],
    'can_modify' => true,
    'requires_approval' => false,
    'writes_to_owner_artifact' => true,
    'supports_diff' => false,
    'supports_snapshot' => true,
    'supports_rollback' => false,
];
