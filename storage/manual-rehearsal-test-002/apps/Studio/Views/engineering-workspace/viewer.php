<?php
declare(strict_types=1);
/* @var array $engineeringWorkspaceView */

$ewData = isset($engineeringWorkspaceView) && is_array($engineeringWorkspaceView) ? $engineeringWorkspaceView : [];

$workspaceKey = (string)($ewData['workspace_key'] ?? '');
$documentType = (string)($ewData['document_type'] ?? 'overview');
$documentLabel = (string)($ewData['document_label'] ?? 'Overview');
$exists = !empty($ewData['exists']);
$renderedContent = (string)($ewData['rendered_content'] ?? '');
$rawContent = (string)($ewData['raw_content'] ?? '');
$draftContent = (string)($ewData['draft_content'] ?? $rawContent);
$fingerprint = (string)($ewData['fingerprint'] ?? '');
$error = (string)($ewData['error'] ?? '');
$errorState = (string)($ewData['error_state'] ?? '');
$sourcePath = (string)($ewData['source_path'] ?? '');
$saveOk = !empty($ewData['save_ok']);
$saveError = (string)($ewData['save_error'] ?? '');
$mode = (string)($ewData['mode'] ?? 'view');
$previewActive = !empty($ewData['preview_active']);
$staleWrite = !empty($ewData['stale_write']);
$documentTabs = isset($ewData['document_tabs']) && is_array($ewData['document_tabs']) ? $ewData['document_tabs'] : [
    'overview' => 'Overview',
    'work' => 'Work',
    'rules' => 'Rules',
    'decisions' => 'Decisions',
];
$parentPage = isset($ewData['parent_page']) && is_array($ewData['parent_page']) ? $ewData['parent_page'] : [];
$parentLabel = trim((string)($parentPage['label'] ?? 'Engineering Workspaces'));
$parentUrl = trim((string)($parentPage['url'] ?? '/apps/studio/tools/engineering-workspaces'));
if ($parentLabel === '') {
    $parentLabel = 'Engineering Workspaces';
}
if ($parentUrl === '') {
    $parentUrl = '/apps/studio/tools/engineering-workspaces';
}
$expectedSections = isset($ewData['expected_sections']) && is_array($ewData['expected_sections']) ? $ewData['expected_sections'] : [];

$workTasks = isset($ewData['work_tasks']) && is_array($ewData['work_tasks']) ? $ewData['work_tasks'] : [];
$toggleSuccess = !empty($ewData['toggle_success']);
$toggleError = (string)($ewData['toggle_error'] ?? '');
$toggleStale = !empty($ewData['toggle_stale']);
$errorTitle = (string)($ewData['error_title'] ?? '');
$errorMessage = (string)($ewData['error_message'] ?? '');

$isWorkDocument = $documentType === 'work';
$isViewMode = $mode === 'view' && $exists;
$hasCheckboxes = $isWorkDocument && $isViewMode && $workTasks !== [];

$csrfToken = (string)($ewData['csrf'] ?? '');
?>

