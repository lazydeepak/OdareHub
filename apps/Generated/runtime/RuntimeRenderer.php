<?php
declare(strict_types=1);

if (!function_exists('generated_runtime_render')) {
    /**
     * @param array<string,mixed> $payload
     */
    function generated_runtime_render(array $payload): void
    {
        $module = is_array($payload['generatedModule'] ?? null) ? $payload['generatedModule'] : [];
        $fields = array_values(array_filter((array)($payload['generatedFields'] ?? []), 'is_array'));
        $rows = array_values(array_filter((array)($payload['generatedRows'] ?? []), 'is_array'));
        $search = (string)($payload['generatedSearch'] ?? '');
        $editRow = is_array($payload['generatedEditRow'] ?? null) ? $payload['generatedEditRow'] : [];
        $flash = is_array($payload['generatedFlash'] ?? null) ? $payload['generatedFlash'] : [];
        $csrf = (string)($payload['csrf'] ?? '');
        $pageTitle = trim((string)($payload['pageTitle'] ?? 'Generated Runtime'));
        $routePath = trim((string)($module['route_path'] ?? '/'));

        $manifest = [];
        $manifestPath = trim((string)($module['manifest_path'] ?? ''));
        if ($manifestPath !== '') {
            if ($manifestPath[0] !== '/') {
                $root = rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/');
                $manifestPath = $root . '/' . ltrim($manifestPath, '/');
            }
            if (is_file($manifestPath)) {
                $raw = @file_get_contents($manifestPath);
                $decoded = $raw !== false ? json_decode($raw, true) : null;
                if (is_array($decoded)) {
                    $manifest = $decoded;
                }
            }
        }

        $runtimeView = [];
        if (is_array($manifest['runtime']['view_definition'] ?? null)) {
            $runtimeView = $manifest['runtime']['view_definition'];
        } elseif (is_array($manifest['view_definition'] ?? null)) {
            $runtimeView = $manifest['view_definition'];
        }

        $layout = is_array($runtimeView['layout'] ?? null) ? $runtimeView['layout'] : [];
        $layoutItems = array_values(array_filter((array)($layout['items'] ?? []), 'is_array'));
        $studioViews = is_array($runtimeView['studio_views'] ?? null) ? $runtimeView['studio_views'] : [];

        if ($layoutItems === []) {
            $displayName = trim((string)($module['display_name'] ?? $pageTitle));
            $layoutItems = [
                [
                    'id' => 'text_intro',
                    'component' => 'text',
                    'x' => 0,
                    'y' => 0,
                    'w' => 7,
                    'h' => 2,
                    'props' => [
                        'title' => $displayName,
                        'body' => 'Runtime surface generated from manifest layout.',
                    ],
                    'data_binding' => 'module.description',
                ],
                [
                    'id' => 'kpi_total',
                    'component' => 'kpi_card',
                    'x' => 7,
                    'y' => 0,
                    'w' => 5,
                    'h' => 2,
                    'props' => [
                        'label' => 'Total Records',
                        'value' => (string)count($rows),
                        'delta' => '',
                    ],
                    'data_binding' => 'module.metrics.total',
                ],
                [
                    'id' => 'table_main',
                    'component' => 'table',
                    'x' => 0,
                    'y' => 2,
                    'w' => 8,
                    'h' => 7,
                    'props' => [
                        'title' => $displayName,
                        'sample_rows' => 10,
                        'density' => 'comfortable',
                    ],
                    'data_binding' => 'module.rows',
                ],
                [
                    'id' => 'form_main',
                    'component' => 'form',
                    'x' => 8,
                    'y' => 2,
                    'w' => 4,
                    'h' => 7,
                    'props' => [
                        'title' => 'Create Record',
                        'submit_label' => 'Save',
                        'show_required' => true,
                    ],
                    'data_binding' => 'module.fields',
                ],
            ];
        }

        usort($layoutItems, static function (array $left, array $right): int {
            $leftY = (int)($left['y'] ?? 0);
            $rightY = (int)($right['y'] ?? 0);
            if ($leftY !== $rightY) {
                return $leftY <=> $rightY;
            }
            return (int)($left['x'] ?? 0) <=> (int)($right['x'] ?? 0);
        });

        $fieldMap = [];
        foreach ($fields as $field) {
            $key = trim((string)($field['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $fieldMap[$key] = $field;
        }

        $moduleContext = [
            'fields' => $fields,
            'rows' => $rows,
            'metrics' => [
                'total' => count($rows),
            ],
            'description' => (string)($module['display_name'] ?? $pageTitle),
        ];

        $resolveBinding = static function (string $path) use ($moduleContext) {
            $segments = array_values(array_filter(array_map('trim', explode('.', $path)), static fn(string $part): bool => $part !== ''));
            if ($segments === []) {
                return null;
            }
            $cursor = ['module' => $moduleContext];
            foreach ($segments as $segment) {
                if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                    return null;
                }
                $cursor = $cursor[$segment];
            }
            return $cursor;
        };

        $viewsById = [];
        foreach ((array)($studioViews['views'] ?? []) as $view) {
            if (!is_array($view)) {
                continue;
            }
            $id = trim((string)($view['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $viewsById[$id] = $view;
        }

        $activeViewId = trim((string)($studioViews['active_view_id'] ?? ''));
        if ($activeViewId === '' && $viewsById !== []) {
            foreach ($viewsById as $candidateId => $candidate) {
                $candidateRoute = trim((string)($candidate['route_path'] ?? ''));
                if ($candidateRoute !== '' && $candidateRoute === $routePath) {
                    $activeViewId = $candidateId;
                    break;
                }
            }
            if ($activeViewId === '') {
                $activeViewId = (string)array_key_first($viewsById);
            }
        }

        $activeLinks = [];
        if ($activeViewId !== '' && is_array($viewsById[$activeViewId]['links'] ?? null)) {
            $activeLinks = $viewsById[$activeViewId]['links'];
        }

        $viewTabs = [];
        foreach ($viewsById as $id => $view) {
            $viewRoute = trim((string)($view['route_path'] ?? ''));
            if ($viewRoute === '') {
                continue;
            }
            $viewTabs[] = [
                'id' => $id,
                'name' => trim((string)($view['name'] ?? $id)),
                'route' => $viewRoute,
            ];
        }

        ?>
<style>
.generated-runtime {
  display: grid;
  gap: 0.9rem;
}
.generated-runtime .runtime-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.generated-runtime .runtime-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}
.generated-runtime .runtime-tab {
  border: 1px solid var(--style-border-soft);
  background: var(--style-subtle-bg);
  border-radius: 999px;
  padding: 0.25rem 0.7rem;
  font-size: 0.85rem;
  text-decoration: none;
}
.generated-runtime .runtime-tab.active {
  background: var(--style-subtle-bg);
  border-color: var(--style-border-soft);
  color: var(--text);
}
.generated-runtime .runtime-grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  grid-auto-rows: minmax(56px, auto);
  gap: 0.75rem;
}
.generated-runtime .runtime-item {
  border: 1px solid var(--style-border-soft);
  border-radius: 8px;
  background: var(--style-subtle-bg);
  padding: 0.75rem;
  display: grid;
  gap: 0.5rem;
}
.generated-runtime .runtime-item h3,
.generated-runtime .runtime-item h4,
.generated-runtime .runtime-item p {
  margin: 0;
}
.generated-runtime .runtime-kpi-value {
  font-size: 1.8rem;
  font-weight: 700;
}
.generated-runtime .runtime-item table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.generated-runtime .runtime-item th,
.generated-runtime .runtime-item td {
  border-bottom: 1px solid var(--style-border-soft);
  padding: 0.35rem;
  text-align: left;
}
.generated-runtime .runtime-form {
  display: grid;
  gap: 0.5rem;
}
.generated-runtime .runtime-form label {
  display: grid;
  gap: 0.25rem;
  font-size: 0.88rem;
}
.generated-runtime .runtime-form input,
.generated-runtime .runtime-form select {
  border: 1px solid var(--style-border-soft);
  border-radius: 6px;
  padding: 0.42rem 0.5rem;
}
.generated-runtime .runtime-link {
  align-self: start;
}
.generated-runtime .runtime-item-empty {
  color: var(--text);
  font-size: 0.9rem;
}
@media (max-width: 900px) {
  .generated-runtime .runtime-grid {
    grid-template-columns: repeat(1, minmax(0, 1fr));
  }
  .generated-runtime .runtime-item {
    grid-column: span 1 !important;
    grid-row: auto !important;
  }
}
</style>
<section class="card generated-runtime" data-studio-app="<?= e((string)($module['app_key'] ?? '')) ?>" data-studio-module="<?= e((string)($module['module_key'] ?? '')) ?>" data-view-id="<?= e($activeViewId !== '' ? $activeViewId : 'index') ?>">
  <div class="runtime-header">
    <div class="ui-block">
      <h1><?= e($pageTitle !== '' ? $pageTitle : 'Generated Runtime') ?></h1>
      <p class="muted"><?= e((string)($module['app_key'] ?? 'generated_app')) ?> / <?= e((string)($module['module_key'] ?? 'generated_module')) ?></p>
    </div>
    <form method="get" action="<?= e($routePath) ?>">
      <input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="Search">
      <button class="btn" type="submit">Search</button>
    </form>
  </div>

  <?php if ($flash !== []): ?>
    <div class="notice <?= e((string)($flash['status'] ?? '')) ?>"><?= e((string)($flash['message'] ?? '')) ?></div>
  <?php endif; ?>

  <?php if ($viewTabs !== []): ?>
    <div class="runtime-tabs">
      <?php foreach ($viewTabs as $tab): ?>
        <a class="runtime-tab<?= $tab['id'] === $activeViewId ? ' active' : '' ?>" href="<?= e((string)$tab['route']) ?>"><?= e((string)$tab['name']) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="runtime-grid">
    <?php foreach ($layoutItems as $item): ?>
      <?php
      $itemId = trim((string)($item['id'] ?? ''));
      if ($itemId === '') {
          continue;
      }
      $component = strtolower(trim((string)($item['component'] ?? 'text')));
      $x = max(0, (int)($item['x'] ?? 0));
      $y = max(0, (int)($item['y'] ?? 0));
      $w = max(1, min(12, (int)($item['w'] ?? 12)));
      $h = max(1, (int)($item['h'] ?? 1));
      $props = is_array($item['props'] ?? null) ? $item['props'] : [];
      $binding = trim((string)($item['data_binding'] ?? ''));
      $boundValue = $binding !== '' ? $resolveBinding($binding) : null;
      $targetViewId = trim((string)($activeLinks[$itemId] ?? ''));
      $targetRoute = '';
      $targetName = '';
      if ($targetViewId !== '' && is_array($viewsById[$targetViewId] ?? null)) {
          $target = $viewsById[$targetViewId];
          $targetRoute = trim((string)($target['route_path'] ?? ''));
          $targetName = trim((string)($target['name'] ?? $targetViewId));
      }
      ?>
      <article class="runtime-item" style="grid-column: <?= $x + 1 ?> / span <?= $w ?>; grid-row: <?= $y + 1 ?> / span <?= $h ?>;">
        <?php if ($component === 'kpi_card'): ?>
          <h4><?= e((string)($props['label'] ?? 'KPI')) ?></h4>
          <div class="runtime-kpi-value"><?= e((string)($boundValue ?? $props['value'] ?? count($rows))) ?></div>
          <div class="muted"><?= e((string)($props['delta'] ?? '')) ?></div>
        <?php elseif ($component === 'table'): ?>
          <h4><?= e((string)($props['title'] ?? 'Table')) ?></h4>
          <?php if ($fields !== [] && $rows !== []): ?>
            <table>
              <thead>
                <tr>
                  <?php foreach ($fields as $field): ?>
                    <th><?= e((string)($field['label'] ?? $field['key'] ?? '')) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php $maxRows = max(1, (int)($props['sample_rows'] ?? 10)); ?>
                <?php foreach (array_slice($rows, 0, $maxRows) as $row): ?>
                  <tr>
                    <?php foreach ($fields as $field): ?>
                      <?php $key = (string)($field['key'] ?? ''); ?>
                      <td><?= e((string)($row[$key] ?? '')) ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="runtime-item-empty">No data.</div>
          <?php endif; ?>
        <?php elseif ($component === 'form'): ?>
          <h4><?= e((string)($props['title'] ?? 'Form')) ?></h4>
          <form method="post" action="<?= e($routePath) ?>" class="runtime-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <?php foreach ($fields as $field): ?>
              <?php
              $key = (string)($field['key'] ?? '');
              if ($key === '') {
                  continue;
              }
              $type = (string)($field['type'] ?? 'string');
              $current = (string)($editRow[$key] ?? $field['default'] ?? '');
              ?>
              <label>
                <?= e((string)($field['label'] ?? $key)) ?>
                <?php if ($type === 'select'): ?>
                  <select name="<?= e($key) ?>">
                    <?php foreach ((array)($field['options'] ?? []) as $option): ?>
                      <?php $optionValue = (string)$option; ?>
                      <option value="<?= e($optionValue) ?>"<?= $current === $optionValue ? ' selected' : '' ?>><?= e($optionValue) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <input class="input" type="<?= $type === 'integer' ? 'number' : 'text' ?>" name="<?= e($key) ?>" value="<?= e($current) ?>">
                <?php endif; ?>
              </label>
            <?php endforeach; ?>
            <button class="btn" type="submit"><?= e((string)($props['submit_label'] ?? 'Save')) ?></button>
          </form>
        <?php else: ?>
          <h4><?= e((string)($props['title'] ?? $props['label'] ?? 'Text')) ?></h4>
          <p><?= e((string)($boundValue ?? $props['body'] ?? '')) ?></p>
        <?php endif; ?>

        <?php if ($targetRoute !== '' && $targetRoute !== $routePath): ?>
          <a class="runtime-link button secondary" href="<?= e($targetRoute) ?>">Open <?= e($targetName !== '' ? $targetName : $targetViewId) ?></a>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php
    }
}
