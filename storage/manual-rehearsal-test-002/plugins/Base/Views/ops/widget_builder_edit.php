<?php
declare(strict_types=1);

$blueprint      = isset($blueprint) && is_array($blueprint) ? $blueprint : [];
$datasets       = isset($datasets) && is_array($datasets) ? $datasets : [];
$templates      = isset($templates) && is_array($templates) ? $templates : [];
$zones          = isset($zones) && is_array($zones) ? $zones : [];
$runtimeFieldMap= isset($runtimeFieldMap) && is_array($runtimeFieldMap) ? $runtimeFieldMap : [];

$blueprintId      = (int)($blueprint['id'] ?? 0);
$blueprintAppKey  = trim((string)($blueprint['app_key'] ?? ''));
$blueprintModule  = trim((string)($blueprint['module_key'] ?? ''));
$blueprintWidget  = trim((string)($blueprint['widget_key'] ?? ''));
$selectedTemplate = trim((string)($blueprint['template_type'] ?? ''));
$selectedDataset  = trim((string)($blueprint['dataset_key'] ?? ''));
$selectedZone     = trim((string)($blueprint['placement_zone'] ?? ''));
$currentTitleKey  = trim((string)($blueprint['title_key'] ?? ''));
$currentDescKey   = trim((string)($blueprint['description_key'] ?? ''));
$currentConfig    = trim((string)($blueprint['config_json'] ?? ''));

$csrf      = (string)($csrfToken ?? '');
$flashKey  = trim((string)($flash ?? ''));
$errorKey  = trim((string)($error ?? ''));
$flashText = $flashKey !== '' ? (string)t($flashKey) : '';
$errorText = $errorKey !== '' ? (string)t($errorKey) : '';

