<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Services;

use Apps\Studio\Tools\CustomizationStudio\Shared\Services\StyleValidationService;

final class VisualCustomizerMetadataService
{
    /**
     * @return array<string,mixed>
     */
    public static function discover(): array
    {
        $studioToolBasePath = dirname(__DIR__);

        $socketCatalogs = self::discoverSocketCatalogs(
            APP_ROOT . '/apps/Shell/DesignSystem/Resources/socket-catalog'
        );
        $previewFixtures = self::discoverPreviewFixtures(
            $studioToolBasePath . '/Resources/preview-fixtures'
        );

        return [
            'source_scope' => 'studio_customization_metadata_only',
            'socket_catalogs' => $socketCatalogs,
            'preview_fixtures' => $previewFixtures,
            'socket_catalog_count' => count($socketCatalogs),
            'preview_fixture_count' => count($previewFixtures),
            'validation_readiness' => StyleValidationService::readiness(),
        ];
    }

    /**
     * @return array<int,array{id:string,name:string,description:string,file:string,related_socket_catalogs:array<int,string>,outline_sections:array<int,string>}>
     */
    private static function discoverPreviewFixtures(string $dirPath): array
    {
        if (!is_dir($dirPath)) {
            return [];
        }

        $matches = glob($dirPath . '/*.json');
        if ($matches === false || $matches === []) {
            return [];
        }

        sort($matches, SORT_STRING);
        $entries = [];
        foreach ($matches as $path) {
            $file = basename($path);
            $id = (string)pathinfo($file, PATHINFO_FILENAME);
            $decoded = self::decodeJsonFile($path);
            if (isset($decoded['fixture']) && is_string($decoded['fixture']) && trim($decoded['fixture']) !== '') {
                $id = trim($decoded['fixture']);
            }

            $description = 'Preview fixture metadata only. No rendering, no save, no apply.';
            if (isset($decoded['purpose']) && is_string($decoded['purpose']) && trim($decoded['purpose']) !== '') {
                $description = trim($decoded['purpose']);
            }

            $relatedCatalogs = self::extractRelatedCatalogs($decoded);
            $outlineSections = self::extractFixtureOutlineSections($decoded);

            $entries[] = [
                'id' => $id,
                'name' => self::humanizeName($id, ''),
                'description' => $description,
                'file' => $file,
                'related_socket_catalogs' => $relatedCatalogs,
                'outline_sections' => $outlineSections,
            ];
        }

        return $entries;
    }

    /**
     * @return array<int,array{id:string,label:string,description:string,category:string,scope:string,value_type:string,simple_controls:array<int,string>,advanced_token:string,default_value:string}>
     */
    private static function extractSocketRows(array $decoded): array
    {
        $rows = isset($decoded['sockets']) && is_array($decoded['sockets']) ? $decoded['sockets'] : [];
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = trim((string)($row['socket'] ?? ''));
            if ($id === '') {
                continue;
            }

            $label = trim((string)($row['label'] ?? ''));
            $description = trim((string)($row['description'] ?? ''));
            $category = trim((string)($row['category'] ?? ''));
            $scope = trim((string)($row['scope'] ?? ''));
            $valueType = trim((string)($row['value_type'] ?? ''));
            $advancedToken = trim((string)($row['advanced_token'] ?? ''));
            $defaultValue = trim((string)($row['default_value'] ?? ''));
            $simpleControlsRaw = isset($row['simple_controls']) && is_array($row['simple_controls'])
                ? $row['simple_controls']
                : [];
            $simpleControls = [];
            foreach ($simpleControlsRaw as $simpleControl) {
                if (!is_string($simpleControl)) {
                    continue;
                }

                $simpleControlText = trim($simpleControl);
                if ($simpleControlText === '') {
                    continue;
                }

                $simpleControls[] = $simpleControlText;
            }

            $result[] = [
                'id' => $id,
                'label' => $label === '' ? $id : $label,
                'description' => $description,
                'category' => $category,
                'scope' => $scope,
                'value_type' => $valueType,
                'simple_controls' => $simpleControls,
                'advanced_token' => $advancedToken,
                'default_value' => $defaultValue,
            ];
        }

