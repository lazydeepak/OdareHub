<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

use App\Core\Auth;
use App\Core\DB;
use Apps\Manufacturing\Services\WorkEntryOwnershipContributionService;
use Apps\Shell\Services\OperatorLayerSidebarService;
use Apps\Shell\Services\ThemePreferenceService;

/**
 * WorkEntryComposer
 *
 * Role-aware universal work entry form for the operator layer (/u/{username}/work-entry).
 * Covers Manufacturing actions: Add, Update, Approval, Receive, Report.
 * Each action shows only the sub-types permitted for the current dashboard_type (role).
 */
final class WorkEntryComposer
{
    private string $username;
    private array $context;
    private string $role;

    // role → [ action → [allowed sub-types] ]
    private const ROLE_ACTION_TYPES = [
        'operator' => [
            'add'    => ['production_entry', 'material_consumption', 'attendance_entry', 'timecard_entry'],
            'report' => ['defect', 'waste', 'machine_issue', 'delay', 'leave_request'],
        ],
        'production_leader' => [
            'add'      => ['production_entry', 'material_consumption', 'attendance_entry', 'timecard_entry'],
            'update'   => ['production_entry', 'plan_status'],
            'approval' => ['production_plan'],
            'report'   => ['defect', 'waste', 'machine_issue', 'delay', 'leave_request'],
        ],
        'assembly_leader' => [
            'add'      => ['production_entry', 'material_consumption', 'attendance_entry', 'timecard_entry'],
            'approval' => ['assembly_plan'],
            'report'   => ['defect', 'waste', 'machine_issue', 'leave_request'],
        ],
        'qc_leader' => [
            'add'      => ['attendance_entry', 'timecard_entry'],
            'update'   => ['production_entry'],
            'approval' => ['qc_report'],
            'report'   => ['defect', 'waste', 'machine_issue', 'leave_request'],
        ],
        'dispatch_leader' => [
            'add'      => ['dispatch_entry', 'attendance_entry', 'timecard_entry'],
            'receive'  => ['material'],
            'approval' => ['dispatch_request'],
            'report'   => ['delay', 'machine_issue', 'leave_request'],
        ],
        'ops' => [
            'add'      => ['production_entry', 'material_consumption', 'dispatch_entry', 'attendance_entry', 'timecard_entry'],
            'update'   => ['production_entry', 'plan_status', 'order_status', 'dispatch_status'],
            'approval' => ['production_plan', 'assembly_plan', 'qc_report', 'dispatch_request'],
            'receive'  => ['material'],
            'report'   => ['defect', 'waste', 'machine_issue', 'delay', 'leave_request'],
        ],
        'platform_admin' => [
            'add'      => ['production_entry', 'material_consumption', 'dispatch_entry', 'attendance_entry', 'timecard_entry'],
            'update'   => ['production_entry', 'plan_status', 'order_status', 'dispatch_status'],
            'approval' => ['production_plan', 'assembly_plan', 'qc_report', 'dispatch_request'],
            'receive'  => ['material'],
            'report'   => ['defect', 'waste', 'machine_issue', 'delay', 'leave_request'],
        ],
    ];

    // Non-manufacturing type ownership for runtime filtering.
    private const TYPE_OWNERSHIP = [
        'attendance_entry' => ['app' => 'sbaio', 'modules' => ['attendance']],
        'timecard_entry' => ['app' => 'sbaio', 'modules' => ['timecards']],
        'leave_request' => ['app' => 'sbaio', 'modules' => ['leave']],
    ];

    private const ACTION_LABELS = [
        'add'      => 'Add',
        'update'   => 'Update',
        'approval' => 'Approval',
        'receive'  => 'Receive',
        'report'   => 'Report',
    ];

    private const TYPE_LABELS = [
        'production_entry'    => 'Production Entry',
        'material_consumption'=> 'Material Consumption',
        'dispatch_entry'      => 'Dispatch Entry',
        'production_entry_u'  => 'Production Entry',
        'plan_status'         => 'Plan Status',
        'order_status'        => 'Order Status',
        'dispatch_status'     => 'Dispatch Status',
        'production_plan'     => 'Production Plan',
        'assembly_plan'       => 'Assembly Plan',
        'qc_report'           => 'QC Report',
        'dispatch_request'    => 'Dispatch Request',
        'material'            => 'Material',
        'defect'              => 'Defect / Quality',
        'waste'               => 'Waste',
        'machine_issue'       => 'Machine Issue',
        'delay'               => 'Delay',
        'attendance_entry'    => 'Attendance Entry',
        'timecard_entry'      => 'Timecard Entry',
        'leave_request'       => 'Leave Request',
    ];

    private const ACTION_DESCRIPTIONS = [
        'add'      => 'Create a new operational entry from today\'s shop-floor work.',
        'update'   => 'Modify status or details for existing records that are still in progress.',
        'approval' => 'Review pending records and record an approval decision.',
        'receive'  => 'Capture inbound material receipt to keep stock and planning accurate.',
        'report'   => 'Log operational incidents so follow-up and root cause actions can start fast.',
    ];

    private const TYPE_DESCRIPTIONS = [
        'production_entry'     => 'Record production output and rejection quantities for a machine shift.',
        'material_consumption' => 'Record consumed material quantity from shop-floor usage.',
        'dispatch_entry'       => 'Create a dispatch record for outbound quantity movement.',
        'plan_status'          => 'Update production plan status with accountability.',
        'order_status'         => 'Update daily order execution status.',
        'dispatch_status'      => 'Update dispatch workflow status for a dispatch entry.',
        'production_plan'      => 'Approve or reject pending production plans.',
        'assembly_plan'        => 'Approve or reject pending assembly plans.',
        'qc_report'            => 'Approve QC records based on quality findings.',
        'dispatch_request'     => 'Approve dispatch requests to move goods downstream.',
        'material'             => 'Receive incoming material and update inventory ledger.',
        'defect'               => 'Report defects with severity and checked quantity context.',
        'waste'                => 'Report scrap or waste quantity with reason coding.',
        'machine_issue'        => 'Report machine incidents with urgency for maintenance response.',
        'delay'                => 'Report delays with expected resolution tracking.',
        'attendance_entry'     => 'Capture attendance updates for office and support operations.',
        'timecard_entry'       => 'Capture worked hours for payroll and utilization traceability.',
        'leave_request'        => 'Log leave requests and context for HR follow-up.',
    ];

    private const TYPE_IMPACTS = [
        'production_entry'     => 'Impacts production performance, yield, and downstream planning visibility.',
        'material_consumption' => 'Impacts inventory availability and material coverage projections.',
        'dispatch_entry'       => 'Impacts dispatch queue, readiness, and customer delivery commitments.',
        'plan_status'          => 'Impacts plan board state and supervisor decision visibility.',
        'order_status'         => 'Impacts daily order closure and operation monitoring.',
        'dispatch_status'      => 'Impacts shipping workflow and execution SLA tracking.',
        'production_plan'      => 'Impacts whether production execution can proceed for pending plans.',
        'assembly_plan'        => 'Impacts assembly execution readiness and queue release.',
        'qc_report'            => 'Impacts quality gate outcomes before dispatch decisions.',
        'dispatch_request'     => 'Impacts dispatch authorization and outbound movement control.',
        'material'             => 'Impacts on-hand stock and material readiness for execution.',
        'defect'               => 'Impacts quality visibility and corrective action prioritization.',
        'waste'                => 'Impacts waste analytics and process improvement actions.',
        'machine_issue'        => 'Impacts machine availability and production continuity.',
        'delay'                => 'Impacts SLA confidence and escalation planning.',
        'attendance_entry'     => 'Impacts attendance visibility and daily workforce readiness.',
        'timecard_entry'       => 'Impacts payroll preparation and time utilization tracking.',
        'leave_request'        => 'Impacts team capacity planning and schedule coverage.',
    ];

    private function tr(string $key, string $fallback, array $params = []): string
    {
        if (function_exists('t')) {
            $translated = (string)t($key, $params);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }
        if ($params === []) {
            return $fallback;
        }
        $replace = [];
        foreach ($params as $paramKey => $paramValue) {
            $replace['{' . $paramKey . '}'] = (string)$paramValue;
        }
        return strtr($fallback, $replace);
    }

    private function actionLabel(string $action): string
    {
        return $this->tr('work_entry.action.' . $action, self::ACTION_LABELS[$action] ?? ucfirst($action));
    }

    private function typeLabel(string $type): string
    {
        return $this->tr('work_entry.type.' . $type, self::TYPE_LABELS[$type] ?? ucwords(str_replace('_', ' ', $type)));
    }

    private function actionDescription(string $action): string
    {
        return $this->tr('work_entry.action_desc.' . $action, self::ACTION_DESCRIPTIONS[$action] ?? '');
    }

    private function typeDescription(string $type): string
    {
        return $this->tr('work_entry.type_desc.' . $type, self::TYPE_DESCRIPTIONS[$type] ?? '');
    }

    private function typeImpact(string $type): string
    {
        return $this->tr('work_entry.type_impact.' . $type, self::TYPE_IMPACTS[$type] ?? '');
    }

    public function __construct(string $username, array $context)
    {
        $this->username = $username;
        $this->context  = $context;
        $this->role     = strtolower(trim((string)($context['dashboard_type'] ?? 'operator')));
    }

    private function allowedActions(): array
    {
        $base = self::ROLE_ACTION_TYPES[$this->role] ?? self::ROLE_ACTION_TYPES['operator'];
        $filtered = [];
        foreach ($base as $action => $types) {
            $eligible = array_values(array_filter($types, fn (string $type): bool => $this->isTypeEligibleForContext($type)));
            if ($eligible !== []) {
                $filtered[$action] = $eligible;
            }
        }
        if ($filtered !== []) {
            return $filtered;
        }
        return [
            'report' => ['leave_request'],
        ];
    }

    private function isTypeEligibleForContext(string $type): bool
    {
        $typeOwnership = $this->typeOwnershipMap();
        $owner = $typeOwnership[$type] ?? null;
        if (!is_array($owner)) {
            return true;
        }
        $assignedApps = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($this->context['active_assigned_apps'] ?? $this->context['assigned_apps'] ?? [])
        );
        $app = strtolower(trim((string)($owner['app'] ?? '')));
        if ($app !== '' && !in_array($app, $assignedApps, true)) {
            return false;
        }

