<?php
$row = is_array($row ?? null) ? $row : [];
$email = (string)($row['email'] ?? '');
$displayName = trim((string)($row['display_name'] ?? '')) !== '' ? (string)$row['display_name'] : $email;
$userId = (int)($row['id'] ?? 0);
$selectedTab = (string)($selectedTab ?? 'access2');
// Backward-compat: legacy 'access' tab was retired; redirect old bookmarks to access2.
if ($selectedTab === 'access') { $selectedTab = 'access2'; }
$isDisplayAccount = strtolower(trim((string)($row['authority_role'] ?? ''))) === 'tv_display'
  || strtolower(trim((string)($row['dashboard_type'] ?? ''))) === 'display';
$isOperatorAccount = !$isDisplayAccount
  && strtolower(trim((string)($row['authority_role'] ?? ''))) === 'app_user';
$assignmentDetailRedirectTo = '/ops/access-control/detail?user_id=' . $userId . '&full=1&tab=' . rawurlencode($selectedTab);
$tabBaseUrl = '/ops/access-control/detail?user_id=' . $userId . '&full=1';
$tabDefs = [
  'access2'     => t('admin.access.detail.tab_access2'),
  'visibility'  => t('admin.access.detail.tab_visibility'),
  'overrides'   => t('admin.access.detail.tab_overrides'),
  'experience'  => $isDisplayAccount ? t('admin.access.detail.display_panels_title') : ($isOperatorAccount ? t('operator.surface.workspace') : t('admin.access.detail.tab_experience')),
  'diagnostics' => t('admin.access.detail.tab_diagnostics'),
];
?>

