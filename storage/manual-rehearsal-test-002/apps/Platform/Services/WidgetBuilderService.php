<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use App\Core\DB;

final class WidgetBuilderService
{
    /**
     * @return array<int,array<string,string>>
     */
    public static function templateCatalog(): array
    {
        return [
            ['template_type' => 'kpi', 'label_key' => 'ops.widget_builder.template.kpi'],
            ['template_type' => 'table', 'label_key' => 'ops.widget_builder.template.table'],
            ['template_type' => 'chart', 'label_key' => 'ops.widget_builder.template.chart'],
            ['template_type' => 'queue', 'label_key' => 'ops.widget_builder.template.queue'],
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    public static function placementZones(): array
    {
        return [
            ['placement_zone' => 'dashboard_summary', 'label_key' => 'ops.widget_builder.zone.dashboard_summary'],
            ['placement_zone' => 'monitoring', 'label_key' => 'ops.widget_builder.zone.monitoring'],
            ['placement_zone' => 'operator_actions', 'label_key' => 'ops.widget_builder.zone.operator_actions'],
            ['placement_zone' => 'supporting_visibility', 'label_key' => 'ops.widget_builder.zone.supporting_visibility'],
        ];
    }

    /**
     * Build a map of dataset_key → allowed runtime fields from a dataset catalog array.
     *
     * @param array<int,array<string,mixed>> $catalog  Output of WidgetBuilderDatasetRegistryService::catalog()
     * @return array<string,array<string,string>>
     */
    public static function buildRuntimeFieldMapFromCatalog(array $catalog): array
    {
        $map = [];
        foreach ($catalog as $dataset) {
            $key = trim((string)($dataset['dataset_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $map[$key] = WidgetBuilderDatasetRegistryService::allowedRuntimeFields($key);
        }
        return $map;
    }

    public static function ensureTables(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS platform_widget_blueprints (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            app_key VARCHAR(80) NOT NULL,
            module_key VARCHAR(120) NOT NULL,
            widget_key VARCHAR(140) NOT NULL,
            title_key VARCHAR(190) NOT NULL,
            description_key VARCHAR(190) NULL,
            template_type VARCHAR(40) NOT NULL,
            dataset_key VARCHAR(120) NOT NULL,
            placement_zone VARCHAR(80) NOT NULL,
            config_json JSON NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_by_user_id BIGINT UNSIGNED NULL,
            created_by_email VARCHAR(190) NULL,
            updated_by_user_id BIGINT UNSIGNED NULL,
            updated_by_email VARCHAR(190) NULL,
            published_by_user_id BIGINT UNSIGNED NULL,
            published_by_email VARCHAR(190) NULL,
            published_at DATETIME NULL,
            archived_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_platform_widget_blueprint_widget (widget_key),
            KEY idx_platform_widget_blueprint_status (status),
            KEY idx_platform_widget_blueprint_app_module (app_key, module_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listBlueprints(): array
    {
        return DB::fetchAll(
            'SELECT id, app_key, module_key, widget_key, title_key, description_key, template_type, dataset_key, placement_zone, config_json,
                    status, created_by_email, updated_by_email, published_by_email, published_at, archived_at, created_at, updated_at
             FROM platform_widget_blueprints
             ORDER BY FIELD(status, "published", "draft", "archived"), updated_at DESC, id DESC
             LIMIT 500'
        );
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array{ready:bool,errors:array<int,string>}>
     */
    public static function validationReportForRows(array $rows): array
    {
        $report = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $datasetKey = trim((string)($row['dataset_key'] ?? ''));
            $templateType = trim((string)($row['template_type'] ?? ''));
            $rowAppKey = strtolower(trim((string)($row['app_key'] ?? '')));
            $rowModuleKey = strtolower(trim((string)($row['module_key'] ?? '')));
            $configRaw = trim((string)($row['config_json'] ?? ''));

            $errors = [];
            $dataset = WidgetBuilderDatasetRegistryService::findDataset($datasetKey);
            if (!is_array($dataset)) {
                $errors[] = 'ops.widget_builder.error.invalid_dataset_key';
            } else {
                $datasetApp = strtolower(trim((string)($dataset['app_key'] ?? '')));
                $datasetModule = strtolower(trim((string)($dataset['module_key'] ?? '')));
                if ($datasetApp !== '' && $rowAppKey !== '' && $datasetApp !== $rowAppKey) {
                    $errors[] = 'ops.widget_builder.error.invalid_dataset_key';
                }
                if ($datasetModule !== '' && $rowModuleKey !== '' && $datasetModule !== $rowModuleKey) {
                    $errors[] = 'ops.widget_builder.error.invalid_dataset_key';
                }
            }
            if (!self::isAllowedTemplate($templateType)) {
                $errors[] = 'ops.widget_builder.error.invalid_template_type';
            }

            $config = [];
            if ($configRaw !== '') {
                $decoded = json_decode($configRaw, true);
                if (!is_array($decoded)) {
                    $errors[] = 'ops.widget_builder.error.invalid_config_json';
                } else {
                    $config = $decoded;
                }
            }

            if ($errors === []) {
                $errors = self::collectConfigValidationErrors($config, $datasetKey, $templateType);
            }

            $report[$id] = [
                'ready' => $errors === [],
                'errors' => $errors,
            ];
        }

        return $report;
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     */
    public static function createDraft(array $input, array $actor): void
    {
        $appKey = self::validateKey((string)($input['app_key'] ?? ''), 'ops.widget_builder.error.invalid_app_key');
        $moduleKey = self::validateKey((string)($input['module_key'] ?? ''), 'ops.widget_builder.error.invalid_module_key');
        $widgetKeyRaw = trim((string)($input['widget_key'] ?? ''));
        $widgetKey = $widgetKeyRaw !== ''
            ? self::validateKey($widgetKeyRaw, 'ops.widget_builder.error.invalid_widget_key')
            : self::buildWidgetKey($appKey, $moduleKey);

        $templateType = trim((string)($input['template_type'] ?? ''));
        if (!self::isAllowedTemplate($templateType)) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_template_type');
        }

        $datasetKey = trim((string)($input['dataset_key'] ?? ''));
        $dataset = WidgetBuilderDatasetRegistryService::findDataset($datasetKey);
        if (!is_array($dataset)) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_dataset_key');
        }

        $datasetApp = strtolower(trim((string)($dataset['app_key'] ?? '')));
        $datasetModule = strtolower(trim((string)($dataset['module_key'] ?? '')));
        if ($datasetApp !== '' && $datasetApp !== $appKey) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_dataset_key');
        }
        if ($datasetModule !== '' && $datasetModule !== $moduleKey) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_dataset_key');
        }

        $placementZone = trim((string)($input['placement_zone'] ?? ''));
        if (!self::isAllowedZone($placementZone)) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_placement_zone');
        }

        $titleKey = self::validateTranslationKey((string)($input['title_key'] ?? ''), 'ops.widget_builder.error.invalid_title_key');
        $descriptionKeyRaw = trim((string)($input['description_key'] ?? ''));
        $descriptionKey = $descriptionKeyRaw === ''
            ? null
            : self::validateTranslationKey($descriptionKeyRaw, 'ops.widget_builder.error.invalid_description_key');

        $configRaw = trim((string)($input['config_json'] ?? ''));
        $config = [];
        if ($configRaw !== '') {
            $decoded = json_decode($configRaw, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('ops.widget_builder.error.invalid_config_json');
            }
            $config = $decoded;
        }
        $config = self::normalizeConfigForTemplate($config, $templateType);
        self::validateConfigPayload($config, $datasetKey, $templateType);
        $configToStore = $config === [] ? null : json_encode($config, JSON_UNESCAPED_SLASHES);

        $actorId = isset($actor['id']) ? (int)$actor['id'] : null;
        $actorEmail = trim((string)($actor['email'] ?? ''));

        DB::query(
            'INSERT INTO platform_widget_blueprints
                (app_key, module_key, widget_key, title_key, description_key, template_type, dataset_key, placement_zone,
                 config_json, status, created_by_user_id, created_by_email, updated_by_user_id, updated_by_email)
             VALUES (?,?,?,?,?,?,?,?,?,"draft",?,?,?,?)
             ON DUPLICATE KEY UPDATE
                app_key=VALUES(app_key),
                module_key=VALUES(module_key),
                title_key=VALUES(title_key),
                description_key=VALUES(description_key),
                template_type=VALUES(template_type),
                dataset_key=VALUES(dataset_key),
                placement_zone=VALUES(placement_zone),
                config_json=VALUES(config_json),
                status="draft",
                archived_at=NULL,
                updated_by_user_id=VALUES(updated_by_user_id),
                updated_by_email=VALUES(updated_by_email),
                updated_at=CURRENT_TIMESTAMP',
            [
                $appKey,
                $moduleKey,
                $widgetKey,
                $titleKey,
                $descriptionKey,
                $templateType,
                $datasetKey,
                $placementZone,
                $configToStore,
                $actorId,
                $actorEmail,
                $actorId,
                $actorEmail,
            ]
        );
    }

    /**
     * @param array<string,mixed> $actor
     */
    public static function changeStatus(int $blueprintId, string $status, array $actor): void
    {
        if ($blueprintId <= 0) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_blueprint_id');
        }

        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_status_transition');
        }

        $existing = DB::fetchOne(
            'SELECT id, status, dataset_key, template_type, config_json FROM platform_widget_blueprints WHERE id=? LIMIT 1',
            [$blueprintId]
        );
        if (!$existing) {
            throw new \RuntimeException('ops.widget_builder.error.blueprint_not_found');
        }

        $currentStatus = strtolower(trim((string)($existing['status'] ?? '')));
        if ($status === 'published' && $currentStatus !== 'draft') {
            throw new \RuntimeException('ops.widget_builder.error.invalid_status_transition');
        }
        if ($status === 'archived' && !in_array($currentStatus, ['draft', 'published'], true)) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_status_transition');
        }
        if ($status === 'draft' && $currentStatus !== 'archived') {
            throw new \RuntimeException('ops.widget_builder.error.invalid_status_transition');
        }

        $actorId = isset($actor['id']) ? (int)$actor['id'] : null;
        $actorEmail = trim((string)($actor['email'] ?? ''));

        if ($status === 'published') {
            $datasetKey = trim((string)($existing['dataset_key'] ?? ''));
            $templateType = trim((string)($existing['template_type'] ?? ''));
            $configRaw = trim((string)($existing['config_json'] ?? ''));
            $config = [];
            if ($configRaw !== '') {
                $decoded = json_decode($configRaw, true);
                if (!is_array($decoded)) {
                    throw new \RuntimeException('ops.widget_builder.error.invalid_config_json');
                }
                $config = $decoded;
            }
            $config = self::normalizeConfigForTemplate($config, $templateType);
            self::validateConfigPayload($config, $datasetKey, $templateType);

            $configToStore = $config === [] ? null : json_encode($config, JSON_UNESCAPED_SLASHES);

            DB::query(
                'UPDATE platform_widget_blueprints
                 SET status="published", published_by_user_id=?, published_by_email=?, published_at=NOW(),
                     archived_at=NULL, config_json=?, updated_by_user_id=?, updated_by_email=?, updated_at=CURRENT_TIMESTAMP
                 WHERE id=? LIMIT 1',
                [$actorId, $actorEmail, $configToStore, $actorId, $actorEmail, $blueprintId]
            );
            return;
        }

        if ($status === 'draft') {
            DB::query(
                'UPDATE platform_widget_blueprints
                 SET status="draft", archived_at=NULL,
                     updated_by_user_id=?, updated_by_email=?, updated_at=CURRENT_TIMESTAMP
                 WHERE id=? LIMIT 1',
                [$actorId, $actorEmail, $blueprintId]
            );
            return;
        }

        DB::query(
            'UPDATE platform_widget_blueprints
             SET status="archived", archived_at=NOW(),
                 updated_by_user_id=?, updated_by_email=?, updated_at=CURRENT_TIMESTAMP
             WHERE id=? LIMIT 1',
            [$actorId, $actorEmail, $blueprintId]
        );
    }

    /**
     * @param array<string,mixed> $actor
     */
    public static function cloneBlueprintAsDraft(int $blueprintId, array $actor): void
    {
        if ($blueprintId <= 0) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_blueprint_id');
        }

        $existing = DB::fetchOne(
            'SELECT id, app_key, module_key, widget_key, title_key, description_key, template_type, dataset_key, placement_zone, config_json
             FROM platform_widget_blueprints WHERE id=? LIMIT 1',
            [$blueprintId]
        );
        if (!$existing) {
            throw new \RuntimeException('ops.widget_builder.error.blueprint_not_found');
        }

        $input = [
            'app_key' => trim((string)($existing['app_key'] ?? '')),
            'module_key' => trim((string)($existing['module_key'] ?? '')),
            'widget_key' => self::buildCloneWidgetKey((string)($existing['widget_key'] ?? 'generated_widget')),
            'title_key' => trim((string)($existing['title_key'] ?? '')),
            'description_key' => trim((string)($existing['description_key'] ?? '')),
            'template_type' => trim((string)($existing['template_type'] ?? '')),
            'dataset_key' => trim((string)($existing['dataset_key'] ?? '')),
            'placement_zone' => trim((string)($existing['placement_zone'] ?? 'dashboard_summary')),
            'config_json' => trim((string)($existing['config_json'] ?? '')),
        ];

        self::createDraft($input, $actor);
    }

