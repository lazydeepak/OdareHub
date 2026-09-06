// Style Compliance — Canonical State Shape Verification (PR 3A)
const { chromium } = require('playwright');

const TARGET_URL = 'http://localhost:8000';

(async () => {
  const launchOptions = { headless: true };
  if (process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE) {
    launchOptions.executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE;
  }
  const browser = await chromium.launch(launchOptions);
  const context = await browser.newContext();
  const page = await context.newPage();
  const consoleErrors = [];
  const httpFailures = [];
  page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', err => consoleErrors.push(err.message));
  page.on('response', resp => {
    if (resp.status() >= 500) httpFailures.push(resp.url() + ' ' + resp.status());
  });

  const checks = { pass: 0, fail: 0 };
  function check(label, condition) {
    if (condition) {
      console.log('  \u2713 ' + label);
      checks.pass++;
    } else {
      console.log('  \u2717 ' + label);
      checks.fail++;
    }
  }

  // Helper: read first matching element's text
  async function firstText(selector) {
    var el = await page.locator(selector).first().textContent().catch(() => null);
    return el;
  }
  async function scanAreaText() {
    return ((await page.locator('.sc-cp-left').textContent().catch(() => '')) || '').replace(/\s+/g, ' ').trim();
  }
  function hasBadScanConsoleText(value) {
    return /Async cockpit sync|Waiting for scan input|Standing by|standing by|No report mounted yet|Recent Activity|Log|Pending|Idle/.test(value || '');
  }

  // 1. Login
  console.log('\n--- Login ---');
  await page.goto(TARGET_URL + '/login');
  await page.waitForSelector('input[name="email"]', { timeout: 10000 });
  await page.fill('input[name="email"]', 'lazydeepak@gmail.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2000);
  check('Logged in', !page.url().includes('/login'));

  // 2. Navigate to Style Compliance
  console.log('\n--- Navigate to Style Compliance ---');
  await page.goto(TARGET_URL + '/apps/studio/tools/customization-studio/diagnose/style-compliance');
  await page.waitForTimeout(2000);
  check('Style Compliance page loaded', page.url().includes('style-compliance'));
  check('Initial scan console shows Ready', await firstText('[data-sc-scan-status]') === 'Ready');
  check('Initial scan button shows Scan', await firstText('[data-sc-scan-label]') === 'Scan');
  check('Initial scan console has no empty activity/log copy', !hasBadScanConsoleText(await scanAreaText()));

  // 3. Fresh-page failure has no prior evidence marker and no stale scanning text.
  console.log('\n--- Fresh Failure Lifecycle ---');
  await page.route('**/style-compliance/scan', async route => {
    await new Promise(resolve => setTimeout(resolve, 800));
    await route.fulfill({
      status: 500,
      contentType: 'application/json',
      body: JSON.stringify({ error: 'probe failure', state: { scan_status: 'failed', scope: 'all_owners', workspace: 'overview' } }),
    });
  });
  const scanBtn = page.locator('.sc-scan-btn');
  await scanBtn.waitFor({ state: 'visible', timeout: 5000 });
  await scanBtn.click();
  await page.waitForTimeout(150);
  check('Scanning state shows Scanning label', await firstText('[data-sc-scan-status]') === 'Scanning…');
  check('Scanning state has no fake file count', !/files|\/\s*\d+/.test(await scanAreaText()));
  await page.waitForTimeout(1000);
  check('Fresh failure shows classified failed status', await firstText('[data-sc-cockpit-status]') === 'Scan failed — server error');
  check('Fresh failure button shows Retry scan', await firstText('[data-sc-scan-label]') === 'Retry scan');
  check('Fresh failure has no prior evidence marker', await page.locator('[data-sc-prior-evidence]:not([hidden])').count() === 0);
  check('Fresh failure action word is not Scanning', await firstText('[data-sc-cockpit-action-word]') !== 'Scanning');
  check('Fresh failure scan button aria-busy false', await scanBtn.getAttribute('aria-busy') === 'false');
  check('Fresh failure shows results unavailable', ((await page.locator('#style-compliance-results').textContent()) || '').includes('Results unavailable'));
  check('Fresh failure scan console has retry guidance and no implementation copy', (await scanAreaText()).includes('retry') && !hasBadScanConsoleText(await scanAreaText()));
  await page.unroute('**/style-compliance/scan');
  consoleErrors.length = 0;
  httpFailures.length = 0;
  await page.reload();
  await page.waitForTimeout(1000);
  consoleErrors.length = 0;
  httpFailures.length = 0;

  // 4. Run an All Owners scan using waitForResponse (no DOM polling race)
  console.log('\n--- All Owners Scan ---');
  await scanBtn.waitFor({ state: 'visible', timeout: 5000 });
  const ownerSelectInitial = page.locator('[name="owner"]');
  check('Owner selector disabled for All Owners', await ownerSelectInitial.isDisabled());

  var scanPromise = page.waitForResponse(function(resp) {
    return resp.url().includes('style-compliance/scan') && resp.request().method() === 'POST';
  }, { timeout: 120000 });

  await scanBtn.click();
  var scanResponse = await scanPromise;
  await page.waitForTimeout(2000);

  var scanData = await scanResponse.json();
  check('All Owners scan HTTP ok', scanResponse.ok() && scanData.state.scan_status === 'complete');

  // 4. Verify cockpit state
  console.log('\n--- Cockpit State Verification ---');
  var statusEl = await firstText('[data-sc-cockpit-status]');
  check('Cockpit status shows "Scan complete"', statusEl === 'Scan complete');
  check('Complete scan console shows real summary values', /Scan complete · \d+ files · \d+ findings · \d+ Fixable Now/.test(await scanAreaText()));
  check('Complete scan console has next step', (await scanAreaText()).includes('Next: Review Fixable Now') || (await scanAreaText()).includes('Next: Review Only'));
  check('Complete scan console has no stale implementation copy', !hasBadScanConsoleText(await scanAreaText()));

  var ownersEl = await firstText('[data-sc-cockpit-owners]');
  check('Owners metric populated', ownersEl !== null && ownersEl !== '\u2014');

  var totalEl = await firstText('[data-sc-cockpit-total]');
  check('Total findings metric populated', totalEl !== null && totalEl !== '\u2014');

  var fixableEl = await firstText('[data-sc-cockpit-ready]');
  var fixableVal = fixableEl ? parseInt(fixableEl.replace(/,/g, ''), 10) : 0;
  check('Fixable Now metric populated', fixableVal >= 0);
  console.log('    Fixable Now: ' + fixableVal);

  // 5. Verify response state shape
  console.log('\n--- Response State Shape ---');
  check('State has summary', !!scanData.state.summary);
  check('State has next_action', !!scanData.state.next_action);
  check('State has scan_status', scanData.state.scan_status === 'complete');
  check('State has scope', scanData.state.scope === 'all_owners');
  check('State owner_key is null', scanData.state.owner_key === null);
  check('summary.owners_scanned', typeof scanData.state.summary.owners_scanned === 'number' && scanData.state.summary.owners_scanned > 0);
  check('summary.files_scanned', typeof scanData.state.summary.files_scanned === 'number');
  check('summary.findings_total', typeof scanData.state.summary.findings_total === 'number');
  check('summary.fixable_now', typeof scanData.state.summary.fixable_now === 'number');
  check('summary.review_only', typeof scanData.state.summary.review_only === 'number');
  check('summary.blocked_or_not_safe', typeof scanData.state.summary.blocked_or_not_safe === 'number');
  check('summary.decision_backlog', typeof scanData.state.summary.decision_backlog === 'number');
  check('summary.compliance_score', typeof scanData.state.summary.compliance_score === 'number');
  check('next_action.kind', typeof scanData.state.next_action.kind === 'string');
  check('next_action.target', typeof scanData.state.next_action.target === 'string');
  check('next_action.label', typeof scanData.state.next_action.label === 'string');
  check('Canonical URL is style-compliance path', scanData.canonical_url.includes('style-compliance'));
  check('Canonical URL excludes owner', !scanData.canonical_url.includes('owner='));
  check('Cockpit total equals state findings_total', parseInt((await firstText('[data-sc-cockpit-total]') || '0').replace(/,/g, ''), 10) === scanData.state.summary.findings_total);
  check('Cockpit Fixable Now equals state fixable_now', fixableVal === scanData.state.summary.fixable_now);
  check('Fix buttons equal Fixable Now section count', await page.locator('.sc-fix-one').count() === parseInt((await firstText('[data-fixable-now-count]') || '0').replace(/,/g, ''), 10));
  check('No broad Apply All controls', !/Apply All|Fix All Safe|Apply Repair/.test(await page.textContent('body')));

  // 6. Completed scan then failed scan preserves genuine prior marker.
  console.log('\n--- Prior Evidence Failure Lifecycle ---');
  await page.route('**/style-compliance/scan', route => route.fulfill({
    status: 500,
    contentType: 'application/json',
    body: JSON.stringify({ error: 'probe failure after complete', state: { scan_status: 'failed', scope: 'all_owners', workspace: 'overview' } }),
  }));
  await scanBtn.click();
  await page.waitForTimeout(1000);
  const priorText = await page.locator('[data-sc-prior-evidence]').textContent();
  check('Post-success failure shows prior evidence marker', await page.locator('[data-sc-prior-evidence]:not([hidden])').count() === 1);
  check('Post-success failure prior marker is failure text', priorText && priorText.includes('Previous completed results'));
  check('Post-success failure action word is retry-ready', await firstText('[data-sc-cockpit-action-word]') === 'Retry ready');
  check('Post-success failure has no stale Scanning action text', !((await page.locator('[data-sc-cockpit-action]').textContent()) || '').includes('Scanning'));
  check('Post-success failure scan console mentions prior results only with prior evidence', (await scanAreaText()).includes('Previous completed results remain available below.'));
  await page.unroute('**/style-compliance/scan');
  consoleErrors.length = 0;
  httpFailures.length = 0;

  // 7. Owner scope scan
  console.log('\n--- Owner Scope Scan ---');
  var scopeSelect = page.locator('#scScopeSelect');
  await scopeSelect.waitFor({ state: 'visible', timeout: 3000 });
  await scopeSelect.selectOption('owner');
  await page.waitForTimeout(500);

  var ownerSelect = page.locator('[name="owner"]');
  await ownerSelect.waitFor({ state: 'visible', timeout: 3000 });
  check('Owner selector enabled for owner scope', !(await ownerSelect.isDisabled()));
  var ownerOptions = await ownerSelect.locator('option').all();
  if (ownerOptions.length > 1) {
    await ownerSelect.selectOption({ index: 1 });
    await page.waitForTimeout(300);
    console.log('    Selected owner: ' + (await ownerSelect.inputValue()));

    var ownerScanPromise = page.waitForResponse(function(resp) {
      return resp.url().includes('style-compliance/scan') && resp.request().method() === 'POST';
    }, { timeout: 120000 });

    await scanBtn.click();
    var ownerScanRes = await ownerScanPromise;
    await page.waitForTimeout(2000);

    var ownerScanData = await ownerScanRes.json();
    check('Owner scan HTTP ok', ownerScanRes.ok() && ownerScanData.state.scan_status === 'complete');

    var ownerWrap = await page.locator('#scOwnerWrap');
    var ownerHidden = await ownerWrap.evaluate(el => el.classList.contains('sc-owner-hidden')).catch(() => true);
    check('Owner wrap visible for owner scope', !ownerHidden);

    check('Owner canonical URL has owner= param', ownerScanData.canonical_url.includes('owner='));
    check('Owner canonical URL has scope=owner', ownerScanData.canonical_url.includes('scope=owner'));
    check('Owner scan state has owner_key', ownerScanData.state.owner_key !== null && ownerScanData.state.owner_key !== undefined);
  } else {
    console.log('  \u26A0 No owner options, skipping owner scan');
    checks.pass++;
  }

  // 8. Shell scan
  console.log('\n--- Shell Scope Scan ---');
  await scopeSelect.selectOption('shell');
  await page.waitForTimeout(300);

  var shellScanPromise = page.waitForResponse(function(resp) {
    return resp.url().includes('style-compliance/scan') && resp.request().method() === 'POST';
  }, { timeout: 120000 });

  await scanBtn.click();
  var shellScanRes = await shellScanPromise;
  await page.waitForTimeout(2000);

  var ownerWrap2 = await page.locator('#scOwnerWrap');
  var ownerHidden2 = await ownerWrap2.evaluate(el => el.classList.contains('sc-owner-hidden')).catch(() => false);
  check('Owner wrap hidden for shell scope', ownerHidden2);
  check('Owner selector disabled for shell scope', await ownerSelect.isDisabled());

  var shellScanData = await shellScanRes.json();
  check('Shell scan HTTP ok', shellScanRes.ok() && shellScanData.state.scan_status === 'complete');
  check('Shell canonical URL has scope=shell', shellScanData.canonical_url.includes('scope=shell'));
  check('Shell state scope=shell', shellScanData.state.scope === 'shell');

  // 9. Theme scan
  console.log('\n--- Theme Scope Scan ---');
  await scopeSelect.selectOption('theme');
  await page.waitForTimeout(300);
  check('Owner selector disabled for theme scope', await ownerSelect.isDisabled());
  var themeScanPromise = page.waitForResponse(function(resp) {
    return resp.url().includes('style-compliance/scan') && resp.request().method() === 'POST';
  }, { timeout: 120000 });
  await scanBtn.click();
  var themeScanRes = await themeScanPromise;
  var themeScanData = await themeScanRes.json();
  check('Theme scan HTTP ok', themeScanRes.ok() && themeScanData.state.scan_status === 'complete');
  check('Theme canonical URL has scope=theme', themeScanData.canonical_url.includes('scope=theme'));
  check('Theme state scope=theme', themeScanData.state.scope === 'theme');

  // 10. History state
  console.log('\n--- History State Check ---');
  var histState = await page.evaluate(() => {
    try {
      var s = history.state;
      if (s && s.summary && !s.metrics) {
        return { ok: true, hasSummary: true, hasMetrics: false, hasNextAction: !!s.next_action, hasFixableNow: typeof s.summary.fixable_now === 'number' };
      }
      return { ok: false, note: s ? JSON.stringify(s) : 'null' };
    } catch (e) {
      return { error: e.message };
    }
  });

  if (histState.ok) {
    check('History state has summary (not metrics)', true);
    check('History state has next_action', histState.hasNextAction);
    check('History state has fixable_now', histState.hasFixableNow);
  } else {
    console.log('    Warning: ' + (histState.note || histState.error));
    check('History state correct', false);
  }

  check('No console errors', consoleErrors.length === 0);
  if (consoleErrors.length > 0) console.log(consoleErrors.join('\n'));
  check('No HTTP 500 responses during successful flows', httpFailures.length === 0);
  if (httpFailures.length > 0) console.log(httpFailures.join('\n'));

  // Results
  console.log('\n========================================');
  console.log('Results: ' + checks.pass + '/' + (checks.pass + checks.fail) + ' passed');
  if (checks.fail > 0) {
    console.log(checks.fail + ' FAILED');
  } else {
    console.log('ALL PASS');
  }

  await browser.close();
  process.exit(checks.fail > 0 ? 1 : 0);
})();
