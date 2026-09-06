<?php
declare(strict_types=1);

require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';

$overview = is_array($overview ?? null) ? $overview : [];
$roles = array_values((array)($roles ?? []));
$sensitive = array_values((array)($overview['sensitive_permissions'] ?? []));
$recentChanges = array_values((array)($overview['recent_changes'] ?? []));
?>

<div class="card">
    <h2 class="u-style-759e41d83d">ACL / RBAC Overview</h2>
    <div class="muted">Operational control surface for role-permission management using the central ACL policy engine.</div>
    <div class="row u-style-512257c17b">
        <a class="btn" href="/admin/acl/matrix">Role Matrix</a>
        <a class="btn" href="/admin/acl/roles">Role Detail</a>
        <a class="btn" href="/admin/acl/permissions">Permission Catalog</a>
    </div>
</div>

<div class="card">
    <h3 class="u-style-759e41d83d">Summary</h3>
    <div class="hero-meta u-style-8a77e5a311">
        <div class="hero-meta-card">
            <div class="muted">Total Roles</div>
            <div class="hero-meta-value"><?= (int)($overview['total_roles'] ?? 0) ?></div>
        </div>
        <div class="hero-meta-card">
            <div class="muted">Total Permissions</div>
            <div class="hero-meta-value"><?= (int)($overview['total_permissions'] ?? 0) ?></div>
        </div>
        <div class="hero-meta-card">
            <div class="muted">Roles With Overrides</div>
            <div class="hero-meta-value"><?= (int)($overview['roles_with_overrides'] ?? 0) ?></div>
        </div>
        <div class="hero-meta-card">
            <div class="muted">Custom Allows</div>
            <div class="hero-meta-value"><?= (int)($overview['custom_allow_count'] ?? 0) ?></div>
        </div>
        <div class="hero-meta-card">
            <div class="muted">Custom Denies</div>
            <div class="hero-meta-value"><?= (int)($overview['custom_deny_count'] ?? 0) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <h3 class="u-style-759e41d83d">Most Sensitive Permissions</h3>
    <?php if (empty($sensitive)): ?>
        <div class="muted">No sensitive permissions were detected.</div>
    <?php else: ?>
        <div class="row u-style-ca6f5db40b">
            <?php foreach ($sensitive as $perm): ?>
                <span class="pill u-style-511e3f441d"><?= htmlspecialchars((string)$perm) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3 class="u-style-759e41d83d">Role Quick Access</h3>
    <div class="row u-style-4725bb7117">
        <?php foreach ($roles as $role): ?>
            <a class="btn" href="/admin/acl/roles?role=<?= urlencode((string)$role) ?>"><?= htmlspecialchars((string)$role) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <h3 class="u-style-759e41d83d">Recent ACL Changes</h3>
    <?php if (empty($recentChanges)): ?>
        <div class="muted">No ACL changes recorded yet.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Actor</th>
                        <th>Target Role</th>
                        <th>Permission</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentChanges as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)($row['created_at'] ?? '')) ?></td>
                            <td>
                                <?= htmlspecialchars((string)($row['actor_email'] ?? '')) ?>
                                <div class="muted u-style-ae4019a50d"><?= htmlspecialchars((string)($row['actor_role'] ?? '')) ?></div>
                            </td>
                            <td><?= htmlspecialchars((string)($row['target_role'] ?? '')) ?></td>
                            <td><code><?= htmlspecialchars((string)($row['perm_key'] ?? '')) ?></code></td>
                            <td><?= htmlspecialchars((string)($row['previous_state'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($row['new_state'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($row['reason_text'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php';
