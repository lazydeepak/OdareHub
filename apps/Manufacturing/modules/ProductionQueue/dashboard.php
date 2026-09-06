<?php
return static function(): array {
    $today = date('Y-m-d');
    $open = (int)(\App\Core\DB::fetchOne("SELECT COUNT(*) AS c FROM production_plans WHERE plan_date=? AND status IN ('Planned','In Progress')", [$today])['c'] ?? 0);
    return [
        'key' => 'production_queue.summary',
        'title' => 'Production Queue',
        'count' => $open,
        'detail' => 'Today open queue',
        'url' => '/apps/manufacturing/production-queue',
        'visible_if' => 'admin_strict',
        'order' => 75,
    ];
};
