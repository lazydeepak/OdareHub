<?php
$rows = isset($rows) && is_array($rows) ? $rows : [];
$datasets = isset($datasets) && is_array($datasets) ? $datasets : [];
$runtimeFieldMap = isset($runtimeFieldMap) && is_array($runtimeFieldMap) ? $runtimeFieldMap : [];
$templates = isset($templates) && is_array($templates) ? $templates : [];
$zones = isset($zones) && is_array($zones) ? $zones : [];
$defaults = isset($defaultValues) && is_array($defaultValues) ? $defaultValues : [];
$validationReport = isset($validationReport) && is_array($validationReport) ? $validationReport : [];
$statusFilter = strtolower(trim((string)($statusFilter ?? 'active')));
$datasetFilter = trim((string)($datasetFilter ?? 'all'));
$readinessFilter = strtolower(trim((string)($readinessFilter ?? 'all')));
$sortFilter = strtolower(trim((string)($sortFilter ?? 'updated_desc')));
$searchFilter = trim((string)($searchFilter ?? ''));
$summaryCounts = isset($summaryCounts) && is_array($summaryCounts) ? $summaryCounts : ['all' => count($rows), 'draft' => 0, 'published' => 0, 'archived' => 0, 'ready' => 0, 'blocked' => 0, 'ready_draft' => 0];
$totalRows = isset($totalRows) ? (int)$totalRows : count($rows);
$filteredRows = isset($filteredRows) ? (int)$filteredRows : count($rows);
$page = isset($page) ? max(1, (int)$page) : 1;
$perPage = isset($perPage) ? max(2, min(100, (int)$perPage)) : 20;
$totalPages = isset($totalPages) ? max(1, (int)$totalPages) : 1;
$currentQuery = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
$redirectTo = '/ops/widget-builder' . ($currentQuery !== '' ? ('?' . $currentQuery) : '');

$csrf = (string)($csrf ?? '');
$flashKey = trim((string)($flash ?? ''));
$errorKey = trim((string)($error ?? ''));
$flashText = $flashKey !== '' ? t($flashKey) : '';
$errorText = $errorKey !== '' ? t($errorKey) : '';
$bulkMeta = isset($bulkMeta) && is_array($bulkMeta) ? $bulkMeta : null;
$bulkActionLabelKey = $bulkMeta !== null ? trim((string)($bulkMeta['action_label_key'] ?? '')) : '';
$bulkSuccess = $bulkMeta !== null ? max(0, (int)($bulkMeta['success'] ?? 0)) : 0;
$bulkSelected = $bulkMeta !== null ? max(0, (int)($bulkMeta['selected'] ?? 0)) : 0;

$datasetLabelMap = [];
foreach ($datasets as $dataset) {
  $datasetKey = trim((string)($dataset['dataset_key'] ?? ''));
  if ($datasetKey === '') {
    continue;
  }
  $datasetLabelMap[$datasetKey] = t((string)($dataset['label_key'] ?? $datasetKey));
}

$buildFilterUrl = static function(array $query): string {
  $queryString = http_build_query($query);
  return '/ops/widget-builder' . ($queryString !== '' ? ('?' . $queryString) : '');
};

$activeFilterChips = [];
$filterBase = is_array($_GET) ? $_GET : [];

if ($searchFilter !== '') {
  $q = $filterBase;
  unset($q['q'], $q['page']);
  $activeFilterChips[] = [
    'label' => t('ops.widget_builder.filter.search'),
    'value' => $searchFilter,
    'url' => $buildFilterUrl($q),
  ];
}
if ($statusFilter !== 'active') {
  $statusLabelMap = [
    'all' => t('ops.widget_builder.filter.all'),
    'draft' => t('ops.widget_builder.status.draft'),
    'published' => t('ops.widget_builder.status.published'),
    'archived' => t('ops.widget_builder.status.archived'),
  ];
  $q = $filterBase;
  unset($q['status'], $q['page']);
  $activeFilterChips[] = [
    'label' => t('ops.widget_builder.filter.status'),
    'value' => (string)($statusLabelMap[$statusFilter] ?? $statusFilter),
    'url' => $buildFilterUrl($q),
  ];
}
if ($datasetFilter !== '' && $datasetFilter !== 'all') {
  $q = $filterBase;
  unset($q['dataset'], $q['page']);
  $activeFilterChips[] = [
    'label' => t('ops.widget_builder.filter.dataset'),
    'value' => (string)($datasetLabelMap[$datasetFilter] ?? $datasetFilter),
    'url' => $buildFilterUrl($q),
  ];
}
if ($readinessFilter !== 'all') {
  $readinessLabelMap = [
    'ready' => t('ops.widget_builder.validation.ready'),
    'blocked' => t('ops.widget_builder.validation.blocked'),
  ];
  $q = $filterBase;
  unset($q['readiness'], $q['page']);
  $activeFilterChips[] = [
    'label' => t('ops.widget_builder.filter.readiness'),
    'value' => (string)($readinessLabelMap[$readinessFilter] ?? $readinessFilter),
    'url' => $buildFilterUrl($q),
  ];
}
if ($sortFilter !== 'updated_desc') {
  $sortLabelMap = [
    'updated_asc' => t('ops.widget_builder.list.col_updated') . ' ^',
    'status' => t('ops.widget_builder.list.col_status'),
    'readiness' => t('ops.widget_builder.list.col_validation'),
  ];
  $q = $filterBase;
  unset($q['sort'], $q['page']);
  $activeFilterChips[] = [
    'label' => t('ops.widget_builder.filter.sort'),
    'value' => (string)($sortLabelMap[$sortFilter] ?? $sortFilter),
    'url' => $buildFilterUrl($q),
  ];
}
if ($perPage !== 20) {
  $q = $filterBase;
  unset($q['per_page'], $q['page']);
  $activeFilterChips[] = [
    'label' => t('ops.widget_builder.filter.per_page'),
    'value' => (string)$perPage,
    'url' => $buildFilterUrl($q),
  ];
}

