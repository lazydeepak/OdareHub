#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_storage_runtime_retention_policy"
echo "- read-only diagnostic for storage runtime artifact retention policy"

PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [[ -z "$PHP_BIN" ]]; then
  echo "missing required binary: php" >&2
  exit 2
fi

"$PHP_BIN" <<'PHP'
<?php
declare(strict_types=1);

$root = getcwd();
$warnings = [];
$failures = [];

/** @return list<string> */
function files_under(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    if (is_file($path)) {
        return [$path];
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        if ($file->getFilename() === '.DS_Store') {
            continue;
        }
        $files[] = $file->getPathname();
    }
    sort($files);
    return $files;
}

function relpath(string $root, string $path): string
{
    return ltrim(str_replace(rtrim($root, '/') . '/', '', $path), '/');
}

/** @return array<string,true> */
function tracked_files(string $root): array
{
    $output = [];
    $code = 0;
    exec('git -C ' . escapeshellarg($root) . ' ls-files storage', $output, $code);
    if ($code !== 0) {
        return [];
    }
    $tracked = [];
    foreach ($output as $line) {
        $line = trim($line);
        if ($line !== '') {
            $tracked[$line] = true;
        }
    }
    return $tracked;
}

/** @return list<string> */
function grep_files(string $root, string $needle): array
{
    $scanRoots = ['app', 'apps', 'plugins', 'packages', 'public', 'scripts', 'docs'];
    $matches = [];
    foreach ($scanRoots as $scanRoot) {
        $path = $root . '/' . $scanRoot;
        if (!file_exists($path)) {
            continue;
        }
        $iterator = is_dir($path)
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS))
            : new ArrayIterator([new SplFileInfo($path)]);
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $filePath = $file->getPathname();
            if (str_contains($filePath, '/vendor/') || str_contains($filePath, '/.git/')) {
                continue;
            }
            $raw = @file_get_contents($filePath);
            if ($raw !== false && str_contains($raw, $needle)) {
                $matches[] = relpath($root, $filePath);
            }
        }
    }
    sort($matches);
    return array_values(array_unique($matches));
}

/** @return array{files:int,tracked:int,examples:list<string>} */
function area_counts(string $root, string $path, array $tracked): array
{
    $files = files_under($root . '/' . $path);
    $trackedCount = 0;
    $examples = [];
    foreach ($files as $file) {
        $rel = relpath($root, $file);
        if (isset($tracked[$rel])) {
            $trackedCount++;
        }
        if (count($examples) < 4) {
            $examples[] = $rel;
        }
    }
    return ['files' => count($files), 'tracked' => $trackedCount, 'examples' => $examples];
}

$tracked = tracked_files($root);

