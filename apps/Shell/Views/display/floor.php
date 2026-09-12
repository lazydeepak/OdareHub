<?php
// Display Layer View: floor.php  — Slideshow Edition
// Readonly kiosk/TV display — no forms, no mutations, no navigation escape.
// Variables injected from DisplaySurfaceComposer::render() scope:
//   $this, $companyName, $branchName, $companyLogo, $displayMode,
//   $companyLogoSvgInline, $companyLogoSvgTheme,
//   $query, $refreshSeconds, $slideInterval,
//   $coverage, $dispatch, $machines, $qc, $activities, $today, $nowLabel
declare(strict_types=1);

// ── KPI helpers ───────────────────────────────────────────────────────────────
$coverageSummary = (array)($coverage['summary'] ?? []);
$dispatchKpi     = (array)($dispatch['kpi']     ?? []);
$machinesKpi     = (array)($machines['kpi']     ?? []);
$qcKpi           = (array)($qc['kpi']           ?? []);

$openOrders   = (int)($coverageSummary['open_orders']          ?? 0);
$criticalOrds = (int)($coverageSummary['critical_orders_count']?? 0);
$covPct       = round((float)($coverageSummary['coverage_pct'] ?? 0), 1);
$runNow       = (int)($machinesKpi['run_now']                  ?? 0);
$delayedJobs  = (int)($machinesKpi['delayed_jobs']             ?? 0);
$waitingQc    = (int)($machinesKpi['waiting_qc']               ?? 0);
$readyNow     = (int)($dispatchKpi['ready_now']                ?? 0);
$blockedHold  = (int)($dispatchKpi['blocked_hold']             ?? 0);
$qcPending    = (int)($qcKpi['pending_qc']   ?? $qcKpi['pending'] ?? $qcKpi['open'] ?? 0);
$qcFailed     = (int)($qcKpi['failed_recheck']?? $qcKpi['failed'] ?? 0);
$qcReadyDisp  = (int)($qcKpi['ready_dispatch']?? 0);
$displayPanelSet = [];
foreach ((array)($displayPanels ?? []) as $panelKey) {
    $panelKey = strtolower(trim((string)$panelKey));
    if ($panelKey !== '') {
        $displayPanelSet[$panelKey] = true;
    }
}
if ($displayPanelSet === []) {
    $displayPanelSet = ['overview' => true, 'machines' => true, 'dispatch' => true, 'qc' => true, 'activity' => true];
}
$displayPanelEnabled = static fn(string $panelKey): bool => isset($displayPanelSet[$panelKey]);

// ── Time-ago helper ───────────────────────────────────────────────────────────
$timeAgo = static function (string $ts): string {
    $diff = max(0, time() - strtotime($ts));
    if ($diff < 60)   return $diff . 's';
    if ($diff < 3600) return floor($diff / 60) . 'm';
    $h = floor($diff / 3600); $m = floor(($diff % 3600) / 60);
    return $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : '');
};

$pageTitle = $this->tr('display.page_title', 'Floor Display');
$headTitle = ($companyName !== '' ? $companyName . ' — ' : '') . $pageTitle;

