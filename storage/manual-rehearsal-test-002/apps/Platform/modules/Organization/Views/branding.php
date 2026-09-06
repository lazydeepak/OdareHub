<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$company = is_array($company ?? null) ? $company : null;
$branding = is_array($branding ?? null) ? $branding : [];
$brandingAssets = is_array($brandingAssets ?? null) ? $brandingAssets : [];
$brandingLogoUrl = trim((string)($brandingLogoUrl ?? ''));
$logoIsSvg = (bool)($logoIsSvg ?? false);
$logoSvgContent = trim((string)($logoSvgContent ?? ''));
$svgThemeOptions = is_array($svgThemeOptions ?? null) ? $svgThemeOptions : [];
$logoSvgPreviewContent = $logoSvgContent;
if ($logoSvgPreviewContent !== '') {
  $previewTheme = trim((string)($branding['logo_svg_theme'] ?? ''));
  if ($previewTheme !== '' && preg_match('/\bclass="ipm-logo\b/', $logoSvgPreviewContent)) {
    $safePreviewTheme = preg_replace('/[^a-z0-9\-]/', '', strtolower($previewTheme));
    if ($safePreviewTheme !== '') {
      $logoSvgPreviewContent = preg_replace('/\bclass="ipm-logo"/', 'class="ipm-logo ' . $safePreviewTheme . '"', $logoSvgPreviewContent, 1);
    }
  }
}
$logoUploadMaxBytes = (int)($logoUploadMaxBytes ?? 0);
$logoUploadMaxLabel = trim((string)($logoUploadMaxLabel ?? ''));
$logoHelp = strtr((string)t('organization.branding.logo_help'), ['{size}' => $logoUploadMaxLabel]);
$logoConstraints = strtr((string)t('organization.branding.logo_constraints'), ['{size}' => $logoUploadMaxLabel]);
$pageHeading = (string)t('organization.nav.branding');
$pageDescription = (string)t('organization.description.branding');
require __DIR__ . '/partials/nav.php';
require __DIR__ . '/partials/feedback.php';
?>

<?php if (!$company): ?>
  <div class="card notice-err"><?= e(t('organization.notice.create_company_before_branding')) ?></div>
