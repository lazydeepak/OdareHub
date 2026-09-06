<?php
$allTokens = isset($tieAllTokens) && is_array($tieAllTokens) ? $tieAllTokens : [];
$catalogGrouped = isset($tieCatalogGrouped) && is_array($tieCatalogGrouped) ? $tieCatalogGrouped : [];
$runtimeGrouped = isset($tieRuntimeGrouped) && is_array($tieRuntimeGrouped) ? $tieRuntimeGrouped : [];
$selectedToken = isset($tieSelectedToken) && is_string($tieSelectedToken) ? $tieSelectedToken : '';
$result = isset($tieExploreResult) && is_array($tieExploreResult) ? $tieExploreResult : null;

$tie = static function (string $key): string {
    $dict = [
        'en' => [
            'page_title' => 'Token Impact Explorer',
            'page_subtitle' => 'Discover where CSS tokens are defined and consumed across the application.',
            'search_placeholder' => 'Search tokens...',
            'catalog_section' => 'Socket Catalog',
            'runtime_section' => 'Runtime Tokens',
            'no_results' => 'No tokens found matching your search.',
            'select_prompt' => 'Select a token from the list or search above to see its impact.',
            'total_occurrences' => 'Total occurrences',
            'definitions' => 'Definitions',
            'references' => 'References',
            'owners' => 'Owners',
            'files' => 'Files',
            'no_definitions' => 'No CSS definitions found for this token.',
            'no_references' => 'No runtime references found for this token.',
            'source' => 'Source',
            'catalog' => 'catalog',
            'runtime' => 'runtime',
            'catalog_metadata' => 'Catalog Metadata',
            'socket_id' => 'Socket ID',
            'category' => 'Category',
            'value_type' => 'Value Type',
            'scope' => 'Scope',
            'owner' => 'Owner',
            'not_consumed' => 'Not yet consumed',
            'token_line' => 'Line',
            'back_to_search' => 'Back to search',
            'total_tokens' => 'tokens',
            'token_in' => 'Token',
            'showing_results_for' => 'Results for',
        ],
    ];
    $lang = function_exists('current_lang') ? current_lang() : 'en';
    $set = isset($dict[$lang]) && is_array($dict[$lang]) ? $dict[$lang] : $dict['en'];
    return (string)($set[$key] ?? $key);
};
?>
<div class="tie-container">
    <div class="tie-sidebar">
        <div class="tie-header">
            <h2 class="tie-title"><?= $tie('page_title') ?></h2>
            <p class="tie-subtitle"><?= $tie('page_subtitle') ?></p>
        </div>
        <div class="tie-search">
            <input type="text" class="tie-search-input" id="tieSearch" placeholder="<?= $tie('search_placeholder') ?>" autocomplete="off">
        </div>
        <div class="tie-token-list" id="tieTokenList">
            <?php if ($catalogGrouped !== []): ?>
                <?php foreach ($catalogGrouped as $catalogId => $group): ?>
                    <div class="tie-group" data-catalog="<?= $tie($catalogId) ?>">
                        <div class="tie-group-header">
                            <span class="tie-group-label"><?= $tie('catalog_section') ?>: <?= $tie($catalogId) ?></span>
                            <span class="tie-group-count"><?= count($group['tokens']) ?> <?= $tie('total_tokens') ?></span>
                        </div>
                        <?php foreach ($group['tokens'] as $token): ?>
                            <?php
                            $tName = $token['token_name'] ?? '';
                            $isActive = $selectedToken !== '' && ($tName === $selectedToken || $token['socket_id'] === $selectedToken);
                            ?>
                            <a href="?token=<?= rawurlencode($tName) ?>" class="tie-token-item <?= $isActive ? 'tie-token-active' : '' ?>" data-token="<?= $tie($tName) ?>" data-label="<?= $tie($token['label'] ?? '') ?>" data-catalog="<?= $tie($catalogId) ?>">
                                <span class="tie-token-name"><?= $tie($tName) ?></span>
                                <span class="tie-token-label"><?= $tie($token['label'] ?? '') ?></span>
                                <span class="tie-token-badge tie-badge-catalog"><?= $tie('catalog') ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($runtimeGrouped !== []): ?>
                <div class="tie-group">
                    <div class="tie-group-header">
                        <span class="tie-group-label"><?= $tie('runtime_section') ?></span>
                        <span class="tie-group-count"><?= count($runtimeGrouped) ?> <?= $tie('total_tokens') ?></span>
                    </div>
                    <?php foreach ($runtimeGrouped as $token): ?>
                        <?php
                        $tName = $token['token_name'] ?? '';
                        $isActive = $selectedToken !== '' && $tName === $selectedToken;
                        ?>
                        <a href="?token=<?= rawurlencode($tName) ?>" class="tie-token-item <?= $isActive ? 'tie-token-active' : '' ?>" data-token="<?= $tie($tName) ?>">
                            <span class="tie-token-name"><?= $tie($tName) ?></span>
                            <span class="tie-token-badge tie-badge-runtime"><?= $tie('runtime') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($allTokens === []): ?>
                <div class="tie-empty"><?= $tie('no_results') ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="tie-main">
        <?php if ($result !== null): ?>
            <div class="tie-result">
                <div class="tie-result-header">
                    <a href="/apps/studio/tools/customization-studio/diagnose/token-impact-explorer" class="tie-back-link">&larr; <?= $tie('back_to_search') ?></a>
                    <h2 class="tie-result-title"><?= $tie('showing_results_for') ?> <code><?= $tie($result['token_name']) ?></code></h2>
                </div>

                <?php if ($result['catalog_match'] !== null): ?>
                    <div class="tie-section tie-catalog-section">
                        <h3 class="tie-section-title"><?= $tie('catalog_metadata') ?></h3>
                        <table class="tie-metadata-table">
                            <?php if (!empty($result['catalog_match']['socket_id'])): ?>
                                <tr><td class="tie-meta-label"><?= $tie('socket_id') ?></td><td><code><?= $tie($result['catalog_match']['socket_id']) ?></code></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($result['catalog_match']['label'])): ?>
                                <tr><td class="tie-meta-label"><?= $tie('token_in') ?></td><td><?= $tie($result['catalog_match']['label']) ?></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($result['catalog_match']['description'])): ?>
                                <tr><td class="tie-meta-label"><?= $tie('scope') ?></td><td><?= $tie($result['catalog_match']['description']) ?></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($result['catalog_match']['category'])): ?>
                                <tr><td class="tie-meta-label"><?= $tie('category') ?></td><td><?= $tie($result['catalog_match']['category']) ?></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($result['catalog_match']['value_type'])): ?>
                                <tr><td class="tie-meta-label"><?= $tie('value_type') ?></td><td><?= $tie($result['catalog_match']['value_type']) ?></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($result['catalog_match']['scope'])): ?>
                                <tr><td class="tie-meta-label"><?= $tie('scope') ?></td><td><code><?= $tie($result['catalog_match']['scope']) ?></code></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($result['catalog_match']['owner'])): ?>
                                <tr><td class="tie-meta-label"><?= $tie('owner') ?></td><td><?= $tie($result['catalog_match']['owner']) ?></td></tr>
                            <?php endif; ?>
                            <?php
                            $consumption = $result['catalog_match']['runtime_consumption'] ?? '';
                            if ($consumption !== '' && str_contains($consumption, 'not_consumed')): ?>
                                <tr><td class="tie-meta-label"><?= $tie('source') ?></td><td><span class="tie-badge-catalog"><?= $tie('not_consumed') ?></span></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="tie-section">
                    <h3 class="tie-section-title"><?= $tie('total_occurrences') ?>: <?= $result['total_occurrences'] ?></h3>
                </div>

                <?php if ($result['owners'] !== []): ?>
                    <div class="tie-section">
                        <h3 class="tie-section-title"><?= $tie('owners') ?></h3>
                        <?php
                        $maxCount = max(array_column($result['owners'], 'count'));
                        ?>
                        <div class="tie-owner-chart">
                            <?php foreach ($result['owners'] as $owner): ?>
                                <div class="tie-owner-row">
                                    <span class="tie-owner-name"><?= $tie($owner['name']) ?></span>
                                    <span class="tie-owner-count"><?= $owner['count'] ?>x</span>
                                    <div class="tie-owner-bar-wrap">
                                        <div class="tie-owner-bar" style="width: <?= $maxCount > 0 ? round($owner['count'] / $maxCount * 100) : 0 ?>%"></div>
                                    </div>
                                    <span class="tie-owner-files"><?= count($owner['files']) ?> <?= $tie('files') ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($result['definitions'] !== []): ?>
                    <div class="tie-section">
                        <h3 class="tie-section-title"><?= $tie('definitions') ?> (<?= count($result['definitions']) ?>)</h3>
                        <div class="tie-file-list">
                            <?php foreach ($result['definitions'] as $def): ?>
                                <div class="tie-file-entry">
                                    <span class="tie-file-owner"><?= $tie($def['owner']) ?></span>
                                    <span class="tie-file-path"><?= $tie($def['file']) ?></span>
                                    <span class="tie-file-line"><?= $tie('token_line') ?> <?= $def['line'] ?></span>
                                    <code class="tie-file-snippet"><?= $tie($def['value']) ?></code>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="tie-section">
                        <p class="tie-no-data"><?= $tie('no_definitions') ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($result['references'] !== []): ?>
                    <div class="tie-section">
                        <h3 class="tie-section-title"><?= $tie('references') ?> (<?= count($result['references']) ?>)</h3>
                        <div class="tie-file-list">
                            <?php foreach ($result['references'] as $ref): ?>
                                <div class="tie-file-entry">
                                    <span class="tie-file-owner"><?= $tie($ref['owner']) ?></span>
                                    <span class="tie-file-path"><?= $tie($ref['file']) ?></span>
                                    <span class="tie-file-line"><?= $tie('token_line') ?> <?= $ref['line'] ?></span>
                                    <code class="tie-file-snippet"><?= $tie($ref['value']) ?></code>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="tie-section">
                        <p class="tie-no-data"><?= $tie('no_references') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="tie-placeholder">
                <div class="tie-placeholder-icon">&#x1F50D;</div>
                <h3><?= $tie('select_prompt') ?></h3>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.tie-container{display:flex;height:calc(100vh - 120px);gap:0;background:var(--card-surface);border:1px solid var(--style-border-soft);border-radius:14px;overflow:hidden}
