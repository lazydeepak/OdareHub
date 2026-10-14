<?php
declare(strict_types=1);

/**
 * OH-AUTO1-044 preparation-only regression probe.
 *
 * Uses synthetic source fixtures and never loads a staged autoloader, starts a
 * process, opens a socket, connects to a database, or executes SQL.
 */

require_once __DIR__ . '/IsolatedSourceRootTestRunner.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$temporary = sys_get_temp_dir() . '/oh-auto1-044-probe-' . bin2hex(random_bytes(6));
$repo = $temporary . '/fixture-repo';
$outside = $temporary . '/outside';
mkdir($repo . '/app', 0700, true);
mkdir($repo . '/platform', 0700, true);
mkdir($repo . '/plugins', 0700, true);
mkdir($repo . '/vendor/composer', 0700, true);
mkdir($repo . '/vendor/acme/runtime/src', 0700, true);
mkdir($repo . '/vendor/acme/runtime/tests', 0700, true);
mkdir($outside, 0700, true);
file_put_contents($repo . '/composer.json', "{}\n");
file_put_contents($repo . '/vendor/autoload.php', "<?php\n");
file_put_contents($repo . '/vendor/composer/installed.json', json_encode(['packages' => [['name' => 'acme/runtime']]]) . "\n");
file_put_contents($repo . '/vendor/acme/runtime/src/Runtime.php', "<?php\n");
file_put_contents($repo . '/vendor/acme/runtime/tests/RuntimeTest.php', "<?php\n");
file_put_contents($repo . '/app/fixture.php', "<?php\n");
file_put_contents($repo . '/platform/fixture.php', "<?php\n");
file_put_contents($repo . '/plugins/fixture.php', "<?php\n");
file_put_contents($repo . '/app/.env', "ERP_DB_PASS=forbidden\n");
file_put_contents($repo . '/app/credentials.txt', "forbidden\n");
foreach (IsolatedSourceRootTestRunner::databaseEntryPoints() as $entryPoint) {
    $path = $repo . '/' . $entryPoint;
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) {
        throw new RuntimeException('could not create fixture entry-point directory');
    }
    file_put_contents($path, "<?php\n// guarded DB probe fixture\nDB::conn();\n");
}

$cleanupFixture = static function () use ($temporary): void {
    $remove = static function (string $path) use (&$remove): void {
        if (is_link($path) || is_file($path)) { @unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $item) { $remove($item->getPathname()); }
        @rmdir($path);
    };
    $remove($temporary);
};

$prepared = null;
try {
    $prepared = IsolatedSourceRootTestRunner::prepare($repo);
    $manifest = $prepared['manifest'];
    $check(count($manifest['entry_points']) === 25, 'all 25 guarded entry points declared');
    $check(count(array_intersect($manifest['entry_points'], IsolatedSourceRootTestRunner::databaseEntryPoints())) === 25, 'entry-point manifest matches authority list');
    $check(!is_file($prepared['source_root'] . '/storage/db_config.php'), 'installed database config excluded');
    $check(!is_file($prepared['source_root'] . '/app/.env') && !is_file($prepared['source_root'] . '/app/credentials.txt'), 'credential-bearing files excluded');
    $check(is_file($prepared['source_root'] . '/vendor/acme/runtime/src/Runtime.php') && !is_file($prepared['source_root'] . '/vendor/acme/runtime/tests/RuntimeTest.php'), 'vendor runtime retained while test tree excluded');
    $check(($manifest['authority'] ?? null) === 'none' && $manifest['bootstrap_executed'] === false, 'preparation creates no execution authority');
    $check($manifest['database_connected'] === false && $manifest['sql_executed'] === false, 'preparation records no DB activity');
    $check(is_file($prepared['manifest_path']), 'reproducible manifest written');
    IsolatedSourceRootTestRunner::assertManifestUnchanged($prepared['source_root'], $manifest);
    $check(true, 'source manifest verifies unchanged');

    $mutated = $prepared['source_root'] . '/app/fixture.php';
    file_put_contents($mutated, "<?php changed\n");
    try { IsolatedSourceRootTestRunner::assertManifestUnchanged($prepared['source_root'], $manifest); $check(false, 'source mutation rejected'); }
    catch (Throwable) { $check(true, 'source mutation rejected'); }
    file_put_contents($mutated, "<?php\n");
    IsolatedSourceRootTestRunner::assertManifestUnchanged($prepared['source_root'], $manifest);

    try { IsolatedSourceRootTestRunner::validateRelativePath('../escape'); $check(false, 'path traversal rejected'); }
    catch (Throwable) { $check(true, 'path traversal rejected'); }
    try { IsolatedSourceRootTestRunner::cleanup($repo); $check(false, 'cleanup outside runner workspace rejected'); }
    catch (Throwable) { $check(true, 'cleanup outside runner workspace rejected'); }

    $workspace = $prepared['workspace'];
    IsolatedSourceRootTestRunner::cleanup($workspace);
    $check(!is_dir($workspace), 'runner-owned cleanup removes only prepared workspace');
    $prepared = null;
} finally {
    if (is_array($prepared) && is_dir($prepared['workspace'])) {
        IsolatedSourceRootTestRunner::cleanup($prepared['workspace']);
    }
    $cleanupFixture();
}

// Missing source root, installed workspace, dependency gap, and symlink escape.
$cases = static function () use ($temporary, $outside): array {
    $build = static function (string $name, bool $dependencies = true) use ($temporary): string {
        $root = $temporary . '/' . $name;
        mkdir($root . '/app', 0700, true);
        mkdir($root . '/apps', 0700, true);
        mkdir($root . '/platform', 0700, true);
        mkdir($root . '/plugins', 0700, true);
        file_put_contents($root . '/composer.json', "{}\n");
        if ($dependencies) {
            mkdir($root . '/vendor/composer', 0700, true);
            file_put_contents($root . '/vendor/autoload.php', "<?php\n");
            file_put_contents($root . '/vendor/composer/installed.json', '{"packages":[]}');
        }
        foreach (IsolatedSourceRootTestRunner::databaseEntryPoints() as $entryPoint) {
            $path = $root . '/' . $entryPoint;
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) {
                throw new RuntimeException('could not create negative fixture directory');
            }
            file_put_contents($path, "<?php\nDB::conn();\n");
        }
        return $root;
    };
    $missing = $build('missing-source');
    rmdir($missing . '/platform');
    $dependencyGap = $build('missing-dependency', false);
    $symlinkRoot = $build('symlink-escape');
    symlink($outside, $symlinkRoot . '/app/escape');
    return [$missing, $dependencyGap, $symlinkRoot];
};
$caseRoots = $cases();
foreach ([[$caseRoots[0], 'missing source fails closed'], [$caseRoots[1], 'missing dependencies fail closed'], [$caseRoots[2], 'symlink escape fails closed']] as [$caseRoot, $label]) {
    try { IsolatedSourceRootTestRunner::prepare($caseRoot); $check(false, $label); }
    catch (Throwable) { $check(true, $label); }
}
try { IsolatedSourceRootTestRunner::prepare($caseRoots[0], $caseRoots[0] . '/workspace'); $check(false, 'installed workspace path rejected'); }
catch (Throwable) { $check(true, 'installed workspace path rejected'); }

$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) { return; }
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $item) { $remove($item->getPathname()); }
    @rmdir($path);
};
$remove($temporary);

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
