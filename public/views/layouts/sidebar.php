<?php
/**
 * Shared /me sidebar shell partial.
 *
 * This partial is included from layouts/header.php and relies on the
 * parent scope for prepared sidebar variables.
 */
?>
<aside class="layout-sidebar" id="layoutSidebar" aria-label="<?= e(t('nav.modules')) ?>">
  <nav class="sidebar-nav" aria-label="<?= e(t('nav.modules')) ?>">
    <?php if (!empty($sidebarSections)): ?>
      <div class="sidebar-nav-sections" id="sidebarNavSections">
        <?php foreach ($sidebarSections as $section): ?>
          <?php $sectionKey = $sidebarSlug((string)($section['key'] ?? 'generic')); ?>
          <section class="sidebar-section sidebar-section-<?= e($sectionKey) ?>" aria-label="<?= e((string)($section['label'] ?? '')) ?>">
            <h2 class="sidebar-section-title"><?= e((string)($section['label'] ?? '')) ?></h2>
            <div class="sidebar-nav-groups">
              <?php foreach ((array)($section['groups'] ?? []) as $group): ?>
                <?php
                  $groupKey = $sidebarSlug((string)($group['key'] ?? 'group'));
                  $overflow = (array)($group['overflow'] ?? []);
                  $overflowEnabled = !empty($overflow['enabled']);
                  $overflowLimit = (int)($overflow['limit'] ?? 0);
                  $overflowStorageKey = trim((string)($overflow['storage_key'] ?? $groupKey));
                  $overflowDefaultVisible = array_fill_keys(array_values(array_map('strval', (array)($overflow['default_visible_keys'] ?? []))), true);
                  $overflowTargetId = 'sidebar-group-links-' . $sectionKey . '-' . $groupKey;
                  $groupLinkCount = 0;
                  foreach ((array)($group['items'] ?? []) as $groupItem) {
                    if (!is_array($groupItem)) {
                      continue;
                    }

                    $groupRenderType = trim((string)($groupItem['render_type'] ?? ''));
                    if ($groupRenderType === 'system_preferences') {
                      continue;
                    }

                    $groupLinkCount++;
                  }
                  $groupClasses = [
                    'sidebar-group',
                    'sidebar-group-' . $groupKey,
                    $overflowEnabled ? 'sidebar-group-has-compact-overflow' : '',
                    !empty($group['is_active']) ? 'sidebar-group-has-active' : '',
                  ];
                ?>
                <details class="<?= e(trim(implode(' ', array_filter($groupClasses)))) ?>"<?= !empty($group['is_open']) ? ' open' : '' ?> data-group-key="<?= e($sectionKey . ':' . $groupKey) ?>" data-default-open="<?= !empty($group['is_open']) ? '1' : '0' ?>" data-overflow-enabled="<?= $overflowEnabled ? '1' : '0' ?>" data-overflow-limit="<?= e((string)$overflowLimit) ?>" data-overflow-key="<?= e($overflowStorageKey) ?>">
                  <summary class="sidebar-group-toggle" aria-expanded="<?= !empty($group['is_open']) ? 'true' : 'false' ?>">
                    <span class="sidebar-group-toggle-main">
                      <span class="sidebar-group-title"><?= e((string)($group['label'] ?? '')) ?></span>
                      <?php if ($groupLinkCount > 0): ?>
                        <span class="sidebar-group-count"><?= e((string)$groupLinkCount) ?></span>
                      <?php endif; ?>
                    </span>
                    <span class="sidebar-group-toggle-end">
                      <span class="sidebar-group-active-mark" aria-hidden="true"></span>
                      <span class="sidebar-group-caret" aria-hidden="true"></span>
                    </span>
                  </summary>
                  <div class="sidebar-group-links" id="<?= e($overflowTargetId) ?>">
                    <?php foreach ((array)($group['items'] ?? []) as $item): ?>
                      <?php
                        if (!is_array($item)) {
                          continue;
                        }

                        $itemKey = (string)($item['key'] ?? '');
                        $renderType = trim((string)($item['render_type'] ?? ''));
                        if ($renderType === 'system_preferences'):
                      ?>
                        <div class="sidebar-control-panel">
                          <div class="utility-section">
                            <label class="utility-label" for="erpLangSelect"><?= e(t('common.language')) ?></label>
                            <select id="erpLangSelect" onchange="switchLang(this.value)">
                              <?php foreach (supported_language_labels() as $languageCode => $languageLabel): ?>
                                <option value="<?= e($languageCode) ?>" <?= $lang === $languageCode ? 'selected' : '' ?>><?= e($languageLabel) ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="utility-section">
                            <label class="utility-label" for="erpCurrencySelect"><?= e(t('common.currency')) ?></label>
                            <select id="erpCurrencySelect" onchange="switchCurrency(this.value)">
                              <option value="usd" <?= $currency === 'usd' ? 'selected' : '' ?>><?= e(t('common.usd')) ?></option>
                              <option value="jpy" <?= $currency === 'jpy' ? 'selected' : '' ?>><?= e(t('common.jpy')) ?></option>
                              <option value="npr" <?= $currency === 'npr' ? 'selected' : '' ?>><?= e(t('common.npr')) ?></option>
                            </select>
                          </div>
                          <div class="utility-section">
                            <label class="utility-label" for="erpThemeSelectSidebar"><?= e(t('common.theme')) ?></label>
                            <select id="erpThemeSelectSidebar" data-theme-select>
                              <?php foreach ($themeChoices as $themeValue => $themeLabel): ?>
                                <option value="<?= e($themeValue) ?>"><?= e($themeLabel) ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                      <?php
                          continue;
                        endif;

                        $style = (array)($item['style'] ?? []);
                        $itemLabel = (string)($item['label'] ?? '');
                        if ((string)($item['url'] ?? '') === '/apps/studio/tools/localization-scan-extraction') {
                          $itemLabel = str_replace('Localization Scan Extraction', 'Inline Localization Correction', $itemLabel);
                          $itemLabel = str_replace('Localization Scan & Extraction', 'Inline Localization Correction', $itemLabel);
                        }
                        $itemIcon = trim((string)($item['icon'] ?? ''));
                        if ($itemIcon === '') {
                          $labelForIcon = trim($itemLabel);
                          if ($labelForIcon !== '') {
                            if (function_exists('mb_substr')) {
                              $itemIcon = (string)mb_substr($labelForIcon, 0, 1);
                            } else {
                              $itemIcon = substr($labelForIcon, 0, 1);
                            }
                          }
                          if ($itemIcon === '') {
                            $itemIcon = '•';
                          }
                        }
                        $activeRules = (array)($item['active_rules'] ?? []);
                        $activeRulesPayload = htmlspecialchars((string)json_encode($activeRules, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                        $usageKey = trim((string)($item['usage_key'] ?? ((string)($item['url'] ?? '') !== '' ? (string)($item['url'] ?? '') : $itemKey)));
                        $isOverflowHidden = $overflowEnabled && $itemKey !== '' && empty($overflowDefaultVisible[$itemKey]);
                      ?>
                      <a class="sidebar-link<?= !empty($item['is_active']) ? ' is-active' : '' ?><?= !empty($style['is_dashboard']) ? ' sidebar-link-featured' : '' ?><?= !empty($style['is_action']) ? ' sidebar-link-action' : '' ?><?= !empty($style['is_secondary']) ? ' sidebar-link-secondary' : '' ?><?= $isOverflowHidden ? ' sidebar-link-overflow-hidden' : '' ?>" href="<?= e((string)($item['url'] ?? '#')) ?>" data-active-rules="<?= $activeRulesPayload ?>" data-item-key="<?= e($itemKey) ?>" data-usage-key="<?= e($usageKey) ?>" data-order="<?= e((string)($item['order'] ?? 100)) ?>" data-priority="<?= e((string)($item['priority'] ?? 0)) ?>" data-always-visible="<?= !empty($item['always_visible']) ? '1' : '0' ?>" data-is-active="<?= !empty($item['is_active']) ? '1' : '0' ?>" data-usage-weight="<?= e((string)($item['usage_weight'] ?? 1)) ?>" data-default-visible="<?= $isOverflowHidden ? '0' : '1' ?>"><span class="sidebar-link-icon" aria-hidden="true"><?= e($itemIcon) ?></span><span class="sidebar-link-label"><?= e($itemLabel) ?></span></a>
                    <?php endforeach; ?>
                  </div>
                  <?php if ($overflowEnabled): ?>
                    <div class="sidebar-overflow-controls">
                      <button type="button" class="sidebar-overflow-toggle" data-group-key="<?= e($overflowStorageKey) ?>" data-more-label="<?= e('+ ' . t('common.more')) ?>" data-less-label="<?= e('- ' . t('common.less')) ?>" aria-expanded="false" aria-controls="<?= e($overflowTargetId) ?>"><?= e('+ ' . t('common.more')) ?></button>
                    </div>
                  <?php endif; ?>
                </details>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="muted sidebar-empty" id="sidebarNavSections"><?= e(t('common.no_modules_available')) ?></div>
    <?php endif; ?>
  </nav>
  <div class="sidebar-settings">
    <?php if ($loggedIn): ?>
      <?php if ($isAdmin && $devToolsEnabled): ?>
        <?php $developerGatewayUrl = rtrim((string)$homeUrl, '/') . '#admin-launcher-group-developer'; ?>
        <div class="utility-dev" aria-label="<?= e(t('common.developer')) ?>">
          <div class="utility-dev-summary"><strong><?= e(t('common.developer')) ?></strong><span><?= e($platformModeLabel) ?></span></div>
          <a class="btn" href="<?= e($developerGatewayUrl) ?>"><?= e(t('admin.launcher.open_developer')) ?></a>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <a class="btn ok" href="/login"><?= e(t('common.login')) ?></a>
    <?php endif; ?>
  </div>
</aside>
<button type="button" class="sidebar-backdrop" id="sidebarBackdrop" hidden aria-hidden="true" aria-label="<?= e(t('common.toggle_sidebar')) ?>" tabindex="-1"></button>