.tie-sidebar{width:340px;min-width:340px;border-right:1px solid var(--style-border-soft);display:flex;flex-direction:column;background:var(--style-subtle-bg)}
.tie-header{padding:16px 16px 8px;border-bottom:1px solid var(--style-border-soft)}
.tie-title{margin:0;font-size:16px;font-weight:700;color:var(--text)}
.tie-subtitle{margin:4px 0 0;font-size:12px;color:var(--muted);line-height:1.4}
.tie-search{padding:8px 16px;border-bottom:1px solid var(--style-border-soft)}
.tie-search-input{width:100%;padding:8px 10px;border:1px solid var(--style-border-soft);border-radius:8px;background:var(--card-surface);color:var(--text);font-size:13px;outline:none;box-sizing:border-box}
.tie-search-input:focus{border-color:var(--style-active-border);box-shadow:0 0 0 2px var(--style-active-bg)}
.tie-token-list{flex:1;overflow-y:auto;padding:4px 0}
.tie-group{margin-bottom:4px}
.tie-group-header{display:flex;justify-content:space-between;align-items:center;padding:6px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
.tie-group-count{font-size:10px;color:var(--muted)}
.tie-token-item{display:flex;align-items:center;gap:6px;padding:6px 16px;text-decoration:none;border-left:3px solid transparent;transition:background .15s}
.tie-token-item:hover{background:var(--style-subtle-bg-hover)}
.tie-token-active{background:var(--style-active-bg);border-left-color:var(--accent)}
.tie-token-name{font-family:monospace;font-size:12px;color:var(--text);flex-shrink:0}
.tie-token-label{font-size:11px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.tie-token-badge{font-size:9px;padding:1px 5px;border-radius:4px;font-weight:600;flex-shrink:0}
.tie-badge-catalog{background:var(--style-active-bg);color:var(--accent)}
.tie-badge-runtime{background:var(--style-subtle-bg-hover);color:var(--muted)}
.tie-empty{padding:24px 16px;text-align:center;color:var(--muted);font-size:13px}
.tie-main{flex:1;overflow-y:auto;padding:24px}
.tie-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--muted);text-align:center}
.tie-placeholder-icon{font-size:48px;margin-bottom:12px;opacity:.4}
.tie-result{max-width:900px}
.tie-result-header{margin-bottom:20px}
.tie-back-link{display:inline-block;font-size:12px;color:var(--accent);text-decoration:none;margin-bottom:8px}
.tie-back-link:hover{text-decoration:underline}
.tie-result-title{margin:0;font-size:18px;color:var(--text);display:flex;align-items:center;gap:8px}
.tie-result-title code{font-size:14px;background:var(--style-subtle-bg);padding:2px 8px;border-radius:6px;border:1px solid var(--style-border-soft)}
.tie-section{margin-bottom:20px}
.tie-section-title{margin:0 0 10px;font-size:14px;font-weight:700;color:var(--text)}
.tie-metadata-table{width:100%;border-collapse:collapse;font-size:13px}
.tie-metadata-table td{padding:6px 10px;border-bottom:1px solid var(--style-border-soft);vertical-align:top}
.tie-meta-label{width:120px;font-weight:600;color:var(--muted)}
.tie-owner-chart{display:flex;flex-direction:column;gap:6px}
.tie-owner-row{display:flex;align-items:center;gap:8px;font-size:13px}
.tie-owner-name{width:140px;font-weight:600;color:var(--text);flex-shrink:0}
.tie-owner-count{width:40px;text-align:right;color:var(--accent);font-weight:700;font-family:monospace;flex-shrink:0}
.tie-owner-bar-wrap{flex:1;height:18px;background:var(--style-subtle-bg);border-radius:4px;overflow:hidden}
.tie-owner-bar{height:100%;background:var(--accent);opacity:.4;border-radius:4px;transition:width .3s}
.tie-owner-files{width:50px;text-align:right;font-size:11px;color:var(--muted);flex-shrink:0}
.tie-file-list{display:flex;flex-direction:column;gap:4px}
.tie-file-entry{display:flex;flex-wrap:wrap;align-items:center;gap:6px;padding:6px 10px;background:var(--style-subtle-bg);border-radius:8px;font-size:12px}
.tie-file-owner{font-weight:600;color:var(--accent);font-size:11px;padding:1px 6px;background:var(--style-active-bg);border-radius:4px}
.tie-file-path{color:var(--text);font-family:monospace;font-size:11px}
.tie-file-line{color:var(--muted);font-size:11px}
.tie-file-snippet{width:100%;font-size:11px;color:var(--text-muted);padding:4px 6px;background:var(--card-surface);border-radius:4px;overflow-x:auto;white-space:pre-wrap;word-break:break-all}
.tie-no-data{font-size:13px;color:var(--muted);font-style:italic}
@media(max-width:768px){.tie-container{flex-direction:column;height:auto;border-radius:0;border:none}.tie-sidebar{width:100%;min-width:auto;border-right:none;border-bottom:1px solid var(--style-border-soft);max-height:40vh}.tie-main{padding:16px}}
</style>

<script>
(function(){
    var search = document.getElementById('tieSearch');
    if (!search) return;
    var items = document.querySelectorAll('.tie-token-item');
    var groups = document.querySelectorAll('.tie-group');
    search.addEventListener('input', function() {
        var q = search.value.toLowerCase().trim();
        items.forEach(function(item) {
            var name = (item.getAttribute('data-token') || '').toLowerCase();
            var label = (item.getAttribute('data-label') || '').toLowerCase();
            var catalog = (item.getAttribute('data-catalog') || '').toLowerCase();
            var match = q === '' || name.indexOf(q) !== -1 || label.indexOf(q) !== -1 || catalog.indexOf(q) !== -1;
            item.style.display = match ? '' : 'none';
        });
        groups.forEach(function(group) {
            var visible = Array.from(group.querySelectorAll('.tie-token-item')).some(function(item) { return item.style.display !== 'none'; });
            group.style.display = visible ? '' : 'none';
        });
    });
})();
</script>
