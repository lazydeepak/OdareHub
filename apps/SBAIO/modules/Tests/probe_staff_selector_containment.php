<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-STAFF-SELECTOR-001 containment probe.
 *
 * This probe verifies that unproven Staff records do not enter the Task or
 * Notice administrator creation views. It does not load application bootstrap
 * or connect to an installed database.
 */

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};

$root = dirname(__DIR__);
$paths = [
    'tasks_controller' => $root . '/Tasks/Controllers/TasksController.php',
    'tasks_view' => $root . '/Tasks/Views/index.php',
    'tasks_service' => $root . '/Tasks/Services/TasksService.php',
    'notices_controller' => $root . '/Notices/Controllers/NoticesController.php',
    'notices_view' => $root . '/Notices/Views/index.php',
    'notices_service' => $root . '/Notices/Services/NoticesService.php',
];
$source = [];
foreach ($paths as $key => $path) {
    $source[$key] = file_get_contents($path) ?: '';
    $assert($source[$key] !== '', $key . ' source is readable');
}

foreach (['tasks', 'notices'] as $module) {
    $controller = $source[$module . '_controller'];
    $view = $source[$module . '_view'];
    $service = $source[$module . '_service'];
    $assert(!str_contains($controller, '::activeStaff()'), $module . ' controller does not invoke activeStaff');
    $assert(!preg_match('/<select\b[^>]*name=["\']related_staff_id/i', $view), $module . ' view has no Staff selector');
    $assert(!str_contains($view, '$staffRows'), $module . ' view has no Staff row data flow');
    $assert(str_contains($view, 'related_staff_unavailable'), $module . ' view renders localized Staff containment state');
    $assert(str_contains($service, "'related_staff_id' =>"), $module . ' service preserves related_staff_id persistence value');
    $assert(str_contains($service, 'normalizeRelatedStaffId'), $module . ' service preserves direct related_staff_id validation boundary');
}

$locales = [
    'en' => [
        'Tasks' => 'sbaio.tasks.related_staff_unavailable',
        'Notices' => 'sbaio.notices.related_staff_unavailable',
    ],
    'ja' => [
        'Tasks' => 'sbaio.tasks.related_staff_unavailable',
        'Notices' => 'sbaio.notices.related_staff_unavailable',
    ],
    'ne' => [
        'Tasks' => 'sbaio.tasks.related_staff_unavailable',
        'Notices' => 'sbaio.notices.related_staff_unavailable',
    ],
];
foreach ($locales as $locale => $keys) {
    foreach ($keys as $module => $key) {
        $path = $root . '/' . $module . '/Resources/lang/' . $locale . '.php';
        $localeSource = file_get_contents($path) ?: '';
        $assert(str_contains($localeSource, "'{$key}'"), $locale . ' locale contains ' . $module . ' containment key');
    }
}

$assert(!str_contains($source['tasks_controller'], "'staffRows'"), 'Task controller does not pass Staff rows to the view');
$assert(!str_contains($source['notices_controller'], "'staffRows'"), 'Notice controller does not pass Staff rows to the view');
$assert(str_contains($source['tasks_service'], 'if ($relatedStaffId === false)') && str_contains($source['notices_service'], 'if ($relatedStaffId === false)'), 'invalid supplied Staff IDs remain rejected');
$assert(str_contains($source['tasks_service'], "'related_staff_id' => \$relatedStaffId") && str_contains($source['notices_service'], "'related_staff_id' => \$relatedStaffId"), 'validated Staff ID persistence contract remains unchanged');

echo 'SBAIO Staff selector containment probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
exit($failures === 0 ? 0 : 1);
