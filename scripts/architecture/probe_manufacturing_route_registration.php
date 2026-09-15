<?php

/**
 * Hermetic Manufacturing route-registration preflight probe.
 *
 * Purpose
 * -------
 * Proves that the Manufacturing root route file plus the Bom / Workflow /
 * DispatchEntries / ProductionPlans / QCEntries module route files can be
 * included under a cold Composer autoload (vendor/autoload.php only) and that
 * every route registration carries a callable handler, with zero database
 * access and zero bootstrap of the runtime entry point.
 *
 * Boundaries (hard)
 * -----------------
 * - Loads ONLY vendor/autoload.php. It does NOT bootstrap public/index.php and
 *   does NOT load storage/db_config.php.
 * - No database read or write. A tripwire stub replaces App\Core\DB BEFORE any
 *   route include: real app/Core/DB.php is never autoloaded and any include-time
 *   DB:: call fails immediately with DB_ACCESS_FORBIDDEN. No mysqli/PDO
 *   connection is ever opened.
 * - No module install/update/activate/deactivate, no recoverFromBroken(), no
 *   controller-action execution, no ensureSchema(), no filesystem mutations.
 * - No /app, apps/Manufacturing, manifest, or gate-runner modifications.
 *
 * Include-time stubs provided in scope
 * ------------------------------------
 * - $router  => Probe\RecordingRouter (get/post/put/patch/delete; records
 *   method/path and asserts is_callable($handler)).
 * - $view    => Probe\ViewStub (addNamespace/render/__call no-ops).
 * - $c       => Probe\ContainerStub; get('plugins') returns a PluginManagerStub
 *   whose isInstalled() returns true for every legacy-bridge plugin, so the
 *   Manufacturing root legacy loop takes the real-DB state branch.
 *
 * Expected registration counts (verified against manifest/runtime contract):
 *   Manufacturing root       12 direct (5 GET + 7 POST) + dynamic Routes/*.php
 *                                 registrations. With PluginManagerStub
 *                                 status()='active', assembly_plans.php adds 4
 *                                 and execution.php adds 2, applied ONLY when
 *                                 those Routes files are reached; the probe
 *                                 reports the runtime-captured aggregate
 *                                 (observed 128 in the current tree: 12 direct
 *                                 + 116 via Routes/*.php).
 *   Bom                       9   (3 GET + 6 POST) - line ~91 is the historical
 *                                 null-handler defect location, now a closure.
 *   Workflow                  0   (services include-only)
 *   DispatchEntries          15   (8 GET + 7 POST)
 *   ProductionPlans           4   (4 GET; include-time PackageManager call)
 *   QCEntries                11   (6 GET + 5 POST)
 *
 * CLI usage:  php scripts/architecture/probe_manufacturing_route_registration.php
 * Exit code:  0 on PASS; 1 on any failure.
 */

declare(strict_types=1);

namespace Probe {

    /**
     * Marker exception thrown by the App\Core\DB tripwire stub whenever a route
     * file (or any file it includes) attempts a database call at include time.
     */
    final class DBForbidden extends \RuntimeException
    {
    }

    /**
     * Recording router. Exposes the five verbs used by application route files
     * and records (method, path, source file) per registration. A null or
     * non-callable handler aborts the probe immediately with a descriptive
     * error, mirroring the historical Router::post() callable-contract failure.
     */
    final class RecordingRouter
    {
        /** @var list<array{method:string,path:string,file:string}> */
        public array $routes = [];

        private string $currentFile = '';

        public function setCurrentFile(string $file): void
        {
            $this->currentFile = $file;
        }

        private function register(string $method, mixed $path, mixed $handler): void
        {
            if (!is_string($path)) {
                throw new \RuntimeException(
                    'NON_STRING_PATH file=' . $this->currentFile
                    . ' method=' . $method
                    . ' path=' . var_export($path, true)
                );
            }

            if (!is_callable($handler)) {
                $desc = is_object($handler)
                    ? get_class($handler)
                    : (is_string($handler) ? $handler : get_debug_type($handler));
                throw new \RuntimeException(
                    'NON_CALLABLE_HANDLER file=' . $this->currentFile
                    . ' method=' . $method
                    . ' path=' . $path
                    . ' handler=' . $desc
                );
            }

            $this->routes[] = [
                'method' => $method,
                'path' => $path,
                'file' => $this->currentFile,
            ];
        }

        public function get(mixed $path, mixed $handler): void
        {
            $this->register('get', $path, $handler);
        }

        public function post(mixed $path, mixed $handler): void
        {
            $this->register('post', $path, $handler);
        }

        public function put(mixed $path, mixed $handler): void
        {
            $this->register('put', $path, $handler);
        }

        public function patch(mixed $path, mixed $handler): void
        {
            $this->register('patch', $path, $handler);
        }

        public function delete(mixed $path, mixed $handler): void
        {
            $this->register('delete', $path, $handler);
        }
    }

    /**
     * Plugin-manager stand-in used by $c->get('plugins').
     *
     * Installed-state matches the live database for all legacy-bridge plugins
     * (installed), so the Manufacturing root legacy loop continues and no
     * plugin routes are pulled in through the bundle-owned fallback loader.
     *
     * status() is deliberate test-only coverage behavior: it returns 'active'
     * solely to exercise every conditional route-registration branch regardless
     * of the live module lifecycle state. Under status()='active':
     *   - Routes/assembly_plans.php:6  (status !== 'active' => return;) takes
     *     the register branch and emits its 4 compat redirect routes.
     *   - Routes/execution.php:75      (status === 'active') emits its 2
     *     AssemblyEntries GET routes.
     * This stub return value makes no claim about the current runtime DB state
     * (all Manufacturing child modules are currently inactive in the live
     * system) and the probe depends on no storage artifact. It diverges
     * deliberately so every route branch is hermetically validated.
     */
    final class PluginManagerStub
    {
        public function isInstalled(string $pluginKey): bool
        {
            return true;
        }

