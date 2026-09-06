<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$payload = is_array($payload ?? null) ? $payload : [];
$product = is_array($payload['product'] ?? null) ? $payload['product'] : [];
$demandSummary = is_array($payload['demand_summary'] ?? null) ? $payload['demand_summary'] : [];
$orderTimeline = is_array($payload['order_timeline'] ?? null) ? $payload['order_timeline'] : [];
$orderRangeSummary = is_array($payload['order_range_summary'] ?? null) ? $payload['order_range_summary'] : [];
$stockSummary = is_array($payload['stock_summary'] ?? null) ? $payload['stock_summary'] : [];
$recentLedger = is_array($payload['recent_ledger'] ?? null) ? $payload['recent_ledger'] : [];
$plannedSupplySummary = is_array($payload['planned_supply_summary'] ?? null) ? $payload['planned_supply_summary'] : [];
$productionSummary = is_array($payload['production_summary'] ?? null) ? $payload['production_summary'] : [];
$recentProductionPlans = is_array($payload['recent_production_plans'] ?? null) ? $payload['recent_production_plans'] : [];
$recentProductionEntries = is_array($payload['recent_production_entries'] ?? null) ? $payload['recent_production_entries'] : [];
$qcSummary = is_array($payload['qc_summary'] ?? null) ? $payload['qc_summary'] : [];
$recentQcPlans = is_array($payload['recent_qc_plans'] ?? null) ? $payload['recent_qc_plans'] : [];
$recentQcEntries = is_array($payload['recent_qc_entries'] ?? null) ? $payload['recent_qc_entries'] : [];
$dispatchSummary = is_array($payload['dispatch_summary'] ?? null) ? $payload['dispatch_summary'] : [];
$recentDispatchEntries = is_array($payload['recent_dispatch_entries'] ?? null) ? $payload['recent_dispatch_entries'] : [];
$machineAssignment = is_array($payload['machine_assignment'] ?? null) ? $payload['machine_assignment'] : [];
$responsibility = is_array($payload['responsibility'] ?? null) ? $payload['responsibility'] : [];
$machineUsage = is_array($payload['machine_usage'] ?? null) ? $payload['machine_usage'] : [];
$molds = is_array($payload['molds'] ?? null) ? $payload['molds'] : [];
$partMaterials = is_array($payload['part_materials'] ?? null) ? $payload['part_materials'] : [];
$moldFitSummary = is_array($payload['mold_fit_summary'] ?? null) ? $payload['mold_fit_summary'] : [];
$activeMachine = is_array($machineAssignment['active_machine'] ?? null) ? $machineAssignment['active_machine'] : null;
$compatibleMachines = is_array($machineAssignment['compatible_machines'] ?? null) ? $machineAssignment['compatible_machines'] : [];
$assignedUser = is_array($responsibility['assigned_user'] ?? null) ? $responsibility['assigned_user'] : null;
$availableResponsibilityUsers = is_array($responsibility['available_users'] ?? null) ? $responsibility['available_users'] : [];
$materialOptions = is_array($payload['material_options'] ?? null) ? $payload['material_options'] : [];
$handoff = is_array($payload['handoff'] ?? null) ? $payload['handoff'] : [];
$handoffCounts = is_array($handoff['counts'] ?? null) ? $handoff['counts'] : [];
$handoffItems = is_array($handoff['items'] ?? null) ? $handoff['items'] : [];
$riskSummary = is_array($payload['risk_summary'] ?? null) ? $payload['risk_summary'] : [];
$links = is_array($payload['links'] ?? null) ? $payload['links'] : [];
$supplyProfile = is_array($payload['supply_profile'] ?? null) ? $payload['supply_profile'] : [];
$operationalSnapshot = is_array($payload['operational_snapshot'] ?? null) ? $payload['operational_snapshot'] : [];
$orderContext = is_array($payload['order_context'] ?? null) ? $payload['order_context'] : [];
$workflowContext = is_array($payload['workflow_context'] ?? null) ? $payload['workflow_context'] : [];
$executionRoute = is_array($payload['execution_route'] ?? null) ? $payload['execution_route'] : [];
$recommendedActions = is_array($payload['recommended_actions'] ?? null) ? $payload['recommended_actions'] : [];
$timeline = is_array($payload['timeline'] ?? null) ? $payload['timeline'] : [];
$currentRole = strtolower(trim((string)($current_role ?? '')));
$canEditLeadSections = !empty($can_edit_lead_sections);
$canManageResponsibility = !empty($can_manage_responsibility);
$canManageLifecycle = !empty($can_manage_lifecycle);
$isAdmin = !empty($is_admin);
$productId = (int)($product['id'] ?? 0);
$labelDefaultCopies = max(1, (int)($links['label_copies_default'] ?? 8));
$labelDefaultMachineNo = (string)($links['label_machine_no_default'] ?? '');
$labelDefaultCaseNumber = (string)($links['label_case_number_default'] ?? '');

$fmt = static function (float $n, int $precision = 2): string {
    return number_format($n, $precision, '.', ',');
};

$riskBadge = static function (string $state): string {
    $s = strtolower(trim($state));
    if ($s === 'blocked') {
        return 'p360-badge-bad';
    }
    if ($s === 'under_pressure') {
        return 'p360-badge-warn';
    }
    return 'p360-badge-good';
};

$sourceUrl = static function (string $module, int $id): string {
    if ($id <= 0) {
        return '#';
    }
    $map = [
        'ProductionEntries' => '/production-entries/edit?id=',
        'DispatchEntries' => '/dispatch-entries/edit?id=',
        'QCEntries' => '/qc-entries/edit?id=',
        'ProductionPlans' => '/production-plans/edit?id=',
        'QCPlans' => '/qc-plans/edit?id=',
    ];
    return ($map[$module] ?? '#') . $id;
};

$handoffUrl = static function (string $entityType, int $entityId): string {
    if ($entityId <= 0) {
        return '#';
    }
    if ($entityType === 'daily_order') {
        return '/daily-orders/360?id=' . $entityId;
    }
    if ($entityType === 'production_entry') {
        return '/production-entries/edit?id=' . $entityId;
    }
    if ($entityType === 'qc_entry') {
        return '/qc-entries/edit?id=' . $entityId;
    }
    if ($entityType === 'dispatch_entry') {
        return '/dispatch-entries/edit?id=' . $entityId;
    }
    return '#';
};

$roleToken = static function (string $role): string {
    $role = strtolower(trim($role));
    if ($role === '' || $role === 'all') {
        return 'all';
    }
    if (str_contains($role, 'dispatch')) {
        return 'dispatch';
    }
    if (str_contains($role, 'qc')) {
        return 'qc';
    }
    if (str_contains($role, 'machine')) {
        return 'machine';
    }
    if (str_contains($role, 'plan')) {
        return 'planning';
    }
    if (str_contains($role, 'admin')) {
        return 'admin';
    }
    return 'all';
};

$actionVisible = static function (array $action, string $roleGroup): bool {
    $roles = array_map(static fn($v): string => strtolower(trim((string)$v)), (array)($action['roles'] ?? ['all']));
    if (in_array('all', $roles, true) || in_array($roleGroup, $roles, true)) {
        return true;
    }
    return $roleGroup === 'admin';
};

$renderList = static function (string $title, string $emptyText, array $rows, callable $renderer): void {
?>
<section class="card">
  <h3 class="p360-section-title"><?= e($title) ?></h3>
  <?php if (empty($rows)): ?>
    <div class="p360-empty"><?= e($emptyText) ?></div>
  <?php else: ?>
    <div class="p360-table-wrap"><?php $renderer($rows); ?></div>
  <?php endif; ?>
</section>
<?php
};

