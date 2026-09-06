<section class="gs-studio-tools-panel">
  <div class="gs-studio-tools-heading">
    <div>
      <h4 class="gs-studio-tools-title"><?= e($gsBatch1('studio_tools_title')) ?></h4>
      <p class="gs-studio-tools-helper"><?= e($gsBatch1('studio_tools_helper')) ?></p>
    </div>
  </div>
  <div class="gs-studio-tools-overview" aria-hidden="true">
    <?php foreach ($studioToolGroupOrder as $studioToolGroupKey): ?>
      <?php $studioTools = isset($studioToolGroups[$studioToolGroupKey]) && is_array($studioToolGroups[$studioToolGroupKey]) ? $studioToolGroups[$studioToolGroupKey] : []; ?>
      <div class="gs-studio-tools-overview-item">
        <span class="gs-studio-tools-overview-label"><?= e($gs($studioToolGroupKey)) ?></span>
        <span class="gs-studio-tools-overview-count"><?= count($studioTools) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="gs-studio-tools-layout">
    <div class="gs-studio-tools-grid">
    <?php foreach ($studioToolGroupOrder as $studioToolGroupKey): ?>
      <?php $studioTools = isset($studioToolGroups[$studioToolGroupKey]) && is_array($studioToolGroups[$studioToolGroupKey]) ? $studioToolGroups[$studioToolGroupKey] : []; ?>
      <dl class="gs-studio-tools-group">
        <dt>
          <span><?= e($gs($studioToolGroupKey)) ?></span>
          <span class="gs-studio-tools-group-count"><?= count($studioTools) ?></span>
        </dt>
        <dd class="gs-studio-tools-cards">
          <?php foreach ($studioTools as $studioTool): ?>
            <?php
            $toolId = (string)($studioTool['id'] ?? '');
            $toolNameKey = (string)($studioTool['name_key'] ?? '');
            $toolStatusKey = (string)($studioTool['status_key'] ?? '');
            $toolPurposeKey = (string)($studioTool['purpose_key'] ?? '');
            $toolWorksOnKey = (string)($studioTool['works_on_key'] ?? '');
            $toolMustNotOwnKey = (string)($studioTool['must_not_own_key'] ?? '');
            $toolFirstSafeKey = (string)($studioTool['first_safe_key'] ?? '');
            $toolBackendKey = (string)($studioTool['backend_key'] ?? '');
            $toolCardBoundaryKey = (string)($studioTool['card_boundary_key'] ?? $toolWorksOnKey);
            $toolHref = isset($studioTool['href']) && is_string($studioTool['href']) ? trim($studioTool['href']) : '';
            $toolLinkHintKey = isset($studioTool['link_hint_key']) && is_string($studioTool['link_hint_key']) ? trim($studioTool['link_hint_key']) : '';
            $toolIsDisabled = !empty($studioTool['is_disabled']);
            ?>
            <?php if ($toolHref !== ''): ?>
              <a class="gs-studio-tool-card" href="<?= e($toolHref) ?>" data-gs-tool-preview-trigger="1" data-tool-id="<?= e($toolId) ?>" data-tool-name="<?= e($gs($toolNameKey)) ?>" data-tool-group="<?= e($gs($studioToolGroupKey)) ?>" data-tool-status="<?= e($gs($toolStatusKey)) ?>" data-tool-purpose="<?= e($gs($toolPurposeKey)) ?>" data-tool-works-on="<?= e($gs($toolWorksOnKey)) ?>" data-tool-must-not-own="<?= e($gs($toolMustNotOwnKey)) ?>" data-tool-first-safe="<?= e($gs($toolFirstSafeKey)) ?>" data-tool-backend="<?= e($gs($toolBackendKey)) ?>">
                <div class="gs-studio-tool-card-title-row">
                  <span class="gs-studio-tool-card-title"><?= e($gs($toolNameKey)) ?></span>
                  <span class="gs-studio-tools-status" data-status-key="<?= e($toolStatusKey) ?>"><?= e($gs($toolStatusKey)) ?></span>
                </div>
                <p class="gs-studio-tool-card-purpose"><?= e($gs($toolPurposeKey)) ?></p>
                <p class="gs-studio-tool-card-boundary"><?= e($gs($toolCardBoundaryKey)) ?></p>
                <?php if ($toolLinkHintKey !== ''): ?>
                  <span class="gs-studio-tool-card-link-hint"><?= e($gs($toolLinkHintKey)) ?></span>
                <?php endif; ?>
              </a>
            <?php else: ?>
              <div class="gs-studio-tool-card"<?= $toolIsDisabled ? ' aria-disabled="true" role="button" tabindex="0"' : '' ?> data-gs-tool-preview-trigger="1" data-tool-id="<?= e($toolId) ?>" data-tool-name="<?= e($gs($toolNameKey)) ?>" data-tool-group="<?= e($gs($studioToolGroupKey)) ?>" data-tool-status="<?= e($gs($toolStatusKey)) ?>" data-tool-purpose="<?= e($gs($toolPurposeKey)) ?>" data-tool-works-on="<?= e($gs($toolWorksOnKey)) ?>" data-tool-must-not-own="<?= e($gs($toolMustNotOwnKey)) ?>" data-tool-first-safe="<?= e($gs($toolFirstSafeKey)) ?>" data-tool-backend="<?= e($gs($toolBackendKey)) ?>">
                <div class="gs-studio-tool-card-title-row">
                  <span class="gs-studio-tool-card-title"><?= e($gs($toolNameKey)) ?></span>
                  <span class="gs-studio-tools-status" data-status-key="<?= e($toolStatusKey) ?>"><?= e($gs($toolStatusKey)) ?></span>
                </div>
                <p class="gs-studio-tool-card-purpose"><?= e($gs($toolPurposeKey)) ?></p>
                <p class="gs-studio-tool-card-boundary"><?= e($gs($toolCardBoundaryKey)) ?></p>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </dd>
      </dl>
    <?php endforeach; ?>
    </div>
  </div>
</section>
