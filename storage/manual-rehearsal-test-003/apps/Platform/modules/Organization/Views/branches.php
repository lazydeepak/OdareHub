<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$companies = is_array($companies ?? null) ? $companies : [];
$rows = is_array($rows ?? null) ? $rows : [];
$editing = is_array($editing ?? null) ? $editing : [];
$selectedCompanyId = (int)($selectedCompanyId ?? 0);
$search = (string)($search ?? '');
$pageHeading = (string)t('organization.nav.branches');
$pageDescription = (string)t('organization.description.branches');
require __DIR__ . '/partials/nav.php';
require __DIR__ . '/partials/feedback.php';
?>

<?php if ($companies === []): ?>
  <div class="card notice-err"><?= e(t('organization.notice.create_company_before_branches')) ?></div>
<?php else: ?>
  <section class="card">
    <form class="u-style-97ded659e4" method="get" action="/ops/organization/branches">
      <label class="u-style-26db0b2108">
        <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.company')) ?></div>
        <select name="company_id">
          <?php foreach ($companies as $companyRow): ?>
            <option value="<?= e((string)($companyRow['id'] ?? 0)) ?>" <?= (int)($companyRow['id'] ?? 0) === $selectedCompanyId ? 'selected' : '' ?>><?= e((string)($companyRow['company_name'] ?? '')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="u-style-26db0b2108">
        <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.search')) ?></div>
        <input class="input" type="text" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('organization.placeholder.branch_search')) ?>">
      </label>
      <button class="btn" type="submit"><?= e(t('organization.action.filter')) ?></button>
      <?php if ($search !== ''): ?>
        <a class="btn" href="/ops/organization/branches?company_id=<?= e((string)$selectedCompanyId) ?>"><?= e(t('organization.action.clear')) ?></a>
      <?php endif; ?>
    </form>
  </section>

  <section class="card">
      <div class="module-header">
        <div class="module-header-info">
        <h3 class="u-style-1169661891"><?= e(t('organization.branch_records')) ?></h3>
        <div class="muted"><?= e(t('organization.branch_records_count', ['count' => count($rows)])) ?></div>
        </div>
      </div>
    <?php if ($rows === []): ?>
      <div class="muted u-style-d2c171b18b"><?= e(t('organization.no_branches_yet')) ?></div>
    <?php else: ?>
      <div class="table-wrap u-style-56f4356299">
        <table>
          <thead>
            <tr>
              <th><?= e(t('organization.field.branch_name')) ?></th>
              <th><?= e(t('organization.field.branch_code')) ?></th>
              <th><?= e(t('organization.field.contact')) ?></th>
              <th><?= e(t('organization.field.location')) ?></th>
              <th><?= e(t('organization.field.status')) ?></th>
              <?php if ($canManage): ?><th><?= e(t('common.actions')) ?></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= e((string)($row['branch_name'] ?? '')) ?></td>
                <td><?= e((string)($row['branch_code'] ?? '')) ?></td>
                <td><?= e(trim((string)($row['email'] ?? '')) !== '' ? (string)$row['email'] : (string)($row['phone'] ?? '')) ?></td>
                <td><?= e(implode(', ', array_values(array_filter([
                  (string)($row['city'] ?? ''),
                  (string)($row['state'] ?? ''),
                  (string)($row['country'] ?? ''),
                ], static fn(string $value): bool => trim($value) !== '')))) ?></td>
                <td><?= e((int)($row['is_active'] ?? 0) === 1 ? t('common.active') : t('common.inactive')) ?></td>
                <?php if ($canManage): ?>
                  <td class="u-style-fbb8489507">
                    <a class="btn" href="/ops/organization/branches?company_id=<?= e((string)$selectedCompanyId) ?>&edit=<?= e((string)($row['id'] ?? 0)) ?>"><?= e(t('common.edit')) ?></a>
                    <form method="post" action="/ops/organization/branches/delete" style="display:inline-block" onsubmit="return confirm('<?= e(t('organization.delete_branch_confirm')) ?>');">
                      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                      <input type="hidden" name="company_id" value="<?= e((string)$selectedCompanyId) ?>">
                      <input type="hidden" name="id" value="<?= e((string)($row['id'] ?? 0)) ?>">
                      <button class="btn" type="submit"><?= e(t('common.delete')) ?></button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($canManage): ?>
    <form method="post" action="/ops/organization/branches" class="card">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= e((string)($editing['id'] ?? 0)) ?>">

      <div class="module-header">
        <div class="module-header-info">
          <h3 class="u-style-1169661891"><?= e((int)($editing['id'] ?? 0) > 0 ? t('organization.edit_branch') : t('organization.add_branch')) ?></h3>
          <div class="muted"><?= e(t('organization.branch_form_desc')) ?></div>
        </div>
      </div>

      <div class="form-grid u-style-56f4356299">
        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.company')) ?></div>
          <select name="company_id">
            <?php foreach ($companies as $companyRow): ?>
              <?php $optionId = (int)($companyRow['id'] ?? 0); ?>
              <option value="<?= e((string)$optionId) ?>" <?= $optionId === (int)($editing['company_id'] ?? $selectedCompanyId) ? 'selected' : '' ?>><?= e((string)($companyRow['company_name'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.branch_name')) ?></div>
          <input class="input" type="text" name="branch_name" value="<?= e((string)($editing['branch_name'] ?? '')) ?>" required>
        </label>
        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.branch_code')) ?></div>
          <input class="input" type="text" name="branch_code" value="<?= e((string)($editing['branch_code'] ?? '')) ?>" required>
        </label>

        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.email')) ?></div>
          <input class="input" type="email" name="email" value="<?= e((string)($editing['email'] ?? '')) ?>">
        </label>
        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.phone')) ?></div>
          <input class="input" type="text" name="phone" value="<?= e((string)($editing['phone'] ?? '')) ?>">
        </label>
        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.active_status')) ?></div>
          <select name="is_active">
            <option value="1" <?= (int)($editing['is_active'] ?? 1) === 1 ? 'selected' : '' ?>><?= e(t('common.active')) ?></option>
            <option value="0" <?= (int)($editing['is_active'] ?? 1) === 0 ? 'selected' : '' ?>><?= e(t('common.inactive')) ?></option>
          </select>
        </label>

        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.address_line_1')) ?></div>
          <input class="input" type="text" name="address_line_1" value="<?= e((string)($editing['address_line_1'] ?? '')) ?>">
        </label>
        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.address_line_2')) ?></div>
          <input class="input" type="text" name="address_line_2" value="<?= e((string)($editing['address_line_2'] ?? '')) ?>">
        </label>
        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.city')) ?></div>
          <input class="input" type="text" name="city" value="<?= e((string)($editing['city'] ?? '')) ?>">
        </label>

        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.state_prefecture')) ?></div>
          <input class="input" type="text" name="state" value="<?= e((string)($editing['state'] ?? '')) ?>">
        </label>
        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.postal_code')) ?></div>
          <input class="input" type="text" name="postal_code" value="<?= e((string)($editing['postal_code'] ?? '')) ?>">
        </label>
        <label>
          <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.country')) ?></div>
          <input class="input" type="text" name="country" value="<?= e((string)($editing['country'] ?? '')) ?>">
        </label>
      </div>

      <div class="u-style-a54f31b4df">
        <button class="btn ok" type="submit"><?= e((int)($editing['id'] ?? 0) > 0 ? t('organization.action.update_branch') : t('organization.action.create_branch')) ?></button>
        <?php if ((int)($editing['id'] ?? 0) > 0): ?>
          <a class="btn" href="/ops/organization/branches?company_id=<?= e((string)$selectedCompanyId) ?>"><?= e(t('organization.action.cancel_edit')) ?></a>
        <?php endif; ?>
      </div>
    </form>
  <?php endif; ?>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
