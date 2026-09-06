<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php $mfg_demands = is_array($mfg_demands ?? null) ? $mfg_demands : []; ?>
<?php $suppliers = is_array($suppliers ?? null) ? $suppliers : []; ?>

<?php
$chipClass = static function (string $state): string {
  $s = strtolower(trim($state));
  return match ($s) {
    'approved', 'issued', 'posted', 'closed' => 'chip chip--ok',
    'partial' => 'chip chip--warn',
    'cancelled', 'rejected', 'blocked', 'inactive' => 'chip chip--danger',
    default => 'chip chip--neutral',
  };
};
?>

<?php if (!empty($message)): ?>
  <section class="card card--success"><?= e((string)$message) ?></section>
<?php endif; ?>
<?php if (!empty($error)): ?>
  <section class="card card--error"><?= e((string)$error) ?></section>
<?php endif; ?>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h2 class="u-style-1169661891"><?= e(t('proc.requests.title')) ?></h2>
      <div class="muted u-style-fe7b4979fe"><?= e(t('proc.requests.subtitle')) ?></div>
    </div>
    <div class="row u-style-4725bb7117">
      <a class="btn" href="/apps/procurement"><?= e(t('proc.requests.link_dashboard')) ?></a>
      <a class="btn" href="/apps/procurement/orders"><?= e(t('proc.requests.link_orders')) ?></a>
    </div>
  </div>
</section>

<section class="card">
  <h3 class="u-style-d462248a40"><?= e(t('proc.requests.create_title')) ?></h3>
  <form method="post" action="/apps/procurement/requests/create" class="grid u-style-1a42931a01">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <label><?= e(t('proc.requests.form_ref')) ?><input class="input" type="text" name="request_ref"></label>
    <label><?= e(t('proc.requests.form_product_id')) ?><input class="input" type="number" name="product_id" min="1" required></label>
    <label><?= e(t('proc.requests.form_qty')) ?><input class="input" type="number" name="requested_qty" min="0.01" step="0.01" required></label>
    <input type="hidden" name="source_app" value="manufacturing">
    <input type="hidden" name="source_ref_type" value="demand">
    <input type="hidden" name="request_status" value="draft">
    <div class="u-style-068a8ddc17">
      <button class="btn ok" type="submit"><?= e(t('proc.requests.btn_create')) ?></button>
    </div>
  </form>
</section>

<section class="card">
  <h3 class="u-style-d462248a40"><?= e(t('proc.requests.mfg_intake_title')) ?></h3>
  <div class="muted u-style-761d3addb2"><?= e(t('proc.requests.mfg_intake_description')) ?></div>
  <?php if (empty($mfg_demands)): ?>
    <p class="muted u-style-1169661891"><?= e(t('proc.requests.mfg_no_demands')) ?></p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e(t('proc.requests.mfg_col_demand')) ?></th><th><?= e(t('proc.requests.mfg_col_product')) ?></th><th class="u-style-54c2afb7ba"><?= e(t('proc.requests.mfg_col_required_qty')) ?></th><th><?= e(t('proc.requests.mfg_col_link')) ?></th><th><?= e(t('proc.requests.mfg_col_action')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($mfg_demands as $d): ?>
            <?php $linkedId = (int)($d['linked_request_id'] ?? 0); ?>
            <tr>
              <td>#<?= (int)($d['id'] ?? 0) ?></td>
              <td><?= (int)($d['product_id'] ?? 0) ?></td>
              <td class="u-style-54c2afb7ba"><?= number_format((float)($d['required_qty'] ?? 0), 2) ?></td>
              <td>
                <?php if ($linkedId > 0): ?>
                  <span class="muted"><?= e(t('proc.requests.mfg_linked')) ?> <?= e((string)($d['linked_request_ref'] ?? ('#' . $linkedId))) ?></span>
                <?php else: ?>
                  <span class="muted"><?= e(t('proc.requests.mfg_not_linked')) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($linkedId <= 0): ?>
                  <form class="u-style-1169661891" method="post" action="/apps/procurement/requests/intake/manufacturing">
                    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                    <input type="hidden" name="demand_id" value="<?= (int)($d['id'] ?? 0) ?>">
                    <button class="btn" type="submit"><?= e(t('proc.requests.mfg_btn_import')) ?></button>
                  </form>
                <?php else: ?>
                  <span class="muted"><?= e(t('proc.requests.mfg_btn_done')) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card">
  <div class="hero-meta u-style-da12f2858b">
    <div class="hero-meta-card"><div class="muted"><?= e(t('proc.requests.total')) ?></div><div class="hero-meta-value"><?= (int)(($summary ?? [])['requests'] ?? 0) ?></div></div>
  </div>

  <?php if (empty($rows)): ?>
    <p class="muted u-style-1169661891"><?= e(t('proc.requests.no_rows')) ?></p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th><?= e(t('proc.requests.col_ref')) ?></th><th><?= e(t('proc.requests.col_product')) ?></th><th class="u-style-54c2afb7ba"><?= e(t('proc.requests.col_qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
        <tbody>
          <?php foreach ((array)$rows as $r): ?>
            <?php $reqStatus = strtolower(trim((string)($r['request_status'] ?? 'draft'))); ?>
            <tr>
              <td><?= e((string)($r['request_ref'] ?? '')) ?></td>
              <td><?= (int)($r['product_id'] ?? 0) ?></td>
              <td class="u-style-54c2afb7ba"><?= number_format((float)($r['requested_qty'] ?? 0), 2) ?></td>
              <td><span class="<?= e($chipClass((string)($r['request_status'] ?? ''))) ?>"><?= e((string)($r['request_status'] ?? '')) ?></span></td>
              <td>
                <?php if ($reqStatus === 'draft'): ?>
                  <form class="u-style-146fbac074" method="post" action="/apps/procurement/requests/approve">
                    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= (int)($r['id'] ?? 0) ?>">
                    <button class="btn" type="submit"><?= e(t('proc.requests.btn_approve')) ?></button>
                  </form>
                <?php elseif ($reqStatus === 'approved'): ?>
                  <form class="u-style-6ba73bb0eb" method="post" action="/apps/procurement/orders/create-from-request">
                    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                    <input type="hidden" name="request_id" value="<?= (int)($r['id'] ?? 0) ?>">
                    <input type="hidden" name="redirect_to" value="/apps/procurement/requests">
                    <select name="supplier_id" class="js-proc-supplier-pref u-style-7d03ac6a49" data-pref-key="procurement.preferred_supplier_id">
                      <option value=""><?= e(t('proc.requests.auto_supplier')) ?></option>
                      <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int)($s['id'] ?? 0) ?>"><?= e((string)($s['supplier_name'] ?? '')) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn" type="submit"><?= e(t('proc.requests.btn_create_po')) ?></button>
                  </form>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<script>
  (function () {
    const selects = Array.from(document.querySelectorAll('.js-proc-supplier-pref'));
    if (selects.length === 0) {
      return;
    }

    const key = selects[0].getAttribute('data-pref-key') || 'procurement.preferred_supplier_id';
    let stored = '';
    try {
      stored = window.localStorage.getItem(key) || '';
    } catch (_) {
      stored = '';
    }

    if (stored !== '') {
      selects.forEach((select) => {
        const option = Array.from(select.options).find((opt) => opt.value === stored);
        if (option) {
          select.value = stored;
        }
      });
    }

    selects.forEach((select) => {
      select.addEventListener('change', () => {
        try {
          window.localStorage.setItem(key, select.value || '');
        } catch (_) {
          // Ignore storage failures in private mode.
        }
      });
    });
  })();
</script>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
