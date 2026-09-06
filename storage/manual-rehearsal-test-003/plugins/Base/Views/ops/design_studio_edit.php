<?php
$composition     = isset($composition) && is_array($composition) ? $composition : [];
$roles           = isset($roles) && is_array($roles) ? $roles : [];
$surfaces        = isset($surfaces) && is_array($surfaces) ? $surfaces : [];
$zones           = isset($zones) && is_array($zones) ? $zones : [];
$blueprintsByZone = isset($blueprintsByZone) && is_array($blueprintsByZone) ? $blueprintsByZone : [];
$savedSlots      = isset($savedSlots) && is_array($savedSlots) ? $savedSlots : [];
$redirectTo      = (string)($redirectTo ?? '/ops/design-studio');
$csrf            = (string)($csrf ?? '');
$flashKey        = trim((string)($flash ?? ''));
$errorKey        = trim((string)($error ?? ''));
$flashText       = $flashKey !== '' ? t($flashKey) : '';
$errorText       = $errorKey !== '' ? t($errorKey) : '';

$id              = (int)($composition['id'] ?? 0);
$compName        = trim((string)($composition['name']           ?? ''));
$compDesc        = trim((string)($composition['description']    ?? ''));
$compRole        = trim((string)($composition['target_role']    ?? ''));
$compSurface     = trim((string)($composition['target_surface'] ?? ''));
$compStatus      = trim((string)($composition['status']         ?? 'draft'));
$compUpdated     = trim((string)($composition['updated_at']     ?? ''));

// Role/surface label maps
$roleLabelMap = [];
foreach ($roles as $r) {
    $roleLabelMap[(string)($r['role_key'] ?? '')] = t((string)($r['label_key'] ?? ''));
}
$surfaceLabelMap = [];
foreach ($surfaces as $s) {
    $surfaceLabelMap[(string)($s['surface_key'] ?? '')] = t((string)($s['label_key'] ?? ''));
}

$statusBadgeClass = match($compStatus) {
    'published' => 'badge--green',
    'archived'  => 'badge--gray',
    default     => 'badge--yellow',
};

// Status transition buttons
$statusTransitions = [];
if ($compStatus === 'draft') {
    $statusTransitions[] = ['new_status' => 'published', 'label_key' => 'ops.design_studio.action.publish'];
    $statusTransitions[] = ['new_status' => 'archived',  'label_key' => 'ops.design_studio.action.archive'];
} elseif ($compStatus === 'published') {
    $statusTransitions[] = ['new_status' => 'draft',     'label_key' => 'ops.design_studio.action.revert_draft'];
    $statusTransitions[] = ['new_status' => 'archived',  'label_key' => 'ops.design_studio.action.archive'];
} elseif ($compStatus === 'archived') {
    $statusTransitions[] = ['new_status' => 'draft',     'label_key' => 'ops.design_studio.action.revert_draft'];
}

$editRedirectTo = '/ops/design-studio/edit?id=' . $id . '&redirect_to=' . urlencode($redirectTo);
?>
<div class="page-header">
  <div class="page-header__inner">
    <div class="page-header__meta">
      <a href="<?= htmlspecialchars($redirectTo) ?>" class="page-header__back"><?= htmlspecialchars(t('ops.design_studio.nav.back_to_list')) ?></a>
    </div>
    <div class="page-header__title-row u-style-ed422c13be">
      <h1 class="page-header__title u-style-1da9facb4d"><?= htmlspecialchars($compName) ?></h1>
      <span class="badge <?= $statusBadgeClass ?>"><?= htmlspecialchars(t('ops.design_studio.status.' . $compStatus)) ?></span>
    </div>
    <p class="page-header__subtitle">
      <?= htmlspecialchars($roleLabelMap[$compRole] ?? $compRole) ?> &middot;
      <?= htmlspecialchars($surfaceLabelMap[$compSurface] ?? $compSurface) ?>
      <?php if ($compUpdated !== ''): ?>
        &middot; <?= htmlspecialchars(t('ops.design_studio.edit.last_updated')) ?>: <?= htmlspecialchars(substr($compUpdated, 0, 10)) ?>
      <?php endif; ?>
    </p>
  </div>
