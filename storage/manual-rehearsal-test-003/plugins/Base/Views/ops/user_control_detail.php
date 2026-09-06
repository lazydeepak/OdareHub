<?php
declare(strict_types=1);


$tt = static function (string $key): string {
  return t($key);
};
$row = is_array($row ?? null) ? $row : [];
$securityEvents = is_array($securityEvents ?? null) ? $securityEvents : [];
$flash = trim((string)($flash ?? ''));
$error = trim((string)($error ?? ''));
$csrf = trim((string)($csrf ?? ''));

$userId = (int)($row['id'] ?? 0);
$detailUrl = '/ops/user-control/detail?user_id=' . $userId;
$accessControlUrl = '/ops/access-control/detail?user_id=' . $userId . '&full=1';
$assignedApps = is_array($row['assigned_app_list'] ?? null) ? $row['assigned_app_list'] : [];
$verificationKey = strtolower(trim((string)($row['verification_status_key'] ?? 'ready')));
$securityKey = strtolower(trim((string)($row['security_status_key'] ?? 'standard')));
$canSendSetupLink = in_array($verificationKey, ['invite_pending', 'setup_pending'], true) || $securityKey === 'password_setup_pending';
$setupActionLabel = $verificationKey === 'invite_pending' ? 'Resend Setup Link' : 'Send Setup Link';

$toneClass = static function (string $tone): string {
    return match (strtolower(trim($tone))) {
        'ok' => 'status-chip-success',
        'warn' => 'status-chip-warning',
        'danger' => 'status-chip-danger',
        'muted' => 'status-chip-neutral',
        default => 'status-chip-info',
    };
};

$passwordStatus = trim((string)($row['password_changed_at'] ?? '')) !== ''
    ? 'Changed at ' . (string)$row['password_changed_at']
    : 'No password change timestamp recorded yet';

$twoFaStatus = !empty($row['twofa_enabled']) ? 'Enabled' : 'Not enabled';

$isSecurityEvent = static function (string $eventType): bool {
    $k = strtolower(trim($eventType));
    return str_contains($k, 'password')
        || str_contains($k, 'recovery')
        || str_contains($k, 'invite')
        || str_contains($k, 'twofa')
        || str_contains($k, 'security');
};

$isAccountEvent = static function (string $eventType): bool {
    $k = strtolower(trim($eventType));
    return str_contains($k, 'login')
        || str_contains($k, 'logout')
        || str_contains($k, 'session')
        || str_contains($k, 'account')
        || str_contains($k, 'setup');
};

$isAppEvent = static function (string $eventType): bool {
    $k = strtolower(trim($eventType));
    return str_starts_with($k, 'app_')
        || str_starts_with($k, 'page_')
        || str_starts_with($k, 'route_')
        || str_contains($k, 'feature')
        || str_contains($k, 'usage');
};

$securityEventsList = [];
$accountEventsList = [];
$appEventsList = [];
foreach ($securityEvents as $event) {
    $eventType = (string)($event['event_type'] ?? '');
    if ($isSecurityEvent($eventType)) {
        $securityEventsList[] = $event;
    }
    if ($isAccountEvent($eventType)) {
        $accountEventsList[] = $event;
    }
    if ($isAppEvent($eventType)) {
        $appEventsList[] = $event;
    }
}

$lastSeen = 'Never';
if ($securityEvents !== []) {
    $lastSeenCandidate = trim((string)($securityEvents[0]['created_at'] ?? ''));
    if ($lastSeenCandidate !== '') {
        $lastSeen = $lastSeenCandidate;
    }
}
?>

<style>
  .ucd-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
  }
  .ucd-tab-btn {
    border: 1px solid var(--style-border-soft);
    background: var(--style-subtle-bg);
    color: var(--text);
    border-radius: 999px;
    padding: 6px 12px;
    cursor: pointer;
    font-size: .86rem;
  }
  .ucd-tab-btn.is-active {
    background: var(--style-subtle-bg);
    color: var(--text);
    border-color: var(--style-border-soft);
  }
  .ucd-tab-panel { display: none; }
  .ucd-tab-panel.is-active { display: block; }
  .ucd-panel-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  }
  .ucd-inline-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }
  .ucd-inline-actions form {
    margin: 0;
    display: inline-flex;
  }