        return $result;
    }

    /**
     * @return array<string,array{id:string,name:string,description:string,file:string,socket_count:int,sockets:array<int,array{id:string,label:string,description:string,category:string,scope:string,value_type:string,simple_controls:array<int,string>,advanced_token:string,default_value:string}>}>
     */
    public static function discoverSocketCatalogsMap(): array
    {
        $dirPath = APP_ROOT . '/apps/Shell/DesignSystem/Resources/socket-catalog';
        if (!is_dir($dirPath)) {
            return [];
        }

        $matches = glob($dirPath . '/*.json');
        if ($matches === false || $matches === []) {
            return [];
        }

        sort($matches, SORT_STRING);
        $map = [];
        foreach ($matches as $path) {
            $file = basename($path);
            $decoded = self::decodeJsonFile($path);

            $id = (string)pathinfo($file, PATHINFO_FILENAME);
            if (isset($decoded['catalog']) && is_string($decoded['catalog']) && trim($decoded['catalog']) !== '') {
                $id = trim($decoded['catalog']);
            }

            $description = 'Socket catalog metadata only. Runtime consumption remains disabled.';
            if (isset($decoded['purpose']) && is_string($decoded['purpose']) && trim($decoded['purpose']) !== '') {
                $description = trim($decoded['purpose']);
            }

            $sockets = self::extractSocketRows($decoded);
            $map[$id] = [
                'id' => $id,
                'name' => self::humanizeName($id, ''),
                'description' => $description,
                'file' => $file,
                'socket_count' => count($sockets),
                'sockets' => $sockets,
            ];
        }

        return $map;
    }

    /**
     * @return array{total:int,catalogs:array<string,array{id:string,name:string,file:string,socket_count:int}>,sockets:array<int,array{socket_id:string,label:string,description:string,category:string,scope:string,value_type:string,simple_controls:array<int,string>,advanced_token:string,default_value:string,catalog_id:string,catalog_name:string,catalog_file:string}>}
     */
    public static function discoverAllSocketsFlat(): array
    {
        $catalogMap = self::discoverSocketCatalogsMap();
        $flat = [];
        $catalogInfos = [];

        foreach ($catalogMap as $cId => $catalog) {
            $catalogInfos[$cId] = [
                'id' => $cId,
                'name' => $catalog['name'],
                'file' => $catalog['file'],
                'socket_count' => $catalog['socket_count'],
            ];
            foreach ($catalog['sockets'] as $socket) {
                $socket['socket_id'] = $socket['id'];
                $socket['catalog_id'] = $cId;
                $socket['catalog_name'] = $catalog['name'];
                $socket['catalog_file'] = $catalog['file'];
                $flat[] = $socket;
            }
        }

        return [
            'total' => count($flat),
            'catalogs' => $catalogInfos,
            'sockets' => $flat,
        ];
    }

    /**
     * @return array<int,array{id:string,name:string,description:string,file:string,socket_count:int,sockets:array<int,array{id:string,label:string}>}>
     */
    private static function discoverSocketCatalogs(string $dirPath): array
    {
        return array_values(self::discoverSocketCatalogsMap());
    }

    /**
     * @return array<int,array{name:string,file:string}>
     */
    private static function discoverJsonNames(string $dirPath, string $prefixToTrim): array
    {
        if (!is_dir($dirPath)) {
            return [];
        }

        $matches = glob($dirPath . '/*.json');
        if ($matches === false || $matches === []) {
            return [];
        }

        sort($matches, SORT_STRING);
        $entries = [];
        foreach ($matches as $path) {
            $file = basename($path);
            $name = self::humanizeName(pathinfo($file, PATHINFO_FILENAME), $prefixToTrim);
            $entries[] = [
                'name' => $name,
                'file' => $file,
            ];
        }

        return $entries;
    }

    private static function humanizeName(string $name, string $prefixToTrim): string
    {
        $trimmed = $prefixToTrim === '' ? $name : str_replace($prefixToTrim . '-', '', $name);
        $trimmed = str_replace('-preview', '', $trimmed);
        $trimmed = str_replace('-', ' ', $trimmed);
        $trimmed = trim($trimmed);
        if ($trimmed === '') {
            return $name;
        }

        return ucwords($trimmed);
    }

    /**
     * @return array<string,mixed>
     */
    private static function decodeJsonFile(string $path): array
    {
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $decoded
     * @return array<int,string>
     */
    private static function extractRelatedCatalogs(array $decoded): array
    {
        $examples = isset($decoded['examples']) && is_array($decoded['examples']) ? $decoded['examples'] : [];
        $related = [];
        foreach ($examples as $example) {
            if (!is_array($example)) {
                continue;
            }

            $catalogs = isset($example['socket_catalogs']) && is_array($example['socket_catalogs'])
                ? $example['socket_catalogs']
                : [];
            foreach ($catalogs as $catalog) {
                if (!is_string($catalog)) {
                    continue;
                }

                $catalogId = trim($catalog);
                if ($catalogId === '') {
                    continue;
                }

                $related[$catalogId] = true;
            }
        }

        $ids = array_keys($related);
        sort($ids, SORT_STRING);
        return $ids;
    }

    /**
     * @param array<string,mixed> $decoded
     * @return array<int,string>
     */
    private static function extractFixtureOutlineSections(array $decoded): array
    {
        $examples = isset($decoded['examples']) && is_array($decoded['examples']) ? $decoded['examples'] : [];
        $items = [];

        foreach ($examples as $example) {
            if (!is_array($example)) {
                continue;
            }

            $exampleLabel = trim((string)($example['label'] ?? ''));
            if ($exampleLabel !== '') {
                $items[$exampleLabel] = true;
            }

            $description = trim((string)($example['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $description = str_replace([' and ', ';'], [', ', ','], $description);
            $parts = explode(',', $description);
            foreach ($parts as $part) {
                $clean = trim($part, " \t\n\r\0\x0B.");
                if ($clean === '') {
                    continue;
                }

                $items[$clean] = true;
            }
        }

        return array_keys($items);
    }
}