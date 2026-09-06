<?php
declare(strict_types=1);

$workspaceQuery = static function (string $target) use ($scope, $ownerKey): string {
    $params = ['scope' => $scope, 'workspace' => $target];
    if ($ownerKey !== '') {
        $params['owner'] = $ownerKey;
    }
    return '/apps/studio/tools/customization-studio/diagnose/style-compliance?' . http_build_query($params);
};
?>
<nav class="sc-workspace-tabs" aria-label="<?= e($sc('workspace_tabs_label')) ?>">
    <a href="<?= e($workspaceQuery('overview')) ?>" <?= $workspace === 'overview' ? 'aria-current="page"' : '' ?>><?= e($sc('workspace_overview')) ?></a>
    <a href="<?= e($workspaceQuery('shell-inventory')) ?>" <?= $workspace === 'shell-inventory' ? 'aria-current="page"' : '' ?>><?= e($sc('workspace_shell_inventory')) ?></a>
</nav>