<style>
.ew-viewer * { box-sizing: border-box; margin: 0; padding: 0; }
.ew-viewer { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f7; color: #1c1c1e; line-height: 1.6; }
.ew-viewer .ew-header { background: #fff; border-bottom: 1px solid #d1d1d6; padding: 16px 24px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.ew-viewer .ew-header h1 { font-size: 18px; font-weight: 600; color: #1c1c1e; }
.ew-viewer .ew-header .ew-badge { font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: .5px; padding: 2px 8px; border-radius: 4px; background: #e8e8ed; color: #636366; }
.ew-viewer .ew-header .ew-badge.ew-exists { background: #d1fae5; color: #065f46; }
.ew-viewer .ew-header .ew-badge.ew-missing { background: #fee2e2; color: #991b1b; }
.ew-viewer .ew-nav { background: #fff; display: flex; gap: 4px; padding: 0 24px; border-bottom: 1px solid #d1d1d6; flex-wrap: wrap; }
.ew-viewer .ew-nav a { display: inline-block; padding: 10px 16px; font-size: 13px; font-weight: 500; color: #636366; text-decoration: none; border-bottom: 2px solid transparent; transition: color .15s, border-color .15s; }
.ew-viewer .ew-nav a:hover { color: #1c1c1e; }
.ew-viewer .ew-nav a.ew-active { color: #007aff; border-bottom-color: #007aff; }
.ew-viewer .ew-content { max-width: 960px; margin: 24px auto; padding: 0 24px; }
.ew-viewer .ew-card { background: #fff; border-radius: 10px; border: 1px solid #e5e5ea; padding: 32px; overflow-x: auto; }
.ew-viewer .ew-back { display: inline-block; margin-bottom: 16px; font-size: 13px; color: #007aff; text-decoration: none; cursor: pointer; }
.ew-viewer .ew-back:hover { text-decoration: underline; }
.ew-viewer .ew-source { font-size: 11px; color: #8e8e93; margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e5ea; }
.ew-viewer .ew-section-contract { font-size: 12px; color: #636366; margin-bottom: 16px; padding: 10px 12px; border: 1px solid #e5e5ea; border-radius: 6px; background: #fafafa; }
.ew-viewer .ew-section-contract strong { color: #1c1c1e; }
.ew-viewer .ew-missing-state { text-align: center; padding: 48px 24px; color: #8e8e93; }
.ew-viewer .ew-missing-state h2 { font-size: 18px; font-weight: 600; color: #1c1c1e; margin-bottom: 8px; }
.ew-viewer .ew-missing-state p { font-size: 14px; }
.ew-viewer .ew-error { background: #fff2f0; border: 1px solid #ffccc7; border-radius: 6px; padding: 16px 24px; margin-bottom: 16px; color: #991b1b; font-size: 14px; }
.ew-viewer .ew-success { background: #f0fff4; border: 1px solid #b7eb8f; border-radius: 6px; padding: 16px 24px; margin-bottom: 16px; color: #065f46; font-size: 14px; }
.ew-viewer .ew-actions { display: flex; gap: 8px; margin-bottom: 16px; }
.ew-viewer .ew-edit-switch { display: flex; gap: 8px; margin-bottom: 16px; }
.ew-viewer .ew-edit-switch span { display: inline-block; padding: 6px 12px; border-radius: 999px; border: 1px solid #d1d1d6; font-size: 12px; font-weight: 700; color: #636366; background: #fff; }
.ew-viewer .ew-edit-switch span.is-active { color: #fff; border-color: #007aff; background: #007aff; }
.ew-viewer .ew-preview-panel { margin-bottom: 16px; }
.ew-viewer .ew-form-actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
.ew-viewer .ew-toggle-fallback { margin-top: 16px; padding: 12px 16px; border: 1px solid #e5e5ea; border-radius: 8px; background: #fafafa; font-size: 13px; }
.ew-viewer .ew-toggle-fallback label { display: inline-flex; align-items: center; gap: 8px; }
.ew-viewer .ew-toggle-fallback select { padding: 4px 8px; border: 1px solid #d1d1d6; border-radius: 4px; font-size: 13px; }
.ew-viewer .btn { display: inline-block; padding: 8px 20px; font-size: 13px; font-weight: 500; border: 1px solid #d1d1d6; border-radius: 6px; background: #fff; color: #1c1c1e; cursor: pointer; text-decoration: none; transition: background .15s, border-color .15s; }
.ew-viewer .btn:hover { background: #f5f5f7; }
.ew-viewer .btn-primary { background: #007aff; color: #fff; border-color: #007aff; }
.ew-viewer .btn-primary:hover { background: #0066d6; }
.ew-viewer .btn-success { background: #34c759; color: #fff; border-color: #34c759; }
.ew-viewer .btn-success:hover { background: #28a745; }
.ew-viewer .ew-textarea { width: 100%; min-height: 400px; padding: 16px; font-family: 'SF Mono', 'Menlo', 'Monaco', 'Consolas', monospace; font-size: 13px; line-height: 1.5; border: 1px solid #d1d1d6; border-radius: 8px; resize: vertical; }
.ew-viewer .ew-textarea:focus { outline: none; border-color: #007aff; box-shadow: 0 0 0 3px rgba(0,122,255,.15); }
.ew-viewer .markdown-body { font-size: 15px; }
.ew-viewer .markdown-body h1 { font-size: 24px; font-weight: 700; margin: 24px 0 16px; padding-bottom: 8px; border-bottom: 1px solid #e5e5ea; }
.ew-viewer .markdown-body h2 { font-size: 20px; font-weight: 600; margin: 20px 0 12px; }
.ew-viewer .markdown-body h3 { font-size: 17px; font-weight: 600; margin: 16px 0 8px; }
.ew-viewer .markdown-body h4 { font-size: 15px; font-weight: 600; margin: 12px 0 6px; }
.ew-viewer .markdown-body p { margin: 8px 0; }
.ew-viewer .markdown-body ul, .ew-viewer .markdown-body ol { margin: 8px 0; padding-left: 24px; }
.ew-viewer .markdown-body li { margin: 4px 0; }
.ew-viewer .markdown-body li input[type="checkbox"] { margin-right: 6px; }
.ew-viewer .markdown-body code { font-family: 'SF Mono', 'Menlo', 'Monaco', 'Consolas', monospace; font-size: 13px; background: #f2f2f7; padding: 2px 6px; border-radius: 4px; }
.ew-viewer .markdown-body pre { background: #f2f2f7; border-radius: 6px; padding: 16px; overflow-x: auto; margin: 12px 0; }
.ew-viewer .markdown-body pre code { background: none; padding: 0; }
.ew-viewer .markdown-body blockquote { border-left: 3px solid #d1d1d6; padding-left: 16px; margin: 12px 0; color: #636366; }
.ew-viewer .markdown-body a { color: #007aff; }
.ew-viewer .markdown-body hr { border: none; border-top: 1px solid #e5e5ea; margin: 24px 0; }
.ew-viewer .markdown-body table { border-collapse: collapse; width: 100%; margin: 12px 0; }
.ew-viewer .markdown-body th, .ew-viewer .markdown-body td { border: 1px solid #e5e5ea; padding: 8px 12px; text-align: left; }
.ew-viewer .markdown-body th { background: #f9f9fb; font-weight: 600; }
.ew-viewer .ew-notice { font-size: 12px; color: #8e8e93; margin-top: 8px; }
.ew-viewer .ew-error-page { text-align: center; max-width: 480px; margin: 80px auto; padding: 48px 24px; }
.ew-viewer .ew-error-page h1 { font-size: 24px; font-weight: 600; margin-bottom: 12px; }
.ew-viewer .ew-error-page p { font-size: 14px; color: #636366; margin-bottom: 24px; line-height: 1.5; }
.ew-viewer .ew-error-page .ew-actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
</style>

<div class="ew-viewer">

<?php if ($errorState === 'not_found'): ?>
    <div class="ew-error-page">
        <h1>Not Found</h1>
        <p>The requested page could not be found.</p>
        <div class="ew-actions">
            <a href="<?= e($parentUrl) ?>" class="btn">&larr; Back to <?= e($parentLabel) ?></a>
            <a href="/apps/studio/tools/engineering-workspaces" class="btn">Engineering Workspaces</a>
        </div>
    </div>
<?php elseif ($errorState === 'access_error'): ?>
    <div class="ew-error-page">
        <h1><?= e($errorTitle !== '' ? $errorTitle : 'Engineering Workspace') ?></h1>
        <p><?= e($errorMessage !== '' ? $errorMessage : 'The requested engineering workspace page is unavailable.') ?></p>
        <div class="ew-actions">
            <a href="<?= e($parentUrl) ?>" class="btn">&larr; Back to <?= e($parentLabel) ?></a>
            <a href="/apps/studio/tools/engineering-workspaces" class="btn">Engineering Workspaces</a>
        </div>
    </div>
<?php elseif ($errorState === 'workspace_unavailable'): ?>
    <div class="ew-error-page">
        <h1>Engineering Workspace Unavailable</h1>
        <p>The requested workspace document is not available. It may not be initialized yet, or the link may be outdated.</p>
        <div class="ew-actions">
            <a href="<?= e($parentUrl) ?>" class="btn">&larr; Back to <?= e($parentLabel) ?></a>
            <a href="/apps/studio/tools/engineering-workspaces" class="btn">Engineering Workspaces</a>
        </div>
    </div>
<?php elseif ($errorState === 'document_unavailable'): ?>
    <div class="ew-error-page">
        <h1>Document Unavailable</h1>
        <p><?= e($workspaceKey) ?> exists, but its <?= e($documentLabel) ?> document is unavailable.</p>
        <div class="ew-actions">
            <a href="/apps/studio/engineering-workspaces?workspace_key=<?= e(rawurlencode($workspaceKey)) ?>&document=overview" class="btn">Back to Workspace Overview</a>
            <a href="<?= e($parentUrl) ?>" class="btn">&larr; Back to <?= e($parentLabel) ?></a>
        </div>
    </div>
<?php else: ?>

<header class="ew-header">
    <h1><?= e($workspaceKey) ?></h1>
    <span class="ew-badge <?= $exists ? 'ew-exists' : 'ew-missing' ?>"><?= e($documentLabel) ?></span>
</header>

<nav class="ew-nav">
    <?php foreach ($documentTabs as $tabKey => $tabLabel): ?>
        <?php $tabKey = (string)$tabKey; ?>
        <a href="/apps/studio/engineering-workspaces?workspace_key=<?= e(rawurlencode($workspaceKey)) ?>&document=<?= e($tabKey) ?>" class="<?= $documentType === $tabKey ? 'ew-active' : '' ?>"><?= e((string)$tabLabel) ?></a>
    <?php endforeach; ?>
</nav>

<div class="ew-content">
    <a href="<?= e($parentUrl) ?>" class="ew-back">&larr; Back to <?= e($parentLabel) ?></a>

    <?php if ($toggleSuccess): ?>
        <div class="ew-success">Checkbox updated. The stored document has been modified.</div>
    <?php endif; ?>

    <?php if ($toggleError !== ''): ?>
        <div class="ew-error"><?= e($toggleError) ?></div>
        <?php if ($toggleStale): ?>
            <div class="ew-actions">
                <a href="/apps/studio/engineering-workspaces?workspace_key=<?= e(rawurlencode($workspaceKey)) ?>&document=<?= e($documentType) ?>" class="btn">Reload Current Document</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($saveOk): ?>
        <div class="ew-success">Saved successfully. Showing rendered result below.</div>
    <?php endif; ?>

    <?php if ($saveError !== ''): ?>
        <div class="ew-error"><?= e($saveError) ?></div>
        <?php if ($staleWrite): ?>
            <div class="ew-actions">
                <a href="/apps/studio/engineering-workspaces?workspace_key=<?= e(rawurlencode($workspaceKey)) ?>&document=<?= e($documentType) ?>&mode=edit" class="btn">Reload Current Document</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($error !== '' && !$saveError && !$toggleError): ?>
        <div class="ew-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($mode === 'edit' && $exists): ?>
        <div class="ew-edit-switch" aria-label="Editor mode">
            <span class="<?= $previewActive ? 'is-active' : '' ?>">Preview</span>
            <span class="<?= !$previewActive ? 'is-active' : '' ?>">Edit</span>
        </div>

        <?php if ($previewActive): ?>
            <div class="ew-card ew-preview-panel">
                <div class="markdown-body"><?= $renderedContent ?></div>
            </div>
        <?php endif; ?>

        <form method="post" action="/apps/studio/engineering-workspaces/save">
            <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="workspace_key" value="<?= e($workspaceKey) ?>">
            <input type="hidden" name="document" value="<?= e($documentType) ?>">
            <input type="hidden" name="fingerprint" value="<?= e($fingerprint) ?>">
            <textarea name="markdown" class="ew-textarea"><?= e($draftContent) ?></textarea>
            <div class="ew-form-actions">
                <button type="submit" class="btn" formaction="/apps/studio/engineering-workspaces/preview">Preview</button>
                <a href="/apps/studio/engineering-workspaces?workspace_key=<?= e(rawurlencode($workspaceKey)) ?>&document=<?= e($documentType) ?>" class="btn">Cancel</a>
                <button type="submit" class="btn btn-success">Save</button>
            </div>
        </form>
    <?php elseif (!$exists): ?>
        <div class="ew-card ew-missing-state">
            <h2>Not initialized</h2>
            <p>The <strong><?= e($documentLabel) ?></strong> file for <strong><?= e($workspaceKey) ?></strong> has not been created yet.</p>
        </div>
    <?php else: ?>
        <div class="ew-actions">
            <a href="/apps/studio/engineering-workspaces?workspace_key=<?= e(rawurlencode($workspaceKey)) ?>&document=<?= e($documentType) ?>&mode=edit" class="btn btn-primary">Edit</a>
        </div>
        <?php if ($expectedSections !== []): ?>
            <div class="ew-section-contract">
                <strong>Expected sections:</strong>
                <?= e(implode(' &#183; ', array_map('strval', $expectedSections))) ?>
            </div>
        <?php endif; ?>
        <div class="ew-card">
            <div class="markdown-body"<?= $hasCheckboxes ? ' data-ew-checklist="1"' : '' ?>><?= $renderedContent ?></div>
            <?php if ($sourcePath !== ''): ?>
                <div class="ew-source">Source: <?= e($sourcePath) ?></div>
            <?php endif; ?>
        </div>
        <?php if ($hasCheckboxes): ?>
            <div class="ew-notice">Click a checkbox to toggle it. Changes are saved immediately.</div>

            <details class="ew-toggle-fallback">
                <summary>Toggle task (no-JavaScript)</summary>
                <form method="post" action="/apps/studio/engineering-workspaces/toggle-work-item" style="margin-top:8px;">
                    <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="workspace_key" value="<?= e($workspaceKey) ?>">
                    <input type="hidden" name="document" value="work">
                    <input type="hidden" name="fingerprint" value="<?= e($fingerprint) ?>">
                    <label>
                        Task:
                        <select name="task_ordinal">
                            <?php foreach ($workTasks as $t): ?>
                                <option value="<?= e((string)$t['ordinal']) ?>">
                                    <?= e((string)$t['ordinal']) ?>: <?= e(mb_substr($t['task_text'], 0, 80)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label style="margin-left:8px;">
                        <input type="checkbox" name="target_state" value="1">
                        Mark as checked
                    </label>
                    <button type="submit" class="btn" style="margin-left:8px;">Toggle</button>
                </form>
            </details>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($hasCheckboxes): ?>
<script>
(function(){
    var container = document.querySelector('.ew-viewer .markdown-body[data-ew-checklist="1"]');
    if (!container) return;

    var tasks = <?= json_encode(array_map(function($t) {
        return [
            'ordinal' => $t['ordinal'],
            'checked' => !empty($t['checked']),
            'line_hash' => $t['line_hash'],
        ];
    }, $workTasks)) ?>;

    var checkboxes = container.querySelectorAll('input[type="checkbox"]');
    var fingerprint = <?= json_encode($fingerprint) ?>;
    var workspaceKey = <?= json_encode($workspaceKey) ?>;
    var csrf = <?= json_encode($csrfToken) ?>;
    var toggleUrl = '/apps/studio/engineering-workspaces/toggle-work-item';

    var taskIndex = 0;
    checkboxes.forEach(function(cb, idx) {
        if (cb.disabled && taskIndex < tasks.length) {
            var task = tasks[taskIndex];
            taskIndex++;

            var newCb = document.createElement('input');
            newCb.type = 'checkbox';
            newCb.checked = cb.checked;
            newCb.disabled = false;
            newCb.setAttribute('data-ew-ordinal', task.ordinal);
            newCb.setAttribute('data-ew-line-hash', task.line_hash);

            var inFlight = false;

            newCb.addEventListener('change', function() {
                if (inFlight) return;
                inFlight = true;
                newCb.disabled = true;

                var form = document.createElement('form');
                form.method = 'POST';
                form.action = toggleUrl;
                form.style.display = 'none';

                var fields = {
                    'csrf': csrf,
                    'workspace_key': workspaceKey,
                    'document': 'work',
                    'task_ordinal': String(task.ordinal),
                    'target_state': newCb.checked ? '1' : '0',
                    'fingerprint': fingerprint,
                    'task_line_hash': task.line_hash,
                };

                for (var key in fields) {
                    if (!fields.hasOwnProperty(key)) continue;
                    var inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = key;
                    inp.value = fields[key];
                    form.appendChild(inp);
                }

                document.body.appendChild(form);
                form.submit();
            });

            cb.parentNode.replaceChild(newCb, cb);
        }
    });
})();
</script>
<?php endif; ?>
<?php endif; ?>

</div>
