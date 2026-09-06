<?php
declare(strict_types=1);

require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';

$detail = is_array($detail ?? null) ? $detail : [];
$role = (string)($detail['role'] ?? 'admin');
$rows = array_values((array)($detail['rows'] ?? []));
$summary = is_array($detail['summary'] ?? null) ? $detail['summary'] : [];
$allowedByGroup = (array)($detail['allowed_by_group'] ?? []);
$highRiskAllowed = array_values((array)($detail['high_risk_allowed'] ?? []));
$roles = array_values((array)($roles ?? []));
$csrf = \App\Core\Auth::csrfToken();
?>

<div class="card">
    <h2 class="u-style-759e41d83d"><?= e(t('acl.role_detail.title')) ?></h2>
    <form method="get" action="/admin/acl/roles" class="row u-style-6ebdd5b5f9">
        <div class="ui-block">
            <label for="role"><strong><?= e(t('acl.role_detail.role_label')) ?></strong></label><br>
            <select id="role" name="role">
                <?php foreach ($roles as $r): ?>
                    <option value="<?= htmlspecialchars((string)$r) ?>" <?= $role === (string)$r ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$r) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ui-block"><button type="submit" class="btn ok"><?= e(t('acl.role_detail.load_role')) ?></button></div>
        <div class="ui-block"><a class="btn" href="/admin/acl/matrix"><?= e(t('acl.role_detail.back_to_matrix')) ?></a></div>
    </form>
</div>

<div class="card">
    <h3 class="u-style-759e41d83d"><?= e(t('acl.role_detail.effective_capabilities')) ?></h3>
    <div class="row u-style-7f1236fc05">
        <div class="u-style-f1a8075913">
            <strong><?= e(t('acl.role_detail.allowed_by_group')) ?></strong>
            <?php if (empty($allowedByGroup)): ?>
                <div class="muted u-style-fe7b4979fe"><?= e(t('acl.role_detail.no_effective_perms')) ?></div>
            <?php else: ?>
                <?php foreach ($allowedByGroup as $group => $permKeys): ?>
                    <div class="u-style-d2c171b18b">
                        <div class="muted u-style-e3ec02ace9"><?= htmlspecialchars((string)$group) ?></div>
                        <div class="row u-style-72c2e8ebbf">
                            <?php foreach ((array)$permKeys as $perm): ?>
                                <span class="pill"><?= htmlspecialchars((string)$perm) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="u-style-368cc682e4">
            <strong><?= e(t('acl.role_detail.high_risk_capabilities')) ?></strong>
            <?php if (empty($highRiskAllowed)): ?>
                <div class="muted u-style-fe7b4979fe"><?= e(t('acl.role_detail.no_sensitive_perms')) ?></div>
            <?php else: ?>
                <div class="row u-style-4587808f6a">
                    <?php foreach ($highRiskAllowed as $perm): ?>
                        <span class="pill u-style-511e3f441d"><?= htmlspecialchars((string)$perm) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($ok !== ''): ?>
    <div class="card"><div class="muted u-style-3f867ec8b5"><?= htmlspecialchars((string)$ok) ?></div></div>
<?php endif; ?>
<?php if ($err !== ''): ?>
    <div class="card"><div class="muted u-style-8ec1503168"><?= htmlspecialchars((string)$err) ?></div></div>
<?php endif; ?>

<div class="card">
    <h3 class="u-style-759e41d83d"><?= e(t('acl.role_detail.effective_summary')) ?>: <?= htmlspecialchars($role) ?></h3>
    <div class="hero-meta">
        <div class="hero-meta-card">
            <div class="muted"><?= e(t('acl.role_detail.effective_allowed')) ?></div>
            <div class="hero-meta-value"><?= (int)($summary['effective_allowed'] ?? 0) ?></div>
        </div>
        <div class="hero-meta-card">
            <div class="muted"><?= e(t('acl.role_detail.default_allowed')) ?></div>
            <div class="hero-meta-value"><?= (int)($summary['default_allowed'] ?? 0) ?></div>
        </div>
        <div class="hero-meta-card">
            <div class="muted"><?= e(t('acl.role_detail.custom_allowed')) ?></div>
            <div class="hero-meta-value"><?= (int)($summary['custom_allowed'] ?? 0) ?></div>
        </div>
        <div class="hero-meta-card">
            <div class="muted"><?= e(t('acl.role_detail.custom_denied')) ?></div>
            <div class="hero-meta-value"><?= (int)($summary['custom_denied'] ?? 0) ?></div>
        </div>
        <div class="hero-meta-card">
            <div class="muted"><?= e(t('acl.role_detail.sensitive_allowed')) ?></div>
            <div class="hero-meta-value"><?= (int)($summary['sensitive_allowed'] ?? 0) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <h3 class="u-style-759e41d83d"><?= e(t('acl.role_detail.permission_controls')) ?></h3>
    <div class="muted"><?= e(t('acl.role_detail.controls_desc')) ?></div>
    <div class="table-wrap u-style-d2c171b18b">
        <table>
            <thead>
                <tr>
                    <th><?= e(t('acl.role_detail.permission_col')) ?></th>
                    <th><?= e(t('acl.role_detail.group_col')) ?></th>
                    <th><?= e(t('acl.role_detail.default_col')) ?></th>
                    <th><?= e(t('acl.role_detail.effective_col')) ?></th>
                    <th><?= e(t('acl.role_detail.source_col')) ?></th>
                    <th><?= e(t('acl.role_detail.action_col')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $state = (array)($row['state'] ?? []); ?>
                    <tr>
                        <td>
                            <code><?= htmlspecialchars((string)($row['perm_key'] ?? '')) ?></code>
                            <div class="muted u-style-ae4019a50d"><?= htmlspecialchars((string)($row['description'] ?? '')) ?></div>
                            <?php if (!empty($row['sensitive'])): ?>
                                <span class="pill u-style-511e3f441d"><?= e(t('acl.matrix.sensitive')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string)($row['group'] ?? '')) ?></td>
                        <td>
                            <?= !empty($state['default_allow']) ? e(t('acl.role_detail.action_grant')) : e(t('acl.matrix.denied')) ?>
                        </td>
                        <td>
                            <?= !empty($state['effective']) ? e(t('acl.role_detail.action_grant')) : e(t('acl.matrix.denied')) ?>
                        </td>
                        <td><?= htmlspecialchars((string)($state['source'] ?? 'default_deny')) ?></td>
                        <td>
                            <form class="u-style-b5711ab0a7" method="post" action="/admin/acl/roles/update" onsubmit="return confirm('Apply this ACL change?');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
                                <input type="hidden" name="permission" value="<?= htmlspecialchars((string)($row['perm_key'] ?? '')) ?>">
                                <select name="action" required>
                                    <option value="grant"><?= e(t('acl.role_detail.action_grant')) ?></option>
                                    <option value="revoke"><?= e(t('acl.role_detail.action_revoke')) ?></option>
                                    <option value="reset"><?= e(t('acl.role_detail.action_reset')) ?></option>
                                </select>
                                <input class="input" type="text" name="reason" maxlength="255" placeholder="<?= e(t('acl.role_detail.reason_placeholder')) ?>">
                                <button class="btn ok" type="submit"><?= e(t('acl.role_detail.btn_apply')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php';
