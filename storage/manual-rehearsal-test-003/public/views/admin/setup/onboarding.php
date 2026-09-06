<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$steps = is_array($steps ?? null) ? $steps : [];
$platformProfiles = is_array($platformProfiles ?? null) ? $platformProfiles : [];
$defaults = is_array($defaults ?? null) ? $defaults : [];
$coreStatus = is_array($coreStatus ?? null) ? $coreStatus : [];
$suiteCards = is_array($suiteCards ?? null) ? $suiteCards : [];
$verification = is_array($verification ?? null) ? $verification : [];
$csrf = (string)($csrf ?? '');

$presetMeta = [
    'core_only' => [
        'for' => 'For teams that want to start with the platform foundation only.',
        'shape' => 'Minimal starting point',
        'adds' => ['Core platform only', 'No business suite installed yet'],
    ],
    'core_sbaio' => [
        'for' => 'For teams starting with people, attendance, and SBAIO operations.',
        'shape' => 'Focused SBAIO start',
        'adds' => ['Adds SBAIO suite', 'Good for HR and time-related setup'],
    ],
    'core_manufacturing' => [
        'for' => 'For teams starting with production, workflow, and manufacturing setup.',
        'shape' => 'Focused manufacturing start',
        'adds' => ['Adds Manufacturing suite', 'Good for workflow, supply, and product setup'],
    ],
    'core_sbaio_manufacturing' => [
        'for' => 'For organizations that want both business suites available from day one.',
        'shape' => 'Fuller combined start',
        'adds' => ['Adds SBAIO and Manufacturing', 'Best when both people and production flows are needed'],
    ],
];
?>
<?php $setupNavCurrent = 'onboarding'; require __DIR__ . '/_nav.php'; ?>
<?php require __DIR__ . '/_flash.php'; ?>