$datasetMetaMap = [];
foreach ($datasets as $dataset) {
  $dk = trim((string)($dataset['dataset_key'] ?? ''));
  if ($dk === '') {
    continue;
  }
  $datasetMetaMap[$dk] = [
    'app_key'    => strtolower(trim((string)($dataset['app_key'] ?? ''))),
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
<h2 class="u-style-6c002e2180"><?= e(t('ops.widget_builder.edit.page_title')) ?></h2>
<p class="muted u-style-0cc6790685">
  <a href="/ops/widget-builder"><?= e(t('ops.widget_builder.nav.back_to_list')) ?></a>
</p>

<?php if ($flashText !== ''): ?>
  <div class="note success u-style-3ef1fa1aa1"><?= e($flashText) ?></div>
<?php endif; ?>
<?php if ($errorText !== ''): ?>
  <div class="note warning u-style-3ef1fa1aa1"><?= e($errorText) ?></div>
<?php endif; ?>

<section class="card u-style-5b6aad9a3f">
  <h3 class="u-style-291b7bbb01"><?= e(t('ops.widget_builder.edit.identity_title')) ?></h3>
  <div class="u-style-7936f1e61f">
    <div class="ui-block">
      <label class="form-label"><?= e(t('ops.widget_builder.form.app_key')) ?></label>
      <input class="form-input" value="<?= e($blueprintAppKey) ?>" disabled>
    </div>
    <div class="ui-block">
      <label class="form-label"><?= e(t('ops.widget_builder.form.module_key')) ?></label>
      <input class="form-input" value="<?= e($blueprintModule) ?>" disabled>
    </div>
    <div class="ui-block">
      <label class="form-label"><?= e(t('ops.widget_builder.form.widget_key')) ?></label>
      <input class="form-input" value="<?= e($blueprintWidget) ?>" disabled>
    </div>
  </div>
  <p class="muted u-style-f0cc92f4b2"><?= e(t('ops.widget_builder.edit.identity_locked_hint')) ?></p>
</section>

<section class="card">
  <div class="u-style-0865a192f9">
    <h3 class="u-style-291b7bbb01"><?= e(t('ops.widget_builder.edit.form_title')) ?></h3>
    <a href="/ops/widget-builder" class="btn btn-sm"><?= e(t('ops.widget_builder.form.clear')) ?></a>
  </div>

  <form class="u-style-c317fec12a" method="post" action="/ops/widget-builder/update">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="blueprint_id" value="<?= e((string)$blueprintId) ?>">

    <div class="u-style-7936f1e61f">
      <div class="ui-block">
        <label for="wbe_template_type" class="form-label"><?= e(t('ops.widget_builder.form.template_type')) ?></label>
        <select id="wbe_template_type" class="form-select" name="template_type" required>
          <?php foreach ($templates as $tmpl): ?>
            <?php $tmplKey = (string)($tmpl['template_type'] ?? ''); ?>
            <option value="<?= e($tmplKey) ?>" <?= $selectedTemplate === $tmplKey ? 'selected' : '' ?>>
              <?= e(t((string)($tmpl['label_key'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ui-block">
        <label for="wbe_dataset_key" class="form-label"><?= e(t('ops.widget_builder.form.dataset_key')) ?></label>
        <select id="wbe_dataset_key" class="form-select" name="dataset_key" required>
          <?php foreach ($datasets as $dataset): ?>
            <?php $dk = (string)($dataset['dataset_key'] ?? ''); ?>
            <option value="<?= e($dk) ?>" <?= $selectedDataset === $dk ? 'selected' : '' ?>>
              <?= e(t((string)($dataset['label_key'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ui-block">
        <label for="wbe_placement_zone" class="form-label"><?= e(t('ops.widget_builder.form.placement_zone')) ?></label>
        <select id="wbe_placement_zone" class="form-select" name="placement_zone" required>
          <?php foreach ($zones as $zone): ?>
            <?php $zk = (string)($zone['placement_zone'] ?? ''); ?>
            <option value="<?= e($zk) ?>" <?= $selectedZone === $zk ? 'selected' : '' ?>>
              <?= e(t((string)($zone['label_key'] ?? ''))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="u-style-3033c1b2cb">
      <div class="ui-block">
        <label for="wbe_title_key" class="form-label"><?= e(t('ops.widget_builder.form.title_key')) ?></label>
        <input id="wbe_title_key" class="form-input" name="title_key" required maxlength="190" value="<?= e($currentTitleKey) ?>">
      </div>
      <div class="ui-block">
        <label for="wbe_description_key" class="form-label"><?= e(t('ops.widget_builder.form.description_key')) ?></label>
        <input id="wbe_description_key" class="form-input" name="description_key" maxlength="190" value="<?= e($currentDescKey) ?>">
      </div>
    </div>

    <div class="ui-block">
      <label for="wbe_config_json" class="form-label"><?= e(t('ops.widget_builder.form.config_json')) ?></label>
      <textarea id="wbe_config_json" class="form-textarea" name="config_json" rows="6"
        placeholder="<?= e(t('ops.widget_builder.form.config_placeholder')) ?>"><?= e($currentConfig !== '' ? $currentConfig : '{}') ?></textarea>
      <div class="u-style-183493331d">
        <span class="muted"><?= e(t('ops.widget_builder.preset.title')) ?></span>
        <button type="button" class="btn btn-sm" id="wbe_apply_dataset_preset"><?= e(t('ops.widget_builder.preset.apply_dataset')) ?></button>
        <button type="button" class="btn btn-sm" id="wbe_apply_template_preset"><?= e(t('ops.widget_builder.preset.apply_template')) ?></button>
      </div>
      <div class="u-style-183493331d">
        <label for="wbe_runtime_field_picker" class="muted u-style-1da9facb4d"><?= e(t('ops.widget_builder.picker.runtime_field')) ?></label>
        <select id="wbe_runtime_field_picker" class="form-select u-style-213c7882cb"></select>
        <button type="button" class="btn btn-sm" id="wbe_set_metric_field"><?= e(t('ops.widget_builder.picker.set_metric')) ?></button>
        <button type="button" class="btn btn-sm" id="wbe_set_subtitle_field"><?= e(t('ops.widget_builder.picker.set_subtitle')) ?></button>
        <button type="button" class="btn btn-sm" id="wbe_add_preview_field"><?= e(t('ops.widget_builder.picker.add_preview')) ?></button>
      </div>
      <div class="u-style-b1c214091c">
        <div class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.structured.title')) ?></div>
        <div class="u-style-b5fb326152">
          <div class="ui-block" id="wbe_struct_metric_wrap">
            <label for="wbe_struct_metric" class="form-label"><?= e(t('ops.widget_builder.structured.metric_field')) ?></label>
            <select id="wbe_struct_metric" class="form-select"></select>
          </div>
          <div class="ui-block" id="wbe_struct_subtitle_wrap">
            <label for="wbe_struct_subtitle" class="form-label"><?= e(t('ops.widget_builder.structured.subtitle_field')) ?></label>
            <select id="wbe_struct_subtitle" class="form-select"></select>
          </div>
          <div class="ui-block" id="wbe_struct_aggregation_wrap" hidden>
            <label for="wbe_struct_aggregation" class="form-label"><?= e(t('ops.widget_builder.structured.aggregation')) ?></label>
            <select id="wbe_struct_aggregation" class="form-select">
              <option value=""><?= e(t('ops.widget_builder.structured.none')) ?></option>
              <option value="count">count</option>
              <option value="sum">sum</option>
              <option value="avg">avg</option>
              <option value="min">min</option>
              <option value="max">max</option>
            </select>
          </div>
          <div class="ui-block" id="wbe_struct_limit_wrap">
            <label for="wbe_struct_limit" class="form-label"><?= e(t('ops.widget_builder.structured.limit')) ?></label>
            <input id="wbe_struct_limit" class="form-input" type="number" min="1" max="200" step="1" value="10">
          </div>
        </div>
        <div class="ui-block" id="wbe_struct_preview_wrap" hidden>
          <label for="wbe_struct_preview" class="form-label"><?= e(t('ops.widget_builder.structured.preview_fields')) ?></label>
          <select id="wbe_struct_preview" class="form-select" multiple size="4"></select>
        </div>
        <div class="u-style-cb7251ae6a">
          <button type="button" class="btn btn-sm" id="wbe_struct_load_json"><?= e(t('ops.widget_builder.structured.load_from_json')) ?></button>
          <button type="button" class="btn btn-sm btn-primary" id="wbe_struct_apply_json"><?= e(t('ops.widget_builder.structured.apply_to_json')) ?></button>
          <span class="muted u-style-41b1f51b79"><?= e(t('ops.widget_builder.structured.helper')) ?></span>
        </div>
      </div>
      <div class="muted u-style-4eb10ea5da">
        <?= e(t('ops.widget_builder.preset.helper')) ?>
      </div>
    </div>

    <div class="u-style-78cead6503">
      <button type="submit" class="btn btn-primary"><?= e(t('ops.widget_builder.edit.save_btn')) ?></button>
      <a href="/ops/widget-builder" class="btn"><?= e(t('ops.widget_builder.edit.cancel_btn')) ?></a>
    </div>
  </form>
</section>

<script>
(function() {
  const datasetMetaMap = <?= json_encode($datasetMetaMap, JSON_UNESCAPED_SLASHES) ?>;
  const presetMap = <?= json_encode($presetMap, JSON_UNESCAPED_SLASHES) ?>;
  const runtimeFieldMap = <?= json_encode($runtimeFieldMap, JSON_UNESCAPED_SLASHES) ?>;
  const templateDefaults = {
    kpi:   { metric_field: '', limit: 10 },
    queue: { metric_field: '', subtitle_field: '', limit: 15 },
    chart: { metric_field: '', aggregation: 'count', limit: 14 },
    table: { preview_fields: [], limit: 25 }
  };
  const datasetSelect      = document.getElementById('wbe_dataset_key');
  const templateSelect     = document.getElementById('wbe_template_type');
  const configField        = document.getElementById('wbe_config_json');
  const runtimeFieldPicker = document.getElementById('wbe_runtime_field_picker');
  const structMetric       = document.getElementById('wbe_struct_metric');
  const structSubtitle     = document.getElementById('wbe_struct_subtitle');
  const structAggregation  = document.getElementById('wbe_struct_aggregation');
  const structLimit        = document.getElementById('wbe_struct_limit');
  const structPreview      = document.getElementById('wbe_struct_preview');
  const structMetricWrap      = document.getElementById('wbe_struct_metric_wrap');
  const structSubtitleWrap    = document.getElementById('wbe_struct_subtitle_wrap');
  const structAggregationWrap = document.getElementById('wbe_struct_aggregation_wrap');
  const structLimitWrap       = document.getElementById('wbe_struct_limit_wrap');
  const structPreviewWrap     = document.getElementById('wbe_struct_preview_wrap');
  const structLoadBtn      = document.getElementById('wbe_struct_load_json');
  const structApplyBtn     = document.getElementById('wbe_struct_apply_json');
  const applyDatasetBtn    = document.getElementById('wbe_apply_dataset_preset');
  const applyTemplateBtn   = document.getElementById('wbe_apply_template_preset');
  const setMetricBtn       = document.getElementById('wbe_set_metric_field');
  const setSubtitleBtn     = document.getElementById('wbe_set_subtitle_field');
  const addPreviewBtn      = document.getElementById('wbe_add_preview_field');

  if (!datasetSelect || !templateSelect || !configField || !runtimeFieldPicker ||
      !structMetric || !structSubtitle || !structAggregation || !structLimit || !structPreview ||
      !structMetricWrap || !structSubtitleWrap || !structAggregationWrap || !structLimitWrap || !structPreviewWrap ||
      !structLoadBtn || !structApplyBtn || !applyDatasetBtn || !applyTemplateBtn ||
      !setMetricBtn || !setSubtitleBtn || !addPreviewBtn) {
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
      if (raw === '') { return {}; }
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
    const datasetKey    = String(datasetSelect.value || '');
    const templateType  = String(templateSelect.value || '');
    const datasetPresets = presetMap[datasetKey] || {};
    const baseTemplate  = templateDefaults[templateType] || {};
    const datasetPreset = datasetPresets[templateType] || {};
    const preset = mode === 'dataset' ? mergeConfig(baseTemplate, datasetPreset) : baseTemplate;
    writeConfig(mergeConfig(parseConfig(), preset));
    loadStructuredFromJson();
  };

  const loadStructuredFromJson = function() {
    const parsed = parseConfig();
    structMetric.value     = String(parsed.metric_field || '');
    structSubtitle.value   = String(parsed.subtitle_field || '');
    structAggregation.value = String(parsed.aggregation || '');
    const numericLimit = Number(parsed.limit);
    structLimit.value = Number.isFinite(numericLimit) && numericLimit > 0 ? String(Math.trunc(numericLimit)) : '10';
    const previewValues = Array.isArray(parsed.preview_fields) ? parsed.preview_fields.map(function(v) { return String(v); }) : [];
    Array.from(structPreview.options).forEach(function(opt) {
      opt.selected = previewValues.includes(String(opt.value));
    });
  };

  const applyStructuredToJson = function() {
    const next         = parseConfig();
    const templateType = String(templateSelect.value || '');
    const isTable      = templateType === 'table';
    const isChart      = templateType === 'chart';
    const metric       = String(structMetric.value || '').trim();
    const subtitle     = String(structSubtitle.value || '').trim();
    const aggregation  = String(structAggregation.value || '').trim();
    const limit        = Number(structLimit.value);
    const preview      = Array.from(structPreview.selectedOptions)
      .map(function(opt) { return String(opt.value || '').trim(); })
      .filter(function(v) { return v !== ''; })
      .slice(0, 6);
    if (!isTable && metric !== '')     { next.metric_field  = metric; }     else { delete next.metric_field; }
    if (!isTable && subtitle !== '')   { next.subtitle_field = subtitle; }  else { delete next.subtitle_field; }
    if (isChart && aggregation !== '') { next.aggregation   = aggregation; } else { delete next.aggregation; }
    if (Number.isFinite(limit) && limit >= 1 && limit <= 200) { next.limit = Math.trunc(limit); } else { delete next.limit; }
    if (isTable && preview.length > 0) { next.preview_fields = preview; } else { delete next.preview_fields; }
    writeConfig(next);
  };

  applyDatasetBtn.addEventListener('click', function() { applyPreset('dataset'); });
  applyTemplateBtn.addEventListener('click', function() { applyPreset('template'); });
  setMetricBtn.addEventListener('click', function() {
    const field = selectedRuntimeField();
    if (field === '') { return; }
    writeConfig(mergeConfig(parseConfig(), { metric_field: field }));
  });
  setSubtitleBtn.addEventListener('click', function() {
    const field = selectedRuntimeField();
    if (field === '') { return; }
    writeConfig(mergeConfig(parseConfig(), { subtitle_field: field }));
  });
  addPreviewBtn.addEventListener('click', function() {
    const field = selectedRuntimeField();
    if (field === '') { return; }
    const parsed  = parseConfig();
    const current = Array.isArray(parsed.preview_fields) ? parsed.preview_fields.map(function(v) { return String(v); }) : [];
    if (!current.includes(field)) { current.push(field); }
    writeConfig(mergeConfig(parsed, { preview_fields: current.slice(0, 6) }));
  });
  datasetSelect.addEventListener('change', refreshRuntimeFieldOptions);
  templateSelect.addEventListener('change', syncTemplateVisibility);
  structLoadBtn.addEventListener('click', loadStructuredFromJson);
  structApplyBtn.addEventListener('click', applyStructuredToJson);
  refreshRuntimeFieldOptions();
  syncTemplateVisibility();
  loadStructuredFromJson();
}());
</script>
