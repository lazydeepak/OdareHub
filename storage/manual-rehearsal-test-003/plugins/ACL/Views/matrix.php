<?php
declare(strict_types=1);

require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';

$matrix = is_array($matrix ?? null) ? $matrix : [];
$roles = array_values((array)($matrix['roles'] ?? []));
$rows = array_values((array)($matrix['rows'] ?? []));
$groups = (array)($matrix['groups'] ?? []);
$groupFilter = (string)($matrix['group_filter'] ?? '');
$search = (string)($matrix['search'] ?? '');
?>

<div class="card">
    <h2 class="u-style-759e41d83d"><?= e(t('acl.matrix.title')) ?></h2>
    <div class="muted"><?= e(t('acl.matrix.subtitle')) ?></div>
    <div class="row u-style-242e13ddea">
        <a class="btn" href="/admin/acl"><?= e(t('acl.matrix.overview_link')) ?></a>
        <a class="btn" href="/admin/acl/permissions"><?= e(t('acl.matrix.permissions_link')) ?></a>
    </div>
</div>

<?php if ($ok !== ''): ?>
    <div class="card"><div class="muted u-style-3f867ec8b5"><?= htmlspecialchars((string)$ok) ?></div></div>
<?php endif; ?>
<?php if ($err !== ''): ?>
    <div class="card"><div class="muted u-style-8ec1503168"><?= htmlspecialchars((string)$err) ?></div></div>
<?php endif; ?>

<div class="card">
    <form method="get" action="/admin/acl/matrix" class="row u-style-6ebdd5b5f9">
        <div class="ui-block">
            <label for="group"><strong><?= e(t('acl.matrix.permission_group')) ?></strong></label><br>
            <select id="group" name="group">
                <option value=""><?= e(t('acl.matrix.all_groups')) ?></option>
                <?php foreach ($groups as $gKey => $gLabel): ?>
                    <option value="<?= htmlspecialchars((string)$gKey) ?>" <?= $groupFilter === (string)$gKey ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$gLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ui-block">
            <label for="q"><strong><?= e(t('common.search')) ?></strong></label><br>
            <input class="input" id="q" type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= e(t('acl.matrix.search_placeholder')) ?>">
        </div>
        <div class="ui-block">
            <button type="submit" class="btn ok"><?= e(t('acl.matrix.apply_filter')) ?></button>
        </div>
    </form>
</div>

<div class="card">
    <h3 class="u-style-759e41d83d"><?= e(t('acl.matrix.matrix_title')) ?></h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?= e(t('acl.matrix.permission')) ?></th>
                    <th><?= e(t('acl.matrix.group')) ?></th>
                    <?php foreach ($roles as $role): ?>
                        <th>
                            <a href="/admin/acl/roles?role=<?= urlencode((string)$role) ?>" style="text-decoration:none">
                                <?= htmlspecialchars((string)$role) ?>
                            </a>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $cells = (array)($row['cells'] ?? []); ?>
                    <tr>
                        <td>
                            <code><?= htmlspecialchars((string)($row['perm_key'] ?? '')) ?></code>
                            <?php if (!empty($row['sensitive'])): ?>
                                <span class="pill u-style-6cb317ee65"><?= e(t('acl.matrix.sensitive')) ?></span>
                            <?php endif; ?>
                            <div class="muted u-style-ae4019a50d"><?= htmlspecialchars((string)($row['description'] ?? '')) ?></div>
                        </td>
                        <td><?= htmlspecialchars((string)($groups[(string)($row['group'] ?? '')] ?? (string)($row['group'] ?? ''))) ?></td>
                        <?php foreach ($roles as $role): ?>
                            <?php $cell = (array)($cells[(string)$role] ?? []); ?>
                            <td>
                                <?php if (!empty($cell['effective'])): ?>
                                    <span class="pill u-style-dabbd945c2"><?= e(t('acl.matrix.allowed')) ?></span>
                                <?php else: ?>
                                    <span class="pill u-style-97767f33f5"><?= e(t('acl.matrix.denied')) ?></span>
                                <?php endif; ?>
                                <div class="muted u-style-3995822e95">
                                    <?= htmlspecialchars((string)($cell['source'] ?? 'default_deny')) ?>
                                </div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php';
