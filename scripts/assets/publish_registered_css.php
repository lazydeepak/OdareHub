<?php
declare(strict_types=1);

/**
 * Registered CSS Publisher
 *
 * Reads manifest-declared styles and maps owner source CSS files to the
 * public asset target paths used by Shell StyleRegistryService URL conventions.
 *
 * Dry-run is the default. Apply mode must be explicit.
 *
 * This tool never edits source CSS, manifests, Core, Shell runtime code, app
 * logic, or database state. Apply mode only copies owner CSS files into the
 * confined public delivery root: public/assets/apps/...
 *
 * Usage:
 *   php scripts/assets/publish_registered_css.php
 *   php scripts/assets/publish_registered_css.php --json
 *   php scripts/assets/publish_registered_css.php --apply
 *   php scripts/assets/publish_registered_css.php --apply --json
 */

$root = realpath(__DIR__ . '/../..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve repository root.\n");
    exit(2);
}

$json = in_array('--json', $argv, true);
$apply = in_array('--apply', $argv, true);
$unknownArgs = array_values(array_filter(array_slice($argv, 1), static fn (string $arg): bool => !in_array($arg, ['--json', '--apply'], true)));
if ($unknownArgs !== []) {
    fwrite(STDERR, "Unsupported argument(s): " . implode(', ', $unknownArgs) . "\n");
    exit(2);
}

$plan = buildCssPublishPlan($root, $apply ? 'apply' : 'dry-run');
if ($apply) {
    applyCssPublishPlan($plan, $root);
}

if ($json) {
    echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit(planExitCode($plan));
}

printHumanPlan($plan);
exit(planExitCode($plan));

/**
 * @return array<string,mixed>
 */
function buildCssPublishPlan(string $root, string $mode): array
{
    $items = [];
    $errors = [];

    foreach (manifestFiles($root) as $manifestFile) {
        $manifest = decodeManifest($manifestFile, $errors, $root);
        if ($manifest === null) {
            continue;
        }

        $styles = $manifest['styles'] ?? [];
        if (!is_array($styles) || $styles === []) {
            continue;
        }

        $appDir = dirname($manifestFile);
        $appKey = strtolower(trim((string)($manifest['app_key'] ?? basename($appDir))));
        $manifestRel = relativePath($root, $manifestFile);

        foreach ($styles as $index => $entry) {
            if (!is_array($entry)) {
                $errors[] = [
                    'manifest' => $manifestRel,
                    'style_index' => $index,
                    'message' => 'style entry is not an object',
                ];
                continue;
            }

            $item = planStyleEntry($root, $appDir, $manifestRel, $appKey, $index, $entry);
            if (($item['status'] ?? '') === 'error') {
                $errors[] = [
                    'manifest' => $manifestRel,
                    'style_index' => $index,
                    'key' => $item['key'] ?? '',
                    'message' => $item['message'] ?? 'invalid style entry',
                ];
            }
            $items[] = $item;
        }
    }

    return [
        'schema' => 'odarehub.css_publish_plan.v1',
        'mode' => $mode,
        'note' => $mode === 'apply'
            ? 'Apply mode copies owner CSS files only into public/assets/apps/... delivery targets.'
            : 'Dry-run only. No files were copied or modified.',
        'summary' => summarizePlan($items, $errors, $mode),
        'items' => $items,
        'errors' => $errors,
    ];
}

/**
 * @param array<string,mixed> $plan
 */
