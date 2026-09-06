<?php
declare(strict_types=1);
?>
<!-- Scope selector -->
<section class="sc-scope-strip gs-tool-scope-strip" aria-label="Scan scope">
    <form method="get" action="<?= e($selfPath) ?>" class="sc-scope-form gs-tool-scope-form" id="scScopeForm">
        <input type="hidden" name="workspace" id="scWorkspace" value="<?= e($workspace) ?>">
        <label>
            <span><?= e($sc('scope_selector_title')) ?></span>
            <select name="scope" id="scScopeSelect">
                <option value="all_owners" <?= $scope === 'all_owners' ? 'selected' : '' ?>><?= e($sc('scope_all_owners')) ?></option>
                <option value="owner" <?= $scope === 'owner' ? 'selected' : '' ?>><?= e($sc('scope_owner')) ?></option>
                <option value="shell" <?= $scope === 'shell' ? 'selected' : '' ?>><?= e($sc('scope_shell')) ?></option>
                <option value="theme" <?= $scope === 'theme' ? 'selected' : '' ?>><?= e($sc('scope_theme')) ?></option>
            </select>
        </label>
        <label id="scOwnerWrap" class="<?= $scope === 'owner' ? '' : 'sc-owner-hidden' ?>">
            <span><?= e($sc('owner_label')) ?></span>
            <select name="owner">
                <?php foreach ($owners as $own): ?>
                    <?php $ok = (string)($own['owner_key'] ?? ''); ?>
                    <option value="<?= e($ok) ?>" <?= ($ok !== '' && $ok === $ownerKey) ? 'selected' : '' ?>>
                        <?= e($ok) ?> (<?= e((string)($own['owner_type'] ?? '')) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <input type="hidden" name="csrf" id="scCsrf" value="<?= e($csrfToken) ?>">
    </form>
    <div class="sc-scope-hint gs-tool-scope-hint" data-sc-cockpit-scope-hint><?= e($sc('scope_label_' . $scope)) ?></div>
</section>