$renderedDisplayLogoSvg = trim((string)($companyLogoSvgInline ?? ''));
$displayLogoTheme = trim((string)($companyLogoSvgTheme ?? ''));
if ($renderedDisplayLogoSvg !== '' && $displayLogoTheme !== '' && preg_match('/\bclass="ipm-logo\b/', $renderedDisplayLogoSvg)) {
    $safeTheme = preg_replace('/[^a-z0-9\-]/', '', strtolower($displayLogoTheme));
    if ($safeTheme !== '') {
        $renderedDisplayLogoSvg = preg_replace('/\bclass="ipm-logo"/', 'class="ipm-logo ' . $safeTheme . '"', $renderedDisplayLogoSvg, 1);
    }
}
if ($renderedDisplayLogoSvg !== '' && preg_match('/<svg\b/i', $renderedDisplayLogoSvg)) {
    if (preg_match('/<svg\b[^>]*\bclass="/i', $renderedDisplayLogoSvg)) {
        $renderedDisplayLogoSvg = preg_replace('/<svg\b([^>]*?)\bclass="([^"]*)"/i', '<svg$1class="$2 d-logo-svg"', $renderedDisplayLogoSvg, 1);
    } else {
        $renderedDisplayLogoSvg = preg_replace('/<svg\b/i', '<svg class="d-logo-svg"', $renderedDisplayLogoSvg, 1);
    }
}
$companyFallbackText = trim((string)($companyFallbackText ?? ''));
$companyFallbackTextCompact = trim((string)($companyFallbackTextCompact ?? ''));
$brandingLogoCssPath = APP_ROOT . '/public/assets/branding/ipm-logo.css';
$brandingLogoCssVersion = is_file($brandingLogoCssPath) ? (string)filemtime($brandingLogoCssPath) : '1';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($headTitle); ?></title>
    <?php if ($refreshSeconds > 0): ?>
    <meta http-equiv="refresh" content="<?php echo $refreshSeconds; ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/branding/ipm-logo.css?v=<?php echo htmlspecialchars($brandingLogoCssVersion); ?>">
    <?php
      if (class_exists('\Apps\Shell\Services\StyleRegistryService')) {
        $displayStyles = array_merge(
          \Apps\Shell\Services\StyleRegistryService::globals(),
          \Apps\Shell\Services\StyleRegistryService::forSurface('display', [])
        );
        foreach ($displayStyles as $style) {
          $styleUrl = $style['url'] ?? '';
          $styleVersion = $style['version'] ?? '1';
          if ($styleUrl !== '') {
            echo '    <link rel="stylesheet" href="' . htmlspecialchars($styleUrl) . '?v=' . htmlspecialchars($styleVersion) . '">' . "\n";
          }
        }
      }
    ?>
</head>
<body>
<header class="d-header" role="banner">
    <div class="d-header-left">
        <?php if ($renderedDisplayLogoSvg !== ''): ?>
            <div class="d-logo-wrap" aria-label="<?php echo htmlspecialchars($companyName); ?>"><?php echo $renderedDisplayLogoSvg; ?></div>
        <?php elseif ($companyLogo !== ''): ?>
            <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="<?php echo htmlspecialchars($companyName); ?>" class="d-logo">
        <?php else: ?>
            <div class="d-logo-wrap"><span class="d-logo-fallback"><?php echo htmlspecialchars($companyFallbackText ?: 'OdareHub OS'); ?></span></div>
        <?php endif; ?>
        <div class="ui-block">
            <div class="d-company"><?php echo htmlspecialchars($companyName); ?></div>
            <?php if ($branchName !== ''): ?><div class="d-branch"><?php echo htmlspecialchars($branchName); ?></div><?php endif; ?>
        </div>
    </div>
    <div class="d-header-right">
        <?php if ($refreshSeconds > 0): ?><span class="d-refresh-badge">&#8635; <?php echo $refreshSeconds; ?>s</span><?php endif; ?>
        <div class="ui-block">
            <div class="d-clock" id="d-clock">--:--:--</div>
            <div class="d-date" id="d-date"></div>
        </div>
    </div>
</header>
<div class="slide-container" role="main">