function applyCssPublishPlan(array &$plan, string $root): void
{
    $applyErrors = [];
    $appliedCreated = 0;
    $appliedUpdated = 0;
    $alreadyCurrent = 0;
    $skipped = 0;

    foreach ($plan['items'] as $index => $item) {
        $action = (string)($item['action'] ?? 'skip');
        $sourceRel = (string)($item['source'] ?? '');
        $targetRel = (string)($item['target'] ?? '');

        $plan['items'][$index]['applied'] = false;
        $plan['items'][$index]['apply_message'] = '';

        if ($action === 'none') {
            $alreadyCurrent++;
            $plan['items'][$index]['apply_message'] = 'already current';
            continue;
        }

        if ($action === 'skip' || ($item['status'] ?? '') !== 'ok') {
            $skipped++;
            $plan['items'][$index]['apply_message'] = 'skipped because item is not publishable';
            continue;
        }

        if (!in_array($action, ['create', 'update'], true)) {
            $skipped++;
            $plan['items'][$index]['apply_message'] = 'skipped unsupported action';
            continue;
        }

        if (!isSafeRelativePath($sourceRel) || !isAllowedPublicAssetTarget($targetRel)) {
            $message = 'unsafe source or target path';
            $applyErrors[] = applyErrorForItem($item, $message);
            $plan['items'][$index]['status'] = 'error';
            $plan['items'][$index]['apply_message'] = $message;
            continue;
        }

        $source = $root . '/' . $sourceRel;
        $target = $root . '/' . $targetRel;
        $targetDir = dirname($target);

        if (!is_file($source)) {
            $message = 'source CSS missing at apply time';
            $applyErrors[] = applyErrorForItem($item, $message);
            $plan['items'][$index]['status'] = 'error';
            $plan['items'][$index]['apply_message'] = $message;
            continue;
        }

        if (is_dir($target)) {
            $message = 'target path is a directory';
            $applyErrors[] = applyErrorForItem($item, $message);
            $plan['items'][$index]['status'] = 'error';
            $plan['items'][$index]['apply_message'] = $message;
            continue;
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $message = 'failed to create target directory';
            $applyErrors[] = applyErrorForItem($item, $message);
            $plan['items'][$index]['status'] = 'error';
            $plan['items'][$index]['apply_message'] = $message;
            continue;
        }

        $tmp = $target . '.tmp-' . getmypid() . '-' . bin2hex(random_bytes(4));
        if (!copy($source, $tmp)) {
            $message = 'failed to copy source to temporary target';
            $applyErrors[] = applyErrorForItem($item, $message);
            $plan['items'][$index]['status'] = 'error';
            $plan['items'][$index]['apply_message'] = $message;
            @unlink($tmp);
            continue;
        }

        if (!rename($tmp, $target)) {
            $message = 'failed to move temporary file into public asset target';
            $applyErrors[] = applyErrorForItem($item, $message);
            $plan['items'][$index]['status'] = 'error';
            $plan['items'][$index]['apply_message'] = $message;
            @unlink($tmp);
            continue;
        }

        $plan['items'][$index]['applied'] = true;
        $plan['items'][$index]['target_exists_after'] = is_file($target);
        $plan['items'][$index]['target_sha256_after'] = is_file($target) ? hash_file('sha256', $target) : null;
        $plan['items'][$index]['apply_message'] = $action === 'create' ? 'created public asset' : 'updated public asset';

        if ($action === 'create') {
            $appliedCreated++;
        } else {
            $appliedUpdated++;
        }
    }

    foreach ($applyErrors as $error) {
        $plan['errors'][] = $error;
    }

    $plan['summary']['applied_created'] = $appliedCreated;
    $plan['summary']['applied_updated'] = $appliedUpdated;
    $plan['summary']['already_current'] = $alreadyCurrent;
    $plan['summary']['apply_skipped'] = $skipped;
    $plan['summary']['apply_errors'] = count($applyErrors);
    $plan['summary']['errors'] = count($plan['errors']);
}

/**
 * @return array<int,string>
 */
function manifestFiles(string $root): array
{
    $paths = [];
    foreach (glob($root . '/apps/*/manifest.json') ?: [] as $file) {
        $paths[] = $file;
    }
    foreach (glob($root . '/apps/Generated/*/manifest.json') ?: [] as $file) {
        $paths[] = $file;
    }
    sort($paths);
    return array_values(array_unique($paths));
}

/**
 * @param array<int,array<string,mixed>> $errors
 * @return array<string,mixed>|null
 */