        if ($app === 'sbaio') {
            return true;
        }

        $modules = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($owner['modules'] ?? [])
        );
        if ($modules === []) {
            return true;
        }

        $moduleVisibility = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($this->context['module_visibility'] ?? [])
        );
        if ($moduleVisibility === []) {
            return true;
        }
        foreach ($modules as $moduleKey) {
            if (in_array($moduleKey, $moduleVisibility, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function typeOwnershipMap(): array
    {
        $ownership = self::TYPE_OWNERSHIP;
        if (class_exists(WorkEntryOwnershipContributionService::class) && method_exists(WorkEntryOwnershipContributionService::class, 'typeOwnershipMap')) {
            $ownership = array_merge(
                (array)WorkEntryOwnershipContributionService::typeOwnershipMap(),
                $ownership
            );
        }

        return $ownership;
    }

    // ─── Data loaders ────────────────────────────────────────────────────────

    private function loadProducts(): array
    {
        try {
            return DB::fetchAll(
                'SELECT id, parts_name, parts_number FROM products ORDER BY parts_name, parts_number LIMIT 300'
            ) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadMachines(): array
    {
        try {
            return DB::fetchAll(
                'SELECT id, machine_name, machine_no FROM machines ORDER BY machine_name LIMIT 100'
            ) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadMaterials(): array
    {
        try {
            return DB::fetchAll(
                'SELECT id, material_name, COALESCE(NULLIF(uom,""), unit, "kg") AS uom FROM materials ORDER BY material_name LIMIT 200'
            ) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadPendingProductionPlans(): array
    {
        try {
            return DB::fetchAll(
                "SELECT pp.id, pp.plan_date, pp.planned_qty, pp.approval_status,
                        COALESCE(NULLIF(TRIM(p.parts_name),''), CONCAT('Part #',pp.product_id)) AS part_name
                 FROM production_plans pp
                 LEFT JOIN products p ON p.id = pp.product_id
                 WHERE pp.approval_status IN ('Draft','Pending','pending','draft')
                 ORDER BY pp.plan_date DESC, pp.id DESC LIMIT 50"
            ) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadPendingAssemblyPlans(): array
    {
        try {
            return DB::fetchAll(
                "SELECT id, plan_date, approval_status
                 FROM assembly_plans
                 WHERE approval_status IN ('Draft','Pending','pending','draft')
                 ORDER BY plan_date DESC, id DESC LIMIT 50"
            ) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadPendingQCEntries(): array
    {
        try {
            return DB::fetchAll(
                "SELECT qe.id, qe.product_id, qe.qc_type, qe.approval_status,
                        COALESCE(NULLIF(TRIM(p.parts_name),''), CONCAT('Part #',qe.product_id)) AS part_name
                 FROM qc_entries qe
                 LEFT JOIN products p ON p.id = qe.product_id
                 WHERE qe.approval_status IN ('Draft','Open','Pending','draft','open','pending')
                 ORDER BY qe.id DESC LIMIT 50"
            ) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadPendingDispatchEntries(): array
    {
        try {
            return DB::fetchAll(
                "SELECT de.id, de.dispatch_date, de.approval_status, de.dispatch_status,
                        COALESCE(NULLIF(TRIM(p.parts_name),''), CONCAT('Part #',de.product_id)) AS part_name
                 FROM dispatch_entries de
                 LEFT JOIN products p ON p.id = de.product_id
                 WHERE de.approval_status IN ('Draft','Pending','draft','pending')
                 ORDER BY de.dispatch_date DESC, de.id DESC LIMIT 50"
            ) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadOpenProductionEntries(): array
    {
        try {
            return DB::fetchAll(
                "SELECT pe.id, pe.production_date, pe.status, pe.produced_qty, pe.rejected_qty,
                        COALESCE(NULLIF(TRIM(p.parts_name),''), CONCAT('Part #',pe.product_id)) AS part_name
                 FROM production_entries pe
                 LEFT JOIN products p ON p.id = pe.product_id
                 WHERE pe.status IN ('Draft','In Progress','draft','in_progress')
                 ORDER BY pe.production_date DESC, pe.id DESC LIMIT 50"
            ) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function loadOpenOrders(): array
    {
        try {
            return DB::fetchAll(
                "SELECT id, order_date, status FROM daily_orders
                 ORDER BY order_date DESC, id DESC LIMIT 50"
            ) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    // ─── Render ──────────────────────────────────────────────────────────────

    public static function render(string $username, array $context, array $query = []): void
    {
        $composer = new self($username, $context);
        header('Content-Type: text/html; charset=utf-8');
        echo $composer->buildPage($query);
    }

    public static function renderFragment(string $username, array $context, array $query = []): string
    {
        $composer = new self($username, $context);
        $query['fragment_only'] = '1';
        return $composer->buildPage($query);
    }

    private function buildPage(array $query): string
    {
        $actions = $this->allowedActions();
        $currentAction = trim((string)($query['action'] ?? array_key_first($actions)));
        if (!isset($actions[$currentAction])) {
            $currentAction = (string)array_key_first($actions);
        }
        $currentType = trim((string)($query['type'] ?? ($actions[$currentAction][0] ?? '')));
        if ($currentType === '' || !in_array($currentType, $actions[$currentAction] ?? [], true)) {
            $currentType = (string)($actions[$currentAction][0] ?? '');
        }

        // Load data lazily only for the selected type
        $data = $this->loadPageData($actions);

        $flash = $this->pullFlash();
        $csrf  = htmlspecialchars(Auth::csrfToken(), ENT_QUOTES);
        $uenc  = rawurlencode($this->username);
        $roleLabel = ucwords(str_replace('_', ' ', $this->role));
        $isFragmentOnly = ((string)($query['fragment_only'] ?? '') === '1');
        $isEmbedded = $isFragmentOnly || ((string)($query['embedded'] ?? '') === '1');
        $homeUrl = '/u/' . $uenc;
        $companyName = trim((string)($this->context['company_name'] ?? 'IPM Local'));
        $sidebarService = new OperatorLayerSidebarService($this->context);
        $rawSidebar = $sidebarService->getContextualSidebar('my-work');
        foreach ($rawSidebar as &$section) {
            if (isset($section['items']) && is_array($section['items'])) {
                $section['items'] = array_values(array_filter(
                    $section['items'],
                    static function (array $item): bool {
                        $route = (string)($item['route'] ?? '');
                        return !str_ends_with($route, '/critical') && !str_ends_with($route, '/recent');
                    }
                ));
            }
        }
        unset($section);
        $sidebar = $sidebarService->formatSidebarItems($rawSidebar, $this->username);
        $pageLanguage = function_exists('current_lang') ? (string)current_lang() : 'en';

        $i18n = [
            'operator_workspace' => $this->tr('operator.surface.operator_workspace', 'Operator Workspace'),
            'workspace' => $this->tr('operator.surface.workspace', 'Workspace'),
            'work_entry' => $this->tr('operator.sidebar.work_entry', 'Work Entry'),
            'dashboard' => $this->tr('operator.sidebar.dashboard', 'Dashboard'),
            'operator_sidebar' => $this->tr('operator.sidebar.aria_label', 'Operator Sidebar'),
            'action' => $this->tr('work_entry.action.label', 'Action'),
            'type' => $this->tr('work_entry.type.label', 'Type'),
            'workflow_guide' => $this->tr('work_entry.workflow.guide', 'Workflow Guide'),
            'step1' => $this->tr('work_entry.workflow.step1', '1. Choose Action'),
            'step2' => $this->tr('work_entry.workflow.step2', '2. Choose Type'),
            'step3' => $this->tr('work_entry.workflow.step3', '3. Fill Required Fields'),
            'step4' => $this->tr('work_entry.workflow.step4', '4. Submit Entry'),
            'selected_action' => $this->tr('work_entry.workflow.selected_action', 'Selected Action'),
            'selected_type' => $this->tr('work_entry.workflow.selected_type', 'Selected Type'),
            'why_it_matters' => $this->tr('work_entry.workflow.why_it_matters', 'Why this matters'),
            'required_hint' => $this->tr('work_entry.workflow.required_hint', 'Fields marked * are required before submit.'),
            'validation_title' => $this->tr('work_entry.validation.title', 'Please complete the required fields:'),
            'confirm_sensitive' => $this->tr('work_entry.validation.confirm_sensitive', 'This action updates operational records. Do you want to continue?'),
            'required_field' => $this->tr('work_entry.validation.required_field', 'Required field'),
            'submitting' => $this->tr('work_entry.submit.submitting', 'Submitting...'),
            'submit' => $this->tr('work_entry.submit', 'Submit'),
            'cancel' => $this->tr('common.cancel', 'Cancel'),
            'switch_to_admin' => $this->tr('operator.surface.switch_to_admin', 'Switch to Admin'),
        ];

        $actionMeta = [];
        foreach (array_keys($actions) as $action) {
            $actionMeta[$action] = [
                'label' => $this->actionLabel($action),
                'description' => $this->actionDescription($action),
            ];
        }

        $typeMeta = [];
        foreach ($actions as $action => $types) {
            foreach ($types as $type) {
                $typeMeta[$action . '/' . $type] = [
                    'label' => $this->typeLabel($type),
                    'description' => $this->typeDescription($type),
                    'impact' => $this->typeImpact($type),
                ];
            }
        }

        ob_start();
        $weThemePreference = ThemePreferenceService::normalizePreference(ThemePreferenceService::defaultPreference());
        $weThemeMode = ThemePreferenceService::modeFromPreference($weThemePreference);
        $weColorStyle = ThemePreferenceService::colorStyleFromPreference($weThemePreference);
        $weEffectiveTheme = $weThemeMode === 'light' ? 'light' : 'dark';
        $weThemeChoices = ThemePreferenceService::themeChoices();
        ?>
    <?php if (!$isFragmentOnly): ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($pageLanguage); ?>" data-theme="<?php echo htmlspecialchars($weEffectiveTheme); ?>" data-theme-mode="<?php echo htmlspecialchars($weThemeMode); ?>" data-color-style="<?php echo htmlspecialchars($weColorStyle); ?>" data-theme-preference="<?php echo htmlspecialchars($weThemePreference); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($this->tr('work_entry.page_title', 'Work Entry')); ?> - <?php echo htmlspecialchars($this->username); ?></title>
<script>
(function () {
  var storageKey = 'erp-theme-preference';
  var fallbackPreference = <?php echo json_encode($weThemePreference, JSON_UNESCAPED_SLASHES); ?>;
  var allowedPreferences = <?php echo json_encode(array_values(array_keys($weThemeChoices)), JSON_UNESCAPED_SLASHES); ?>;

  function normalizeThemePreference(value) {
    var raw = (value || '').toString().trim().toLowerCase();
    var fallbackParts = (fallbackPreference || 'system-liquid-glass').split('-');
    var fallbackStyle = fallbackParts.slice(1).join('-') || 'liquid-glass';
    if (allowedPreferences.indexOf(raw) !== -1) return raw;
    if (raw === 'light') return 'light-' + fallbackStyle;
    if (raw === 'dark') return 'dark-' + fallbackStyle;
    if (raw === 'system') return 'system-' + fallbackStyle;
    return fallbackPreference || 'system-liquid-glass';
  }

  function resolveSystemTheme() {
    return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
      ? 'dark' : 'light';
  }

  function applyThemePreference(value) {
    var preference = normalizeThemePreference(value);
    var parts = preference.split('-');
    var mode = parts[0] === 'light' || parts[0] === 'dark' ? parts[0] : 'system';
    var colorStyle = parts.slice(1).join('-') || 'liquid-glass';
    var effectiveTheme = mode === 'system' ? resolveSystemTheme() : mode;
    document.documentElement.setAttribute('data-theme-mode', mode);
    document.documentElement.setAttribute('data-theme', effectiveTheme);
    document.documentElement.setAttribute('data-color-style', colorStyle);
    document.documentElement.setAttribute('data-theme-preference', preference);
    document.documentElement.style.colorScheme = effectiveTheme;
  }

  try {
    applyThemePreference(window.localStorage.getItem(storageKey) || fallbackPreference);
  } catch (_e) {
    applyThemePreference(fallbackPreference);
  }
})();
</script>
<link rel="stylesheet" href="/assets/branding/ipm-logo.css">
<!-- All CSS managed by StyleRegistryService -->
<?php
    if (class_exists('\Apps\Shell\Services\StyleRegistryService')) {
        $allStyles = array_merge(
            \Apps\Shell\Services\StyleRegistryService::globals(),
            \Apps\Shell\Services\StyleRegistryService::forSurface('work-entry', $this->data ?? [])
        );
        foreach ($allStyles as $style) {
            $styleUrl = $style['url'] ?? '';
            $styleVersion = $style['version'] ?? '1';
            if ($styleUrl !== '') {
                echo '<link rel="stylesheet" href="' . htmlspecialchars($styleUrl) . '?v=' . htmlspecialchars($styleVersion) . '">' . "\n";
            }
        }
    }
?>
</head>
<body class="<?php echo $isEmbedded ? '' : 'layout-shell operator-shell-page'; ?>">
<?php endif; ?>

<?php if (!$isEmbedded): ?>

<header class="u-header">
    <a href="<?php echo htmlspecialchars($homeUrl); ?>" class="u-company"><?php echo htmlspecialchars($companyName); ?></a>
    <div class="u-header-center"><?php echo htmlspecialchars($i18n['operator_workspace']); ?></div>
    <span class="u-user"><?php echo htmlspecialchars((string)$this->username); ?></span>
</header>

<div class="u-body">
    <aside class="u-sidebar" aria-label="<?php echo htmlspecialchars($i18n['operator_sidebar']); ?>">
        <div class="u-sidebar-title"><?php echo htmlspecialchars((string)($sidebar['app_label'] ?? $i18n['workspace'])); ?></div>
        <?php foreach ((array)($sidebar['sections'] ?? []) as $section): ?>
            <div class="u-sidebar-section">
                <div class="u-sidebar-section-title"><?php echo htmlspecialchars((string)($section['title'] ?? '')); ?></div>
                <?php foreach ((array)($section['items'] ?? []) as $item): ?>
                    <?php $route = (string)($item['route'] ?? '#'); ?>
                    <a href="<?php echo htmlspecialchars($route); ?>" class="u-nav-item<?php echo str_contains($route, '/work-entry') ? ' active' : ''; ?>">
                        <span class="u-nav-icon"><?php echo htmlspecialchars((string)($item['icon'] ?? '•')); ?></span>
                        <span class="u-nav-label"><?php echo htmlspecialchars((string)($item['label'] ?? '')); ?></span>
                        <?php if (isset($item['badge']) && (int)$item['badge'] > 0): ?>
                            <span class="u-nav-badge"><?php echo (int)$item['badge']; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </aside>

    <main class="u-main">
<?php endif; ?>

<div class="we-shell<?php echo $isEmbedded ? ' we-shell-embedded' : ''; ?>">
    <?php if (!$isEmbedded): ?>
    <!-- Top bar -->
    <header class="we-header">
        <a href="/u/<?php echo $uenc; ?>" class="we-back">&#8592; <?php echo htmlspecialchars($i18n['dashboard']); ?></a>
        <div class="we-header-center">
            <span class="we-title"><?php echo htmlspecialchars($i18n['work_entry']); ?></span>
        </div>
        <span class="we-role-badge"><?php echo htmlspecialchars($roleLabel); ?></span>
    </header>
    <?php endif; ?>

  <!-- Flash -->
  <?php if ($flash !== null): ?>
  <div class="we-flash we-flash-<?php echo $flash['type'] === 'ok' ? 'ok' : 'err'; ?>">
    <?php echo htmlspecialchars($flash['msg']); ?>
  </div>
  <?php endif; ?>

  <!-- Content -->
  <main class="we-content">
    <form method="POST" action="/u/<?php echo $uenc; ?>/work-entry" id="we-form" novalidate>
      <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">

            <section class="we-section we-workflow" id="we-workflow">
                <div class="we-section-label"><?php echo htmlspecialchars($i18n['workflow_guide']); ?></div>
                <div class="we-workflow-steps">
                    <span class="we-workflow-step"><?php echo htmlspecialchars($i18n['step1']); ?></span>
                    <span class="we-workflow-step"><?php echo htmlspecialchars($i18n['step2']); ?></span>
                    <span class="we-workflow-step"><?php echo htmlspecialchars($i18n['step3']); ?></span>
                    <span class="we-workflow-step"><?php echo htmlspecialchars($i18n['step4']); ?></span>
                </div>
                <div class="we-workflow-context">
                    <div class="we-workflow-grid">
                        <p class="we-workflow-line"><span class="we-workflow-key"><?php echo htmlspecialchars($i18n['selected_action']); ?>:</span> <strong id="we-selected-action"></strong></p>
                        <p class="we-workflow-line"><span class="we-workflow-key"><?php echo htmlspecialchars($i18n['selected_type']); ?>:</span> <strong id="we-selected-type"></strong></p>
                    </div>
                    <p class="we-workflow-desc" id="we-selected-desc"></p>
                    <p class="we-workflow-impact" id="we-selected-impact"><strong><?php echo htmlspecialchars($i18n['why_it_matters']); ?>:</strong> <span id="we-selected-impact-text"></span></p>
                    <p class="we-required-hint"><?php echo htmlspecialchars($i18n['required_hint']); ?></p>
                </div>
                <div class="we-validation we-hidden" id="we-validation" role="alert" aria-live="polite"></div>
            </section>

      <!-- Step 1: Action pills -->
      <section class="we-section">
                <div class="we-section-label"><?php echo htmlspecialchars($i18n['action']); ?></div>
        <div class="we-pills" id="action-pills">
          <?php foreach ($actions as $act => $types): ?>
          <label class="we-pill<?php echo $act === $currentAction ? ' active' : ''; ?>" data-action="<?php echo htmlspecialchars($act); ?>">
            <input type="radio" name="action" value="<?php echo htmlspecialchars($act); ?>"<?php echo $act === $currentAction ? ' checked' : ''; ?>>
                        <?php echo htmlspecialchars($this->actionLabel($act)); ?>
          </label>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Step 2: Sub-type pills (per action) -->
      <?php foreach ($actions as $act => $types): ?>
            <section class="we-section we-subtypes<?php echo $act === $currentAction ? '' : ' we-hidden'; ?>" id="subtypes-<?php echo $act; ?>">
                <div class="we-section-label"><?php echo htmlspecialchars($i18n['type']); ?></div>
        <div class="we-pills" id="subtype-pills-<?php echo $act; ?>">
          <?php foreach ($types as $i => $type): ?>
          <?php $firstOfAction = ($i === 0 && $act === $currentAction) || ($act !== $currentAction && $i === 0); ?>
          <label class="we-pill<?php echo ($act === $currentAction && $type === $currentType) ? ' active' : ''; ?>" data-type="<?php echo htmlspecialchars($type); ?>" data-action="<?php echo htmlspecialchars($act); ?>">
            <input type="radio" name="type" value="<?php echo htmlspecialchars($type); ?>"<?php echo ($act === $currentAction && $type === $currentType) ? ' checked' : ''; ?>>
                        <?php echo htmlspecialchars($this->typeLabel($type)); ?>
          </label>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endforeach; ?>

      <!-- Step 3: Field groups -->
      <?php foreach ($actions as $act => $types): ?>
        <?php foreach ($types as $type): ?>
                <section class="we-fields<?php echo ($act === $currentAction && $type === $currentType) ? '' : ' we-hidden'; ?>" id="fields-<?php echo $act; ?>-<?php echo $type; ?>">
          <?php $this->renderFieldGroup($act, $type, $data); ?>
        </section>
        <?php endforeach; ?>
      <?php endforeach; ?>

      <!-- Submit bar -->
      <div class="we-submit-bar" id="submit-bar">
                <button type="submit" class="we-btn-primary" id="we-submit-btn"><?php echo htmlspecialchars($i18n['submit']); ?></button>
                <a href="/u/<?php echo $uenc; ?>" class="we-btn-secondary"><?php echo htmlspecialchars($i18n['cancel']); ?></a>
      </div>

    </form>
  </main>
</div>

<?php if (!$isEmbedded): ?>

    </main>
</div>

<footer class="u-footer">
    <span><?php echo htmlspecialchars($i18n['operator_workspace']); ?></span>
    <?php if ($this->role === 'platform_admin'): ?>
        <a href="/admin/<?php echo $uenc; ?>" class="u-footer-link"><?php echo htmlspecialchars($i18n['switch_to_admin']); ?> ⚙️</a>
    <?php endif; ?>
</footer>
<?php endif; ?>

<script>
(function() {
  var form = document.getElementById('we-form');
  var actionPills = document.querySelectorAll('#action-pills .we-pill');
    var actionMeta = <?php echo json_encode($actionMeta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var typeMeta = <?php echo json_encode($typeMeta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var i18n = <?php echo json_encode([
            'validationTitle' => $i18n['validation_title'],
            'confirmSensitive' => $i18n['confirm_sensitive'],
            'requiredField' => $i18n['required_field'],
            'submitting' => $i18n['submitting'],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var selectedActionEl = document.getElementById('we-selected-action');
    var selectedTypeEl = document.getElementById('we-selected-type');
    var selectedDescEl = document.getElementById('we-selected-desc');
    var selectedImpactTextEl = document.getElementById('we-selected-impact-text');
    var validationEl = document.getElementById('we-validation');
    var submitBtn = document.getElementById('we-submit-btn');
    var flashType = <?php echo json_encode($flash['type'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var draftPrefix = 'we-draft:' + <?php echo json_encode($this->username, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + ':';

    function hasStorage() {
        try {
            return typeof window.sessionStorage !== 'undefined';
        } catch (e) {
            return false;
        }
    }

    function draftKey(action, type) {
        return draftPrefix + action + '/' + type;
    }

    function clearCurrentDraft() {
        if (!hasStorage()) return;
        var action = getSelectedAction();
        var type = getSelectedType();
        if (!action || !type) return;
        window.sessionStorage.removeItem(draftKey(action, type));
    }
  
  function showSubtypes(action) {
    document.querySelectorAll('.we-subtypes').forEach(function(el) {
      el.style.display = el.id === 'subtypes-' + action ? 'block' : 'none';
    });
  }

  function showFields(action, type) {
    document.querySelectorAll('.we-fields').forEach(function(el) {
      el.style.display = el.id === 'fields-' + action + '-' + type ? 'block' : 'none';
    });
  }

  function getSelectedAction() {
    var r = form.querySelector('input[name="action"]:checked');
    return r ? r.value : null;
  }

  function getSelectedType() {
    var r = form.querySelector('input[name="type"]:checked');
    return r ? r.value : null;
  }

    function persistDraft() {
        if (!hasStorage()) return;
        var section = currentFieldsSection();
        var action = getSelectedAction();
        var type = getSelectedType();
        if (!section || !action || !type) return;
        var payload = {};
        section.querySelectorAll('input[name], select[name], textarea[name]').forEach(function(field) {
            if (field.type === 'radio' || field.type === 'checkbox') {
                if (field.checked) payload[field.name] = field.value;
                return;
            }
            payload[field.name] = field.value;
        });
        window.sessionStorage.setItem(draftKey(action, type), JSON.stringify(payload));
    }

    function restoreDraft(action, type) {
        if (!hasStorage() || !action || !type) return;
        var section = document.getElementById('fields-' + action + '-' + type);
        if (!section) return;
        var raw = window.sessionStorage.getItem(draftKey(action, type));
        if (!raw) return;
        var payload;
        try {
            payload = JSON.parse(raw);
        } catch (e) {
            return;
        }
        Object.keys(payload || {}).forEach(function(name) {
            var field = section.querySelector('[name="' + name.replace(/"/g, '\\"') + '"]');
            if (!field) return;
            if (field.type === 'radio' || field.type === 'checkbox') {
                field.checked = (field.value === payload[name]);
                return;
            }
            field.value = payload[name];
        });
    }

    function currentFieldsSection() {
        var action = getSelectedAction();
        var type = getSelectedType();
        if (!action || !type) return null;
        return document.getElementById('fields-' + action + '-' + type);
    }

    function clearValidation() {
        if (!validationEl) return;
        validationEl.innerHTML = '';
        validationEl.classList.add('we-hidden');
    }

    function collectValidationErrors(section) {
        var errors = [];
        if (!section) return errors;
        var requiredFields = section.querySelectorAll('input[required], select[required], textarea[required]');
        requiredFields.forEach(function(field) {
            if (!field.checkValidity()) {
                var fieldWrap = field.closest('.we-field');
                var labelEl = fieldWrap ? fieldWrap.querySelector('.we-label') : null;
                var label = i18n.requiredField;
                if (labelEl) {
                    var clonedLabel = labelEl.cloneNode(true);
                    clonedLabel.querySelectorAll('.we-sr-only').forEach(function(el) { el.remove(); });
                    label = clonedLabel.textContent.replace('*', '').trim();
                }
                errors.push(label);
            }
        });
        return errors;
    }

    function showValidationErrors(errors) {
        if (!validationEl) return;
        if (!errors.length) {
            clearValidation();
            return;
        }
        var list = errors.map(function(msg) {
            return '<li>' + msg + '</li>';
        }).join('');
        validationEl.innerHTML = '<strong>' + i18n.validationTitle + '</strong><ul>' + list + '</ul>';
        validationEl.classList.remove('we-hidden');
    }

    function shouldConfirmSensitiveAction(action) {
        return action === 'update' || action === 'approval' || action === 'receive';
    }

    function updateSelectionContext() {
        var action = getSelectedAction();
        var type = getSelectedType();
        var actionInfo = action ? actionMeta[action] : null;
        var typeInfo = (action && type) ? typeMeta[action + '/' + type] : null;

        if (selectedActionEl) {
            selectedActionEl.textContent = actionInfo && actionInfo.label ? actionInfo.label : '';
        }
        if (selectedTypeEl) {
            selectedTypeEl.textContent = typeInfo && typeInfo.label ? typeInfo.label : '';
        }
        if (selectedDescEl) {
            var desc = '';
            if (actionInfo && actionInfo.description) {
                desc += actionInfo.description;
            }
            if (typeInfo && typeInfo.description) {
                desc += (desc !== '' ? ' ' : '') + typeInfo.description;
            }
            selectedDescEl.textContent = desc;
        }
        if (selectedImpactTextEl) {
            selectedImpactTextEl.textContent = typeInfo && typeInfo.impact ? typeInfo.impact : '';
        }
    }

  function syncPillStyles(groupSelector, checkedValue) {
    document.querySelectorAll(groupSelector + ' .we-pill').forEach(function(pill) {
      var inp = pill.querySelector('input');
      if (inp && inp.value === checkedValue) pill.classList.add('active');
      else pill.classList.remove('active');
    });
  }

  // Action selection
  actionPills.forEach(function(pill) {
    pill.addEventListener('click', function() {
      var action = pill.getAttribute('data-action');
      showSubtypes(action);
      // Auto-select first sub-type for this action
      var firstSub = document.querySelector('#subtype-pills-' + action + ' input[type="radio"]');
      if (firstSub) {
        firstSub.checked = true;
        var type = firstSub.value;
        syncPillStyles('#subtype-pills-' + action, type);
        showFields(action, type);
                clearValidation();
                updateSelectionContext();
                                restoreDraft(action, type);
      } else {
        document.querySelectorAll('.we-fields').forEach(function(el) { el.style.display = 'none'; });
      }
    });
  });

  // Type selection (delegate)
  document.querySelectorAll('.we-subtypes').forEach(function(section) {
    section.querySelectorAll('.we-pill').forEach(function(pill) {
      pill.addEventListener('click', function() {
        var action = getSelectedAction();
        var type = pill.getAttribute('data-type');
        if (action) {
          syncPillStyles('#subtype-pills-' + action, type);
          showFields(action, type);
                    clearValidation();
                    updateSelectionContext();
                                        restoreDraft(action, type);
        }
      });
    });
  });

  // On radio change (keyboard/direct)
  form.addEventListener('change', function(e) {
    var inp = e.target;
    if (!inp || inp.tagName !== 'INPUT') return;
    if (inp.name === 'action') {
      syncPillStyles('#action-pills', inp.value);
      showSubtypes(inp.value);
      var firstSub = document.querySelector('#subtype-pills-' + inp.value + ' input[type="radio"]');
      if (firstSub) {
        firstSub.checked = true;
        syncPillStyles('#subtype-pills-' + inp.value, firstSub.value);
        showFields(inp.value, firstSub.value);
                clearValidation();
                                restoreDraft(inp.value, firstSub.value);
      }
            updateSelectionContext();
    }
    if (inp.name === 'type') {
      var action = getSelectedAction();
      if (action) {
        syncPillStyles('#subtype-pills-' + action, inp.value);
        showFields(action, inp.value);
                clearValidation();
                updateSelectionContext();
                                restoreDraft(action, inp.value);
      }
    }
  });

        form.addEventListener('input', persistDraft);
        form.addEventListener('change', persistDraft);

    form.addEventListener('submit', function(e) {
        var action = getSelectedAction();
        var section = currentFieldsSection();
        var errors = collectValidationErrors(section);
        if (errors.length > 0) {
            e.preventDefault();
            showValidationErrors(errors);
            return;
        }

        if (shouldConfirmSensitiveAction(action) && !window.confirm(i18n.confirmSensitive)) {
            e.preventDefault();
            return;
        }

        clearValidation();
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = i18n.submitting;
        }
    });

    updateSelectionContext();
    restoreDraft(getSelectedAction(), getSelectedType());
    if (flashType === 'ok') {
        clearCurrentDraft();
    }
})();
</script>

<?php if (!$isFragmentOnly): ?>
</body>
</html>
<?php endif; ?>
        <?php
        return (string)ob_get_clean();
    }

    /**
     * Load all data needed for form dropdowns
     */
    private function loadPageData(array $actions): array
    {
        $data = [];
        $allTypes = array_merge(...array_values($actions));

        if (in_array('production_entry', $allTypes, true)) {
            $data['products']  = $this->loadProducts();
            $data['machines']  = $this->loadMachines();
        }
        if (in_array('material_consumption', $allTypes, true) || in_array('material', $allTypes, true)) {
            $data['materials'] = $this->loadMaterials();
        }
        if (in_array('production_plan', $allTypes, true)) {
            $data['pending_production_plans'] = $this->loadPendingProductionPlans();
        }
        if (in_array('assembly_plan', $allTypes, true)) {
            $data['pending_assembly_plans'] = $this->loadPendingAssemblyPlans();
        }
        if (in_array('qc_report', $allTypes, true)) {
            $data['pending_qc_entries'] = $this->loadPendingQCEntries();
        }
        if (in_array('dispatch_request', $allTypes, true) || in_array('dispatch_entry', $allTypes, true)) {
            $data['pending_dispatch_entries'] = $this->loadPendingDispatchEntries();
        }
        if (in_array('production_entry', $allTypes, true) && isset($actions['update'])) {
            $data['open_production_entries'] = $this->loadOpenProductionEntries();
        }
        if (in_array('order_status', $allTypes, true)) {
            $data['open_orders'] = $this->loadOpenOrders();
        }
        if (in_array('defect', $allTypes, true) || in_array('waste', $allTypes, true)) {
            if (!isset($data['products'])) {
                $data['products'] = $this->loadProducts();
            }
        }
        if (in_array('machine_issue', $allTypes, true)) {
            if (!isset($data['machines'])) {
                $data['machines'] = $this->loadMachines();
            }
        }

        return $data;
    }

    /**
     * Render form fields for a given action+type combo
     */
    private function renderFieldGroup(string $action, string $type, array $data): void
    {
        $key = $action . '/' . $type;
        switch ($key) {
            case 'add/production_entry':
                $this->fieldsAddProductionEntry($data);
                break;
            case 'add/material_consumption':
                $this->fieldsAddMaterialConsumption($data);
                break;
            case 'add/dispatch_entry':
                $this->fieldsAddDispatchEntry($data);
                break;
            case 'add/attendance_entry':
                $this->fieldsAddAttendanceEntry($data);
                break;
            case 'add/timecard_entry':
                $this->fieldsAddTimecardEntry($data);
                break;
            case 'update/production_entry':
                $this->fieldsUpdateProductionEntry($data);
                break;
            case 'update/plan_status':
                $this->fieldsUpdatePlanStatus($data);
                break;
            case 'update/order_status':
                $this->fieldsUpdateOrderStatus($data);
                break;
            case 'update/dispatch_status':
                $this->fieldsUpdateDispatchStatus($data);
                break;
            case 'approval/production_plan':
                $this->fieldsApproval($data['pending_production_plans'] ?? [], 'plan_id', 'Production Plan', 'plan_date');
                break;
            case 'approval/assembly_plan':
                $this->fieldsApproval($data['pending_assembly_plans'] ?? [], 'plan_id', 'Assembly Plan', 'plan_date');
                break;
            case 'approval/qc_report':
                $this->fieldsApproval($data['pending_qc_entries'] ?? [], 'qc_id', 'QC Report', null, 'part_name');
                break;
            case 'approval/dispatch_request':
                $this->fieldsApproval($data['pending_dispatch_entries'] ?? [], 'dispatch_id', 'Dispatch Entry', 'dispatch_date', 'part_name');
                break;
            case 'receive/material':
                $this->fieldsReceiveMaterial($data);
                break;
            case 'report/defect':
                $this->fieldsReportDefect($data);
                break;
            case 'report/waste':
                $this->fieldsReportWaste($data);
                break;
            case 'report/machine_issue':
                $this->fieldsReportMachineIssue($data);
                break;
            case 'report/delay':
                $this->fieldsReportDelay($data);
                break;
            case 'report/leave_request':
                $this->fieldsReportLeaveRequest($data);
                break;
            default:
                echo '<p class="we-hint">' . htmlspecialchars($this->tr('work_entry.form_unavailable', 'Form not available for this combination.')) . '</p>';
        }
    }

    // ─── Field group renderers ────────────────────────────────────────────────

    private function fieldsAddProductionEntry(array $data): void
    {
        $today = date('Y-m-d');
        ?>
        <div class="we-field-group">
          <?php $this->field('Date', '<input class="input" type="date" name="production_date" value="'.htmlspecialchars($today).'" required>'); ?>
          <?php $this->field('Machine', $this->selectHtml('machine_id', $data['machines'] ?? [], 'id', 'machine_name', '-- Select Machine --', true)); ?>
          <?php $this->field('Part / Product', $this->selectProductHtml('product_id', $data['products'] ?? [])); ?>
          <?php $this->field('Shift', '<select name="shift"><option>Day</option><option>Night</option><option>Morning</option><option>Evening</option></select>'); ?>
          <?php $this->field('Produced Qty', '<input class="input" type="number" name="produced_qty" min="0" step="0.01" placeholder="0" required>'); ?>
          <?php $this->field('Rejected Qty', '<input class="input" type="number" name="rejected_qty" min="0" step="0.01" value="0">'); ?>
          <?php $this->field('Notes', '<textarea name="notes" rows="2" placeholder="Optional notes…"></textarea>'); ?>
        </div>
        <?php
    }

    private function fieldsAddMaterialConsumption(array $data): void
    {
        $today = date('Y-m-d');
        ?>
        <div class="we-field-group">
          <?php $this->field('Date', '<input class="input" type="date" name="entry_date" value="'.htmlspecialchars($today).'" required>'); ?>
          <?php $this->field('Material', $this->selectHtml('material_id', $data['materials'] ?? [], 'id', 'material_name', '-- Select Material --', true)); ?>
          <?php $this->field('Quantity', '<input class="input" type="number" name="qty" min="0.01" step="0.01" placeholder="0.00" required>'); ?>
          <?php $this->field('Notes', '<textarea name="notes" rows="2" placeholder="Optional notes…"></textarea>'); ?>
        </div>
        <?php
    }

    private function fieldsAddDispatchEntry(array $data): void
    {
        $today = date('Y-m-d');
        ?>
        <div class="we-field-group">
          <?php $this->field('Dispatch Date', '<input class="input" type="date" name="dispatch_date" value="'.htmlspecialchars($today).'" required>'); ?>
          <?php $this->field('Part / Product', $this->selectProductHtml('product_id', $data['products'] ?? [])); ?>
          <?php $this->field('Quantity', '<input class="input" type="number" name="dispatchable_qty" min="0.01" step="0.01" placeholder="0.00" required>'); ?>
          <?php $this->field('Destination', '<input class="input" type="text" name="destination" maxlength="190" placeholder="Customer / location">'); ?>
          <?php $this->field('Mode',
            '<select name="dispatch_mode">
              <option value="company_origin_dispatch">Company Origin</option>
              <option value="in_house_dispatch">In-House</option>
              <option value="third_party_dispatch">Third Party</option>
            </select>'
          ); ?>
          <?php $this->field('Remarks', '<textarea name="remarks" rows="2" placeholder="Optional remarks…"></textarea>'); ?>
        </div>
        <?php
    }

        private function fieldsAddAttendanceEntry(array $data): void
        {
                $today = date('Y-m-d');
                ?>
                <div class="we-field-group">
                    <?php $this->field('Date', '<input class="input" type="date" name="entry_date" value="'.htmlspecialchars($today).'" required>'); ?>
                    <?php $this->field('Status',
                        '<select name="attendance_status" required>
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                            <option value="leave">Leave</option>
                        </select>'
                    ); ?>
                    <?php $this->field('Hours Worked', '<input class="input" type="number" name="hours_worked" min="0" max="24" step="0.25" placeholder="0.00">'); ?>
                    <?php $this->field('Notes', '<textarea name="notes" rows="2" placeholder="Optional notes…"></textarea>'); ?>
                </div>
                <?php
        }

        private function fieldsAddTimecardEntry(array $data): void
        {
                $today = date('Y-m-d');
                ?>
                <div class="we-field-group">
                    <?php $this->field('Date', '<input class="input" type="date" name="entry_date" value="'.htmlspecialchars($today).'" required>'); ?>
                    <?php $this->field('Clock In', '<input class="input" type="time" name="clock_in" required>'); ?>
                    <?php $this->field('Clock Out', '<input class="input" type="time" name="clock_out" required>'); ?>
                    <?php $this->field('Break (minutes)', '<input class="input" type="number" name="break_minutes" min="0" max="300" step="5" value="0">'); ?>
                    <?php $this->field('Notes', '<textarea name="notes" rows="2" placeholder="Optional notes…"></textarea>'); ?>
                </div>
                <?php
        }

    private function fieldsUpdateProductionEntry(array $data): void
    {
        ?>
        <div class="we-field-group">
          <?php
          $opts = '';
          foreach ($data['open_production_entries'] ?? [] as $row) {
              $label = htmlspecialchars(sprintf('#%d — %s  %s  (%s)',
                  (int)$row['id'], (string)($row['production_date'] ?? ''),
                  (string)($row['part_name'] ?? ''), (string)($row['status'] ?? '')));
              $opts .= '<option value="'.((int)$row['id']).'">'.$label.'</option>';
          }
          $sel = '<select name="entry_id" required><option value="">-- Select Entry --</option>'.$opts.'</select>';
          $this->field('Production Entry', $sel);
          ?>
          <?php $this->field('Field to Update',
            '<select name="update_field">
              <option value="status">Status</option>
              <option value="notes">Notes</option>
            </select>'
          ); ?>
          <?php $this->field('New Value',
            '<input class="input" type="text" name="update_value" maxlength="190" placeholder="New value…" required>'
          ); ?>
          <?php $this->field('Reason', '<textarea name="reason" rows="2" placeholder="Optional reason…"></textarea>'); ?>
        </div>
        <?php
    }

    private function fieldsUpdatePlanStatus(array $data): void
    {
        $plans = $this->loadPendingProductionPlans();
        $opts  = '';
        foreach ($plans as $row) {
            $label = htmlspecialchars(sprintf('#%d — %s  %s', (int)$row['id'],
                (string)($row['plan_date'] ?? ''), (string)($row['part_name'] ?? '')));
            $opts .= '<option value="'.((int)$row['id']).'">'.$label.'</option>';
        }
        ?>
        <div class="we-field-group">
          <?php $this->field('Production Plan',
            '<select name="plan_id" required><option value="">-- Select Plan --</option>'.$opts.'</select>'
          ); ?>
          <?php $this->field('New Status',
            '<select name="update_value">
              <option value="Approved">Approved</option>
              <option value="Pending">Pending</option>
              <option value="Rejected">Rejected</option>
            </select>'
          ); ?>
          <?php $this->field('Reason', '<textarea name="reason" rows="2"></textarea>'); ?>
        </div>
        <?php
    }

    private function fieldsUpdateOrderStatus(array $data): void
    {
        $opts = '';
        foreach ($data['open_orders'] ?? [] as $row) {
            $label = htmlspecialchars(sprintf('#%s — %s (%s)',
                (string)($row['id']),
                (string)($row['order_date'] ?? ''),
                (string)($row['status'] ?? '')));
            $opts .= '<option value="'.((int)$row['id']).'">'.$label.'</option>';
        }
        ?>
        <div class="we-field-group">
          <?php $this->field('Order',
            '<select name="order_id" required><option value="">-- Select Order --</option>'.$opts.'</select>'
          ); ?>
          <?php $this->field('New Status',
            '<select name="update_value">
              <option value="open">Open</option>
              <option value="closed">Closed</option>
              <option value="on_hold">On Hold</option>
              <option value="cancelled">Cancelled</option>
            </select>'
          ); ?>
          <?php $this->field('Reason', '<textarea name="reason" rows="2"></textarea>'); ?>
        </div>
        <?php
    }

    private function fieldsUpdateDispatchStatus(array $data): void
    {
        $entries = $this->loadPendingDispatchEntries();
        $opts    = '';
        foreach ($entries as $row) {
            $label = htmlspecialchars(sprintf('#%d — %s  %s',
                (int)$row['id'], (string)($row['dispatch_date'] ?? ''), (string)($row['part_name'] ?? '')));
            $opts .= '<option value="'.((int)$row['id']).'">'.$label.'</option>';
        }
        ?>
        <div class="we-field-group">
          <?php $this->field('Dispatch Entry',
            '<select name="dispatch_id" required><option value="">-- Select Entry --</option>'.$opts.'</select>'
          ); ?>
          <?php $this->field('New Status',
            '<select name="update_value">
              <option value="Ready">Ready</option>
              <option value="Hold">Hold</option>
              <option value="Dispatched">Dispatched</option>
              <option value="Cancelled">Cancelled</option>
            </select>'
          ); ?>
          <?php $this->field('Reason', '<textarea name="reason" rows="2"></textarea>'); ?>
        </div>
        <?php
    }

    /** Generic approval form — works for plans, QC, dispatch */
    private function fieldsApproval(array $items, string $idField, string $label, ?string $dateField, string $nameField = 'plan_date'): void
    {
        $opts = '';
        foreach ($items as $row) {
            $date = $dateField ? htmlspecialchars((string)($row[$dateField] ?? '')) : '';
            $name = htmlspecialchars((string)($row[$nameField] ?? (string)($row['part_name'] ?? '')));
            $display = $date ? '#'.(int)$row['id'].' — '.$date.'  '.$name : '#'.(int)$row['id'].' — '.$name;
            $opts .= '<option value="'.((int)$row['id']).'">'.$display.'</option>';
        }
        if ($opts === '') {
            $opts = '<option value="" disabled>No pending items</option>';
        }
        ?>
        <div class="we-field-group">
          <?php $this->field($label,
            '<select name="'.$idField.'" required><option value="">-- Select '.$label.' --</option>'.$opts.'</select>'
          ); ?>
          <?php $this->field('Decision',
            '<select name="decision" required>
              <option value="Approved">Approve</option>
              <option value="Rejected">Reject</option>
              <option value="Hold">Hold</option>
            </select>'
          ); ?>
          <?php $this->field('Notes', '<textarea name="notes" rows="2" placeholder="Optional notes…"></textarea>'); ?>
        </div>
        <?php
    }

    private function fieldsReceiveMaterial(array $data): void
    {
        ?>
        <div class="we-field-group">
          <?php $this->field('Material', $this->selectHtml('material_id', $data['materials'] ?? [], 'id', 'material_name', '-- Select Material --', true)); ?>
          <?php $this->field('Quantity Received', '<input class="input" type="number" name="qty" min="0.01" step="0.01" placeholder="0.00" required>'); ?>
          <?php $this->field('PO / Reference', '<input class="input" type="text" name="po_ref" maxlength="120" placeholder="Purchase order or delivery note #">'); ?>
          <?php $this->field('Condition Note', '<input class="input" type="text" name="condition_note" maxlength="190" placeholder="Good / Damaged / Partial…">'); ?>
          <?php $this->field('Notes', '<textarea name="notes" rows="2"></textarea>'); ?>
        </div>
        <?php
    }

    private function fieldsReportDefect(array $data): void
    {
        ?>
        <div class="we-field-group">
          <?php $this->field('Part / Product', $this->selectProductHtml('product_id', $data['products'] ?? [])); ?>
          <?php $this->field('Defect Type',
            '<select name="defect_type">
              <option value="Visual">Visual</option>
              <option value="Dimensional">Dimensional</option>
              <option value="Functional">Functional</option>
              <option value="Assembly">Assembly</option>
              <option value="Other">Other</option>
            </select>'
          ); ?>
          <?php $this->field('Qty Affected', '<input class="input" type="number" name="fail_qty" min="1" step="1" placeholder="0" required>'); ?>
          <?php $this->field('Total Checked', '<input class="input" type="number" name="checked_qty" min="1" step="1" placeholder="0" required>'); ?>
          <?php $this->field('Severity',
            '<select name="severity">
              <option value="Low">Low</option>
              <option value="Medium">Medium</option>
              <option value="High">High</option>
              <option value="Critical">Critical</option>
            </select>'
          ); ?>
          <?php $this->field('Description', '<textarea name="description" rows="3" placeholder="Describe the defect…" required></textarea>'); ?>
        </div>
        <?php
    }

    private function fieldsReportWaste(array $data): void
    {
        ?>
        <div class="we-field-group">
          <?php $this->field('Part / Product', $this->selectProductHtml('product_id', $data['products'] ?? [])); ?>
          <?php $this->field('Waste Qty', '<input class="input" type="number" name="waste_qty" min="0.01" step="0.01" placeholder="0.00" required>'); ?>
          <?php $this->field('Reason Code',
            '<select name="reason_code">
              <option value="Process Defect">Process Defect</option>
              <option value="Material Defect">Material Defect</option>
              <option value="Machine Fault">Machine Fault</option>
              <option value="Operator Error">Operator Error</option>
              <option value="Setup Loss">Setup Loss</option>
              <option value="Other">Other</option>
            </select>'
          ); ?>
          <?php $this->field('Notes', '<textarea name="notes" rows="2" placeholder="Additional context…"></textarea>'); ?>
        </div>
        <?php
    }

    private function fieldsReportMachineIssue(array $data): void
    {
        ?>
        <div class="we-field-group">
          <?php $this->field('Machine', $this->selectHtml('machine_id', $data['machines'] ?? [], 'id', 'machine_name', '-- Select Machine --', true)); ?>
          <?php $this->field('Issue Type',
            '<select name="issue_type">
              <option value="Breakdown">Breakdown</option>
              <option value="Slowdown">Slowdown</option>
              <option value="Vibration">Vibration</option>
              <option value="Noise">Noise</option>
              <option value="Electrical">Electrical</option>
              <option value="Tooling">Tooling</option>
              <option value="Other">Other</option>
            </select>'
          ); ?>
          <?php $this->field('Urgency',
            '<select name="urgency">
              <option value="Low">Low</option>
              <option value="Normal">Normal</option>
              <option value="High">High</option>
              <option value="Critical">Critical — Stop machine</option>
            </select>'
          ); ?>
          <?php $this->field('Description', '<textarea name="description" rows="3" placeholder="Describe the issue…" required></textarea>'); ?>
        </div>
        <?php
    }

    private function fieldsReportDelay(array $data): void
    {
        $today = date('Y-m-d');
        ?>
        <div class="we-field-group">
          <?php $this->field('Work Order / Reference', '<input class="input" type="text" name="work_ref" maxlength="120" placeholder="Order # or plan reference" required>'); ?>
          <?php $this->field('Reason',
            '<select name="delay_reason">
              <option value="Material Shortage">Material Shortage</option>
              <option value="Machine Downtime">Machine Downtime</option>
              <option value="Staff Absence">Staff Absence</option>
              <option value="Quality Issue">Quality Issue</option>
              <option value="Customer Change">Customer Change</option>
              <option value="Other">Other</option>
            </select>'
          ); ?>
          <?php $this->field('Details', '<textarea name="description" rows="2" placeholder="Additional details…"></textarea>'); ?>
          <?php $this->field('Expected Resolution', '<input class="input" type="date" name="expected_resolution" value="'.htmlspecialchars($today).'">'); ?>
        </div>
        <?php
    }

        private function fieldsReportLeaveRequest(array $data): void
        {
                $today = date('Y-m-d');
                ?>
                <div class="we-field-group">
                    <?php $this->field('Start Date', '<input class="input" type="date" name="start_date" value="'.htmlspecialchars($today).'" required>'); ?>
                    <?php $this->field('End Date', '<input class="input" type="date" name="end_date" value="'.htmlspecialchars($today).'" required>'); ?>
                    <?php $this->field('Leave Type',
                        '<select name="leave_type" required>
                            <option value="annual">Annual</option>
                            <option value="sick">Sick</option>
                            <option value="casual">Casual</option>
                            <option value="unpaid">Unpaid</option>
                        </select>'
                    ); ?>
                    <?php $this->field('Reason', '<textarea name="description" rows="3" placeholder="Describe the leave request…" required></textarea>'); ?>
                </div>
                <?php
        }

    // ─── HTML helpers ─────────────────────────────────────────────────────────

    private function field(string $label, string $controlHtml): void
    {
        $isRequired = stripos($controlHtml, ' required') !== false;
        $requiredHtml = $isRequired
            ? ' <span class="we-required" aria-hidden="true">*</span><span class="we-sr-only">'.$this->tr('work_entry.validation.required_field', 'Required field').'</span>'
            : '';
        echo '<div class="we-field"><label class="we-label">'.htmlspecialchars($label).$requiredHtml.'</label><div class="we-control">'.$controlHtml.'</div></div>';
    }

    private function selectHtml(string $name, array $rows, string $valKey, string $labelKey, string $placeholder, bool $required = false): string
    {
        $req = $required ? ' required' : '';
        $html = '<select name="'.$name.'"'.$req.'><option value="">'.$placeholder.'</option>';
        foreach ($rows as $row) {
            $html .= '<option value="'.((int)$row[$valKey]).'">'.htmlspecialchars((string)($row[$labelKey] ?? '')).'</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private function selectProductHtml(string $name, array $products): string
    {
        $html = '<select name="'.$name.'" required><option value="">-- Select Part / Product --</option>';
        foreach ($products as $p) {
            $label = trim((string)($p['parts_name'] ?? ''));
            $num   = trim((string)($p['parts_number'] ?? ''));
            $display = $label !== '' ? ($num !== '' ? $label.' ('.$num.')' : $label) : 'Part #'.(int)$p['id'];
            $html .= '<option value="'.((int)$p['id']).'">'.htmlspecialchars($display).'</option>';
        }
        $html .= '</select>';
        return $html;
    }

    // ─── Flash helper ─────────────────────────────────────────────────────────

    private static function setFlash(string $type, string $msg): void
    {
        Auth::bootSession();
        $_SESSION['we_flash'] = ['type' => $type, 'msg' => $msg];
    }

    private function pullFlash(): ?array
    {
        Auth::bootSession();
        if (!empty($_SESSION['we_flash'])) {
            $f = $_SESSION['we_flash'];
            unset($_SESSION['we_flash']);
            return $f;
        }
        return null;
    }

    // ─── POST handler ─────────────────────────────────────────────────────────

    public static function handlePost(string $username, array $context, array $post = []): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            header('Location: /login', true, 302);
            exit;
        }
        Auth::requireCsrf((string)($post['csrf'] ?? ''));

        $composer = new self($username, $context);
        $allowed  = $composer->allowedActions();
        $action   = trim((string)($post['action'] ?? ''));
        $type     = trim((string)($post['type'] ?? ''));
        $uenc     = rawurlencode($username);

        // Server-side role gate
        if (!isset($allowed[$action]) || !in_array($type, $allowed[$action], true)) {
            self::setFlash('err', $composer->tr('work_entry.flash.not_permitted', 'Action not permitted for your role.'));
            header('Location: /u/'.$uenc.'/work-entry', true, 302);
            exit;
        }

        try {
            $userId = (int)($context['user_id'] ?? 0);
            $key    = $action . '/' . $type;

            switch ($key) {
                case 'add/production_entry':
                    $composer->saveProductionEntry($post, $userId);
                    break;
                case 'add/material_consumption':
                    $composer->saveMaterialConsumption($post, $userId);
                    break;
                case 'add/dispatch_entry':
                    $composer->saveDispatchEntry($post, $userId);
                    break;
                case 'add/attendance_entry':
                    $composer->saveSbaioAuditEntry($post, $userId, 'attendance_entry');
                    break;
                case 'add/timecard_entry':
                    $composer->saveSbaioAuditEntry($post, $userId, 'timecard_entry');
                    break;
                case 'update/production_entry':
                    $composer->updateProductionEntry($post, $userId);
                    break;
                case 'update/plan_status':
                    $composer->updatePlanStatus($post, $userId);
                    break;
                case 'update/order_status':
                    $composer->updateOrderStatus($post, $userId);
                    break;
                case 'update/dispatch_status':
                    $composer->updateDispatchStatus($post, $userId);
                    break;
                case 'approval/production_plan':
                    $composer->saveApproval('production_plans', 'plan_id', $post, $userId);
                    break;
                case 'approval/assembly_plan':
                    $composer->saveApproval('assembly_plans', 'plan_id', $post, $userId);
                    break;
                case 'approval/qc_report':
                    $composer->saveApproval('qc_entries', 'qc_id', $post, $userId);
                    break;
                case 'approval/dispatch_request':
                    $composer->saveApproval('dispatch_entries', 'dispatch_id', $post, $userId);
                    break;
                case 'receive/material':
                    $composer->saveReceiveMaterial($post, $userId);
                    break;
                case 'report/defect':
                    $composer->saveReportDefect($post, $userId);
                    break;
                case 'report/waste':
                    $composer->saveReportWaste($post, $userId);
                    break;
                case 'report/machine_issue':
                    $composer->saveReportAudit($post, $userId, 'machine_issue');
                    break;
                case 'report/delay':
                    $composer->saveReportAudit($post, $userId, 'delay_report');
                    break;
                case 'report/leave_request':
                    $composer->saveSbaioAuditEntry($post, $userId, 'leave_request');
                    break;
                default:
                    throw new \InvalidArgumentException('Unsupported work entry type.');
            }

            self::setFlash('ok', $composer->tr('work_entry.flash.submitted', 'Entry submitted successfully.'));
        } catch (\Throwable $e) {
            self::setFlash('err', $composer->tr('work_entry.flash.save_failed', 'Could not save: {message}', ['message' => $e->getMessage()]));
            header('Location: /u/'.$uenc.'/work-entry?action='.$action.'&type='.$type, true, 302);
            exit;
        }

        header('Location: /u/'.$uenc.'/work-entry?action='.$action.'&type='.$type, true, 302);
        exit;
    }

    // ─── Save methods ─────────────────────────────────────────────────────────

    private function saveProductionEntry(array $p, int $userId): void
    {
        $date     = $this->safeDate((string)($p['production_date'] ?? ''));
        $machineId= (int)($p['machine_id'] ?? 0);
        $productId= (int)($p['product_id'] ?? 0);
        $shift    = $this->safeEnum((string)($p['shift'] ?? 'Day'), ['Day','Night','Morning','Evening'], 'Day');
        $produced = max(0.0, (float)($p['produced_qty'] ?? 0));
        $rejected = max(0.0, (float)($p['rejected_qty'] ?? 0));
        $goodQty  = max(0.0, $produced - $rejected);
        $notes    = $this->safeText((string)($p['notes'] ?? ''), 2000);

        if ($date === '' || $machineId <= 0 || $productId <= 0) {
            throw new \InvalidArgumentException('Date, machine, and product are required.');
        }
        if ($produced <= 0) {
            throw new \InvalidArgumentException($this->tr('work_entry.error.produced_qty_required', 'Produced quantity must be greater than zero.'));
        }
        if ($rejected > $produced) {
            throw new \InvalidArgumentException($this->tr('work_entry.error.rejected_qty_invalid', 'Rejected quantity cannot exceed produced quantity.'));
        }

        DB::query(
            'INSERT INTO production_entries (production_date, shift, machine_id, product_id, produced_qty, rejected_qty, good_qty, status, notes)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$date, $shift, $machineId, $productId, $produced, $rejected, $goodQty, 'Draft', $notes]
        );
    }

    private function saveMaterialConsumption(array $p, int $userId): void
    {
        $materialId = (int)($p['material_id'] ?? 0);
        $qty        = (float)($p['qty'] ?? 0);
        $notes      = $this->safeText((string)($p['notes'] ?? ''), 1000);
        $date       = $this->safeDate((string)($p['entry_date'] ?? date('Y-m-d')));

        if ($materialId <= 0 || $qty <= 0) {
            throw new \InvalidArgumentException('Material and quantity are required.');
        }

        DB::query(
            'INSERT INTO material_ledger (material_id, movement_type, qty_delta, reserved_delta, reference_type, notes, updated_by)
             VALUES (?,?,?,0,?,?,?)',
            [$materialId, 'CONSUME', -abs($qty), 'work_entry_consume', $notes, $userId]
        );
    }

    private function saveDispatchEntry(array $p, int $userId): void
    {
        $dispatchDate = $this->safeDate((string)($p['dispatch_date'] ?? date('Y-m-d')));
        $productId    = (int)($p['product_id'] ?? 0);
        $qty          = max(0.0, (float)($p['dispatchable_qty'] ?? 0));
        $destination  = $this->safeText((string)($p['destination'] ?? ''), 190);
        $mode         = $this->safeEnum(
            (string)($p['dispatch_mode'] ?? 'company_origin_dispatch'),
            ['company_origin_dispatch','in_house_dispatch','third_party_dispatch','third_party_direct_dispatch','third_party_to_company_then_destination'],
            'company_origin_dispatch'
        );
        $remarks = $this->safeText((string)($p['remarks'] ?? ''), 2000);

        if ($productId <= 0 || $qty <= 0) {
            throw new \InvalidArgumentException('Product and quantity are required.');
        }

        DB::query(
            'INSERT INTO dispatch_entries (dispatch_date, product_id, dispatchable_qty, destination, dispatch_mode, delivery_flow, source_type, remarks, completion_status, approval_status, dispatch_status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$dispatchDate, $productId, $qty, $destination, $mode, 'company_to_destination', 'in_house', $remarks, 'draft', 'Draft', 'Ready']
        );
    }

    private function updateProductionEntry(array $p, int $userId): void
    {
        $entryId = (int)($p['entry_id'] ?? 0);
        $field   = $this->safeEnum((string)($p['update_field'] ?? ''), ['status','notes'], 'notes');
        $value   = $this->safeText((string)($p['update_value'] ?? ''), 190);

        if ($entryId <= 0) {
            throw new \InvalidArgumentException('Entry is required.');
        }

        $this->assertRecordExists(
            'production_entries',
            $entryId,
            $this->tr('work_entry.error.production_entry_unavailable', 'Production entry is not available.')
        );

        if ($field === 'status') {
            $value = $this->requireEnum(
                $value,
                ['Draft', 'In Progress', 'Completed', 'On Hold', 'Cancelled'],
                $this->tr('work_entry.error.invalid_status', 'Invalid status value.')
            );
            DB::query('UPDATE production_entries SET status=?, updated_at=NOW() WHERE id=?', [$value, $entryId]);
        } else {
            if ($value === '') {
                throw new \InvalidArgumentException($this->tr('work_entry.error.new_value_required', 'New value is required.'));
            }
            DB::query('UPDATE production_entries SET notes=?, updated_at=NOW() WHERE id=?', [$value, $entryId]);
        }
    }

    private function updatePlanStatus(array $p, int $userId): void
    {
        $planId = (int)($p['plan_id'] ?? 0);
        $status = $this->requireEnum(
            (string)($p['update_value'] ?? ''),
            ['Approved','Pending','Rejected'],
            $this->tr('work_entry.error.invalid_status', 'Invalid status value.')
        );
        $reason = $this->safeText((string)($p['reason'] ?? ''), 1000);
        if ($planId <= 0) throw new \InvalidArgumentException('Plan is required.');
        $this->assertRecordExists(
            'production_plans',
            $planId,
            $this->tr('work_entry.error.production_plan_unavailable', 'Production plan is not available.')
        );
        DB::query('UPDATE production_plans SET approval_status=?, approval_note=?, approved_by=?, approved_at=NOW() WHERE id=?',
            [$status, $reason, $userId, $planId]);
    }

    private function updateOrderStatus(array $p, int $userId): void
    {
        $orderId = (int)($p['order_id'] ?? 0);
        $status  = $this->requireEnum(
            (string)($p['update_value'] ?? ''),
            ['open', 'closed', 'on_hold', 'cancelled'],
            $this->tr('work_entry.error.invalid_status', 'Invalid status value.')
        );
        if ($orderId <= 0) throw new \InvalidArgumentException('Order is required.');
        $this->assertRecordExists('daily_orders', $orderId, $this->tr('work_entry.error.order_unavailable', 'Order is not available.'));
        DB::query('UPDATE daily_orders SET status=? WHERE id=?', [$status, $orderId]);
    }

    private function updateDispatchStatus(array $p, int $userId): void
    {
        $id     = (int)($p['dispatch_id'] ?? 0);
        $status = $this->requireEnum(
            (string)($p['update_value'] ?? ''),
            ['Ready', 'Hold', 'Dispatched', 'Cancelled'],
            $this->tr('work_entry.error.invalid_status', 'Invalid status value.')
        );
        if ($id <= 0) throw new \InvalidArgumentException('Dispatch entry is required.');
        $this->assertRecordExists('dispatch_entries', $id, $this->tr('work_entry.error.dispatch_entry_unavailable', 'Dispatch entry is not available.'));
        DB::query('UPDATE dispatch_entries SET dispatch_status=?, status_updated_by=? WHERE id=?', [$status, $userId, $id]);
    }

    private function saveApproval(string $table, string $idField, array $p, int $userId): void
    {
        $id       = (int)($p[$idField] ?? 0);
        $decision = $this->requireEnum(
            (string)($p['decision'] ?? ''),
            ['Approved','Rejected','Hold'],
            $this->tr('work_entry.error.invalid_decision', 'Invalid approval decision.')
        );
        $notes    = $this->safeText((string)($p['notes'] ?? ''), 1000);

        if ($id <= 0) {
            throw new \InvalidArgumentException('Record selection is required.');
        }

        $this->assertRecordExists($table, $id, $this->tr('work_entry.error.record_unavailable', 'Selected record is not available.'));

        $pending = DB::fetchOne("SELECT id FROM `$table` WHERE id=? AND approval_status IN ('Draft','Pending','Open','draft','pending','open')", [$id]);
        if (!is_array($pending)) {
            throw new \InvalidArgumentException($this->tr('work_entry.error.record_not_pending', 'Selected record is no longer pending approval.'));
        }

        DB::query(
            "UPDATE `$table` SET approval_status=?, approval_note=?, approved_by=?, approved_at=NOW() WHERE id=?",
            [$decision, $notes, $userId, $id]
        );
    }

    private function saveReceiveMaterial(array $p, int $userId): void
    {
        $materialId = (int)($p['material_id'] ?? 0);
        $qty        = (float)($p['qty'] ?? 0);
        $poRef      = $this->safeText((string)($p['po_ref'] ?? ''), 120);
        $notes      = $this->safeText((string)($p['notes'] ?? ''), 1000);

        if ($materialId <= 0 || $qty <= 0) {
            throw new \InvalidArgumentException('Material and quantity are required.');
        }

        DB::query(
            'INSERT INTO material_ledger (material_id, movement_type, qty_delta, reserved_delta, ledger_reference, reference_type, notes, updated_by)
             VALUES (?,?,?,0,?,?,?,?)',
            [$materialId, 'RECEIPT', abs($qty), $poRef, 'receipt', $notes, $userId]
        );
    }

    private function saveReportDefect(array $p, int $userId): void
    {
        $productId   = (int)($p['product_id'] ?? 0);
        $failQty     = max(0.0, (float)($p['fail_qty'] ?? 0));
        $checkedQty  = max($failQty, (float)($p['checked_qty'] ?? $failQty));
        $defectType  = $this->safeText((string)($p['defect_type'] ?? 'Other'), 40);
        $severity    = $this->safeText((string)($p['severity'] ?? 'Medium'), 40);
        $description = $this->safeText((string)($p['description'] ?? ''), 2000);

        if ($productId <= 0 || $failQty <= 0) {
            throw new \InvalidArgumentException('Product and qty affected are required.');
        }

        $remarks = '[' . $defectType . ' / ' . $severity . '] ' . $description;
        DB::query(
            'INSERT INTO qc_entries (product_id, qc_type, checked_qty, pass_qty, fail_qty, status, remarks, approval_status)
             VALUES (?,?,?,?,?,?,?,?)',
            [$productId, 'Defect', $checkedQty, max(0.0, $checkedQty - $failQty), $failQty, 'Open', $remarks, 'Draft']
        );
    }

    private function saveReportWaste(array $p, int $userId): void
    {
        $productId  = (int)($p['product_id'] ?? 0);
        $wasteQty   = max(0.0, (float)($p['waste_qty'] ?? 0));
        $reasonCode = $this->safeText((string)($p['reason_code'] ?? 'Other'), 60);
        $notes      = $this->safeText((string)($p['notes'] ?? ''), 1000);

        if ($productId <= 0 || $wasteQty <= 0) {
            throw new \InvalidArgumentException('Product and waste qty are required.');
        }

        // Record as a production entry with zero production and waste qty as rejected
        DB::query(
            'INSERT INTO production_entries (production_date, shift, machine_id, product_id, produced_qty, rejected_qty, good_qty, status, notes)
             VALUES (?,?,0,?,0,?,0,?,?)',
            [date('Y-m-d'), 'Day', $productId, $wasteQty, 'Waste', '[WASTE] '.$reasonCode.($notes !== '' ? ': '.$notes : '')]
        );
    }

    private function saveReportAudit(array $p, int $userId, string $entityType): void
    {
        $machineId   = (int)($p['machine_id'] ?? 0);
        $issueType   = $this->safeText((string)($p['issue_type'] ?? $p['delay_reason'] ?? ''), 60);
        $urgency     = $this->safeText((string)($p['urgency'] ?? ''), 40);
        $description = $this->safeText((string)($p['description'] ?? $p['work_ref'] ?? ''), 2000);
        $workRef     = $this->safeText((string)($p['work_ref'] ?? ''), 120);
        $resDate     = $this->safeDate((string)($p['expected_resolution'] ?? ''));

        $meta = json_encode([
            'issue_type'          => $issueType,
            'urgency'             => $urgency,
            'machine_id'          => $machineId ?: null,
            'work_ref'            => $workRef,
            'expected_resolution' => $resDate,
        ], JSON_UNESCAPED_UNICODE);

        DB::query(
            'INSERT INTO audit_activity_log (entity_type, entity_id, event_type, action_name, actor_user_id, app_key, module_key, note_text, metadata_json)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$entityType, $machineId > 0 ? $machineId : 0, 'lifecycle', 'reported', $userId, 'manufacturing', 'work_entry', $description, $meta]
        );
    }

    private function saveSbaioAuditEntry(array $p, int $userId, string $entryType): void
    {
        $date = $this->safeDate((string)($p['entry_date'] ?? date('Y-m-d')));
        $description = $this->safeText((string)($p['description'] ?? $p['notes'] ?? ''), 2000);
        $clockIn = $this->safeText((string)($p['clock_in'] ?? ''), 10);
        $clockOut = $this->safeText((string)($p['clock_out'] ?? ''), 10);
        $leaveType = $this->safeText((string)($p['leave_type'] ?? ''), 40);
        $attendanceStatus = $this->safeText((string)($p['attendance_status'] ?? ''), 40);
        $hoursWorked = (float)($p['hours_worked'] ?? 0);
        $breakMinutes = (int)($p['break_minutes'] ?? 0);
        $startDate = $this->safeDate((string)($p['start_date'] ?? ''));
        $endDate = $this->safeDate((string)($p['end_date'] ?? ''));

        if ($entryType === 'leave_request') {
            if ($startDate === '' || $endDate === '' || $leaveType === '' || $description === '') {
                throw new \InvalidArgumentException('Leave request details are required.');
            }
        }

        if ($entryType === 'attendance_entry' && $attendanceStatus === '') {
            throw new \InvalidArgumentException('Attendance status is required.');
        }

        if ($entryType === 'timecard_entry' && ($clockIn === '' || $clockOut === '')) {
            throw new \InvalidArgumentException('Clock in and clock out are required.');
        }

        $meta = json_encode([
            'entry_type' => $entryType,
            'entry_date' => $date,
            'attendance_status' => $attendanceStatus,
            'hours_worked' => $hoursWorked,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'break_minutes' => $breakMinutes,
            'leave_type' => $leaveType,
            'leave_start_date' => $startDate,
            'leave_end_date' => $endDate,
        ], JSON_UNESCAPED_UNICODE);

        DB::query(
            'INSERT INTO audit_activity_log (entity_type, entity_id, event_type, action_name, actor_user_id, app_key, module_key, note_text, metadata_json)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$entryType, 0, 'lifecycle', 'reported', $userId, 'sbaio', 'work_entry', $description, $meta]
        );
    }

    // ─── Input sanitizers ────────────────────────────────────────────────────

    private function safeDate(string $v): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    }

    private function safeText(string $v, int $max): string
    {
        return substr(trim(strip_tags($v)), 0, $max);
    }

    private function safeEnum(string $v, array $allowed, string $default): string
    {
        return in_array($v, $allowed, true) ? $v : $default;
    }

    private function requireEnum(string $v, array $allowed, string $errorMessage): string
    {
        if (in_array($v, $allowed, true)) {
            return $v;
        }
        throw new \InvalidArgumentException($errorMessage);
    }

    private function assertRecordExists(string $table, int $id, string $message): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException($message);
        }

        $row = DB::fetchOne("SELECT id FROM `$table` WHERE id=? LIMIT 1", [$id]);
        if (!is_array($row)) {
            throw new \InvalidArgumentException($message);
        }
    }

    // ─── CSS extracted to /public/assets/work-entry.css ─────────────────────

}