$areas = [
    ['storage/appstudio/apps_registry.json', 'Studio', 'RUNTIME_STATE', 'Studio generated app lifecycle registry', 'Studio loaders and generated archive diagnostics', 'Studio apply/publish flows', 'high', 'retain; read-only diagnostics; governed Studio lifecycle edits', 'delete or rewrite before generated app retirement policy', 'retain indefinitely while any generated app exists; back up before generated app archive/delete'],
    ['storage/appstudio/generated_data', 'Studio + generated app providers', 'GENERATED_DATA', 'runtime data for generated app modules', 'generated module providers', 'Studio generated module apply flows and runtime edits', 'high', 'retain per generated app until registry, app code, snapshots, routes, public assets, and docs are retired together', 'delete as sample cleanup or public asset cleanup', 'retain for lifetime of generated app; archive with app when app is retired'],
    ['storage/appstudio/snapshots', 'Studio', 'SNAPSHOT_EVIDENCE', 'generated compile/apply snapshot evidence', 'Studio diagnostics, rollback and archive review', 'Studio compile/apply flows', 'high', 'retain; introduce dated retention after rollback window and archive export exist', 'delete before apply/audit history is reconciled', 'retain at least through rollback/support window; preserve latest per generated app plus any referenced by audit/apply records'],
    ['storage/appstudio/applies', 'Studio', 'AUDIT_EVIDENCE', 'apply operation records', 'Studio apply history and diagnostics', 'Studio apply flows', 'high', 'retain; compact only with signed audit summary policy', 'delete before writer inventory and compliance decision', 'retain as audit evidence; immutable except governed redaction/compaction policy'],
    ['storage/appstudio/apply_log.json', 'Studio', 'AUDIT_EVIDENCE', 'Studio apply log', 'Studio hardening diagnostics and apply history', 'Studio apply flows', 'high', 'retain with applies/audit records', 'delete or truncate without audit retention decision', 'retain with apply records'],
    ['storage/appstudio/apply_log.ndjson', 'Studio', 'AUDIT_EVIDENCE', 'append-friendly Studio apply log', 'Studio hardening diagnostics and apply history', 'Studio apply flows', 'high', 'retain with applies/audit records', 'delete or truncate without audit retention decision', 'retain with apply records'],
    ['storage/appstudio/audit', 'Studio governance', 'AUDIT_EVIDENCE', 'compile and publish audit records', 'Studio governance services and diagnostics', 'Studio governance service', 'high', 'retain; add retention/compaction policy later', 'delete before governance retention policy', 'retain as governance evidence; never delete without retention ticket'],
    ['storage/appstudio/packages', 'Studio package/export lifecycle', 'PACKAGE_ARTIFACT', 'generated snapshot packages and signatures', 'Studio package services', 'Studio package/export flows', 'medium', 'retain until package export retention policy and download references are known', 'delete package zip/signature independently', 'retain zip and signature as a pair until package TTL or archive ledger exists'],
    ['storage/appstudio/publish_decisions', 'Studio governance', 'AUDIT_EVIDENCE', 'publish approval decision records', 'Studio governance and publish gate diagnostics', 'Studio governance service', 'high', 'retain; use as generated/public publish provenance', 'delete before generated/public cleanup decisions', 'retain at least as long as related generated app and public output exist'],
    ['storage/appstudio/registry.json', 'Studio compatibility', 'RUNTIME_STATE', 'legacy/current Studio registry reflection', 'Base admin apps view and Studio services', 'Studio registry/apply flows', 'high', 'retain until apps_registry/registry split is resolved', 'treat as duplicate dead registry', 'retain as compatibility truth until no readers remain'],
    ['storage/appstudio/schema_registry.json', 'Studio schema governance', 'RUNTIME_STATE', 'Studio schema governance registry', 'Studio schema governance service', 'Studio schema governance service', 'high', 'retain; owner-governed schema updates only', 'delete during generated sample cleanup', 'retain while Studio schema governance is enabled'],
    ['storage/appstudio/history.json', 'Studio', 'AUDIT_EVIDENCE', 'Studio history timeline', 'Studio history views/diagnostics', 'Studio apply/compile flows', 'medium', 'retain with Studio audit evidence', 'delete before history reader retirement', 'retain until history is migrated or compacted by policy'],
    ['storage/tools', 'System tools/developer tooling', 'TOOL_STATE', 'developer/reset and Studio placeholder tooling state', 'tools and manual workflows', 'tooling authors', 'medium', 'retain tracked tool fixtures; classify untracked tool outputs separately', 'delete as temp without owner review', 'tracked fixtures remain source; untracked outputs need owner TTL'],
    ['storage/tmp', 'Shell/data exchange/runtime jobs', 'TEMP_ARTIFACT', 'temporary data exchange and job files', 'Shell data exchange and setup flows', 'runtime data exchange jobs', 'medium', 'retain until TTL policy and active job detection exist', 'blanket delete in cleanup batch', 'preserve active-day files; delete only by TTL after job completion markers exist'],
    ['storage/environment_snapshots', 'Platform/environment portability', 'RELEASE_PREVIEW', 'environment snapshot exports', 'environment snapshot service', 'environment snapshot service', 'medium', 'retain until snapshot export TTL/download references are known', 'delete before export retention decision', 'retain latest successful snapshot and metadata until release/export retention policy exists'],
    ['storage/release_previews', 'Release packaging', 'RELEASE_PREVIEW', 'release preview records', 'release history service', 'release packaging flow', 'medium', 'retain until release workflow TTL exists', 'delete before release history policy', 'retain until release finalized or expired by explicit TTL'],
    ['storage/releases', 'Release packaging', 'PACKAGE_ARTIFACT', 'release package artifacts', 'release packaging/history services', 'release packaging service', 'medium', 'retain by release artifact policy', 'delete without release ledger/archive', 'retain published release artifacts indefinitely unless superseded policy exists'],
    ['storage/restore_previews', 'Restore/import lifecycle', 'RESTORE_PREVIEW', 'restore preview records', 'restore history service and app restore controllers', 'restore preview flows', 'medium', 'retain until restore preview TTL and completion state exist', 'delete before restore workflow TTL', 'retain until restore is applied/cancelled plus TTL'],
    ['storage/restore_uploads', 'Restore/import lifecycle', 'RESTORE_PREVIEW', 'uploaded restore packages', 'restore controllers', 'restore upload flows', 'medium', 'retain until upload TTL and job state exist', 'delete while restore preview may reference upload', 'retain until restore workflow closes plus TTL'],
    ['storage/restore_tmp', 'Restore/import lifecycle', 'TEMP_ARTIFACT', 'restore staging files', 'suite restore service', 'restore service', 'medium', 'clear only by restore service TTL/job cleanup', 'manual cleanup without job state', 'retain active jobs; expire completed staging by TTL'],
    ['storage/exports', 'Package/export lifecycle', 'PACKAGE_ARTIFACT', 'suite/app/module export files', 'suite export and download flows', 'suite export service', 'medium', 'retain until export artifact TTL/download policy exists', 'delete before export history is reconciled', 'retain latest exports until explicit TTL/archive policy'],
    ['storage/plugin_exports', 'Package/plugin lifecycle', 'PACKAGE_ARTIFACT', 'plugin export packages', 'PackageManager and download flows', 'PackageManager export flows', 'medium', 'retain until plugin export retention policy exists', 'delete while plugin export references may exist', 'retain export packages by plugin/version until TTL'],
    ['storage/tmp_plugins', 'Package/plugin lifecycle', 'TEMP_ARTIFACT', 'plugin install/import staging', 'PackageManager', 'PackageManager install/import flows', 'medium', 'clear only through PackageManager-safe TTL', 'manual delete during install/import work', 'retain active installs; expire completed staging by TTL'],
    ['storage/app_files', 'Runtime uploads', 'RUNTIME_STATE', 'app/customer file storage', 'app-specific runtime flows', 'app/runtime upload flows', 'high', 'retain; app-owner cleanup only', 'delete as repository clutter', 'customer/runtime state: retain unless app owner defines policy'],
    ['storage/branding', 'Platform/Organization branding', 'RUNTIME_STATE', 'uploaded and derived branding assets', 'LogoUploadService, BrandingVariantService, OrganizationService, Shell routes', 'Platform organization settings', 'high', 'retain; owner-governed orphan sweep only', 'manual delete without organization asset references check', 'retain active and referenced assets; orphan sweep only by Platform service policy'],
    ['storage/logs', 'Runtime diagnostics', 'AUDIT_EVIDENCE', 'runtime logs and lifecycle logs', 'runtime, app lifecycle diagnostics, docs', 'runtime logging', 'medium', 'rotate/expire by log policy', 'delete active logs without rotation policy', 'retain recent logs; archive or rotate by environment policy'],
    ['storage/db.sqlite', 'Local runtime database', 'RUNTIME_STATE', 'local database state', 'DB layer/runtime', 'runtime/database setup', 'critical', 'retain; never cleanup in migration batches', 'delete/reset without explicit DB operation approval', 'environment-owned runtime state; backup before any DB lifecycle operation'],
    ['storage/db_config.php', 'Environment configuration', 'RUNTIME_STATE', 'local DB credentials/config', 'DB helpers and setup', 'setup/environment operator', 'critical', 'retain local secret; never commit real credentials', 'delete or commit real credentials', 'environment-owned config; example only belongs in git'],
    ['storage/db_config.php.example', 'Setup fixture', 'TEST_FIXTURE', 'tracked config template', 'setup docs and installers', 'repo maintainers', 'low', 'owner-reviewed template edits', 'delete during runtime cleanup', 'retain in git as install fixture'],
    ['storage/architecture_policy.php', 'Architecture policy compatibility', 'RUNTIME_STATE', 'architecture policy config', 'PluginManager', 'setup/platform policy writers', 'medium', 'retain until policy source owner is clarified', 'delete as unknown storage file', 'retain until policy ownership decision exists'],
    ['storage/schema_snapshots', 'Schema tooling', 'SNAPSHOT_EVIDENCE', 'schema snapshot placeholders/artifacts', 'schema tools if enabled', 'schema tooling', 'medium', 'retain tracked placeholders; add TTL for generated snapshots', 'delete without schema tooling review', 'retain tracked placeholder and latest schema evidence'],
    ['storage/environment_clone_previews', 'Environment portability', 'RESTORE_PREVIEW', 'environment clone preview records', 'environment portability history service', 'environment clone flow', 'medium', 'retain until preview TTL exists', 'delete before clone workflow TTL', 'retain until clone is applied/cancelled plus TTL'],
    ['storage/environment_clone_uploads', 'Environment portability', 'TEMP_ARTIFACT', 'environment clone uploads', 'setup/environment clone flows', 'environment clone upload flow', 'medium', 'clear only by clone workflow TTL', 'manual delete during clone flow', 'retain active uploads; expire closed jobs by TTL'],
    ['storage/import_previews', 'Import lifecycle', 'RESTORE_PREVIEW', 'import preview records', 'ImportHistoryService', 'import preview flows', 'medium', 'retain until import preview TTL exists', 'delete before import workflow TTL', 'retain until import is applied/cancelled plus TTL'],
    ['storage/import_uploads', 'Import lifecycle', 'TEMP_ARTIFACT', 'import upload files', 'Manufacturing/SBAIO import controllers', 'import upload flows', 'medium', 'clear only by import TTL/job cleanup', 'manual delete during import flow', 'retain active uploads; expire closed jobs by TTL'],
    ['storage/legacy_app_backups', 'App lifecycle', 'SNAPSHOT_EVIDENCE', 'legacy app backup artifacts', 'app lifecycle/setup flows if referenced', 'app lifecycle flows', 'medium', 'retain until app backup retention policy exists', 'delete before legacy app backup inventory', 'retain until replacement archive ledger exists'],
];

