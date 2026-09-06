<?php
define('APP_ROOT', 'C:\Projects\Susankhya');

// Load the discovery service directly
require_once APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/Services/LocalizationStudioDiscoveryService.php';

$discovery = \Apps\Studio\Tools\LocalizationStudio\Services\LocalizationStudioDiscoveryService::scan();

echo 'Owners: ' . $discovery['summary']['total_owners'] . "\n";
echo 'Files: ' . $discovery['summary']['total_files'] . "\n";
echo 'Keys: ' . $discovery['summary']['total_keys'] . "\n";
echo 'Locale counts: ' . json_encode($discovery['summary']['locale_file_counts']) . "\n";
echo 'Owners with missing locales: ' . $discovery['summary']['owners_with_missing_locales'] . "\n";
echo '---' . str_repeat('-', 100) . "\n";
foreach ($discovery['groups'] as $owner => $data) {
    $locales = [];
    foreach (['en','ja','ne'] as $l) {
        if ($data['files'][$l]['exists']) {
            $locales[] = $l . '(' . $data['files'][$l]['path'] . ')';
        }
    }
    echo $owner . ': ' . implode(', ', $locales)
        . ' | keys=' . $data['key_count']
        . ' | missing=' . implode(',', $data['missing_locales']) . "\n";
}