</style>

<div class="u-style-6788b499a4">
  <section class="card">
    <div class="u-style-6788b499a4">
      <div class="ui-block">
        <div class="hero-kicker">Platform Admin</div>
        <div class="section-head">
          <div class="ui-block">
            <h2 class="u-style-1169661891">User Control Detail</h2>
            <div class="muted u-style-4e03918398"><?= e((string)($row['display_label'] ?? 'User')) ?><?= trim((string)($row['email'] ?? '')) !== '' ? ' · ' . e((string)$row['email']) : '' ?></div>
          </div>
        </div>
        <div class="muted">User/account management detail for lifecycle, security, activity, and admin support actions.</div>
      </div>
      <div class="row">
        <a class="btn" href="/ops/user-control">Back to User Control Board</a>
        <a class="btn" href="<?= e($accessControlUrl) ?>">Open Access Control Board</a>
        <a class="btn" href="/">Open Admin Home</a>
      </div>
    </div>
  </section>

  <?php if ($flash !== ''): ?>
    <section class="card notice-ok"><?= e($flash) ?></section>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <section class="card notice-err"><?= e($error) ?></section>
  <?php endif; ?>

  <section class="card">
    <div class="ucd-tabs" role="tablist" aria-label="User control detail tabs">
      <button type="button" class="ucd-tab-btn is-active" data-ucd-tab="overview" role="tab" aria-selected="true">Overview</button>
      <button type="button" class="ucd-tab-btn" data-ucd-tab="security" role="tab" aria-selected="false">Security & Recovery</button>
      <button type="button" class="ucd-tab-btn" data-ucd-tab="account" role="tab" aria-selected="false">Account Activity</button>
      <button type="button" class="ucd-tab-btn" data-ucd-tab="app" role="tab" aria-selected="false">App Activity</button>
      <button type="button" class="ucd-tab-btn" data-ucd-tab="admin" role="tab" aria-selected="false">Admin Actions</button>
    </div>

    <div class="ucd-tab-panel is-active" data-ucd-panel="overview">
      <div class="ucd-panel-grid">
        <div class="card u-style-1169661891">
          <div class="hero-kicker">Identity</div>
          <h3><?= e((string)($row['display_label'] ?? 'User')) ?></h3>
          <div class="detail-grid u-style-d2c171b18b">
            <div class="detail-item">
              <div class="field-label">Email</div>
              <div class="detail-value"><?= e((string)($row['email'] ?? '-')) ?></div>
            </div>
            <div class="detail-item">
              <div class="field-label">Username</div>
              <div class="detail-value"><?= e(trim((string)($row['username'] ?? '')) !== '' ? (string)$row['username'] : '-') ?></div>
            </div>
            <div class="detail-item">
              <div class="field-label">Department</div>
              <div class="detail-value"><?= e(trim((string)($row['department'] ?? '')) !== '' ? (string)$row['department'] : '-') ?></div>
            </div>
            <div class="detail-item">
              <div class="field-label">Last Seen</div>
              <div class="detail-value"><?= e($lastSeen) ?></div>
            </div>
          </div>
        </div>

        <div class="card u-style-1169661891">
          <div class="hero-kicker">Account</div>
          <h3>Readiness Snapshot</h3>
          <div class="u-style-3a011d71df">
            <div class="detail-item">
              <div class="field-label"> <?= e($tt('base.account_status_message')) ?> </div>
              <?php
                $accountStatus = strtolower((string)($row['account_status'] ?? 'active'));
                $statusTone = $accountStatus === 'disabled' ? 'muted' : ($accountStatus === 'pending' ? 'warn' : 'ok');
              ?>
              <div class="detail-value"><span class="status-chip <?= e($toneClass($statusTone)) ?>"><?= e((string)($row['status_label'] ?? 'Active')) ?></span></div>
            </div>
            <div class="detail-item">
              <div class="field-label">Setup</div>
              <div class="detail-value"><span class="status-chip <?= e($toneClass((string)($row['verification_status_tone'] ?? 'info'))) ?>"><?= e((string)($row['verification_status_label'] ?? 'Ready')) ?></span></div>
            </div>
            <div class="detail-item">
              <div class="field-label">Security</div>
              <div class="detail-value"><span class="status-chip <?= e($toneClass((string)($row['security_status_tone'] ?? 'info'))) ?>"><?= e((string)($row['security_status_label'] ?? 'Standard')) ?></span></div>
            </div>
          </div>
        </div>

        <div class="card u-style-1169661891">
          <div class="hero-kicker"> <?= e($tt('base.user_control_detail_description')) ?> </div>
          <h3>Compact Access Note</h3>
          <div class="u-style-3a011d71df">
            <div class="detail-item">
              <div class="field-label">Access Authority</div>
              <div class="detail-value"><?= e((string)($row['account_type_label'] ?? '-')) ?></div>
            </div>
            <div class="detail-item">
              <div class="field-label">Assigned Apps</div>
              <div class="detail-value"><?= e($assignedApps === [] ? '-' : implode(', ', $assignedApps)) ?></div>
            </div>
            <div class="detail-item">
              <div class="field-label">Default App / Home Surface</div>
              <div class="detail-value"><?= e((string)($row['landing_summary'] ?? '-')) ?></div>
            </div>
          </div>
          <div class="muted u-style-d2c171b18b">Provisioning and governance modeling are maintained in Access Control Board.</div>
        </div>
      </div>
    </div>

    <div class="ucd-tab-panel" data-ucd-panel="security">
      <div class="ucd-panel-grid">
        <div class="card u-style-1169661891">
          <div class="hero-kicker">Security Posture</div>
          <h3>Recovery and Protection</h3>
          <div class="u-style-3a011d71df">
            <div class="detail-item">
              <div class="field-label">Password</div>
              <div class="detail-value"><?= e($passwordStatus) ?></div>
            </div>
            <div class="detail-item">
              <div class="field-label">2FA</div>
              <div class="detail-value"><?= e($twoFaStatus) ?></div>
            </div>
            <div class="detail-item">
              <div class="field-label">Last Recovery Action</div>
              <div class="detail-value"><?= e((string)($row['last_recovery_action'] ?? 'No recovery activity recorded')) ?></div>
            </div>
          </div>
        </div>

        <div class="card u-style-1169661891">
          <div class="hero-kicker">Security Events</div>
          <h3>Recent Recovery / Identity Events</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Event</th>
                  <th>Outcome</th>
                  <th>At</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($securityEventsList as $event): ?>
                  <tr>
                    <td><?= e((string)($event['event_type'] ?? '')) ?></td>
                    <td><?= e((string)($event['outcome'] ?? '')) ?></td>
                    <td><?= e((string)($event['created_at'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if ($securityEventsList === []): ?>
                  <tr>
                    <td colspan="3" class="muted">No security or recovery events recorded yet.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="ucd-tab-panel" data-ucd-panel="account">
      <div class="ucd-panel-grid">
        <div class="card u-style-1169661891">
          <div class="hero-kicker">Account Activity</div>
          <h3>Login / Setup / Account Events</h3>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Event</th>
                  <th>Outcome</th>
                  <th>At</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($accountEventsList as $event): ?>
                  <tr>
                    <td><?= e((string)($event['event_type'] ?? '')) ?></td>
                    <td><?= e((string)($event['outcome'] ?? '')) ?></td>
                    <td><?= e((string)($event['created_at'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if ($accountEventsList === []): ?>
                  <tr>
                    <td colspan="3" class="muted">No account activity events are currently available.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="ucd-tab-panel" data-ucd-panel="app">
      <div class="ucd-panel-grid">
        <div class="card u-style-1169661891">
          <div class="hero-kicker">App Activity</div>
          <h3>App/Page Usage Signals</h3>
          <div class="muted u-style-761d3addb2">This section is prepared for user-scoped app activity summaries without turning this page into a full event system.</div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Event</th>
                  <th>Outcome</th>
                  <th>At</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($appEventsList as $event): ?>
                  <tr>
                    <td><?= e((string)($event['event_type'] ?? '')) ?></td>
                    <td><?= e((string)($event['outcome'] ?? '')) ?></td>
                    <td><?= e((string)($event['created_at'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if ($appEventsList === []): ?>
                  <tr>
                    <td colspan="3" class="muted">No app activity events are currently available.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="ucd-tab-panel" data-ucd-panel="admin">
      <div class="ucd-panel-grid">
        <div class="card u-style-1169661891">
          <div class="hero-kicker">Admin Actions</div>
          <h3>User Support Operations</h3>
          <form method="post" action="/ops/access-control/user/update">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="user_id" value="<?= $userId ?>">
            <input type="hidden" name="redirect_to" value="<?= e($detailUrl) ?>">
            <div class="row">
              <label>Email
                <input class="input" type="email" name="email" value="<?= e((string)($row['email'] ?? '')) ?>" required>
              </label>
              <label>Display Name
                <input class="input" type="text" name="display_name" value="<?= e((string)($row['display_name'] ?? '')) ?>">
              </label>
            </div>
            <div class="row">
              <label>Username
                <input class="input" type="text" name="username" value="<?= e((string)($row['username'] ?? '')) ?>">
              </label>
              <label>Department
                <input class="input" type="text" name="department" value="<?= e((string)($row['department'] ?? '')) ?>">
              </label>
              <label>Status
                <select name="account_status">
                  <option value="active" <?= strtolower((string)($row['account_status'] ?? 'active')) === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="pending" <?= strtolower((string)($row['account_status'] ?? 'active')) === 'pending' ? 'selected' : '' ?>> <?= e($tt('base.pending_message')) ?> </option>
                  <option value="disabled" <?= strtolower((string)($row['account_status'] ?? 'active')) === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
              </label>
            </div>
            <div class="ucd-inline-actions u-style-d2c171b18b">
              <button class="btn" type="submit">Save Basic User Info</button>
              <a class="btn" href="<?= e($accessControlUrl) ?>">Open Access Control Board for This User</a>
            </div>
          </form>

          <div class="ucd-inline-actions u-style-56f4356299">
            <form method="post" action="/ops/user-control/user/setup-link">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="user_id" value="<?= $userId ?>">
              <input type="hidden" name="redirect_to" value="<?= e($detailUrl) ?>">
              <button class="btn" type="submit" <?= $canSendSetupLink ? '' : 'disabled' ?>><?= e($setupActionLabel) ?></button>
            </form>
            <form method="post" action="/ops/user-control/user/recovery-link">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="user_id" value="<?= $userId ?>">
              <input type="hidden" name="redirect_to" value="<?= e($detailUrl) ?>">
              <button class="btn" type="submit">Require Password Reset</button>
            </form>
          </div>

          <div class="ucd-inline-actions u-style-56f4356299">
            <form method="post" action="/ops/access-control/user/status">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="user_id" value="<?= $userId ?>">
              <input type="hidden" name="account_status" value="active">
              <input type="hidden" name="redirect_to" value="<?= e($detailUrl) ?>">
              <button class="btn" type="submit">Enable</button>
            </form>
            <form method="post" action="/ops/access-control/user/status">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="user_id" value="<?= $userId ?>">
              <input type="hidden" name="account_status" value="disabled">
              <input type="hidden" name="redirect_to" value="<?= e($detailUrl) ?>">
              <button class="btn" type="submit">Disable</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
  (function () {
    var tabs = document.querySelectorAll('.ucd-tab-btn[data-ucd-tab]');
    var panels = document.querySelectorAll('.ucd-tab-panel[data-ucd-panel]');

    function activateTab(key) {
      tabs.forEach(function (btn) {
        var on = btn.getAttribute('data-ucd-tab') === key;
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      panels.forEach(function (panel) {
        var on = panel.getAttribute('data-ucd-panel') === key;
        panel.classList.toggle('is-active', on);
      });
    }

    tabs.forEach(function (btn) {
      btn.addEventListener('click', function () {
        activateTab(String(btn.getAttribute('data-ucd-tab') || 'overview'));
      });
    });

    activateTab('overview');
  })();
</script>