$partName = (string)($product['parts_name'] ?? '-');
$partNumber = (string)($product['parts_number'] ?? '-');
$riskLabel = (string)($riskSummary['label'] ?? 'Healthy');
$riskState = (string)($riskSummary['state'] ?? 'healthy');
$statusLabel = (int)($product['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive';
$roleAccessLabel = $canManageLifecycle ? 'Admin' : ($canEditLeadSections ? 'Assigned Lead' : 'Viewer');
$roleAccessHelp = $canManageLifecycle
    ? 'Lifecycle, lead-owned edits, and admin controls are available.'
    : ($canEditLeadSections
        ? 'Lead-owned sections can be edited. Admin lifecycle controls remain separated.'
        : 'This page is read-only for your role.');

$heroMeta = [
    'Model' => (string)($product['model'] ?? '-'),
    'Producer' => (string)($product['producer'] ?? '-'),
    'Part Lead' => (string)($product['lead'] ?? '-'),
    'Cycle Time' => (string)($product['cycle_time'] ?? '-'),
    'Status' => $statusLabel,
    'Access' => $roleAccessLabel,
];

$overviewCards = [
    ['label' => 'Current State', 'value' => $riskLabel, 'meta' => 'Risk posture right now.', 'badge' => $riskBadge($riskState)],
    ['label' => 'Open Demand', 'value' => $fmt((float)($demandSummary['open_demand_qty'] ?? 0)), 'meta' => (int)($demandSummary['open_orders'] ?? 0) . ' live orders'],
    ['label' => 'Available Stock', 'value' => $fmt((float)($stockSummary['current_balance'] ?? 0)), 'meta' => 'Ledger-backed balance'],
    ['label' => 'Planned Supply', 'value' => $fmt((float)($plannedSupplySummary['planned_qty'] ?? 0)), 'meta' => 'Today ' . $fmt((float)($plannedSupplySummary['today_planned_qty'] ?? 0))],
    ['label' => 'Dispatch Ready', 'value' => $fmt((float)($dispatchSummary['ready_qty'] ?? 0)), 'meta' => 'Dispatched ' . $fmt((float)($dispatchSummary['dispatched_qty'] ?? 0))],
    ['label' => 'Coverage Pressure', 'value' => $fmt((float)($operationalSnapshot['coverage_pressure_qty'] ?? 0)), 'meta' => 'Low coverage orders ' . (int)($operationalSnapshot['low_coverage_orders'] ?? 0)],
];

$roleGroup = $roleToken($currentRole);
$currentLeadName = $assignedUser
    ? (string)($assignedUser['display_name'] ?? $assignedUser['email'] ?? '-')
    : 'Not assigned';
$currentLeadMeta = $assignedUser
    ? trim((string)($assignedUser['responsibility_label'] ?? 'Lead') . (((string)($assignedUser['email'] ?? '') !== '') ? ' · ' . (string)$assignedUser['email'] : ''))
    : 'Assign a lead to unlock lead-owned editing.';
$machineContext = $activeMachine
    ? trim((string)($activeMachine['machine_no'] ?? '') . ' ' . (string)($activeMachine['machine_name'] ?? ''))
    : 'Not assigned';
$machineContextMeta = $activeMachine
    ? trim((string)($activeMachine['section'] ?? '-') . ((trim((string)($activeMachine['machine_group'] ?? '')) !== '') ? ' · ' . (string)$activeMachine['machine_group'] : ''))
    : 'Machine context will appear here when assigned.';
$primaryMaterialCount = count(array_filter($partMaterials, static fn(array $row): bool => (int)($row['is_primary'] ?? 0) === 1));
$activeMoldCount = count(array_filter($molds, static fn(array $row): bool => strtolower(trim((string)($row['status'] ?? 'active'))) === 'active'));

$leadSlots = [
    ['label' => 'Current Lead', 'value' => $currentLeadName, 'meta' => $currentLeadMeta],
    ['label' => 'Production Lead', 'value' => $currentLeadName, 'meta' => 'Current part owner for daily execution'],
    ['label' => 'Assembly Lead', 'value' => !empty($workflowContext['requires_assembly']) ? 'Not assigned' : 'Not required', 'meta' => !empty($workflowContext['requires_assembly']) ? 'Assembly-specific ownership can be assigned when needed.' : 'This part does not currently require assembly.'],
    ['label' => 'QC Lead', 'value' => !empty($workflowContext['awaiting_qc']) ? 'Not assigned' : 'Monitor by QC queue', 'meta' => 'QC ownership follows queue and plan activity until a named lead is assigned.'],
    ['label' => 'Dispatch Lead', 'value' => !empty($workflowContext['ready_for_dispatch']) ? 'Not assigned' : 'Monitor by dispatch queue', 'meta' => 'Dispatch ownership follows release readiness.'],
    ['label' => 'Preparation Lead', 'value' => 'Not assigned', 'meta' => 'Use when prep-specific ownership is introduced.'],
    ['label' => 'Order Lead', 'value' => 'Planning / order flow', 'meta' => 'Demand and due-window ownership stays visible in Demand & Coverage.'],
    ['label' => 'Active Machine', 'value' => $machineContext, 'meta' => $machineContextMeta],
];

$tabDefinitions = [
    ['id' => 'overview', 'label' => 'Overview'],
    ['id' => 'output-labels', 'label' => 'Output / Labels'],
    ['id' => 'demand-coverage', 'label' => 'Demand & Coverage'],
    ['id' => 'execution', 'label' => 'Execution'],
    ['id' => 'tooling-capacity', 'label' => 'Tooling & Capacity'],
    ['id' => 'flow-packaging', 'label' => 'Flow & Packaging'],
    ['id' => 'materials', 'label' => 'Materials'],
    ['id' => 'ownership-leads', 'label' => 'Ownership / Leads'],
];
if ($isAdmin) {
    $tabDefinitions[] = ['id' => 'admin-controls', 'label' => 'Admin Controls'];
}
$allowedTabs = array_map(static fn(array $tab): string => (string)$tab['id'], $tabDefinitions);
$requestedTab = strtolower(trim((string)($_GET['tab'] ?? 'overview')));
$activeTab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : 'overview';
?>

<section class="card">
  <div class="p360-hero">
    <div class="p360-hero-copy">
      <div class="p360-eyebrow">Part Intelligence Detail</div>
      <h2 class="p360-title">Part 360 | <?= e($partName) ?> <span class="p360-muted">(<?= e($partNumber) ?>)</span></h2>
      <div class="p360-sub"> <?= e($tt('products.part_360_description')) ?> </div>
      <div class="p360-meta-line">
        <?php foreach ($heroMeta as $label => $value): ?>
          <span><strong><?= e($label) ?>:</strong> <?= e($value) ?></span>
        <?php endforeach; ?>
        <span><strong>Supply:</strong> <?= e((string)($supplyProfile['supply_mode'] ?? 'in_house')) ?></span>
        <span><strong>Fulfillment:</strong> <?= e((string)($supplyProfile['fulfillment_mode'] ?? 'company_to_destination')) ?></span>
        <span><strong>Requires Assembly:</strong> <?= !empty($workflowContext['requires_assembly']) ? 'Yes' : 'No' ?></span>
        <span><strong>Requires IPM QC:</strong> <?= !empty($supplyProfile['requires_ipm_qc']) ? 'Yes' : 'No' ?></span>
      </div>
      <div class="p360-muted"><?= e($roleAccessHelp) ?></div>
    </div>
    <div class="p360-actions">
      <a class="btn" href="<?= e((string)($links['part_list'] ?? '/products')) ?>">Back to Parts</a>
      <a class="btn" href="<?= e((string)($links['ledger'] ?? '#')) ?>">Stock Ledger</a>
      <a class="btn" href="<?= e((string)($links['orders_search'] ?? '/daily-orders')) ?>">Related Orders</a>
      <a class="btn" href="#p360-tab-output-labels" data-p360-tab-link="output-labels"> <?= e($tt('products.part_360_link')) ?> </a>
      <?php if ($canEditLeadSections): ?>
        <a class="btn" href="<?= e((string)($links['part_edit'] ?? '#')) ?>"> <?= e($tt('products.edit_link')) ?> </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if (trim((string)($flash ?? '')) !== ''): ?>
  <section class="card"><div class="alert success"><?= e((string)$flash) ?></div></section>
<?php endif; ?>
<?php if (trim((string)($error ?? '')) !== ''): ?>
  <section class="card"><div class="alert error"><?= e((string)$error) ?></div></section>
<?php endif; ?>

<section class="card">
  <div class="p360-tab-strip" role="tablist" aria-label="Part 360 Tabs">
    <?php foreach ($tabDefinitions as $tab): ?>
      <button
        type="button"
        class="p360-tab-btn<?= $tab['id'] === $activeTab ? ' is-active' : '' ?>"
        data-p360-tab="<?= e((string)$tab['id']) ?>"
        role="tab"
        aria-selected="<?= $tab['id'] === $activeTab ? 'true' : 'false' ?>"
      >
        <?= e((string)$tab['label']) ?>
      </button>
    <?php endforeach; ?>
  </div>
</section>

<div class="p360-panels" data-p360-default-tab="<?= e($activeTab) ?>">
  <div class="p360-panel<?= $activeTab === 'overview' ? ' is-active' : '' ?>" id="p360-panel-overview" data-p360-panel="overview" role="tabpanel">
    <section class="card">
      <h3 class="p360-section-title">Operational Truth</h3>
      <div class="p360-grid p360-grid-tight">
        <?php foreach ($overviewCards as $item): ?>
          <div class="p360-card p360-highlight-card">
            <div class="p360-muted"><?= e((string)$item['label']) ?></div>
            <div class="p360-kpi">
              <?php if (!empty($item['badge'])): ?>
                <span class="p360-badge <?= e((string)$item['badge']) ?>"><?= e((string)$item['value']) ?></span>
              <?php else: ?>
                <?= e((string)$item['value']) ?>
              <?php endif; ?>
            </div>
            <div class="p360-muted"><?= e((string)($item['meta'] ?? '')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card">
      <div class="p360-grid">
        <div class="p360-card">
          <div class="p360-muted">Supply / Fulfillment</div>
          <div class="p360-kpi p360-kpi-sm"><?= e((string)($supplyProfile['supply_mode'] ?? '-')) ?></div>
          <div class="p360-muted"><?= e((string)($supplyProfile['fulfillment_mode'] ?? '-')) ?></div>
        </div>
        <div class="p360-card">
          <div class="p360-muted">Packaging Summary</div>
          <div class="p360-kpi p360-kpi-sm"><?= e((string)((int)($product['qty_per_case'] ?? 0) > 0 ? (int)$product['qty_per_case'] : '-')) ?> / case</div>
          <div class="p360-muted"><?= e((string)($product['case_type'] ?? '-')) ?><?php if (trim((string)($product['case_spec'] ?? '')) !== ''): ?> · <?= e((string)$product['case_spec']) ?><?php endif; ?></div>
        </div>
        <div class="p360-card">
          <div class="p360-muted">Current Lead</div>
          <div class="p360-kpi p360-kpi-sm"><?= e($currentLeadName) ?></div>
          <div class="p360-muted"><?= e($currentLeadMeta) ?></div>
        </div>
        <div class="p360-card">
          <div class="p360-muted">Assigned Machine</div>
          <div class="p360-kpi p360-kpi-sm"><?= e($machineContext) ?></div>
          <div class="p360-muted"><?= e($machineContextMeta) ?></div>
        </div>
      </div>
      <div class="p360-card p360-stack-sm">
        <div class="p360-muted"> <?= e($tt('products.current_risk_state_message')) ?> </div>
        <ul class="p360-list">
          <?php foreach ((array)($riskSummary['highlights'] ?? []) as $line): ?>
            <li><?= e((string)$line) ?></li>
          <?php endforeach; ?>
          <?php if (empty((array)($riskSummary['highlights'] ?? []))): ?>
            <li>No elevated risk highlights are currently recorded for this part.</li>
          <?php endif; ?>
        </ul>
      </div>
    </section>

    <section class="card">
      <div class="control-toolbar">
        <div class="control-toolbar-copy">
          <h3 class="p360-section-title">Quick Links</h3>
          <div class="p360-muted">Jump from summary into the day-to-day surfaces around this part.</div>
        </div>
      </div>
      <div class="p360-grid">
        <div class="p360-card">
          <div class="p360-text-strong">Orders</div>
          <div class="p360-muted">Demand history, due windows, and related order context.</div>
          <div class="p360-inline-actions"><a class="btn" href="<?= e((string)($links['orders_search'] ?? '/daily-orders')) ?>">Related Orders</a></div>
        </div>
        <div class="p360-card">
          <div class="p360-text-strong">Stock Ledger</div>
          <div class="p360-muted">Balance movement and recent truth from stock ledger events.</div>
          <div class="p360-inline-actions"><a class="btn" href="<?= e((string)($links['ledger'] ?? '#')) ?>"> <?= e($tt('products.open_link')) ?> </a></div>
        </div>
        <div class="p360-card">
          <div class="p360-text-strong">Materials</div>
          <div class="p360-muted">Material master, mapping, and dependency context.</div>
          <div class="p360-inline-actions"><a class="btn" href="<?= e((string)($links['materials'] ?? '/apps/manufacturing/materials')) ?>">Open Materials</a></div>
        </div>
        <div class="p360-card">
          <div class="p360-text-strong"> <?= e($tt('products.output_labels_label')) ?> </div>
          <div class="p360-muted">Keep label output close without making it the default work surface.</div>
          <div class="p360-inline-actions"><a class="btn" href="#p360-tab-output-labels" data-p360-tab-link="output-labels">Open Output / Labels</a></div>
        </div>
      </div>
    </section>
  </div>

  <div class="p360-panel<?= $activeTab === 'output-labels' ? ' is-active' : '' ?>" id="p360-panel-output-labels" data-p360-panel="output-labels" role="tabpanel">
    <section class="card">
      <div class="control-toolbar">
        <div class="control-toolbar-copy">
          <h3 class="p360-section-title"> <?= e($tt('products.output_labels_label')) ?> </h3>
          <div class="p360-muted"> <?= e($tt('products.label_output_is_label')) ?> </div>
        </div>
      </div>
      <div class="p360-grid">
        <div class="p360-card">
          <div class="p360-muted">Part Number</div>
          <div class="p360-kpi p360-kpi-sm"><?= e($partNumber) ?></div>
        </div>
        <div class="p360-card">
          <div class="p360-muted">Default Case Number</div>
          <div class="p360-kpi p360-kpi-sm"><?= e($labelDefaultCaseNumber !== '' ? $labelDefaultCaseNumber : '-') ?></div>
        </div>
        <div class="p360-card">
          <div class="p360-muted">Default Machine No.</div>
          <div class="p360-kpi p360-kpi-sm"><?= e($labelDefaultMachineNo !== '' ? $labelDefaultMachineNo : '-') ?></div>
        </div>
        <div class="p360-card">
          <div class="p360-muted">Qty Per Case</div>
          <div class="p360-kpi p360-kpi-sm"><?= e((string)((int)($product['qty_per_case'] ?? 0) > 0 ? (int)$product['qty_per_case'] : '-')) ?></div>
        </div>
      </div>
    </section>

    <section class="card">
      <h3 class="p360-section-title"> <?= e($tt('products.part_360_link')) ?> </h3>
      <div class="p360-muted">
        <?= $canEditLeadSections
            ? 'Assigned leads and admins can print directly from this form.'
            : 'Label details remain visible here, but printing is limited to the assigned lead and admins.' ?>
      </div>
      <?php if ($canEditLeadSections): ?>
        <form method="get" action="<?= e((string)($links['label_print'] ?? '/qr/product/label')) ?>" class="form-grid" target="_blank">
          <input type="hidden" name="product_id" value="<?= $productId ?>">
          <div class="form-field">
            <label class="field-label" for="p360LabelCopies"> <?= e($tt('products.labels_label')) ?> </label>
            <input class="input" id="p360LabelCopies" type="number" name="copies" min="1" max="48" step="1" value="<?= $labelDefaultCopies ?>">
          </div>
          <div class="form-field">
            <label class="field-label" for="p360LabelDate">Production Date</label>
            <input class="input" id="p360LabelDate" type="date" name="production_date" value="<?= e(date('Y-m-d')) ?>">
          </div>
          <div class="form-field">
            <label class="field-label" for="p360LabelSerial">Serial Number</label>
            <input class="input" id="p360LabelSerial" name="serial_number" placeholder="Optional serial or lot no.">
          </div>
          <div class="form-field">
            <label class="field-label" for="p360LabelMachine">Machine No.</label>
            <input class="input" id="p360LabelMachine" name="machine_no" value="<?= e($labelDefaultMachineNo) ?>" placeholder="Machine no.">
          </div>
          <div class="form-field">
            <label class="field-label" for="p360LabelCaseNumber">Case Number</label>
            <input class="input" id="p360LabelCaseNumber" name="case_number" value="<?= e($labelDefaultCaseNumber) ?>" placeholder="Case no.">
          </div>
          <div class="form-field-full">
            <div class="p360-muted"> <?= e($tt('products.include_on_label_label')) ?> </div>
            <div class="control-row u-style-21547fb5ce">
              <label class="checkbox-label"><input type="checkbox" name="include_date" value="1" checked><span>Date</span></label>
              <label class="checkbox-label"><input type="checkbox" name="include_serial_number" value="1"><span>Serial Number</span></label>
              <label class="checkbox-label"><input type="checkbox" name="include_machine_no" value="1" <?= $labelDefaultMachineNo !== '' ? 'checked' : '' ?>><span>Machine No.</span></label>
              <label class="checkbox-label"><input type="checkbox" name="include_case_number" value="1" <?= $labelDefaultCaseNumber !== '' ? 'checked' : '' ?>><span>Case Number</span></label>
              <label class="checkbox-label"><input type="checkbox" name="include_qty_per_case" value="1" <?= (int)($product['qty_per_case'] ?? 0) > 0 ? 'checked' : '' ?>><span>Qty Per Case</span></label>
              <label class="checkbox-label"><input type="checkbox" name="include_case_spec" value="1" <?= trim((string)($product['case_spec'] ?? '')) !== '' ? 'checked' : '' ?>><span>Case Spec</span></label>
              <label class="checkbox-label"><input type="checkbox" name="include_cases_per_pallet" value="1" <?= (int)($product['cases_per_pallet'] ?? 0) > 0 ? 'checked' : '' ?>><span>Cases Per Pallet</span></label>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn ok" type="submit"> <?= e($tt('products.part_360_link')) ?> </button>
          </div>
        </form>
      <?php else: ?>
        <div class="p360-card">
          <div class="p360-empty">Printing is intentionally limited here. Contact the assigned lead or an admin if label output is needed.</div>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <div class="p360-panel<?= $activeTab === 'demand-coverage' ? ' is-active' : '' ?>" id="p360-panel-demand-coverage" data-p360-panel="demand-coverage" role="tabpanel">
    <section class="card">
      <h3 class="p360-section-title">Demand Buckets & Coverage</h3>
      <div class="p360-grid">
        <div class="p360-card"><div class="p360-muted">Urgent Orders (<=48h)</div><div class="p360-kpi p360-kpi-sm"><?= count((array)($orderContext['urgent_orders'] ?? [])) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Low Coverage Orders</div><div class="p360-kpi p360-kpi-sm"><?= (int)($demandSummary['low_coverage_orders'] ?? 0) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Partial Coverage Orders</div><div class="p360-kpi p360-kpi-sm"><?= count((array)($orderContext['partial_orders'] ?? [])) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Shortage Qty</div><div class="p360-kpi p360-kpi-sm p360-accent-bad"><?= e($fmt((float)($demandSummary['shortage_qty'] ?? 0))) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Due within 48h</div><div class="p360-kpi p360-kpi-sm"><?= (int)($demandSummary['due_within_48h'] ?? 0) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Average Coverage</div><div class="p360-kpi p360-kpi-sm"><?= e($fmt((float)($demandSummary['avg_coverage_pct'] ?? 0))) ?>%</div></div>
      </div>
    </section>

    <section class="card">
      <h3 class="p360-section-title">Plan-Facing Demand Context</h3>
      <div class="p360-grid">
        <div class="p360-card"><div class="p360-muted">Demand Qty</div><div class="p360-kpi"><?= e($fmt((float)($demandSummary['demand_qty'] ?? 0))) ?></div><div class="p360-muted"> <?= e($tt('products.open_action')) ?> <?= e($fmt((float)($demandSummary['open_demand_qty'] ?? 0))) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Coverage Pressure</div><div class="p360-kpi"><?= e($fmt((float)($operationalSnapshot['coverage_pressure_qty'] ?? 0))) ?></div><div class="p360-muted">Pressure against stock + planned supply</div></div>
        <div class="p360-card"><div class="p360-muted">Current Stock / Planned Supply</div><div class="p360-kpi"><?= e($fmt((float)($stockSummary['current_balance'] ?? 0))) ?> / <?= e($fmt((float)($plannedSupplySummary['planned_qty'] ?? 0))) ?></div><div class="p360-muted">Upcoming <?= e($fmt((float)($plannedSupplySummary['upcoming_planned_qty'] ?? 0))) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Next Due Window</div><div class="p360-kpi p360-kpi-sm"><?= e((string)($demandSummary['next_due_at'] ?? '-')) ?></div><div class="p360-muted">Coverage status stays visible per order below.</div></div>
      </div>
    </section>

    <section class="card">
      <div class="control-toolbar">
        <div class="control-toolbar-copy">
          <h3 class="p360-section-title">Order History Windows</h3>
          <div class="p360-muted">Last 3 years of order history plus the next 6 months of forward demand.</div>
        </div>
        <div class="control-toolbar-actions">
          <a class="btn" href="<?= e((string)($links['orders_search'] ?? '/daily-orders')) ?>">Related Orders</a>
        </div>
      </div>
      <div class="p360-grid">
        <?php foreach ($orderRangeSummary as $range): ?>
          <div class="p360-card">
            <div class="p360-muted"><?= e((string)($range['label'] ?? '-')) ?></div>
            <div class="p360-kpi p360-kpi-sm"><?= (int)($range['orders'] ?? 0) ?></div>
            <div class="p360-muted">Qty <?= e($fmt((float)($range['qty'] ?? 0))) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (empty($orderTimeline)): ?>
        <div class="p360-empty">No daily-order history or future pre-orders were found for this part in the current history window.</div>
      <?php else: ?>
        <div class="p360-table-wrap">
          <table class="p360-table">
            <thead><tr><th>Date</th><th>Source</th><th>Order</th><th>Customer / Context</th><th>Demand</th><th> <?= e($tt('products.open_demand_column')) ?> </th><th>Coverage / Priority</th><th>Shortage</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($orderTimeline as $row): ?>
              <?php $sourceType = (string)($row['source_type'] ?? 'daily_order'); ?>
              <tr>
                <td><?= e((string)($row['due_date'] ?? $row['required_date'] ?? $row['order_date'] ?? '-')) ?></td>
                <td><?= $sourceType === 'pre_order' ? 'Pre-Order' : 'Daily Order' ?></td>
                <td>#<?= (int)($row['id'] ?? 0) ?></td>
                <td>
                  <?= e((string)($row['customer_name'] ?? '-')) ?>
                  <?php if ($sourceType === 'pre_order' && !empty($row['forecast_type'])): ?>
                    <div class="p360-muted"><?= e((string)($row['forecast_type'] ?? 'Forecast')) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= e($fmt((float)($row['qty'] ?? 0))) ?></td>
                <td><?= e($fmt((float)($row['open_demand_qty'] ?? 0))) ?></td>
                <td>
                  <?php if ($sourceType === 'pre_order'): ?>
                    <?= e((string)($row['planning_priority'] ?? '-')) ?>
                  <?php else: ?>
                    <?= e($fmt((float)($row['coverage_pct'] ?? 0))) ?>% <span class="p360-muted"><?= e((string)($row['coverage_status'] ?? '-')) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= e($fmt((float)($row['shortage_qty'] ?? 0))) ?></td>
                <td><?= e((string)($row['status'] ?? '-')) ?></td>
                <td>
                  <?php if ($sourceType === 'pre_order'): ?>
                    <a class="btn" href="/pre-orders/edit?id=<?= (int)($row['id'] ?? 0) ?>"> <?= e($tt('common.open_action')) ?> </a>
                  <?php else: ?>
                    <a class="btn" href="/daily-orders/360?id=<?= (int)($row['id'] ?? 0) ?>">Order 360</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <div class="p360-panel<?= $activeTab === 'execution' ? ' is-active' : '' ?>" id="p360-panel-execution" data-p360-panel="execution" role="tabpanel">
    <section class="card">
      <h3 class="p360-section-title"><?= e(t('part_360.section.workflow_context')) ?></h3>
      <div class="p360-grid">
        <div class="p360-card"><div class="p360-muted"><?= e(t('part_360.kpi.operational_stage')) ?></div><div class="p360-kpi p360-kpi-sm"><?= e((string)($workflowContext['stage_label'] ?? '-')) ?></div></div>
        <div class="p360-card"><div class="p360-muted"><?= e(t('part_360.kpi.execution_path')) ?></div><div class="p360-kpi p360-kpi-sm"><?= e((string)($workflowContext['execution_path'] ?? $executionRoute['description'] ?? '-')) ?></div></div>
        <div class="p360-card"><div class="p360-muted"><?= e(t('part_360.kpi.planning_needed')) ?></div><div class="p360-kpi p360-kpi-sm"><?= !empty($workflowContext['planning_needed']) ? e(t('common.yes')) : e(t('common.no')) ?></div></div>
        <div class="p360-card"><div class="p360-muted"><?= e(t('part_360.kpi.awaiting_qc')) ?></div><div class="p360-kpi p360-kpi-sm"><?= !empty($workflowContext['awaiting_qc']) ? e(t('common.yes')) : e(t('common.no')) ?></div></div>
        <div class="p360-card"><div class="p360-muted"><?= e(t('part_360.kpi.ready_for_dispatch')) ?></div><div class="p360-kpi p360-kpi-sm"><?= !empty($workflowContext['ready_for_dispatch']) ? e(t('common.yes')) : e(t('common.no')) ?></div></div>
        <div class="p360-card"><div class="p360-muted"><?= e(t('part_360.kpi.bottlenecks_handoffs')) ?></div><div class="p360-kpi p360-kpi-sm"><?= (int)($handoffCounts['blocked'] ?? 0) ?> / <?= (int)($handoffCounts['escalated'] ?? 0) ?></div><div class="p360-muted"><?= e(t('part_360.kpi.blocked_escalated')) ?></div></div>
      </div>
    </section>

    <?php
    $renderList(t('part_360.list.production_plans'), t('part_360.empty.production_plans'), $recentProductionPlans, static function (array $rows) use ($fmt): void {
    ?>
    <table class="p360-table"><thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.machine')) ?></th><th><?= e(t('common.planned_qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('module.production_plans.approval')) ?></th><th><?= e(t('module.production_plans.lock')) ?></th><th><?= e(t('common.action')) ?></th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
    <tr><td>#<?= (int)($row['id'] ?? 0) ?></td><td><?= e((string)($row['plan_date'] ?? '-')) ?></td><td><?= e((string)($row['machine_no'] ?? '-')) ?> <?= e((string)($row['machine_name'] ?? '')) ?></td><td><?= e($fmt((float)($row['planned_qty'] ?? 0))) ?></td><td><?= e((string)($row['status'] ?? '-')) ?></td><td><?= e((string)($row['approval_status'] ?? t('common.draft'))) ?></td><td><?= trim((string)($row['locked_at'] ?? '')) !== '' ? e(t('common.locked')) : e(t('common.open')) ?></td><td><a class="btn" href="/production-plans/edit?id=<?= (int)($row['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php
    });

    $renderList(t('part_360.list.production_entries'), t('part_360.empty.production_entries'), $recentProductionEntries, static function (array $rows) use ($fmt): void {
    ?>
    <table class="p360-table"><thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.machine')) ?></th><th>Produced</th><th><?= e(t('common.good_qty')) ?></th><th>Rejected</th><th><?= e(t('common.action')) ?></th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
    <tr><td>#<?= (int)($row['id'] ?? 0) ?></td><td><?= e((string)($row['production_date'] ?? '-')) ?></td><td><?= e((string)($row['machine_no'] ?? '-')) ?> <?= e((string)($row['machine_name'] ?? '')) ?></td><td><?= e($fmt((float)($row['produced_qty'] ?? 0))) ?></td><td><?= e($fmt((float)($row['good_qty'] ?? 0))) ?></td><td><?= e($fmt((float)($row['rejected_qty'] ?? 0))) ?></td><td><a class="btn" href="/production-entries/edit?id=<?= (int)($row['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php
    });

    $renderList(t('part_360.list.qc_plans'), t('part_360.empty.qc_plans'), $recentQcPlans, static function (array $rows) use ($fmt): void {
    ?>
    <table class="p360-table"><thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('order_360.label.required')) ?></th><th><?= e(t('common.planned_qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.action')) ?></th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
    <tr><td>#<?= (int)($row['id'] ?? 0) ?></td><td><?= e((string)($row['plan_date'] ?? '-')) ?></td><td><?= e((string)($row['required_date'] ?? '-')) ?></td><td><?= e($fmt((float)($row['planned_qty'] ?? 0))) ?></td><td><?= e((string)($row['status'] ?? '-')) ?></td><td><a class="btn" href="/qc-plans/edit?id=<?= (int)($row['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a><?php if ((int)($row['daily_order_id'] ?? 0) > 0): ?> <a class="btn" href="/daily-orders/360?id=<?= (int)($row['daily_order_id'] ?? 0) ?>"><?= e(t('module.daily_orders.order_360')) ?></a><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php
    });

    $renderList(t('part_360.list.qc_entries'), t('part_360.empty.qc_entries'), $recentQcEntries, static function (array $rows) use ($fmt, $product): void {
    ?>
    <table class="p360-table"><thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('common.checked_qty')) ?></th><th><?= e(t('common.pass_qty')) ?></th><th><?= e(t('common.fail_qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('module.production_plans.approval')) ?></th><th><?= e(t('module.production_plans.lock')) ?></th><th><?= e(t('common.action')) ?></th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
    <tr><td>#<?= (int)($row['id'] ?? 0) ?></td><td><?= e((string)($row['qc_type'] ?? '-')) ?></td><td><?= e($fmt((float)($row['checked_qty'] ?? 0))) ?></td><td><?= e($fmt((float)($row['pass_qty'] ?? 0))) ?></td><td><?= e($fmt((float)($row['fail_qty'] ?? 0))) ?></td><td><?= e((string)($row['status'] ?? '-')) ?></td><td><?= e((string)($row['approval_status'] ?? t('common.draft'))) ?></td><td><?= trim((string)($row['locked_at'] ?? '')) !== '' ? e(t('common.locked')) : e(t('common.open')) ?></td><td><a class="btn" href="/qc-entries/edit?id=<?= (int)($row['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a><a class="btn" href="/ledger?product_id=<?= (int)($product['id'] ?? 0) ?>&source_module=QCEntries&source_id=<?= (int)($row['id'] ?? 0) ?>">Ledger</a><?php if ((int)($row['daily_order_id'] ?? 0) > 0): ?> <a class="btn" href="/daily-orders/360?id=<?= (int)($row['daily_order_id'] ?? 0) ?>"><?= e(t('module.daily_orders.order_360')) ?></a><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php
    });

    $renderList(t('part_360.list.dispatch_entries'), t('part_360.empty.dispatch_entries'), $recentDispatchEntries, static function (array $rows) use ($fmt, $product): void {
    ?>
    <table class="p360-table"><thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('module.production_plans.approval')) ?></th><th><?= e(t('module.production_plans.lock')) ?></th><th><?= e(t('common.destination')) ?></th><th><?= e(t('common.action')) ?></th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
    <tr><td>#<?= (int)($row['id'] ?? 0) ?></td><td><?= e((string)($row['dispatch_date'] ?? '-')) ?></td><td><?= e($fmt((float)($row['dispatchable_qty'] ?? 0))) ?></td><td><?= e((string)($row['dispatch_status'] ?? '-')) ?></td><td><?= e((string)($row['approval_status'] ?? t('common.draft'))) ?></td><td><?= trim((string)($row['locked_at'] ?? '')) !== '' || strtolower(trim((string)($row['dispatch_status'] ?? ''))) === 'dispatched' ? e(t('common.locked')) : e(t('common.open')) ?></td><td><?= e((string)($row['destination'] ?? '-')) ?></td><td><a class="btn" href="/dispatch-entries/edit?id=<?= (int)($row['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a><a class="btn" href="/ledger?product_id=<?= (int)($product['id'] ?? 0) ?>&source_module=DispatchEntries&source_id=<?= (int)($row['id'] ?? 0) ?>">Ledger</a><?php if ((int)($row['daily_order_id'] ?? 0) > 0): ?> <a class="btn" href="/daily-orders/360?id=<?= (int)($row['daily_order_id'] ?? 0) ?>"><?= e(t('module.daily_orders.order_360')) ?></a><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php
    });
    ?>

    <section class="card">
      <h3 class="p360-section-title">Current Bottlenecks / Handoffs</h3>
      <div class="p360-muted"> <?= e($tt('products.active_message')) ?> <?= (int)($handoffCounts['active'] ?? 0) ?> | blocked <?= (int)($handoffCounts['blocked'] ?? 0) ?> | escalated <?= (int)($handoffCounts['escalated'] ?? 0) ?> | breach <?= (int)($handoffCounts['breach'] ?? 0) ?></div>
      <?php if (empty($handoffItems)): ?>
        <div class="p360-empty"> <?= e($tt('products.part_360_active_message')) ?> </div>
      <?php else: ?>
        <ul class="p360-list">
          <?php foreach ($handoffItems as $row): ?>
            <li>
              <?= e((string)($row['stage_label'] ?? '-')) ?> on <?= e((string)($row['entity_type'] ?? '-')) ?> #<?= (int)($row['entity_id'] ?? 0) ?>,
              owner <?= e((string)($row['owner_role'] ?? '-')) ?>,
              age <?= e($fmt((float)($row['age_hours'] ?? 0))) ?>h
              <a href="<?= e($handoffUrl((string)($row['entity_type'] ?? ''), (int)($row['entity_id'] ?? 0))) ?>">open</a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if (!empty($timeline)): ?>
      <section class="card">
        <h3 class="p360-section-title">Workflow Timeline</h3>
        <div class="p360-table-wrap">
          <table class="p360-table p360-table-medium">
            <thead><tr><th>When</th><th>Type</th><th>Event</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($timeline as $event): ?>
              <tr>
                <td><?= e((string)($event['when'] ?? '')) ?></td>
                <td><?= e((string)($event['type'] ?? '')) ?></td>
                <td><?= e((string)($event['title'] ?? '')) ?></td>
                <td><?= e((string)($event['status'] ?? '')) ?></td>
                <td><a class="btn" href="<?= e((string)($event['url'] ?? '#')) ?>"> <?= e($tt('common.open_action')) ?> </a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  </div>

  <div class="p360-panel<?= $activeTab === 'tooling-capacity' ? ' is-active' : '' ?>" id="p360-panel-tooling-capacity" data-p360-panel="tooling-capacity" role="tabpanel">
    <section class="card">
      <div class="control-toolbar">
        <div class="control-toolbar-copy">
          <h3 class="p360-section-title">Tooling & Capacity</h3>
          <div class="p360-muted">Mold detail, cycle framing, machine fit, and machine usage stay together here.</div>
        </div>
        <div class="control-toolbar-actions">
          <a class="btn" href="<?= e((string)($links['materials'] ?? '/apps/manufacturing/materials')) ?>">Open Materials</a>
        </div>
      </div>
      <div class="p360-grid">
        <div class="p360-card"><div class="p360-muted">Molds</div><div class="p360-kpi p360-kpi-sm"><?= count($molds) ?></div><div class="p360-muted"> <?= e($tt('products.active_molds_message')) ?> <?= $activeMoldCount ?></div></div>
        <div class="p360-card"><div class="p360-muted">Material Maps</div><div class="p360-kpi p360-kpi-sm"><?= count($partMaterials) ?></div><div class="p360-muted">Primary mappings <?= $primaryMaterialCount ?></div></div>
        <div class="p360-card"><div class="p360-muted">Compatible Machines</div><div class="p360-kpi p360-kpi-sm"><?= count($compatibleMachines) ?></div><div class="p360-muted">Fit driven by tooling + material context</div></div>
        <div class="p360-card"><div class="p360-muted">Recent Machine Usage</div><div class="p360-kpi p360-kpi-sm"><?= count($machineUsage) ?></div><div class="p360-muted">Recent machine runs found in production activity</div></div>
      </div>
      <?php if (!$canEditLeadSections): ?>
        <div class="p360-card"><div class="p360-empty">Tooling and machine details remain visible to all users. Editing is limited to the assigned lead or admins.</div></div>
      <?php endif; ?>
    </section>

    <section class="card">
      <h3 class="p360-section-title">Mold Specifications</h3>
      <?php if ($molds === []): ?>
        <div class="p360-empty">No mold records exist for this part.</div>
      <?php else: ?>
        <div class="p360-table-wrap">
          <table class="p360-table p360-table-narrow">
            <thead><tr><th>Mold</th><th>Cavity</th><th> <?= e($tt('products.footprint_column')) ?> </th><th>Thickness</th><th>Clamp / Shot</th><th>Preferred Machine</th><th>Cycle</th><th>Status</th><?php if ($canEditLeadSections): ?><th>Action</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($molds as $mold): ?>
              <tr>
                <td><strong><?= e((string)($mold['mold_code'] ?? '')) ?></strong><br><span class="p360-muted"><?= e((string)($mold['mold_name'] ?? '')) ?></span></td>
                <td><?= (int)($mold['cavity_count'] ?? 1) ?><?php if (!empty($mold['is_family_mold'])): ?> <span class="p360-muted">family</span><?php endif; ?></td>
                <td><?= e((string)($mold['mold_width_mm'] ?? '-')) ?> x <?= e((string)($mold['mold_height_mm'] ?? '-')) ?> mm</td>
                <td><?= e((string)($mold['mold_thickness_mm'] ?? '-')) ?> mm</td>
                <td><?= e((string)($mold['min_clamp_ton_required'] ?? '-')) ?> T / <?= e((string)($mold['min_shot_g_required'] ?? '-')) ?> g</td>
                <td>
                  <?= e((string)($mold['preferred_machine_group'] ?? '-')) ?>
                  <?php if (trim((string)($mold['preferred_machine_type'] ?? '')) !== ''): ?>
                    <br><span class="p360-muted"><?= e((string)$mold['preferred_machine_type']) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= e((string)($mold['cycle_time_sec'] ?? '-')) ?> sec</td>
                <td><?= e((string)($mold['status'] ?? '-')) ?></td>
                <?php if ($canEditLeadSections): ?>
                  <td>
                    <form method="post" action="/products/molds/delete" onsubmit="return confirm('Remove this mold record?');">
                      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                      <input type="hidden" name="product_id" value="<?= $productId ?>">
                      <input type="hidden" name="mold_id" value="<?= (int)($mold['id'] ?? 0) ?>">
                      <button class="btn danger" type="submit"><?= e(t('common.delete')) ?></button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($canEditLeadSections): ?>
        <form method="post" action="/products/molds/add" class="form-grid u-style-1b0f4999d2">
          <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
          <input type="hidden" name="product_id" value="<?= $productId ?>">
          <div class="form-field"><label class="field-label">Mold Code</label><input class="input" name="mold_code" required></div>
          <div class="form-field-wide"><label class="field-label">Mold Name</label><input class="input" name="mold_name" required></div>
          <div class="form-field"><label class="field-label">Cavity Count</label><input class="input" type="number" min="1" step="1" name="cavity_count" value="1"></div>
          <div class="form-field"><label class="field-label">Runner Type</label><input class="input" name="runner_type" placeholder="hot / cold"></div>
          <div class="form-field"><label class="field-label">Mold Width (mm)</label><input class="input" type="number" step="0.01" min="0" name="mold_width_mm"></div>
          <div class="form-field"><label class="field-label">Mold Height (mm)</label><input class="input" type="number" step="0.01" min="0" name="mold_height_mm"></div>
          <div class="form-field"><label class="field-label">Mold Thickness (mm)</label><input class="input" type="number" step="0.01" min="0" name="mold_thickness_mm"></div>
          <div class="form-field"><label class="field-label">Min Clamp Required (T)</label><input class="input" type="number" step="0.01" min="0" name="min_clamp_ton_required"></div>
          <div class="form-field"><label class="field-label">Min Shot Required (g)</label><input class="input" type="number" step="0.01" min="0" name="min_shot_g_required"></div>
          <div class="form-field"><label class="field-label">Cycle Time (sec)</label><input class="input" type="number" step="0.01" min="0" name="cycle_time_sec"></div>
          <div class="form-field"><label class="field-label">Preferred Machine Group</label><input class="input" name="preferred_machine_group" placeholder="HD / CX / SV / MD"></div>
          <div class="form-field"><label class="field-label">Preferred Machine Type</label><input class="input" name="preferred_machine_type" placeholder="Heavy Duty / Complex Moulding / Insert Moulding / Medium"></div>
          <div class="form-field"><label class="checkbox-label"><input type="hidden" name="is_family_mold" value="0"><input type="checkbox" name="is_family_mold" value="1"><span class="field-label u-style-8000819c9b">Family Mold</span></label></div>
          <div class="form-field"><label class="field-label">Tool Weight (kg)</label><input class="input" type="number" step="0.01" min="0" name="tool_weight_kg"></div>
          <div class="form-field"><label class="field-label">Status</label><input class="input" name="status" value="active"></div>
          <div class="form-field-full"><label class="field-label">Notes</label><textarea name="notes"></textarea></div>
          <div class="form-actions"><button class="btn ok" type="submit"> <?= e($tt('products.save_action')) ?> </button></div>
        </form>
      <?php endif; ?>
    </section>

    <section class="card">
      <h3 class="p360-section-title">Mold Fit Against Machine Specs</h3>
      <?php if ($moldFitSummary === []): ?>
        <div class="p360-empty">Add a mold record first to evaluate machine fit.</div>
      <?php else: ?>
        <?php foreach ($moldFitSummary as $fit): ?>
          <div class="p360-card u-style-da12f2858b">
            <div class="p360-eyebrow"><?= e((string)($fit['mold_code'] ?? '')) ?></div>
            <div class="p360-kpi p360-kpi-sm"><?= e((string)($fit['mold_name'] ?? '')) ?></div>
            <div class="p360-muted"><?= (int)($fit['fit_count'] ?? 0) ?> fit · <?= (int)($fit['review_count'] ?? 0) ?> review · <?= (int)($fit['blocked_count'] ?? 0) ?> blocked</div>
            <?php if (!empty($fit['top_fits'])): ?>
              <div class="p360-muted u-style-8a77e5a311">Best fits:</div>
              <ul class="p360-list">
                <?php foreach ((array)$fit['top_fits'] as $machine): ?>
                  <li><a href="/machines/detail?id=<?= (int)($machine['machine_id'] ?? 0) ?>"><?= e((string)($machine['machine_no'] ?? '')) ?> <?= e((string)($machine['machine_name'] ?? '')) ?></a> · <?= e((string)($machine['preference'] ?? '')) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if (!empty($fit['top_reviews'])): ?>
              <div class="p360-muted u-style-8a77e5a311">Need review:</div>
              <ul class="p360-list">
                <?php foreach ((array)$fit['top_reviews'] as $machine): ?>
                  <li><?= e((string)($machine['machine_no'] ?? '')) ?> <?= e((string)($machine['machine_name'] ?? '')) ?> · <?= e(implode(' ', (array)($machine['reasons'] ?? []))) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="p360-grid p360-stack-md">
        <div class="p360-card">
          <h3 class="p360-section-title">Machine Assignment</h3>
          <div class="p360-muted">One active machine can be assigned here. Compatible machines are auto-populated from mold fit and resin preference.</div>
          <?php if ($canEditLeadSections): ?>
            <form method="post" action="/products/active-machine" class="form-grid u-style-1b0f4999d2">
              <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
              <input type="hidden" name="product_id" value="<?= $productId ?>">
              <div class="form-field-wide">
                <label class="field-label"> <?= e($tt('products.active_machine_message')) ?> </label>
                <select name="active_machine_id">
                  <option value="">Not assigned</option>
                  <?php foreach ($compatibleMachines as $machine): ?>
                    <option value="<?= (int)($machine['machine_id'] ?? 0) ?>" <?= (int)($activeMachine['id'] ?? 0) === (int)($machine['machine_id'] ?? 0) ? 'selected' : '' ?>>
                      <?= e((string)($machine['machine_no'] ?? '-')) ?> <?= e((string)($machine['machine_name'] ?? '')) ?><?php if (trim((string)($machine['machine_type'] ?? '')) !== ''): ?> · <?= e((string)($machine['machine_type'] ?? '')) ?><?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-actions"><button class="btn ok" type="submit">Save Active Machine</button></div>
            </form>
          <?php else: ?>
            <div class="p360-card u-style-56f4356299"><div class="p360-empty">Machine assignment edits are limited to the assigned lead or admins.</div></div>
          <?php endif; ?>
          <div class="p360-card u-style-56f4356299">
            <div class="p360-muted"> <?= e($tt('products.current_active_machine_message')) ?> </div>
            <?php if ($activeMachine): ?>
              <div class="p360-kpi p360-kpi-sm"><?= e((string)($activeMachine['machine_no'] ?? '')) ?> <?= e((string)($activeMachine['machine_name'] ?? '')) ?></div>
              <div class="p360-muted"><?= e((string)($activeMachine['section'] ?? '-')) ?><?php if (trim((string)($activeMachine['machine_group'] ?? '')) !== ''): ?> · <?= e((string)($activeMachine['machine_group'] ?? '')) ?><?php endif; ?><?php if (trim((string)($activeMachine['machine_type'] ?? '')) !== ''): ?> · <?= e((string)($activeMachine['machine_type'] ?? '')) ?><?php endif; ?></div>
            <?php else: ?>
              <div class="p360-empty">No active machine assigned.</div>
            <?php endif; ?>
          </div>
        </div>

        <div class="p360-card">
          <h3 class="p360-section-title">Compatible Machines</h3>
          <?php if (empty($compatibleMachines)): ?>
            <div class="p360-empty"> <?= e($tt('products.part_360_empty_state')) ?> </div>
          <?php else: ?>
            <div class="p360-table-wrap">
              <table class="p360-table p360-table-narrow">
                <thead><tr><th>Machine</th><th>Group / Type</th><th>Fit Molds</th><th>Preference</th></tr></thead>
                <tbody>
                <?php foreach ($compatibleMachines as $row): ?>
                  <tr>
                    <td><?= e((string)($row['machine_no'] ?? '-')) ?> <?= e((string)($row['machine_name'] ?? '')) ?></td>
                    <td><?= e((string)($row['machine_group'] ?? '-')) ?> / <?= e((string)($row['machine_type'] ?? '-')) ?></td>
                    <td><?= (int)($row['fit_molds'] ?? 0) ?></td>
                    <td><?= e((string)($row['preference_summary'] ?? 'General fit')) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <div class="p360-card">
          <h3 class="p360-section-title">Machine Usage</h3>
          <?php if (empty($machineUsage)): ?>
            <div class="p360-empty">No recent production usage found.</div>
          <?php else: ?>
            <div class="p360-table-wrap">
              <table class="p360-table p360-table-narrow">
                <thead><tr><th>Machine</th><th>Planned Runs</th><th>Planned Qty</th><th>Produced Runs</th><th>Good Qty</th></tr></thead>
                <tbody>
                <?php foreach ($machineUsage as $row): ?>
                  <tr>
                    <td><?= e((string)($row['machine_no'] ?? '-')) ?> <?= e((string)($row['machine_name'] ?? '')) ?></td>
                    <td><?= (int)($row['plan_count'] ?? 0) ?></td>
                    <td><?= e($fmt((float)($row['planned_qty'] ?? 0))) ?></td>
                    <td><?= (int)($row['entry_count'] ?? 0) ?></td>
                    <td><?= e($fmt((float)($row['good_qty'] ?? 0))) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="p360-panel<?= $activeTab === 'flow-packaging' ? ' is-active' : '' ?>" id="p360-panel-flow-packaging" data-p360-panel="flow-packaging" role="tabpanel">
    <section class="card">
      <h3 class="p360-section-title">Supply & Flow</h3>
      <div class="p360-grid">
        <div class="p360-card"><div class="p360-muted">Supply Mode</div><div class="p360-kpi p360-kpi-sm"><?= e((string)($supplyProfile['supply_mode'] ?? '-')) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Fulfillment Mode</div><div class="p360-kpi p360-kpi-sm"><?= e((string)($supplyProfile['fulfillment_mode'] ?? '-')) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Stocked at IPM</div><div class="p360-kpi p360-kpi-sm"><?= !empty($supplyProfile['stocked_at_ipm']) ? 'Yes' : 'No' ?></div></div>
        <div class="p360-card"><div class="p360-muted">Default Supplier</div><div class="p360-kpi p360-kpi-sm"><?= e((string)($supplyProfile['default_supplier'] ?? '-')) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Lead Days</div><div class="p360-kpi p360-kpi-sm"><?= e($fmt((float)($supplyProfile['default_procurement_lead_days'] ?? 0), 0)) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Dispatch Mode</div><div class="p360-kpi p360-kpi-sm"><?= e((string)($product['dispatch_mode'] ?? 'Auto')) ?></div></div>
      </div>
      <div class="p360-card p360-stack-sm">
        <div class="p360-muted">Flow Narrative</div>
        <div class="ui-block"><?= e((string)($supplyProfile['flow_narrative'] ?? '-')) ?></div>
        <?php if (trim((string)($supplyProfile['default_supply_note'] ?? '')) !== ''): ?>
          <div class="p360-muted">Supply Note: <?= e((string)$supplyProfile['default_supply_note']) ?></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="card">
      <h3 class="p360-section-title">Packaging Profile</h3>
      <div class="p360-grid">
        <div class="p360-card"><div class="p360-muted">Qty Per Case</div><div class="p360-kpi p360-kpi-sm"><?= e((string)((int)($product['qty_per_case'] ?? 0) > 0 ? (int)$product['qty_per_case'] : '-')) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Case Type</div><div class="p360-kpi p360-kpi-sm"><?= e((string)($product['case_type'] ?? '-')) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Case Spec</div><div class="p360-kpi p360-kpi-sm"><?= e((string)($product['case_spec'] ?? '-')) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Default Case Number</div><div class="p360-kpi p360-kpi-sm"><?= e((string)($product['default_case_number'] ?? '-')) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Cases Per Pallet</div><div class="p360-kpi p360-kpi-sm"><?= e((string)((int)($product['cases_per_pallet'] ?? 0) > 0 ? (int)$product['cases_per_pallet'] : '-')) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Fulfillment Readiness</div><div class="p360-kpi p360-kpi-sm"><?= e($fmt((float)($dispatchSummary['ready_qty'] ?? 0))) ?></div><div class="p360-muted">Dispatch-ready qty</div></div>
      </div>
      <?php if (!$canEditLeadSections): ?>
        <div class="p360-card"><div class="p360-empty">Flow and packaging are visible to everyone. Edits remain limited to the assigned lead or admins via part edit.</div></div>
      <?php elseif ((string)($links['part_edit'] ?? '') !== ''): ?>
        <div class="p360-inline-actions"><a class="btn" href="<?= e((string)$links['part_edit']) ?>">Edit Flow / Packaging</a></div>
      <?php endif; ?>
    </section>
  </div>

  <div class="p360-panel<?= $activeTab === 'materials' ? ' is-active' : '' ?>" id="p360-panel-materials" data-p360-panel="materials" role="tabpanel">
    <section class="card">
      <h3 class="p360-section-title">Material Dependency Context</h3>
      <div class="p360-grid">
        <div class="p360-card"><div class="p360-muted">Material Maps</div><div class="p360-kpi p360-kpi-sm"><?= count($partMaterials) ?></div></div>
        <div class="p360-card"><div class="p360-muted">Primary Maps</div><div class="p360-kpi p360-kpi-sm"><?= $primaryMaterialCount ?></div></div>
        <div class="p360-card"><div class="p360-muted">Materials App</div><div class="p360-kpi p360-kpi-sm">Connected</div><div class="p360-muted">Mapping resolves to active material master records.</div></div>
      </div>
      <?php if (!$canEditLeadSections): ?>
        <div class="p360-card"><div class="p360-empty">Material mapping stays visible for all users. Editing is limited to the assigned lead or admins.</div></div>
      <?php endif; ?>
    </section>

    <section class="card">
      <h3 class="p360-section-title">Material Mapping</h3>
      <?php if ($partMaterials === []): ?>
        <div class="p360-empty">No material mapping exists for this part.</div>
      <?php else: ?>
        <div class="p360-table-wrap">
          <table class="p360-table">
            <thead><tr><th>Material</th><th>Type</th><th>Vendor</th><th>Usage / Part</th><th>Yield / kg</th><th>Scrap</th><th>Window</th><?php if ($canEditLeadSections): ?><th>Action</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($partMaterials as $map): ?>
              <tr>
                <td><strong><?= e((string)($map['material_code_resolved'] ?? '-')) ?></strong><br><span class="p360-muted"><?= e((string)($map['material_name_resolved'] ?? '-')) ?></span></td>
                <td><?= e((string)($map['material_type_resolved'] ?? '-')) ?></td>
                <td><?= e((string)($map['vendor_name_resolved'] ?? '-')) ?><?php if (trim((string)($map['vendor_code_resolved'] ?? '')) !== ''): ?><br><span class="p360-muted"><?= e((string)$map['vendor_code_resolved']) ?></span><?php endif; ?></td>
                <td><?= e($fmt((float)($map['usage_qty'] ?? 0), 4)) ?> <?= e((string)($map['usage_unit'] ?? 'kg')) ?></td>
                <td><?= e($fmt((float)($map['yield_parts_per_kg'] ?? 0), 2)) ?></td>
                <td><?= e($fmt((float)($map['scrap_pct'] ?? 0), 2)) ?>%</td>
                <td><?= e((string)($map['effective_from'] ?? '-')) ?> → <?= e((string)($map['effective_to'] ?? '-')) ?></td>
                <?php if ($canEditLeadSections): ?>
                  <td>
                    <form method="post" action="/products/materials/delete" onsubmit="return confirm('Remove this material mapping?');">
                      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                      <input type="hidden" name="product_id" value="<?= $productId ?>">
                      <input type="hidden" name="map_id" value="<?= (int)($map['id'] ?? 0) ?>">
                      <button class="btn danger" type="submit"><?= e(t('common.delete')) ?></button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($canEditLeadSections): ?>
        <form method="post" action="/products/materials/add" class="form-grid u-style-1b0f4999d2">
          <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
          <input type="hidden" name="product_id" value="<?= $productId ?>">
          <div class="form-field-wide">
            <label class="field-label">Material</label>
            <select name="material_id" required>
              <option value="">Select material</option>
              <?php foreach ($materialOptions as $option): ?>
                <option value="<?= (int)($option['id'] ?? 0) ?>"><?= e((string)($option['material_code'] ?? '')) ?> - <?= e((string)($option['material_name'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field"><label class="field-label">Usage per Part</label><input class="input" type="number" step="0.0001" min="0" name="usage_qty" required></div>
          <div class="form-field"><label class="field-label">Usage Unit</label><input class="input" name="usage_unit" value="kg"></div>
          <div class="form-field"><label class="field-label">Yield Parts / kg</label><input class="input" type="number" step="0.0001" min="0" name="yield_parts_per_kg"></div>
          <div class="form-field"><label class="field-label">Scrap %</label><input class="input" type="number" step="0.01" min="0" name="scrap_pct" value="0"></div>
          <div class="form-field"><label class="field-label">Sequence</label><input class="input" type="number" min="1" step="1" name="sequence_no" value="1"></div>
          <div class="form-field"><label class="field-label">Effective From</label><input class="input" type="date" name="effective_from"></div>
          <div class="form-field"><label class="field-label">Effective To</label><input class="input" type="date" name="effective_to"></div>
          <div class="form-field"><label class="checkbox-label"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked><span class="field-label u-style-8000819c9b"> <?= e($tt('products.active_message')) ?> </span></label></div>
          <div class="form-field-full"><label class="field-label">Notes</label><textarea name="notes"></textarea></div>
          <div class="form-actions"><button class="btn ok" type="submit">Save Material Map</button></div>
        </form>
      <?php endif; ?>
    </section>
  </div>

  <div class="p360-panel<?= $activeTab === 'ownership-leads' ? ' is-active' : '' ?>" id="p360-panel-ownership-leads" data-p360-panel="ownership-leads" role="tabpanel">
    <section class="card">
      <h3 class="p360-section-title">Ownership / Leads</h3>
      <div class="p360-grid">
        <?php foreach ($leadSlots as $slot): ?>
          <div class="p360-card">
            <div class="p360-muted"><?= e((string)$slot['label']) ?></div>
            <div class="p360-kpi p360-kpi-sm"><?= e((string)$slot['value']) ?></div>
            <div class="p360-muted"><?= e((string)$slot['meta']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card">
      <h3 class="p360-section-title">Lead Ownership Context</h3>
      <div class="p360-grid p360-stack-md">
        <div class="p360-card">
          <div class="p360-muted">Current Lead</div>
          <?php if ($assignedUser): ?>
            <div class="p360-kpi p360-kpi-sm"><?= e((string)($assignedUser['display_name'] ?? $assignedUser['email'] ?? '-')) ?></div>
            <div class="p360-muted">
              <?= e((string)($assignedUser['responsibility_label'] ?? 'Lead')) ?>
              <?php if (trim((string)($assignedUser['department'] ?? '')) !== ''): ?> · <?= e((string)$assignedUser['department']) ?><?php endif; ?>
              <?php if (trim((string)($assignedUser['email'] ?? '')) !== ''): ?> · <?= e((string)$assignedUser['email']) ?><?php endif; ?>
            </div>
          <?php else: ?>
            <div class="p360-empty">No lead is assigned yet.</div>
          <?php endif; ?>
        </div>
        <div class="p360-card">
          <div class="p360-muted"> <?= e($tt('products.part_lead_master_label')) ?> </div>
          <div class="p360-kpi p360-kpi-sm"><?= e((string)($product['lead'] ?? '-')) ?></div>
          <div class="p360-muted">This remains visible even when the operational lead assignment is empty.</div>
        </div>
        <div class="p360-card">
          <div class="p360-muted"> <?= e($tt('products.active_machine_assignment_message')) ?> </div>
          <div class="p360-kpi p360-kpi-sm"><?= e($machineContext) ?></div>
          <div class="p360-muted"><?= e($machineContextMeta) ?></div>
        </div>
      </div>
    </section>

    <section class="card">
      <h3 class="p360-section-title">Lead Ownership Maintenance</h3>
      <div class="p360-muted">Assigned leads and admins can update ownership where intended.</div>
      <?php if ($canManageResponsibility): ?>
        <form method="post" action="/products/responsible-user" class="form-grid u-style-1b0f4999d2">
          <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
          <input type="hidden" name="product_id" value="<?= $productId ?>">
          <div class="form-field-wide">
            <label class="field-label">Current Lead</label>
            <select name="responsible_user_id">
              <option value="">Not assigned</option>
              <?php foreach ($availableResponsibilityUsers as $userOption): ?>
                <option value="<?= (int)($userOption['id'] ?? 0) ?>" <?= (int)($assignedUser['id'] ?? 0) === (int)($userOption['id'] ?? 0) ? 'selected' : '' ?>>
                  <?= e((string)($userOption['display_name'] ?? $userOption['email'] ?? '-')) ?>
                  <?php if (trim((string)($userOption['responsibility_label'] ?? '')) !== ''): ?> · <?= e((string)$userOption['responsibility_label']) ?><?php endif; ?>
                  <?php if (trim((string)($userOption['department'] ?? '')) !== ''): ?> · <?= e((string)$userOption['department']) ?><?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-actions"><button class="btn ok" type="submit">Save Lead Ownership</button></div>
        </form>
      <?php else: ?>
        <div class="p360-card u-style-56f4356299"><div class="p360-empty">Ownership remains visible here for all users. Editing is limited to the assigned lead or admins.</div></div>
      <?php endif; ?>
    </section>
  </div>

  <?php if ($isAdmin): ?>
    <div class="p360-panel<?= $activeTab === 'admin-controls' ? ' is-active' : '' ?>" id="p360-panel-admin-controls" data-p360-panel="admin-controls" role="tabpanel">
      <section class="card">
        <h3 class="p360-section-title">Admin Controls</h3>
        <div class="p360-muted">Lifecycle and advanced maintenance stay separated here so viewers and assigned leads never see admin-only state controls.</div>
        <div class="p360-grid">
          <div class="p360-card">
            <div class="p360-muted"> <?= e($tt('products.lifecycle_state_message')) ?> </div>
            <div class="p360-kpi p360-kpi-sm"><?= e($statusLabel) ?></div>
            <div class="p360-inline-actions u-style-56f4356299">
              <?php if ((int)($product['is_active'] ?? 1) === 1): ?>
                <form method="post" action="<?= e((string)($links['part_deactivate'] ?? '/products/deactivate')) ?>" class="p360-inline-form">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="id" value="<?= $productId ?>">
                  <button class="btn danger" type="submit">Deactivate Part</button>
                </form>
              <?php else: ?>
                <form method="post" action="<?= e((string)($links['part_activate'] ?? '/products/activate')) ?>" class="p360-inline-form">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="id" value="<?= $productId ?>">
                  <button class="btn ok" type="submit">Activate Part</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
          <div class="p360-card">
            <div class="p360-muted">Advanced Maintenance</div>
            <div class="p360-empty">Permanent delete is intentionally buried here and clearly marked destructive.</div>
            <details class="p360-admin-detail">
              <summary>Open destructive maintenance</summary>
              <form method="post" action="<?= e((string)($links['part_delete'] ?? '/products/delete')) ?>" class="p360-stack-md" style="margin-top:12px" onsubmit="return confirm('<?= e(t('module.products.delete_confirm')) ?>');">
                <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= $productId ?>">
                <div class="p360-muted">Permanent delete is not part of normal workflow. Prefer activate/deactivate unless the record truly must be removed.</div>
                <div class="control-actions">
                  <button class="btn danger" type="submit"> <?= e($tt('products.permanently_delete_part_action')) ?> </button>
                </div>
              </form>
            </details>
          </div>
        </div>
      </section>
    </div>
  <?php endif; ?>
</div>

<?php
$renderList(t('part_360.list.stock_ledger'), t('part_360.empty.stock_ledger'), $recentLedger, static function (array $rows) use ($fmt, $sourceUrl): void {
?>
<table class="p360-table"><thead><tr><th><?= e(t('common.when')) ?></th><th><?= e(t('common.type')) ?></th><th>Delta</th><th>Balance</th><th>Reference</th><th><?= e(t('common.action')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= e((string)($row['created_at'] ?? '-')) ?></td><td><?= e((string)($row['movement_type'] ?? '-')) ?></td><td><?= e($fmt((float)($row['qty_delta'] ?? 0))) ?></td><td><?= e($fmt((float)($row['balance_after'] ?? 0))) ?></td><td><strong><?= e((string)($row['reference_no'] ?? '-')) ?></strong><br><span class="p360-muted"><?= e((string)($row['source_module'] ?? '')) ?> #<?= (int)($row['source_id'] ?? 0) ?></span></td><td><?php if ((string)($row['source_module'] ?? '') !== '' && (int)($row['source_id'] ?? 0) > 0): ?><a class="btn" href="<?= e($sourceUrl((string)($row['source_module'] ?? ''), (int)($row['source_id'] ?? 0))) ?>"><?= e(t('common.open')) ?></a><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php
});
?>

<script>
  (function () {
    var root = document.querySelector('.p360-panels');
    if (!root) {
      return;
    }

    var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-p360-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-p360-panel]'));
    var linkButtons = Array.prototype.slice.call(document.querySelectorAll('[data-p360-tab-link]'));
    var defaultTab = root.getAttribute('data-p360-default-tab') || 'overview';

    function setTab(tabId, updateHash) {
      var target = tabId || defaultTab;
      buttons.forEach(function (button) {
        var active = button.getAttribute('data-p360-tab') === target;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panels.forEach(function (panel) {
        var active = panel.getAttribute('data-p360-panel') === target;
        panel.classList.toggle('is-active', active);
      });
      if (updateHash) {
        window.location.hash = 'p360-tab-' + target;
      }
    }

    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        setTab(button.getAttribute('data-p360-tab'), true);
      });
    });

    linkButtons.forEach(function (link) {
      link.addEventListener('click', function () {
        var target = link.getAttribute('data-p360-tab-link');
        if (target) {
          setTab(target, true);
        }
      });
    });

    var hashMatch = window.location.hash.match(/^#p360-tab-(.+)$/);
    setTab(hashMatch ? hashMatch[1] : defaultTab, false);
  }());
</script>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