<div class="assignment-detail-page">
  <section class="card assignment-detail-hero">
    <div class="assignment-detail-breadcrumbs">
      <a class="assignment-detail-crumb" href="/ops/user-control"><?= e(t('admin.access.detail.breadcrumb_user_control')) ?></a>
      <a class="assignment-detail-crumb" href="/ops/access-control#row-<?= $userId ?>"><?= e(t('admin.access.detail.breadcrumb_access_control')) ?></a>
      <a class="assignment-detail-crumb" href="/"><?= e(t('admin.access.detail.breadcrumb_admin_home')) ?></a>
    </div>

    <div class="assignment-detail-head">
      <div class="assignment-detail-head-copy">
        <div class="assignment-detail-kicker"><?= e(t('admin.access.detail.kicker')) ?></div>
        <div class="section-head assignment-detail-headline">
          <div class="ui-block">
            <h2 class="u-style-1169661891"><?= e(t('admin.access.detail.title')) ?></h2>
            <div class="muted assignment-detail-user-meta"><?= e($displayName) ?><?= $email !== '' ? ' · ' . e($email) : '' ?></div>
          </div>
        </div>
        <p class="muted assignment-detail-intro"><?= e(t($isDisplayAccount ? 'admin.access.detail.display_config_note' : 'admin.access.detail.intro')) ?></p>
      </div>
      <div class="assignment-detail-nav">
        <a class="btn" href="/ops/user-control/detail?user_id=<?= (int)($row['id'] ?? 0) ?>"><?= e(t('admin.access.detail.btn_open_user_detail')) ?></a>
        <a class="btn" href="/ops/access-control#row-<?= $userId ?>"><?= e(t('admin.access.detail.breadcrumb_access_control')) ?></a>
        <a class="btn" href="/"><?= e(t('admin.access.detail.btn_open_admin_home')) ?></a>
      </div>
    </div>
  </section>

  <nav class="access-tabs" role="tablist" aria-label="<?= e(t('admin.access.detail.aria_tabs')) ?>">
    <?php foreach ($tabDefs as $tabKey => $tabLabel): ?>
      <a class="tab-btn <?= $selectedTab === $tabKey ? 'active' : '' ?>" href="<?= e($tabBaseUrl . '&tab=' . rawurlencode($tabKey)) ?>" role="tab" aria-selected="<?= $selectedTab === $tabKey ? 'true' : 'false' ?>"><?= e($tabLabel) ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="tab-panel active" id="<?= e($selectedTab) ?>-panel" role="tabpanel">
    <?php if ($selectedTab === 'access2'): ?>
      <?php require __DIR__ . '/dashboard_assignment_detail_access2.php'; ?>
    <?php elseif ($selectedTab === 'visibility'): ?>
      <?php require __DIR__ . '/dashboard_assignment_visibility.php'; ?>
    <?php elseif ($selectedTab === 'overrides'): ?>
      <?php require __DIR__ . '/dashboard_assignment_overrides.php'; ?>
    <?php elseif ($selectedTab === 'experience'): ?>
      <?php
        if ($isDisplayAccount) {
          $uid = $userId;
          require __DIR__ . '/display_layout.php';
        } elseif ($isOperatorAccount) {
          $uid = $userId;
          require __DIR__ . '/operator_layout.php';
        } else {
          $uid = $userId;
          $meDashboardBlocks = \Plugins\Base\Services\UserDashboardAssignmentService::eligibleMeDashboardBlocksForRow($row);
          $mePluginCards = \Plugins\Base\Services\UserDashboardAssignmentService::eligibleMePluginCardsForRow($row);
          $experienceGroupLabels = \Plugins\Base\Services\UserDashboardAssignmentService::meExperienceGroupLabels();
          $experienceBlockGroupOf = [\Plugins\Base\Services\UserDashboardAssignmentService::class, 'meDashboardBlockGroupKey'];
          $experienceCardGroupOf = [\Plugins\Base\Services\UserDashboardAssignmentService::class, 'mePluginCardGroupKey'];
          require __DIR__ . '/experience_layout.php';
        }
      ?>
    <?php else: ?>
      <?php require __DIR__ . '/dashboard_assignment_diagnostics.php'; ?>
    <?php endif; ?>
  </div>


  <script>
  (function () {
    var page = document.querySelector('.assignment-detail-page');
    if (!page) {
      return;
    }

    function parseCsv(value) {
      return String(value || '').split(',').map(function (token) {
        return String(token || '').trim().toLowerCase();
      }).filter(function (token) {
        return token !== '';
      });
    }

    function writeCsv(target, values) {
      target.value = values.join(',');
    }

    function refreshButtonGroup(targetId, values) {
      page.querySelectorAll('.tg-btn[data-target="' + targetId + '"]').forEach(function (button) {
        var current = String(button.getAttribute('data-value') || '').trim().toLowerCase();
        var isOn = values.indexOf(current) !== -1;
        button.classList.toggle('is-on', isOn);
        button.setAttribute('aria-pressed', isOn ? 'true' : 'false');
      });
    }

    function syncCrossFunctionalHidden(rowId) {
      var hidden = page.querySelector('#cross_functional_access_' + rowId);
      if (!hidden) {
        return;
      }
      var pairs = [];
      page.querySelectorAll('.cross-bundle-toggle[data-row-id="' + rowId + '"]').forEach(function (toggle) {
        var bundle = String(toggle.getAttribute('data-bundle') || '').trim().toLowerCase();
        var select = page.querySelector('.cross-bundle-level[data-row-id="' + rowId + '"][data-bundle="' + bundle + '"]');
        if (!toggle.checked || !bundle || !select) {
          return;
        }
        var level = String(select.value || 'view').trim().toLowerCase() || 'view';
        pairs.push(bundle + ':' + level);
      });
      hidden.value = pairs.join(',');
    }

    page.addEventListener('click', function (ev) {
      var tokenBtn = ev.target.closest('.tg-btn[data-target]');
      if (tokenBtn) {
        ev.preventDefault();
        var targetId = String(tokenBtn.getAttribute('data-target') || '');
        var mode = String(tokenBtn.getAttribute('data-mode') || 'multi');
        var value = String(tokenBtn.getAttribute('data-value') || '').trim().toLowerCase();
        var hidden = targetId ? document.getElementById(targetId) : null;
        if (!hidden || value === '') {
          return;
        }

        var values = parseCsv(hidden.value);
        var exists = values.indexOf(value) !== -1;
        if (mode === 'single') {
          values = exists ? [] : [value];
        } else if (exists) {
          values = values.filter(function (token) { return token !== value; });
        } else {
          values.push(value);
        }

        writeCsv(hidden, values);
        refreshButtonGroup(targetId, values);
        return;
      }

      var presetBtn = ev.target.closest('[data-action="apply-cross-preset"]');
      if (presetBtn) {
        ev.preventDefault();
        var presetKey = String(presetBtn.getAttribute('data-preset') || '');
        var presetMap = <?= json_encode((array)($assignmentUiConfig['crossFunctionalPresets'] ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        var rowId = String(presetBtn.getAttribute('data-row-id') || '');
        var preset = presetMap[presetKey] || {};
        var bundleLevels = {};
        (preset.grants || []).forEach(function (grant) {
          var parts = String(grant || '').toLowerCase().split(':');
          var bundle = String(parts[0] || '').trim();
          var level = String(parts[1] || 'view').trim() || 'view';
          if (bundle) {
            bundleLevels[bundle] = level;
          }
        });
        page.querySelectorAll('.cross-bundle-toggle[data-row-id="' + rowId + '"]').forEach(function (toggle) {
          var bundle = String(toggle.getAttribute('data-bundle') || '').trim();
          var select = page.querySelector('.cross-bundle-level[data-row-id="' + rowId + '"][data-bundle="' + bundle + '"]');
          var level = bundleLevels[bundle] || bundleLevels[bundle.toLowerCase()] || '';
          toggle.checked = level !== '';
          if (select) {
            select.disabled = level === '';
            if (level !== '') {
              select.value = level;
            }
          }
        });
        syncCrossFunctionalHidden(rowId);
      }
    });

    page.querySelectorAll('.cross-bundle-toggle').forEach(function (toggle) {
      toggle.addEventListener('change', function () {
        var rowId = String(toggle.getAttribute('data-row-id') || '');
        var bundle = String(toggle.getAttribute('data-bundle') || '');
        var select = page.querySelector('.cross-bundle-level[data-row-id="' + rowId + '"][data-bundle="' + bundle + '"]');
        if (select) {
          select.disabled = !toggle.checked;
        }
        syncCrossFunctionalHidden(rowId);
      });
    });

    page.querySelectorAll('.cross-bundle-level').forEach(function (select) {
      select.addEventListener('change', function () {
        syncCrossFunctionalHidden(String(select.getAttribute('data-row-id') || ''));
      });
    });

    function applyAuditFilters(owner) {
      var filterInput = page.querySelector('[data-audit-owner="' + owner + '"][data-audit-filter="permission"]');
      var grantedToggle = page.querySelector('[data-audit-owner="' + owner + '"][data-audit-toggle="permission-granted"]');
      var flaggedToggle = page.querySelector('[data-audit-owner="' + owner + '"][data-audit-toggle="permission-flagged"]');
      page.querySelectorAll('[data-audit-owner="' + owner + '"][data-audit-row="permission"]').forEach(function (row) {
        var token = String(row.getAttribute('data-token') || '').toLowerCase();
        var source = String(row.getAttribute('data-source') || '').toLowerCase();
        var flags = String(row.getAttribute('data-flags') || '').toLowerCase();
        var granted = String(row.getAttribute('data-granted') || '0') === '1';
        var query = filterInput ? String(filterInput.value || '').trim().toLowerCase() : '';
        var matches = query === '' || token.indexOf(query) !== -1 || source.indexOf(query) !== -1;
        if (grantedToggle && grantedToggle.checked && !granted) {
          matches = false;
        }
        if (flaggedToggle && flaggedToggle.checked && flags === '') {
          matches = false;
        }
        row.hidden = !matches;
      });
    }

    function applyVisibilityFilter(owner) {
      var grantedToggle = page.querySelector('[data-audit-owner="' + owner + '"][data-audit-toggle="visibility-granted"]');
      page.querySelectorAll('[data-audit-owner="' + owner + '"][data-audit-row="visibility"]').forEach(function (row) {
        var granted = String(row.getAttribute('data-granted') || '0') === '1';
        row.hidden = !!(grantedToggle && grantedToggle.checked && !granted);
      });
    }

    page.querySelectorAll('[data-audit-filter="permission"], [data-audit-toggle="permission-granted"], [data-audit-toggle="permission-flagged"]').forEach(function (node) {
      node.addEventListener('input', function () {
        applyAuditFilters(String(node.getAttribute('data-audit-owner') || ''));
      });
      node.addEventListener('change', function () {
        applyAuditFilters(String(node.getAttribute('data-audit-owner') || ''));
      });
    });

    page.querySelectorAll('[data-audit-toggle="visibility-granted"]').forEach(function (node) {
      node.addEventListener('change', function () {
        applyVisibilityFilter(String(node.getAttribute('data-audit-owner') || ''));
      });
    });

    applyAuditFilters('detail-permissions');
    applyVisibilityFilter('detail-visibility');
  })();
  </script>
</div>
