<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$company = is_array($company ?? null) ? $company : null;
$fiscal = is_array($fiscal ?? null) ? $fiscal : [];
$taxModeOptions = is_array($taxModeOptions ?? null) ? $taxModeOptions : [];
$pageHeading = (string)t('organization.nav.fiscal');
$pageDescription = (string)t('organization.description.fiscal');
require __DIR__ . '/partials/nav.php';
require __DIR__ . '/partials/feedback.php';
?>

<?php if (!$company): ?>
  <div class="card notice-err"><?= e(t('organization.notice.create_company_before_fiscal')) ?></div>
<?php else: ?>
  <form method="post" action="/ops/organization/fiscal" class="card">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="company_id" value="<?= e((string)($company['id'] ?? 0)) ?>">

      <div class="module-header">
        <div class="module-header-info">
          <h3 class="u-style-1169661891"><?= e((string)($company['company_name'] ?? '')) ?></h3>
        <div class="muted"><?= e(t('organization.fiscal_defaults_company')) ?></div>
        </div>
      </div>

    <div class="form-grid u-style-56f4356299">
      <label>
        <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.fiscal_year_start')) ?></div>
        <input class="input" type="text" name="fiscal_year_start" value="<?= e((string)($fiscal['fiscal_year_start'] ?? '04-01')) ?>" placeholder="<?= e(t('organization.placeholder.fiscal_start')) ?>" <?= $canManage ? '' : 'disabled' ?>>
      </label>
      <label>
        <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.fiscal_year_end')) ?></div>
        <input class="input" type="text" name="fiscal_year_end" value="<?= e((string)($fiscal['fiscal_year_end'] ?? '03-31')) ?>" placeholder="<?= e(t('organization.placeholder.fiscal_end')) ?>" <?= $canManage ? '' : 'disabled' ?>>
      </label>
      <label>
        <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.default_tax_mode')) ?></div>
        <select name="default_tax_mode" <?= $canManage ? '' : 'disabled' ?>>
          <?php foreach ($taxModeOptions as $value => $label): ?>
            <option value="<?= e((string)$value) ?>" <?= (string)($fiscal['default_tax_mode'] ?? 'exclusive') === (string)$value ? 'selected' : '' ?>><?= e((string)$label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.invoice_prefix')) ?></div>
        <input class="input" type="text" name="invoice_prefix" value="<?= e((string)($fiscal['invoice_prefix'] ?? '')) ?>" placeholder="<?= e(t('organization.placeholder.invoice_prefix')) ?>" <?= $canManage ? '' : 'disabled' ?>>
      </label>
      <label>
        <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.document_prefix_pattern')) ?></div>
        <input class="input" type="text" name="document_prefix_pattern" value="<?= e((string)($fiscal['document_prefix_pattern'] ?? '')) ?>" placeholder="<?= e(t('organization.placeholder.document_prefix_pattern')) ?>" <?= $canManage ? '' : 'disabled' ?>>
      </label>
      <label class="u-style-4ddcfbe561">
        <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.notes')) ?></div>
        <textarea name="notes" rows="4" <?= $canManage ? '' : 'disabled' ?>><?= e((string)($fiscal['notes'] ?? '')) ?></textarea>
      </label>
    </div>

    <div class="muted u-style-56f4356299"><?= e(t('organization.mmdd_hint')) ?></div>

    <?php if ($canManage): ?>
      <div class="u-style-a54f31b4df">
        <button class="btn ok" type="submit"><?= e(t('organization.action.save_fiscal')) ?></button>
      </div>
    <?php endif; ?>
  </form>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
