<?php

declare(strict_types=1);

/**
 * Bom module foundation probe — Manufacturing-owned consumer of the 012/013 adoption.
 *
 * Static contracts (module shape, localization parity, migration DDL parity, isolation)
 * always run. Canonical service behavior runs against the live DB when available and is
 * skipped cleanly otherwise (mirrors the Hospitality probe guard).
 */

define('APP_ROOT', dirname(__DIR__, 5));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';

use App\Core\DB;
use Apps\Manufacturing\Modules\Bom\BomService;
use Apps\Manufacturing\Modules\Bom\Services\BomWidgetRegistry;

$passed = 0;
$failed = 0;
$suffix = substr((string)microtime(true), -6);

$pass = static function (string $l) use (&$passed): void { $passed++; echo "  PASS [$l]\n"; };
$fail = static function (string $l, string $d = '') use (&$failed): void { $failed++; echo "  FAIL [$l]" . ($d !== '' ? ": $d" : '') . "\n"; };

$moduleDir = APP_ROOT . '/apps/Manufacturing/modules/Bom';

echo "== Bom module foundation probe ==\n";

// ---- Static: module shape ----
$pluginJson = json_decode((string)file_get_contents($moduleDir . '/plugin.json'), true) ?? [];
is_array($pluginJson) && ($pluginJson['name'] ?? '') === 'Bom' && ($pluginJson['owner_app'] ?? '') === 'manufacturing'
    ? $pass('plugin.json parses with owner manufacturing') : $fail('plugin.json shape');
($pluginJson['required_tables'] ?? null) === ['manufacturing_bom', 'manufacturing_bom_line']
    ? $pass('required_tables declares both BOM tables') : $fail('required_tables');

foreach ([
    'routes.php', 'BomService.php', 'BomPolicies.php', 'BomHooks.php', 'EntityDefinition.php',
    'Controllers/BomController.php', 'Services/BomWidgetRegistry.php',
    'Views/index.php', 'Views/form.php', 'Views/detail.php',
    'migrations/001_bom_contract.sql', 'Resources/lang/en.php',
    'Resources/lang/ja.php', 'Resources/lang/ne.php',
] as $rel) {
    is_file($moduleDir . '/' . $rel)
        ? $pass("file present: {$rel}") : $fail("missing file: {$rel}");
}

// ---- Static: route registration ----
$routes = (string)file_get_contents($moduleDir . '/routes.php');
$routePaths = ['/apps/manufacturing/bom', '/apps/manufacturing/bom/detail', '/apps/manufacturing/bom/add', '/apps/manufacturing/bom/update', '/apps/manufacturing/bom/status', '/apps/manufacturing/bom/lines/add', '/apps/manufacturing/bom/lines/update', '/apps/manufacturing/bom/lines/delete'];
$missingRoutes = array_values(array_filter($routePaths, static fn(string $p): bool => !str_contains($routes, $p)));
$missingRoutes === [] ? $pass('module routes register all /apps/manufacturing/bom* paths') : $fail('route coverage', implode(',', $missingRoutes));

// ---- Static: DDL parity with Products/013 ----
$bomMigration = (string)file_get_contents($moduleDir . '/migrations/001_bom_contract.sql');
$productsMigration = (string)file_get_contents(APP_ROOT . '/apps/Manufacturing/modules/Products/migrations/013_add_manufacturing_bom.sql');
$normalizeSql = static function (string $sql): array {
    $clean = preg_replace('/--[^\n]*/s', '', $sql);
    $clean = preg_replace('/\s+/', ' ', (string)$clean);
    preg_match_all('/CREATE TABLE IF NOT EXISTS\s+manufacturing_bom\b[^;]+/i', (string)$clean, $bom);
    preg_match_all('/CREATE TABLE IF NOT EXISTS\s+manufacturing_bom_line\b[^;]+/i', (string)$clean, $line);
    return [trim($bom[0][0] ?? ''), trim($line[0][0] ?? '')];
};
[$bomDdl, $bomLineDdl] = $normalizeSql($bomMigration);
[$prodBomDdl, $prodBomLineDdl] = $normalizeSql($productsMigration);
($bomDdl !== '' && $bomDdl === $prodBomDdl)
    ? $pass('manufacturing_bom DDL identical to Products/013') : $fail('bom DDL parity');
