<?php
$rows = is_array($rows ?? null) ? $rows : [];
$flashKey = trim((string)($flash ?? ''));
$errorKey = trim((string)($error ?? ''));
$csrf = (string)($csrf ?? '');

$flashText = $flashKey !== '' ? t($flashKey) : '';
$errorText = $errorKey !== '' ? t($errorKey) : '';

$localizeKey = static function (string $key, string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '-';
    }
    $translated = t($key);
    // t() returns the key itself when no translation exists; in that case
    // humanize the raw value rather than show the raw_snake_case enum.
    if ($translated === $key) {
        return ucwords(str_replace('_', ' ', $raw));
    }
    return $translated;
};
?>

<section class="card">
  <h2 class="u-style-4ef9babeac"><?= e(t('ops.display_manager.title')) ?></h2>
  <div class="muted"><?= e(t('ops.display_manager.subtitle')) ?></div>
  <?php if ($flashText !== ''): ?>
    <div class="note success u-style-d8a81eac84"><?= e($flashText) ?></div>
  <?php endif; ?>
  <?php if ($errorText !== ''): ?>
    <div class="note warning u-style-d8a81eac84"><?= e($errorText) ?></div>
  <?php endif; ?>
</section>

<section class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e(t('ops.display_manager.column_user')) ?></th>
          <th><?= e(t('ops.display_manager.column_account_type')) ?></th>
          <th><?= e(t('ops.display_manager.column_dashboard')) ?></th>
          <th><?= e(t('ops.display_manager.column_status')) ?></th>
          <th><?= e(t('ops.display_manager.column_links')) ?></th>
          <th><?= e(t('ops.display_manager.column_actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="6" class="muted"><?= e(t('ops.display_manager.empty')) ?></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $username = trim((string)($row['username'] ?? ''));
              $authorityRole = trim((string)($row['authority_role'] ?? 'app_user'));
              $dashboardType = trim((string)($row['dashboard_type'] ?? 'my_work'));
              $isActive = strtolower(trim((string)($row['account_status'] ?? 'active'))) === 'active';
              $isEditable = in_array($authorityRole, ['app_user', 'tv_display'], true);
            ?>
            <tr>
              <td>
                <div class="ui-block"><strong><?= e($username !== '' ? $username : '-') ?></strong></div>
                <div class="muted u-style-a6422ad83c"><?= e((string)($row['email'] ?? '')) ?></div>
              </td>
              <td><?= e($localizeKey('ops.display_manager.authority_role.' . $authorityRole, $authorityRole)) ?></td>
              <td><?= e($localizeKey('ops.display_manager.dashboard_type.' . $dashboardType, $dashboardType)) ?></td>
              <td><?= e($isActive ? t('ops.display_manager.status_active') : t('ops.display_manager.status_inactive')) ?></td>
              <td>
                <?php if ($username !== ''): ?>
                  <a class="btn" href="/displays/user/<?= rawurlencode($username) ?>" target="_blank" rel="noopener"><?= e(t('ops.display_manager.link_user_display')) ?></a>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($isEditable): ?>
                  <form class="u-style-00bb94c5b4" method="post" action="/ops/display-manager/save-device">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="user_id" value="<?= (int)($row['id'] ?? 0) ?>">
                    <select name="authority_role">
                      <option value="app_user" <?= $authorityRole === 'app_user' ? 'selected' : '' ?>><?= e(t('ops.display_manager.authority_role.app_user')) ?></option>
                      <option value="tv_display" <?= $authorityRole === 'tv_display' ? 'selected' : '' ?>><?= e(t('ops.display_manager.authority_role.tv_display')) ?></option>
                    </select>
                    <select name="dashboard_type">
                      <option value="my_work" <?= $dashboardType === 'my_work' ? 'selected' : '' ?>><?= e(t('ops.display_manager.dashboard_type.my_work')) ?></option>
                      <option value="operator" <?= $dashboardType === 'operator' ? 'selected' : '' ?>><?= e(t('ops.display_manager.dashboard_type.operator')) ?></option>
                      <option value="display" <?= $dashboardType === 'display' ? 'selected' : '' ?>><?= e(t('ops.display_manager.dashboard_type.display')) ?></option>
                    </select>
                    <button class="btn" type="submit"><?= e(t('ops.display_manager.button_save')) ?></button>
                  </form>
                <?php else: ?>
                  <span class="muted"><?= e(t('ops.display_manager.locked_admin_profile')) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