<!-- SLIDE 0: Overview -->
<?php if ($displayPanelEnabled('overview')): ?>
<section class="slide" id="s0">
    <div class="slide-title"><span class="slide-accent"></span><span class="slide-label"><?php echo htmlspecialchars($this->tr('display.slide.overview', 'Today at a Glance')); ?></span></div>
    <div class="kpi-grid">
        <div class="kpi-card <?php echo $criticalOrds > 0 ? 'kpi-danger' : 'kpi-ok'; ?>">
            <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.open_orders', 'Open Orders')); ?></div>
            <div class="kpi-value"><?php echo number_format($openOrders); ?></div>
            <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.today', 'Today')); ?></div>
        </div>
        <div class="kpi-card <?php echo $criticalOrds > 0 ? 'kpi-danger' : 'kpi-ok'; ?>">
            <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.critical', 'Critical')); ?></div>
            <div class="kpi-value"><?php echo number_format($criticalOrds); ?></div>
            <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.orders', 'orders')); ?></div>
        </div>
        <div class="kpi-card <?php echo $covPct < 50 ? 'kpi-danger' : ($covPct < 80 ? 'kpi-warn' : 'kpi-ok'); ?>">
            <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.coverage', 'Coverage')); ?></div>
            <div class="kpi-value sm"><?php echo number_format($covPct, 1); ?>%</div>
            <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.avg', 'avg')); ?></div>
        </div>
        <div class="kpi-card kpi-accent">
            <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.machines_running', 'Running')); ?></div>
            <div class="kpi-value"><?php echo number_format($runNow); ?></div>
            <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.machines', 'machines')); ?></div>
        </div>
        <div class="kpi-card <?php echo $delayedJobs > 0 ? 'kpi-warn' : 'kpi-ok'; ?>">
            <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.delayed', 'Delayed')); ?></div>
            <div class="kpi-value"><?php echo number_format($delayedJobs); ?></div>
            <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.jobs', 'jobs')); ?></div>
        </div>
        <div class="kpi-card kpi-ok">
            <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.dispatch_ready', 'Dispatch Ready')); ?></div>
            <div class="kpi-value"><?php echo number_format($readyNow); ?></div>
            <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.items', 'items')); ?></div>
        </div>
        <div class="kpi-card <?php echo $blockedHold > 0 ? 'kpi-warn' : 'kpi-ok'; ?>">
            <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.on_hold', 'On Hold')); ?></div>
            <div class="kpi-value"><?php echo number_format($blockedHold); ?></div>
            <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.items', 'items')); ?></div>
        </div>
        <div class="kpi-card <?php echo $qcFailed > 0 ? 'kpi-danger' : 'kpi-info'; ?>">
            <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.qc_queue', 'QC Queue')); ?></div>
            <div class="kpi-value"><?php echo number_format($qcPending); ?></div>
            <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.pending', 'pending')); ?></div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- SLIDE 1: Production Floor -->
<?php if ($displayPanelEnabled('machines')): ?>
<section class="slide" id="s1">
    <div class="slide-title"><span class="slide-accent u-style-36d30510f5"></span><span class="slide-label"><?php echo htmlspecialchars($this->tr('display.slide.machines', 'Production Floor')); ?></span></div>
    <div class="slide-cols">
        <div class="kpi-stack">
            <div class="kpi-card kpi-accent">
                <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.machines_running', 'Running')); ?></div>
                <div class="kpi-value"><?php echo number_format($runNow); ?></div>
                <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.machines', 'machines')); ?></div>
            </div>
            <div class="kpi-card <?php echo $delayedJobs > 0 ? 'kpi-warn' : 'kpi-ok'; ?>">
                <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.delayed', 'Delayed')); ?></div>
                <div class="kpi-value"><?php echo number_format($delayedJobs); ?></div>
                <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.jobs', 'jobs')); ?></div>
            </div>
            <div class="kpi-card kpi-info">
                <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.waiting_qc', 'Waiting QC')); ?></div>
                <div class="kpi-value"><?php echo number_format($waitingQc); ?></div>
                <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.jobs', 'jobs')); ?></div>
            </div>
        </div>
        <div class="d-panel">
            <div class="d-panel-head"><span class="d-panel-title"><?php echo htmlspecialchars($this->tr('display.panel.machines', 'Machines')); ?></span><span class="d-panel-badge"><?php echo count((array)($machines['run_now'] ?? [])); ?></span></div>
            <div class="d-panel-body">
                <?php $runRows = array_slice((array)($machines['run_now'] ?? []), 0, 14); ?>
                <?php if ($runRows): ?><?php foreach ($runRows as $row): ?>
                    <div class="d-row">
                        <div class="d-row-name"><?php echo htmlspecialchars((string)($row['machine_name'] ?? '-')); ?></div>
                        <div class="d-row-sub"><?php echo htmlspecialchars((string)($row['part_number'] ?? $row['product_name'] ?? '')); ?></div>
                        <span class="chip chip-ok"><?php echo htmlspecialchars($this->tr('display.status.running', 'Run')); ?></span>
                    </div>
                <?php endforeach; ?><?php else: ?>
                    <div class="d-row-empty"><?php echo htmlspecialchars($this->tr('display.empty.no_running', 'No machines running')); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- SLIDE 2: Dispatch -->