$selectedDataset = trim((string)($defaults['dataset_key'] ?? ''));
$selectedTemplate = trim((string)($defaults['template_type'] ?? ''));
$selectedZone = trim((string)($defaults['placement_zone'] ?? ''));
$datasetMetaMap = [];
foreach ($datasets as $dataset) {
  $datasetKey = trim((string)($dataset['dataset_key'] ?? ''));
  if ($datasetKey === '') {
    continue;
  }
  $datasetMetaMap[$datasetKey] = [
    'app_key' => strtolower(trim((string)($dataset['app_key'] ?? ''))),
    'module_key' => strtolower(trim((string)($dataset['module_key'] ?? ''))),
  ];
}
$presetMap = [];
foreach ($datasets as $dataset) {
  $datasetKey = trim((string)($dataset['dataset_key'] ?? ''));
  $templateDefaults = is_array($dataset['template_defaults'] ?? null) ? (array)$dataset['template_defaults'] : [];
  if ($datasetKey !== '' && $templateDefaults !== []) {
    $presetMap[$datasetKey] = $templateDefaults;
  }
}
?>

<section class="card">
  <h2 class="u-style-4ef9babeac"><?= e(t('ops.widget_builder.title')) ?></h2>
  <div class="muted"><?= e(t('ops.widget_builder.subtitle')) ?></div>
  <?php if ($flashText !== ''): ?>
    <div class="note success u-style-d8a81eac84"><?= e($flashText) ?></div>
    <?php if ($bulkActionLabelKey !== '' && $bulkSelected > 0): ?>
      <div class="muted u-style-edbcad1f6b"><?= e(t($bulkActionLabelKey)) ?>: <?= $bulkSuccess ?>/<?= $bulkSelected ?></div>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($errorText !== ''): ?>
    <div class="note warning u-style-d8a81eac84"><?= e($errorText) ?></div>
  <?php endif; ?>
</section>

