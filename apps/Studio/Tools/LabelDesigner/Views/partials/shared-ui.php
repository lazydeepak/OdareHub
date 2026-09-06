<?php
$renderProgressCard = static function () use ($ld, $wsProgress): void {
    $steps = [
        ['key' => 'context', 'done' => $wsProgress['has_context']],
        ['key' => 'template', 'done' => $wsProgress['has_template']],
        ['key' => 'rule', 'done' => $wsProgress['has_rules']],
        ['key' => 'preview', 'done' => $wsProgress['has_preview']],
    ];
    ?><div class="ld-progress-card"><div class="ld-progress-title"><?= $ld('progress_title') ?></div><div class="ld-progress-steps"><?php foreach ($steps as $step): ?><span class="ld-progress-step ld-progress-step-<?= $step['done'] ? 'done' : 'pending' ?>"><?= $step['done'] ? '&#10003;' : '&#10007;' ?> <?= $ld('progress_' . $step['key']) ?></span><?php endforeach; ?></div><div class="ld-progress-status"><?php if ($wsProgress['all_complete']): ?><strong><?= $ld('progress_ready') ?></strong><?php elseif ($wsProgress['none_started']): ?><strong><?= $ld('progress_not_started') ?></strong><?php else: ?><strong><?= $ld('progress_in_progress') ?></strong><?php endif; ?></div></div><?php
};
$workspaceLabel = static function (string $ws): string {
    $labels = [
        'overview' => 'Overview',
        'build' => 'Build Label',
        'rules' => 'Rules',
        'preview' => 'Preview',
        'governance' => 'Governance',
    ];
    return $labels[$ws] ?? 'Overview';
};
$buildUrl = static function (array $overrides = []) use ($activeWorkspace, $selectedDataSourceOwnerKey): string {
    $params = [];
    $ws = $overrides['workspace'] ?? $activeWorkspace;
    if ($ws !== 'overview') {
        $params['workspace'] = $ws;
    }
    $owner = $overrides['owner'] ?? $selectedDataSourceOwnerKey;
    if ($owner !== '') {
        $params['owner'] = $owner;
    }
    return $params !== [] ? '?' . http_build_query($params) : '';
};