<?php if ($displayPanelEnabled('dispatch')): ?>
<section class="slide" id="s2">
    <div class="slide-title"><span class="slide-accent u-style-995f9fa9fd"></span><span class="slide-label"><?php echo htmlspecialchars($this->tr('display.slide.dispatch', 'Dispatch')); ?></span></div>
    <div class="slide-cols">
        <div class="kpi-stack">
            <div class="kpi-card kpi-ok">
                <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.dispatch_ready', 'Dispatch Ready')); ?></div>
                <div class="kpi-value"><?php echo number_format($readyNow); ?></div>
                <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.items', 'items')); ?></div>
            </div>
            <div class="kpi-card <?php echo $blockedHold > 0 ? 'kpi-warn' : 'kpi-ok'; ?>">
                <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.on_hold', 'On Hold')); ?></div>
                <div class="kpi-value"><?php echo number_format($blockedHold); ?></div>
                <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.items', 'items')); ?></div>
            </div>
            <?php $urgentDisp = (int)($dispatchKpi['urgent'] ?? 0); ?>
            <div class="kpi-card <?php echo $urgentDisp > 0 ? 'kpi-danger' : 'kpi-ok'; ?>">
                <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.urgent', 'Urgent')); ?></div>
                <div class="kpi-value"><?php echo number_format($urgentDisp); ?></div>
                <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.items', 'items')); ?></div>
            </div>
        </div>
        <div class="d-panel">
            <div class="d-panel-head"><span class="d-panel-title"><?php echo htmlspecialchars($this->tr('display.panel.dispatch', 'Dispatch Queue')); ?></span><span class="d-panel-badge"><?php echo count((array)($dispatch['ready_now'] ?? [])); ?></span></div>
            <div class="d-panel-body">
                <?php $dispRows = array_slice((array)($dispatch['ready_now'] ?? []), 0, 14); ?>
                <?php if ($dispRows): ?><?php foreach ($dispRows as $row): ?>
                    <div class="d-row">
                        <div class="d-row-name"><?php echo htmlspecialchars((string)($row['part_number'] ?? $row['product_name'] ?? '-')); ?></div>
                        <div class="d-row-sub"><?php echo htmlspecialchars((string)($row['destination'] ?? '')); ?></div>
                        <div class="d-row-qty"><?php echo number_format((float)($row['dispatchable_qty'] ?? 0), 0); ?></div>
                        <span class="chip chip-ok"><?php echo htmlspecialchars($this->tr('display.status.ready', 'Ready')); ?></span>
                    </div>
                <?php endforeach; ?><?php else: ?>
                    <div class="d-row-empty"><?php echo htmlspecialchars($this->tr('display.empty.no_dispatch', 'No dispatch items ready')); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- SLIDE 3: Quality Control -->
