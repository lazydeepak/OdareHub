<div class="cte-preview-stage">
  <style data-cte-preview-tokens></style>
  <div class="cte-preview-shell" data-cte-preview-shell>
    <div class="cte-preview-topbar">
      <strong><?= e($cte('preview_app_title')) ?></strong>
      <span class="cte-badge"><?= e($cte('preview_mode_chip')) ?></span>
    </div>
    <div class="cte-preview-layout">
      <aside class="cte-preview-sidebar">
        <span><?= e($cte('preview_nav_overview')) ?></span>
        <span><?= e($cte('preview_nav_orders')) ?></span>
        <span><?= e($cte('preview_nav_operations')) ?></span>
        <span><?= e($cte('preview_nav_reports')) ?></span>
      </aside>
      <div class="cte-preview-main">
        <section class="cte-preview-kpis">
          <article><label><?= e($cte('preview_kpi_one')) ?></label><strong>98.4%</strong></article>
          <article><label><?= e($cte('preview_kpi_two')) ?></label><strong>4.2h</strong></article>
          <article><label><?= e($cte('preview_kpi_three')) ?></label><strong>312</strong></article>
        </section>
        <section class="cte-preview-card">
          <strong><?= e($cte('preview_card_title')) ?></strong>
          <p class="cte-preview-card-text"><?= e($cte('preview_card_text')) ?></p>
        </section>
        <section class="cte-preview-card">
          <div class="cte-preview-actions">
            <span class="cte-preview-btn"><?= e($cte('preview_button_primary')) ?></span>
            <span class="cte-preview-btn"><?= e($cte('preview_button_secondary')) ?></span>
          </div>
          <div class="cte-preview-form-row">
            <input type="text" readonly value="<?= e($cte('preview_form_field')) ?>">
          </div>
        </section>
      </div>
    </div>
  </div>
</div>
