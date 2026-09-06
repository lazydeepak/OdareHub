<?php
declare(strict_types=1);

use App\Core\Auth;

$editHelper = static function (string $key): string {
    $dict = [
        'page_title' => 'Create Missing Locale File',
        'select_owner' => 'Select Owner',
        'select_locale' => 'Select Locale',
        'create_locale' => 'Create Locale File',
        'preview_title' => 'Preview: Keys to Create',
        'no_owner_selected' => 'Select an owner and a missing locale to preview.',
        'select_owner_prompt' => 'Choose an owner',
        'select_locale_prompt' => 'Choose a locale to create',
        'file_path' => 'Target Path',
        'back_to_dashboard' => 'Back to Dashboard',
        'back_to_localization_studio' => 'Back to Localization Studio',
        'back_to_studio' => 'Back to Studio',
        'not_available' => 'not available',
        'load' => 'Load',
        'create_file' => 'Create File',
        'creating' => 'Creating...',
        'confirm_create' => 'Create a new locale file from English keys? Empty values will be added for all keys.',
        'key_count_label' => 'Keys',
        'no_missing_locales' => 'All locales exist for this owner.',
        'key_heading' => 'Key',
    ];
    return (string)($dict[$key] ?? $key);
};

$owners = isset($createFileOwners) && is_array($createFileOwners) ? $createFileOwners : [];
$flash = isset($createFileFlash) && is_array($createFileFlash) ? $createFileFlash : null;
$selectedOwner = isset($createFileSelectedOwner) && is_string($createFileSelectedOwner) ? $createFileSelectedOwner : '';
$selectedLocale = isset($createFileSelectedLocale) && is_string($createFileSelectedLocale) ? $createFileSelectedLocale : '';
$enPath = isset($createFileEnPath) && is_string($createFileEnPath) ? $createFileEnPath : '';
$previewKeys = isset($createFilePreviewKeys) && is_array($createFilePreviewKeys) ? $createFilePreviewKeys : [];
$previewKeyCount = isset($createFilePreviewKeyCount) ? (int)$createFilePreviewKeyCount : 0;
$previewError = isset($createFilePreviewError) && is_string($createFilePreviewError) ? $createFilePreviewError : '';
$csrf = Auth::csrfToken();