echo 'path|classification|owner|current_role|tracked_files|total_files|readers|writers|cleanup_risk|allowed_future_action|blocked_future_action|minimum_retention_rule' . PHP_EOL;
foreach ($areas as $area) {
    [$path, $owner, $classification, $role, $readerHint, $writerHint, $risk, $allowed, $blocked, $retention] = $area;
    $counts = area_counts($root, $path, $tracked);
    $readers = grep_files($root, $path);
    $readerText = $readers === [] ? $readerHint : implode(',', array_slice($readers, 0, 8));
    if (count($readers) > 8) {
        $readerText .= ',...';
    }
    echo implode('|', [
        $path,
        $classification,
        $owner,
        $role,
        (string)$counts['tracked'],
        (string)$counts['files'],
        $readerText,
        $writerHint,
        $risk,
        $allowed,
        $blocked,
        $retention,
    ]) . PHP_EOL;
    if ($counts['files'] > 0 && $counts['tracked'] < $counts['files'] && in_array($classification, ['RUNTIME_STATE', 'GENERATED_DATA', 'SNAPSHOT_EVIDENCE', 'AUDIT_EVIDENCE', 'PACKAGE_ARTIFACT', 'TEMP_ARTIFACT', 'RESTORE_PREVIEW', 'RELEASE_PREVIEW'], true)) {
        $warnings[] = "{$path}: contains untracked operational artifacts; cleanup requires owner retention policy";
    }
}

