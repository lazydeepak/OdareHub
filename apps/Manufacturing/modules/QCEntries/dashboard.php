<?php
declare(strict_types=1);
use App\Core\DB;
return static function(): array {
    $open  = 0;
    $total = 0;
    try {
        $open  = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM qc_entries WHERE status='Open'")['c'] ?? 0);
        $total = (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM qc_entries")['c'] ?? 0);
    } catch (\Throwable $e) {}
    return [
        'key'    => 'qc_entries.summary',
        'title'  => t('nav.qc_entries'),
        'count'  => $open,
        'detail' => localized_count_phrase('dashboard.pending_checks', $open),
        'extra'  => $total !== $open ? localized_total_phrase($total) : null,
        'url'    => '/manufacturing/qc-workboard',
        'visible_if' => 'admin_or_qc',
        'order'  => 100,
    ];
};
