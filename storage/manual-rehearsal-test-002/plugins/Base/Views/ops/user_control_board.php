<?php
declare(strict_types=1);

$rows = is_array($rows ?? null) ? $rows : [];
$flash = trim((string)($flash ?? ''));
$error = trim((string)($error ?? ''));
$addUserOpen = !empty($addUserOpen);
$csrf = trim((string)($csrf ?? ''));

$accountTypeOptions = ['platform_admin', 'app_admin', 'app_user'];
$accountTypeLabels = [
    'platform_admin' => 'Platform Admin',
    'app_admin' => 'App Admin',
    'app_user' => 'App User',
];
$i18n = [
  'close_add_user' => t('admin.user_control.close_add_user'),
  'open_add_user' => t('admin.user_control.open_add_user'),
];

$toneClass = static function (string $tone): string {
    return match (strtolower(trim($tone))) {
        'ok' => 'status-chip-success',
        'warn' => 'status-chip-warning',
        'danger' => 'status-chip-danger',
        'muted' => 'status-chip-neutral',
        default => 'status-chip-info',
    };
};

$accountTypeLabel = static function (string $type) use ($accountTypeLabels): string {
    $k = strtolower(trim($type));
    if ($k !== '' && isset($accountTypeLabels[$k])) {
        return (string)$accountTypeLabels[$k];
    }
    return trim(str_replace('_', ' ', ucwords($type, '_')));
};

$lastActivityLabel = static function (array $row): string {
    $events = is_array($row['security_events'] ?? null) ? $row['security_events'] : [];
    if ($events !== []) {
        $first = $events[0];
        $at = trim((string)($first['created_at'] ?? ''));
        if ($at !== '') {
            return $at;
        }
    }
    $updated = trim((string)($row['updated_at'] ?? ''));
    if ($updated !== '') {
        return $updated;
    }
    return 'Never';
};

$summary = [
    'total' => count($rows),
    'pending' => 0,
    'disabled' => 0,
    'security_attention' => 0,
];
foreach ($rows as $row) {
    $status = strtolower(trim((string)($row['account_status'] ?? 'active')));
    $securityKey = strtolower(trim((string)($row['security_status_key'] ?? 'standard')));
    if ($status === 'pending') {
        $summary['pending']++;
    }
    if ($status === 'disabled') {
        $summary['disabled']++;
    }
    if (in_array($securityKey, ['recovery_attention', 'recovery_in_progress', 'suspended'], true)) {
        $summary['security_attention']++;
    }
}
?>

<style>
  .ucb-add-panel {
    border: 1px solid var(--style-border-soft);
    border-radius: 14px;
    background: var(--style-subtle-bg);
    padding: 12px;
    margin-bottom: 14px;
    display: none;
  }
  .ucb-add-panel.is-open { display: block; }
  .ucb-summary-grid {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    margin-top: 10px;
  }
  .ucb-summary-card {
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--card);
    padding: 10px;
    display: grid;
    gap: 4px;
  }
  .ucb-summary-value {
    font-size: 1.3rem;
    font-weight: 700;
    line-height: 1.15;
  }
  .ucb-row-link {
    cursor: pointer;
  }
  .ucb-row-link:hover {
    background: var(--style-subtle-bg);
  }
  .ucb-row-link:focus {
    outline: var(--style-border-soft);
    outline-offset: -2px;
  }
</style>

<section class="card">
  <div class="u-style-6788b499a4">
    <div class="u-style-51eba70256">
      <div class="hero-kicker"><?= e(t('admin.user_control.platform_admin')) ?></div>
      <div class="section-head">
        <div class="ui-block">
          <h2 class="u-style-1169661891"><?= e(t('admin.user_control.title')) ?></h2>
          <div class="muted u-style-fe7b4979fe"><?= e(t('admin.user_control.subtitle')) ?></div>
        </div>
      </div>
    </div>
    <div class="ucb-summary-grid">
      <div class="ucb-summary-card">
        <div class="muted"><?= e(t('admin.user_control.users')) ?></div>
        <div class="ucb-summary-value"><?= number_format((int)$summary['total']) ?></div>
      </div>
      <div class="ucb-summary-card">
        <div class="muted"><?= e(t('admin.user_control.pending_setup')) ?></div>
        <div class="ucb-summary-value"><?= number_format((int)$summary['pending']) ?></div>
      </div>
      <div class="ucb-summary-card">
        <div class="muted"><?= e(t('admin.user_control.disabled')) ?></div>
        <div class="ucb-summary-value"><?= number_format((int)$summary['disabled']) ?></div>
      </div>
      <div class="ucb-summary-card">
        <div class="muted"><?= e(t('admin.user_control.security_attention')) ?></div>
        <div class="ucb-summary-value"><?= number_format((int)$summary['security_attention']) ?></div>
      </div>
    </div>
    <div class="row">
      <a class="btn" href="/admin"><?= e(t('admin.launcher.title')) ?></a>
      <button class="btn" type="button" id="toggleUserCreate" aria-expanded="<?= $addUserOpen ? 'true' : 'false' ?>"><?= e(t('admin.user_control.open_add_user')) ?></button>
      <a class="btn" href="/ops/access-control"><?= e(t('admin.user_control.open_access_control_board')) ?></a>
      <a class="btn" href="/admin/apps"><?= e(t('dashboard.admin_tools')) ?></a>
    </div>
  </div>