$trackedStorage = array_keys($tracked);
sort($trackedStorage);

echo PHP_EOL . 'metrics:' . PHP_EOL;
echo 'storage.tracked_files=' . count($trackedStorage) . PHP_EOL;
echo 'storage.tracked_file_list=' . implode(',', $trackedStorage) . PHP_EOL;
echo 'storage.appstudio.generated_data.files=' . count(files_under($root . '/storage/appstudio/generated_data')) . PHP_EOL;
echo 'storage.appstudio.snapshots.files=' . count(files_under($root . '/storage/appstudio/snapshots')) . PHP_EOL;
echo 'storage.appstudio.applies.files=' . count(files_under($root . '/storage/appstudio/applies')) . PHP_EOL;
echo 'storage.appstudio.audit.files=' . count(files_under($root . '/storage/appstudio/audit')) . PHP_EOL;
echo 'storage.appstudio.packages.files=' . count(files_under($root . '/storage/appstudio/packages')) . PHP_EOL;
echo 'storage.appstudio.publish_decisions.files=' . count(files_under($root . '/storage/appstudio/publish_decisions')) . PHP_EOL;
echo 'storage.tmp.files=' . count(files_under($root . '/storage/tmp')) . PHP_EOL;
echo 'storage.restore_previews.files=' . count(files_under($root . '/storage/restore_previews')) . PHP_EOL;
echo 'storage.release_previews.files=' . count(files_under($root . '/storage/release_previews')) . PHP_EOL;
echo 'storage.environment_snapshots.files=' . count(files_under($root . '/storage/environment_snapshots')) . PHP_EOL;