<style>
  .onboarding-shell { display: grid; gap: 16px; }
  .onboarding-hero { padding: 24px; }
  .onboarding-eyebrow { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #8ec1ff; margin-bottom: 8px; }
  .onboarding-title { margin: 0; font-size: clamp(28px, 4vw, 36px); line-height: 1.1; }
  .onboarding-subtitle { margin: 10px 0 0; max-width: 820px; color: #9fb0c7; line-height: 1.65; }
  .onboarding-progress { display: grid; gap: 10px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .onboarding-step { border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 12px; background: rgba(255,255,255,.02); min-height: 74px; }
  .onboarding-step-num { font-size: 12px; color: #93a4ba; margin-bottom: 8px; }
  .onboarding-step-label { font-weight: 700; }
  .onboarding-step[data-state="done"] { border-color: rgba(109,242,166,.28); background: rgba(109,242,166,.06); }
  .onboarding-step[data-state="active"] { border-color: rgba(142,193,255,.4); background: rgba(142,193,255,.09); }
  .onboarding-grid { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .onboarding-presets { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
  .onboarding-preset { display: grid; gap: 12px; padding: 18px; border: 1px solid rgba(255,255,255,.08); border-radius: 16px; background: rgba(255,255,255,.02); cursor: pointer; transition: border-color .12s ease, background .12s ease, transform .12s ease; }
  .onboarding-preset[data-tone="minimal"] { border-color: rgba(255,255,255,.12); }
  .onboarding-preset[data-tone="ops"] { border-color: rgba(142,193,255,.22); }
  .onboarding-preset[data-tone="manufacturing"] { border-color: rgba(255,210,125,.22); }
  .onboarding-preset[data-tone="combined"] { border-color: rgba(109,242,166,.22); }
  .onboarding-preset.is-selected { border-color: rgba(142,193,255,.5); background: rgba(142,193,255,.08); transform: translateY(-1px); }
  .onboarding-preset h4 { margin: 0; font-size: 18px; }
  .onboarding-preset p { margin: 0; color: #9fb0c7; line-height: 1.55; }
  .onboarding-badge { display: inline-flex; align-items: center; width: fit-content; border: 1px solid rgba(142,193,255,.28); border-radius: 999px; padding: 5px 10px; color: #d7e8ff; background: rgba(142,193,255,.08); font-size: 12px; font-weight: 700; }
  .onboarding-list { display: grid; gap: 6px; color: #cfd7e6; font-size: 14px; }
  .onboarding-card { padding: 18px; }
  .onboarding-card h3 { margin: 0 0 8px; }
  .onboarding-card p { margin: 0; color: #9fb0c7; line-height: 1.6; }
  .onboarding-status { display: grid; gap: 10px; }
  .onboarding-status-item { border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 14px 16px; background: rgba(255,255,255,.02); }
  .onboarding-status-item strong { display: block; margin-bottom: 6px; }
  .onboarding-muted { color: #9fb0c7; line-height: 1.6; }
  .onboarding-selection { display:grid; gap: 14px; }
  .onboarding-selection-input { position:absolute; opacity:0; pointer-events:none; }
  .onboarding-selection-summary { display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; border:1px solid rgba(255,255,255,.08); border-radius:14px; padding:14px 16px; background:rgba(255,255,255,.02); }
  .onboarding-selection-summary strong { display:block; margin-bottom:4px; }
  .btn[disabled] { opacity:.68; cursor:not-allowed; }
  @media (max-width: 900px) {
    .onboarding-progress { grid-template-columns: 1fr; }
    .onboarding-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 640px) {
    .onboarding-hero, .onboarding-card { padding: 18px; }
    .onboarding-progress { grid-template-columns: 1fr; }
  }
</style>

<div class="onboarding-shell">
  <section class="card onboarding-hero">
    <div class="onboarding-eyebrow">Post-Login Onboarding</div>
    <h1 class="onboarding-title">Choose how you want to start using the platform</h1>
    <p class="onboarding-subtitle">The core platform is already installed and your administrator account is secured. This onboarding flow adds optional business setup on top of that core foundation. Presets help you start faster, but you can also keep the system Core only and expand later.</p>
  </section>

  <section class="card onboarding-card" style="padding:16px 18px;">
    <div class="onboarding-progress">
      <div class="onboarding-step" data-state="done">
        <div class="onboarding-step-num">Phase 1</div>
        <div class="onboarding-step-label">Core Installed</div>
      </div>
      <div class="onboarding-step" data-state="done">
        <div class="onboarding-step-num">Phase 2</div>
        <div class="onboarding-step-label">Secure Account</div>
      </div>
      <div class="onboarding-step" data-state="active">
        <div class="onboarding-step-num">Phase 3</div>
        <div class="onboarding-step-label">Start Using Platform</div>
      </div>
    </div>
  </section>

  <section class="card onboarding-card">
    <h3>What presets do</h3>
    <p>Presets add optional business setup on top of the installed core platform. Choose one starting point now, and change or extend it later as your setup grows.</p>
  </section>

  <section class="card onboarding-card">
    <h3 style="margin:0 0 8px;">Choose a starting preset</h3>
    <p class="onboarding-muted" style="margin-bottom:16px;">Pick the option that best matches your immediate business needs. The selection below is only your starting point.</p>

    <form method="post" action="/admin/setup/profile" class="onboarding-selection" id="presetSelectionForm">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <div class="onboarding-presets">
        <?php foreach ($platformProfiles as $index => $profile): ?>
          <?php
          $profileKey = (string)($profile['key'] ?? '');
          $meta = $presetMeta[$profileKey] ?? ['for' => '', 'shape' => 'Starting point', 'adds' => []];
          $tone = match ($profileKey) {
              'core_only' => 'minimal',
              'core_sbaio' => 'ops',
              'core_manufacturing' => 'manufacturing',
              default => 'combined',
          };
          $checked = false;
          ?>
          <label class="onboarding-preset<?= $checked ? ' is-selected' : '' ?>" data-preset-card data-preset-key="<?= e($profileKey) ?>" data-tone="<?= e($tone) ?>">
            <input class="onboarding-selection-input" type="radio" name="profile_key" value="<?= e($profileKey) ?>"<?= $checked ? ' checked' : '' ?>>
            <div style="display:grid;gap:8px;">
              <span class="onboarding-badge"><?= e((string)$meta['shape']) ?></span>
              <h4><?= e((string)($profile['label'] ?? '')) ?></h4>
              <p><?= e((string)($meta['for'] ?? '')) ?></p>
            </div>
            <div class="onboarding-list">
              <?php foreach ((array)($meta['adds'] ?? []) as $item): ?>
                <div><?= e((string)$item) ?></div>
              <?php endforeach; ?>
            </div>
            <div class="onboarding-muted"><?= e((string)($profile['description'] ?? '')) ?></div>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="onboarding-selection-summary">
        <div>
          <strong id="presetSelectionTitle">Selected preset</strong>
          <div class="onboarding-muted" id="presetSelectionHint">Select a preset to continue. You can change or extend this later from setup.</div>
        </div>
        <button class="btn ok" type="submit" id="presetSelectionButton" data-processing-text="Applying preset..." disabled>Continue with Selected Preset</button>
      </div>
    </form>
  </section>

  <section class="onboarding-grid">
    <div class="card onboarding-card">
      <h3>Your next guided path</h3>
      <div class="onboarding-status">
        <?php foreach ($steps as $step): ?>
          <div class="onboarding-status-item">
            <strong>Step <?= e((string)($step['number'] ?? '')) ?>: <?= e((string)($step['title'] ?? '')) ?></strong>
            <div class="onboarding-muted"><?= e((string)($step['description'] ?? '')) ?></div>
            <div style="margin-top:6px;">Status: <?= e((string)($step['state'] ?? 'to do')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="display:grid;gap:16px;">
      <div class="card onboarding-card">
        <h3>Core foundation</h3>
        <p><?= e((string)($coreStatus['next_action'] ?? 'Core platform is installed and ready for business setup.')) ?></p>
        <div class="onboarding-muted" style="margin-top:8px;">Company identity and shared defaults are managed in Organization, not inside the setup wizard.</div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
          <a class="btn" href="/admin/setup/core">Open Core Setup</a>
          <a class="btn ok" href="/ops/organization">Open Organization</a>
          <a class="btn" href="/ops/organization/company">Open Company Profile</a>
        </div>
      </div>

      <div class="card onboarding-card">
        <h3>Suite setup options</h3>
        <p><?= e((string)count($suiteCards)) ?> suite area<?= count($suiteCards) === 1 ? '' : 's' ?> are available. Use a preset for the fastest start, or open suite setup for manual control.</p>
        <div style="margin-top:12px;"><a class="btn" href="/admin/setup/suites">Open Suite Setup</a></div>
      </div>

      <div class="card onboarding-card">
        <h3>Final setup check</h3>
        <p>Warnings: <?= e((string)count((array)($verification['warnings'] ?? []))) ?> · Errors: <?= e((string)count((array)($verification['errors'] ?? []))) ?></p>
        <div class="onboarding-muted" style="margin-top:8px;">Run the setup check before inviting daily users into the system.</div>
        <div style="margin-top:12px;">
          <form method="post" action="/admin/setup/verify">
            <button class="btn" type="submit">Run Setup Check</button>
          </form>
        </div>
      </div>
    </div>
  </section>

  <section class="card onboarding-card">
    <h3>Safe starting defaults</h3>
    <div class="onboarding-list">
      <div><strong>Administrator:</strong> <?= e((string)($defaults['first_admin'] ?? '')) ?></div>
      <div><strong>Language:</strong> <?= e((string)($defaults['language'] ?? 'English')) ?></div>
      <div><strong>Currency:</strong> <?= e((string)($defaults['currency'] ?? 'JPY')) ?></div>
      <div><strong>Theme:</strong> <?= e((string)($defaults['theme'] ?? 'system')) ?></div>
      <div><strong>Workspace home:</strong> <code>/me</code></div>
      <div><strong>Suite dashboards:</strong> SBAIO <code>/apps/sbaio</code> · Manufacturing <code>/apps/manufacturing</code></div>
    </div>
  </section>
</div>

<script>
  (function () {
    const presetCards = Array.from(document.querySelectorAll('[data-preset-card]'));
    const title = document.getElementById('presetSelectionTitle');
    const hint = document.getElementById('presetSelectionHint');
    const button = document.getElementById('presetSelectionButton');
    const updateSelection = () => {
      let activeLabel = 'Selected preset';
      let hasSelection = false;
      presetCards.forEach((card) => {
        const input = card.querySelector('input[type="radio"]');
        const isSelected = !!input && input.checked;
        card.classList.toggle('is-selected', isSelected);
        if (isSelected) {
          hasSelection = true;
          const heading = card.querySelector('h4');
          activeLabel = heading ? heading.textContent || activeLabel : activeLabel;
        }
      });
      if (title) title.textContent = activeLabel;
      if (hint) {
        hint.textContent = hasSelection
          ? 'You can change or extend this later from setup.'
          : 'Select a preset to continue. You can change or extend this later from setup.';
      }
      if (button) {
        button.textContent = hasSelection ? 'Continue with ' + activeLabel : 'Continue with Selected Preset';
        button.disabled = !hasSelection;
      }
    };

    presetCards.forEach((card) => {
      card.addEventListener('click', function () {
        const input = card.querySelector('input[type="radio"]');
        if (input) {
          input.checked = true;
          updateSelection();
        }
      });
    });

    updateSelection();

    const presetForm = document.getElementById('presetSelectionForm');
    if (presetForm instanceof HTMLFormElement) {
      presetForm.addEventListener('submit', function (event) {
        const hasSelection = presetCards.some((card) => {
          const input = card.querySelector('input[type="radio"]');
          return !!input && input.checked;
        });
        if (!hasSelection) {
          event.preventDefault();
          updateSelection();
        }
      });
    }

    document.addEventListener('submit', function (event) {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) return;
      const submitter = event.submitter instanceof HTMLButtonElement ? event.submitter : null;
      if (!submitter || submitter.dataset.processingApplied === '1') return;
      submitter.dataset.processingApplied = '1';
      submitter.textContent = submitter.dataset.processingText || 'Processing...';
      submitter.disabled = true;
    });
  })();
</script>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