    /**
     * Update an existing draft blueprint in place.
     * Only drafts may be edited; attempting to edit a published or archived blueprint throws.
     *
     * @param array<string,mixed> $input  Must include: title_key, description_key, template_type, dataset_key,
     *                                     placement_zone, config_json
     * @param array<string,mixed> $actor
     */
    public static function updateDraft(int $blueprintId, array $input, array $actor): void
    {
        if ($blueprintId <= 0) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_blueprint_id');
        }

        $existing = DB::fetchOne(
            'SELECT id, status FROM platform_widget_blueprints WHERE id=? LIMIT 1',
            [$blueprintId]
        );
        if (!$existing) {
            throw new \RuntimeException('ops.widget_builder.error.blueprint_not_found');
        }
        if (strtolower(trim((string)($existing['status'] ?? ''))) !== 'draft') {
            throw new \RuntimeException('ops.widget_builder.error.edit_only_allowed_for_drafts');
        }

        $templateType = trim((string)($input['template_type'] ?? ''));
        if (!self::isAllowedTemplate($templateType)) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_template_type');
        }

        $datasetKey = trim((string)($input['dataset_key'] ?? ''));
        if (!is_array(WidgetBuilderDatasetRegistryService::findDataset($datasetKey))) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_dataset_key');
        }

        $placementZone = trim((string)($input['placement_zone'] ?? ''));
        if (!self::isAllowedZone($placementZone)) {
            throw new \RuntimeException('ops.widget_builder.error.invalid_placement_zone');
        }

        $titleKey = self::validateTranslationKey((string)($input['title_key'] ?? ''), 'ops.widget_builder.error.invalid_title_key');
        $descriptionKeyRaw = trim((string)($input['description_key'] ?? ''));
        $descriptionKey = $descriptionKeyRaw === ''
            ? null
            : self::validateTranslationKey($descriptionKeyRaw, 'ops.widget_builder.error.invalid_description_key');

        $configRaw = trim((string)($input['config_json'] ?? ''));
        $config = [];
        if ($configRaw !== '') {
            $decoded = json_decode($configRaw, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('ops.widget_builder.error.invalid_config_json');
            }
            $config = $decoded;
        }
        $config = self::normalizeConfigForTemplate($config, $templateType);
        self::validateConfigPayload($config, $datasetKey, $templateType);
        $configToStore = $config === [] ? null : json_encode($config, JSON_UNESCAPED_SLASHES);

        $actorId = isset($actor['id']) ? (int)$actor['id'] : null;
        $actorEmail = trim((string)($actor['email'] ?? ''));

        DB::query(
            'UPDATE platform_widget_blueprints
             SET title_key=?, description_key=?, template_type=?, dataset_key=?,
                 placement_zone=?, config_json=?,
                 updated_by_user_id=?, updated_by_email=?, updated_at=CURRENT_TIMESTAMP
             WHERE id=? AND status="draft" LIMIT 1',
            [
                $titleKey,
                $descriptionKey,
                $templateType,
                $datasetKey,
                $placementZone,
                $configToStore,
                $actorId,
                $actorEmail,
                $blueprintId,
            ]
        );
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,int>
     */
    public static function filterPublishReadyIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn ($value): int => (int)$value, $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::fetchAll(
            "SELECT id, status, dataset_key, template_type, config_json
             FROM platform_widget_blueprints
             WHERE id IN ({$placeholders})",
            $ids
        );

        $ready = [];
        foreach ((array)$rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $status = strtolower(trim((string)($row['status'] ?? '')));
            if ($status !== 'draft') {
                continue;
            }

            $datasetKey = trim((string)($row['dataset_key'] ?? ''));
            $templateType = trim((string)($row['template_type'] ?? ''));
            $configRaw = trim((string)($row['config_json'] ?? ''));
            $config = [];
            if ($configRaw !== '') {
                $decoded = json_decode($configRaw, true);
                if (!is_array($decoded)) {
                    continue;
                }
                $config = $decoded;
            }

            try {
                $config = self::normalizeConfigForTemplate($config, $templateType);
                self::validateConfigPayload($config, $datasetKey, $templateType);
                $ready[] = $id;
            } catch (\Throwable) {
                // Skip invalid rows in batch eligibility filtering.
            }
        }

        return array_values(array_unique($ready));
    }

    /**
     * @param array<int,int> $ids
     * @param array<int,string> $allowedStatuses
     * @return array<int,int>
     */
    public static function filterIdsByStatuses(array $ids, array $allowedStatuses): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn ($value): int => (int)$value, $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $allowed = array_values(array_unique(array_filter(array_map(static fn ($value): string => strtolower(trim((string)$value)), $allowedStatuses), static fn (string $status): bool => $status !== '')));
        if ($allowed === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::fetchAll(
            "SELECT id, status FROM platform_widget_blueprints WHERE id IN ({$placeholders})",
            $ids
        );

        $statusById = [];
        foreach ((array)$rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $statusById[$id] = strtolower(trim((string)($row['status'] ?? '')));
        }

        return array_values(array_filter($ids, static function (int $id) use ($allowed, $statusById): bool {
            $status = (string)($statusById[$id] ?? '');
            return in_array($status, $allowed, true);
        }));
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,int>
     */
    public static function filterBulkEligibleIds(string $action, array $ids): array
    {
        $action = strtolower(trim($action));
        if ($action === 'publish') {
            return self::filterPublishReadyIds($ids);
        }

        if ($action === 'archive') {
            return self::filterIdsByStatuses($ids, ['draft', 'published']);
        }

        if ($action === 'restore') {
            return self::filterIdsByStatuses($ids, ['archived']);
        }

        return [];
    }

    private static function isAllowedTemplate(string $templateType): bool
    {
        foreach (self::templateCatalog() as $template) {
            if ((string)($template['template_type'] ?? '') === $templateType) {
                return true;
            }
        }

        return false;
    }

    private static function isAllowedZone(string $zone): bool
    {
        foreach (self::placementZones() as $placement) {
            if ((string)($placement['placement_zone'] ?? '') === $zone) {
                return true;
            }
        }

        return false;
    }

    private static function buildWidgetKey(string $appKey, string $moduleKey): string
    {
        return $appKey . '.' . $moduleKey . '.generated_' . gmdate('YmdHis');
    }

    private static function buildCloneWidgetKey(string $sourceKey): string
    {
        $normalized = strtolower(trim($sourceKey));
        $normalized = preg_replace('/[^a-z0-9_\.:-]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '._:-');
        if ($normalized === '') {
            $normalized = 'generated_widget';
        }

        for ($i = 0; $i < 8; $i++) {
            $suffix = '.clone_' . gmdate('YmdHis') . ($i > 0 ? ('_' . $i) : '');
            $baseLength = 140 - strlen($suffix);
            $base = $baseLength > 0 ? substr($normalized, 0, $baseLength) : '';
            $candidate = $base . $suffix;

            $existing = DB::fetchOne('SELECT id FROM platform_widget_blueprints WHERE widget_key=? LIMIT 1', [$candidate]);
            if (!$existing) {
                return $candidate;
            }
        }

        return substr($normalized, 0, 90) . '.clone_' . bin2hex(random_bytes(4));
    }

    private static function validateTranslationKey(string $value, string $errorKey): string
    {
        $key = trim($value);
        if ($key === '' || !preg_match('/^[a-z0-9_\.:-]+$/', $key)) {
            throw new \RuntimeException($errorKey);
        }
        return $key;
    }

    private static function validateKey(string $value, string $errorKey): string
    {
        $key = strtolower(trim($value));
        if ($key === '' || !preg_match('/^[a-z][a-z0-9_\.:-]*$/', $key)) {
            throw new \RuntimeException($errorKey);
        }
        return $key;
    }

    /**
     * @param array<string,mixed> $config
     */
    private static function validateConfigPayload(array $config, string $datasetKey, string $templateType): void
    {
        $errors = self::collectConfigValidationErrors($config, $datasetKey, $templateType);
        if ($errors !== []) {
            throw new \RuntimeException((string)$errors[0]);
        }
    }

    /**
     * @param array<string,mixed> $config
     * @return array<int,string>
     */
    private static function collectConfigValidationErrors(array $config, string $datasetKey, string $templateType): array
    {
        $errors = [];

        $allowedConfigKeys = [
            'metric_field',
            'subtitle_field',
            'preview_fields',
            'limit',
            'aggregation',
            'group_by',
            'filter_field',
        ];

        foreach ($config as $key => $value) {
            if (!in_array((string)$key, $allowedConfigKeys, true)) {
                $errors[] = 'ops.widget_builder.error.invalid_config_key';
            }
        }

        $metricField = trim((string)($config['metric_field'] ?? ''));
        $subtitleField = trim((string)($config['subtitle_field'] ?? ''));
        $groupBy = trim((string)($config['group_by'] ?? ''));
        $filterField = trim((string)($config['filter_field'] ?? ''));
        $aggregation = trim((string)($config['aggregation'] ?? ''));

        if ($metricField !== '' && !WidgetBuilderDatasetRegistryService::isAllowedRuntimeField($datasetKey, $metricField)) {
            $errors[] = 'ops.widget_builder.error.invalid_metric_field';
        }
        if ($subtitleField !== '' && !WidgetBuilderDatasetRegistryService::isAllowedRuntimeField($datasetKey, $subtitleField)) {
            $errors[] = 'ops.widget_builder.error.invalid_subtitle_field';
        }
        if ($groupBy !== '' && !WidgetBuilderDatasetRegistryService::isAllowedFilter($datasetKey, $groupBy)) {
            $errors[] = 'ops.widget_builder.error.invalid_group_by';
        }
        if ($filterField !== '' && !WidgetBuilderDatasetRegistryService::isAllowedFilter($datasetKey, $filterField)) {
            $errors[] = 'ops.widget_builder.error.invalid_filter_field';
        }
        if ($aggregation !== '' && !WidgetBuilderDatasetRegistryService::isAllowedAggregation($datasetKey, $aggregation)) {
            $errors[] = 'ops.widget_builder.error.invalid_aggregation';
        }

        if (array_key_exists('limit', $config)) {
            $limit = (int)$config['limit'];
            if ($limit < 1 || $limit > 200) {
                $errors[] = 'ops.widget_builder.error.invalid_limit';
            }
        }

        $previewFields = [];
        if (array_key_exists('preview_fields', $config)) {
            if (!is_array($config['preview_fields'])) {
                $errors[] = 'ops.widget_builder.error.invalid_preview_fields';
            }
            foreach ((array)$config['preview_fields'] as $field) {
                $candidate = trim((string)$field);
                if ($candidate === '') {
                    continue;
                }
                if (!WidgetBuilderDatasetRegistryService::isAllowedRuntimeField($datasetKey, $candidate)) {
                    $errors[] = 'ops.widget_builder.error.invalid_preview_fields';
                }
                $previewFields[] = $candidate;
            }
            if (count($previewFields) > 6) {
                $errors[] = 'ops.widget_builder.error.invalid_preview_fields';
            }
        }

        if (in_array($templateType, ['kpi', 'queue', 'chart'], true) && $metricField === '') {
            $errors[] = 'ops.widget_builder.error.metric_required';
        }
        if ($templateType === 'table' && $previewFields === []) {
            $errors[] = 'ops.widget_builder.error.preview_fields_required';
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private static function normalizeConfigForTemplate(array $config, string $templateType): array
    {
        $normalized = $config;

        if ($templateType === 'table') {
            unset($normalized['metric_field'], $normalized['subtitle_field'], $normalized['aggregation']);
        }

        if (in_array($templateType, ['kpi', 'queue', 'chart'], true)) {
            unset($normalized['preview_fields']);
        }

        if ($templateType !== 'chart') {
            unset($normalized['aggregation']);
        }

        return $normalized;
    }
}
