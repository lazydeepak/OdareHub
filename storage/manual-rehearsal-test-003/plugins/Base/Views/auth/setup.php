<?php require APP_ROOT . '/public/views/layouts/auth_header.php'; ?>
<?php
$mode = trim((string)($mode ?? 'initial'));
$loginUrl = trim((string)($loginUrl ?? '/login'));
$setupStatus = is_array($setupStatus ?? null) ? $setupStatus : [];
require APP_ROOT . '/public/views/setup/_stage_chrome.php';
?>
<div class="card" id="initial-admin-setup">
  <?php if ($mode === 'resume'): ?>
    <h2 class="u-style-759e41d83d"><?= e(t('setup.action.login_to_continue')) ?></h2>
    <div class="muted"><?= e(t('setup.alert.resume_login_required.summary')) ?></div>

    <div class="u-style-60d53e75d8">
      <a class="btn ok" href="<?= e($loginUrl) ?>"><?= e(t('setup.action.login_to_continue')) ?></a>
      <a class="btn" href="/login"><?= e(t('common.login')) ?></a>
    </div>
  <?php else: ?>
    <h2 class="u-style-759e41d83d"><?= e(t('setup.admin.title')) ?></h2>
    <div class="muted"><?= e(t('setup.admin.subtitle')) ?></div>

    <form class="u-style-3744f1b491" method="post" action="/setup">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <label><?= e(t('auth.email')) ?>
        <input class="input" type="email" name="email" required>
      </label>
      <label><?= e(t('setup.admin.password')) ?>
        <input class="input" type="password" name="password" required minlength="10">
        <small class="muted"><?= e(t('setup.admin.password_hint')) ?></small>
      </label>
      <button class="btn ok" type="submit"><?= e(t('setup.action.create_admin')) ?></button>
    </form>
  <?php endif; ?>
</div>
<?php require APP_ROOT . '/public/views/layouts/auth_footer.php'; ?>
