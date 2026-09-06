<?php
declare(strict_types=1);

// Analytics Dashboard View

$analytics = $analytics ?? [];
$kpi = $analytics['kpi'] ?? [];
$performance = $analytics['performance'] ?? [];
$workload = $analytics['workload'] ?? [];
$unassignedCount = (int)($analytics['unassigned_count'] ?? 0);
$priorityDist = $analytics['priority_distribution'] ?? [];
$stateDist = $analytics['state_distribution'] ?? [];
$overdueOrders = $analytics['overdue_orders'] ?? [];

$efficiencyScore = (int)($efficiency_score ?? 0);
$topPerformers = $top_performers ?? [];
$alerts = $alerts ?? [];
$trends = $trends ?? [];
$manufacturingIntelligence = is_array($manufacturing_intelligence ?? null) ? $manufacturing_intelligence : [];
$bottlenecks = is_array($manufacturingIntelligence['bottlenecks'] ?? null) ? $manufacturingIntelligence['bottlenecks'] : [];
$correlations = is_array($manufacturingIntelligence['correlations'] ?? null) ? $manufacturingIntelligence['correlations'] : [];
$predictiveAlerts = is_array($manufacturingIntelligence['predictive_alerts'] ?? null) ? $manufacturingIntelligence['predictive_alerts'] : [];
$healthSummary = is_array($manufacturingIntelligence['health_summary'] ?? null) ? $manufacturingIntelligence['health_summary'] : [];
$recommendations = is_array($recommendations ?? null) ? $recommendations : [];

$csrf = (string)($csrf ?? '');
$appKey = (string)($app_key ?? 'studio_sales');
$role = (string)($role ?? 'viewer');

// Status class helper
$statusClass = function ($value, $good = 50, $warn = 30) {
    if ($value >= $good) return 'status-good';
    if ($value >= $warn) return 'status-warn';
    return 'status-danger';
};

// Color helper for priority/state
$priorityColor = function ($priority) {
    $p = strtolower(trim((string)$priority));
    if ($p === 'high') return '#ff4d4d';
    if ($p === 'medium') return '#ffa940';
    return '#5b8fd3';
};

$stateColor = function ($state) {
    $s = strtolower(trim((string)$state));
    if ($s === 'draft') return '#8c92a0';
    if ($s === 'approved') return '#5b8fd3';
    if ($s === 'processing') return '#ffa940';
    if ($s === 'completed') return '#52c41a';
    return '#8c92a0';
};
?>

<?php $this->layout('Shell/default', [
    'title' => $this->tr('analytics.page_title') ?? 'Analytics Dashboard',
]); ?>

<style>
.analytics-container {
    padding: 20px;
    background: var(--bg);
    min-height: 100vh;
}

.analytics-header {
    margin-bottom: 24px;
}

.analytics-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: var(--text);
    margin: 0 0 8px 0;
}

.analytics-header p {
    color: var(--muted);
    font-size: 14px;
    margin: 0;
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.kpi-card {
    background: var(--style-subtle-bg);
    border: 1px solid var(--style-border-soft);
    border-radius: 8px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    color: inherit;
}

.kpi-card:hover {
    border-color: var(--link);
    background: var(--style-subtle-hover);
    transform: translateY(-2px);
    box-shadow: var(--style-surface-shadow);
}

.kpi-card a {
    text-decoration: none;
    color: inherit;
}

.kpi-card h3 {
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0;
}

.kpi-value {
    font-size: 28px;
    font-weight: 800;
    color: var(--text);
    margin: 4px 0;
}

.kpi-subtext {
    font-size: 12px;
    color: var(--muted);
}

.status-good {
    color: var(--text);
}

.status-warn {
    color: var(--text);
}

.status-danger {
    color: var(--text);
}

.efficiency-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    font-size: 24px;
    font-weight: 800;
    border: 3px solid;
}

.efficiency-good {
    background: var(--style-subtle-bg);
    color: var(--text);
    border-color: var(--style-border-soft);
}

.efficiency-warn {
    background: var(--style-subtle-bg);
    color: var(--text);
    border-color: var(--style-border-soft);
}

.efficiency-danger {
    background: var(--style-subtle-bg);
    color: var(--text);
    border-color: var(--style-border-soft);
}

.section {
    margin-bottom: 32px;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--style-border-soft);
}

.section-header h2 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    margin: 0;
}

.section-header .badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
    background: var(--style-subtle-bg);
    color: var(--muted);
}

.chart-container {
    background: var(--style-subtle-bg);
    border: 1px solid var(--style-border-soft);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
}

.chart-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    margin: 0 0 16px 0;
}

.bar-chart {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.bar-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.bar-label {
    flex: 0 0 80px;
    font-size: 13px;
    color: var(--text);
    font-weight: 500;
}

.bar-wrapper {
    flex: 1;
    height: 24px;
    background: var(--bg);
    border-radius: 4px;
    overflow: hidden;
    position: relative;
}

.bar-fill {
    height: 100%;
    background: var(--style-subtle-bg);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 8px;
    font-size: 11px;
    color: var(--text);
    font-weight: 600;
}

.table-responsive {
    overflow-x: auto;
}

.analytics-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--style-subtle-bg);
    border-radius: 8px;
    overflow: hidden;
}

.analytics-table thead {
    background: var(--style-border-soft);
}

.analytics-table th {
    padding: 12px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    border: none;
}

.analytics-table td {
    padding: 12px;
    border-bottom: 1px solid var(--style-border-soft);
    font-size: 13px;
    color: var(--text);
}

