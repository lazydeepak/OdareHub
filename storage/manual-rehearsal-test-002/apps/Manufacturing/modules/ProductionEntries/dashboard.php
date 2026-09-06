<?php
declare(strict_types=1);
use App\Core\DB;
return static function(): array {
    $draft = 0;
    $total = 0;
    try {
        $draft = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM production_entries WHERE status='Draft'")['c'] ?? 0);
        $total = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM production_entries")['c'] ?? 0);
    } catch (\Throwable $e) {}
    return [
        'key'    => 'production_entries.summary',
        'title'  => t('nav.production_entries'),
        'count'  => $draft,
        'detail' => localized_count_phrase('dashboard.in_draft', $draft),
        'extra'  => $total !== $draft ? localized_total_phrase($total) : null,
        'url'    => '/production-entries',
        'visible_if' => 'admin_strict',
        'order'  => 80,
    ];
};
