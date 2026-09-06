<?php
$hostWidgetVisibleForMode = static function (array $item, string $mode): bool {
    $profiles = array_values(array_filter(array_map(
        static fn($profile): string => strtolower(trim((string)$profile)),
        (array)($item['interaction_profiles'] ?? [])
    ), static fn(string $profile): bool => $profile !== ''));

    if ($profiles === []) {
        return true;
    }

    return in_array($mode, $profiles, true);
};

$dedupeKey = static function (array $item): string {
    $key = trim((string)($item['widget_key'] ?? $item['key'] ?? ''));
    if ($key !== '') {
        return strtolower($key);
    }

    $title = trim((string)($item['title'] ?? $item['label'] ?? ''));
    $url = trim((string)($item['url'] ?? ''));
    return strtolower($title . '|' . $url);
};

$streamItems = [];
$seen = [];

$moduleKeyFromItem = static function (array $item): string {
  $moduleKey = strtolower(trim((string)($item['module_key'] ?? '')));
  if ($moduleKey !== '') {
    return $moduleKey;
  }

  $widgetKey = strtolower(trim((string)($item['widget_key'] ?? $item['key'] ?? '')));
  if ($widgetKey === '') {
    return '';
  }

  $normalized = preg_replace('/_(worker_queue|form|table|chart|summary)$/', '', $widgetKey);
  return is_string($normalized) ? $normalized : $widgetKey;
};

foreach (['header_actions', 'summary_cards', 'quick_links', 'monitoring_sections'] as $regionKey) {
    $regionItems = match ($regionKey) {
        'header_actions' => $hostHeaderActions,
        'summary_cards' => $hostSummaryCards,
        'quick_links' => $hostQuickLinks,
        default => $hostMonitoringSections,
    };

    foreach ((array)$regionItems as $item) {
        if (!is_array($item) || !$hostWidgetVisibleForMode($item, $experienceMode)) {
            continue;
        }

        $dKey = $dedupeKey($item);
        $pluginCardKey = strtolower(trim((string)($item['key'] ?? $item['widget_key'] ?? '')));
        if ($regionKey === 'quick_links') {
            if ($pluginCardKey === '') {
                $pluginCardKey = $dKey;
            }
            if ($pluginCardsExplicitNone || ($enabledPluginCards !== [] && !isset($enabledPluginCards[$pluginCardKey]))) {
                continue;
            }
        }
        if ($dKey !== '' && isset($seen[$dKey])) {
            continue;
        }
        if ($dKey !== '') {
            $seen[$dKey] = true;
        }

        $configuredCardOrder = is_array($enabledPluginCardOrder ?? null) ? array_flip($enabledPluginCardOrder) : [];
        $item['_priority'] = $regionKey === 'quick_links' && $pluginCardKey !== '' && isset($configuredCardOrder[$pluginCardKey])
            ? (10 + (int)$configuredCardOrder[$pluginCardKey])
            : (int)($item['priority'] ?? $item['weight'] ?? 100);
        $item['_weight'] = (int)($item['weight'] ?? 100);
        $item['_label_sort'] = strtolower(trim((string)($item['title'] ?? $item['label'] ?? '')));
        $item['_region'] = $regionKey;
        $streamItems[] = $item;
    }
}

usort($streamItems, static function (array $a, array $b): int {
    $p = ((int)($a['_priority'] ?? 100)) <=> ((int)($b['_priority'] ?? 100));
    if ($p !== 0) {
        return $p;
    }

    $w = ((int)($a['_weight'] ?? 100)) <=> ((int)($b['_weight'] ?? 100));
    if ($w !== 0) {
        return $w;
    }

    return strcmp((string)($a['_label_sort'] ?? ''), (string)($b['_label_sort'] ?? ''));
});