<?php else: ?>
  <form method="post" action="/ops/organization/branding" class="card">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="company_id" value="<?= e((string)($company['id'] ?? 0)) ?>">

    <div class="module-header">
      <div class="module-header-info">
        <h3><?= e((string)($company['company_name'] ?? '')) ?></h3>
        <div class="muted"><?= e(t('organization.branding_defaults_company')) ?></div>
      </div>
    </div>

    <div class="form-grid organization-branding-grid">
      <label>
        <div class="muted organization-branding-label"><?= e(t('organization.field.short_brand_name')) ?></div>
        <input class="input" type="text" name="short_brand_name" value="<?= e((string)($branding['short_brand_name'] ?? '')) ?>" <?= $canManage ? '' : 'disabled' ?>>
      </label>

      <div class="organization-branding-section">
        <div class="organization-branding-section-head">
          <div class="ui-block">
            <div class="organization-branding-title"><?= e(t('organization.branding.logo_title')) ?></div>
            <p class="organization-branding-copy"><?= e($logoHelp) ?></p>
          </div>
          <span class="status-chip info"><?= e($logoConstraints) ?></span>
        </div>

        <div class="organization-branding-preview-card">
          <div class="muted organization-branding-label"><?= e(t('organization.branding.logo_current')) ?></div>
          <div class="organization-branding-preview-shell">
            <?php if ($logoIsSvg && $logoSvgPreviewContent !== ''): ?>
              <div class="organization-branding-svg-preview" aria-label="<?= e(t('organization.branding.logo_alt')) ?>"><?= $logoSvgPreviewContent ?></div>
            <?php elseif ($brandingLogoUrl !== ''): ?>
              <img src="<?= e($brandingLogoUrl) ?>" alt="<?= e(t('organization.branding.logo_alt')) ?>" class="organization-branding-logo-image">
            <?php elseif (!empty($branding['logo_path'])): ?>
              <span class="pill"><?= e((string)($branding['logo_path'] ?? '')) ?></span>
            <?php else: ?>
              <div class="organization-branding-empty"><?= e(t('organization.branding.logo_missing')) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($logoIsSvg && $canManage && !empty($svgThemeOptions)): ?>
          <div class="organization-branding-preview-card">
            <div class="muted organization-branding-label"><?= e(t('organization.branding.svg_theme_label')) ?></div>
            <p class="organization-branding-copy"><?= e(t('organization.branding.svg_theme_help')) ?></p>
            <select name="logo_svg_theme" id="logo-svg-theme" <?= $canManage ? '' : 'disabled' ?>>
              <?php foreach ($svgThemeOptions as $themeVal => $themeLabel): ?>
                <option value="<?= e($themeVal) ?>" <?= ((string)($branding['logo_svg_theme'] ?? '')) === $themeVal ? 'selected' : '' ?>><?= e($themeLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <?php if ($canManage): ?>
          <div class="organization-branding-upload-actions">
            <label class="organization-branding-upload-trigger">
              <input type="file" id="logo-file" class="organization-branding-file-input" accept="image/png,image/svg+xml">
              <span class="organization-branding-upload-title"><?= e(t('organization.branding.logo_choose')) ?></span>
              <span class="muted"><?= e($logoConstraints) ?></span>
            </label>
            <?php if (!empty($branding['logo_path'])): ?>
              <button type="button" class="btn danger" id="remove-logo-button"><?= e(t('organization.action.remove_logo')) ?></button>
            <?php endif; ?>
          </div>

          <div id="upload-status" class="organization-branding-status" hidden></div>

          <div id="upload-preview" class="organization-branding-preview-card" hidden>
            <div class="muted organization-branding-label"><?= e(t('organization.branding.logo_preview')) ?></div>
            <div class="organization-branding-preview-shell">
              <img id="preview-image" src="" alt="<?= e(t('organization.branding.logo_preview_alt')) ?>" class="organization-branding-logo-image">
              <div id="preview-svg" class="organization-branding-svg-preview" hidden></div>
            </div>
          </div>

          <script>
          document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('logo-file');
            const uploadStatus = document.getElementById('upload-status');
            const uploadPreview = document.getElementById('upload-preview');
            const previewImage = document.getElementById('preview-image');
            const previewSvg = document.getElementById('preview-svg');
            const removeLogoButton = document.getElementById('remove-logo-button');
            const svgThemeSelect = document.getElementById('logo-svg-theme');
            const brandingForm = document.querySelector('form[action="/ops/organization/branding"]');
            const brandingCsrfInput = brandingForm ? brandingForm.querySelector('input[name="csrf"]') : null;
            const brandingCompanyIdInput = brandingForm ? brandingForm.querySelector('input[name="company_id"]') : null;
            const i18n = <?= json_encode([
              'fileTooLarge' => (string)t('organization.error.logo_file_too_large'),
              'invalidType' => (string)t('organization.error.logo_invalid_type'),
              'uploading' => (string)t('organization.feedback.logo_uploading'),
              'uploadUploaded' => (string)t('organization.feedback.logo_uploaded'),
              'uploadFailed' => (string)t('organization.feedback.logo_upload_failed'),
              'uploadError' => (string)t('organization.feedback.logo_upload_error'),
              'removing' => (string)t('organization.feedback.logo_removing'),
              'removed' => (string)t('organization.feedback.logo_removed'),
              'removeFailed' => (string)t('organization.feedback.logo_remove_failed'),
              'removeError' => (string)t('organization.feedback.logo_remove_error'),
              'removeConfirm' => (string)t('organization.confirm.remove_logo'),
              'unknown' => (string)t('operator.recent.event_unknown'),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const maxSize = <?= $logoUploadMaxBytes ?>;
            const maxSizeLabel = <?= json_encode($logoUploadMaxLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            if (svgThemeSelect && brandingCsrfInput && brandingCompanyIdInput) {
              svgThemeSelect.addEventListener('change', function() {
                const selectedTheme = (svgThemeSelect.value || '').trim();
                const submitBody = new FormData();
                submitBody.append('csrf', brandingCsrfInput.value);
                submitBody.append('company_id', brandingCompanyIdInput.value);
                submitBody.append('logo_svg_theme', selectedTheme);

                svgThemeSelect.disabled = true;
                fetch('/ops/organization/branding', {
                  method: 'POST',
                  body: submitBody,
                  credentials: 'same-origin',
                })
                .then(function() {
                  window.location.reload();
                })
                .catch(function() {
                  svgThemeSelect.disabled = false;
                });
              });
            }

            function applyMessage(template, replacements) {
              return Object.keys(replacements).reduce(function(message, key) {
                return message.replaceAll(key, replacements[key]);
              }, template);
            }

            function formatBytes(bytes) {
              if (!bytes) {
                return '0 B';
              }
              const units = ['B', 'KB', 'MB', 'GB'];
              const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
              const value = bytes / Math.pow(1024, power);
              return value.toFixed(value >= 10 || power === 0 ? 0 : 1) + ' ' + units[power];
            }

            fileInput.addEventListener('change', function() {
              const file = this.files[0];
              if (!file) return;

              if (file.size > maxSize) {
                showStatus(applyMessage(i18n.fileTooLarge, {
                  '{size}': maxSizeLabel,
                  '{current}': formatBytes(file.size)
                }), 'error');
                fileInput.value = '';
                return;
              }

              if (file.type !== 'image/png' && file.type !== 'image/svg+xml') {
                showStatus(applyMessage(i18n.invalidType, {
                  '{type}': file.type || i18n.unknown
                }), 'error');
                fileInput.value = '';
                return;
              }

              const reader = new FileReader();
              if (file.type === 'image/svg+xml') {
                reader.onload = function(e) {
                  // Strip XML declaration and render inline SVG for preview
                  const svgText = e.target.result.replace(/^\s*<\?xml[^>]*>\s*/i, '');
                  previewSvg.innerHTML = svgText;
                  previewSvg.hidden = false;
                  previewImage.hidden = true;
                  uploadPreview.hidden = false;
                  uploadLogo();
                };
                reader.readAsText(file);
              } else {
                reader.onload = function(e) {
                  previewImage.src = e.target.result;
                  previewImage.hidden = false;
                  previewSvg.hidden = true;
                  uploadPreview.hidden = false;
                  uploadLogo();
                };
                reader.readAsDataURL(file);
              }
            });

            function showStatus(message, type) {
              uploadStatus.textContent = message;
              uploadStatus.hidden = false;
              uploadStatus.className = 'organization-branding-status ' + (type === 'error' ? 'notice-err' : (type === 'success' ? 'notice-ok' : 'notice-info'));
              if (type !== 'error') {
                setTimeout(() => {
                  uploadStatus.hidden = true;
                }, 3000);
              }
            }

            window.uploadLogo = function() {
              const file = fileInput.files[0];
              if (!file) return;

              const formData = new FormData();
              formData.append('company_id', '<?= e((string)($company['id'] ?? 0)) ?>');
              formData.append('csrf', '<?= e(\App\Core\Auth::csrfToken()) ?>');
              formData.append('logo', file);

              showStatus(i18n.uploading, 'info');

              fetch('/ops/organization/branding/upload-logo', {
                method: 'POST',
                body: formData
              })
              .then(response => response.json())
              .then(data => {
                fileInput.value = '';
                if (data.success) {
                  showStatus(i18n.uploadUploaded, 'success');
                  if (svgThemeSelect) {
                    svgThemeSelect.closest('.organization-branding-preview-card') && (svgThemeSelect.closest('.organization-branding-preview-card').hidden = false);
                  }
                  setTimeout(() => location.reload(), 1500);
                } else {
                  showStatus(applyMessage(i18n.uploadFailed, {
                    '{error}': data.error || i18n.unknown
                  }), 'error');
                  uploadPreview.hidden = true;
                }
              })
              .catch(err => {
                showStatus(applyMessage(i18n.uploadError, {
                  '{error}': err.message
                }), 'error');
                uploadPreview.hidden = true;
              });
            };

            window.removeLogo = function(e) {
              if (e) {
                e.preventDefault();
              }
              if (!confirm(i18n.removeConfirm)) return;

              const formData = new FormData();
              formData.append('company_id', '<?= e((string)($company['id'] ?? 0)) ?>');
              formData.append('csrf', '<?= e(\App\Core\Auth::csrfToken()) ?>');

              showStatus(i18n.removing, 'info');

              fetch('/ops/organization/branding/remove-logo', {
                method: 'POST',
                body: formData
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  showStatus(i18n.removed, 'success');
                  setTimeout(() => location.reload(), 1500);
                } else {
                  showStatus(applyMessage(i18n.removeFailed, {
                    '{error}': data.error || i18n.unknown
                  }), 'error');
                }
              })
              .catch(err => {
                showStatus(applyMessage(i18n.removeError, {
                  '{error}': err.message
                }), 'error');
              });
            };

            if (removeLogoButton) {
              removeLogoButton.addEventListener('click', window.removeLogo);
            }
          });
          </script>
        <?php endif; ?>

        <div class="organization-branding-assets-card">
          <?php
            $hasActivePngLogo = false;
            $hasInactiveAssets = false;
            $orphanedStorageCount = (int)($orphanedStorageCount ?? 0);
            foreach ($brandingAssets as $_a) {
                if ((int)($_a['is_active'] ?? 0) === 1
                    && ($_a['variant_key'] ?? '') === 'original'
                    && str_ends_with(strtolower((string)($_a['file_path'] ?? '')), '.png')
                ) {
                    $hasActivePngLogo = true;
                }
                if ((int)($_a['is_active'] ?? 0) === 0) {
                    $hasInactiveAssets = true;
                }
            }
            $canPurge = $hasInactiveAssets || $orphanedStorageCount > 0;
          ?>
          <div class="organization-branding-section-head">
            <div class="ui-block">
              <div class="organization-branding-title"><?= e(t('organization.branding.assets_title')) ?></div>
              <p class="organization-branding-copy"><?= e(t('organization.branding.assets_help')) ?></p>
            </div>
            <div class="organization-branding-assets-header-actions">
              <span class="pill"><?= e((string)count($brandingAssets)) ?></span>
              <?php if ($canManage && $hasActivePngLogo): ?>
                <form method="post" action="/ops/organization/branding/regenerate-variants" class="organization-branding-regen-form">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="company_id" value="<?= e((string)($company['id'] ?? 0)) ?>">
                  <button class="btn" type="submit"><?= e(t('organization.action.regenerate_variants')) ?></button>
                </form>
              <?php endif; ?>
              <?php if ($canManage && $canPurge): ?>
                <form method="post" action="/ops/organization/branding/purge-inactive" class="organization-branding-regen-form"
                      onsubmit="return confirm('<?= e(t('organization.confirm.purge_inactive_assets')) ?>')">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="company_id" value="<?= e((string)($company['id'] ?? 0)) ?>">
                  <button class="btn danger" type="submit"><?= e(t('organization.action.purge_inactive_assets')) ?></button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($brandingAssets === []): ?>
            <div class="organization-branding-empty"><?= e(t('organization.branding.assets_empty')) ?></div>
          <?php else: ?>
            <div class="organization-branding-asset-list">
              <?php foreach ($brandingAssets as $asset): ?>
                <?php $assetPreviewUrl = trim((string)($asset['preview_url'] ?? '')); ?>
                <?php $assetSvgContent = trim((string)($asset['svg_inline_content'] ?? '')); ?>
                <?php $assetIsActive = (int)($asset['is_active'] ?? 0) === 1; ?>
                <?php $assetVariantKey = (string)($asset['variant_key'] ?? 'original'); ?>
                <?php $assetIsOriginal = $assetVariantKey === 'original'; ?>
                <div class="organization-branding-asset-item">
                  <div class="organization-branding-asset-preview">
                    <?php if ($assetSvgContent !== ''): ?>
                      <div class="organization-branding-asset-svg"><?= $assetSvgContent ?></div>
                    <?php elseif ($assetPreviewUrl !== ''): ?>
                      <img src="<?= e($assetPreviewUrl) ?>" alt="<?= e(t('organization.branding.logo_alt')) ?>" class="organization-branding-asset-image">
                    <?php else: ?>
                      <div class="organization-branding-empty"><?= e(t('organization.branding.logo_missing')) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="organization-branding-asset-meta">
                    <div class="organization-branding-asset-head">
                      <strong><?= e((string)($asset['display_name'] ?? basename((string)($asset['file_path'] ?? '')))) ?></strong>
                      <div class="organization-branding-asset-chips">
                        <span class="status-chip <?= $assetIsActive ? 'success' : 'neutral' ?>"><?= e($assetIsActive ? t('organization.branding.asset_active') : t('organization.branding.asset_inactive')) ?></span>
                        <?php if (!$assetIsOriginal): ?>
                          <span class="status-chip info"><?= e(t('organization.branding.variant_' . $assetVariantKey, [], ucfirst($assetVariantKey))) ?></span>
                        <?php endif; ?>
                        <?php $surfaceKey = trim((string)($asset['surface_label_key'] ?? '')); if ($surfaceKey !== ''): ?>
                          <span class="status-chip muted"><?= e(t($surfaceKey)) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="muted"><?= e(t('organization.branding.asset_type')) ?>: <?= e((string)($asset['mime_type'] ?? '-')) ?></div>
                    <div class="muted"><?= e(t('organization.branding.asset_size')) ?>: <?= e((string)($asset['file_size_label'] ?? '0 B')) ?></div>
                    <div class="muted"><?= e(t('organization.branding.asset_dimensions')) ?>: <?= e((string)($asset['dimensions_label'] ?? '-')) ?></div>
                    <div class="muted"><?= e(t('organization.branding.asset_path')) ?>: <?= e((string)($asset['file_path'] ?? '')) ?></div>
                    <div class="muted"><?= e(t('organization.branding.asset_uploaded_at')) ?>: <?= e((string)($asset['created_at'] ?? '')) ?></div>
                  </div>
                  <?php if ($canManage): ?>
                    <div class="organization-branding-asset-actions">
                      <?php if (!$assetIsActive && $assetIsOriginal): ?>
                        <form method="post" action="/ops/organization/branding/activate-asset" class="organization-branding-asset-form">
                          <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                          <input type="hidden" name="company_id" value="<?= e((string)($company['id'] ?? 0)) ?>">
                          <input type="hidden" name="asset_id" value="<?= e((string)($asset['id'] ?? 0)) ?>">
                          <button class="btn" type="submit"><?= e(t('organization.action.use_logo_asset')) ?></button>
                        </form>
                      <?php endif; ?>
                      <?php if (!$assetIsActive): ?>
                        <form method="post" action="/ops/organization/branding/delete-asset" class="organization-branding-asset-form"
                              onsubmit="return confirm('<?= e(t('organization.confirm.delete_asset')) ?>')">
                          <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                          <input type="hidden" name="company_id" value="<?= e((string)($company['id'] ?? 0)) ?>">
                          <input type="hidden" name="asset_id" value="<?= e((string)($asset['id'] ?? 0)) ?>">
                          <button class="btn danger" type="submit"><?= e(t('organization.action.delete_asset')) ?></button>
                        </form>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div class="ui-block"></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <label class="organization-branding-span-full">
        <div class="muted organization-branding-label"><?= e(t('organization.field.report_header_text')) ?></div>
        <textarea name="report_header_text" rows="3" <?= $canManage ? '' : 'disabled' ?>><?= e((string)($branding['report_header_text'] ?? '')) ?></textarea>
      </label>
      <label class="organization-branding-span-full">
        <div class="muted organization-branding-label"><?= e(t('organization.field.report_footer_text')) ?></div>
        <textarea name="report_footer_text" rows="3" <?= $canManage ? '' : 'disabled' ?>><?= e((string)($branding['report_footer_text'] ?? '')) ?></textarea>
      </label>
    </div>

    <?php if ($canManage): ?>
      <div class="organization-branding-actions">
        <button class="btn ok" type="submit"><?= e(t('organization.action.save_branding')) ?></button>
      </div>
    <?php endif; ?>
  </form>
<?php endif; ?>

<!-- Favicon Management Section -->
<?php
  $favicon = \Plugins\Organization\Services\OrganizationService::getFaviconAsset((int)($company['id'] ?? 0));
  $faviconUrl = trim((string)($favicon['preview_url'] ?? ''));
?>
<div class="organization-branding-section">
  <div class="organization-branding-section-head">
    <div class="ui-block">
      <div class="organization-branding-title"><?= e(t('organization.branding.favicon_title')) ?></div>
      <p class="organization-branding-copy"><?= e(t('organization.branding.favicon_help')) ?></p>
    </div>
  </div>

  <?php if ($faviconUrl !== ''): ?>
    <div class="organization-branding-preview-card">
      <div class="muted"><?= e(t('organization.branding.favicon_current')) ?></div>
      <div class="organization-branding-preview-shell">
        <img src="<?= e($faviconUrl) ?>" alt="<?= e(t('organization.branding.favicon_alt')) ?>" class="organization-branding-logo-image" style="max-width:32px; max-height:32px;">
      </div>
      <?php if ($canManage): ?>
        <form method="post" action="/ops/organization/branding/remove-favicon" class="organization-branding-upload-actions">
          <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
          <input type="hidden" name="company_id" value="<?= e((string)($company['id'] ?? 0)) ?>">
          <button class="btn danger" type="submit"><?= e(t('organization.action.remove_favicon')) ?></button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($canManage): ?>
    <form method="post" action="/ops/organization/branding/upload-favicon" enctype="multipart/form-data" class="organization-branding-upload-form">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <input type="hidden" name="company_id" value="<?= e((string)($company['id'] ?? 0)) ?>">

      <div class="organization-branding-upload-actions">
        <label class="organization-branding-upload-trigger">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="16"></line>
            <line x1="8" y1="12" x2="16" y2="12"></line>
          </svg>
          <div class="organization-branding-upload-title"><?= e(t('organization.action.upload_favicon')) ?></div>
          <div class="muted u-style-e71ae94b55"><?= e(strtr((string)t('organization.branding.favicon_format_help'), ['{size}' => $logoUploadMaxLabel])) ?></div>
          <input type="file" name="favicon" accept=".png,.svg,image/png,image/svg+xml" class="organization-branding-file-input" required>
        </label>
      </div>

      <?php if (isset($flash) && $flash !== ''): ?>
        <div class="organization-branding-status ok"><?= e($flash) ?></div>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