</div>

<div class="ops-section">

<?php if ($flashText !== ''): ?>
  <div class="alert alert--success" role="status"><?= htmlspecialchars($flashText) ?></div>
<?php endif; ?>
<?php if ($errorText !== ''): ?>
  <div class="alert alert--error" role="alert"><?= htmlspecialchars($errorText) ?></div>
<?php endif; ?>

  <!-- ── Metadata form ─────────────────────────────────────────────────── -->
  <section class="form-card">
    <h2 class="form-card__title"><?= htmlspecialchars(t('ops.design_studio.edit.metadata_title')) ?></h2>
    <form method="POST" action="/ops/design-studio/update">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="composition_id" value="<?= $id ?>">
      <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($editRedirectTo) ?>">
      <?php
      // Re-post hidden slot inputs so updating metadata doesn't clear slots
      foreach ($zones as $zone):
          $zoneKey    = (string)($zone['placement_zone'] ?? '');
          $slotIds    = isset($savedSlots[$zoneKey]) && is_array($savedSlots[$zoneKey]) ? $savedSlots[$zoneKey] : [];
          foreach ($slotIds as $bpId):
              $bpId = (int)$bpId;
              if ($bpId > 0):
      ?>
        <input type="hidden" name="slots[<?= htmlspecialchars($zoneKey) ?>][]" value="<?= $bpId ?>">
      <?php endif; endforeach; endforeach; ?>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="edit_name"><?= htmlspecialchars(t('ops.design_studio.create.name_label')) ?> *</label>
          <input type="text" id="edit_name" name="name" class="form-input" required maxlength="190"
                 value="<?= htmlspecialchars($compName) ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_description"><?= htmlspecialchars(t('ops.design_studio.create.description_label')) ?></label>
          <input type="text" id="edit_description" name="description" class="form-input" maxlength="500"
                 value="<?= htmlspecialchars($compDesc) ?>">
        </div>
      </div>
      <div class="form-actions u-style-0cf8f93b0e">
        <button type="submit" class="btn btn--primary"><?= htmlspecialchars(t('ops.design_studio.edit.save_metadata_btn')) ?></button>
      </div>
    </form>
  </section>

  <!-- ── Slot editor ───────────────────────────────────────────────────── -->
  <section class="form-card u-style-2a01802927">
    <h2 class="form-card__title"><?= htmlspecialchars(t('ops.design_studio.edit.slots_title')) ?></h2>
    <p class="form-card__subtitle"><?= htmlspecialchars(t('ops.design_studio.edit.slots_hint')) ?></p>
    <form method="POST" action="/ops/design-studio/update" id="slotEditorForm">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="composition_id" value="<?= $id ?>">
      <input type="hidden" name="name" value="<?= htmlspecialchars($compName) ?>">
      <input type="hidden" name="description" value="<?= htmlspecialchars($compDesc) ?>">
      <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($editRedirectTo) ?>">

      <?php foreach ($zones as $zone): ?>
        <?php
        $zoneKey   = (string)($zone['placement_zone'] ?? '');
        $zoneLabelKey = (string)($zone['label_key'] ?? '');
        $bpsInZone = isset($blueprintsByZone[$zoneKey]) && is_array($blueprintsByZone[$zoneKey]) ? $blueprintsByZone[$zoneKey] : [];
        $assignedIds = [];
        if (isset($savedSlots[$zoneKey]) && is_array($savedSlots[$zoneKey])) {
            foreach ($savedSlots[$zoneKey] as $sid) {
                $assignedIds[] = (int)$sid;
            }
        }
        ?>
        <div class="slot-zone u-style-e8bb474eba">
          <div class="slot-zone__header u-style-de6e65a21e">
            <h3 class="slot-zone__title u-style-a37d333d8a">
              <?= htmlspecialchars(t($zoneLabelKey)) ?>
            </h3>
            <span class="badge badge--blue u-style-bf17975318"><?= count($assignedIds) ?> <?= htmlspecialchars(t('ops.design_studio.edit.assigned_count')) ?></span>
          </div>
          <?php if ($bpsInZone === []): ?>
            <p class="text-muted u-style-3423f41a76"><?= htmlspecialchars(t('ops.design_studio.edit.zone_empty')) ?></p>
          <?php else: ?>
            <div class="slot-zone__blueprints u-style-943b3c085a">
              <?php foreach ($bpsInZone as $bp): ?>
                <?php
                $bpId    = (int)($bp['id'] ?? 0);
                $bpTitle = trim((string)($bp['title_key'] ?? ''));
                $bpTmpl  = trim((string)($bp['template_type'] ?? ''));
                $bpDataset = trim((string)($bp['dataset_key'] ?? ''));
                $checked = in_array($bpId, $assignedIds, true) ? ' checked' : '';
                $inputId = 'slot_' . $zoneKey . '_' . $bpId;
                ?>
                <label class="slot-item" for="<?= htmlspecialchars($inputId) ?>"
                       style="display:flex;align-items:flex-start;gap:.6rem;cursor:pointer;padding:.5rem .6rem;border-radius:4px;transition:background .1s;"
                       onmouseover="this.style.background='var(--color-hover-bg)'"
                       onmouseout="this.style.background='transparent'">
                  <input type="checkbox" id="<?= htmlspecialchars($inputId) ?>"
                         name="slots[<?= htmlspecialchars($zoneKey) ?>][]"
                         value="<?= $bpId ?>"<?= $checked ?>
                         style="margin-top:.15rem;flex-shrink:0;">
                  <span class="slot-item__body">
                    <span class="slot-item__title u-style-4e0b2f482a"><?= htmlspecialchars($bpTitle) ?></span>
                    <span class="slot-item__meta u-style-42ad7ab3ef">
                      <?= htmlspecialchars($bpTmpl) ?> &middot; <?= htmlspecialchars($bpDataset) ?>
                    </span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="form-actions u-style-0cf8f93b0e">
        <button type="submit" class="btn btn--primary"><?= htmlspecialchars(t('ops.design_studio.edit.save_slots_btn')) ?></button>
      </div>
    </form>
  </section>

  <!-- ── Status transitions ────────────────────────────────────────────── -->
  <?php if ($statusTransitions !== []): ?>
  <section class="form-card u-style-2a01802927">
    <h2 class="form-card__title"><?= htmlspecialchars(t('ops.design_studio.edit.status_title')) ?></h2>
    <div class="u-style-cdf112bb08">
      <?php foreach ($statusTransitions as $transition): ?>
        <form method="POST" action="/ops/design-studio/status" onsubmit="return confirm('<?= htmlspecialchars(t('common.are_you_sure')) ?>');">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="composition_id" value="<?= $id ?>">
          <input type="hidden" name="new_status" value="<?= htmlspecialchars($transition['new_status']) ?>">
          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($editRedirectTo) ?>">
          <?php
          $btnClass = match($transition['new_status']) {
              'published' => 'btn--success',
              'archived'  => 'btn--danger',
              default     => 'btn--secondary',
          };
          ?>
          <button type="submit" class="btn <?= $btnClass ?>"><?= htmlspecialchars(t($transition['label_key'])) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ── config_json preview ───────────────────────────────────────────── -->
  <section class="form-card u-style-2a01802927">
    <h2 class="form-card__title"><?= htmlspecialchars(t('ops.design_studio.edit.config_preview_title')) ?></h2>
    <?php
    $configJson = trim((string)($composition['config_json'] ?? ''));
    $prettyJson = '';
    if ($configJson !== '') {
        $decoded = json_decode($configJson, true);
        $prettyJson = is_array($decoded)
            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $configJson;
    }
    ?>
    <?php if ($prettyJson !== ''): ?>
      <pre class="u-style-4faa1653f1"><?= htmlspecialchars($prettyJson) ?></pre>
    <?php else: ?>
      <p class="text-muted u-style-3423f41a76"><?= htmlspecialchars(t('ops.design_studio.edit.config_empty')) ?></p>
    <?php endif; ?>
  </section>

</div>