echo PHP_EOL . 'answers:' . PHP_EOL;
echo 'runtime_customer_state=storage/db.sqlite,storage/db_config.php,storage/app_files,storage/branding,storage/logs,storage/appstudio/apps_registry.json,storage/appstudio/registry.json,storage/appstudio/schema_registry.json' . PHP_EOL;
echo 'generated_app_evidence=storage/appstudio/generated_data,storage/appstudio/snapshots,storage/appstudio/applies,storage/appstudio/audit,storage/appstudio/publish_decisions,storage/appstudio/packages,storage/appstudio/history.json,storage/appstudio/apply_log.json,storage/appstudio/apply_log.ndjson' . PHP_EOL;
echo 'temporary_policy_required=storage/tmp,storage/tmp_plugins,storage/restore_tmp,storage/import_uploads,storage/import_previews,storage/restore_uploads,storage/environment_clone_uploads,storage/environment_clone_previews' . PHP_EOL;
echo 'package_export_restore_artifacts=storage/appstudio/packages,storage/exports,storage/plugin_exports,storage/releases,storage/release_previews,storage/restore_previews,storage/restore_uploads,storage/environment_snapshots' . PHP_EOL;
echo 'tracked_fixtures=storage/.gitkeep,storage/README.md,storage/appstudio/applies/.gitkeep,storage/db_config.php.example' . PHP_EOL;
echo 'generated_app_storage_delete_prechecks=apps_registry status,generated app code/routes/navigation,generated_data,snapshots,applies,audit,publish_decisions,packages,public assets,docs/scripts references,backup/export availability' . PHP_EOL;
echo 'retention_policy=snapshots/audit/apply/publish evidence retained through rollback/support window; packages/releases retained by version/export ledger; tmp/import/restore staging deleted only by TTL after completed job; runtime/customer state never deleted by migration cleanup' . PHP_EOL;

foreach ($warnings as $warning) {
    echo 'warning: ' . $warning . PHP_EOL;
}

foreach ($failures as $failure) {
    echo 'failure: ' . $failure . PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR, "RESULT: FAIL (storage retention policy violations found)\n");
    exit(1);
}

echo 'RESULT: PASS (read-only storage retention diagnostic complete)' . PHP_EOL;
PHP
