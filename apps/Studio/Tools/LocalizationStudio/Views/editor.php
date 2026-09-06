<?php
declare(strict_types=1);

use App\Core\Auth;

$editHelper = static function (string $key): string {
    $dict = [
        'page_title' => 'Edit Language Files',
        'select_owner' => 'Select Owner',
        'select_locale' => 'Select Locale',
        'file_path' => 'File Path',
        'key_column' => 'Key',
        'current_value' => 'Current Value',
        'new_value' => 'New Value',
        'reference_value' => 'English Reference',
        'add_key' => 'Add Key',
        'add_value' => 'Add Value',
        'add_row' => 'Add Row',
        'remove' => 'Remove',
        'preview_diff' => 'Preview Diff',
        'apply_changes' => 'Apply Changes',
        'cancel' => 'Cancel',
        'saving' => 'Saving...',
        'confirm_apply' => 'Are you sure you want to apply these changes? The existing file will be backed up automatically.',
        'no_changes' => 'No changes detected.',
        'save_success' => 'Changes applied successfully.',
        'save_error' => 'Failed to apply changes.',
        'snapshot_created' => 'Backup created.',
        'post_diagnostics_ok' => 'Post-apply diagnostics passed.',
        'post_diagnostics_fail' => 'Post-apply diagnostics failed.',
        'validation_error' => 'Validation errors.',
        'no_owner_selected' => 'Select an owner and locale to start editing.',
        'continue_editing' => 'Continue editing',
        'select_owner_prompt' => 'Choose an owner',
        'select_locale_prompt' => 'Choose a locale',
        'edit_instructions' => 'Edit values directly in the table. Add new keys using the form below. Changes are previewed client-side before applying.',
        'apply_disabled' => 'Editing in progress',
        'back_to_dashboard' => 'Back to Dashboard',
        'back_to_scan_results' => 'Back to scan results',
        'back_to_localization_studio' => 'Back to Localization Studio',
        'back_to_studio' => 'Back to Studio',
        'not_available' => 'not available',
        'load' => 'Load',
        'file_not_found' => 'file not found',
        'locale_file_not_found' => 'Locale file not found for this owner/locale combination.',
        'changes_preview' => 'Changes Preview',
    ];
    return (string)($dict[$key] ?? $key);
};

