<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$companies         = is_array($companies ?? null) ? $companies : [];
$selectedCompanyId = (int)($selectedCompanyId ?? 0);
$filters           = is_array($filters ?? null) ? $filters : [];
$log               = is_array($log ?? null) ? $log : [];
$rows              = is_array($log['rows'] ?? null) ? $log['rows'] : [];
$total             = (int)($log['total'] ?? 0);
$pages             = (int)($log['pages'] ?? 1);
$currentPage       = max(1, (int)($filters['page'] ?? 1));
$perPage           = (int)($filters['per_page'] ?? 50);

require __DIR__ . '/partials/nav.php';
require __DIR__ . '/partials/feedback.php';

$actionClasses = [
    'create' => 'badge-success',
    'update' => 'badge-neutral',
    'delete' => 'badge-danger',
];
?>

<?php if ($companies === []): ?>
  <div class="card notice-err"><?= e(t('organization.notice.create_company_before_branches')) ?></div>
<?php else: ?>

  <!-- Filters -->
  <section class="card">
    <form class="u-style-97ded659e4" method="get" action="/ops/organization/audit">
      <input type="hidden" name="company_id" value="<?= e((string)$selectedCompanyId) ?>">

      <label class="u-style-76722d313a">
        <div class="muted u-style-c81ce4b250"><?= e(t('organization.field.company')) ?></div>
        <select name="company_id" onchange="this.form.submit()">
          <?php foreach ($companies as $companyRow): ?>
            <option value="<?= e((string)($companyRow['id'] ?? 0)) ?>"
              <?= (int)($companyRow['id'] ?? 0) === $selectedCompanyId ? 'selected' : '' ?>>
              <?= e((string)($companyRow['company_name'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="u-style-94253f99ba">
        <div class="muted u-style-c81ce4b250"><?= e(t('organization.audit.filter.entity_type')) ?></div>
        <select name="entity_type">
          <option value=""><?= e(t('organization.audit.all_entities')) ?></option>
          <?php foreach (['company', 'branch', 'fiscal', 'branding', 'hierarchy'] as $et): ?>
            <option value="<?= e($et) ?>" <?= ($filters['entity_type'] ?? '') === $et ? 'selected' : '' ?>>
              <?= e($et) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="u-style-4592c743a5">
        <div class="muted u-style-c81ce4b250"><?= e(t('organization.audit.filter.action')) ?></div>
        <select name="action">
          <option value=""><?= e(t('organization.audit.all_actions')) ?></option>
          <?php foreach (['create', 'update', 'delete'] as $act): ?>
            <option value="<?= e($act) ?>" <?= ($filters['action'] ?? '') === $act ? 'selected' : '' ?>>
              <?= e(t('organization.audit.action.' . $act)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        <div class="muted u-style-c81ce4b250"><?= e(t('organization.audit.filter.date_from')) ?></div>
        <input class="input" type="date" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>">
      </label>

      <label>
        <div class="muted u-style-c81ce4b250"><?= e(t('organization.audit.filter.date_to')) ?></div>
        <input class="input" type="date" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>">
      </label>

      <div class="u-style-f3738ba582">
        <button class="btn" type="submit"><?= e(t('organization.action.filter')) ?></button>
        <a class="btn" href="/ops/organization/audit?company_id=<?= e((string)$selectedCompanyId) ?>"><?= e(t('organization.action.clear')) ?></a>
      </div>
    </form>
  </section>

  <!-- Audit table -->
  <section class="card">
    <div class="module-header">
      <div class="module-header-info">
        <h3 class="u-style-1169661891"><?= e(t('organization.audit.title')) ?></h3>
        <div class="muted"><?= e(t('organization.audit.description')) ?></div>
      </div>
    </div>

    <?php if ($rows === []): ?>
      <div class="muted u-style-56f4356299"><?= e(t('organization.audit.empty')) ?></div>
    <?php else: ?>
      <div class="table-wrap u-style-56f4356299">
        <table>
          <thead>
            <tr>
              <th><?= e(t('organization.audit.timestamp')) ?></th>
              <th><?= e(t('organization.audit.entity_type')) ?></th>
              <th><?= e(t('organization.audit.entity_id')) ?></th>
              <th><?= e(t('organization.audit.action')) ?></th>
              <th><?= e(t('organization.audit.user')) ?></th>
              <th><?= e(t('organization.audit.changed_fields')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php
              $action       = (string)($row['action'] ?? '');
              $badgeClass   = $actionClasses[$action] ?? 'badge-neutral';
              $changedRaw   = (string)($row['changed_fields'] ?? '');
              $changedArr   = [];
              if ($changedRaw !== '' && $changedRaw !== 'null') {
                  $decoded = json_decode($changedRaw, true);
                  if (is_array($decoded)) {
                      $changedArr = $decoded;
                  }
              }
              ?>
              <tr>
                <td class="u-style-095365f975"><?= e((string)($row['created_at'] ?? '')) ?></td>
                <td><?= e((string)($row['entity_type'] ?? '')) ?></td>
                <td><?= e((string)($row['entity_id'] ?? '')) ?></td>
                <td><span class="badge <?= e($badgeClass) ?>"><?= e(t('organization.audit.action.' . $action)) ?></span></td>
                <td class="u-style-0af3174751"><?= e((string)($row['user_email'] ?? '')) ?></td>
                <td>
                  <?php if ($changedArr !== []): ?>
                    <details>
                      <summary class="u-style-f3705f964f"><?= e(t('organization.audit.changed_fields')) ?> (<?= count($changedArr) ?>)</summary>
                      <ul class="u-style-7c6851ab87">
                        <?php foreach ($changedArr as $field => $diff): ?>
                          <li>
                            <strong><?= e((string)$field) ?></strong>:
                            <span class="u-style-57f9e31d78"><?= e((string)($diff['before'] ?? '')) ?></span>
                            &rarr;
                            <span><?= e((string)($diff['after'] ?? '')) ?></span>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    </details>
                  <?php else: ?>
                    <span class="muted">&mdash;</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
        <div class="u-style-f4a03fcc00">
          <?php
          $baseQuery = http_build_query(array_merge($filters, ['company_id' => $selectedCompanyId, 'page' => 0]));
          for ($p = 1; $p <= $pages; $p++):
            $q = http_build_query(array_merge($filters, ['company_id' => $selectedCompanyId, 'page' => $p]));
          ?>
            <a class="btn <?= $p === $currentPage ? 'btn-primary' : '' ?>" href="/ops/organization/audit?<?= $q ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </section>

<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
