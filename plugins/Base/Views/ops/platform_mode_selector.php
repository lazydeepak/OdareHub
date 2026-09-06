<?php
/**
 * Platform Mode Selector Component
 *
 * Shows current platform mode and allows switching between modes.
 * Canonical Platform Mode workspace selector.
 *
 * Consolidated, no duplication of mode info.
 * Variables expected:
 * - $currentMode: Current platform mode (string)
 * - $availableModes: Array of available modes with descriptions (array)
 */

$currentMode = (string)($currentMode ?? '');
$availableModes = is_array($availableModes ?? null) ? $availableModes : [];
$platformModeReturnTo = trim((string)($platformModeReturnTo ?? '/admin/system-tools/platform-mode'));
$platformModeLocked = \App\Services\PlatformModeService::isModeSwitchLocked();

if ($currentMode === '' || $availableModes === []) {
    echo '<!-- Platform Mode Selector: Missing data -->';
    return;
}
?>

<div class="platform-mode-selector-section">
    <div class="card u-style-ead0b6f702">
    <div class="section-head u-style-da12f2858b">
        <div class="ui-block">
            <h3 class="u-style-1169661891"><?= e(t('admin.platform_mode.title')) ?></h3>
            <div class="muted u-style-fe7b4979fe"><?= e(t('admin.platform_mode.currently')) ?>: <strong><?= e(t('admin.platform_mode.mode.' . $currentMode)) ?></strong></div>
        </div>
    </div>

    <form method="post" action="/ops/platform-mode-switch">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="return_to" value="<?= e($platformModeReturnTo) ?>">

        <div class="detail-grid u-style-e2f405ef3d">
            <?php foreach ($availableModes as $mode => $info): ?>
                <?php
                    $label = t('admin.platform_mode.mode.' . $mode);
                    $description = t('admin.platform_mode.mode.' . $mode . '_description');
                    $isSelected = $mode === $currentMode;
                    $inputId = 'platform_mode_' . $mode;
                ?>
                <div class="detail-item">
                    <input
                        type="radio"
                        id="<?= e($inputId) ?>"
                        name="platform_mode"
                        value="<?= e($mode) ?>"
                        <?= $isSelected ? 'checked' : '' ?>
                        <?= $platformModeLocked ? 'disabled' : '' ?>
                    >
                    <label class="platform-mode-option-label" for="<?= e($inputId) ?>">
                        <div class="u-style-e3ec02ace9"><?= e($label) ?></div>
                        <div class="muted u-style-11adacbf51"><?= e($description) ?></div>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row u-style-d6f2af6e0a">
            <button type="submit" class="btn ok"<?= $platformModeLocked ? ' disabled' : '' ?>>
                <?= e(t('admin.platform_mode.update')) ?>
            </button>
        </div>

        <?php if ($platformModeLocked): ?>
          <div class="notice-warn"><?= e(t('admin.platform_mode.locked_notice')) ?></div>
        <?php endif; ?>

        <div class="notice-warn u-style-4b378c0c6a">
            <strong><?= e(t('admin.platform_mode.note_label')) ?>:</strong> <?= e(t('admin.platform_mode.note')) ?>
        </div>
    </form>
    </div>
</div>