        public function status(string $pluginKey): string
        {
            return 'active';
        }
    }

    /**
     * Service-container stand-in. Only the 'plugins' service is requested by
     * the Manufacturing root route file at include time.
     */
    final class ContainerStub
    {
        public function get(string $id): mixed
        {
            if ($id === 'plugins') {
                return new PluginManagerStub();
            }

            throw new \RuntimeException('UNKNOWN_CONTAINER_SERVICE ' . $id);
        }
    }

    /**
     * View stub. addNamespace() is only reached when a non-installed legacy
     * plugin would be loaded (never in the probe); render() and __call() are
     * never invoked because handlers are only registered, never executed.
     */
    final class ViewStub
    {
        public function addNamespace(string $name, string $path): void
        {
        }

        public function render(string $template, array $data = []): void
        {
        }

        public function __call(string $name, array $args): void
        {
        }
    }
}

namespace App\Core {

    /**
     * Database tripwire.
     *
     * Declared BEFORE any route include so the real app/Core/DB.php is never
     * autoloaded and storage/db_config.php is unreachable. Any include-time
     * database access attempt aborts the probe with DB_ACCESS_FORBIDDEN.
     */
    final class DB
    {
        public static function conn(): void
        {
            throw new \Probe\DBForbidden('DB::conn() invoked at include time');
        }

        public static function __callStatic(string $name, array $args): void
        {
            throw new \Probe\DBForbidden('DB::' . $name . '() invoked at include time');
        }

        public function __call(string $name, array $args): void
        {
            throw new \Probe\DBForbidden('DB instance->' . $name . '() invoked at include time');
        }
    }
}

namespace {

    /**
     * Emit a failure reason on STDERR and terminate with a non-zero exit code.
     */
    function probe_fail(string $detail): never
    {
        fwrite(STDERR, "MANUFACTURING_ROUTE_REGISTRATION_PROBE: FAIL " . $detail . "\n");
        exit(1);
    }

    // APP_ROOT must be the repository root. From scripts/architecture/ this is
    // dirname(__DIR__, 2); the deeper dirname depth used by nested probes
    // (platform/*/tests) does not apply here. PackageManager::pluginSourcePath()
    // is exercised at include time by ProductionPlans/routes.php and is
    // filesystem-only.
    define('APP_ROOT', dirname(__DIR__, 2));

    // ---- Section 1: cold Composer autoload ---------------------------------
    $autoload = APP_ROOT . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        probe_fail('vendor/autoload.php missing at ' . $autoload);
    }
    require $autoload;

    // ---- Section 2: Workflow cold-autoload assertion (before any bridge) ---
    $workflowClasses = [
        'Plugins\\Workflow\\Services\\WorkflowGovernance',
        'Plugins\\Workflow\\Services\\WorkflowPolicy',
        'Plugins\\Workflow\\Services\\WorkflowTransitionEngine',
    ];
    foreach ($workflowClasses as $workflowClass) {
        if (!class_exists($workflowClass)) {
            probe_fail('WORKFLOW_COLD_AUTOLOAD_FAILED ' . $workflowClass);
        }
    }
    echo 'WORKFLOW_COLD_AUTOLOAD_RESOLVED ' . count($workflowClasses) . "\n";

    // ---- Section 3: support stubs (App\Core\DB tripwire already declared) --
    $router = new \Probe\RecordingRouter();
    $view = new \Probe\ViewStub();
    $c = new \Probe\ContainerStub();

    // ---- Section 4: include the six target route files ----------------------
    $targets = [
        'apps/Manufacturing/routes.php',
        'apps/Manufacturing/modules/Bom/routes.php',
        'apps/Manufacturing/modules/Workflow/routes.php',
        'apps/Manufacturing/modules/DispatchEntries/routes.php',
        'apps/Manufacturing/modules/ProductionPlans/routes.php',
        'apps/Manufacturing/modules/QCEntries/routes.php',
    ];

    foreach ($targets as $relative) {
        $file = APP_ROOT . '/' . $relative;
        if (!is_file($file)) {
            probe_fail('TARGET_MISSING ' . $relative);
        }

        $router->setCurrentFile($relative);
        $before = count($router->routes);

        try {
            require $file;
        } catch (\Probe\DBForbidden $e) {
            probe_fail('DB_ACCESS_FORBIDDEN during ' . $relative . ': ' . $e->getMessage());
        } catch (\Throwable $e) {
            probe_fail(
                'INCLUDE_FAILED ' . $relative . ': '
                . get_class($e) . ': ' . $e->getMessage()
            );
        }

        $registered = count(array_slice($router->routes, $before));
        // RecordingRouter aborts on a null/non-callable handler, so reaching
        // this line implies every handler for this file was callable.
        echo 'FILE ' . $relative . "\n";
        echo 'REGISTERED ' . $registered . "\n";
        echo 'NON_CALLABLE 0' . "\n";
        echo 'DB_ACCESS 0' . "\n";
        echo 'PASS' . "\n";
        flush();
    }

    echo 'MANUFACTURING_ROUTE_REGISTRATION_PROBE: PASS' . "\n";
    exit(0);
}