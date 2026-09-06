<section class="card" id="gs-live-migration-plan">
  <h3><?= e($gs('migration_plan_title')) ?></h3>
  <div id="gs-migration-plan" class="stack">
    <div id="gs-migration-warning" class="note warning u-style-c8be1ccba6"><?= e($gs('migration_warning_destructive')) ?></div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e($gs('migration_action')) ?></th>
            <th><?= e($gs('field_name')) ?></th>
            <th><?= e($gs('migration_strategy')) ?></th>
            <th><?= e($gs('risk_level')) ?></th>
          </tr>
        </thead>
        <tbody id="gs-migration-plan-body">
          <tr>
            <td colspan="4" class="muted"><?= e($gs('migration_empty')) ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>