<?php if ($displayPanelEnabled('qc')): ?>
<section class="slide" id="s3">
    <div class="slide-title"><span class="slide-accent u-style-07fad16d36"></span><span class="slide-label"><?php echo htmlspecialchars($this->tr('display.slide.qc', 'Quality Control')); ?></span></div>
    <div class="slide-cols">
        <div class="kpi-stack">
            <div class="kpi-card <?php echo $qcFailed > 0 ? 'kpi-danger' : 'kpi-info'; ?>">
                <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.qc_queue', 'QC Queue')); ?></div>
                <div class="kpi-value"><?php echo number_format($qcPending); ?></div>
                <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.pending', 'pending')); ?></div>
            </div>
            <div class="kpi-card <?php echo $qcFailed > 0 ? 'kpi-danger' : 'kpi-ok'; ?>">
                <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.qc_failed', 'Failed/Recheck')); ?></div>
                <div class="kpi-value"><?php echo number_format($qcFailed); ?></div>
                <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.items', 'items')); ?></div>
            </div>
            <div class="kpi-card kpi-ok">
                <div class="kpi-label"><?php echo htmlspecialchars($this->tr('display.kpi.qc_ready_dispatch', 'Ready to Ship')); ?></div>
                <div class="kpi-value"><?php echo number_format($qcReadyDisp); ?></div>
                <div class="kpi-sub"><?php echo htmlspecialchars($this->tr('display.kpi.items', 'items')); ?></div>
            </div>
        </div>
        <div class="d-panel">
            <div class="d-panel-head"><span class="d-panel-title"><?php echo htmlspecialchars($this->tr('display.panel.qc', 'QC Queue')); ?></span><span class="d-panel-badge"><?php echo count((array)($qc['pending_qc'] ?? $qc['open_plans'] ?? [])); ?></span></div>
            <div class="d-panel-body">
                <?php $qcRows = array_slice((array)($qc['pending_qc'] ?? $qc['open_plans'] ?? []), 0, 14); ?>
                <?php if ($qcRows): ?><?php foreach ($qcRows as $row): ?>
                    <?php
                        $qSt = strtolower(trim((string)($row['status'] ?? '')));
                        [$cCls, $cLbl] = match(true) {
                            in_array($qSt, ['failed', 'rejected'], true)         => ['chip-danger', $this->tr('display.status.failed', 'Fail')],
                            in_array($qSt, ['in_progress', 'in progress'], true) => ['chip-warn',   $this->tr('display.status.in_progress', 'WIP')],
                            in_array($qSt, ['passed', 'approved'], true)         => ['chip-ok',     $this->tr('display.status.passed', 'Pass')],
                            default                                              => ['chip-neutral', $this->tr('display.status.pending', 'Pend')],
                        };
                    ?>
                    <div class="d-row">
                        <div class="d-row-name"><?php echo htmlspecialchars((string)($row['part_number'] ?? $row['product_name'] ?? '-')); ?></div>
                        <div class="d-row-qty"><?php echo number_format((float)($row['planned_qty'] ?? $row['checked_qty'] ?? 0), 0); ?></div>
                        <span class="chip <?php echo htmlspecialchars($cCls); ?>"><?php echo htmlspecialchars($cLbl); ?></span>
                    </div>
                <?php endforeach; ?><?php else: ?>
                    <div class="d-row-empty"><?php echo htmlspecialchars($this->tr('display.empty.no_qc', 'No QC items queued')); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- SLIDE 4: Recent Activity -->
<?php if ($displayPanelEnabled('activity')): ?>
<section class="slide" id="s4">
    <div class="slide-title">
        <span class="slide-accent u-style-d491fa508e"></span>
        <span class="slide-label"><?php echo htmlspecialchars($this->tr('display.slide.activity', 'Recent Activity')); ?> &mdash; <?php echo htmlspecialchars($this->tr('display.slide.last_2h', 'Last 2 hours')); ?></span>
    </div>
    <?php if (empty($activities)): ?>
        <div class="u-style-689068e444">
            <span class="u-style-27b836e714"><?php echo htmlspecialchars($this->tr('display.activity.empty', 'No recent activity in the last 2 hours')); ?></span>
        </div>
    <?php else: ?>
        <?php
            $col1 = array_values(array_filter($activities, fn($_, $i) => $i % 2 === 0, ARRAY_FILTER_USE_BOTH));
            $col2 = array_values(array_filter($activities, fn($_, $i) => $i % 2 === 1, ARRAY_FILTER_USE_BOTH));
        ?>
        <div class="activity-feed">
        <?php foreach ([[$col1, 1], [$col2, 2]] as [$col, $colN]): ?>
            <div class="activity-col">
                <div class="d-panel-head"><span class="d-panel-title"><?php echo htmlspecialchars($this->tr('display.activity.feed', 'Activity')); ?> <?php echo $colN; ?></span></div>
                <div class="d-panel-body">
                    <?php if (empty($col)): ?>
                        <div class="d-row-empty">&nbsp;</div>
                    <?php else: ?>
                        <?php foreach ($col as $act): ?>
                            <?php
                                $aType   = (string)($act['type']         ?? '');
                                $aProd   = (string)($act['product_name'] ?? '');
                                $aPart   = (string)($act['part_number']  ?? '');
                                $aStatus = (string)($act['status']       ?? '');
                                $aQty    = (float)($act['qty']           ?? 0);
                                $aTs     = (string)($act['ts']           ?? '');
                                $aAgo    = $aTs !== '' ? ($timeAgo($aTs) . ' ' . $this->tr('display.activity.ago', 'ago')) : '';
                                [$iCls, $icon, $cCls, $tLabel] = match ($aType) {
                                    'assembly' => ['a-asm',  '&#9881;',   'chip-info',   $this->tr('display.activity.assembly_done', 'Assembly')],
                                    'dispatch' => ['a-disp', '&#128230;', 'chip-ok',     $this->tr('display.activity.dispatched',    'Dispatched')],
                                    'qc'       => ['a-qc',   '&#10003;',  'chip-purple', $this->tr('display.activity.qc_result',     'QC')],
                                    default    => ['a-asm',  '&#8901;',   'chip-neutral', $aType],
                                };
                            ?>
                            <div class="activity-row">
                                <div class="a-icon <?php echo htmlspecialchars($iCls); ?>"><?php echo $icon; ?></div>
                                <div class="a-body">
                                    <div class="a-name"><?php echo htmlspecialchars($aPart !== '' ? $aPart : $aProd); ?></div>
                                    <div class="a-meta">
                                        <span class="chip <?php echo htmlspecialchars($cCls); ?>"><?php echo htmlspecialchars($tLabel); ?></span>
                                        &nbsp;<?php echo htmlspecialchars(number_format($aQty, 0)); ?>
                                        &nbsp;&middot;&nbsp;<?php echo htmlspecialchars($aStatus); ?>
                                    </div>
                                </div>
                                <div class="a-time"><?php echo htmlspecialchars($aAgo); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