</section>

<?php if ($flash !== ''): ?>
  <section class="card notice-ok"><?= e($flash) ?></section>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <section class="card notice-err"><?= e($error) ?></section>
<?php endif; ?>

<section id="userCreatePanel" class="ucb-add-panel <?= $addUserOpen ? 'is-open' : '' ?>">
  <form method="post" action="/ops/user-control/user/create" class="create-wizard" id="userControlCreateForm">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="redirect_to" value="/ops/user-control">

    <section class="create-step">
      <h4 class="create-step-title"><?= e(t('admin.user_control.create_user_account')) ?></h4>
      <p class="create-step-note"><?= e(t('admin.user_control.create_user_note')) ?></p>
      <div class="admin-form-grid admin-form-grid-compact">
        <label><?= e(t('auth.email')) ?>
          <input class="input" type="email" name="email" id="create_email" required placeholder="<?= e(t('admin.user_control.email_placeholder')) ?>" autocomplete="off">
        </label>
        <label><?= e(t('admin.user_control.display_name')) ?>
          <input class="input" type="text" name="display_name" maxlength="190" placeholder="<?= e(t('admin.user_control.optional')) ?>">
        </label>
        <label><?= e(t('admin.user_control.username')) ?>
          <input class="input" type="text" name="username" maxlength="120" placeholder="<?= e(t('admin.user_control.optional')) ?>">
        </label>
        <label><?= e(t('admin.user_control.department')) ?>
          <input class="input" type="text" name="department" maxlength="120" placeholder="<?= e(t('admin.user_control.optional')) ?>">
        </label>
        <label><?= e(t('common.status')) ?>
          <select name="account_status" id="create_account_status">
            <option value="active"><?= e(t('common.active')) ?></option>
            <option value="pending" selected><?= e(t('admin.user_control.pending')) ?></option>
            <option value="disabled"><?= e(t('admin.user_control.disabled')) ?></option>
          </select>
        </label>
        <label><?= e(t('admin.user_control.system_level')) ?>
          <select name="authority_role" id="create_account_type">
            <?php foreach ($accountTypeOptions as $authorityRole): ?>
              <option value="<?= e($authorityRole) ?>" <?= $authorityRole === 'app_user' ? 'selected' : '' ?>><?= e($accountTypeLabel((string)$authorityRole)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="admin-form-grid admin-form-grid-compact u-style-8a77e5a311">
        <label class="u-style-0a9765dab3">
          <input type="checkbox" name="send_setup_link" value="1" checked>
          <span><strong><?= e(t('admin.user_control.send_setup_link')) ?></strong><br><span class="create-step-note"><?= e(t('admin.user_control.send_setup_link_note')) ?></span></span>
        </label>
        <label class="u-style-0a9765dab3">
          <input type="checkbox" name="require_password_setup" value="1" checked>
          <span><strong><?= e(t('admin.user_control.require_password_setup')) ?></strong><br><span class="create-step-note"><?= e(t('admin.user_control.require_password_setup_note')) ?></span></span>
        </label>
      </div>
    </section>

    <div class="admin-action-row">
      <button class="btn ok" type="submit"><?= e(t('admin.user_control.create_user')) ?></button>
      <button class="btn" type="reset"><?= e(t('common.reset')) ?></button>
      <a class="btn" href="/ops/access-control"><?= e(t('admin.user_control.open_access_control_board')) ?></a>
    </div>
  </form>
</section>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h2 class="u-style-1169661891"><?= e(t('admin.user_control.users')) ?></h2>
      <div class="muted u-style-fe7b4979fe"><?= e(t('admin.user_control.users_subtitle')) ?></div>
    </div>
  </div>
  <div class="table-wrap">
    <table class="u-style-df80035ac5">
      <thead>
        <tr>
          <th><?= e(t('admin.user_control.col_user')) ?></th>
          <th><?= e(t('admin.user_control.col_org_scope')) ?></th>
          <th><?= e(t('admin.user_control.col_account_status')) ?></th>
          <th><?= e(t('admin.user_control.col_setup')) ?></th>
          <th><?= e(t('admin.user_control.col_security')) ?></th>
          <th><?= e(t('admin.user_control.col_last_activity')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <?php
            $userId = (int)($row['id'] ?? 0);
            $detailUrl = '/ops/user-control/detail?user_id=' . $userId;
            $accountStatus = strtolower((string)($row['account_status'] ?? 'active'));
            $assignedApps = is_array($row['assigned_app_list'] ?? null) ? (array)$row['assigned_app_list'] : [];
            $displayLabel = (string)($row['display_label'] ?? 'User');
          ?>
          <tr class="ucb-row-link" data-href="<?= e($detailUrl) ?>" tabindex="0" role="link" aria-label="<?= e(t('admin.user_control.open_user_detail', ['user' => $displayLabel])) ?>">
            <td>
              <a class="user-control-detail-link" href="<?= e($detailUrl) ?>"><?= e((string)($row['display_label'] ?? t('admin.user_control.user_fallback'))) ?></a>
              <div class="muted u-style-0772d0db65"><?= e((string)($row['email'] ?? '')) ?></div>
              <?php if (trim((string)($row['username'] ?? '')) !== ''): ?>
                <div class="muted u-style-0772d0db65">@<?= e((string)$row['username']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="u-style-24fbece857">
                <div class="ui-block"><?= e(trim((string)($row['department'] ?? '')) !== '' ? (string)$row['department'] : '-') ?></div>
                <div class="muted u-style-0772d0db65"><?= e($assignedApps === [] ? t('admin.user_control.no_assigned_apps') : (t('admin.user_control.apps_prefix') . implode(', ', $assignedApps))) ?></div>
              </div>
            </td>
            <td>
              <?php
                $statusTone = $accountStatus === 'disabled' ? 'muted' : ($accountStatus === 'pending' ? 'warn' : 'ok');
              ?>
              <span class="status-chip <?= e($toneClass($statusTone)) ?>"><?= e((string)($row['status_label'] ?? 'Active')) ?></span>
            </td>
            <td>
              <span class="status-chip <?= e($toneClass((string)($row['verification_status_tone'] ?? 'info'))) ?>"><?= e((string)($row['verification_status_label'] ?? 'Ready')) ?></span>
            </td>
            <td>
              <span class="status-chip <?= e($toneClass((string)($row['security_status_tone'] ?? 'info'))) ?>"><?= e((string)($row['security_status_label'] ?? 'Standard')) ?></span>
            </td>
            <td>
              <div class="muted u-style-0772d0db65"><?= e($lastActivityLabel($row)) ?></div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
          <tr><td colspan="6" class="muted"><?= e(t('admin.user_control.no_users')) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<script>
  (function () {
    var createPanel = document.getElementById('userCreatePanel');
    var createToggle = document.getElementById('toggleUserCreate');
    var createEmail = document.getElementById('create_email');
    var createPanelStartsOpen = <?= $addUserOpen ? 'true' : 'false' ?>;
    var i18n = <?= json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function openCreatePanel(focus) {
      if (!createPanel) {
        return;
      }
      createPanel.classList.add('is-open');
      if (createToggle) {
        createToggle.textContent = String(i18n.close_add_user || 'Close Add User');
        createToggle.setAttribute('aria-expanded', 'true');
      }
      if (focus && createEmail) {
        window.setTimeout(function () {
          createPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
          createEmail.focus({ preventScroll: true });
        }, 10);
      }
    }

    function closeCreatePanel() {
      if (!createPanel) {
        return;
      }
      createPanel.classList.remove('is-open');
      if (createToggle) {
        createToggle.textContent = String(i18n.open_add_user || '+ Add User');
        createToggle.setAttribute('aria-expanded', 'false');
      }
    }

    if (createToggle) {
      createToggle.addEventListener('click', function () {
        if (createPanel && createPanel.classList.contains('is-open')) {
          closeCreatePanel();
        } else {
          openCreatePanel(true);
        }
      });
    }

    if (createPanelStartsOpen) {
      openCreatePanel(false);
    } else {
      closeCreatePanel();
    }

    var createStatus = document.getElementById('create_account_status');
    var sendSetup = document.querySelector('#userControlCreateForm input[name="send_setup_link"]');
    var requireSetup = document.querySelector('#userControlCreateForm input[name="require_password_setup"]');

    function syncStatusFromSetup() {
      if (!createStatus) {
        return;
      }
      if (createStatus.value === 'disabled') {
        return;
      }
      var setupPending = !!((sendSetup && sendSetup.checked) || (requireSetup && requireSetup.checked));
      createStatus.value = setupPending ? 'pending' : 'active';
    }

    [sendSetup, requireSetup].forEach(function (el) {
      if (el) {
        el.addEventListener('change', syncStatusFromSetup);
      }
    });

    document.querySelectorAll('tr.ucb-row-link[data-href]').forEach(function (row) {
      var url = String(row.getAttribute('data-href') || '').trim();
      if (!url) {
        return;
      }
      row.addEventListener('click', function (ev) {
        var target = ev.target;
        if (target && target.closest('a,button,input,select,textarea,label,form')) {
          return;
        }
        window.location.assign(url);
      });
      row.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          window.location.assign(url);
        }
      });
    });
  })();
</script>