$moduleHasNonFormViews = [];
foreach ($streamItems as $streamItem) {
    $itemKind = strtolower(trim((string)($streamItem['kind'] ?? '')));
    $viewKind = strtolower(trim((string)($streamItem['view_kind'] ?? '')));
    $hasStructured = $itemKind !== ''
      || isset($streamItem['rows'])
      || isset($streamItem['fields'])
      || isset($streamItem['columns'])
      || in_array($viewKind, ['table', 'form', 'chart', 'queue', 'timeline', 'mixed'], true);
    if (!$hasStructured) {
      continue;
    }

    if ($itemKind === 'form' || ($itemKind === '' && $viewKind === 'form')) {
      continue;
    }

    $moduleKey = $moduleKeyFromItem($streamItem);
    if ($moduleKey !== '') {
      $moduleHasNonFormViews[$moduleKey] = true;
    }
}

$renderHostSection = static function (array $section): void {
    $title = trim((string)($section['title'] ?? $section['label'] ?? ''));
    $description = trim((string)($section['description'] ?? ''));
    $viewType = strtolower(trim((string)($section['_view_type'] ?? 'operational')));
    $fitClass = match ($viewType) {
        'my_work', 'operational_queue' => 'me-fit-medium',
        'reference', 'watchlist' => 'me-fit-small',
        default => 'me-fit-medium',
    };
    $fitTypeClass = 'me-fit-type-' . preg_replace('/[^a-z0-9_\-]+/', '-', $viewType);
    $widgetTypeRaw = trim((string)($section['widget_type'] ?? ''));
    $widgetTypeKey = 'admin.workspace.widget_type.' . str_replace([' ', '-'], '_', strtolower($widgetTypeRaw));
    $widgetTypeTranslated = $widgetTypeRaw !== '' ? (string)t($widgetTypeKey) : '';
    $typeLabel = strtoupper(
        ($widgetTypeTranslated !== $widgetTypeKey && $widgetTypeTranslated !== '')
            ? $widgetTypeTranslated
            : str_replace('_', ' ', $widgetTypeRaw)
    );
    $kind = strtolower(trim((string)($section['kind'] ?? 'table')));
    $widgetType = strtolower(trim((string)($section['widget_type'] ?? '')));
    $renderKind = $kind;
    if (in_array($widgetType, ['queue', 'watchlist'], true) && in_array($kind, ['', 'table', 'queue', 'watchlist'], true)) {
        $renderKind = 'queue';
    }
    $rows = is_array($section['rows'] ?? null) ? (array)$section['rows'] : [];
    $inlineFilter = is_array($section['inline_filter'] ?? null) ? (array)$section['inline_filter'] : [];
    $columns = array_values((array)($section['columns'] ?? []));
    $toolbarActions = array_values(array_filter((array)($section['toolbar_actions'] ?? []), 'is_array'));
    $toggles = array_values(array_filter((array)($section['toggles'] ?? []), 'is_array'));
    $sectionActionUrl = trim((string)($section['url'] ?? ''));
    if ($sectionActionUrl === '' && $toolbarActions !== []) {
      $sectionActionUrl = trim((string)($toolbarActions[0]['url'] ?? ''));
    }
    if ($sectionActionUrl === '' && $toggles !== []) {
      $sectionActionUrl = trim((string)($toggles[0]['url'] ?? ''));
    }
    if ($sectionActionUrl === '' && $rows !== []) {
      foreach ($rows as $candidateRow) {
        if (!is_array($candidateRow)) {
          continue;
        }

        $candidateUrl = trim((string)($candidateRow['url'] ?? ''));
        if ($candidateUrl !== '') {
          $sectionActionUrl = $candidateUrl;
          break;
        }
      }
    }
    $emptyMessage = trim((string)($section['empty_message'] ?? t('common.no_results_found')));
    $parsePercent = static function (mixed $value): ?float {
      if (is_int($value) || is_float($value)) {
        return max(0.0, min(100.0, (float)$value));
      }

      if (!is_string($value)) {
        return null;
      }

      $raw = trim($value);
      if ($raw === '') {
        return null;
      }

      if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*%/', $raw, $matches)) {
        return max(0.0, min(100.0, (float)$matches[1]));
      }

      if (!is_numeric($raw)) {
        return null;
      }

      return max(0.0, min(100.0, (float)$raw));
    };
    $queueRows = static function (array $sourceRows) use ($parsePercent, $sectionActionUrl): array {
      $result = [];

      foreach ($sourceRows as $row) {
        if (!is_array($row)) {
          continue;
        }

        $cells = is_array($row['cells'] ?? null) ? array_values((array)$row['cells']) : [];
        $titleText = trim((string)($row['title'] ?? ($cells[0] ?? '')));
        $subtitleText = trim((string)($row['subtitle'] ?? ($cells[1] ?? '')));
        $metaText = trim((string)($row['meta'] ?? ($cells[2] ?? '')));
        $statusText = trim((string)($row['status'] ?? $row['state'] ?? ''));
        $statusTone = strtolower(trim((string)($row['status_tone'] ?? 'info')));
        $rowActionUrl = trim((string)($row['url'] ?? ''));
        $actionUrl = $rowActionUrl !== '' ? $rowActionUrl : $sectionActionUrl;

        $progressValue = $parsePercent($row['progress_pct'] ?? $row['progress'] ?? $row['percent'] ?? null);
        if ($progressValue === null) {
          foreach ($cells as $cellValue) {
            $progressValue = $parsePercent($cellValue);
            if ($progressValue !== null) {
              break;
            }
          }
        }

        $metrics = [];
        $metricLabels = [
          'planned_qty' => t('admin.workspace.metric.planned'),
          'good_qty' => t('admin.workspace.metric.good'),
          'rejected_qty' => t('admin.workspace.metric.rejected'),
          'remaining_qty' => t('admin.workspace.metric.remaining'),
          'entry_count' => t('admin.workspace.metric.entries'),
          'entries' => t('admin.workspace.metric.entries'),
          'stock_qty' => t('admin.workspace.metric.stock'),
          'target_today_qty' => t('admin.workspace.metric.target_today'),
          'coverage_balance_qty' => t('admin.workspace.metric.coverage_balance'),
        ];

        foreach ($metricLabels as $metricKey => $metricLabel) {
          if (!array_key_exists($metricKey, $row)) {
            continue;
          }

          $metricRaw = $row[$metricKey];
          if (is_numeric($metricRaw)) {
            $metricValue = number_format((float)$metricRaw, 0, '.', ',');
          } else {
            $metricValue = trim((string)$metricRaw);
          }

          if ($metricValue === '') {
            continue;
          }

          $metrics[] = ['label' => $metricLabel, 'value' => $metricValue];
        }

        if ($titleText === '' && $subtitleText === '' && $metaText === '' && $metrics === [] && $progressValue === null) {
          continue;
        }

        $queuePreview = strtolower(trim($titleText . ' ' . $subtitleText . ' ' . $metaText . ' ' . $statusText . ' ' . implode(' ', array_map(static fn($cell): string => trim((string)$cell), $cells))));
        $queuePreview = preg_replace('/\s+/', ' ', (string)$queuePreview);
        $isWorkspaceQueuePlaceholder = preg_match('/\bworkspace\b.*\bcurrent\s+queue\b|\bcurrent\s+queue\b.*\bworkspace\b/', (string)$queuePreview) === 1;
        if ($isWorkspaceQueuePlaceholder && $rowActionUrl === '' && $progressValue === null && $metrics === []) {
          continue;
        }

        $result[] = [
          'title' => $titleText,
          'subtitle' => $subtitleText,
          'meta' => $metaText,
          'status' => $statusText,
          'status_tone' => in_array($statusTone, ['success', 'warning', 'danger', 'info', 'neutral'], true) ? $statusTone : 'info',
          'url' => $actionUrl,
          'progress' => $progressValue,
          'metrics' => $metrics,
        ];
      }

      return $result;
    };

    $queueItems = [];
    if ($renderKind === 'queue') {
      $queueItems = $queueRows($rows);
      $isMyWorkWorkerQueueCard = preg_match('/\bmy\s*work\b/i', $title) === 1
        && preg_match('/worker-side actionable module queue\./i', $description) === 1;
      $isMachineMyWorkCard = preg_match('/^(Machines|Part[- ]Machine\s+Map)\s*·\s*My\s*Work$/i', $title) === 1;
      if ($isMachineMyWorkCard && $isMyWorkWorkerQueueCard) {
        return;
      }
      if ($isMyWorkWorkerQueueCard && $queueItems === []) {
        return;
      }
    }
    ?>
    <section class="card me-fit-card <?= e($fitClass) ?> <?= e((string)$fitTypeClass) ?>">
      <?php if ($title !== '' || $toolbarActions !== []): ?>
        <div class="section-head">
          <?php if ($title !== ''): ?><h4><?= e($title) ?></h4><?php endif; ?>
          <?php if ($typeLabel !== ''): ?><span class="mapping-label"><?= e($typeLabel) ?></span><?php endif; ?>
          <?php if ($toolbarActions !== []): ?>
            <div class="row row-tight">
              <?php foreach ($toolbarActions as $action): ?>
                <a class="btn" href="<?= e((string)($action['url'] ?? '/')) ?>"><?= e((string)($action['label'] ?? t('common.open'))) ?></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($description !== ''): ?>
        <div class="muted me-card-desc"><?= e($description) ?></div>
      <?php endif; ?>

      <?php
        $showInlineFilter = in_array($renderKind, ['queue', 'table', 'timeline', 'mixed'], true)
          && $inlineFilter !== []
          && trim((string)($inlineFilter['action'] ?? '')) !== ''
          && is_array($inlineFilter['fields'] ?? null)
          && (array)$inlineFilter['fields'] !== [];
      ?>
      <?php if ($showInlineFilter): ?>
        <?php
          $filterMethod = strtolower(trim((string)($inlineFilter['method'] ?? 'get')));
          $filterAction = trim((string)($inlineFilter['action'] ?? ''));
          $filterFields = array_values(array_filter((array)($inlineFilter['fields'] ?? []), 'is_array'));
          $filterActions = array_values(array_filter((array)($inlineFilter['actions'] ?? []), 'is_array'));
          $filterTitle = trim((string)($inlineFilter['title'] ?? ''));
          $filterDescription = trim((string)($inlineFilter['description'] ?? ''));
        ?>
        <div class="me-inline-filter-wrap">
          <?php if ($filterTitle !== ''): ?><div class="muted me-filter-title"><?= e($filterTitle) ?></div><?php endif; ?>
          <?php if ($filterDescription !== ''): ?><div class="muted me-filter-desc"><?= e($filterDescription) ?></div><?php endif; ?>
          <form method="<?= e($filterMethod === 'get' ? 'get' : 'post') ?>" action="<?= e($filterAction) ?>" class="form-grid me-inline-filter-form">
            <?php foreach ($filterFields as $field): ?>
              <?php
                $fieldType = strtolower(trim((string)($field['type'] ?? 'text')));
                $fieldName = trim((string)($field['name'] ?? ''));
                if ($fieldName === '') {
                    continue;
                }
              ?>
              <div class="form-field<?= !empty($field['full']) ? '-full' : '' ?>">
                <?php if ($fieldType !== 'hidden'): ?>
                  <label class="field-label"><?= e((string)($field['label'] ?? ucfirst($fieldName))) ?></label>
                <?php endif; ?>
                <?php if ($fieldType === 'select'): ?>
                  <select name="<?= e($fieldName) ?>">
                    <?php foreach ((array)($field['options'] ?? []) as $option): ?>
                      <option value="<?= e((string)($option['value'] ?? '')) ?>" <?= !empty($option['selected']) ? 'selected' : '' ?>><?= e((string)($option['label'] ?? $option['value'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($fieldType === 'textarea'): ?>
                  <textarea name="<?= e($fieldName) ?>"><?= e((string)($field['value'] ?? '')) ?></textarea>
                <?php else: ?>
                  <input class="input" type="<?= e($fieldType !== '' ? $fieldType : 'text') ?>" name="<?= e($fieldName) ?>" value="<?= e((string)($field['value'] ?? '')) ?>">
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <div class="form-actions">
              <?php foreach ($filterActions as $actionItem): ?>
                <button class="btn<?= !empty($actionItem['primary']) ? ' ok' : '' ?>" type="<?= e((string)($actionItem['type'] ?? 'submit')) ?>"><?= e((string)($actionItem['label'] ?? t('common.submit'))) ?></button>
              <?php endforeach; ?>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($toggles !== []): ?>
          <div class="row row-tight mt-8">
          <?php foreach ($toggles as $toggle): ?>
            <?php $toggleClass = !empty($toggle['is_active']) ? 'btn ok' : 'btn'; ?>
            <a class="<?= e($toggleClass) ?>" href="<?= e((string)($toggle['url'] ?? '#')) ?>"><?= e((string)($toggle['label'] ?? t('common.open'))) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($renderKind === 'queue'): ?>
        <?php if ($queueItems === []): ?>
          <div class="muted mt-10"><?= e($emptyMessage) ?></div>
        <?php else: ?>
          <div class="me-queue-list">
            <?php foreach ($queueItems as $queueItem): ?>
              <?php
                $actionUrl = (string)($queueItem['url'] ?? '');
                $isClickable = $actionUrl !== '';
                $tagName = $isClickable ? 'a' : 'div';
                $progressValue = $queueItem['progress'];
                $statusText = trim((string)($queueItem['status'] ?? ''));
                $statusTone = strtolower(trim((string)($queueItem['status_tone'] ?? 'info')));
                $metrics = is_array($queueItem['metrics'] ?? null) ? $queueItem['metrics'] : [];
                $statusClass = match ($statusTone) {
                    'success' => 'success',
                    'warning' => 'warning',
                    'danger' => 'danger',
                    'neutral' => 'neutral',
                    default => 'info',
                };
              ?>
              <<?= $tagName ?> class="me-queue-card<?= $isClickable ? ' is-clickable' : '' ?>"<?= $isClickable ? ' href="' . e($actionUrl) . '"' : '' ?>>
                <div class="me-queue-head">
                  <h5 class="me-queue-title"><?= e((string)($queueItem['title'] ?? t('admin.workspace.fallback.queue_item'))) ?></h5>
                  <?php if ($statusText !== ''): ?>
                    <span class="status-chip <?= e($statusClass) ?>"><?= e($statusText) ?></span>
                  <?php endif; ?>
                </div>

                <?php if (trim((string)($queueItem['subtitle'] ?? '')) !== ''): ?>
                  <div class="me-queue-subtitle"><?= e((string)$queueItem['subtitle']) ?></div>
                <?php endif; ?>

                <?php if (trim((string)($queueItem['meta'] ?? '')) !== ''): ?>
                  <div class="muted me-queue-meta"><?= e((string)$queueItem['meta']) ?></div>
                <?php endif; ?>

                <?php if ($progressValue !== null): ?>
                  <progress class="me-queue-progress" max="100" value="<?= e((string)round((float)$progressValue, 1)) ?>"></progress>
                  <div class="me-queue-progress-label"><?= e(number_format((float)$progressValue, 1)) ?>%</div>
                <?php endif; ?>

                <?php if ($metrics !== []): ?>
                  <div class="me-queue-metrics">
                    <?php foreach ($metrics as $metric): ?>
                      <span class="me-queue-metric"><strong><?= e((string)($metric['value'] ?? '0')) ?></strong> <?= e((string)($metric['label'] ?? '')) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </<?= $tagName ?>>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php elseif ($renderKind === 'table' || $renderKind === 'timeline' || $renderKind === 'mixed'): ?>
        <?php if ($rows === []): ?>
          <div class="muted mt-10"><?= e($emptyMessage) ?></div>
        <?php else: ?>
          <?php
            $renderColumns = $columns;
            if ($renderColumns !== [] && $rows !== []) {
                $lastColumn = strtolower(trim((string)($renderColumns[count($renderColumns) - 1] ?? '')));
                $isActionColumn = in_array($lastColumn, ['actions', 'action', t('common.actions')], true);
                $allRowsUseSyntheticAction = $isActionColumn;

                if ($allRowsUseSyntheticAction) {
                    foreach ($rows as $row) {
                        if (!is_array($row)) {
                            $allRowsUseSyntheticAction = false;
                            break;
                        }

                        $cells = is_array($row['cells'] ?? null) ? array_values((array)$row['cells']) : [];
                        $rowUrl = trim((string)($row['url'] ?? ''));
                        if ($rowUrl === '') {
                          $rowUrl = $sectionActionUrl;
                        }
                        if ($rowUrl === '' || count($cells) !== count($renderColumns) - 1) {
                            $allRowsUseSyntheticAction = false;
                            break;
                        }
                    }
                }

                if ($allRowsUseSyntheticAction) {
                    array_pop($renderColumns);
                }
            }
          ?>
          <div class="table-wrap mt-10">
            <table>
              <?php if ($renderColumns !== []): ?>
                <thead>
                  <tr>
                    <?php foreach ($renderColumns as $column): ?>
                      <th><?= e((string)$column) ?></th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
              <?php endif; ?>
              <tbody>
                <?php foreach ($rows as $row): ?>
                  <?php
                    $cells = is_array($row['cells'] ?? null) ? array_values((array)$row['cells']) : [];
                    $actionUrl = trim((string)($row['url'] ?? ''));
                    if ($actionUrl === '') {
                      $actionUrl = $sectionActionUrl;
                    }
                    $rowClass = $actionUrl !== '' ? ' class="row-link"' : '';
                    $rowAttrs = $actionUrl !== ''
                      ? ' data-row-href="' . e($actionUrl) . '" tabindex="0"'
                      : '';
                  ?>
                  <tr<?= $rowClass ?><?= $rowAttrs ?>>
                    <?php foreach ($cells as $cell): ?>
                      <td><?= e((string)$cell) ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      <?php elseif ($renderKind === 'chart'): ?>
        <?php if ($rows === []): ?>
          <div class="muted mt-10"><?= e($emptyMessage) ?></div>
        <?php else: ?>
          <div class="coverage-kpi-grid mt-10">
            <?php foreach ($rows as $row): ?>
              <div class="coverage-kpi">
                <?php if (trim((string)($row['label'] ?? '')) !== ''): ?><div class="muted"><?= e((string)$row['label']) ?></div><?php endif; ?>
                <div class="coverage-kpi-value"><?= e((string)($row['value'] ?? '0')) ?></div>
                <?php if (trim((string)($row['meta'] ?? '')) !== ''): ?><div class="muted"><?= e((string)$row['meta']) ?></div><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php elseif ($renderKind === 'form'): ?>
        <?php
          $method = strtolower(trim((string)($section['method'] ?? 'post')));
          $action = trim((string)($section['action'] ?? ''));
          $fields = array_values(array_filter((array)($section['fields'] ?? []), 'is_array'));
        ?>
        <?php if ($action === '' || $fields === []): ?>
          <div class="muted mt-10"><?= e($emptyMessage !== '' ? $emptyMessage : t('common.no_results_found')) ?></div>
        <?php else: ?>
          <form method="<?= e($method === 'get' ? 'get' : 'post') ?>" action="<?= e($action) ?>" class="form-grid mt-10">
            <?php foreach ($fields as $field): ?>
              <?php
                $fieldType = strtolower(trim((string)($field['type'] ?? 'text')));
                $fieldName = trim((string)($field['name'] ?? ''));
                if ($fieldName === '') {
                    continue;
                }
              ?>
              <div class="form-field<?= !empty($field['full']) ? '-full' : '' ?>">
                <?php if ($fieldType !== 'hidden'): ?>
                  <label class="field-label"><?= e((string)($field['label'] ?? ucfirst($fieldName))) ?></label>
                <?php endif; ?>
                <?php if ($fieldType === 'select'): ?>
                  <select name="<?= e($fieldName) ?>">
                    <?php foreach ((array)($field['options'] ?? []) as $option): ?>
                      <option value="<?= e((string)($option['value'] ?? '')) ?>" <?= !empty($option['selected']) ? 'selected' : '' ?>><?= e((string)($option['label'] ?? $option['value'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($fieldType === 'textarea'): ?>
                  <textarea name="<?= e($fieldName) ?>"><?= e((string)($field['value'] ?? '')) ?></textarea>
                <?php else: ?>
                  <input class="input" type="<?= e($fieldType !== '' ? $fieldType : 'text') ?>" name="<?= e($fieldName) ?>" value="<?= e((string)($field['value'] ?? '')) ?>">
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <div class="form-actions">
              <?php foreach (array_values(array_filter((array)($section['actions'] ?? []), 'is_array')) as $actionItem): ?>
                <button class="btn<?= !empty($actionItem['primary']) ? ' ok' : '' ?>" type="<?= e((string)($actionItem['type'] ?? 'submit')) ?>"><?= e((string)($actionItem['label'] ?? t('common.submit'))) ?></button>
              <?php endforeach; ?>
            </div>
          </form>
        <?php endif; ?>
      <?php else: ?>
        <div class="muted mt-10"><?= e($emptyMessage) ?></div>
      <?php endif; ?>
    </section>
    <?php
};

$moduleLabelFromKey = static function (string $moduleKey): string {
    $clean = trim(strtolower($moduleKey));
    if ($clean === '' || $clean === 'operations') {
        return (string)t('admin.workspace.fallback.operations');
    }
    $localeKey = 'admin.workspace.module.' . str_replace([' ', '-'], '_', $clean);
    $translated = (string)t($localeKey);
    if ($translated !== $localeKey) {
        return $translated;
    }
    $label = str_replace(['_', '-'], ' ', $clean);
    $label = preg_replace('/\s+/', ' ', (string)$label);
    return ucwords(trim((string)$label));
};

$dashboardItems = [];
$isPlaceholderQueueRow = static function (array $row): bool {
  $cells = is_array($row['cells'] ?? null) ? array_values((array)$row['cells']) : [];
  $textParts = array_map(static fn($cell): string => trim((string)$cell), $cells);
  $textParts[] = trim((string)($row['title'] ?? ''));
  $textParts[] = trim((string)($row['subtitle'] ?? ''));
  $textParts[] = trim((string)($row['meta'] ?? ''));
  $textParts[] = trim((string)($row['status'] ?? $row['state'] ?? ''));

  $joined = strtolower(trim(implode(' ', array_filter($textParts, static fn(string $part): bool => $part !== ''))));
  $joined = preg_replace('/\s+/', ' ', $joined);
  if (!is_string($joined)) {
    $joined = '';
  }

  if ($joined === '') {
    return true;
  }

  $workspaceQueuePattern = '/\bworkspace\b.*\bcurrent\s+queue\b|\bcurrent\s+queue\b.*\bworkspace\b/';
  return preg_match($workspaceQueuePattern, $joined) === 1;
};

$hasActionableQueueContent = static function (array $rows) use ($isPlaceholderQueueRow): bool {
  foreach ($rows as $row) {
    if (!is_array($row)) {
      continue;
    }

    $isPlaceholderRow = $isPlaceholderQueueRow($row);
    $rowUrl = trim((string)($row['url'] ?? ''));
    if ($rowUrl !== '' && !$isPlaceholderRow) {
      return true;
    }

    foreach (['progress', 'progress_pct', 'percent', 'planned_qty', 'good_qty', 'remaining_qty', 'entry_count', 'entries', 'stock_qty', 'target_today_qty'] as $metricKey) {
      if (!array_key_exists($metricKey, $row)) {
        continue;
      }

      $rawValue = trim((string)$row[$metricKey]);
      if ($rawValue === '') {
        continue;
      }

      if (is_numeric($rawValue)) {
        if ((float)$rawValue > 0.0) {
          return true;
        }
        continue;
      }

      if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $rawValue, $metricMatch) === 1) {
        if ((float)$metricMatch[1] > 0.0) {
          return true;
        }
        continue;
      }

      return true;
    }

    if (!$isPlaceholderRow) {
      return true;
    }
  }

  return false;
};

$isLowValueSurfaceCard = static function (array $item, string $kind, string $viewKind, string $widgetType): bool {
  $itemTitle = strtolower(trim((string)($item['title'] ?? $item['label'] ?? '')));
  $itemDescription = strtolower(trim((string)($item['description'] ?? '')));
  $isTableLike = in_array($kind, ['table', 'mixed'], true)
    || in_array($viewKind, ['table', 'mixed'], true)
    || $widgetType === 'reference';

  if (!$isTableLike) {
    return false;
  }

  return preg_match('/\bsurfaces?\b/', $itemTitle) === 1
    || preg_match('/\bmodule\s+surfaces?\b/', $itemDescription) === 1;
};

$viewTypeFromItem = static function (array $item, string $kind, string $viewKind, string $widgetType): string {
  $title = strtolower(trim((string)($item['title'] ?? $item['label'] ?? '')));
  $description = strtolower(trim((string)($item['description'] ?? '')));
  $isQueueLike = in_array($kind, ['queue', 'watchlist'], true)
    || in_array($viewKind, ['queue', 'watchlist'], true)
    || in_array($widgetType, ['queue', 'watchlist'], true);

  if (in_array('watchlist', [$kind, $viewKind, $widgetType], true) || preg_match('/\bwatchlist\b/', $title) === 1) {
    return 'watchlist';
  }

  if ($isQueueLike && preg_match('/\bmy\s*work\b/', $title) === 1) {
    return 'my_work';
  }

  if ($widgetType === 'reference'
    || preg_match('/\b(reference|control|decision)\b/', $description) === 1
    || preg_match('/\b(queues|views|work\s+queues|execution\s+views|action\s+queues|control\s+queues|decision\s+queues)\b/', $title) === 1) {
    return 'reference';
  }

  if ($isQueueLike) {
    return 'operational_queue';
  }

  return 'operational';
};

foreach ($streamItems as $item) {
    $kind = strtolower(trim((string)($item['kind'] ?? '')));
    $viewKind = strtolower(trim((string)($item['view_kind'] ?? '')));
    $hasStructured = $kind !== ''
      || isset($item['rows'])
      || isset($item['fields'])
      || isset($item['columns'])
      || in_array($viewKind, ['table', 'form', 'chart', 'queue', 'timeline', 'mixed'], true);

    if (!$hasStructured) {
        continue;
    }

    $requiresCompanionViews = (bool)($item['requires_companion_views'] ?? false);
    if ($requiresCompanionViews && ($kind === 'form' || ($kind === '' && $viewKind === 'form'))) {
        $moduleKey = $moduleKeyFromItem($item);
        if ($moduleKey === '' || !isset($moduleHasNonFormViews[$moduleKey])) {
            continue;
        }
    }

    $moduleKey = $moduleKeyFromItem($item);
    $item['_module_key'] = $moduleKey !== '' ? $moduleKey : 'operations';

    $itemTitle = trim((string)($item['title'] ?? $item['label'] ?? ''));
    $widgetType = strtolower(trim((string)($item['widget_type'] ?? '')));
    $item['_view_type'] = $viewTypeFromItem($item, $kind, $viewKind, $widgetType);

    if (in_array(strtolower($itemTitle), ['coverage · decision queues', 'daily orders · queues', 'production plans · work queues', 'production entries · execution views', 'qc entries · action queues', 'dispatch ops · control queues'], true)) {
      continue;
    }

    if ($isLowValueSurfaceCard($item, $kind, $viewKind, $widgetType)) {
      continue;
    }

    $isQueueLike = in_array($kind, ['queue', 'watchlist'], true)
      || in_array($viewKind, ['queue', 'watchlist'], true)
      || in_array($widgetType, ['queue', 'watchlist'], true);
    $isMyWorkQueueCard = $isQueueLike && preg_match('/\bmy\s*work\b/i', $itemTitle) === 1;
    if ($isMyWorkQueueCard) {
      $rows = is_array($item['rows'] ?? null) ? array_values((array)$item['rows']) : [];
      if (!$hasActionableQueueContent($rows)) {
        continue;
      }
    }

    $dashboardItems[] = $item;
}