</div><!-- /.slide-container -->
<footer class="d-footer" role="contentinfo">
    <span class="d-footer-text"><?php echo htmlspecialchars($this->tr('display.footer.readonly', 'Read-only display — no actions available')); ?></span>
    <div class="slide-dots" id="slide-dots">
        <?php $dotIndex = 0; ?>
        <?php foreach (['overview', 'machines', 'dispatch', 'qc', 'activity'] as $panelKey): ?>
            <?php if (!$displayPanelEnabled($panelKey)) { continue; } ?>
            <span class="slide-dot <?= $dotIndex === 0 ? 'active' : '' ?>" data-s="<?= $dotIndex ?>"></span>
            <?php $dotIndex++; ?>
        <?php endforeach; ?>
    </div>
    <span class="d-footer-text"><?php echo htmlspecialchars($today); ?></span>
</footer>
<script>
(function () {
    'use strict';
    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function tick() {
        var now = new Date(), ce = document.getElementById('d-clock'), de = document.getElementById('d-date');
        if (ce) ce.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
        if (de) de.textContent = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
    }
    tick(); setInterval(tick, 1000);
    var INTERVAL = <?php echo (int)$slideInterval * 1000; ?>;
    var slides = document.querySelectorAll('.slide');
    var dots   = document.querySelectorAll('.slide-dot');
    var cur = 0, timer;
    if (!slides.length) return;
    function goTo(n) {
        n = ((n % slides.length) + slides.length) % slides.length;
        slides[cur].classList.remove('active'); slides[cur].classList.add('exiting');
        var lv = cur;
        setTimeout(function () { if (slides[lv]) slides[lv].classList.remove('exiting'); }, 650);
        cur = n; slides[cur].classList.add('active');
        dots.forEach(function (d, i) { d.classList.toggle('active', i === cur); });
    }
    function next() { goTo(cur + 1); }
    slides[0].classList.add('active');
    timer = setInterval(next, INTERVAL);
    dots.forEach(function (d, i) {
        d.addEventListener('click', function () { clearInterval(timer); goTo(i); timer = setInterval(next, INTERVAL); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { clearInterval(timer); next();         timer = setInterval(next, INTERVAL); }
        if (e.key === 'ArrowLeft')  { clearInterval(timer); goTo(cur - 1); timer = setInterval(next, INTERVAL); }
    });
})();
</script>
</body>
</html>
