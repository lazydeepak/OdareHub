<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-STAFF-READ-PROJECTION-001 probe.
 *
 * Verifies Task/Notice read and export source contracts without loading the
 * application bootstrap or connecting to an installed/configured database.
 */

$root = dirname(__DIR__, 2);
$tasksService = file_get_contents($root . '/modules/Tasks/Services/TasksService.php') ?: '';
$noticesService = file_get_contents($root . '/modules/Notices/Services/NoticesService.php') ?: '';
$reportService = file_get_contents($root . '/Services/ModuleReportRegistryService.php') ?: '';
$tasksView = file_get_contents($root . '/modules/Tasks/Views/index.php') ?: '';
$noticesView = file_get_contents($root . '/modules/Notices/Views/index.php') ?: '';

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};

$assert(str_contains($tasksService, 'SELECT id, task_title, owner_name, task_type, task_bucket, related_date, task_status, priority, due_date,') && !str_contains($tasksService, 'SELECT id, task_title, owner_name, task_type, task_bucket, related_staff_id, related_date'), 'Task search projection excludes related_staff_id');
$assert(str_contains($tasksService, 'SELECT id, task_title, owner_name, task_type, task_bucket, related_date, task_status, priority, due_date FROM sbaio_tasks WHERE id = ? LIMIT 1'), 'Task exact projection is explicit and Staff-free');
$assert(str_contains($noticesService, 'SELECT id, title, notice_type, target_period, notice_level, requires_ack, is_active, created_at FROM sbaio_notices ORDER BY id DESC LIMIT'), 'Notice recent projection remains explicit and Staff-free');
$assert(str_contains($noticesService, 'SELECT id, title, notice_type, target_period, notice_level, requires_ack, is_active, created_at FROM sbaio_notices WHERE id = ? LIMIT 1'), 'Notice exact projection remains explicit and Staff-free');
$assert(!str_contains($tasksView, 'related_staff_id') && !str_contains($noticesView, 'related_staff_id'), 'Task and Notice views do not consume Staff linkage');
$assert(str_contains($reportService, "'sbaio.tasks.overview'") && str_contains($reportService, "'columns' => ['id', 'task_title', 'owner_name', 'task_type', 'task_bucket', 'related_date'") && !str_contains($reportService, "'columns' => ['id', 'task_title', 'owner_name', 'task_type', 'task_bucket', 'related_staff_id"), 'Task CSV export uses an explicit Staff-free column contract');
$assert(str_contains($reportService, "'sbaio.notices.overview'") && str_contains($reportService, "'columns' => ['id', 'title', 'notice_type', 'target_period', 'requires_ack'") && !str_contains($reportService, "'columns' => ['id', 'title', 'body'") && !str_contains($reportService, "'columns' => ['id', 'title', 'notice_type', 'target_period', 'related_staff_id"), 'Notice CSV export uses an explicit metadata-only privacy contract');
$assert(str_contains($reportService, 'SELECT %s FROM %s ORDER BY %s %s LIMIT 5000') && str_contains($reportService, '$headers = $columns;'), 'CSV engine preserves generic SELECT-star fallback while honoring explicit source columns');

echo 'SBAIO Staff read projection probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
exit($failures === 0 ? 0 : 1);