$owners = isset($editOwners) && is_array($editOwners) ? $editOwners : [];
$flash = isset($editFlash) && is_array($editFlash) ? $editFlash : null;
$selectedOwner = isset($editSelectedOwner) && is_string($editSelectedOwner) ? $editSelectedOwner : '';
$selectedLocale = isset($editSelectedLocale) && is_string($editSelectedLocale) ? $editSelectedLocale : '';
$filePath = isset($editFilePath) && is_string($editFilePath) ? $editFilePath : '';
$keyValues = isset($editKeyValues) && is_array($editKeyValues) ? $editKeyValues : [];
$refValues = isset($editRefValues) && is_array($editRefValues) ? $editRefValues : [];
$selectedKey = isset($editSelectedKey) && is_string($editSelectedKey) ? $editSelectedKey : '';
$returnTo = isset($editReturnTo) && is_string($editReturnTo) ? $editReturnTo : '';
$csrf = Auth::csrfToken();
?>
<style>
<?php require APP_ROOT . '/apps/Studio/Tools/LocalizationStudio/assets/localization-studio.css'; ?>
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
        <?php if ($isSuccess): ?>
          <div class="lse-flash-actions">
            <?php if ($returnTo !== ''): ?>
              <a class="btn btn-sm" href="<?= e($returnTo) ?>">&larr; <?= e($editHelper('back_to_scan_results')) ?></a>
            <?php endif; ?>
            <a class="btn btn-sm" href="/apps/studio/tools/localization-studio">&larr; <?= e($editHelper('back_to_dashboard')) ?></a>
            <a class="btn btn-sm btn-secondary" href="/apps/studio/tools/localization-studio/edit?owner=<?= rawurlencode($selectedOwner) ?>&locale=<?= rawurlencode($selectedLocale) ?><?= $returnTo !== '' ? '&return_to=' . rawurlencode($returnTo) : '' ?>"><?= e($editHelper('continue_editing')) ?></a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <form method="get" action="/apps/studio/tools/localization-studio/edit" class="lse-selectors">
    <?php if ($returnTo !== ''): ?>
      <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <?php endif; ?>
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
        <?php if ($selectedOwner !== ''): ?>
          <?php foreach (['en', 'ja', 'ne'] as $lc): ?>
            <?php
            $ownerData = [];
            foreach ($owners as $o) {
                if ($o['key'] === $selectedOwner) { $ownerData = $o; break; }
            }
            $available = in_array($lc, $ownerData['locales'] ?? [], true);
            ?>
            <option value="<?= $lc ?>"<?= $lc === $selectedLocale ? ' selected' : '' ?><?= !$available ? ' disabled' : '' ?>>
              <?= strtoupper($lc) ?><?= !$available ? ' (' . e($editHelper('not_available')) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>
    </label>
    <?php if ($selectedOwner !== '' && $selectedLocale !== ''): ?>
      <noscript><button type="submit" class="btn"><?= e($editHelper('load')) ?></button></noscript>
    <?php endif; ?>
  </form>

  <?php if ($selectedOwner === '' || $selectedLocale === ''): ?>
    <div class="lse-empty"><?= $editHelper('no_owner_selected') ?></div>
  <?php else: ?>

    <div class="lse-path">
      <strong><?= $editHelper('file_path') ?>:</strong>
      <code><?= e($filePath !== '' ? $filePath : '(' . $editHelper('file_not_found') . ')') ?></code>
    </div>

    <?php if ($selectedKey !== ''): ?>
      <div class="lse-selected-key-notice">
        Editing key: <strong><?= e($selectedKey) ?></strong>
        <?php if (!isset($keyValues[$selectedKey])): ?>
          <span class="ls-badge is-warn">not in file — add below</span>
        <?php endif; ?>
      </div>
      <?php if (!isset($keyValues[$selectedKey])): ?>
      <div class="lse-missing-key-helper">
        This key is missing in the selected locale.
        <?php if ($selectedLocale !== 'en' && isset($refValues[$selectedKey])): ?>
          English reference shown below. Enter a value, preview diff, then apply.
        <?php else: ?>
          Enter a value below, preview diff, then apply.
        <?php endif; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($returnTo !== ''): ?>
      <div class="lse-missing-key-helper">
        <a class="btn btn-secondary" href="<?= e($returnTo) ?>">&larr; <?= e($editHelper('back_to_scan_results')) ?></a>
      </div>
    <?php endif; ?>

    <?php if ($filePath === ''): ?>
      <div class="lse-empty"><?= e($editHelper('locale_file_not_found')) ?></div>
    <?php else: ?>

    <div class="muted">
      <?= $editHelper('edit_instructions') ?>
    </div>

    <div id="lse-status" class="lse-status"></div>

    <div id="lse-diff-panel" class="lse-diff">
      <h4><?= e($editHelper('changes_preview')) ?></h4>
      <div id="lse-diff-items"></div>
    </div>

    <div class="lse-table-wrap">
      <table class="lse-table">
        <thead>
          <tr>
            <th><?= $editHelper('key_column') ?></th>
            <?php if ($selectedLocale !== 'en'): ?>
              <th><?= $editHelper('reference_value') ?></th>
            <?php endif; ?>
            <th><?= $selectedLocale === 'en' ? $editHelper('current_value') : $editHelper('current_value') ?></th>
            <th><?= $editHelper('new_value') ?></th>
          </tr>
        </thead>
        <tbody id="lse-rows">
          <?php foreach ($keyValues as $key => $value):
            $isSelected = ($key === $selectedKey && $selectedKey !== '');
          ?>
          <tr<?= $isSelected ? ' id="lse-selected-key-row" class="lse-selected-row"' : '' ?>>
            <td class="lse-key-cell"><?= e($key) ?></td>
            <?php if ($selectedLocale !== 'en'): ?>
              <td class="lse-ref-cell"><?= e((string)($refValues[$key] ?? '')) ?></td>
            <?php endif; ?>
            <td class="lse-ref-cell"><?= e((string)$value) ?></td>
            <td class="lse-edit-cell">
              <input type="text" name="values[<?= e($key) ?>]" value="<?= e((string)$value) ?>"
                     data-original="<?= e((string)$value) ?>"
                     oninput="LSE.markChanged(this)" autocomplete="off">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tbody id="lse-add-section">
          <tr class="lse-add-row">
            <td><input type="text" id="lse-new-key" class="lse-new-key-input" placeholder="<?= $editHelper('add_key') ?>"<?= ($selectedKey !== '' && !isset($keyValues[$selectedKey])) ? ' value="' . e($selectedKey) . '"' : '' ?>></td>
            <?php if ($selectedLocale !== 'en'): ?>
              <?php $addRefValue = ($selectedKey !== '' && !isset($keyValues[$selectedKey]) && isset($refValues[$selectedKey])) ? $refValues[$selectedKey] : null; ?>
              <td class="lse-ref-cell lse-add-ref-cell"><?= $addRefValue !== null ? e((string)$addRefValue) : '' ?></td>
            <?php endif; ?>
            <td></td>
            <td>
              <input type="text" id="lse-new-value" class="lse-new-value-input" placeholder="<?= $editHelper('add_value') ?>">
              <div class="lse-add-btn-wrap"><button type="button" class="btn-sm" onclick="LSE.addRow()"><?= $editHelper('add_row') ?></button></div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <form method="post" action="/apps/studio/tools/localization-studio/edit/save" id="lse-form">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="owner" value="<?= e($selectedOwner) ?>">
      <input type="hidden" name="locale" value="<?= e($selectedLocale) ?>">
      <input type="hidden" name="key" value="<?= e($selectedKey) ?>">
      <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
      <input type="hidden" name="values_json" id="lse-values-json" value="">

      <div class="lse-actions">
        <button type="button" class="btn btn-primary" onclick="LSE.previewDiff()"><?= $editHelper('preview_diff') ?></button>
        <button type="submit" class="btn btn-danger" id="lse-apply-btn" disabled onclick="return LSE.confirmApply()"><?= $editHelper('apply_changes') ?></button>
        <a href="/apps/studio/tools/localization-studio/edit?owner=<?= e($selectedOwner) ?>&locale=<?= e($selectedLocale) ?>" class="btn btn-secondary"><?= $editHelper('cancel') ?></a>
      </div>
    </form>

    <script>
    var LSE = (function(){
      function markChanged(input){
        var orig = input.getAttribute('data-original');
        if(input.value !== orig){
          input.classList.add('changed');
        }else{
          input.classList.remove('changed');
        }
        updateApplyButton();
      }

      function updateApplyButton(){
        var inputs = document.querySelectorAll('#lse-rows input[type="text"]');
        var changed = false;
        for(var i=0;i<inputs.length;i++){
          if(inputs[i].value !== inputs[i].getAttribute('data-original')){
            changed = true;
            break;
          }
        }
        var newKey = document.getElementById('lse-new-key');
        if(newKey && newKey.value.trim() !== ''){
          changed = true;
        }
        var btn = document.getElementById('lse-apply-btn');
        if(btn){
          btn.disabled = !changed;
        }
      }

      function addRow(){
        var keyInput = document.getElementById('lse-new-key');
        var valInput = document.getElementById('lse-new-value');
        var key = keyInput.value.trim();
        var val = valInput.value.trim();
        if(key === '' || val === '') return;

        var tbody = document.getElementById('lse-rows');
        var tr = document.createElement('tr');
        var locale = '<?= $selectedLocale ?>';
        var isNonEn = locale !== 'en';

        var html = '<td class="lse-key-cell">' + escapeHtml(key) + '</td>';
        if(isNonEn){
          html += '<td class="lse-ref-cell"></td>';
        }
        html += '<td class="lse-ref-cell"></td>';
        html += '<td class="lse-edit-cell">';
        html += '<input type="text" name="values[' + escapeHtml(key) + ']" value="' + escapeHtml(val) + '" data-original="" oninput="LSE.markChanged(this)" autocomplete="off">';
        html += '</td>';
        tr.innerHTML = html;
        tbody.appendChild(tr);

        keyInput.value = '';
        valInput.value = '';
        updateApplyButton();
      }

      function escapeHtml(s){
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
      }

      function collectValues(){
        var inputs = document.querySelectorAll('#lse-rows input[type="text"]');
        var names = document.querySelectorAll('#lse-rows .lse-key-cell');
        var map = {};
        for(var i=0;i<inputs.length;i++){
          var keyText = names[i] ? names[i].textContent.trim() : '';
          map[keyText] = inputs[i].value;
        }
        return map;
      }

      function previewDiff(){
        var panel = document.getElementById('lse-diff-panel');
        var items = document.getElementById('lse-diff-items');
        var inputs = document.querySelectorAll('#lse-rows input[type="text"]');
        var names = document.querySelectorAll('#lse-rows .lse-key-cell');
        var html = '';
        var count = 0;

        for(var i=0;i<inputs.length;i++){
          var key = names[i] ? names[i].textContent.trim() : '';
          var orig = inputs[i].getAttribute('data-original');
          var curr = inputs[i].value;
          if(curr !== orig){
            html += '<div class="lse-diff-item">';
            html += '<span class="lse-diff-del">-' + escapeHtml(key) + ': ' + escapeHtml(orig) + '</span><br>';
            html += '<span class="lse-diff-add">+' + escapeHtml(key) + ': ' + escapeHtml(curr) + '</span>';
            html += '</div>';
            count++;
          }
        }

        if(count > 0){
          items.innerHTML = html;
          panel.classList.add('is-visible');
        }else{
          items.innerHTML = '<div class="lse-diff-item lse-diff-eq"><?= $editHelper('no_changes') ?></div>';
          panel.classList.add('is-visible');
        }
      }

      function confirmApply(){
        var panel = document.getElementById('lse-diff-panel');
        var items = document.getElementById('lse-diff-items');
        if(!panel.classList.contains('is-visible') || items.children.length === 0){
          previewDiff();
        }
        var hasChanges = false;
        var inputs = document.querySelectorAll('#lse-rows input[type="text"]');
        for(var i=0;i<inputs.length;i++){
          if(inputs[i].value !== inputs[i].getAttribute('data-original')){
            hasChanges = true;
            break;
          }
        }
        if(!hasChanges && document.getElementById('lse-new-key').value.trim() === ''){
          return false;
        }
        if(!confirm('<?= $editHelper('confirm_apply') ?>')){
          return false;
        }
        var map = collectValues();
        document.getElementById('lse-values-json').value = JSON.stringify(map);

        var status = document.getElementById('lse-status');
        status.textContent = 'Applying changes...';
        status.className = 'lse-status lse-status-working is-visible';

        var btn = document.getElementById('lse-apply-btn');
        btn.disabled = true;
        btn.textContent = '<?= $editHelper('saving') ?>';

        document.getElementById('lse-form').submit();
        return false;
      }

      document.addEventListener('DOMContentLoaded', function(){
        updateApplyButton();
        var selectedRow = document.getElementById('lse-selected-key-row');
        if(selectedRow){
          selectedRow.scrollIntoView({behavior:'smooth',block:'center'});
          var input = selectedRow.querySelector('input[type="text"]');
          if(input) input.focus();
        }else{
          var newKey = document.getElementById('lse-new-key');
          if(newKey && newKey.value.trim() !== ''){
            document.getElementById('lse-new-value').focus();
          }
        }
      });

      return {
        markChanged: markChanged,
        addRow: addRow,
        previewDiff: previewDiff,
        confirmApply: confirmApply,
        collectValues: collectValues
      };
    })();
    </script>

    <?php endif; ?>
  <?php endif; ?>

  <div class="ls-actions">
    <a class="btn" href="/apps/studio/tools/localization-studio"><?= e($editHelper('back_to_localization_studio')) ?></a>
  </div>
</div>
</section>