// Determine which locales are missing for the selected owner
$missingLocales = [];
if ($selectedOwner !== '') {
    foreach ($owners as $o) {
        if ($o['key'] === $selectedOwner) {
            $existing = $o['locales'] ?? [];
            foreach (['ja', 'ne'] as $lc) {
                if (!in_array($lc, $existing, true)) {
                    $missingLocales[] = $lc;
                }
            }
            break;
        }
    }
}
?>
<style>
<?php require APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/assets/localization-studio.css'; ?>
.lse-preview-keys { max-height: 400px; overflow-y: auto; font-size: 12px; margin-top: 12px; }
.lse-preview-keys code { display: inline-block; margin: 2px; padding: 2px 6px; background: var(--panel-bg, var(--panel, #fff)); border: 1px solid var(--style-border-soft, #eee); border-radius: 3px; font-size: 11px; }
.lse-preview-info { font-size: 13px; margin: 8px 0; }
</style>

<section class="gui-studio ls-tool">
<div class="lse-wrap">

  <div class="lse-header">
    <h2><?= $editHelper('page_title') ?></h2>
    <a class="btn btn-secondary ls-back-link" href="/apps/studio/tools/localization-studio">&larr; <?= e($editHelper('back_to_dashboard')) ?></a>
  </div>

  <?php if ($flash !== null): ?>
    <?php $flashType = !empty($flash['type']) ? $flash['type'] : 'info'; ?>
    <?php $isSuccess = $flashType === 'success'; ?>
    <div class="lse-flash lse-flash-<?= $flashType ?>">
      <div class="lse-flash-icon"><?= $isSuccess ? '&#10004;' : '&#9888;' ?></div>
      <div class="lse-flash-body">
        <strong><?= e($editHelper($flash['message'] ?? '')) ?></strong>
        <?php if (!empty($flash['details'])): ?>
          <ul class="lse-flash-details">
            <?php foreach ((array)$flash['details'] as $detail): ?>
              <li><?= e((string)$detail) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <form method="get" action="/apps/studio/tools/localization-studio/create-file" class="lse-selectors">
    <label>
      <?= $editHelper('select_owner') ?>
      <select name="owner" onchange="this.form.submit()">
        <option value=""><?= $editHelper('select_owner_prompt') ?></option>
        <?php foreach ($owners as $o):
          $locales = $o['locales'] ?? [];
          $localeHint = implode(', ', array_map('strtoupper', $locales));
        ?>
          <option value="<?= e($o['key']) ?>"<?= $o['key'] === $selectedOwner ? ' selected' : '' ?>>
            <?= e($o['key']) ?> (<?= e($localeHint) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <?= $editHelper('select_locale') ?>
      <select name="locale" onchange="this.form.submit()">
        <option value=""><?= $editHelper('select_locale_prompt') ?></option>
        <?php foreach (['ja', 'ne'] as $lc): ?>
          <?php $isAvailable = in_array($lc, $missingLocales, true); ?>
          <option value="<?= $lc ?>"<?= $lc === $selectedLocale ? ' selected' : '' ?><?= !$isAvailable ? ' disabled' : '' ?>>
            <?= strtoupper($lc) ?><?= !$isAvailable ? ' (' . e($editHelper('not_available')) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if ($selectedOwner !== ''): ?>
      <noscript><button type="submit" class="btn"><?= e($editHelper('load')) ?></button></noscript>
    <?php endif; ?>
  </form>

  <?php if ($selectedOwner === '' || $selectedLocale === ''): ?>
    <div class="lse-empty"><?= $editHelper('no_owner_selected') ?></div>

  <?php elseif ($previewError !== ''): ?>
    <div class="lse-flash lse-flash-error">
      <div class="lse-flash-icon">&#9888;</div>
      <div class="lse-flash-body">
        <strong><?= e($previewError) ?></strong>
      </div>
    </div>

  <?php elseif ($enPath !== '' && $previewKeyCount > 0): ?>
    <div class="lse-path">
      <strong><?= $editHelper('file_path') ?>:</strong>
      <code><?= e(dirname($enPath) . '/' . $selectedLocale . '.php') ?></code>
    </div>

    <h4><?= e($editHelper('preview_title')) ?></h4>
    <div class="lse-preview-info">
      <?= $previewKeyCount ?> <?= e($editHelper('key_count_label')) ?> will be created with empty values.
    </div>

    <div class="lse-preview-keys">
      <?php foreach ($previewKeys as $key): ?>
        <code><?= e($key) ?></code>
      <?php endforeach; ?>
    </div>

    <form method="post" action="/apps/studio/tools/localization-studio/create-file/do" class="lse-create-form" style="margin-top:16px;">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="owner" value="<?= e($selectedOwner) ?>">
      <input type="hidden" name="locale" value="<?= e($selectedLocale) ?>">
      <div class="lse-actions">
        <button type="submit" class="btn btn-danger" id="lse-create-btn"><?= e($editHelper('create_file')) ?></button>
        <a href="/apps/studio/tools/localization-studio/create-file?owner=<?= e($selectedOwner) ?>&locale=<?= e($selectedLocale) ?>" class="btn btn-secondary"><?= $editHelper('cancel') ?></a>
      </div>
    </form>

    <script>
    document.getElementById('lse-create-form')?.addEventListener('submit', function(e) {
      if (!confirm('<?= e($editHelper('confirm_create')) ?>')) {
        e.preventDefault();
        return;
      }
      var btn = document.getElementById('lse-create-btn');
      btn.disabled = true;
      btn.textContent = '<?= e($editHelper('creating')) ?>';
    });
    </script>

  <?php elseif ($selectedOwner !== '' && $selectedLocale !== '' && $missingLocales === []): ?>
    <div class="lse-empty"><?= $editHelper('no_missing_locales') ?></div>
  <?php endif; ?>

  <div class="ls-actions">
    <a class="btn" href="/apps/studio/tools/localization-studio"><?= e($editHelper('back_to_localization_studio')) ?></a>
  </div>
</div>
</section>