.analytics-table tbody tr:last-child td {
    border-bottom: none;
}

.alert-box {
    background: var(--style-subtle-bg);
    border-left: 4px solid;
    border-radius: 4px;
    padding: 16px;
    margin-bottom: 12px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.alert-critical {
    border-left-color: var(--style-border-soft);
    background: var(--style-subtle-bg);
}

.alert-warning {
    border-left-color: var(--style-border-soft);
    background: var(--style-subtle-bg);
}

.alert-icon {
    flex: 0 0 20px;
    font-size: 18px;
    line-height: 1;
}

.alert-content {
    flex: 1;
}

.alert-title {
    font-weight: 600;
    color: var(--text);
    margin: 0 0 4px 0;
    font-size: 13px;
}

.alert-message {
    color: var(--muted);
    font-size: 12px;
    margin: 0;
}

.priority-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 8px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.priority-high {
    color: var(--text);
    background: var(--style-subtle-bg);
}

.priority-medium {
    color: var(--text);
    background: var(--style-subtle-bg);
}

.priority-low {
    color: var(--text);
    background: var(--style-subtle-bg);
}

.grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

@media (max-width: 768px) {
    .grid-2 {
        grid-template-columns: 1fr;
    }
    
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="analytics-container">
    <div class="analytics-header">
        <h1><?php echo $this->tr('analytics.page_title') ?? 'Analytics Dashboard'; ?></h1>
        <p><?php echo $this->tr('analytics.subtitle') ?? 'Real-time insights into order processing and team performance'; ?></p>
    </div>

    <!-- Efficiency Score Card -->
    <div class="section">
        <div class="chart-container u-style-72a68ac344">
            <div class="chart-title"><?php echo $this->tr('analytics.efficiency_score') ?? 'System Efficiency'; ?></div>
            <div class="efficiency-badge <?php echo $efficiencyScore >= 75 ? 'efficiency-good' : ($efficiencyScore >= 50 ? 'efficiency-warn' : 'efficiency-danger'); ?>">
                <?php echo $efficiencyScore; ?>
            </div>
            <p class="u-style-a2995e7082">
                <?php echo $this->tr('analytics.efficiency_based_on') ?? 'Based on completion rate, overdue %, and processing time'; ?>
            </p>
        </div>
    </div>

    <!-- System Health Summary -->
    <div class="section">
        <div class="section-header">
            <h2><?php echo $this->tr('analytics.system_health_summary') ?? 'System Health Summary'; ?></h2>
            <span class="badge"><?php echo (int)($healthSummary['score'] ?? 100); ?></span>
        </div>

        <div class="grid-2">
            <div class="chart-container u-style-72a68ac344">
                <div class="chart-title"><?php echo $this->tr('analytics.health_score') ?? 'Health Score'; ?></div>
                <?php
                $healthScore = (int)($healthSummary['score'] ?? 100);
                $healthClass = $healthScore >= 70 ? 'efficiency-good' : ($healthScore >= 50 ? 'efficiency-warn' : 'efficiency-danger');
                ?>
                <div class="efficiency-badge <?php echo $healthClass; ?>">
                    <?php echo $healthScore; ?>
                </div>
                <p class="u-style-a2995e7082">
                    <?php echo $this->tr('analytics.health_status_' . (string)($healthSummary['status'] ?? 'good')) ?? ucfirst((string)($healthSummary['status'] ?? 'good')); ?>
                </p>
            </div>

            <div class="chart-container">
                <div class="chart-title"><?php echo $this->tr('analytics.health_signal_breakdown') ?? 'Signal Breakdown'; ?></div>
                <div class="bar-chart">
                    <div class="bar-row">
                        <div class="bar-label"><?php echo $this->tr('analytics.bottleneck_alerts') ?? 'Bottlenecks'; ?></div>
                        <div class="bar-wrapper">
                            <div class="bar-fill platform-analytics-bar-fill-subtle" style="width: <?php echo min(100, (int)($healthSummary['bottleneck_count'] ?? 0) * 20); ?>%;">
                                <?php echo (int)($healthSummary['bottleneck_count'] ?? 0); ?>
                            </div>
                        </div>
                    </div>
                    <div class="bar-row">
                        <div class="bar-label"><?php echo $this->tr('analytics.correlation_signals') ?? 'Correlations'; ?></div>
                        <div class="bar-wrapper">
                            <div class="bar-fill platform-analytics-bar-fill-subtle" style="width: <?php echo min(100, (int)($healthSummary['correlation_count'] ?? 0) * 20); ?>%;">
                                <?php echo (int)($healthSummary['correlation_count'] ?? 0); ?>
                            </div>
                        </div>
                    </div>
                    <div class="bar-row">
                        <div class="bar-label"><?php echo $this->tr('analytics.predictive_alerts') ?? 'Predictive'; ?></div>
                        <div class="bar-wrapper">
                            <div class="bar-fill platform-analytics-bar-fill-danger" style="width: <?php echo min(100, (int)($healthSummary['predictive_count'] ?? 0) * 20); ?>%;">
                                <?php echo (int)($healthSummary['predictive_count'] ?? 0); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottleneck Alerts -->
    <?php if (!empty($bottlenecks) || !empty($predictiveAlerts) || !empty($correlations)) { ?>
    <div class="section">
        <div class="section-header">
            <h2><?php echo $this->tr('analytics.bottleneck_alerts') ?? 'Bottleneck Alerts'; ?></h2>
            <span class="badge"><?php echo count($bottlenecks) + count($predictiveAlerts) + count($correlations); ?></span>
        </div>

        <?php foreach ((array)$bottlenecks as $alert) {
            if (!is_array($alert)) continue;
            $severity = (string)($alert['severity'] ?? 'warning');
            $alertClass = $severity === 'critical' ? 'alert-critical' : 'alert-warning';
            $icon = $severity === 'critical' ? '⚠️' : 'ℹ️';
            $actionUrl = (string)($alert['action_url'] ?? '');
        ?>
        <div class="alert-box <?php echo $alertClass; ?>">
            <div class="alert-icon"><?php echo $icon; ?></div>
            <div class="alert-content">
                <p class="alert-title"><?php echo $this->tr('analytics.bottleneck_prefix') ?? 'Bottleneck'; ?>: <?php echo ucfirst(str_replace('_', ' ', (string)($alert['type'] ?? 'unknown'))); ?></p>
                <p class="alert-message"><?php echo (string)($alert['message'] ?? ''); ?></p>
                <?php if ($actionUrl !== '') { ?>
                    <p class="u-style-36ca0aff10"><a class="platform-analytics-drilldown-link" href="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES); ?>"><?php echo $this->tr('analytics.open_drilldown') ?? 'Open drill-down'; ?></a></p>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php foreach ((array)$predictiveAlerts as $alert) {
            if (!is_array($alert)) continue;
            $severity = (string)($alert['severity'] ?? 'warning');
            $alertClass = $severity === 'critical' ? 'alert-critical' : 'alert-warning';
            $icon = '🔮';
            $actionUrl = (string)($alert['action_url'] ?? '');
        ?>
        <div class="alert-box <?php echo $alertClass; ?>">
            <div class="alert-icon"><?php echo $icon; ?></div>
            <div class="alert-content">
                <p class="alert-title"><?php echo $this->tr('analytics.predictive_prefix') ?? 'Predictive'; ?>: <?php echo ucfirst(str_replace('_', ' ', (string)($alert['type'] ?? 'unknown'))); ?></p>
                <p class="alert-message"><?php echo (string)($alert['message'] ?? ''); ?></p>
                <?php if ($actionUrl !== '') { ?>
                    <p class="u-style-36ca0aff10"><a class="platform-analytics-drilldown-link" href="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES); ?>"><?php echo $this->tr('analytics.open_drilldown') ?? 'Open drill-down'; ?></a></p>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php foreach ((array)$correlations as $signal) {
            if (!is_array($signal)) continue;
            $severity = (string)($signal['severity'] ?? 'warning');
            $alertClass = $severity === 'critical' ? 'alert-critical' : 'alert-warning';
        ?>
        <div class="alert-box <?php echo $alertClass; ?>">
            <div class="alert-icon">🔗</div>
            <div class="alert-content">
                <p class="alert-title"><?php echo $this->tr('analytics.correlation_signals') ?? 'Correlation Signal'; ?></p>
                <p class="alert-message"><?php echo (string)($signal['message'] ?? ''); ?></p>
            </div>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <a href="/ops/analytics/drilldown?type=all" class="kpi-card">
            <h3><?php echo $this->tr('analytics.kpi.total_orders') ?? 'Total Orders'; ?></h3>
            <div class="kpi-value"><?php echo (int)($kpi['total_orders'] ?? 0); ?></div>
            <span class="kpi-subtext"><?php echo $this->tr('analytics.kpi.active_items') ?? 'Active items'; ?></span>
        </a>

        <a href="/ops/analytics/drilldown?type=completed" class="kpi-card">
            <h3><?php echo $this->tr('analytics.kpi.completed_today') ?? 'Completed Today'; ?></h3>
            <div class="kpi-value <?php echo $statusClass((int)($kpi['completed_today'] ?? 0), 10, 5); ?>">
                <?php echo (int)($kpi['completed_today'] ?? 0); ?>
            </div>
            <span class="kpi-subtext"><?php echo $this->tr('analytics.kpi.last_24h') ?? 'Last 24 hours'; ?></span>
        </a>

        <a href="/ops/analytics/drilldown?type=overdue" class="kpi-card">
            <h3><?php echo $this->tr('analytics.kpi.overdue') ?? 'Overdue'; ?></h3>
            <div class="kpi-value <?php echo $statusClass((int)($kpi['overdue_count'] ?? 0), -50, 5); ?>">
                <?php echo (int)($kpi['overdue_count'] ?? 0); ?>
            </div>
            <span class="kpi-subtext"><?php echo $this->tr('analytics.kpi.pending_completion') ?? 'Pending completion'; ?></span>
        </a>

        <a href="/ops/analytics/drilldown?type=processing" class="kpi-card">
            <h3><?php echo $this->tr('analytics.kpi.in_progress') ?? 'In Progress'; ?></h3>
            <div class="kpi-value"><?php echo (int)($kpi['in_progress'] ?? 0); ?></div>
            <span class="kpi-subtext"><?php echo $this->tr('analytics.kpi.processing_state') ?? 'Processing state'; ?></span>
        </a>

        <a href="/ops/analytics/drilldown?type=draft" class="kpi-card">
            <h3><?php echo $this->tr('analytics.kpi.pending') ?? 'Pending Approval'; ?></h3>
            <div class="kpi-value"><?php echo (int)($kpi['pending_approval'] ?? 0); ?></div>
            <span class="kpi-subtext"><?php echo $this->tr('analytics.kpi.awaiting_approval') ?? 'Awaiting approval'; ?></span>
        </a>

        <div class="kpi-card">
            <h3><?php echo $this->tr('analytics.kpi.avg_processing') ?? 'Avg Processing'; ?></h3>
            <div class="kpi-value"><?php echo round((float)($kpi['avg_processing_hours'] ?? 0), 1); ?><span class="u-style-df67104f3b"> hrs</span></div>
            <span class="kpi-subtext"><?php echo $this->tr('analytics.kpi.from_approval') ?? 'From approval → completion'; ?></span>
        </div>

        <div class="kpi-card">
            <h3><?php echo $this->tr('analytics.kpi.avg_turnaround') ?? 'Avg Turnaround'; ?></h3>
            <div class="kpi-value"><?php echo round((float)($kpi['avg_turnaround_days'] ?? 0), 1); ?><span class="u-style-df67104f3b"> d</span></div>
            <span class="kpi-subtext"><?php echo $this->tr('analytics.kpi.create_to_complete') ?? 'Create → complete'; ?></span>
        </div>

        <div class="kpi-card">
            <h3><?php echo $this->tr('analytics.kpi.unassigned') ?? 'Unassigned'; ?></h3>
            <div class="kpi-value <?php echo $statusClass(100 - $unassignedCount, 90, 70); ?>">
                <?php echo $unassignedCount; ?>
            </div>
            <span class="kpi-subtext"><?php echo $this->tr('analytics.kpi.awaiting_assignment') ?? 'Awaiting assignment'; ?></span>
        </div>
    </div>

    <!-- Performance Section -->
    <div class="section">
        <div class="section-header">
            <h2><?php echo $this->tr('analytics.performance') ?? 'Performance'; ?></h2>
            <span class="badge"><?php echo round((float)($performance['completion_rate'] ?? 0), 1); ?>%</span>
        </div>

        <div class="chart-container">
            <div class="chart-title"><?php echo $this->tr('analytics.completion_rate') ?? 'Completion Rate'; ?></div>
            <div class="bar-chart">
                <div class="bar-row">
                    <div class="bar-label"><?php echo $this->tr('analytics.completed') ?? 'Completed'; ?></div>
                    <div class="bar-wrapper">
                        <div class="bar-fill" style="width: <?php echo min(100, (float)($performance['completion_rate'] ?? 0)); ?>%;">
                            <?php echo round((float)($performance['completion_rate'] ?? 0), 1); ?>%
                        </div>
                    </div>
                </div>
                <div class="bar-row">
                    <div class="bar-label"><?php echo $this->tr('analytics.pending') ?? 'Pending'; ?></div>
                    <div class="bar-wrapper">
                        <div class="bar-fill platform-analytics-bar-fill-subtle" style="width: <?php echo 100 - min(100, (float)($performance['completion_rate'] ?? 0)); ?>%;">
                            <?php echo round(100 - (float)($performance['completion_rate'] ?? 0), 1); ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <div class="chart-container">
                <div class="chart-title"><?php echo $this->tr('analytics.priority_distribution') ?? 'Priority Distribution'; ?></div>
                <div class="bar-chart">
                    <?php
                    $priorities = ['high', 'medium', 'low'];
                    $priorityLabels = [
                        'high' => $this->tr('analytics.priority_high') ?? 'High',
                        'medium' => $this->tr('analytics.priority_medium') ?? 'Medium',
                        'low' => $this->tr('analytics.priority_low') ?? 'Low',
                    ];
                    $total = array_sum((array)$priorityDist);
                    foreach ($priorities as $p) {
                        $count = (int)($priorityDist[$p] ?? 0);
                        $pct = $total > 0 ? ($count / $total) * 100 : 0;
                    ?>
                    <div class="bar-row">
                        <div class="bar-label"><?php echo $priorityLabels[$p]; ?></div>
                        <div class="bar-wrapper">
                            <div class="bar-fill" style="width: <?php echo max(5, $pct); ?>%; background: <?php echo $priorityColor($p); ?>;">
                                <?php echo $count; ?>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="chart-container">
                <div class="chart-title"><?php echo $this->tr('analytics.state_distribution') ?? 'State Distribution'; ?></div>
                <div class="bar-chart">
                    <?php
                    $stateLabels = [
                        'draft' => $this->tr('analytics.state_draft') ?? 'Draft',
                        'approved' => $this->tr('analytics.state_approved') ?? 'Approved',
                        'processing' => $this->tr('analytics.state_processing') ?? 'Processing',
                        'completed' => $this->tr('analytics.state_completed') ?? 'Completed',
                    ];
                    $stateTotal = array_sum((array)$stateDist);
                    foreach (['draft', 'approved', 'processing', 'completed'] as $s) {
                        $count = (int)($stateDist[$s] ?? 0);
                        $pct = $stateTotal > 0 ? ($count / $stateTotal) * 100 : 0;
                    ?>
                    <div class="bar-row">
                        <div class="bar-label"><?php echo $stateLabels[$s]; ?></div>
                        <div class="bar-wrapper">
                            <div class="bar-fill" style="width: <?php echo max(5, $pct); ?>%; background: <?php echo $stateColor($s); ?>;">
                                <?php echo $count; ?>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts Section -->
    <?php if (!empty($alerts)) { ?>
    <div class="section">
        <div class="section-header">
            <h2><?php echo $this->tr('analytics.alerts') ?? 'System Alerts'; ?></h2>
            <span class="badge"><?php echo count((array)$alerts); ?></span>
        </div>

        <?php foreach ((array)$alerts as $alert) {
            if (!is_array($alert)) continue;
            $severity = (string)($alert['severity'] ?? 'warning');
            $alertClass = $severity === 'critical' ? 'alert-critical' : 'alert-warning';
            $icon = $severity === 'critical' ? '⚠️' : 'ℹ️';
        ?>
        <div class="alert-box <?php echo $alertClass; ?>">
            <div class="alert-icon"><?php echo $icon; ?></div>
            <div class="alert-content">
                <p class="alert-title"><?php echo ucfirst(str_replace('_', ' ', (string)($alert['type'] ?? 'unknown'))); ?></p>
                <p class="alert-message"><?php echo (string)($alert['message'] ?? ''); ?></p>
            </div>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

    <!-- Recommended Actions -->
    <div class="section">
        <div class="section-header">
            <h2><?php echo $this->tr('analytics.recommended_actions') ?? 'Recommended Actions'; ?></h2>
            <span class="badge"><?php echo count($recommendations); ?></span>
        </div>

        <?php if (!empty($recommendations)) { ?>
            <?php foreach ((array)$recommendations as $item) {
                if (!is_array($item)) continue;
                $priority = strtolower((string)($item['priority'] ?? 'low'));
                $priorityClass = $priority === 'high' ? 'priority-high' : ($priority === 'medium' ? 'priority-medium' : 'priority-low');
            ?>
            <div class="chart-container u-style-72d6c38e5f">
                <div class="u-style-93c6a8ff07">
                    <div class="chart-title u-style-648149cea2"><?php echo ucfirst(str_replace('_', ' ', (string)($item['type'] ?? 'recommendation'))); ?></div>
                    <span class="priority-badge <?php echo $priorityClass; ?>"><?php echo $this->tr('analytics.priority_' . $priority) ?? ucfirst($priority); ?></span>
                </div>
                <p class="alert-message u-style-54d32d80db"><?php echo (string)($item['message'] ?? ''); ?></p>
                <p class="alert-message u-style-082f6e5d85"><strong><?php echo $this->tr('analytics.decision_guidance') ?? 'Decision Guidance'; ?>:</strong> <?php echo (string)($item['decision_guidance'] ?? ''); ?></p>
                <p class="alert-message u-style-082f6e5d85"><strong><?php echo $this->tr('analytics.audience') ?? 'Audience'; ?>:</strong> <?php echo ucfirst((string)($item['audience'] ?? 'manager')); ?></p>
                <p class="u-style-54d32d80db"><a class="platform-analytics-drilldown-link" href="<?php echo htmlspecialchars((string)($item['action_url'] ?? '/ops/analytics'), ENT_QUOTES); ?>"><?php echo $this->tr('analytics.open_drilldown') ?? 'Open drill-down'; ?></a></p>
            </div>
            <?php } ?>
        <?php } else { ?>
            <div class="chart-container">
                <p class="alert-message"><?php echo $this->tr('analytics.no_recommendations') ?? 'No recommendations at this time'; ?></p>
            </div>
        <?php } ?>
    </div>

    <!-- Workload Section -->
    <div class="section">
        <div class="section-header">
            <h2><?php echo $this->tr('analytics.workload') ?? 'Team Workload'; ?></h2>
            <span class="badge"><?php echo count((array)$workload); ?></span>
        </div>

        <?php if (!empty($workload)) { ?>
        <div class="table-responsive">
            <table class="analytics-table">
                <thead>
                    <tr>
                        <th><?php echo $this->tr('analytics.table.user_id') ?? 'User ID'; ?></th>
                        <th><?php echo $this->tr('analytics.table.total_tasks') ?? 'Total Tasks'; ?></th>
                        <th><?php echo $this->tr('analytics.table.active') ?? 'Active'; ?></th>
                        <th><?php echo $this->tr('analytics.table.overdue') ?? 'Overdue'; ?></th>
                        <th><?php echo $this->tr('analytics.table.completed') ?? 'Completed'; ?></th>
                        <th><?php echo $this->tr('analytics.table.workload') ?? 'Workload'; ?></th>
                        <th><?php echo $this->tr('analytics.table.status') ?? 'Status'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array)$workload as $userId => $row) {
                        if (!is_array($row)) continue;
                        $workloadClass = (string)($row['workload_class'] ?? 'workload-low');
                        $overdueClass = (string)($row['overdue_class'] ?? 'overdue-ok');
                    ?>
                    <tr>
                        <td><strong>User #<?php echo (int)$userId; ?></strong></td>
                        <td><?php echo (int)($row['total_tasks'] ?? 0); ?></td>
                        <td><?php echo (int)($row['active_tasks'] ?? 0); ?></td>
                        <td><span class="u-style-b7082be545"><?php echo (int)($row['overdue_tasks'] ?? 0); ?></span></td>
                        <td><?php echo (int)($row['completed_today'] ?? 0); ?></td>
                        <td>
                            <div class="u-style-3088da1131">
                                <div class="u-style-e14aeaf831">
                                    <div class="ui-block platform-analytics-workload-fill" style="width: <?php echo (float)($row['workload_percent'] ?? 0); ?>%;"></div>
                                </div>
                                <span class="u-style-1b7b4f1ed6"><?php echo round((float)($row['workload_percent'] ?? 0), 0); ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge platform-analytics-overdue-badge <?php echo $overdueClass; ?>">
                                <?php echo $row['overdue_tasks'] > 0 ? '⚠️ ALERT' : '✓ OK'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } else { ?>
        <div class="u-style-a34c3f9e8d">
            <p><?php echo $this->tr('analytics.no_workload_data') ?? 'No workload data available'; ?></p>
        </div>
        <?php } ?>
    </div>

    <!-- Trends Section -->
    <div class="section">
        <div class="section-header">
            <h2><?php echo $this->tr('analytics.trends.title_manufacturing') ?? 'Manufacturing Trends (Last 7 Days)'; ?></h2>
        </div>

        <div class="u-style-72096adc7d">
            <div class="chart-container">
                <h3 class="chart-title"><?php echo $this->tr('analytics.trends.qc_pass_rate_per_day') ?? 'QC Pass Rate Per Day'; ?></h3>
                <?php if (!empty($analytics['trend_qc_pass_rate'])): ?>
                <div class="bar-chart">
                    <?php foreach ((array)$analytics['trend_qc_pass_rate'] as $day): ?>
                        <?php if (!is_array($day)) continue; ?>
                        <div class="bar-row">
                            <div class="bar-label"><?php echo (string)($day['day_short'] ?? ''); ?></div>
                            <div class="bar-wrapper">
                                <?php if ((float)($day['value'] ?? 0) > 0): ?>
                                <div class="bar-fill" style="width: <?php echo (float)($day['bar_height'] ?? 0); ?>%;">
                                    <?php echo (string)($day['value_text'] ?? '0%'); ?>
                                </div>
                                <?php else: ?>
                                <div class="u-style-c5077c6432">0%</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="u-style-54d32d80db">
                    <a class="u-style-931b432182" href="/ops/analytics/qc/drilldown?type=fail"><?php echo $this->tr('analytics.actions.review_failures') ?? 'Review failed and rework entries'; ?></a>
                </div>
                <?php else: ?>
                <p class="u-style-4ffb324c7f">
                    <?php echo $this->tr('analytics.trends.no_data') ?? 'No trend data available'; ?>
                </p>
                <?php endif; ?>
            </div>

            <div class="chart-container">
                <h3 class="chart-title"><?php echo $this->tr('analytics.trends.production_output_per_day') ?? 'Production Output Per Day'; ?></h3>
                <?php if (!empty($analytics['trend_production_output'])): ?>
                <div class="bar-chart">
                    <?php foreach ((array)$analytics['trend_production_output'] as $day): ?>
                        <?php if (!is_array($day)) continue; ?>
                        <div class="bar-row">
                            <div class="bar-label"><?php echo (string)($day['day_short'] ?? ''); ?></div>
                            <div class="bar-wrapper">
                                <?php if ((float)($day['value'] ?? 0) > 0): ?>
                                <div class="bar-fill platform-analytics-bar-fill-subtle" style="width: <?php echo (float)($day['bar_height'] ?? 0); ?>%;">
                                    <?php echo (string)($day['value_text'] ?? '0'); ?>
                                </div>
                                <?php else: ?>
                                <div class="u-style-c5077c6432">0</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="u-style-54d32d80db">
                    <a class="u-style-931b432182" href="/ops/analytics/production/drilldown?type=low_utilization"><?php echo $this->tr('analytics.actions.review_low_utilization') ?? 'Review low utilization machines'; ?></a>
                </div>
                <?php else: ?>
                <p class="u-style-4ffb324c7f">
                    <?php echo $this->tr('analytics.trends.no_data') ?? 'No trend data available'; ?>
                </p>
                <?php endif; ?>
            </div>

            <div class="chart-container">
                <h3 class="chart-title"><?php echo $this->tr('analytics.trends.dispatch_delay_per_day') ?? 'Dispatch Delay Per Day'; ?></h3>
                <?php if (!empty($analytics['trend_dispatch_delay'])): ?>
                <div class="bar-chart">
                    <?php foreach ((array)$analytics['trend_dispatch_delay'] as $day): ?>
                        <?php if (!is_array($day)) continue; ?>
                        <div class="bar-row">
                            <div class="bar-label"><?php echo (string)($day['day_short'] ?? ''); ?></div>
                            <div class="bar-wrapper">
                                <?php if ((float)($day['value'] ?? 0) > 0): ?>
                                <div class="bar-fill platform-analytics-bar-fill-subtle" style="width: <?php echo (float)($day['bar_height'] ?? 0); ?>%;">
                                    <?php echo (string)($day['value_text'] ?? '0'); ?>
                                </div>
                                <?php else: ?>
                                <div class="u-style-c5077c6432">0</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="u-style-54d32d80db">
                    <a class="u-style-931b432182" href="/ops/analytics/dispatch/drilldown?type=delayed"><?php echo $this->tr('analytics.actions.review_delayed_dispatch') ?? 'Review delayed shipments'; ?></a>
                </div>
                <?php else: ?>
                <p class="u-style-4ffb324c7f">
                    <?php echo $this->tr('analytics.trends.no_data') ?? 'No trend data available'; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Overdue Orders -->
    <div class="section">
        <div class="section-header">
            <h2><?php echo $this->tr('analytics.overdue_orders') ?? 'Overdue Orders'; ?></h2>
            <span class="badge"><?php echo count((array)$overdueOrders); ?></span>
        </div>

        <?php if (!empty($overdueOrders)) { ?>
        <div class="table-responsive">
            <table class="analytics-table">
                <thead>
                    <tr>
                        <th><?php echo $this->tr('analytics.table.order_no') ?? 'Order No'; ?></th>
                        <th><?php echo $this->tr('analytics.table.customer') ?? 'Customer'; ?></th>
                        <th><?php echo $this->tr('analytics.table.priority') ?? 'Priority'; ?></th>
                        <th><?php echo $this->tr('analytics.table.state') ?? 'State'; ?></th>
                        <th><?php echo $this->tr('analytics.table.due_at') ?? 'Due At'; ?></th>
                        <th><?php echo $this->tr('analytics.table.assigned_to') ?? 'Assigned To'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array)$overdueOrders as $order) {
                        if (!is_array($order)) continue;
                    ?>
                    <tr>
                        <td><strong><?php echo (string)($order['order_no'] ?? ''); ?></strong></td>
                        <td><?php echo (string)($order['customer'] ?? ''); ?></td>
                        <td>
                            <span class="badge" style="background: <?php echo $priorityColor($order['priority']); ?>20; color: <?php echo $priorityColor($order['priority']); ?>; border: 1px solid <?php echo $priorityColor($order['priority']); ?>40;">
                                <?php echo ucfirst((string)($order['priority'] ?? 'medium')); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background: <?php echo $stateColor($order['state']); ?>20; color: <?php echo $stateColor($order['state']); ?>; border: 1px solid <?php echo $stateColor($order['state']); ?>40;">
                                <?php echo ucfirst((string)($order['state'] ?? 'draft')); ?>
                            </span>
                        </td>
                        <td>
                            <span class="u-style-b7082be545">
                                <?php echo date('M d, H:i', strtotime((string)($order['due_at'] ?? 'now'))); ?>
                            </span>
                        </td>
                        <td><?php echo (int)($order['assigned_to'] ?? 0) > 0 ? 'User #' . (int)($order['assigned_to'] ?? 0) : '—'; ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } else { ?>
        <div class="u-style-a34c3f9e8d">
            <p><?php echo $this->tr('analytics.no_overdue_orders') ?? 'No overdue orders'; ?></p>
        </div>
        <?php } ?>
    </div>

    <!-- Manufacturing Modules Section -->
    <div class="u-style-dcfca6cd96">
        <div class="analytics-header">
            <h2><?php echo $this->tr('analytics.manufacturing') ?? 'Manufacturing Operations'; ?></h2>
            <p class="u-style-07d18c8fca"><?php echo $this->tr('analytics.manufacturing_subtitle') ?? 'Quality Control, Dispatch, Assembly, and Production metrics'; ?></p>
        </div>

        <div class="section">
            <div class="section-header">
                <h2><?php echo $this->tr('analytics.qc_performance') ?? 'QC Performance'; ?></h2>
            </div>

            <div class="kpi-grid">
                <a class="u-style-7054009588" href="/ops/analytics/qc/drilldown?type=all">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.qc.pass_rate') ?? 'Pass Rate'; ?></h3>
                        <div class="kpi-value <?php echo $statusClass((float)($qc_metrics['pass_rate'] ?? 0), 95, 85); ?>">
                            <?php echo round((float)($qc_metrics['pass_rate'] ?? 0), 1); ?><span class="u-style-df67104f3b">%</span>
                        </div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.qc.items_passed') ?? 'Items passed inspection'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/qc/drilldown?type=fail">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.qc.fail_rate') ?? 'Fail Rate'; ?></h3>
                        <div class="kpi-value <?php echo $statusClass(100 - (float)($qc_metrics['fail_rate'] ?? 0), 95, 85); ?>">
                            <?php echo round((float)($qc_metrics['fail_rate'] ?? 0), 1); ?><span class="u-style-df67104f3b">%</span>
                        </div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.qc.items_failed') ?? 'Items failed inspection'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/qc/drilldown?type=rework">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.qc.rework_count') ?? 'Rework Count'; ?></h3>
                        <div class="kpi-value"><?php echo (int)($qc_metrics['rework_count'] ?? 0); ?></div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.qc.pending_rework') ?? 'Pending rework'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/qc/drilldown?type=all">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.qc.total_checked') ?? 'Total Checked'; ?></h3>
                        <div class="kpi-value"><?php echo round((float)($qc_metrics['total_checked'] ?? 0), 0); ?></div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.qc.items_inspected') ?? 'Items inspected'; ?></span>
                    </div>
                </a>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2><?php echo $this->tr('analytics.dispatch_status') ?? 'Dispatch Status'; ?></h2>
            </div>

            <div class="kpi-grid">
                <a class="u-style-7054009588" href="/ops/analytics/dispatch/drilldown?type=all">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.dispatch.dispatched_today') ?? 'Dispatched Today'; ?></h3>
                        <div class="kpi-value"><?php echo (int)($dispatch_metrics['dispatched_today'] ?? 0); ?></div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.dispatch.last_24h') ?? 'Last 24 hours'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/dispatch/drilldown?type=pending">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.dispatch.pending') ?? 'Pending Dispatch'; ?></h3>
                        <div class="kpi-value <?php echo $statusClass(100 - (int)($dispatch_metrics['pending_dispatch'] ?? 0), 90, 75); ?>">
                            <?php echo (int)($dispatch_metrics['pending_dispatch'] ?? 0); ?>
                        </div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.dispatch.ready_prepared') ?? 'Ready / Prepared'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/dispatch/drilldown?type=delayed">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.dispatch.delivery_delay') ?? 'Delivery Delay'; ?></h3>
                        <div class="kpi-value <?php echo $statusClass(100 - (int)($dispatch_metrics['delivery_delay'] ?? 0), 95, 90); ?>">
                            <?php echo (int)($dispatch_metrics['delivery_delay'] ?? 0); ?>
                        </div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.dispatch.beyond_sla') ?? 'Beyond SLA'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/dispatch/drilldown?type=pending">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.dispatch.pending_qty') ?? 'Pending Quantity'; ?></h3>
                        <div class="kpi-value"><?php echo round((float)($dispatch_metrics['pending_quantity'] ?? 0), 0); ?></div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.dispatch.units') ?? 'Units'; ?></span>
                    </div>
                </a>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2><?php echo $this->tr('analytics.assembly_flow') ?? 'Assembly Flow'; ?></h2>
            </div>

            <div class="kpi-grid">
                <a class="u-style-7054009588" href="/ops/analytics/assembly/drilldown?type=completed">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.assembly.completed_today') ?? 'Completed Today'; ?></h3>
                        <div class="kpi-value"><?php echo (int)($assembly_metrics['completed_today'] ?? 0); ?></div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.assembly.last_24h') ?? 'Last 24 hours'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/assembly/drilldown?type=pending">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.assembly.pending') ?? 'Pending Assembly'; ?></h3>
                        <div class="kpi-value <?php echo $statusClass(100 - (int)($assembly_metrics['pending_assembly'] ?? 0), 90, 75); ?>">
                            <?php echo (int)($assembly_metrics['pending_assembly'] ?? 0); ?>
                        </div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.assembly.in_progress_draft') ?? 'In progress / Draft'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/assembly/drilldown?type=all">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.assembly.avg_time') ?? 'Avg Assembly Time'; ?></h3>
                        <div class="kpi-value"><?php echo round((float)($assembly_metrics['avg_assembly_hours'] ?? 0), 1); ?><span class="u-style-df67104f3b"> hrs</span></div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.assembly.per_unit') ?? 'Per unit'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/assembly/drilldown?type=high_rejection">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.assembly.rejection_rate') ?? 'Rejection Rate'; ?></h3>
                        <div class="kpi-value <?php echo $statusClass(100 - (float)($assembly_metrics['rejection_rate'] ?? 0), 95, 90); ?>">
                            <?php echo round((float)($assembly_metrics['rejection_rate'] ?? 0), 1); ?><span class="u-style-df67104f3b">%</span>
                        </div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.assembly.defect_rate') ?? 'Defect rate'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/assembly/drilldown?type=completed">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.assembly.completed_qty') ?? 'Completed Qty'; ?></h3>
                        <div class="kpi-value"><?php echo round((float)($assembly_metrics['total_completed_qty'] ?? 0), 0); ?></div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.assembly.units') ?? 'Units'; ?></span>
                    </div>
                </a>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2><?php echo $this->tr('analytics.production_metrics') ?? 'Production Metrics'; ?></h2>
            </div>

            <div class="kpi-grid">
                <a class="u-style-7054009588" href="/ops/analytics/production/drilldown?type=all">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.production.output_today') ?? 'Output Today'; ?></h3>
                        <div class="kpi-value"><?php echo round((float)($production_metrics['production_today'] ?? 0), 0); ?></div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.production.units') ?? 'Units'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/production/drilldown?type=low_utilization">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.production.utilization') ?? 'Machine Utilization'; ?></h3>
                        <div class="kpi-value <?php echo $statusClass((float)($production_metrics['machine_utilization'] ?? 0), 80, 60); ?>">
                            <?php echo round((float)($production_metrics['machine_utilization'] ?? 0), 1); ?><span class="u-style-df67104f3b">%</span>
                        </div>
                        <span class="kpi-subtext"><?php echo (int)($production_metrics['active_machines'] ?? 0); ?>/<?php echo (int)($production_metrics['total_machines'] ?? 0); ?> <?php echo $this->tr('analytics.production.machines_active') ?? 'machines active'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/production/drilldown?type=all">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.production.good_qty') ?? 'Good Qty'; ?></h3>
                        <div class="kpi-value"><?php echo round((float)($production_metrics['good_qty'] ?? 0), 0); ?></div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.production.quality_units') ?? 'Quality units'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/production/drilldown?type=high_rejection">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.production.rejected_qty') ?? 'Rejected Qty'; ?></h3>
                        <div class="kpi-value <?php echo $statusClass(100 - (float)($production_metrics['rejected_qty'] ?? 0), 95, 90); ?>">
                            <?php echo round((float)($production_metrics['rejected_qty'] ?? 0), 0); ?>
                        </div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.production.defective_units') ?? 'Defective units'; ?></span>
                    </div>
                </a>

                <a class="u-style-7054009588" href="/ops/analytics/production/drilldown?type=all">
                    <div class="kpi-card u-style-8410c989c9">
                        <h3><?php echo $this->tr('analytics.production.oee') ?? 'Overall Equipment Effectiveness'; ?></h3>
                        <div class="kpi-value <?php echo $statusClass((float)($production_metrics['overall_equipment_effectiveness'] ?? 0), 85, 70); ?>">
                            <?php echo round((float)($production_metrics['overall_equipment_effectiveness'] ?? 0), 1); ?><span class="u-style-df67104f3b">%</span>
                        </div>
                        <span class="kpi-subtext"><?php echo $this->tr('analytics.production.7day_average') ?? '7-day average'; ?></span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