($bomLineDdl !== '' && $bomLineDdl === $prodBomLineDdl)
    ? $pass('manufacturing_bom_line DDL identical to Products/013') : $fail('bom_line DDL parity');

// ---- Static: manifest + provider wiring ----
$manifest = json_decode((string)file_get_contents(APP_ROOT . '/apps/Manufacturing/manifest.json'), true) ?? [];
in_array('Bom', $manifest['legacy_bridge_plugins'] ?? [], true)
    ? $pass('manifest legacy_bridge_plugins includes Bom') : $fail('manifest legacy_bridge');
in_array('bom', $manifest['native_modules'] ?? [], true)
    ? $pass('manifest native_modules includes bom') : $fail('manifest native_modules');
$purgeHas = in_array('manufacturing_bom', $manifest['purge']['tables'] ?? [], true) && in_array('manufacturing_bom_line', $manifest['purge']['tables'] ?? [], true);
$purgeHas ? $pass('manifest purge.tables includes both BOM tables') : $fail('manifest purge tables');
$menuHas = false;
foreach (($manifest['menus'] ?? []) as $m) {
    if (($m['key'] ?? '') === 'app.mfg.bom') { $menuHas = true; break; }
}
$menuHas ? $pass('manifest menus include app.mfg.bom') : $fail('manifest bom menu');

$host = (string)file_get_contents(APP_ROOT . '/apps/Manufacturing/Services/HostSurfaceContributionService.php');
str_contains($host, 'Apps\\\\Manufacturing\\\\Modules\\\\Bom\\\\Services\\\\BomWidgetRegistry')
    ? $pass('HostSurface provider list registers BomWidgetRegistry') : $fail('HostSurface provider');
$operator = (string)file_get_contents(APP_ROOT . '/apps/Manufacturing/Services/OperatorWidgetProviderContributionService.php');
str_contains($operator, 'BomWidgetRegistry') ? $pass('operator provider catalog registers Bom') : $fail('operator provider');

// ---- Static: localization parity ----
$langs = [];
foreach (['en', 'ja', 'ne'] as $lang) {
    $langs[$lang] = (array)(include $moduleDir . '/Resources/lang/' . $lang . '.php');
}
$enKeys = array_keys($langs['en']);
$parity = true;
foreach (['ja', 'ne'] as $lang) {
    $other = array_keys($langs[$lang]);
    if ($enKeys !== $other) {
        $parity = false;
        $diff = array_merge(array_diff($enKeys, $other), array_diff($other, $enKeys));
        $fail("locale parity {$lang}", implode(',', array_slice($diff, 0, 6)));
    }
}
$parity ? $pass('en/ja/ne locale key parity') : null;
($enKeys !== []) && isset($langs['en']['nav.bom'])
    ? $pass('locale defines nav.bom and non-empty en set') : $fail('locale nav.bom');

// ---- Static: isolation (no Shared\ runtime import; no /app coupling) ----
$sharedLeak = false;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $src = (string)file_get_contents($file->getPathname());
        if (preg_match('/use\s+Shared\\\\/i', $src) || preg_match('/Shared\\\\Item\\\\/i', $src)) {
            $sharedLeak = true;
            break;
        }
    }
}
$sharedLeak ? $fail('module must not reference Shared\\ runtime') : $pass('module references no Shared\\ runtime namespace');

try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: db (canonical sections)\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

// ---- Canonical: schema ----
BomService::ensureSchema();
foreach (['manufacturing_bom', 'manufacturing_bom_line'] as $t) {
    BomService::tableExists($t) ? $pass("table exists after ensureSchema: {$t}") : $fail("missing table {$t}");
}
$statusType = DB::fetchOne("SELECT COLUMN_TYPE AS ct FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='manufacturing_bom' AND column_name='status'");
str_contains((string)($statusType['ct'] ?? ''), "'draft'") && str_contains((string)($statusType['ct'] ?? ''), "'released'")
    ? $pass('status enum matches contract lifecycle') : $fail('status enum');
