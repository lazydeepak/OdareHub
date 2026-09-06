<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$company = is_array($company ?? null) ? $company : [];
$currencyOptions = is_array($currencyOptions ?? null) ? $currencyOptions : [];
$timezoneOptions = is_array($timezoneOptions ?? null) ? $timezoneOptions : [];
$pageHeading = (string)t('organization.nav.company');
$pageDescription = (string)t('organization.description.company');
require __DIR__ . '/partials/nav.php';
require __DIR__ . '/partials/feedback.php';
?>

<?php if (!$canManage && (int)($company['id'] ?? 0) <= 0): ?>
  <div class="card notice-err"><?= e(t('organization.notice.read_only_no_company')) ?></div>
<?php endif; ?>

<form method="post" action="/ops/organization/company" class="card">
  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
  <input type="hidden" name="id" value="<?= e((string)($company['id'] ?? 0)) ?>">

  <div class="form-grid">
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.company_name')) ?></div>
      <input class="input" type="text" name="company_name" value="<?= e((string)($company['company_name'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?> required>
    </label>
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.company_code')) ?></div>
      <input class="input" type="text" name="company_code" value="<?= e((string)($company['company_code'] ?? '')) ?>" maxlength="40" <?= $canManage ? '' : 'disabled' ?> required>
    </label>
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.legal_name')) ?></div>
      <input class="input" type="text" name="legal_name" value="<?= e((string)($company['legal_name'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>

    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.registration_number')) ?></div>
      <input class="input" type="text" name="registration_no" value="<?= e((string)($company['registration_no'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.tax_vat_number')) ?></div>
      <input class="input" type="text" name="tax_no" value="<?= e((string)($company['tax_no'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.active_status')) ?></div>
      <select name="is_active" <?= $canManage ? '' : 'disabled' ?>>
        <option value="1" <?= (int)($company['is_active'] ?? 1) === 1 ? 'selected' : '' ?>><?= e(t('common.active')) ?></option>
        <option value="0" <?= (int)($company['is_active'] ?? 1) === 0 ? 'selected' : '' ?>><?= e(t('common.inactive')) ?></option>
      </select>
    </label>

    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.base_currency')) ?></div>
      <select name="base_currency" <?= $canManage ? '' : 'disabled' ?>>
        <?php foreach ($currencyOptions as $value => $label): ?>
          <option value="<?= e((string)$value) ?>" <?= (string)($company['base_currency'] ?? 'JPY') === (string)$value ? 'selected' : '' ?>><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.timezone')) ?></div>
      <select name="timezone" <?= $canManage ? '' : 'disabled' ?>>
        <?php foreach ($timezoneOptions as $value => $label): ?>
          <option value="<?= e((string)$value) ?>" <?= (string)($company['timezone'] ?? 'Asia/Tokyo') === (string)$value ? 'selected' : '' ?>><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.logo_brand_reference')) ?></div>
      <input class="input" type="text" name="logo_path" value="<?= e((string)($company['logo_path'] ?? '')) ?>" placeholder="<?= e(t('organization.placeholder.logo_path')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>

    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.email')) ?></div>
      <input class="input" type="email" name="email" value="<?= e((string)($company['email'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.phone')) ?></div>
      <input class="input" type="text" name="phone" value="<?= e((string)($company['phone'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.website')) ?></div>
      <input class="input" type="text" name="website" value="<?= e((string)($company['website'] ?? '')) ?>" placeholder="<?= e(t('organization.placeholder.website')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>

    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.address_line_1')) ?></div>
      <input class="input" type="text" name="address_line_1" value="<?= e((string)($company['address_line_1'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.address_line_2')) ?></div>
      <input class="input" type="text" name="address_line_2" value="<?= e((string)($company['address_line_2'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.city')) ?></div>
      <input class="input" type="text" name="city" value="<?= e((string)($company['city'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>

    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.state_prefecture')) ?></div>
      <input class="input" type="text" name="state" value="<?= e((string)($company['state'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.postal_code')) ?></div>
      <input class="input" type="text" name="postal_code" value="<?= e((string)($company['postal_code'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>
    <label>
      <div class="muted u-style-4e420aff3f"><?= e(t('organization.field.country')) ?></div>
      <input class="input" type="text" name="country" value="<?= e((string)($company['country'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
    </label>
  </div>

  <?php if ($canManage): ?>
    <div class="u-style-a54f31b4df">
      <button class="btn ok" type="submit"><?= e(t('organization.action.save_company')) ?></button>
      <a class="btn" href="/ops/organization"><?= e(t('organization.action.back_to_overview')) ?></a>
    </div>
  <?php endif; ?>
</form>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