<section class="card">
  <div class="u-style-0865a192f9">
    <h3 class="u-style-291b7bbb01"><?= e(t('ops.widget_builder.form.title')) ?></h3>
    <a href="/ops/widget-builder" class="btn btn-sm"><?= e(t('ops.widget_builder.form.clear')) ?></a>
  </div>
  <form class="u-style-c317fec12a" method="post" action="/ops/widget-builder/save">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">

    <div class="u-style-7936f1e61f">
      <div class="ui-block">
        <label for="wb_app_key" class="form-label"><?= e(t('ops.widget_builder.form.app_key')) ?></label>
        <input id="wb_app_key" class="form-input" name="app_key" required maxlength="80" value="<?= e((string)($defaults['app_key'] ?? '')) ?>">
      </div>
      <div class="ui-block">
        <label for="wb_module_key" class="form-label"><?= e(t('ops.widget_builder.form.module_key')) ?></label>
        <input id="wb_module_key" class="form-input" name="module_key" required maxlength="120" value="<?= e((string)($defaults['module_key'] ?? '')) ?>">
      </div>
      <div class="ui-block">
        <label for="wb_widget_key" class="form-label"><?= e(t('ops.widget_builder.form.widget_key')) ?></label>
        <input id="wb_widget_key" class="form-input" name="widget_key" maxlength="140" value="<?= e((string)($defaults['widget_key'] ?? '')) ?>">
      </div>
    </div>
    <div class="u-style-cb7251ae6a">
      <button type="button" class="btn btn-sm" id="wb_sync_app_module_from_dataset"><?= e(t('ops.widget_builder.form.sync_from_dataset')) ?></button>
      <span class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.form.sync_from_dataset_helper')) ?></span>
    </div>

    <div class="u-style-7936f1e61f">
      <div class="ui-block">
        <label for="wb_template_type" class="form-label"><?= e(t('ops.widget_builder.form.template_type')) ?></label>
        <select id="wb_template_type" class="form-select" name="template_type" required>
          <?php foreach ($templates as $template): ?>
            <?php $templateType = (string)($template['template_type'] ?? ''); ?>
            <option value="<?= e($templateType) ?>" <?= $selectedTemplate === $templateType ? 'selected' : '' ?>>
              <?= e(t((string)($template['label_key'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ui-block">
        <label for="wb_dataset_key" class="form-label"><?= e(t('ops.widget_builder.form.dataset_key')) ?></label>
        <select id="wb_dataset_key" class="form-select" name="dataset_key" required>
          <?php foreach ($datasets as $dataset): ?>
            <?php $datasetKey = (string)($dataset['dataset_key'] ?? ''); ?>
            <option value="<?= e($datasetKey) ?>" <?= $selectedDataset === $datasetKey ? 'selected' : '' ?>>
              <?= e(t((string)($dataset['label_key'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ui-block">
        <label for="wb_placement_zone" class="form-label"><?= e(t('ops.widget_builder.form.placement_zone')) ?></label>
        <select id="wb_placement_zone" class="form-select" name="placement_zone" required>
          <?php foreach ($zones as $zone): ?>
            <?php $zoneKey = (string)($zone['placement_zone'] ?? ''); ?>
            <option value="<?= e($zoneKey) ?>" <?= $selectedZone === $zoneKey ? 'selected' : '' ?>>
              <?= e(t((string)($zone['label_key'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="u-style-3033c1b2cb">
      <div class="ui-block">
        <label for="wb_title_key" class="form-label"><?= e(t('ops.widget_builder.form.title_key')) ?></label>
        <input id="wb_title_key" class="form-input" name="title_key" required maxlength="190" value="<?= e((string)($defaults['title_key'] ?? '')) ?>">
      </div>
      <div class="ui-block">
        <label for="wb_description_key" class="form-label"><?= e(t('ops.widget_builder.form.description_key')) ?></label>
        <input id="wb_description_key" class="form-input" name="description_key" maxlength="190" value="<?= e((string)($defaults['description_key'] ?? '')) ?>">
      </div>
    </div>

    <div class="ui-block">
      <label for="wb_config_json" class="form-label"><?= e(t('ops.widget_builder.form.config_json')) ?></label>
      <textarea id="wb_config_json" class="form-textarea" name="config_json" rows="6" placeholder="<?= e(t('ops.widget_builder.form.config_placeholder')) ?>"><?= e((string)($defaults['config_json'] ?? '{}')) ?></textarea>
      <div class="u-style-183493331d">
        <span class="muted"><?= e(t('ops.widget_builder.preset.title')) ?></span>
        <button type="button" class="btn btn-sm" id="wb_apply_dataset_preset"><?= e(t('ops.widget_builder.preset.apply_dataset')) ?></button>
        <button type="button" class="btn btn-sm" id="wb_apply_template_preset"><?= e(t('ops.widget_builder.preset.apply_template')) ?></button>
      </div>
      <div class="u-style-183493331d">
        <label for="wb_runtime_field_picker" class="muted u-style-1da9facb4d"><?= e(t('ops.widget_builder.picker.runtime_field')) ?></label>
        <select id="wb_runtime_field_picker" class="form-select u-style-213c7882cb"></select>
        <button type="button" class="btn btn-sm" id="wb_set_metric_field"><?= e(t('ops.widget_builder.picker.set_metric')) ?></button>
        <button type="button" class="btn btn-sm" id="wb_set_subtitle_field"><?= e(t('ops.widget_builder.picker.set_subtitle')) ?></button>
        <button type="button" class="btn btn-sm" id="wb_add_preview_field"><?= e(t('ops.widget_builder.picker.add_preview')) ?></button>
      </div>
      <div class="u-style-b1c214091c">
        <div class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.structured.title')) ?></div>
        <div class="u-style-b5fb326152">
          <div class="ui-block" id="wb_struct_metric_wrap">
            <label for="wb_struct_metric" class="form-label"><?= e(t('ops.widget_builder.structured.metric_field')) ?></label>
            <select id="wb_struct_metric" class="form-select"></select>
          </div>
          <div class="ui-block" id="wb_struct_subtitle_wrap">
            <label for="wb_struct_subtitle" class="form-label"><?= e(t('ops.widget_builder.structured.subtitle_field')) ?></label>
            <select id="wb_struct_subtitle" class="form-select"></select>
          </div>
          <div class="ui-block" id="wb_struct_aggregation_wrap" hidden>
            <label for="wb_struct_aggregation" class="form-label"><?= e(t('ops.widget_builder.structured.aggregation')) ?></label>
            <select id="wb_struct_aggregation" class="form-select">
              <option value=""><?= e(t('ops.widget_builder.structured.none')) ?></option>
              <option value="count">count</option>
              <option value="sum">sum</option>
              <option value="avg">avg</option>
              <option value="min">min</option>
              <option value="max">max</option>
            </select>
          </div>
          <div class="ui-block" id="wb_struct_limit_wrap">
            <label for="wb_struct_limit" class="form-label"><?= e(t('ops.widget_builder.structured.limit')) ?></label>
            <input id="wb_struct_limit" class="form-input" type="number" min="1" max="200" step="1" value="10">
          </div>
        </div>
        <div class="ui-block" id="wb_struct_preview_wrap" hidden>
          <label for="wb_struct_preview" class="form-label"><?= e(t('ops.widget_builder.structured.preview_fields')) ?></label>
          <select id="wb_struct_preview" class="form-select" multiple size="4"></select>
        </div>
        <div class="u-style-cb7251ae6a">
          <button type="button" class="btn btn-sm" id="wb_struct_load_json"><?= e(t('ops.widget_builder.structured.load_from_json')) ?></button>
          <button type="button" class="btn btn-sm btn-primary" id="wb_struct_apply_json"><?= e(t('ops.widget_builder.structured.apply_to_json')) ?></button>
          <span class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.structured.helper')) ?></span>
        </div>
      </div>
      <div class="muted u-style-4eb10ea5da">
        <?= e(t('ops.widget_builder.preset.helper')) ?>
      </div>
    </div>

    <div class="u-style-78cead6503">
      <button type="submit" class="btn btn-primary"><?= e(t('ops.widget_builder.form.save')) ?></button>
      <span class="muted"><?= e(t('ops.widget_builder.form.helper')) ?></span>
    </div>
  </form>
</section>

<script>
(function() {
  const datasetMetaMap = <?= json_encode($datasetMetaMap, JSON_UNESCAPED_SLASHES) ?>;
  const presetMap = <?= json_encode($presetMap, JSON_UNESCAPED_SLASHES) ?>;
  const runtimeFieldMap = <?= json_encode($runtimeFieldMap, JSON_UNESCAPED_SLASHES) ?>;
  const templateDefaults = {
    kpi: { metric_field: '', limit: 10 },
    queue: { metric_field: '', subtitle_field: '', limit: 15 },
    chart: { metric_field: '', aggregation: 'count', limit: 14 },
    table: { preview_fields: [], limit: 25 }
  };
  const datasetSelect = document.getElementById('wb_dataset_key');
  const templateSelect = document.getElementById('wb_template_type');
  const appKeyField = document.getElementById('wb_app_key');
  const moduleKeyField = document.getElementById('wb_module_key');
  const syncFromDatasetBtn = document.getElementById('wb_sync_app_module_from_dataset');
  const configField = document.getElementById('wb_config_json');
  const runtimeFieldPicker = document.getElementById('wb_runtime_field_picker');
  const structMetric = document.getElementById('wb_struct_metric');
  const structSubtitle = document.getElementById('wb_struct_subtitle');
  const structAggregation = document.getElementById('wb_struct_aggregation');
  const structLimit = document.getElementById('wb_struct_limit');
  const structPreview = document.getElementById('wb_struct_preview');
  const structMetricWrap = document.getElementById('wb_struct_metric_wrap');
  const structSubtitleWrap = document.getElementById('wb_struct_subtitle_wrap');
  const structAggregationWrap = document.getElementById('wb_struct_aggregation_wrap');
  const structLimitWrap = document.getElementById('wb_struct_limit_wrap');
  const structPreviewWrap = document.getElementById('wb_struct_preview_wrap');
  const structLoadBtn = document.getElementById('wb_struct_load_json');
  const structApplyBtn = document.getElementById('wb_struct_apply_json');
  const applyDatasetBtn = document.getElementById('wb_apply_dataset_preset');
  const applyTemplateBtn = document.getElementById('wb_apply_template_preset');
  const setMetricBtn = document.getElementById('wb_set_metric_field');
  const setSubtitleBtn = document.getElementById('wb_set_subtitle_field');
  const addPreviewBtn = document.getElementById('wb_add_preview_field');

  if (!datasetSelect || !templateSelect || !appKeyField || !moduleKeyField || !syncFromDatasetBtn || !configField || !runtimeFieldPicker || !structMetric || !structSubtitle || !structAggregation || !structLimit || !structPreview || !structMetricWrap || !structSubtitleWrap || !structAggregationWrap || !structLimitWrap || !structPreviewWrap || !structLoadBtn || !structApplyBtn || !applyDatasetBtn || !applyTemplateBtn || !setMetricBtn || !setSubtitleBtn || !addPreviewBtn) {
    return;
  }

  const mergeConfig = function(base, next) {
    const merged = {};
    Object.keys(base || {}).forEach(function(key) { merged[key] = base[key]; });
    Object.keys(next || {}).forEach(function(key) { merged[key] = next[key]; });
    return merged;
  };

  const parseConfig = function() {
    try {
      const raw = String(configField.value || '').trim();
      if (raw === '') {
        return {};
      }
      const decoded = JSON.parse(raw);
      return decoded && typeof decoded === 'object' && !Array.isArray(decoded) ? decoded : {};
    } catch (err) {
      return {};
    }
  };

  const writeConfig = function(nextConfig) {
    configField.value = JSON.stringify(nextConfig, null, 2);
  };

  const refreshRuntimeFieldOptions = function() {
    const datasetKey = String(datasetSelect.value || '');
    const fields = Array.isArray(runtimeFieldMap[datasetKey]) ? runtimeFieldMap[datasetKey] : [];
    runtimeFieldPicker.innerHTML = '';
    fields.forEach(function(field) {
      const option = document.createElement('option');
      option.value = String(field);
      option.textContent = String(field);
      runtimeFieldPicker.appendChild(option);
    });

    const resetSelectOptions = function(selectEl, includeEmpty) {
      selectEl.innerHTML = '';
      if (includeEmpty) {
        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = '';
        selectEl.appendChild(emptyOption);
      }
      fields.forEach(function(field) {
        const option = document.createElement('option');
        option.value = String(field);
        option.textContent = String(field);
        selectEl.appendChild(option);
      });
    };

    resetSelectOptions(structMetric, true);
    resetSelectOptions(structSubtitle, true);
    resetSelectOptions(structPreview, false);
  };

  const selectedRuntimeField = function() {
    return String(runtimeFieldPicker.value || '').trim();
  };

  const syncAppModuleFromDataset = function() {
    const datasetKey = String(datasetSelect.value || '').trim();
    const meta = datasetMetaMap[datasetKey] || {};
    const appKey = String(meta.app_key || '').trim();
    const moduleKey = String(meta.module_key || '').trim();
    if (appKey !== '') {
      appKeyField.value = appKey;
    }
    if (moduleKey !== '') {
      moduleKeyField.value = moduleKey;
    }
  };

  const showControl = function(container, shouldShow) {
    container.hidden = !shouldShow;
  };

  const syncTemplateVisibility = function() {
    const templateType = String(templateSelect.value || '');
    const isTable = templateType === 'table';
    const isChart = templateType === 'chart';

    showControl(structMetricWrap, !isTable);
    showControl(structSubtitleWrap, !isTable);
    showControl(structAggregationWrap, isChart);
    showControl(structPreviewWrap, isTable);
    showControl(structLimitWrap, true);
  };

  const applyPreset = function(mode) {
    const datasetKey = String(datasetSelect.value || '');
    const templateType = String(templateSelect.value || '');
    const datasetPresets = presetMap[datasetKey] || {};
    const baseTemplate = templateDefaults[templateType] || {};
    const datasetTemplate = datasetPresets[templateType] || {};

    let preset = baseTemplate;
    if (mode === 'dataset') {
      preset = mergeConfig(baseTemplate, datasetTemplate);
    }

    const merged = mergeConfig(parseConfig(), preset);
    writeConfig(merged);
    loadStructuredFromJson();
  };

  const loadStructuredFromJson = function() {
    const parsed = parseConfig();
    structMetric.value = String(parsed.metric_field || '');
    structSubtitle.value = String(parsed.subtitle_field || '');
    structAggregation.value = String(parsed.aggregation || '');

    const numericLimit = Number(parsed.limit);
    structLimit.value = Number.isFinite(numericLimit) && numericLimit > 0 ? String(Math.trunc(numericLimit)) : '10';

    const previewValues = Array.isArray(parsed.preview_fields) ? parsed.preview_fields.map(function(v) { return String(v); }) : [];
    Array.from(structPreview.options).forEach(function(opt) {
      opt.selected = previewValues.includes(String(opt.value));
    });
  };

  const applyStructuredToJson = function() {
    const next = parseConfig();
    const templateType = String(templateSelect.value || '');
    const isTable = templateType === 'table';
    const isChart = templateType === 'chart';

    const metric = String(structMetric.value || '').trim();
    const subtitle = String(structSubtitle.value || '').trim();
    const aggregation = String(structAggregation.value || '').trim();
    const limit = Number(structLimit.value);
    const preview = Array.from(structPreview.selectedOptions).map(function(opt) { return String(opt.value || '').trim(); }).filter(function(v) { return v !== ''; }).slice(0, 6);

    if (!isTable && metric !== '') {
      next.metric_field = metric;
    } else {
      delete next.metric_field;
    }

    if (!isTable && subtitle !== '') {
      next.subtitle_field = subtitle;
    } else {
      delete next.subtitle_field;
    }

    if (isChart && aggregation !== '') {
      next.aggregation = aggregation;
    } else {
      delete next.aggregation;
    }

    if (Number.isFinite(limit) && limit >= 1 && limit <= 200) {
      next.limit = Math.trunc(limit);
    } else {
      delete next.limit;
    }

    if (isTable && preview.length > 0) {
      next.preview_fields = preview;
    } else {
      delete next.preview_fields;
    }

    writeConfig(next);
  };

  applyDatasetBtn.addEventListener('click', function() {
    applyPreset('dataset');
  });

  applyTemplateBtn.addEventListener('click', function() {
    applyPreset('template');
  });

  setMetricBtn.addEventListener('click', function() {
    const field = selectedRuntimeField();
    if (field === '') {
      return;
    }
    const merged = mergeConfig(parseConfig(), { metric_field: field });
    writeConfig(merged);
  });

  setSubtitleBtn.addEventListener('click', function() {
    const field = selectedRuntimeField();
    if (field === '') {
      return;
    }
    const merged = mergeConfig(parseConfig(), { subtitle_field: field });
    writeConfig(merged);
  });

  addPreviewBtn.addEventListener('click', function() {
    const field = selectedRuntimeField();
    if (field === '') {
      return;
    }
    const parsed = parseConfig();
    const current = Array.isArray(parsed.preview_fields) ? parsed.preview_fields.map(function(v) { return String(v); }) : [];
    if (!current.includes(field)) {
      current.push(field);
    }
    const merged = mergeConfig(parsed, { preview_fields: current.slice(0, 6) });
    writeConfig(merged);
  });

  datasetSelect.addEventListener('change', refreshRuntimeFieldOptions);
  syncFromDatasetBtn.addEventListener('click', syncAppModuleFromDataset);
  templateSelect.addEventListener('change', syncTemplateVisibility);
  structLoadBtn.addEventListener('click', loadStructuredFromJson);
  structApplyBtn.addEventListener('click', applyStructuredToJson);
  refreshRuntimeFieldOptions();
  syncAppModuleFromDataset();
  syncTemplateVisibility();
  loadStructuredFromJson();
})();
</script>

<section class="card">
  <h3 class="u-style-291b7bbb01"><?= e(t('ops.widget_builder.list.title')) ?></h3>
  <form class="u-style-94024d05d4" method="get" action="/ops/widget-builder">
    <div class="ui-block">
      <label for="wb_filter_q" class="form-label"><?= e(t('ops.widget_builder.filter.search')) ?></label>
      <input id="wb_filter_q" name="q" class="form-input widget-builder-search" value="<?= e($searchFilter) ?>" placeholder="<?= e(t('ops.widget_builder.filter.search_placeholder')) ?>">
    </div>
    <div class="ui-block">
      <label for="wb_filter_status" class="form-label"><?= e(t('ops.widget_builder.filter.status')) ?></label>
      <select id="wb_filter_status" name="status" class="form-select">
        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.filter.active_only')) ?></option>
        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.filter.all')) ?></option>
        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.status.draft')) ?></option>
        <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.status.published')) ?></option>
        <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.status.archived')) ?></option>
      </select>
    </div>
    <div class="ui-block">
      <label for="wb_filter_dataset" class="form-label"><?= e(t('ops.widget_builder.filter.dataset')) ?></label>
      <select id="wb_filter_dataset" name="dataset" class="form-select">
        <option value="all" <?= $datasetFilter === 'all' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.filter.all')) ?></option>
        <?php foreach ($datasets as $dataset): ?>
          <?php $dsKey = (string)($dataset['dataset_key'] ?? ''); ?>
          <option value="<?= e($dsKey) ?>" <?= $datasetFilter === $dsKey ? 'selected' : '' ?>>
            <?= e(t((string)($dataset['label_key'] ?? $dsKey))) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ui-block">
      <label for="wb_filter_readiness" class="form-label"><?= e(t('ops.widget_builder.filter.readiness')) ?></label>
      <select id="wb_filter_readiness" name="readiness" class="form-select">
        <option value="all" <?= $readinessFilter === 'all' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.filter.all')) ?></option>
        <option value="ready" <?= $readinessFilter === 'ready' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.validation.ready')) ?></option>
        <option value="blocked" <?= $readinessFilter === 'blocked' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.validation.blocked')) ?></option>
      </select>
    </div>
    <div class="ui-block">
      <label for="wb_filter_sort" class="form-label"><?= e(t('ops.widget_builder.filter.sort')) ?></label>
      <select id="wb_filter_sort" name="sort" class="form-select">
        <option value="updated_desc" <?= $sortFilter === 'updated_desc' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.list.col_updated')) ?> ↓</option>
        <option value="updated_asc" <?= $sortFilter === 'updated_asc' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.list.col_updated')) ?> ↑</option>
        <option value="status" <?= $sortFilter === 'status' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.list.col_status')) ?></option>
        <option value="readiness" <?= $sortFilter === 'readiness' ? 'selected' : '' ?>><?= e(t('ops.widget_builder.list.col_validation')) ?></option>
      </select>
    </div>
    <div class="ui-block">
      <label for="wb_filter_per_page" class="form-label"><?= e(t('ops.widget_builder.filter.per_page')) ?></label>
      <select id="wb_filter_per_page" name="per_page" class="form-select">
        <?php foreach ([2, 10, 20, 50, 100] as $size): ?>
          <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="u-style-78cead6503">
      <button type="submit" class="btn btn-sm"><?= e(t('ops.widget_builder.filter.apply')) ?></button>
      <a href="/ops/widget-builder" class="btn btn-sm"><?= e(t('ops.widget_builder.filter.reset')) ?></a>
      <a href="/ops/widget-builder?status=archived" class="btn btn-sm"><?= e(t('ops.widget_builder.filter.show_archived')) ?></a>
      <a href="/ops/widget-builder?status=draft&amp;readiness=ready" class="btn btn-sm"><?= e(t('ops.widget_builder.status.draft')) ?> + <?= e(t('ops.widget_builder.validation.ready')) ?></a>
    </div>
  </form>
  <?php if ($activeFilterChips !== []): ?>
    <div class="u-style-6751e4ff0e">
      <?php foreach ($activeFilterChips as $chip): ?>
        <a href="<?= e((string)$chip['url']) ?>" class="btn btn-sm"><?= e((string)$chip['label']) ?>: <?= e((string)$chip['value']) ?> x</a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="u-style-6751e4ff0e">
    <span class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.filter.results')) ?>: <?= $filteredRows ?> / <?= $totalRows ?></span>
    <span class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.filter.page')) ?>: <?= $page ?> / <?= $totalPages ?></span>
    <span class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.filter.all')) ?>: <?= (int)($summaryCounts['all'] ?? 0) ?></span>
    <span class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.status.draft')) ?>: <?= (int)($summaryCounts['draft'] ?? 0) ?></span>
    <span class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.status.published')) ?>: <?= (int)($summaryCounts['published'] ?? 0) ?></span>
    <span class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.status.archived')) ?>: <?= (int)($summaryCounts['archived'] ?? 0) ?></span>
    <span class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.validation.ready')) ?>: <?= (int)($summaryCounts['ready'] ?? 0) ?></span>
    <span class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.validation.blocked')) ?>: <?= (int)($summaryCounts['blocked'] ?? 0) ?></span>
    <span class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.status.draft')) ?> + <?= e(t('ops.widget_builder.validation.ready')) ?>: <?= (int)($summaryCounts['ready_draft'] ?? 0) ?></span>
  </div>
  <div class="u-style-6751e4ff0e">
    <button type="button" class="btn btn-sm" id="wb_select_publishable"><?= e(t('ops.widget_builder.action.select_publishable')) ?></button>
    <button type="button" class="btn btn-sm" id="wb_select_archivable"><?= e(t('ops.widget_builder.action.select_archivable')) ?></button>
    <button type="button" class="btn btn-sm" id="wb_select_restorable"><?= e(t('ops.widget_builder.action.select_restorable')) ?></button>
    <button type="button" class="btn btn-sm" id="wb_select_none"><?= e(t('ops.widget_builder.action.select_none')) ?></button>
    <span class="muted widget-builder-bulk-meta" id="wb_eligible_publish_count" data-label="<?= e(t('ops.widget_builder.action.bulk_publish')) ?>"></span>
    <span class="muted widget-builder-bulk-meta" id="wb_eligible_archive_count" data-label="<?= e(t('ops.widget_builder.action.bulk_archive')) ?>"></span>
    <span class="muted widget-builder-bulk-meta" id="wb_eligible_restore_count" data-label="<?= e(t('ops.widget_builder.action.bulk_restore')) ?>"></span>
  </div>
  <form class="u-style-6751e4ff0e" method="post" action="/ops/widget-builder/bulk-archive" id="wb_bulk_archive_form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
    <div class="ui-block" id="wb_bulk_archive_ids"></div>
    <button type="submit" class="btn btn-sm btn-warn" id="wb_bulk_archive_btn" disabled><?= e(t('ops.widget_builder.action.bulk_archive')) ?></button>
    <span
      class="muted widget-builder-bulk-meta"
      id="wb_bulk_archive_count"
      data-selected-prefix="<?= e(t('ops.widget_builder.action.bulk_archive_selected')) ?>"
      data-none-label="<?= e(t('ops.widget_builder.action.bulk_archive_none')) ?>"
    ><?= e(t('ops.widget_builder.action.bulk_archive_none')) ?></span>
  </form>
  <form class="u-style-6751e4ff0e" method="post" action="/ops/widget-builder/bulk-publish" id="wb_bulk_publish_form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
    <div class="ui-block" id="wb_bulk_publish_ids"></div>
    <button type="submit" class="btn btn-sm btn-primary" id="wb_bulk_publish_btn" disabled><?= e(t('ops.widget_builder.action.bulk_publish')) ?></button>
    <span
      class="muted widget-builder-bulk-meta"
      id="wb_bulk_publish_count"
      data-selected-prefix="<?= e(t('ops.widget_builder.action.bulk_publish_selected')) ?>"
      data-none-label="<?= e(t('ops.widget_builder.action.bulk_publish_none')) ?>"
    ><?= e(t('ops.widget_builder.action.bulk_publish_none')) ?></span>
  </form>
  <form class="u-style-6751e4ff0e" method="post" action="/ops/widget-builder/bulk-restore" id="wb_bulk_restore_form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
    <div class="ui-block" id="wb_bulk_restore_ids"></div>
    <button type="submit" class="btn btn-sm" id="wb_bulk_restore_btn" disabled><?= e(t('ops.widget_builder.action.bulk_restore')) ?></button>
    <span
      class="muted widget-builder-bulk-meta"
      id="wb_bulk_restore_count"
      data-selected-prefix="<?= e(t('ops.widget_builder.action.bulk_restore_selected')) ?>"
      data-none-label="<?= e(t('ops.widget_builder.action.bulk_restore_none')) ?>"
    ><?= e(t('ops.widget_builder.action.bulk_restore_none')) ?></span>
  </form>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>
            <input type="checkbox" id="wb_select_all_rows" aria-label="<?= e(t('ops.widget_builder.action.select_all')) ?>">
          </th>
          <th><?= e(t('ops.widget_builder.list.col_id')) ?></th>
          <th><?= e(t('ops.widget_builder.list.col_widget')) ?></th>
          <th><?= e(t('ops.widget_builder.list.col_type')) ?></th>
          <th><?= e(t('ops.widget_builder.list.col_dataset')) ?></th>
          <th><?= e(t('ops.widget_builder.list.col_zone')) ?></th>
          <th><?= e(t('ops.widget_builder.list.col_status')) ?></th>
          <th><?= e(t('ops.widget_builder.list.col_validation')) ?></th>
          <th><?= e(t('ops.widget_builder.list.col_updated')) ?></th>
          <th><?= e(t('ops.widget_builder.list.col_actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10" class="muted"><?= e(t('ops.widget_builder.list.empty')) ?></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $id = (int)($row['id'] ?? 0);
              $status = (string)($row['status'] ?? 'draft');
              $isDraft = $status === 'draft';
              $isPublished = $status === 'published';
              $rowReport = (isset($validationReport[$id]) && is_array($validationReport[$id])) ? $validationReport[$id] : ['ready' => false, 'errors' => ['ops.widget_builder.error.validation_unavailable']];
              $isReady = (bool)($rowReport['ready'] ?? false);
              $rowErrors = is_array($rowReport['errors'] ?? null) ? (array)$rowReport['errors'] : [];
            ?>
            <tr>
              <td>
                <input
                  type="checkbox"
                  class="wb_row_select"
                  value="<?= $id ?>"
                  data-status="<?= e($status) ?>"
                  data-ready="<?= $isReady ? '1' : '0' ?>"
                  aria-label="<?= e(t('ops.widget_builder.action.select_row')) ?>"
                >
              </td>
              <td><?= $id ?></td>
              <td>
                <div class="ui-block"><strong><?= e((string)($row['widget_key'] ?? '')) ?></strong></div>
                <div class="muted u-style-a6422ad83c"><?= e((string)($row['title_key'] ?? '')) ?></div>
              </td>
              <td><?= e((string)($row['template_type'] ?? '')) ?></td>
              <td><?= e((string)($row['dataset_key'] ?? '')) ?></td>
              <td><?= e((string)($row['placement_zone'] ?? '')) ?></td>
              <td><?= e(t('ops.widget_builder.status.' . $status)) ?></td>
              <td>
                <div class="muted u-style-3058542a10">
                  <?= e($isReady ? t('ops.widget_builder.validation.ready') : t('ops.widget_builder.validation.blocked')) ?>
                </div>
                <?php if (!$isReady && $rowErrors !== []): ?>
                  <ul class="u-style-bc18e87fe9">
                    <?php foreach (array_slice($rowErrors, 0, 3) as $errorKey): ?>
                      <li class="muted u-style-41b1f51b79"><?= e(t((string)$errorKey)) ?></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </td>
              <td class="muted u-style-3058542a10"><?= e((string)($row['updated_at'] ?? '')) ?></td>
              <td>
                <div class="u-style-c8d67009fe">
                  <form method="get" action="/ops/widget-builder">
                    <input type="hidden" name="app_key" value="<?= e((string)($row['app_key'] ?? '')) ?>">
                    <input type="hidden" name="module_key" value="<?= e((string)($row['module_key'] ?? '')) ?>">
                    <input type="hidden" name="widget_key" value="<?= e((string)($row['widget_key'] ?? '')) ?>">
                    <input type="hidden" name="template_type" value="<?= e((string)($row['template_type'] ?? '')) ?>">
                    <input type="hidden" name="dataset_key" value="<?= e((string)($row['dataset_key'] ?? '')) ?>">
                    <input type="hidden" name="placement_zone" value="<?= e((string)($row['placement_zone'] ?? '')) ?>">
                    <input type="hidden" name="title_key" value="<?= e((string)($row['title_key'] ?? '')) ?>">
                    <input type="hidden" name="description_key" value="<?= e((string)($row['description_key'] ?? '')) ?>">
                    <input type="hidden" name="config_json" value="<?= e((string)($row['config_json'] ?? '')) ?>">
                    <button type="submit" class="btn btn-sm"><?= e(t('ops.widget_builder.action.load')) ?></button>
                  </form>
                  <?php if ($isDraft): ?>
                    <a
                      href="/ops/widget-builder/edit?id=<?= $id ?>"
                      class="btn btn-sm"
                    ><?= e(t('ops.widget_builder.action.edit')) ?></a>
                  <?php endif; ?>
                  <form method="post" action="/ops/widget-builder/clone">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                    <input type="hidden" name="blueprint_id" value="<?= $id ?>">
                    <button
                      type="submit"
                      class="btn btn-sm"
                      <?= $isReady ? '' : 'disabled' ?>
                      title="<?= e($isReady ? t('ops.widget_builder.action.clone') : t('ops.widget_builder.validation.blocked')) ?>"
                      aria-label="<?= e($isReady ? t('ops.widget_builder.action.clone') : t('ops.widget_builder.validation.blocked')) ?>"
                    ><?= e(t('ops.widget_builder.action.clone')) ?></button>
                  </form>
                  <?php if ($isDraft): ?>
                    <form method="post" action="/ops/widget-builder/publish">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                      <input type="hidden" name="blueprint_id" value="<?= $id ?>">
                      <button
                        type="submit"
                        class="btn btn-sm btn-primary"
                        <?= $isReady ? '' : 'disabled' ?>
                        title="<?= e($isReady ? t('ops.widget_builder.action.publish') : t('ops.widget_builder.validation.blocked')) ?>"
                        aria-label="<?= e($isReady ? t('ops.widget_builder.action.publish') : t('ops.widget_builder.validation.blocked')) ?>"
                      ><?= e(t('ops.widget_builder.action.publish')) ?></button>
                    </form>
                  <?php endif; ?>
                  <?php if ($isPublished || $isDraft): ?>
                    <form method="post" action="/ops/widget-builder/archive">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                      <input type="hidden" name="blueprint_id" value="<?= $id ?>">
                      <button type="submit" class="btn btn-sm btn-warn"><?= e(t('ops.widget_builder.action.archive')) ?></button>
                    </form>
                  <?php endif; ?>
                  <?php if (!$isDraft && !$isPublished): ?>
                    <form method="post" action="/ops/widget-builder/restore">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
                      <input type="hidden" name="blueprint_id" value="<?= $id ?>">
                      <button type="submit" class="btn btn-sm"><?= e(t('ops.widget_builder.action.restore')) ?></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
    $queryBase = $_GET;
    unset($queryBase['page']);
    $buildPageUrl = static function (int $targetPage) use ($queryBase): string {
      $q = $queryBase;
      $q['page'] = $targetPage;
      return '/ops/widget-builder?' . http_build_query($q);
    };
  ?>
  <div class="u-style-0bff0adb47">
    <?php if ($page > 1): ?>
      <a href="<?= e($buildPageUrl($page - 1)) ?>" class="btn btn-sm"><?= e(t('ops.widget_builder.filter.prev')) ?></a>
    <?php else: ?>
      <button type="button" class="btn btn-sm" disabled><?= e(t('ops.widget_builder.filter.prev')) ?></button>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
      <a href="<?= e($buildPageUrl($page + 1)) ?>" class="btn btn-sm"><?= e(t('ops.widget_builder.filter.next')) ?></a>
    <?php else: ?>
      <button type="button" class="btn btn-sm" disabled><?= e(t('ops.widget_builder.filter.next')) ?></button>
    <?php endif; ?>
  </div>
</section>

<script>
(function() {
  const selectAll = document.getElementById('wb_select_all_rows');
  const selectPublishable = document.getElementById('wb_select_publishable');
  const selectArchivable = document.getElementById('wb_select_archivable');
  const selectRestorable = document.getElementById('wb_select_restorable');
  const selectNone = document.getElementById('wb_select_none');
  const eligiblePublishCount = document.getElementById('wb_eligible_publish_count');
  const eligibleArchiveCount = document.getElementById('wb_eligible_archive_count');
  const eligibleRestoreCount = document.getElementById('wb_eligible_restore_count');
  const rowChecks = Array.from(document.querySelectorAll('.wb_row_select'));
  const archiveIdsContainer = document.getElementById('wb_bulk_archive_ids');
  const archiveBtn = document.getElementById('wb_bulk_archive_btn');
  const archiveCount = document.getElementById('wb_bulk_archive_count');
  const publishIdsContainer = document.getElementById('wb_bulk_publish_ids');
  const publishBtn = document.getElementById('wb_bulk_publish_btn');
  const publishCount = document.getElementById('wb_bulk_publish_count');
  const restoreIdsContainer = document.getElementById('wb_bulk_restore_ids');
  const restoreBtn = document.getElementById('wb_bulk_restore_btn');
  const restoreCount = document.getElementById('wb_bulk_restore_count');

  if (!selectAll || !selectPublishable || !selectArchivable || !selectRestorable || !selectNone || !eligiblePublishCount || !eligibleArchiveCount || !eligibleRestoreCount || !archiveIdsContainer || !archiveBtn || !archiveCount || !publishIdsContainer || !publishBtn || !publishCount || !restoreIdsContainer || !restoreBtn || !restoreCount) {
    return;
  }

  const isEligible = function(el, action) {
    const status = String(el.getAttribute('data-status') || '').trim();
    const ready = String(el.getAttribute('data-ready') || '0') === '1';

    if (action === 'archive') {
      return status === 'draft' || status === 'published';
    }
    if (action === 'publish') {
      return status === 'draft' && ready;
    }
    if (action === 'restore') {
      return status === 'archived';
    }
    return false;
  };

  const selectedIds = function() {
    return rowChecks.filter(function(el) { return el.checked; });
  };

  const idsForAction = function(action) {
    return selectedIds().filter(function(el) {
      return isEligible(el, action);
    }).map(function(el) { return String(el.value); });
  };

  const refreshBulkState = function() {
    const archiveIds = idsForAction('archive');
    const publishIds = idsForAction('publish');
    const restoreIds = idsForAction('restore');

    archiveIdsContainer.innerHTML = '';
    publishIdsContainer.innerHTML = '';
    restoreIdsContainer.innerHTML = '';
    archiveIds.forEach(function(id) {
      const hiddenArchive = document.createElement('input');
      hiddenArchive.type = 'hidden';
      hiddenArchive.name = 'blueprint_ids[]';
      hiddenArchive.value = id;
      archiveIdsContainer.appendChild(hiddenArchive);
    });

    publishIds.forEach(function(id) {
      const hiddenPublish = document.createElement('input');
      hiddenPublish.type = 'hidden';
      hiddenPublish.name = 'blueprint_ids[]';
      hiddenPublish.value = id;
      publishIdsContainer.appendChild(hiddenPublish);
    });

    restoreIds.forEach(function(id) {
      const hiddenRestore = document.createElement('input');
      hiddenRestore.type = 'hidden';
      hiddenRestore.name = 'blueprint_ids[]';
      hiddenRestore.value = id;
      restoreIdsContainer.appendChild(hiddenRestore);
    });

    archiveBtn.disabled = archiveIds.length === 0;
    publishBtn.disabled = publishIds.length === 0;
    restoreBtn.disabled = restoreIds.length === 0;

    const archivePrefix = String(archiveCount.getAttribute('data-selected-prefix') || '');
    const archiveNone = String(archiveCount.getAttribute('data-none-label') || '');
    archiveCount.textContent = archiveIds.length > 0 ? (archivePrefix + ' ' + archiveIds.length) : archiveNone;

    const publishPrefix = String(publishCount.getAttribute('data-selected-prefix') || '');
    const publishNone = String(publishCount.getAttribute('data-none-label') || '');
    publishCount.textContent = publishIds.length > 0 ? (publishPrefix + ' ' + publishIds.length) : publishNone;

    const restorePrefix = String(restoreCount.getAttribute('data-selected-prefix') || '');
    const restoreNone = String(restoreCount.getAttribute('data-none-label') || '');
    restoreCount.textContent = restoreIds.length > 0 ? (restorePrefix + ' ' + restoreIds.length) : restoreNone;

    const selectedCount = selectedIds().length;
    selectAll.checked = rowChecks.length > 0 && selectedCount === rowChecks.length;

    const allPublishable = rowChecks.filter(function(el) { return isEligible(el, 'publish'); }).length;
    const allArchivable = rowChecks.filter(function(el) { return isEligible(el, 'archive'); }).length;
    const allRestorable = rowChecks.filter(function(el) { return isEligible(el, 'restore'); }).length;
    const publishLabel = String(eligiblePublishCount.getAttribute('data-label') || '');
    const archiveLabel = String(eligibleArchiveCount.getAttribute('data-label') || '');
    const restoreLabel = String(eligibleRestoreCount.getAttribute('data-label') || '');
    eligiblePublishCount.textContent = publishLabel + ': ' + allPublishable;
    eligibleArchiveCount.textContent = archiveLabel + ': ' + allArchivable;
    eligibleRestoreCount.textContent = restoreLabel + ': ' + allRestorable;

    selectPublishable.disabled = allPublishable === 0;
    selectArchivable.disabled = allArchivable === 0;
    selectRestorable.disabled = allRestorable === 0;
  };

  selectAll.addEventListener('change', function() {
    const checked = !!selectAll.checked;
    rowChecks.forEach(function(el) { el.checked = checked; });
    refreshBulkState();
  });

  selectPublishable.addEventListener('click', function() {
    rowChecks.forEach(function(el) { el.checked = isEligible(el, 'publish'); });
    refreshBulkState();
  });

  selectArchivable.addEventListener('click', function() {
    rowChecks.forEach(function(el) { el.checked = isEligible(el, 'archive'); });
    refreshBulkState();
  });

  selectRestorable.addEventListener('click', function() {
    rowChecks.forEach(function(el) { el.checked = isEligible(el, 'restore'); });
    refreshBulkState();
  });

  selectNone.addEventListener('click', function() {
    rowChecks.forEach(function(el) { el.checked = false; });
    refreshBulkState();
  });

  rowChecks.forEach(function(el) {
    el.addEventListener('change', refreshBulkState);
  });

  refreshBulkState();
})();
</script>
