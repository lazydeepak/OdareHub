<?php
declare(strict_types=1);

/**
 * Generic Base probe: DECLARED app-contributed operator focus views must flow
 * through the Resolved Experience compatibility bridge without any hardcoded
 * business-app knowledge.
 *
 * Contract under test (Option A - declarative owner capability metadata):
 *   focus_views hooks declare their tokens additively via `focus_tokens`
 *   inside the EXISTING operator_surface hook; providers remain the runtime
 *   rendering map; core_app_hooks.payload_json materializes the declaration;
   * Base reads declarations only - it never executes business providers and
 *   NEVER infers tokens from hook-key prefixes.
 *
 * Fixture uses TWO synthetic tokens (fx-alpha, fx-beta) that differ from the
 * synthetic app key (basecatfx) so key-prefix inference can never pass.
 *
 * All fixture rows removed + pre-state verified in finally. No schema changes.
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';
require_once APP_ROOT . '/plugins/Base/Services/ResolvedExperienceDiagnosticsService.php';
require_once APP_ROOT . '/plugins/Base/Services/ResolvedExperienceConsumerService.php';

use App\Core\DB;

const FIXTURE_APP = 'basecatfx';
const FIXTURE_TOKENS = ['fx-alpha', 'fx-beta'];

$passed = 0;
$failed = 0;
$pass = static function (string $label) use (&$passed): void {
    $passed++;
    echo "  PASS [{$label}]\n";
};
$fail = static function (string $label, string $detail = '') use (&$failed): void {
    $failed++;
    echo "  FAIL [{$label}]" . ($detail !== '' ? ": {$detail}" : '') . "\n";
};

echo "== Base: declared contributed operator focus bridge ==\n";

try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

$ref = new ReflectionMethod(Plugins\Base\Services\ResolvedExperienceDiagnosticsService::class, 'contributedOperatorFocuses');
$focusTokens = static function () use ($ref): array {
    return array_column($ref->invoke(null), 'token');
};
$assignmentRowPayload = static function (string $assignedApps, string $operatorViews): array {
    return [
        // Synthetic assignment-row payload consumed by the resolved-experience gates.
        'user_id' => 0,
        'authority_role' => 'app_user',
        'dashboard_type' => 'operator', // row payload: operator surface type
        'assigned_apps' => $assignedApps, // row payload: owning-app availability
        'module_visibility' => '', // row payload: module visibility mode
        'display_surfaces' => '',
        'workspace_profile_key' => '',
        'operator_views' => $operatorViews, // row payload: experience inclusion list
        'me_dashboard_blocks' => '',
        'me_plugin_cards' => '',
    ];
};

$appCountBefore = (int)(DB::fetchOne('SELECT COUNT(*) c FROM core_apps WHERE app_key = ?', [FIXTURE_APP])['c'] ?? 0);
$hooksBefore = (int)(DB::fetchOne('SELECT COUNT(*) c FROM core_app_hooks WHERE app_key = ?', [FIXTURE_APP])['c'] ?? 0);
($appCountBefore === 0 && $hooksBefore === 0)
    ? $pass('fixture namespace clean before run')
    : $fail('fixture namespace clean before run');

$insertApp = static function (string $status) use ($fail): void {
    try {
        DB::query(
            "INSERT INTO core_apps (app_key, app_name, version, app_type, status, install_path, manifest_json, installed_by)
             VALUES (?, 'Base Catalog Fixture', '1.0.0', 'business', ?, 'apps/BaseCatalogFixture', '{}', 'probe')",
            [FIXTURE_APP, $status]
        );
    } catch (\Throwable $e) {
        $fail('fixture app insert', $e->getMessage());
    }
};
$insertHook = static function (array $tokens) use ($fail): void {
    try {
        DB::query(
            "INSERT INTO core_app_hooks (app_key, hook_type, hook_key, payload_json, is_enabled)
             VALUES (?, 'operator_surface', ?, ?, 1)",
            [
                FIXTURE_APP,
                FIXTURE_APP . '.operator.focus_views',
                json_encode([
                    'type' => 'operator_surface',
                    'key' => FIXTURE_APP . '.operator.focus_views',
                    'surface' => 'operator',
                    'region' => 'focus_views',
                    'focus_tokens' => $tokens,
                ]),
            ]
        );
    } catch (\Throwable $e) {
        $fail('fixture hook insert', $e->getMessage());
    }
};
$insertRouteHook = static function () use ($fail): void {
    try {
        DB::query(
            "INSERT INTO core_app_hooks (app_key, hook_type, hook_key, payload_json, is_enabled)
             VALUES (?, 'route', '/basecatfx/ignored', '{}', 1)",
            [FIXTURE_APP]
        );
    } catch (\Throwable $e) {
        $fail('fixture route hook insert', $e->getMessage());
    }
};

try {
    // ---- Multi-view discovery (tokens != app key) ----
    $insertApp('enabled');
    $insertHook(FIXTURE_TOKENS);

    $row =  $assignmentRowPayload(FIXTURE_APP, 'dashboard,fx-alpha,fx-beta');
    $views = Plugins\Base\Services\ResolvedExperienceConsumerService::operatorViews($row);
    (in_array('fx-alpha', $views, true) && in_array('fx-beta', $views, true))
        ? $pass('BOTH declared multi-view tokens resolve through operatorViews')
        : $fail('multi-view resolution', json_encode($views));
    (!in_array(FIXTURE_APP, $views, true))
        ? $pass('synthetic app key NOT fabricated as a focus token')
        : $fail('app key fabricated as focus token');

    $tokens = $focusTokens();
    foreach (FIXTURE_TOKENS as $t) {
        in_array($t, $tokens, true)
            ? $pass("discovery list contains declared token '{$t}'")
            : $fail("discovery list missing '{$t}'", json_encode($tokens));
    }
    (!in_array(FIXTURE_APP, $tokens, true))
        ? $pass('discovery never infers token from hook-key prefix')
        : $fail('prefix inference detected');

    // ---- Authorization separation gates ----
    $unassigned = Plugins\Base\Services\ResolvedExperienceConsumerService::operatorViews($assignmentRowPayload('', 'dashboard,fx-alpha,fx-beta'));
    (!in_array('fx-alpha', $unassigned, true) && !in_array('fx-beta', $unassigned, true))
        ? $pass('owning app not assigned -> both tokens excluded (assignment gate)')
        : $fail('assignment gate bypassed');

    $omitted = Plugins\Base\Services\ResolvedExperienceConsumerService::operatorViews( $assignmentRowPayload(FIXTURE_APP, 'dashboard,fx-alpha'));
    (!in_array('fx-beta', $omitted, true) && in_array('fx-alpha', $omitted, true))
        ? $pass('omitted token excluded while included token resolves independently')
        : $fail('override gate independence broken');

    in_array('dashboard', $views, true) && in_array('account', $views, true)
        ? $pass('dashboard/account allow-always preserved')
        : $fail('allow-always regression');

    // ---- Lifecycle: disabled app drops contributions ----
    DB::query('UPDATE core_apps SET status = ? WHERE app_key = ?', ['disabled', FIXTURE_APP]);
    $disabled = Plugins\Base\Services\ResolvedExperienceConsumerService::operatorViews( $assignmentRowPayload(FIXTURE_APP, 'dashboard,fx-alpha,fx-beta'));
    (!in_array('fx-alpha', $disabled, true) && !in_array('fx-beta', $disabled, true))
        ? $pass('disabled app contributions disappear (lifecycle gate)')
        : $fail('disabled app still contributes');

    // ---- Safety: malformed / builtin collision / duplicates / unrelated regions ----
    DB::query('UPDATE core_apps SET status = ? WHERE app_key = ?', ['enabled', FIXTURE_APP]);
    $insertHook(['Bad Token!', '']);                       // malformed entries ignored
    $insertHook(['dashboard']);                            // builtin collision attempt
    $insertHook(FIXTURE_TOKENS);                           // duplicate declaration
    $insertRouteHook();                                    // unrelated region/type ignored

    $tokens2 = $focusTokens();
    $fxCount = count(array_keys($tokens2, 'fx-alpha', true));
    (!in_array('bad token!', $tokens2, true) && !in_array('', $tokens2, true))
        ? $pass('malformed declared tokens rejected')
        : $fail('malformed tokens accepted', json_encode($tokens2));
    (!in_array('dashboard', $tokens2, true))
        ? $pass('builtin collision cannot be overridden by contribution')
        : $fail('builtin collision overridden');
    ($fxCount === 1)
        ? $pass('duplicate declarations collapse deterministically')
        : $fail('duplicate contributed tokens', (string)$fxCount);

    // ---- Current-repository multi-view regression (Manufacturing) ----
    (new App\Services\AppLocalDiscoveryService())->syncLocalApps();
    $mfgPayload = DB::fetchOne(
        "SELECT payload_json FROM core_app_hooks WHERE app_key = 'manufacturing' AND hook_key = 'manufacturing.operator.focus_views'"
    );
    $declaredMfg = [];
    if (is_array($mfgPayload)) {
        $pj = json_decode((string)$mfgPayload['payload_json'], true);
        $declaredMfg = (array)($pj['focus_tokens'] ?? []);
    }
    (count($declaredMfg) > 1)
        ? $pass('Manufacturing declares MULTIPLE focus views behind ONE hook (count=' . count($declaredMfg) . ')')
        : $fail('Manufacturing multi-view declaration', json_encode($declaredMfg));
    (!in_array('manufacturing', $tokens2, true))
        ? $pass('no fabricated manufacturing token from hook-key prefix')
        : $fail('fabricated manufacturing token');
    $expectedSubset = array_intersect(['dispatch_adapter', 'dispatch_detail'], $declaredMfg);
    $missingNew = array_diff($expectedSubset, $tokens2);
    ($missingNew === [])
        ? $pass('genuinely-new Manufacturing tokens (non-builtin) flow through discovery')
        : $fail('new Manufacturing tokens missing from discovery', json_encode($missingNew));
} finally {
    try {
        DB::query('DELETE FROM core_app_hooks WHERE app_key = ?', [FIXTURE_APP]);
        DB::query('DELETE FROM core_apps WHERE app_key = ?', [FIXTURE_APP]);
    } catch (\Throwable $e) {
        $fail('cleanup', $e->getMessage());
    }
    $leftApps = (int)(DB::fetchOne('SELECT COUNT(*) c FROM core_apps WHERE app_key = ?', [FIXTURE_APP])['c'] ?? -1);
    $leftHooks = (int)(DB::fetchOne('SELECT COUNT(*) c FROM core_app_hooks WHERE app_key = ?', [FIXTURE_APP])['c'] ?? -1);
    ($leftApps === 0 && $leftHooks === 0)
        ? $pass('cleanup restored fixture namespace exactly')
        : $fail('cleanup left residue', "apps={$leftApps} hooks={$leftHooks}");
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