function decodeManifest(string $file, array &$errors, string $root): ?array
{
    $raw = @file_get_contents($file);
    if (!is_string($raw)) {
        $errors[] = ['manifest' => relativePath($root, $file), 'message' => 'unable to read manifest'];
        return null;
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        $errors[] = ['manifest' => relativePath($root, $file), 'message' => 'invalid JSON: ' . $e->getMessage()];
        return null;
    }

    return is_array($data) ? $data : null;
}

/**
 * @param array<string,mixed> $entry
 * @return array<string,mixed>
 */
function planStyleEntry(string $root, string $appDir, string $manifestRel, string $appKey, int $index, array $entry): array
{
    $key = strtolower(trim((string)($entry['key'] ?? '')));
    $path = trim((string)($entry['path'] ?? ''));
    $scope = strtolower(trim((string)($entry['scope'] ?? 'app')));
    $module = strtolower(trim((string)($entry['module'] ?? '')));

    $base = [
        'manifest' => $manifestRel,
        'style_index' => $index,
        'key' => $key,
        'app_key' => $appKey,
        'scope' => $scope,
        'module' => $module !== '' ? $module : null,
        'source' => null,
        'target' => null,
        'action' => 'skip',
        'status' => 'error',
    ];

    if ($key === '') {
        return array_replace($base, ['message' => 'missing style key']);
    }
    if ($path === '') {
        return array_replace($base, ['message' => 'missing style path']);
    }
    if (!isSafeRelativePath($path)) {
        return array_replace($base, ['message' => 'unsafe style path']);
    }
    if (!in_array($scope, ['app', 'module'], true)) {
        return array_replace($base, ['message' => 'unsupported scope']);
    }
    if ($scope === 'module' && $module === '') {
        return array_replace($base, ['message' => 'module scope requires module key']);
    }

    $source = $appDir . '/' . ltrim($path, '/');
    $sourceRel = relativePath($root, $source);
    $targetRel = publicTargetFor($appKey, $path, $scope, $module);
    $target = $root . '/' . $targetRel;

    $item = array_replace($base, [
        'source' => $sourceRel,
        'target' => $targetRel,
        'status' => 'ok',
        'message' => '',
        'source_exists' => is_file($source),
        'target_exists' => is_file($target),
        'source_sha256' => is_file($source) ? hash_file('sha256', $source) : null,
        'target_sha256' => is_file($target) ? hash_file('sha256', $target) : null,
    ]);

    if (!is_file($source)) {
        $item['action'] = 'skip';
        $item['status'] = 'warning';
        $item['message'] = 'source CSS missing; cannot publish';
        return $item;
    }

    if (!isAllowedPublicAssetTarget($targetRel)) {
        $item['action'] = 'skip';
        $item['status'] = 'error';
        $item['message'] = 'target is outside public/assets/apps delivery root';
        return $item;
    }

    if (!is_file($target)) {
        $item['action'] = 'create';
        $item['message'] = 'would create public asset from owner source';
        return $item;
    }

    if ($item['source_sha256'] !== $item['target_sha256']) {
        $item['action'] = 'update';
        $item['message'] = 'would update public asset from changed owner source';
        return $item;
    }

    $item['action'] = 'none';
    $item['message'] = 'public asset already matches owner source';
    return $item;
}

function publicTargetFor(string $appKey, string $path, string $scope, string $module): string
{
    if ($scope === 'module') {
        return 'public/assets/apps/' . strtolower($appKey) . '/modules/' . strtolower($module) . '/styles.css';
    }

    return 'public/assets/apps/' . strtolower($appKey) . '/styles/' . basename($path);
}

function isSafeRelativePath(string $path): bool
{
    if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
        return false;
    }

    foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
        if ($segment === '..') {
            return false;
        }
    }

    return true;
}

function isAllowedPublicAssetTarget(string $targetRel): bool
{
    return isSafeRelativePath($targetRel) && str_starts_with($targetRel, 'public/assets/apps/') && str_ends_with($targetRel, '.css');
}

