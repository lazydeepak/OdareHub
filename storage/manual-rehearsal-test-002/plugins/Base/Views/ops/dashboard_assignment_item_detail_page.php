<?php
declare(strict_types=1);


$tt = static function (string $key): string {
  return t($key);
};
$item = is_array($item ?? null) ? $item : [];
$assignedUsers = is_array($item['assigned_users'] ?? null) ? $item['assigned_users'] : [];
$assignedRoles = is_array($item['role_labels'] ?? null) ? $item['role_labels'] : [];
?>


<div class="u-style-6788b499a4">
  <section class="card">
    <div class="detail-grid u-style-aae630380c">
      <div class="ui-block">
        <div class="hero-kicker">Access Governance</div>
        <div class="section-head">
          <div class="ui-block">
            <h2 class="u-style-1169661891">Access Item Detail</h2>
            <div class="muted u-style-fe7b4979fe"><?= e((string)($item['label'] ?? 'Access Item')) ?><?= trim((string)($item['key'] ?? '')) !== '' ? ' · ' . e((string)$item['key']) : '' ?></div>
          </div>
        </div>
        <div class="muted"><?= e((string)($item['summary'] ?? 'Access registry detail.')) ?></div>
      </div>
      <div class="row">
        <a class="btn" href="/ops/access-control">Back to Access Control Board</a>
        <a class="btn" href="/ops/user-control">Open User Control Board</a>
        <a class="btn" href="/">Open Admin Home</a>
      </div>
    </div>
  </section>

  <section class="detail-grid">
    <div class="card u-style-1169661891">
      <div class="hero-kicker">Access Item</div>
      <div class="detail-grid u-style-d2c171b18b">
        <div class="detail-item">
          <div class="field-label">Label</div>
          <div class="detail-value"><?= e((string)($item['label'] ?? '-')) ?></div>
        </div>
        <div class="detail-item">
          <div class="field-label">Type</div>
          <div class="detail-value"><?= e((string)($item['type_label'] ?? '-')) ?></div>
        </div>
        <div class="detail-item">
          <div class="field-label">Key</div>
          <div class="detail-value"><?= e((string)($item['key'] ?? '-')) ?></div>
        </div>
      </div>
    </div>

    <div class="card u-style-1169661891">
      <div class="hero-kicker">Coverage</div>
      <div class="detail-grid u-style-d2c171b18b">
        <div class="detail-item">
          <div class="field-label">Assigned Users</div>
          <div class="detail-value"><?= e((string)count($assignedUsers)) ?></div>
        </div>
        <div class="detail-item">
          <div class="field-label">Linked Permission Profiles</div>
          <div class="detail-value"><?= e((string)((int)($item['assigned_roles_count'] ?? 0))) ?></div>
        </div>
      </div>
      <div class="row u-style-d2c171b18b">
        <?php foreach ((array)($item['role_preview'] ?? []) as $roleLabel): ?>
          <span class="status-chip status-chip-neutral"><?= e((string)$roleLabel) ?></span>
        <?php endforeach; ?>
        <?php if (empty($item['role_preview'])): ?>
          <span class="status-chip status-chip-neutral"> <?= e($tt('base.no_permission_profile_label')) ?> </span>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="detail-grid">
    <div class="card u-style-1169661891">
      <div class="hero-kicker">Linked Permission Profiles</div>
      <h3 class="u-style-65e94889f8">Permission Profile Coverage</h3>
      <div class="u-style-3a011d71df">
        <?php foreach ($assignedRoles as $roleLabel): ?>
          <div class="detail-item"><?= e((string)$roleLabel) ?></div>
        <?php endforeach; ?>
        <?php if ($assignedRoles === []): ?>
          <div class="detail-item muted">No permission profile groupings are attached to this governance item yet.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card u-style-1169661891">
      <div class="hero-kicker">Detail Guidance</div>
      <h3 class="u-style-65e94889f8">What This Page Means</h3>
      <div class="u-style-3a011d71df">
        <div class="detail-item muted">This view shows which users currently hold the selected governance item and which Permission Profile patterns are most commonly associated with it.</div>
        <div class="detail-item muted">Use the user detail links below to edit a specific person&apos;s provisioning. Access Control Board remains the canonical place for changing governance assignments.</div>
      </div>
    </div>
  </section>

  <section class="card u-style-1169661891">
    <div class="hero-kicker">Assigned Users</div>
    <h3 class="u-style-65e94889f8">Users Holding This Access Item</h3>
    <div class="table-wrap">
      <table class="u-style-682e877430">
        <thead>
          <tr>
            <th>User</th>
            <th>Access Authority</th>
            <th>Experience</th>
            <th>Assigned Apps</th>
            <th>Permission Profiles</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($assignedUsers as $user): ?>
            <tr>
              <td>
                <strong><?= e(trim((string)($user['display_name'] ?? '')) !== '' ? (string)$user['display_name'] : (string)($user['email'] ?? '')) ?></strong>
                <div class="muted"><?= e((string)($user['email'] ?? '')) ?></div>
              </td>
              <td>
                <div class="ui-block"><?= e((string)($user['authority_role'] ?? '-')) ?></div>
                <div class="muted"><?= e((string)($user['account_class'] ?? '-')) ?></div>
              </td>
              <td><?= e(trim((string)($user['dashboard_type'] ?? '')) !== '' ? (string)$user['dashboard_type'] : '-') ?></td>
              <td><?= e(!empty($user['assigned_apps']) ? implode(', ', (array)$user['assigned_apps']) : '-') ?></td>
              <td><?= e(!empty($user['profiles']) ? implode(', ', (array)$user['profiles']) : '-') ?></td>
              <td>
                <div class="u-style-6157e89086">
                  <a class="btn" href="<?= e((string)($user['user_detail_url'] ?? '/ops/access-control')) ?>">Governance Detail</a>
                  <a class="btn" href="<?= e((string)($user['user_control_url'] ?? '/ops/user-control')) ?>">User Detail</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($assignedUsers === []): ?>
            <tr><td colspan="6" class="muted">No users currently hold this access item.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