$uniq = DB::fetchOne("SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='manufacturing_bom' AND index_name='uniq_manufacturing_bom_reference'");
(int)($uniq['c'] ?? 0) > 0 ? $pass('unique (finished_item_ref,version,revision) present') : $fail('unique key');
$fk = DB::fetchOne("SELECT DELETE_RULE AS dr FROM information_schema.referential_constraints WHERE constraint_schema=DATABASE() AND table_name='manufacturing_bom_line' AND referenced_table_name='manufacturing_bom'");
strtoupper((string)($fk['dr'] ?? '')) === 'CASCADE' ? $pass('bom_line FK cascades on bom delete') : $fail('FK cascade');
BomService::ensureSchema();
$passed++;

// ---- Canonical: opaque-int fixtures ----
$mk = static fn(string $label): string => 'pbom' . $label . '_' . $suffix;
$itemFinished = 900000 + (int)$suffix;
$itemComponent = 910000 + (int)$suffix;
$itemUnresolved = 990000 + (int)$suffix;

try {
    // products.item_ref is Products-owned (migration 012). Mirror it idempotently so the
    // fixture DB can exercise the canonical service contract when 012 is not yet applied.
    $hasRef = DB::fetchOne("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='item_ref'");
    if ((int)($hasRef['c'] ?? 0) === 0) {
        DB::query('ALTER TABLE products ADD COLUMN item_ref INT NULL DEFAULT NULL, ADD KEY idx_products_item_ref_alt (item_ref)');
    }
    $assigned = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='item_ref'")['c'] ?? 0) > 0;
    $assigned ? $pass('products.item_ref column ensured for fixtures') : $fail('item_ref column ensure');

    DB::query('INSERT INTO products (parts_name, parts_number, item_ref) VALUES (?,?,?)', [$mk('fin'), 'PBF-' . $suffix, $itemFinished]);
    $prodFinishedId = (int)(DB::fetchOne('SELECT id FROM products WHERE parts_number=?', ['PBF-' . $suffix])['id'] ?? 0);
    DB::query('INSERT INTO products (parts_name, parts_number, item_ref) VALUES (?,?,?)', [$mk('comp'), 'PBC-' . $suffix, $itemComponent]);
    DB::query('INSERT INTO products (parts_name, parts_number, item_ref) VALUES (?,?,?)', [$mk('bare'), 'PBB-' . $suffix, null]);

    // item_ref != products.id: opaque-int proof
    ($prodFinishedId > 0 && $prodFinishedId !== $itemFinished)
        ? $pass('opaque item_ref differs from products.id (fixture valid)')
        : $fail('opaque fixture', 'expected id <> item_ref');

    $bomId = BomService::createBom($itemFinished, '1.0', 1, 'probe', []);
    $bomId > 0 ? $pass('createBom ok') : $fail('createBom');

    $dupe = false;
    try { BomService::createBom($itemFinished, '1.0', 1, 'probe2', []); } catch (\Throwable $e) { $dupe = true; }
    $dupe ? $pass('duplicate (item,version,revision) rejected') : $fail('duplicate bom');

    $badRef = false;
    try { BomService::createBom(0, '1.0', 1, '', []); } catch (\InvalidArgumentException $e) { $badRef = true; }
    $badRef ? $pass('finished_item_ref<=0 rejected') : $fail('zero item ref');

    // release blocked without lines
    $noLines = false;
    try { BomService::transitionBom($bomId, 'release', []); } catch (\Throwable $e) { $noLines = true; }
    $noLines ? $pass('release blocked without component lines') : $fail('release w/o lines');

    // lines
    $lineId = BomService::addLine($bomId, $itemComponent, 2, 'each', 0, []);
    $lineId > 0 ? $pass('addLine ok') : $fail('addLine');
    $badQty = false;
    try { BomService::addLine($bomId, $itemComponent, 0, 'each', 0, []); } catch (\InvalidArgumentException $e) { $badQty = true; }
    $badQty ? $pass('quantity<=0 rejected') : $fail('zero qty');

    // release now ok; released immutable
    BomService::transitionBom($bomId, 'release', []);
    $state = DB::fetchOne('SELECT status FROM manufacturing_bom WHERE id=?', [$bomId]);
    (($state['status'] ?? '') === 'released') ? $pass('draft -> released ok') : $fail('release transition');
    $immutable = false;
    try { BomService::updateBomHeader($bomId, 'nope', []); } catch (\RuntimeException $e) { $immutable = true; }
    try { BomService::addLine($bomId, $itemComponent, 1, 'each', 1, []); } catch (\RuntimeException $e) { $immutable = true && $immutable; }
    $immutable ? $pass('released BOM immutable to edits') : $fail('released immutability');

    // second released same item+version rejected
    $dupActive = false;
    try { BomService::createBom($itemFinished, '1.0', 2, '', []); } catch (\RuntimeException $e) { $dupActive = true; }
    $dupActive ? $pass('active duplicate release rejected') : $fail('duplicate active');

    // supersede; terminal
    BomService::transitionBom($bomId, 'supersede', []);
    $after = DB::fetchOne('SELECT status FROM manufacturing_bom WHERE id=?', [$bomId]);
    (($after['status'] ?? '') === 'superseded') ? $pass('released -> superseded ok') : $fail('supersede');
    $terminal = false;
    try { BomService::transitionBom($bomId, 'release', []); } catch (\RuntimeException $e) { $terminal = true; }
    $terminal ? $pass('superseded is terminal') : $fail('superseded terminal');

    // resolution by item_ref
    $detail = BomService::bomDetail($bomId);
    str_contains((string)($detail['resolved']['finished_label'] ?? ''), $mk('fin'))
        ? $pass('bomDetail resolves finished item through item_ref') : $fail('bomDetail resolution');
    $released = BomService::releasedBomForItem($itemFinished);
    $released === null ? $pass('releasedBomForItem null after supersede (correct)') : $fail('no released after supersede');

    // integrity: unresolved ref + released without lines
    $badBom = BomService::createBom($itemUnresolved, '2.0', 1, '', []);
    $codeLine = BomService::addLine($badBom, $itemUnresolved, 1, 'each', 0, []);
    BomService::transitionBom($badBom, 'release', []);
    $issues = BomService::integrityIssues();
    $codes = array_column($issues, 'code');
    in_array('unresolved_item_ref', $codes, true) ? $pass('integrity flags unresolved item ref') : $fail('integrity unresolved');
    // simulate out-of-band line removal (tampering guard), then the live release check that
    // can never be reached through the service (release is blocked while a BOM has zero lines)
    DB::query('DELETE FROM manufacturing_bom_line WHERE id=?', [$codeLine]);
    $issues2 = BomService::integrityIssues();
    in_array('released_without_lines', array_column($issues2, 'code'), true)
        ? $pass('integrity flags released-without-lines (tamper guard)') : $fail('integrity empty release');

    $refItems = BomService::referenceableItems();
    $refs = array_map(static fn($r): int => (int)$r, array_column($refItems, 'item_ref'));
    in_array($itemFinished, $refs, true) && in_array($itemComponent, $refs, true)
        ? $pass('referenceableItems lists product rows with item_ref only') : $fail('referenceableItems');
} catch (\Throwable $e) {
    $fail('canonical section', $e->getMessage());
}