/**
 * @param array<int,array<string,mixed>> $items
 * @param array<int,array<string,mixed>> $errors
 * @return array<string,mixed>
 */
function summarizePlan(array $items, array $errors, string $mode): array
{
    return [
        'mode' => $mode,
        'items' => count($items),
        'would_create' => countByAction($items, 'create'),
        'would_update' => countByAction($items, 'update'),
        'up_to_date' => countByAction($items, 'none'),
        'skipped' => countByAction($items, 'skip'),
        'errors' => count($errors),
        'applied_created' => 0,
        'applied_updated' => 0,
        'already_current' => 0,
        'apply_skipped' => 0,
        'apply_errors' => 0,
    ];
}

/**
 * @param array<int,array<string,mixed>> $items
 */
function countByAction(array $items, string $action): int
{
    return count(array_filter($items, static fn (array $item): bool => ($item['action'] ?? '') === $action));
}

/**
 * @param array<string,mixed> $item
 * @return array<string,mixed>
 */
function applyErrorForItem(array $item, string $message): array
{
    return [
        'manifest' => $item['manifest'] ?? '',
        'style_index' => $item['style_index'] ?? null,
        'key' => $item['key'] ?? '',
        'source' => $item['source'] ?? '',
        'target' => $item['target'] ?? '',
        'message' => $message,
    ];
}

function relativePath(string $root, string $path): string
{
    $prefix = rtrim($root, '/') . '/';
    return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
}

/**
 * @param array<string,mixed> $plan
 */
function planExitCode(array $plan): int
{
    return ((int)($plan['summary']['errors'] ?? 0) > 0 || (int)($plan['summary']['apply_errors'] ?? 0) > 0) ? 1 : 0;
}

/**
 * @param array<string,mixed> $plan
 */
function printHumanPlan(array $plan): void
{
    $mode = (string)($plan['mode'] ?? 'dry-run');
    echo "Registered CSS Publisher\n";
    echo "========================\n";
    echo "Mode: {$mode}\n";
    echo $mode === 'apply'
        ? "Apply mode copies only owner CSS into public/assets/apps/...\n\n"
        : "Dry-run only. No files were copied or modified.\n\n";

    $summary = $plan['summary'];
    echo "Summary\n";
    echo "-------\n";
    echo "Items: {$summary['items']}\n";
    echo "Would create: {$summary['would_create']}\n";
    echo "Would update: {$summary['would_update']}\n";
    echo "Up to date: {$summary['up_to_date']}\n";
    echo "Skipped: {$summary['skipped']}\n";
    echo "Errors: {$summary['errors']}\n";
    if ($mode === 'apply') {
        echo "Applied created: {$summary['applied_created']}\n";
        echo "Applied updated: {$summary['applied_updated']}\n";
        echo "Already current: {$summary['already_current']}\n";
        echo "Apply skipped: {$summary['apply_skipped']}\n";
        echo "Apply errors: {$summary['apply_errors']}\n";
    }
    echo "\n";

    echo "Plan\n";
    echo "----\n";
    foreach ($plan['items'] as $item) {
        $action = strtoupper((string)($item['action'] ?? 'skip'));
        $key = (string)($item['key'] ?? '<missing>');
        $source = (string)($item['source'] ?? '<missing>');
        $target = (string)($item['target'] ?? '<missing>');
        $message = (string)($item['message'] ?? '');
        echo "[$action] {$key}\n";
        echo "  source: {$source}\n";
        echo "  target: {$target}\n";
        if ($message !== '') {
            echo "  note: {$message}\n";
        }
        if ($mode === 'apply' && isset($item['apply_message'])) {
            echo "  apply: {$item['apply_message']}\n";
        }
    }

    if (($summary['errors'] ?? 0) > 0) {
        echo "\nErrors\n";
        echo "------\n";
        foreach ($plan['errors'] as $error) {
            echo "- " . json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
