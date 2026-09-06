  <nav class="ld-tabs">
    <?php foreach (['overview', 'build', 'rules', 'preview', 'governance'] as $ws): ?>
      <?php $isSecondary = $ws === 'governance'; ?>
      <a class="ld-tab<?= $activeWorkspace === $ws ? ' ld-active' : '' ?><?= $isSecondary ? ' ld-secondary' : '' ?>"
         href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => $ws]) ?>"
         data-workspace="<?= e($ws) ?>">
        <?= e($workspaceLabel($ws)) ?>
      </a>
    <?php endforeach; ?>
  </nav>
