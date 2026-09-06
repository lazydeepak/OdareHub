<?php
declare(strict_types=1);

require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';

$catalog = array_values((array)($catalog ?? []));
$groups = (array)($groups ?? []);

$byGroup = [];
foreach ($catalog as $row) {
    $g = (string)($row['group'] ?? 'extensions');
    $byGroup[$g][] = $row;
}
?>

<div class="card">
    <h2 class="u-style-759e41d83d"><?= e(t('acl.permissions.title')) ?></h2>
    <div class="muted"><?= e(t('acl.permissions.subtitle')) ?></div>
    <div class="row u-style-242e13ddea">
        <a class="btn" href="/admin/acl"><?= e(t('acl.permissions.overview_link')) ?></a>
        <a class="btn" href="/admin/acl/matrix"><?= e(t('acl.permissions.matrix_link')) ?></a>
    </div>
</div>

<?php foreach ($groups as $groupKey => $groupLabel): ?>
    <?php $rows = array_values((array)($byGroup[(string)$groupKey] ?? [])); ?>
    <?php if (empty($rows)) continue; ?>
    <div class="card">
        <h3 class="u-style-759e41d83d"><?= htmlspecialchars((string)$groupLabel) ?></h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?= e(t('acl.permissions.permission')) ?></th>
                        <th><?= e(t('acl.permissions.description')) ?></th>
                        <th><?= e(t('acl.permissions.sensitivity')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><code><?= htmlspecialchars((string)($row['perm_key'] ?? '')) ?></code></td>
                            <td><?= htmlspecialchars((string)($row['description'] ?? '')) ?></td>
                            <td>
                                <?php if (!empty($row['sensitive'])): ?>
                                    <span class="pill u-style-511e3f441d"><?= e(t('acl.permissions.sensitive')) ?></span>
                                <?php else: ?>
                                    <span class="pill"><?= e(t('acl.permissions.standard')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php';