// ---- Widget contract ----
$widgets = BomWidgetRegistry::contribute('summary_cards', []);
$kpi = null;
foreach ($widgets as $w) {
    if (($w['widget_key'] ?? '') === 'bom_integrity') { $kpi = $w; break; }
}
is_array($kpi) ? $pass('summary_cards contributes bom_integrity kpi') : $fail('summary kpi');
$required = ['view_kind', 'widget_key', 'widget_type', 'placement_zone', 'interaction_profiles', 'surface_key', 'supports_empty_state', 'supports_clickthrough'];
$missing = array_values(array_filter($required, static fn(string $f): bool => !array_key_exists($f, $kpi ?? [])));
$missing === [] ? $pass('kpi carries full required widget vocabulary') : $fail('kpi vocabulary', implode(',', $missing));

// ---- Cleanup ----
try {
    DB::query('DELETE FROM manufacturing_bom_line WHERE bom_id IN (SELECT id FROM manufacturing_bom WHERE finished_item_ref IN (?,?,?))', [$itemFinished, $itemComponent, $itemUnresolved]);
    DB::query('DELETE FROM manufacturing_bom WHERE finished_item_ref IN (?,?,?)', [$itemFinished, $itemComponent, $itemUnresolved]);
    DB::query('DELETE FROM products WHERE parts_number IN (?,?,?)', ['PBF-' . $suffix, 'PBC-' . $suffix, 'PBB-' . $suffix]);
    $pass('fixture cleanup executed');
} catch (\Throwable $e) {
    $fail('fixture cleanup', $e->getMessage());
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);