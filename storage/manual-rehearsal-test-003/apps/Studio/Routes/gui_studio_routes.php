<?php
declare(strict_types=1);

use Apps\Studio\Services\GuiStudioService;
use Apps\Studio\Services\AppStudioRegistryService;
use Apps\Studio\Services\StudioViewIntrospectionService;
use Apps\Studio\Services\StudioGovernanceService;
use Apps\Studio\Services\StudioExperienceGovernanceService;
use Apps\Studio\Services\StudioDataContractService;
use Apps\Studio\Services\StudioDependencyGraphService;
use Apps\Studio\Services\StudioRouteLinkingService;
use Apps\Studio\Services\StudioNavLinkingService;
use Apps\Studio\Services\StudioNavCandidateProviderService;
use Apps\Studio\Services\StudioGovernedToolRegistryService;
use Apps\Studio\Services\StudioResourceTypeRegistryService;
use Apps\Studio\Repositories\StudioSchemaGovernanceService;

require_once APP_ROOT . '/apps/Platform/bootstrap.php';
require_once APP_ROOT . '/apps/Studio/Services/GuiStudioService.php';
require_once APP_ROOT . '/apps/Studio/Services/AppStudioRegistryService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioViewIntrospectionService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioGovernanceService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioExperienceGovernanceService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioRuntimeBindingService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDataContractService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioDependencyGraphService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioRouteLinkingService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioNavLinkingService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioNavCandidateProviderService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioGovernedToolRegistryService.php';
require_once APP_ROOT . '/apps/Studio/Services/StudioResourceTypeRegistryService.php';
require_once APP_ROOT . '/apps/Studio/Repositories/StudioSchemaGovernanceService.php';

if (!function_exists('studio_register_gui_studio_routes')) {
    function studio_register_gui_studio_routes($router, $view, string $basePath): void
    {
        $basePath = rtrim('/' . ltrim($basePath, '/'), '/') ?: '/apps/studio';
$buildStructuredChangeContext = static function (array $post, array $payload): array {
    $currentBundle = GuiStudioService::decodeStructuredBundlePayload($payload);
    $previousBundle = GuiStudioService::resolveStructuredPreviousBundle($post, $currentBundle);
    $flowGuard = GuiStudioService::validateStudioFlowMode($post);
    $changeIntelligence = GuiStudioService::computeStructuredDiff($previousBundle, $currentBundle);
    $dependencyGraph = GuiStudioService::buildDependencyGraph($currentBundle);
    $impactAnalysis = GuiStudioService::computeImpact($changeIntelligence, $dependencyGraph);
    $impactGuard = GuiStudioService::validateImpactAcknowledgment($impactAnalysis, [
        'impact_confirmation' => !empty($post['impact_confirmation']),
        'impact_acknowledged' => !empty($post['impact_acknowledged']),
    ]);
    $simulationPreview = GuiStudioService::simulateFutureState($previousBundle, $currentBundle, $changeIntelligence);
    $simulationGuard = GuiStudioService::validateSimulationOverride($simulationPreview, [
        'simulation_override' => !empty($post['simulation_override']),
        'simulation_override_reason' => (string)($post['simulation_override_reason'] ?? ''),
    ]);
    $migrationPlan = GuiStudioService::buildMigrationPlan($changeIntelligence);
    $migrationGuard = GuiStudioService::validateMigrationPlanOverride($migrationPlan, [
        'migration_override' => !empty($post['migration_override']),
        'migration_override_reason' => (string)($post['migration_override_reason'] ?? ''),
    ]);
    $riskEscalation = GuiStudioService::buildStructuredRiskEscalation($changeIntelligence, [
        'reason' => (string)($post['reason'] ?? ''),
        'risk_acknowledged' => !empty($post['risk_acknowledged']),
    ]);
    return [
        'previous_bundle' => $previousBundle,
        'current_bundle' => $currentBundle,
        'flow_guard' => $flowGuard,
        'dependency_graph' => $dependencyGraph,
        'impact_analysis' => $impactAnalysis,
        'impact_guard' => $impactGuard,
        'simulation_preview' => $simulationPreview,
        'simulation_guard' => $simulationGuard,
        'change_intelligence' => $changeIntelligence,
        'migration_plan' => $migrationPlan,
        'migration_guard' => $migrationGuard,
        'risk_escalation' => $riskEscalation,
    ];
};

$buildPipelineAnalysisFingerprint = static function (array $decoded, array $changeContext): string {
    $material = [
        'decoded' => $decoded,
        'impact_analysis' => (array)($changeContext['impact_analysis'] ?? []),
        'dependency_graph' => (array)($changeContext['dependency_graph'] ?? []),
    ];

    $encoded = json_encode($material, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return '';
    }

    return 'analysis:' . substr(sha1($encoded), 0, 20);
};

$buildGlobalLibraryContext = static function (array $query) use ($router): array {
    $library = AppStudioRegistryService::buildGlobalLibrary($router->listRoutes());
    $selectedId = trim((string)($query['library_item'] ?? ''));
    return [
        'library' => $library,
        'selected' => AppStudioRegistryService::findNodeById($library, $selectedId),
        'selected_id' => $selectedId,
    ];
};

$buildStudioDataContractContext = static function (array $inputs, ?array $bundle = null, array $importModel = []): array {
    $resolvedBundle = is_array($bundle) ? $bundle : GuiStudioService::decodeStructuredBundlePayload($inputs);
    return StudioDataContractService::extract($resolvedBundle, $importModel);
};

$buildStudioDependencyGraphContext = static function (array $inputs, array $dataContract, ?array $bundle = null): array {
    $resolvedBundle = is_array($bundle) ? $bundle : GuiStudioService::decodeStructuredBundlePayload($inputs);
    return StudioDependencyGraphService::build($resolvedBundle, $dataContract);
};

$supportedLibraryArtifactTypes = ['app', 'module', 'view', 'dashboard'];

$deriveArtifactKindFromBundle = static function (?array $bundle): string {
    if (!is_array($bundle) || $bundle === []) {
        return 'unknown';
    }

    $viewDef = is_array($bundle['view_definition'] ?? null) ? $bundle['view_definition'] : [];
    $view = is_array($viewDef['view'] ?? null) ? $viewDef['view'] : [];
    if ($view !== []) {
        $viewKind = strtolower(trim((string)($view['view_kind'] ?? '')));
        if ($viewKind === 'dashboard') {
            return 'dashboard';
        }
        return 'view';
    }

    $moduleManifest = is_array($bundle['module_manifest'] ?? null) ? $bundle['module_manifest'] : [];
    if ($moduleManifest !== []) {
        return 'module';
    }

    $appManifest = is_array($bundle['app_manifest'] ?? null) ? $bundle['app_manifest'] : [];
    if ($appManifest !== []) {
        return 'app';
    }

    $navigation = is_array($bundle['navigation_definition'] ?? null) ? $bundle['navigation_definition'] : [];
    if ($navigation !== []) {
        return 'navigation';
    }

    return 'unknown';
};

$deriveArtifactKindFromLibraryNode = static function (?array $selectedNode, string $selectedNodeId = ''): string {
    if (is_array($selectedNode)) {
        $nodeType = strtolower(trim((string)($selectedNode['type'] ?? '')));
        if ($nodeType !== '') {
            return $nodeType;
        }
    }

    $normalizedId = strtolower(trim($selectedNodeId));
    if ($normalizedId === '') {
        return 'unknown';
    }

    if (str_starts_with($normalizedId, 'app:')) {
        return 'app';
    }
    if (str_starts_with($normalizedId, 'module:')) {
        return 'module';
    }
    if (str_starts_with($normalizedId, 'view:')) {
        return 'view';
    }
    if (str_starts_with($normalizedId, 'dashboard:')) {
        return 'dashboard';
    }
    if (str_starts_with($normalizedId, 'route:') || str_starts_with($normalizedId, 'route_file:')) {
        return 'route';
    }
    if (str_starts_with($normalizedId, 'nav:')) {
        return 'nav';
    }

    return 'unknown';
};

$buildLoadContract = static function (
    string $artifactKind,
    bool $ok,
    bool $partial,
    array $supportedKinds,
    string $code = '',
    string $message = ''
): array {
    $state = 'ready';
    if (!$ok) {
        $state = 'failed';
    } elseif ($partial) {
        $state = 'partial';
    }

    return [
        'version' => 'studio.load.v1',
        'deterministic' => true,
        'state' => $state,
        'artifact_kind' => $artifactKind,
        'supported_artifact_kinds' => array_values($supportedKinds),
        'code' => $code,
        'message' => $message,
    ];
};
$router->get($basePath . '/library', function () use ($basePath, $view, $buildGlobalLibraryContext, $buildStudioDataContractContext, $buildStudioDependencyGraphContext) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl($basePath . '/library');
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    $wantsJson = strtolower(trim((string)($_GET['format'] ?? ''))) === 'json'
        || strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'xmlhttprequest'
        || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

    $libraryEntries = GuiStudioService::listGeneratedAppsWithModules();
    $globalLibraryContext = $buildGlobalLibraryContext(is_array($_GET) ? $_GET : []);
    $studioDataContract = $buildStudioDataContractContext([]);
    $studioDependencyGraph = $buildStudioDependencyGraphContext([], $studioDataContract);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => true,
            'library' => $libraryEntries,
            'global_library' => $globalLibraryContext['library'] ?? [],
            'selected' => $globalLibraryContext['selected'] ?? null,
            'data_contract' => $studioDataContract,
            'dependency_graph' => $studioDependencyGraph,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return null;
    }

    $templates = GuiStudioService::loadTemplates();
    $inputs = [];
    foreach ($templates as $key => $value) {
        $inputs[$key] = $value;
    }

    $view->render('studio::gui_studio.php', [
        'pageTitle' => t('ops.gui_studio.page_title'),
        'csrf' => \App\Core\Auth::csrfToken(),
        'flash' => '',
        'error' => '',
        'result' => null,
        'inputs' => $inputs,
        'studioProject' => GuiStudioService::studioProjectModel(),
        'templateLibrary' => GuiStudioService::templateLibrary(),
        'draftLifecycle' => GuiStudioService::draftLifecycle(),
        'appLifecycleEntries' => GuiStudioService::generatedAppLifecycleEntries(),
        'generatedLibraryEntries' => $libraryEntries,
        'libraryInspectorBundle' => null,
        'studioMode' => 'library',
        'globalLibrary' => $globalLibraryContext['library'] ?? [],
        'globalLibrarySelected' => $globalLibraryContext['selected'] ?? null,
        'globalLibrarySelectedId' => $globalLibraryContext['selected_id'] ?? '',
        'studioDataContract' => $studioDataContract,
        'studioDependencyGraph' => $studioDependencyGraph,
    ]);
    return null;
});

$router->get($basePath . '/system-library', function () use ($basePath, $buildGlobalLibraryContext) {
    \App\Core\Auth::bootSession();
    header('Content-Type: application/json; charset=UTF-8');
    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'code' => 'auth_required', 'message' => 'auth_required']);
        return null;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'code' => 'forbidden', 'message' => 'forbidden']);
        return null;
    }

    $context = $buildGlobalLibraryContext(is_array($_GET) ? $_GET : []);
    echo json_encode([
        'ok' => true,
        'library' => $context['library'] ?? [],
        'selected' => $context['selected'] ?? null,
        'selected_id' => $context['selected_id'] ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return null;
});

$router->get($basePath . '/tools/registry', function () use ($basePath) {
    \App\Core\Auth::bootSession();
    header('Content-Type: application/json; charset=UTF-8');

    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'code' => 'auth_required', 'message' => 'auth_required']);
        return null;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'code' => 'forbidden', 'message' => 'forbidden']);
        return null;
    }

    $tools = StudioGovernedToolRegistryService::listTools();
    echo json_encode([
        'ok' => true,
        'base_path' => $basePath,
        'tools' => $tools,
        'count' => count($tools),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return null;
});

$router->get($basePath . '/resource-types', function () {
    \App\Core\Auth::bootSession();
    header('Content-Type: application/json; charset=UTF-8');

    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'code' => 'auth_required', 'message' => 'auth_required']);
        return null;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'code' => 'forbidden', 'message' => 'forbidden']);
        return null;
    }

    $types = StudioResourceTypeRegistryService::listTypes();
    echo json_encode([
        'ok' => true,
        'resource_types' => $types,
        'count' => count($types),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return null;
});

$router->get($basePath . '/tools/resource-explorer', function () use ($basePath, $buildGlobalLibraryContext) {
    \App\Core\Auth::bootSession();
    header('Content-Type: application/json; charset=UTF-8');

    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'code' => 'auth_required', 'message' => 'auth_required']);
        return null;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'code' => 'forbidden', 'message' => 'forbidden']);
        return null;
    }

    $query = is_array($_GET) ? $_GET : [];
    $context = $buildGlobalLibraryContext($query);
    $selected = is_array($context['selected'] ?? null) ? $context['selected'] : null;

    $resource = StudioResourceTypeRegistryService::describeLibraryNode($selected);
    $resourceType = (string)($resource['resource_type'] ?? 'unknown');
    $eligibleTools = StudioGovernedToolRegistryService::listEligibleToolsForResourceType($resourceType);

    echo json_encode([
        'ok' => true,
        'read_only' => true,
        'selected_id' => (string)($context['selected_id'] ?? ''),
        'selected' => $selected,
        'resource' => $resource,
        'eligible_tools' => $eligibleTools,
        'eligible_tool_count' => count($eligibleTools),
        'global_library_counts' => is_array($context['library']['counts'] ?? null) ? $context['library']['counts'] : [],
        'ownership_boundary' => [
            'studio_owns' => [
                'drafts',
                'change_records',
                'approval_workflow',
                'diffs',
                'snapshots',
                'rollback_records',
                'tool_ui',
                'tool_logic',
                'validation_workflow',
            ],
            'studio_does_not_own' => [
                'business_logic',
                'runtime_layout_truth',
                'core_platform_law',
                'owner_source_of_truth',
                'live_db_rows',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return null;
});

$router->post($basePath . '/experience-governance/analyze', function () use ($basePath) {
    \App\Core\Auth::bootSession();
    header('Content-Type: application/json; charset=UTF-8');

    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'code' => 'auth_required']);
        return null;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'code' => 'forbidden']);
        return null;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath);

    $decodePayload = static function (string $field): array {
        $raw = $_POST[$field] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    };

    $resolvedExperience = $decodePayload('resolved_experience');
    $proposal = $decodePayload('proposal');
    if ($resolvedExperience === [] || $proposal === []) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'code' => 'invalid_experience_governance_payload',
        ]);
        return null;
    }

    echo json_encode(
        StudioExperienceGovernanceService::evaluateProposal($resolvedExperience, $proposal),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    return null;
});

$router->post($basePath . '/experience-governance/apply', function () use ($basePath) {
    \App\Core\Auth::bootSession();
    header('Content-Type: application/json; charset=UTF-8');

    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'code' => 'auth_required']);
        return null;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'code' => 'forbidden']);
        return null;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath);

    $decodePayload = static function (string $field): array {
        $raw = $_POST[$field] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    };

    $resolvedExperience = $decodePayload('resolved_experience');
    $proposal = $decodePayload('proposal');
    if ($resolvedExperience === [] || $proposal === []) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'code' => 'invalid_experience_apply_payload',
        ]);
        return null;
    }

    $analysis = StudioExperienceGovernanceService::analyzeProposal($resolvedExperience, $proposal);
    $applyPlan = StudioExperienceGovernanceService::buildApplyPlan($analysis, $proposal);
    $fingerprint = trim((string)($_POST['apply_fingerprint'] ?? ''));
    if ($fingerprint === '') {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'code' => 'missing_experience_apply_fingerprint',
        ]);
        return null;
    }
    if ($applyPlan === []) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'code' => 'invalid_experience_apply_payload',
        ]);
        return null;
    }

    $result = StudioExperienceGovernanceService::applyApprovedPlan(
        $applyPlan,
        $fingerprint
    );
    if (empty($result['ok'])) {
        http_response_code((string)($result['status'] ?? '') === 'FAILED' ? 500 : 409);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return null;
});

// Phase 1E: Route → View linking — Analyze gate
// POST /apps/studio/route-link/analyze
// Accepts: app_key, module_key, proposed_route, mode, current_route, upgrade_acknowledged, csrf
// Returns: analysis with apply_gate, changes, fingerprint (JSON)
$router->post($basePath . '/route-link/analyze', function () use ($basePath) {
    \App\Core\Auth::bootSession();
    header('Content-Type: application/json; charset=UTF-8');

    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'code' => 'auth_required']);
        return null;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'code' => 'forbidden']);
        return null;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath);

    $analysis = StudioRouteLinkingService::analyzeProposal([
        'app_key'              => trim((string)($_POST['app_key'] ?? '')),
        'module_key'           => trim((string)($_POST['module_key'] ?? '')),
        'proposed_route'       => trim((string)($_POST['proposed_route'] ?? '')),
        'mode'                 => trim((string)($_POST['mode'] ?? 'create')),
        'current_route'        => trim((string)($_POST['current_route'] ?? '')),
        'upgrade_acknowledged' => !empty($_POST['upgrade_acknowledged']),
    ]);

    $httpStatus = ($analysis['apply_gate']['status'] ?? '') === 'READY' ? 200 : 422;
    http_response_code($httpStatus);
    echo json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return null;
});

// Phase 1E: Route → View linking — Guarded Apply
// POST /apps/studio/route-link/apply
// Accepts: plan (JSON-encoded), apply_fingerprint, csrf
// Returns: apply result (JSON)
$router->post($basePath . '/route-link/apply', function () use ($basePath) {
    \App\Core\Auth::bootSession();
    header('Content-Type: application/json; charset=UTF-8');

    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'code' => 'auth_required']);
        return null;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'code' => 'forbidden']);
        return null;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath);

    $rawPlan = $_POST['plan'] ?? null;
    $plan = [];
    if (is_string($rawPlan) && trim($rawPlan) !== '') {
        $decoded = json_decode($rawPlan, true);
        $plan = is_array($decoded) ? $decoded : [];
    } elseif (is_array($rawPlan)) {
        $plan = $rawPlan;
    }

    if ($plan === []) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'code' => 'missing_plan']);
        return null;
    }

    $fingerprint = trim((string)($_POST['apply_fingerprint'] ?? ''));
    if ($fingerprint === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'code' => 'missing_fingerprint']);
        return null;
    }

    $result = StudioRouteLinkingService::applyApprovedPlan($plan, $fingerprint);
    $httpStatus = empty($result['ok']) ? (($result['status'] ?? '') === 'FAILED' ? 500 : 409) : 200;
    http_response_code($httpStatus);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return null;
});

// Phase 1F: Nav → View linking — Analyze gate
// POST /apps/studio/nav-link/analyze
// Accepts: app_key, module_key, proposed_url, mode, current_url, nav_label, upgrade_acknowledged, csrf
// Returns: analysis result with changes, apply_gate, plan, fingerprint (JSON)
$router->post($basePath . '/nav-link/analyze', function () use ($basePath) {
    \App\Core\Auth::bootSession();
    header('Content-Type: application/json; charset=UTF-8');

    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'code' => 'auth_required']);
        return null;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'code' => 'forbidden']);
        return null;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath);

    $result = StudioNavLinkingService::analyzeProposal([
        'app_key' => (string)($_POST['app_key'] ?? ''),
        'module_key' => (string)($_POST['module_key'] ?? ''),
        'proposed_url' => (string)($_POST['proposed_url'] ?? ''),
        'mode' => (string)($_POST['mode'] ?? 'create'),
        'current_url' => (string)($_POST['current_url'] ?? ''),
        'nav_label' => (string)($_POST['nav_label'] ?? ''),
        'upgrade_acknowledged' => !empty($_POST['upgrade_acknowledged']),
    ]);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return null;
});

// Phase 1F: Nav → View linking — Guarded Apply
// POST /apps/studio/nav-link/apply
// Accepts: plan (JSON-encoded), apply_fingerprint, csrf
// Returns: apply result (JSON)
$router->post($basePath . '/nav-link/apply', function () use ($basePath) {
    \App\Core\Auth::bootSession();
    header('Content-Type: application/json; charset=UTF-8');

    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'code' => 'auth_required']);
        return null;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'code' => 'forbidden']);
        return null;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath);

    $rawPlan = $_POST['plan'] ?? null;
    $plan = [];
    if (is_string($rawPlan) && trim($rawPlan) !== '') {
        $decoded = json_decode($rawPlan, true);
        $plan = is_array($decoded) ? $decoded : [];
    } elseif (is_array($rawPlan)) {
        $plan = $rawPlan;
    }

    if ($plan === []) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'code' => 'missing_plan']);
        return null;
    }

    $fingerprint = trim((string)($_POST['apply_fingerprint'] ?? ''));
    if ($fingerprint === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'code' => 'missing_fingerprint']);
        return null;
    }

    $result = StudioNavLinkingService::applyApprovedPlan($plan, $fingerprint);
    $httpStatus = empty($result['ok']) ? (($result['status'] ?? '') === 'FAILED' ? 500 : 409) : 200;
    http_response_code($httpStatus);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return null;
});

$router->get($basePath . '/library/module', function () use ($basePath, $view, $buildGlobalLibraryContext, $buildStudioDataContractContext, $buildStudioDependencyGraphContext, $buildLoadContract, $deriveArtifactKindFromBundle, $supportedLibraryArtifactTypes) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl($basePath . '/library/module');
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    $wantsJson = strtolower(trim((string)($_GET['format'] ?? ''))) === 'json'
        || strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'xmlhttprequest'
        || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

    $appKey = trim((string)($_GET['app_key'] ?? ''));
    $moduleKey = trim((string)($_GET['module_key'] ?? ''));
    $bundle = GuiStudioService::loadGeneratedModuleDefinition($appKey, $moduleKey);
    $globalLibraryContext = $buildGlobalLibraryContext(is_array($_GET) ? $_GET : []);
    $studioDataContract = $buildStudioDataContractContext([], is_array($bundle) ? $bundle : null);
    $studioDependencyGraph = $buildStudioDependencyGraphContext([], $studioDataContract, is_array($bundle) ? $bundle : null);

    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        if (!is_array($bundle)) {
            http_response_code(404);
            echo json_encode([
                'ok' => false,
                'code' => 'generated_module_not_found',
                'message' => 'generated_module_not_found',
                'artifact_kind' => 'module',
                'supported_artifacts' => $supportedLibraryArtifactTypes,
                'errors' => ['generated_module_not_found'],
                'warnings' => [],
                'load_contract' => $buildLoadContract('module', false, false, $supportedLibraryArtifactTypes, 'generated_module_not_found', 'generated_module_not_found'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return null;
        }

        $artifactKind = $deriveArtifactKindFromBundle($bundle);
        echo json_encode([
            'ok' => true,
            'bundle' => $bundle,
            'previous_bundle' => $bundle,
            'artifact_kind' => $artifactKind,
            'supported_artifacts' => $supportedLibraryArtifactTypes,
            'errors' => [],
            'warnings' => [],
            'load_contract' => $buildLoadContract($artifactKind, true, false, $supportedLibraryArtifactTypes),
            'global_library' => $globalLibraryContext['library'] ?? [],
            'selected' => $globalLibraryContext['selected'] ?? null,
            'data_contract' => $studioDataContract,
            'dependency_graph' => $studioDependencyGraph,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return null;
    }

    $templates = GuiStudioService::loadTemplates();
    $inputs = [];
    foreach ($templates as $key => $value) {
        $inputs[$key] = $value;
    }
    if (is_array($bundle)) {
        $inputs['app_manifest'] = (string)json_encode((array)($bundle['app_manifest'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputs['module_manifest'] = (string)json_encode((array)($bundle['module_manifest'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputs['view_definition'] = (string)json_encode((array)($bundle['view_definition'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputs['navigation_definition'] = (string)json_encode((array)($bundle['navigation_definition'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputs['se_previous_bundle'] = (string)json_encode($bundle, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $flash = is_array($bundle) ? 'ops.gui_studio.flash_library_bundle_loaded' : '';
    $error = is_array($bundle) ? '' : 'ops.gui_studio.flash_library_bundle_missing';

    $view->render('studio::gui_studio.php', [
        'pageTitle' => t('ops.gui_studio.page_title'),
        'csrf' => \App\Core\Auth::csrfToken(),
        'flash' => $flash,
        'error' => $error,
        'result' => null,
        'inputs' => $inputs,
        'studioProject' => GuiStudioService::studioProjectModel(),
        'templateLibrary' => GuiStudioService::templateLibrary(),
        'draftLifecycle' => GuiStudioService::draftLifecycle(),
        'appLifecycleEntries' => GuiStudioService::generatedAppLifecycleEntries(),
        'generatedLibraryEntries' => GuiStudioService::listGeneratedAppsWithModules(),
        'libraryInspectorBundle' => $bundle,
        'studioMode' => is_array($bundle) ? 'edit_existing' : 'library',
        'globalLibrary' => $globalLibraryContext['library'] ?? [],
        'globalLibrarySelected' => $globalLibraryContext['selected'] ?? null,
        'globalLibrarySelectedId' => $globalLibraryContext['selected_id'] ?? '',
        'studioDataContract' => $studioDataContract,
        'studioDependencyGraph' => $studioDependencyGraph,
    ]);
    return null;
});

$router->get($basePath . '/library/load', function () use ($basePath, $view, $buildGlobalLibraryContext, $buildStudioDataContractContext, $buildStudioDependencyGraphContext, $buildLoadContract, $deriveArtifactKindFromBundle, $deriveArtifactKindFromLibraryNode, $supportedLibraryArtifactTypes) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl($basePath . '/library/load');
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    $wantsJson = strtolower(trim((string)($_GET['format'] ?? ''))) === 'json'
        || strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'xmlhttprequest'
        || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

    $selectedNodeId = trim((string)($_GET['node_id'] ?? $_GET['library_item'] ?? ''));
    $query = is_array($_GET) ? $_GET : [];
    $query['library_item'] = $selectedNodeId;
    $globalLibraryContext = $buildGlobalLibraryContext($query);
    $selectedNode = is_array($globalLibraryContext['selected'] ?? null) ? $globalLibraryContext['selected'] : null;

    $introspection = is_array($selectedNode)
        ? StudioViewIntrospectionService::importLibraryNode($selectedNode)
        : ['ok' => false, 'code' => 'library_node_not_found', 'message' => 'library_node_not_found'];

    $bundle = is_array($introspection['bundle'] ?? null) ? $introspection['bundle'] : null;
    $studioDataContract = $buildStudioDataContractContext([], $bundle, is_array($introspection['import_model'] ?? null) ? $introspection['import_model'] : []);
    $studioDependencyGraph = $buildStudioDependencyGraphContext([], $studioDataContract, $bundle);

    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        if (empty($introspection['ok'])) {
            $errorCode = (string)($introspection['code'] ?? 'import_failed');
            $errorMessage = (string)($introspection['message'] ?? 'import_failed');
            $artifactKind = $deriveArtifactKindFromLibraryNode($selectedNode, $selectedNodeId);
            $responseCode = $errorCode === 'unsupported_library_node' ? 422 : 404;
            http_response_code($responseCode);
            echo json_encode([
                'ok' => false,
                'code' => $errorCode,
                'message' => $errorMessage,
                'artifact_kind' => $artifactKind,
                'selected_id' => $globalLibraryContext['selected_id'] ?? $selectedNodeId,
                'supported_artifacts' => $supportedLibraryArtifactTypes,
                'errors' => [$errorMessage],
                'warnings' => $errorCode === 'unsupported_library_node'
                    ? ['unsupported_library_node:' . $artifactKind]
                    : [],
                'load_contract' => $buildLoadContract($artifactKind, false, false, $supportedLibraryArtifactTypes, $errorCode, $errorMessage),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return null;
        }

        $artifactKind = $deriveArtifactKindFromBundle($bundle);
        $partialImport = !empty($introspection['partial_import']);
        echo json_encode([
            'ok' => true,
            'bundle' => $bundle,
            'previous_bundle' => $bundle,
            'artifact_kind' => $artifactKind,
            'source_detection' => $introspection['source_detection'] ?? [],
            'selected_source' => (string)($introspection['selected_source'] ?? ''),
            'partial_import' => $partialImport,
            'import_model' => $introspection['import_model'] ?? [],
            'global_library' => $globalLibraryContext['library'] ?? [],
            'selected' => $globalLibraryContext['selected'] ?? null,
            'selected_id' => $globalLibraryContext['selected_id'] ?? $selectedNodeId,
            'supported_artifacts' => $supportedLibraryArtifactTypes,
            'errors' => [],
            'warnings' => $partialImport ? ['partial_import'] : [],
            'load_contract' => $buildLoadContract($artifactKind, true, $partialImport, $supportedLibraryArtifactTypes),
            'data_contract' => $studioDataContract,
            'dependency_graph' => $studioDependencyGraph,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return null;
    }

    $templates = GuiStudioService::loadTemplates();
    $inputs = [];
    foreach ($templates as $key => $value) {
        $inputs[$key] = $value;
    }

    if (is_array($bundle)) {
        $inputs['app_manifest'] = (string)json_encode((array)($bundle['app_manifest'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputs['module_manifest'] = (string)json_encode((array)($bundle['module_manifest'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputs['view_definition'] = (string)json_encode((array)($bundle['view_definition'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputs['navigation_definition'] = (string)json_encode((array)($bundle['navigation_definition'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputs['se_previous_bundle'] = (string)json_encode($bundle, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $flash = is_array($bundle)
        ? (!empty($introspection['partial_import']) ? 'ops.gui_studio.flash_import_partial' : 'ops.gui_studio.flash_import_loaded')
        : '';
    $error = is_array($bundle) ? '' : 'ops.gui_studio.flash_import_missing';

    $view->render('studio::gui_studio.php', [
        'pageTitle' => t('ops.gui_studio.page_title'),
        'csrf' => \App\Core\Auth::csrfToken(),
        'flash' => $flash,
        'error' => $error,
        'result' => null,
        'inputs' => $inputs,
        'studioProject' => GuiStudioService::studioProjectModel(),
        'templateLibrary' => GuiStudioService::templateLibrary(),
        'draftLifecycle' => GuiStudioService::draftLifecycle(),
        'appLifecycleEntries' => GuiStudioService::generatedAppLifecycleEntries(),
        'generatedLibraryEntries' => GuiStudioService::listGeneratedAppsWithModules(),
        'libraryInspectorBundle' => $bundle,
        'studioMode' => is_array($bundle) ? 'edit_existing' : 'library',
        'globalLibrary' => $globalLibraryContext['library'] ?? [],
        'globalLibrarySelected' => $globalLibraryContext['selected'] ?? null,
        'globalLibrarySelectedId' => $globalLibraryContext['selected_id'] ?? $selectedNodeId,
        'studioDataContract' => $studioDataContract,
        'studioDependencyGraph' => $studioDependencyGraph,
    ]);
    return null;
});

$router->get($basePath . '/import-view', function () use ($basePath, $view, $buildGlobalLibraryContext, $buildStudioDataContractContext, $buildStudioDependencyGraphContext, $buildLoadContract, $deriveArtifactKindFromBundle, $supportedLibraryArtifactTypes) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        \App\Core\Auth::rememberIntendedUrl($basePath . '/import-view');
        header('Location: /login', true, 302);
        exit;
    }

    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    $wantsJson = strtolower(trim((string)($_GET['format'] ?? ''))) === 'json'
        || strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'xmlhttprequest'
        || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

    $appKey = trim((string)($_GET['app_key'] ?? ''));
    $moduleKey = trim((string)($_GET['module_key'] ?? ''));
    $viewKey = trim((string)($_GET['view_key'] ?? 'index'));

    $introspection = StudioViewIntrospectionService::importExistingView($appKey, $moduleKey, $viewKey);
    $globalLibraryContext = $buildGlobalLibraryContext(is_array($_GET) ? $_GET : []);
    $bundle = is_array($introspection['bundle'] ?? null) ? $introspection['bundle'] : null;
    $studioDataContract = $buildStudioDataContractContext([], $bundle, is_array($introspection['import_model'] ?? null) ? $introspection['import_model'] : []);
    $studioDependencyGraph = $buildStudioDependencyGraphContext([], $studioDataContract, $bundle);

    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        if (empty($introspection['ok'])) {
            http_response_code(404);
            $errorCode = (string)($introspection['code'] ?? 'import_failed');
            $errorMessage = (string)($introspection['message'] ?? 'import_failed');
            echo json_encode([
                'ok' => false,
                'code' => $errorCode,
                'message' => $errorMessage,
                'artifact_kind' => 'view',
                'supported_artifacts' => $supportedLibraryArtifactTypes,
                'errors' => [$errorMessage],
                'warnings' => [],
                'load_contract' => $buildLoadContract('view', false, false, $supportedLibraryArtifactTypes, $errorCode, $errorMessage),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return null;
        }

        $artifactKind = $deriveArtifactKindFromBundle($bundle);
        $partialImport = !empty($introspection['partial_import']);
        echo json_encode([
            'ok' => true,
            'bundle' => $introspection['bundle'] ?? [],
            'previous_bundle' => $introspection['bundle'] ?? [],
            'artifact_kind' => $artifactKind,
            'source_detection' => $introspection['source_detection'] ?? [],
            'selected_source' => (string)($introspection['selected_source'] ?? ''),
            'partial_import' => $partialImport,
            'import_model' => $introspection['import_model'] ?? [],
            'global_library' => $globalLibraryContext['library'] ?? [],
            'selected' => $globalLibraryContext['selected'] ?? null,
            'supported_artifacts' => $supportedLibraryArtifactTypes,
            'errors' => [],
            'warnings' => $partialImport ? ['partial_import'] : [],
            'load_contract' => $buildLoadContract($artifactKind, true, $partialImport, $supportedLibraryArtifactTypes),
            'data_contract' => $studioDataContract,
            'dependency_graph' => $studioDependencyGraph,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return null;
    }

    $templates = GuiStudioService::loadTemplates();
    $inputs = [];
    foreach ($templates as $key => $value) {
        $inputs[$key] = $value;
    }

    if (is_array($bundle)) {
        $inputs['app_manifest'] = (string)json_encode((array)($bundle['app_manifest'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputs['module_manifest'] = (string)json_encode((array)($bundle['module_manifest'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputs['view_definition'] = (string)json_encode((array)($bundle['view_definition'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputs['navigation_definition'] = (string)json_encode((array)($bundle['navigation_definition'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $inputs['se_previous_bundle'] = (string)json_encode($bundle, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $flash = is_array($bundle)
        ? (!empty($introspection['partial_import']) ? 'ops.gui_studio.flash_import_partial' : 'ops.gui_studio.flash_import_loaded')
        : '';
    $error = is_array($bundle) ? '' : 'ops.gui_studio.flash_import_missing';

    $view->render('studio::gui_studio.php', [
        'pageTitle' => t('ops.gui_studio.page_title'),
        'csrf' => \App\Core\Auth::csrfToken(),
        'flash' => $flash,
        'error' => $error,
        'result' => null,
        'inputs' => $inputs,
        'studioProject' => GuiStudioService::studioProjectModel(),
        'templateLibrary' => GuiStudioService::templateLibrary(),
        'draftLifecycle' => GuiStudioService::draftLifecycle(),
        'appLifecycleEntries' => GuiStudioService::generatedAppLifecycleEntries(),
        'generatedLibraryEntries' => GuiStudioService::listGeneratedAppsWithModules(),
        'libraryInspectorBundle' => $bundle,
        'studioMode' => is_array($bundle) ? 'edit_existing' : 'library',
        'globalLibrary' => $globalLibraryContext['library'] ?? [],
        'globalLibrarySelected' => $globalLibraryContext['selected'] ?? null,
        'globalLibrarySelectedId' => $globalLibraryContext['selected_id'] ?? '',
        'studioDataContract' => $studioDataContract,
        'studioDependencyGraph' => $studioDependencyGraph,
    ]);
    return null;
});

$router->post($basePath . '/lifecycle', function () use ($basePath) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    $appKey = trim((string)($_POST['app_key'] ?? ''));
    $action = strtolower(trim((string)($_POST['action'] ?? '')));
    $result = match ($action) {
        'enable' => GuiStudioService::setGeneratedAppLifecycleStatus($appKey, 'enabled'),
        'disable' => GuiStudioService::setGeneratedAppLifecycleStatus($appKey, 'disabled'),
        'uninstall' => GuiStudioService::uninstallGeneratedApp($appKey),
        default => ['ok' => false, 'status' => 'FAILED', 'message' => 'invalid_lifecycle_request'],
    };

    $_SESSION['ops_gui_studio_flash'] = !empty($result['ok'])
        ? 'ops.gui_studio.flash_lifecycle_updated'
        : '';
    $_SESSION['ops_gui_studio_error'] = empty($result['ok'])
        ? 'ops.gui_studio.flash_lifecycle_failed'
        : '';
    $_SESSION['ops_gui_studio_result'] = [
        'mode' => 'lifecycle',
        'ok' => !empty($result['ok']),
        'checks' => [],
        'errors' => empty($result['ok']) ? [(string)($result['message'] ?? 'lifecycle_failed')] : [],
        'compile_plan' => [],
        'diff_view_model' => [],
        'approval_payload' => [],
        'approval_validation' => ['valid' => false, 'errors' => []],
        'approval_summary' => [],
        'snapshot' => [],
        'snapshot_summary' => [],
        'execution' => [],
        'apply' => [],
        'rollback' => [],
        'lifecycle' => $result,
    ];
    header('Location: ' . $basePath . '/legacy', true, 302);
    exit;
});

$router->post($basePath . '/validate', function () use ($basePath) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    $payload = GuiStudioService::mapStructuredEditorPayload($_POST);
    $payload['se_previous_bundle'] = (string)($_POST['se_previous_bundle'] ?? '');
    $payload['reason'] = (string)($_POST['reason'] ?? '');
    $payload['risk_acknowledged'] = !empty($_POST['risk_acknowledged']) ? '1' : '0';
    $payload['migration_override'] = !empty($_POST['migration_override']) ? '1' : '0';
    $payload['migration_override_reason'] = (string)($_POST['migration_override_reason'] ?? '');
    $payload['impact_confirmation'] = !empty($_POST['impact_confirmation']) ? '1' : '0';
    $payload['impact_acknowledged'] = !empty($_POST['impact_acknowledged']) ? '1' : '0';
    $payload['simulation_override'] = !empty($_POST['simulation_override']) ? '1' : '0';
    $payload['simulation_override_reason'] = (string)($_POST['simulation_override_reason'] ?? '');

    $report = GuiStudioService::validateBundle($payload);
    $_SESSION['ops_gui_studio_inputs'] = $payload;
    $_SESSION['ops_gui_studio_result'] = [
        'mode' => 'validate',
        'ok' => (bool)$report['ok'],
        'checks' => $report['checks'],
        'errors' => $report['errors'],
        'compile_plan' => [],
    ];
    $_SESSION['ops_gui_studio_flash'] = $report['ok'] ? 'ops.gui_studio.flash_validation_ok' : '';
    $_SESSION['ops_gui_studio_error'] = $report['ok'] ? '' : 'ops.gui_studio.flash_validation_failed';
    header('Location: ' . $basePath . '/legacy', true, 302);
    exit;
});

// G1-G4: Preflight check route — runs all G2 guardrails + G3 lint before compile plan
$router->post($basePath . '/preflight', function () use ($basePath, $buildStructuredChangeContext) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    $payload = GuiStudioService::mapStructuredEditorPayload($_POST);
    $payload['se_previous_bundle'] = (string)($_POST['se_previous_bundle'] ?? '');
    $payload['reason'] = (string)($_POST['reason'] ?? '');
    $payload['risk_acknowledged'] = !empty($_POST['risk_acknowledged']) ? '1' : '0';
    $payload['migration_override'] = !empty($_POST['migration_override']) ? '1' : '0';
    $payload['migration_override_reason'] = (string)($_POST['migration_override_reason'] ?? '');
    $payload['impact_confirmation'] = !empty($_POST['impact_confirmation']) ? '1' : '0';
    $payload['impact_acknowledged'] = !empty($_POST['impact_acknowledged']) ? '1' : '0';
    $payload['simulation_override'] = !empty($_POST['simulation_override']) ? '1' : '0';
    $payload['simulation_override_reason'] = (string)($_POST['simulation_override_reason'] ?? '');
    $changeContext = $buildStructuredChangeContext($_POST, $payload);

    $validateReport  = GuiStudioService::validateBundle($payload);
    $decoded         = $validateReport['decoded'];
    $preflightResult = StudioGovernanceService::preflightCheck($decoded);
    $lintResult      = StudioGovernanceService::lintGeneratedBundle($decoded);
    $focusedPlan     = StudioGovernanceService::focusedStartPlan(GuiStudioService::templateLibrary());
    $todoSeed        = StudioGovernanceService::workflowTodoSeed();
    $checkpoints     = StudioGovernanceService::approvalCheckpoints();

    $ok = $validateReport['ok']
        && $preflightResult['ok']
        && $lintResult['ok']
        && !empty($changeContext['risk_escalation']['valid']);

    $_SESSION['ops_gui_studio_inputs'] = $payload;
    $_SESSION['ops_gui_studio_result'] = [
        'mode'             => 'preflight',
        'ok'               => $ok,
        'validate_checks'  => $validateReport['checks'],
        'validate_errors'  => $validateReport['errors'],
        'preflight_checks' => $preflightResult['checks'],
        'preflight_errors' => $preflightResult['errors'],
        'lint_checks'      => $lintResult['lint_checks'],
        'lint_errors'      => $lintResult['errors'],
        'focused_plan'     => $focusedPlan,
        'todo_seed'        => $todoSeed,
        'checkpoints'      => $checkpoints,
        'change_intelligence' => $changeContext['change_intelligence'],
        'dependency_graph' => $changeContext['dependency_graph'],
        'impact_analysis'  => $changeContext['impact_analysis'],
        'impact_guard'     => $changeContext['impact_guard'],
        'migration_plan'   => $changeContext['migration_plan'],
        'migration_guard'  => $changeContext['migration_guard'],
        'risk_escalation'  => $changeContext['risk_escalation'],
        'compile_plan'     => [],
    ];
    $_SESSION['ops_gui_studio_flash'] = $ok ? 'ops.gui_studio.flash_preflight_ok' : '';
    $_SESSION['ops_gui_studio_error'] = $ok ? '' : 'ops.gui_studio.flash_preflight_failed';

    header('Location: ' . $basePath . '/legacy', true, 302);
    exit;
});

// G4: Publish gate check — all G2/G3/G4 gates must pass before publish is enabled
$router->post($basePath . '/publish-gate', function () use ($basePath, $buildStructuredChangeContext, $buildPipelineAnalysisFingerprint) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    // Reconstruct decoded bundle from POST
    $payload = GuiStudioService::mapStructuredEditorPayload($_POST);
    $payload['se_previous_bundle'] = (string)($_POST['se_previous_bundle'] ?? '');
    $payload['reason'] = (string)($_POST['reason'] ?? '');
    $payload['risk_acknowledged'] = !empty($_POST['risk_acknowledged']) ? '1' : '0';
    $payload['migration_override'] = !empty($_POST['migration_override']) ? '1' : '0';
    $payload['migration_override_reason'] = (string)($_POST['migration_override_reason'] ?? '');
    $payload['impact_confirmation'] = !empty($_POST['impact_confirmation']) ? '1' : '0';
    $payload['impact_acknowledged'] = !empty($_POST['impact_acknowledged']) ? '1' : '0';
    $payload['simulation_override'] = !empty($_POST['simulation_override']) ? '1' : '0';
    $payload['simulation_override_reason'] = (string)($_POST['simulation_override_reason'] ?? '');
    $changeContext = $buildStructuredChangeContext($_POST, $payload);
    $validateReport  = GuiStudioService::validateBundle($payload);
    $decoded         = $validateReport['decoded'];
    $preflightResult = StudioGovernanceService::preflightCheck($decoded);
    $lintResult      = StudioGovernanceService::lintGeneratedBundle($decoded);

    // Reconstruct compile plan + approval payload from the live Studio form payload.
    $compilePlan = [];
    $approvalPayload = [];
    $user = \App\Core\Auth::user();
    $adminHandle = trim((string)($user['handle'] ?? $user['email'] ?? 'unknown'));
    $compilePlan = GuiStudioService::buildGeneratedApplyPlan($decoded);
    $compilePlan['change_intelligence'] = $changeContext['change_intelligence'];
    $compilePlan['dependency_graph'] = $changeContext['dependency_graph'];
    $compilePlan['impact_analysis'] = $changeContext['impact_analysis'];
    $compilePlan['impact_guard'] = $changeContext['impact_guard'];
    $compilePlan['simulation_preview'] = $changeContext['simulation_preview'];
    $compilePlan['simulation_guard'] = $changeContext['simulation_guard'];
    $compilePlan['migration_plan'] = $changeContext['migration_plan'];
    $compilePlan['migration_guard'] = $changeContext['migration_guard'];
    $compilePlan['risk_escalation'] = $changeContext['risk_escalation'];
    $approvedBy = $adminHandle !== '' ? $adminHandle : (string)($user['username'] ?? 'current_user');
    $compileId = (string)($compilePlan['compile_meta']['compile_id'] ?? $compilePlan['compile_snapshot']['compile_id'] ?? '');
    $analysisFingerprint = $buildPipelineAnalysisFingerprint($decoded, $changeContext);
    $pipelineState = is_array($_SESSION['ops_gui_studio_pipeline'] ?? null) ? $_SESSION['ops_gui_studio_pipeline'] : [];
    $analyzeState = is_array($pipelineState['analyze'] ?? null) ? $pipelineState['analyze'] : [];
    $analyzeDone = !empty($analyzeState['analyze_done']);
    $analyzeFingerprint = trim((string)($analyzeState['analysis_fingerprint'] ?? ''));
    $approvalPayload = GuiStudioService::buildApprovalPayload($compilePlan, [
        'approved_by' => $approvedBy,
        'decision' => (string)($_POST['decision'] ?? 'approved'),
        'reason' => (string)($_POST['reason'] ?? ''),
        'risk_acknowledged' => !empty($_POST['risk_acknowledged']),
    ], (array)($changeContext['change_intelligence'] ?? []), (array)($changeContext['migration_plan'] ?? []), (array)($changeContext['impact_analysis'] ?? []), (array)($changeContext['simulation_preview'] ?? []));
    $approvalValidation = GuiStudioService::validateApproval($approvalPayload);
    $approvalSummary    = GuiStudioService::summarizeApproval($approvalPayload);

    $gateResult   = ['ok' => false, 'errors' => ['safe_pipeline_analyze_required'], 'gate_checks' => [], 'apply_mode' => GuiStudioService::APPLY_MODE];
    if ($analyzeDone && $analysisFingerprint !== '' && $analyzeFingerprint !== '' && hash_equals($analyzeFingerprint, $analysisFingerprint)) {
        $gateResult = StudioGovernanceService::publishGateCheck($compilePlan, $approvalPayload, $preflightResult, $lintResult);
    }
    $rollbackPlan = StudioGovernanceService::captureRollbackPlan($compilePlan);

    $publishDecision = StudioGovernanceService::buildPublishDecisionRecord($compilePlan, $approvalPayload, $adminHandle);
    $snapshot = [];
    $snapshotSummary = [];
    $execution = [];

    $auditWritten = false;
    if ($gateResult['ok']) {
        $decisionWritten = StudioGovernanceService::writePublishDecisionRecord($publishDecision);
        if (!$decisionWritten) {
            $gateResult['ok'] = false;
            $gateResult['errors'][] = 'publish_decision_record_write_failed';
        }
    }
    if ($gateResult['ok']) {
        $approvalPayload['publish_approved'] = true;
        $approvalPayload['publish_gate_status'] = 'PASSED';
        $approvalPayload['publish_decision_id'] = (string)($publishDecision['record_id'] ?? '');
        $approvalPayload['approved_by'] = (string)($publishDecision['decided_by'] ?? $adminHandle);
        $approvalPayload['approval_timestamp'] = (string)($publishDecision['decided_at'] ?? gmdate('c'));
        $auditWritten = StudioGovernanceService::writeAuditSnapshot($compilePlan, $approvalPayload, $adminHandle);
        $_SESSION['ops_gui_studio_publish_gate'] = [
            'compile_id' => $compileId,
            'approval_id' => (string)($approvalPayload['approval_id'] ?? ''),
            'publish_decision_id' => (string)($publishDecision['record_id'] ?? ''),
            'approved_by' => (string)($publishDecision['decided_by'] ?? $adminHandle),
            'approval_timestamp' => (string)($publishDecision['decided_at'] ?? gmdate('c')),
            'publish_approved' => true,
            'publish_gate_status' => 'PASSED',
        ];
        $_SESSION['ops_gui_studio_pipeline'] = [
            'analyze' => [
                'analyze_done' => true,
                'analysis_fingerprint' => $analysisFingerprint,
                'compile_id' => $compileId,
                'recorded_at' => gmdate('c'),
            ],
            'confirm' => [
                'compile_id' => $compileId,
                'approval_id' => (string)($approvalPayload['approval_id'] ?? ''),
                'recorded_at' => gmdate('c'),
            ],
        ];
        $snapshot = GuiStudioService::buildSnapshot($compilePlan, $approvalPayload);
        $snapshotSummary = GuiStudioService::summarizeSnapshot($snapshot);
        $execution = GuiStudioService::simulateExecution($snapshot);
    } else {
        unset($_SESSION['ops_gui_studio_publish_gate']);
        if ($gateResult['errors'] !== []) {
            unset($_SESSION['ops_gui_studio_pipeline']['confirm']);
        }
    }

    $_SESSION['ops_gui_studio_inputs'] = $payload;
    $_SESSION['ops_gui_studio_result'] = [
        'mode'              => 'publish_gate',
        'ok'                => $gateResult['ok'],
        'gate_checks'       => $gateResult['gate_checks'],
        'gate_errors'       => $gateResult['errors'],
        'apply_mode'        => $gateResult['apply_mode'],
        'rollback_plan'     => $rollbackPlan,
        'publish_decision'  => $publishDecision,
        'audit_written'     => $auditWritten,
        'compile_plan'      => $compilePlan,
        'approval_payload'  => $approvalPayload,
        'approval_validation' => $approvalValidation,
        'approval_summary'  => $approvalSummary,
        'snapshot'          => $snapshot,
        'snapshot_summary'  => $snapshotSummary,
        'execution'         => $execution,
        'change_intelligence' => $changeContext['change_intelligence'],
        'dependency_graph'  => $changeContext['dependency_graph'],
        'impact_analysis'   => $changeContext['impact_analysis'],
        'impact_guard'      => $changeContext['impact_guard'],
        'migration_plan'    => $changeContext['migration_plan'],
        'migration_guard'   => $changeContext['migration_guard'],
        'risk_escalation'   => $changeContext['risk_escalation'],
    ];
    $_SESSION['ops_gui_studio_flash'] = $gateResult['ok'] ? 'ops.gui_studio.flash_publish_gate_ok' : '';
    $_SESSION['ops_gui_studio_error'] = $gateResult['ok'] ? '' : 'ops.gui_studio.flash_publish_gate_failed';

    header('Location: ' . $basePath . '/legacy', true, 302);
    exit;
});

$guiStudioCompilePlanHandler = function () use ($basePath, $buildStructuredChangeContext, $buildPipelineAnalysisFingerprint) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $ctx = platform_user_context_contract()->resolveUserContext(\App\Core\Auth::user());
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    $payload = GuiStudioService::mapStructuredEditorPayload($_POST);
    $payload['se_previous_bundle'] = (string)($_POST['se_previous_bundle'] ?? '');
    $payload['reason'] = (string)($_POST['reason'] ?? '');
    $payload['risk_acknowledged'] = !empty($_POST['risk_acknowledged']) ? '1' : '0';
    $payload['migration_override'] = !empty($_POST['migration_override']) ? '1' : '0';
    $payload['migration_override_reason'] = (string)($_POST['migration_override_reason'] ?? '');
    $payload['impact_confirmation'] = !empty($_POST['impact_confirmation']) ? '1' : '0';
    $payload['impact_acknowledged'] = !empty($_POST['impact_acknowledged']) ? '1' : '0';
    $payload['simulation_override'] = !empty($_POST['simulation_override']) ? '1' : '0';
    $payload['simulation_override_reason'] = (string)($_POST['simulation_override_reason'] ?? '');
    $changeContext = $buildStructuredChangeContext($_POST, $payload);

    $report = GuiStudioService::validateBundle($payload);
    $_SESSION['ops_gui_studio_inputs'] = $payload;

    $flowGuard = (array)($changeContext['flow_guard'] ?? []);
    $flowErrors = is_array($flowGuard['errors'] ?? null) ? $flowGuard['errors'] : [];
    $riskEscalation = (array)($changeContext['risk_escalation'] ?? []);
    $riskErrors = is_array($riskEscalation['errors'] ?? null) ? $riskEscalation['errors'] : [];
    $blockingErrors = array_values(array_unique(array_merge($flowErrors, $riskErrors)));
    $migrationGuard = (array)($changeContext['migration_guard'] ?? []);

    $compilePlan = [];
    $diffViewModel = [];
    if ($report['ok'] && $blockingErrors === []) {
        $compilePlan = GuiStudioService::buildCompilePlan($report['decoded']);
        $compilePlan['change_intelligence'] = $changeContext['change_intelligence'];
        $compilePlan['dependency_graph'] = $changeContext['dependency_graph'];
        $compilePlan['impact_analysis'] = $changeContext['impact_analysis'];
        $compilePlan['impact_guard'] = $changeContext['impact_guard'];
    $compilePlan['simulation_preview'] = $changeContext['simulation_preview'];
    $compilePlan['simulation_guard'] = $changeContext['simulation_guard'];
        $compilePlan['migration_plan'] = $changeContext['migration_plan'];
        $compilePlan['migration_guard'] = $migrationGuard;
        $compilePlan['risk_escalation'] = $riskEscalation;
        $analysisFingerprint = $buildPipelineAnalysisFingerprint($report['decoded'], $changeContext);
        $compileId = (string)($compilePlan['compile_meta']['compile_id'] ?? $compilePlan['compile_snapshot']['compile_id'] ?? '');
        $_SESSION['ops_gui_studio_pipeline'] = [
            'analyze' => [
                'analyze_done' => true,
                'analysis_fingerprint' => $analysisFingerprint,
                'compile_id' => $compileId,
                'recorded_at' => gmdate('c'),
            ],
        ];
        $diffViewModel = GuiStudioService::buildDiffViewModel($compilePlan);
        $_SESSION['ops_gui_studio_flash'] = 'ops.gui_studio.flash_compile_plan_ready';
        $_SESSION['ops_gui_studio_error'] = '';
    } else {
        unset($_SESSION['ops_gui_studio_pipeline'], $_SESSION['ops_gui_studio_publish_gate']);
        $_SESSION['ops_gui_studio_flash'] = '';
        $_SESSION['ops_gui_studio_error'] = 'ops.gui_studio.flash_compile_plan_blocked';
    }

    $_SESSION['ops_gui_studio_result'] = [
        'mode' => 'compile_plan',
        'ok' => (bool)$report['ok'] && $blockingErrors === [],
        'checks' => $report['checks'],
        'errors' => array_values(array_unique(array_merge((array)$report['errors'], $blockingErrors))),
        'compile_plan' => $compilePlan,
        'diff_view_model' => $diffViewModel,
        'change_intelligence' => $changeContext['change_intelligence'],
        'dependency_graph' => $changeContext['dependency_graph'],
        'impact_analysis' => $changeContext['impact_analysis'],
        'impact_guard' => $changeContext['impact_guard'],
        'simulation_preview' => $changeContext['simulation_preview'],
        'simulation_guard' => $changeContext['simulation_guard'],
        'migration_plan' => $changeContext['migration_plan'],
        'migration_guard' => $migrationGuard,
        'risk_escalation' => $riskEscalation,
        'flow_guard' => $flowGuard,
    ];

    header('Location: ' . $basePath . '/legacy', true, 302);
    exit;
};

$router->post($basePath . '/compile-plan', $guiStudioCompilePlanHandler);
$router->post($basePath . '/preview', $guiStudioCompilePlanHandler);

$router->post($basePath . '/approval-preview', function () use ($basePath, $view, $buildStructuredChangeContext) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    $payload = GuiStudioService::mapStructuredEditorPayload($_POST);
    $payload['se_previous_bundle'] = (string)($_POST['se_previous_bundle'] ?? '');
    $payload['reason'] = (string)($_POST['reason'] ?? '');
    $payload['risk_acknowledged'] = !empty($_POST['risk_acknowledged']) ? '1' : '0';
    $payload['migration_override'] = !empty($_POST['migration_override']) ? '1' : '0';
    $payload['migration_override_reason'] = (string)($_POST['migration_override_reason'] ?? '');
    $payload['impact_confirmation'] = !empty($_POST['impact_confirmation']) ? '1' : '0';
    $payload['impact_acknowledged'] = !empty($_POST['impact_acknowledged']) ? '1' : '0';
    $payload['simulation_override'] = !empty($_POST['simulation_override']) ? '1' : '0';
    $payload['simulation_override_reason'] = (string)($_POST['simulation_override_reason'] ?? '');
    $changeContext = $buildStructuredChangeContext($_POST, $payload);

    $report = GuiStudioService::validateBundle($payload);
    $compilePlan = [];
    $diffViewModel = [];
    $approvalPayload = [];
    $approvalValidation = ['valid' => false, 'errors' => []];
    $approvalSummary = [];
    $flowGuard = (array)($changeContext['flow_guard'] ?? []);
    $flowErrors = is_array($flowGuard['errors'] ?? null) ? $flowGuard['errors'] : [];
    if ($report['ok'] && $flowErrors === []) {
        $compilePlan = GuiStudioService::buildCompilePlan($report['decoded']);
        $compilePlan['change_intelligence'] = $changeContext['change_intelligence'];
        $compilePlan['dependency_graph'] = $changeContext['dependency_graph'];
        $compilePlan['impact_analysis'] = $changeContext['impact_analysis'];
        $compilePlan['impact_guard'] = $changeContext['impact_guard'];
    $compilePlan['simulation_preview'] = $changeContext['simulation_preview'];
    $compilePlan['simulation_guard'] = $changeContext['simulation_guard'];
        $compilePlan['migration_plan'] = $changeContext['migration_plan'];
        $compilePlan['migration_guard'] = $changeContext['migration_guard'];
        $compilePlan['risk_escalation'] = $changeContext['risk_escalation'];
        $diffViewModel = GuiStudioService::buildDiffViewModel($compilePlan);
        $approvedBy = (string)($user['id'] ?? $user['user_id'] ?? $user['username'] ?? 'current_user');
        $approvalPayload = GuiStudioService::buildApprovalPayload($compilePlan, [
            'approved_by' => $approvedBy,
            'decision' => (string)($_POST['decision'] ?? 'rejected'),
            'reason' => (string)($_POST['reason'] ?? ''),
            'risk_acknowledged' => !empty($_POST['risk_acknowledged']),
        ], (array)($changeContext['change_intelligence'] ?? []), (array)($changeContext['migration_plan'] ?? []), (array)($changeContext['impact_analysis'] ?? []), (array)($changeContext['simulation_preview'] ?? []));
        $approvalValidation = GuiStudioService::validateApproval($approvalPayload);
        $approvalSummary = GuiStudioService::summarizeApproval($approvalPayload);
    }

    $view->render('studio::gui_studio.php', [
        'pageTitle' => t('ops.gui_studio.page_title'),
        'csrf' => \App\Core\Auth::csrfToken(),
        'flash' => $report['ok'] ? 'ops.gui_studio.flash_approval_preview_ready' : '',
        'error' => $report['ok'] ? '' : 'ops.gui_studio.flash_compile_plan_blocked',
        'result' => [
            'mode' => 'approval_preview',
            'ok' => (bool)$report['ok'] && $flowErrors === [],
            'checks' => $report['checks'],
            'errors' => array_values(array_unique(array_merge((array)$report['errors'], $flowErrors))),
            'compile_plan' => $compilePlan,
            'diff_view_model' => $diffViewModel,
            'approval_payload' => $approvalPayload,
            'approval_validation' => $approvalValidation,
            'approval_summary' => $approvalSummary,
            'change_intelligence' => $changeContext['change_intelligence'],
            'dependency_graph' => $changeContext['dependency_graph'],
            'impact_analysis' => $changeContext['impact_analysis'],
            'impact_guard' => $changeContext['impact_guard'],
            'simulation_preview' => $changeContext['simulation_preview'],
            'simulation_guard' => $changeContext['simulation_guard'],
        'migration_plan' => $changeContext['migration_plan'],
            'migration_guard' => $changeContext['migration_guard'],
            'risk_escalation' => $changeContext['risk_escalation'],
            'flow_guard' => $flowGuard,
        ],
        'inputs' => $payload,
        'studioProject' => GuiStudioService::studioProjectModel(),
        'templateLibrary' => GuiStudioService::templateLibrary(),
        'draftLifecycle' => GuiStudioService::draftLifecycle(),
    ]);
    return null;
});

$router->post($basePath . '/snapshot-preview', function () use ($basePath, $view, $buildStructuredChangeContext) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    $payload = GuiStudioService::mapStructuredEditorPayload($_POST);
    $payload['se_previous_bundle'] = (string)($_POST['se_previous_bundle'] ?? '');
    $payload['reason'] = (string)($_POST['reason'] ?? '');
    $payload['risk_acknowledged'] = !empty($_POST['risk_acknowledged']) ? '1' : '0';
    $payload['migration_override'] = !empty($_POST['migration_override']) ? '1' : '0';
    $payload['migration_override_reason'] = (string)($_POST['migration_override_reason'] ?? '');
    $payload['impact_confirmation'] = !empty($_POST['impact_confirmation']) ? '1' : '0';
    $payload['impact_acknowledged'] = !empty($_POST['impact_acknowledged']) ? '1' : '0';
    $payload['simulation_override'] = !empty($_POST['simulation_override']) ? '1' : '0';
    $payload['simulation_override_reason'] = (string)($_POST['simulation_override_reason'] ?? '');
    $changeContext = $buildStructuredChangeContext($_POST, $payload);

    $report = GuiStudioService::validateBundle($payload);
    $compilePlan = [];
    $diffViewModel = [];
    $approvalPayload = [];
    $approvalValidation = ['valid' => false, 'errors' => []];
    $approvalSummary = [];
    $snapshot = [];
    $snapshotSummary = [];
    if ($report['ok']) {
        $compilePlan = GuiStudioService::buildCompilePlan($report['decoded']);
        $compilePlan['change_intelligence'] = $changeContext['change_intelligence'];
        $compilePlan['dependency_graph'] = $changeContext['dependency_graph'];
        $compilePlan['impact_analysis'] = $changeContext['impact_analysis'];
        $compilePlan['impact_guard'] = $changeContext['impact_guard'];
    $compilePlan['simulation_preview'] = $changeContext['simulation_preview'];
    $compilePlan['simulation_guard'] = $changeContext['simulation_guard'];
        $compilePlan['migration_plan'] = $changeContext['migration_plan'];
        $compilePlan['migration_guard'] = $changeContext['migration_guard'];
        $compilePlan['risk_escalation'] = $changeContext['risk_escalation'];
        $diffViewModel = GuiStudioService::buildDiffViewModel($compilePlan);
        $approvedBy = (string)($user['id'] ?? $user['user_id'] ?? $user['username'] ?? 'current_user');
        $approvalPayload = GuiStudioService::buildApprovalPayload($compilePlan, [
            'approved_by' => $approvedBy,
            'decision' => (string)($_POST['decision'] ?? 'rejected'),
            'reason' => (string)($_POST['reason'] ?? ''),
            'risk_acknowledged' => !empty($_POST['risk_acknowledged']),
        ], (array)($changeContext['change_intelligence'] ?? []), (array)($changeContext['migration_plan'] ?? []), (array)($changeContext['impact_analysis'] ?? []), (array)($changeContext['simulation_preview'] ?? []));
        $approvalValidation = GuiStudioService::validateApproval($approvalPayload);
        $approvalSummary = GuiStudioService::summarizeApproval($approvalPayload);
        if (!empty($approvalValidation['valid'])) {
            $snapshot = GuiStudioService::buildSnapshot($compilePlan, $approvalPayload);
            $snapshotSummary = GuiStudioService::summarizeSnapshot($snapshot);
        }
    }

    $view->render('studio::gui_studio.php', [
        'pageTitle' => t('ops.gui_studio.page_title'),
        'csrf' => \App\Core\Auth::csrfToken(),
        'flash' => $report['ok'] ? 'ops.gui_studio.flash_snapshot_preview_ready' : '',
        'error' => $report['ok'] ? '' : 'ops.gui_studio.flash_compile_plan_blocked',
        'result' => [
            'mode' => 'snapshot_preview',
            'ok' => (bool)$report['ok'],
            'checks' => $report['checks'],
            'errors' => $report['errors'],
            'compile_plan' => $compilePlan,
            'diff_view_model' => $diffViewModel,
            'approval_payload' => $approvalPayload,
            'approval_validation' => $approvalValidation,
            'approval_summary' => $approvalSummary,
            'snapshot' => $snapshot,
            'snapshot_summary' => $snapshotSummary,
            'change_intelligence' => $changeContext['change_intelligence'],
            'dependency_graph' => $changeContext['dependency_graph'],
            'impact_analysis' => $changeContext['impact_analysis'],
            'impact_guard' => $changeContext['impact_guard'],
            'simulation_preview' => $changeContext['simulation_preview'],
            'simulation_guard' => $changeContext['simulation_guard'],
        'migration_plan' => $changeContext['migration_plan'],
            'migration_guard' => $changeContext['migration_guard'],
            'risk_escalation' => $changeContext['risk_escalation'],
        ],
        'inputs' => $payload,
        'studioProject' => GuiStudioService::studioProjectModel(),
        'templateLibrary' => GuiStudioService::templateLibrary(),
        'draftLifecycle' => GuiStudioService::draftLifecycle(),
    ]);
    return null;
});

$router->post($basePath . '/execution-preview', function () use ($basePath, $view, $buildStructuredChangeContext) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    $payload = GuiStudioService::mapStructuredEditorPayload($_POST);
    $payload['se_previous_bundle'] = (string)($_POST['se_previous_bundle'] ?? '');
    $payload['reason'] = (string)($_POST['reason'] ?? '');
    $payload['risk_acknowledged'] = !empty($_POST['risk_acknowledged']) ? '1' : '0';
    $payload['migration_override'] = !empty($_POST['migration_override']) ? '1' : '0';
    $payload['migration_override_reason'] = (string)($_POST['migration_override_reason'] ?? '');
    $payload['impact_confirmation'] = !empty($_POST['impact_confirmation']) ? '1' : '0';
    $payload['impact_acknowledged'] = !empty($_POST['impact_acknowledged']) ? '1' : '0';
    $payload['simulation_override'] = !empty($_POST['simulation_override']) ? '1' : '0';
    $payload['simulation_override_reason'] = (string)($_POST['simulation_override_reason'] ?? '');
    $changeContext = $buildStructuredChangeContext($_POST, $payload);

    $report = GuiStudioService::validateBundle($payload);
    $compilePlan = [];
    $diffViewModel = [];
    $approvalPayload = [];
    $approvalValidation = ['valid' => false, 'errors' => []];
    $approvalSummary = [];
    $snapshot = [];
    $snapshotSummary = [];
    $execution = [];
    if ($report['ok']) {
        $compilePlan = GuiStudioService::buildCompilePlan($report['decoded']);
        $compilePlan['change_intelligence'] = $changeContext['change_intelligence'];
        $compilePlan['dependency_graph'] = $changeContext['dependency_graph'];
        $compilePlan['impact_analysis'] = $changeContext['impact_analysis'];
        $compilePlan['impact_guard'] = $changeContext['impact_guard'];
    $compilePlan['simulation_preview'] = $changeContext['simulation_preview'];
    $compilePlan['simulation_guard'] = $changeContext['simulation_guard'];
        $compilePlan['migration_plan'] = $changeContext['migration_plan'];
        $compilePlan['migration_guard'] = $changeContext['migration_guard'];
        $compilePlan['risk_escalation'] = $changeContext['risk_escalation'];
        $diffViewModel = GuiStudioService::buildDiffViewModel($compilePlan);
        $approvedBy = (string)($user['id'] ?? $user['user_id'] ?? $user['username'] ?? 'current_user');
        $approvalPayload = GuiStudioService::buildApprovalPayload($compilePlan, [
            'approved_by' => $approvedBy,
            'decision' => (string)($_POST['decision'] ?? 'rejected'),
            'reason' => (string)($_POST['reason'] ?? ''),
            'risk_acknowledged' => !empty($_POST['risk_acknowledged']),
        ], (array)($changeContext['change_intelligence'] ?? []), (array)($changeContext['migration_plan'] ?? []), (array)($changeContext['impact_analysis'] ?? []), (array)($changeContext['simulation_preview'] ?? []));
        $approvalValidation = GuiStudioService::validateApproval($approvalPayload);
        $approvalSummary = GuiStudioService::summarizeApproval($approvalPayload);
        if (!empty($approvalValidation['valid'])) {
            $snapshot = GuiStudioService::buildSnapshot($compilePlan, $approvalPayload);
            $snapshotSummary = GuiStudioService::summarizeSnapshot($snapshot);
            $execution = GuiStudioService::simulateExecution($snapshot);
        }
    }

    $view->render('studio::gui_studio.php', [
        'pageTitle' => t('ops.gui_studio.page_title'),
        'csrf' => \App\Core\Auth::csrfToken(),
        'flash' => $report['ok'] ? 'ops.gui_studio.flash_execution_preview_ready' : '',
        'error' => $report['ok'] ? '' : 'ops.gui_studio.flash_compile_plan_blocked',
        'result' => [
            'mode' => 'execution_preview',
            'ok' => (bool)$report['ok'],
            'checks' => $report['checks'],
            'errors' => $report['errors'],
            'compile_plan' => $compilePlan,
            'diff_view_model' => $diffViewModel,
            'approval_payload' => $approvalPayload,
            'approval_validation' => $approvalValidation,
            'approval_summary' => $approvalSummary,
            'snapshot' => $snapshot,
            'snapshot_summary' => $snapshotSummary,
            'execution' => $execution,
            'change_intelligence' => $changeContext['change_intelligence'],
            'dependency_graph' => $changeContext['dependency_graph'],
            'impact_analysis' => $changeContext['impact_analysis'],
            'impact_guard' => $changeContext['impact_guard'],
            'simulation_preview' => $changeContext['simulation_preview'],
            'simulation_guard' => $changeContext['simulation_guard'],
        'migration_plan' => $changeContext['migration_plan'],
            'migration_guard' => $changeContext['migration_guard'],
            'risk_escalation' => $changeContext['risk_escalation'],
        ],
        'inputs' => $payload,
        'studioProject' => GuiStudioService::studioProjectModel(),
        'templateLibrary' => GuiStudioService::templateLibrary(),
        'draftLifecycle' => GuiStudioService::draftLifecycle(),
    ]);
    return null;
});

$guiStudioApplySnapshotHandler = function () use ($basePath, $view, $buildStructuredChangeContext) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    $payload = GuiStudioService::mapStructuredEditorPayload($_POST);
    $payload['se_previous_bundle'] = (string)($_POST['se_previous_bundle'] ?? '');
    $payload['reason'] = (string)($_POST['reason'] ?? '');
    $payload['risk_acknowledged'] = !empty($_POST['risk_acknowledged']) ? '1' : '0';
    $payload['migration_override'] = !empty($_POST['migration_override']) ? '1' : '0';
    $payload['migration_override_reason'] = (string)($_POST['migration_override_reason'] ?? '');
    $payload['impact_confirmation'] = !empty($_POST['impact_confirmation']) ? '1' : '0';
    $payload['impact_acknowledged'] = !empty($_POST['impact_acknowledged']) ? '1' : '0';
    $payload['simulation_override'] = !empty($_POST['simulation_override']) ? '1' : '0';
    $payload['simulation_override_reason'] = (string)($_POST['simulation_override_reason'] ?? '');
    $changeContext = $buildStructuredChangeContext($_POST, $payload);

    // Rebuild minimal first-apply chain: validate → generated plan → approval → snapshot → generate.
    $report = GuiStudioService::validateBundle($payload);
    $compilePlan = [];
    $diffViewModel = [];
    $approvalPayload = [];
    $approvalValidation = ['valid' => false, 'errors' => []];
    $approvalSummary = [];
    $snapshot = [];
    $snapshotSummary = [];
    $execution = [];
    $apply = [];
    $flowGuard = (array)($changeContext['flow_guard'] ?? []);
    $flowErrors = is_array($flowGuard['errors'] ?? null) ? $flowGuard['errors'] : [];

    if (!$report['ok'] || $flowErrors !== []) {
        $view->render('studio::gui_studio.php', [
            'pageTitle' => t('ops.gui_studio.page_title'),
            'csrf' => \App\Core\Auth::csrfToken(),
            'flash' => '',
            'error' => 'ops.gui_studio.flash_compile_plan_blocked',
            'result' => [
                'mode' => 'apply',
                'ok' => false,
                'checks' => $report['checks'],
                'errors' => array_values(array_unique(array_merge((array)$report['errors'], $flowErrors))),
                'compile_plan' => [],
                'diff_view_model' => [],
                'approval_payload' => [],
                'approval_validation' => $approvalValidation,
                'approval_summary' => [],
                'snapshot' => [],
                'snapshot_summary' => [],
                'execution' => [],
                'apply' => [],
                'change_intelligence' => $changeContext['change_intelligence'],
                'dependency_graph' => $changeContext['dependency_graph'],
                'impact_analysis' => $changeContext['impact_analysis'],
                'impact_guard' => $changeContext['impact_guard'],
                'simulation_preview' => $changeContext['simulation_preview'],
                'simulation_guard' => $changeContext['simulation_guard'],
                'migration_plan' => $changeContext['migration_plan'],
                'migration_guard' => $changeContext['migration_guard'],
                'risk_escalation' => $changeContext['risk_escalation'],
                'flow_guard' => $flowGuard,
            ],
            'inputs' => $payload,
            'studioProject' => GuiStudioService::studioProjectModel(),
            'templateLibrary' => GuiStudioService::templateLibrary(),
            'draftLifecycle' => GuiStudioService::draftLifecycle(),
        ]);
        return null;
    }

    $compilePlan = GuiStudioService::buildGeneratedApplyPlan($report['decoded']);
    $compilePlan['change_intelligence'] = $changeContext['change_intelligence'];
    $compilePlan['dependency_graph'] = $changeContext['dependency_graph'];
    $compilePlan['impact_analysis'] = $changeContext['impact_analysis'];
    $compilePlan['impact_guard'] = $changeContext['impact_guard'];
    $compilePlan['simulation_preview'] = $changeContext['simulation_preview'];
    $compilePlan['simulation_guard'] = $changeContext['simulation_guard'];
    $compilePlan['migration_plan'] = $changeContext['migration_plan'];
    $compilePlan['migration_guard'] = $changeContext['migration_guard'];
    $compilePlan['risk_escalation'] = $changeContext['risk_escalation'];
    $diffViewModel = GuiStudioService::buildDiffViewModel($compilePlan);
    $adminHandle = trim((string)($user['handle'] ?? $user['email'] ?? 'unknown'));
    $approvedBy = $adminHandle !== '' ? $adminHandle : (string)($user['username'] ?? 'current_user');
    $approvalPayload = GuiStudioService::buildApprovalPayload($compilePlan, [
        'approved_by' => $approvedBy,
        'decision' => (string)($_POST['decision'] ?? 'approved'),
        'reason' => (string)($_POST['reason'] ?? ''),
        'risk_acknowledged' => !empty($_POST['risk_acknowledged']),
    ], (array)($changeContext['change_intelligence'] ?? []), (array)($changeContext['migration_plan'] ?? []), (array)($changeContext['impact_analysis'] ?? []), (array)($changeContext['simulation_preview'] ?? []));
    $approvalValidation = GuiStudioService::validateApproval($approvalPayload);
    $approvalSummary = GuiStudioService::summarizeApproval($approvalPayload);
    $preflightResult = StudioGovernanceService::preflightCheck($report['decoded']);
    $lintResult = StudioGovernanceService::lintGeneratedBundle($report['decoded']);
    $publishGateResult = ['ok' => false, 'errors' => ['publish_gate_token_missing'], 'gate_checks' => [], 'apply_mode' => GuiStudioService::APPLY_MODE];
    $publishDecision = [];
    $publishGateToken = is_array($_SESSION['ops_gui_studio_publish_gate'] ?? null) ? $_SESSION['ops_gui_studio_publish_gate'] : [];
    $snapshot = [];
    $snapshotSummary = [];
    $execution = [];
    $apply = [];

    $migrationGuard = (array)($changeContext['migration_guard'] ?? []);
    $impactGuard = (array)($changeContext['impact_guard'] ?? []);
    $simulationGuard = (array)($changeContext['simulation_guard'] ?? []);
    $pipelineState = is_array($_SESSION['ops_gui_studio_pipeline'] ?? null) ? $_SESSION['ops_gui_studio_pipeline'] : [];
    $confirmState = is_array($pipelineState['confirm'] ?? null) ? $pipelineState['confirm'] : [];
    $pipelineGuard = [
        'requires_sequence' => true,
        'analyze_present' => is_array($pipelineState['analyze'] ?? null),
        'confirm_present' => $confirmState !== [],
        'valid' => false,
        'errors' => [],
    ];

    if (!empty($approvalValidation['valid']) && !empty($migrationGuard['valid']) && !empty($impactGuard['valid']) && !empty($simulationGuard['valid']) && $publishGateToken !== []) {
        $tokenCompileId = (string)($publishGateToken['compile_id'] ?? '');
        $tokenApprovalId = (string)($publishGateToken['approval_id'] ?? '');
        $currentCompileId = (string)($compilePlan['compile_meta']['compile_id'] ?? $compilePlan['compile_snapshot']['compile_id'] ?? '');
        $currentApprovalId = (string)($approvalPayload['approval_id'] ?? '');
        $confirmCompileId = trim((string)($confirmState['compile_id'] ?? ''));
        $confirmApprovalId = trim((string)($confirmState['approval_id'] ?? ''));
        if ($confirmState === []) {
            $pipelineGuard['errors'][] = 'safe_pipeline_confirm_required';
        } elseif ($confirmCompileId !== $currentCompileId || $confirmApprovalId !== $currentApprovalId) {
            $pipelineGuard['errors'][] = 'safe_pipeline_context_mismatch';
        }
        if ($tokenCompileId === $currentCompileId && $tokenApprovalId === $currentApprovalId && $pipelineGuard['errors'] === []) {
            $pipelineGuard['valid'] = true;
            $publishGateResult = StudioGovernanceService::publishGateCheck($compilePlan, $approvalPayload, $preflightResult, $lintResult);
        } else {
            $publishGateResult['errors'] = $pipelineGuard['errors'] !== [] ? $pipelineGuard['errors'] : ['publish_gate_token_mismatch'];
        }
    } else {
        if ($confirmState === []) {
            $pipelineGuard['errors'][] = 'safe_pipeline_confirm_required';
        }
    }

    if (!empty($approvalValidation['valid']) && !empty($impactGuard['valid']) && !empty($simulationGuard['valid']) && !empty($publishGateResult['ok']) && !empty($pipelineGuard['valid'])) {
        $publishDecision = [
            'record_id' => (string)($publishGateToken['publish_decision_id'] ?? ''),
            'decided_by' => (string)($publishGateToken['approved_by'] ?? $approvedBy),
            'decided_at' => (string)($publishGateToken['approval_timestamp'] ?? gmdate('c')),
        ];
        $approvalPayload['publish_approved'] = true;
        $approvalPayload['publish_gate_status'] = (string)($publishGateToken['publish_gate_status'] ?? 'PASSED');
        $approvalPayload['publish_decision_id'] = (string)($publishGateToken['publish_decision_id'] ?? '');
        $approvalPayload['approved_by'] = (string)($publishGateToken['approved_by'] ?? $approvedBy);
        $approvalPayload['approval_timestamp'] = (string)($publishGateToken['approval_timestamp'] ?? gmdate('c'));
        if (!empty($publishGateResult['ok'])) {
            $snapshot = GuiStudioService::buildSnapshot($compilePlan, $approvalPayload);
            $snapshotSummary = GuiStudioService::summarizeSnapshot($snapshot);
            $execution = GuiStudioService::simulateExecution($snapshot);
            // Only apply if execution simulation passed
            if (!empty($execution['can_execute'])) {
                $apply = GuiStudioService::applyGeneratedSnapshot($snapshot, $report['decoded']);
            }
        }
    }

    $applyOk = !empty($apply) && ($apply['status'] ?? '') === 'APPLIED';
    $migrationErrors = is_array($migrationGuard['errors'] ?? null) ? $migrationGuard['errors'] : [];
    $impactErrors = is_array($impactGuard['errors'] ?? null) ? $impactGuard['errors'] : [];
    $simulationErrors = is_array($simulationGuard['errors'] ?? null) ? $simulationGuard['errors'] : [];
    $applyErrorKey = !$applyOk && $migrationErrors !== []
        ? 'ops.gui_studio.flash_apply_failed'
        : (!$applyOk && $impactErrors !== []
        ? 'ops.gui_studio.flash_apply_failed'
        : (!$applyOk && $simulationErrors !== []
        ? 'ops.gui_studio.flash_apply_failed'
        : (!$applyOk && empty($publishGateResult['ok'])
        ? 'ops.gui_studio.flash_publish_gate_failed'
        : ((!$applyOk) ? 'ops.gui_studio.flash_apply_failed' : ''))));
    if ($applyOk) {
        unset($_SESSION['ops_gui_studio_publish_gate'], $_SESSION['ops_gui_studio_pipeline']);
    }

    $view->render('studio::gui_studio.php', [
        'pageTitle' => t('ops.gui_studio.page_title'),
        'csrf' => \App\Core\Auth::csrfToken(),
        'flash' => $applyOk ? 'ops.gui_studio.flash_apply_applied' : '',
        'error' => $applyErrorKey,
        'result' => [
            'mode' => 'apply',
            'ok' => (bool)$report['ok'],
            'checks' => $report['checks'],
            'errors' => array_values(array_unique(array_merge((array)$report['errors'], $migrationErrors, $impactErrors, $simulationErrors))),
            'compile_plan' => $compilePlan,
            'diff_view_model' => $diffViewModel,
            'approval_payload' => $approvalPayload,
            'approval_validation' => $approvalValidation,
            'approval_summary' => $approvalSummary,
            'publish_gate' => $publishGateResult,
            'publish_decision' => $publishDecision,
            'snapshot' => $snapshot,
            'snapshot_summary' => $snapshotSummary,
            'execution' => $execution,
            'apply' => $apply,
            'rollback' => [],
            'apply_mode' => 'first_apply',
            'change_intelligence' => $changeContext['change_intelligence'],
            'dependency_graph' => $changeContext['dependency_graph'],
            'impact_analysis' => $changeContext['impact_analysis'],
            'impact_guard' => $impactGuard,
            'pipeline_guard' => $pipelineGuard,
            'simulation_preview' => $changeContext['simulation_preview'],
            'simulation_guard' => $simulationGuard,
            'migration_plan' => $changeContext['migration_plan'],
            'migration_guard' => $migrationGuard,
            'risk_escalation' => $changeContext['risk_escalation'],
        ],
        'inputs' => $payload,
        'studioProject' => GuiStudioService::studioProjectModel(),
        'templateLibrary' => GuiStudioService::templateLibrary(),
        'draftLifecycle' => GuiStudioService::draftLifecycle(),
    ]);
    return null;
};

$router->post($basePath . '/apply-snapshot', $guiStudioApplySnapshotHandler);
$router->post($basePath . '/apply', $guiStudioApplySnapshotHandler);

// --- Studio Direct DB Table Editor API ---
$router->get($basePath . '/db-rows', function () use ($basePath) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'unauthenticated']);
        return null;
    }
    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'forbidden']);
        return null;
    }

    $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($_GET['app_key'] ?? ''));
    $tableTypeRaw = strtolower(trim((string)($_GET['table_type'] ?? 'orders')));
    $allowedTypes = ['orders' => true, 'parts' => true];
    if (!isset($allowedTypes[$tableTypeRaw])) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'invalid_table_type']);
        return null;
    }

    $tableName = $tableTypeRaw === 'orders'
        ? StudioSchemaGovernanceService::ordersTable($appKey)
        : StudioSchemaGovernanceService::partsTable($appKey);

    // Verify table exists
    $exists = \App\Core\DB::fetchOne(
        'SELECT 1 AS present FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
        [$tableName]
    );
    if (!$exists) {
        StudioSchemaGovernanceService::ensureSchema($appKey);
    }

    $quotedTable = '`' . str_replace('`', '``', $tableName) . '`';
    $schema = \App\Core\DB::fetchAll('SHOW COLUMNS FROM ' . $quotedTable);
    $rows   = \App\Core\DB::fetchAll('SELECT * FROM ' . $quotedTable . ' ORDER BY id DESC LIMIT 100');

    header('Content-Type: application/json');
    echo json_encode(['schema' => $schema, 'rows' => $rows, 'table' => $tableName], JSON_UNESCAPED_UNICODE);
    return null;
});

$router->post($basePath . '/db-rows/save', function () use ($basePath) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'unauthenticated']);
        return null;
    }
    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        return null;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    $appKey = StudioSchemaGovernanceService::sanitizeAppKey((string)($_POST['app_key'] ?? ''));
    $tableTypeRaw = strtolower(trim((string)($_POST['table_type'] ?? 'orders')));
    $allowedTypes = ['orders' => true, 'parts' => true];
    if (!isset($allowedTypes[$tableTypeRaw])) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'invalid_table_type']);
        return null;
    }

    $tableName = $tableTypeRaw === 'orders'
        ? StudioSchemaGovernanceService::ordersTable($appKey)
        : StudioSchemaGovernanceService::partsTable($appKey);

    $quotedTable = '`' . str_replace('`', '``', $tableName) . '`';
    $action = strtolower(trim((string)($_POST['action'] ?? 'save')));
    $readOnlyCols = ['id' => true, 'created_at' => true];

    // Get current schema to validate allowed columns
    $schema = \App\Core\DB::fetchAll('SHOW COLUMNS FROM ' . $quotedTable);
    $allowedCols = [];
    foreach ($schema as $col) {
        $colName = (string)($col['Field'] ?? $col['field'] ?? '');
        if ($colName !== '' && !isset($readOnlyCols[$colName])) {
            $allowedCols[$colName] = true;
        }
    }

    header('Content-Type: application/json');

    if ($action === 'delete') {
        $rowId = (int)($_POST['row_id'] ?? 0);
        if ($rowId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'invalid_row_id']);
            return null;
        }
        $stmt = \App\Core\DB::conn()->prepare('DELETE FROM ' . $quotedTable . ' WHERE id = ? LIMIT 1');
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'db_error']);
            return null;
        }
        $stmt->bind_param('i', $rowId);
        $ok = $stmt->execute();
        echo json_encode(['ok' => $ok]);
        return null;
    }

    // Save (insert or update)
    $rowJson = (string)($_POST['row_json'] ?? '{}');
    $rowData = json_decode($rowJson, true);
    if (!is_array($rowData)) {
        echo json_encode(['ok' => false, 'error' => 'invalid_row_json']);
        return null;
    }

    // Strip disallowed columns
    $safeData = [];
    foreach ($rowData as $colName => $colValue) {
        $colName = (string)$colName;
        if (isset($allowedCols[$colName])) {
            $safeData[$colName] = (string)$colValue;
        }
    }

    if (empty($safeData)) {
        echo json_encode(['ok' => false, 'error' => 'no_writable_fields']);
        return null;
    }

    $rowId = isset($rowData['id']) && (int)$rowData['id'] > 0 ? (int)$rowData['id'] : 0;

    if ($rowId > 0) {
        // UPDATE
        $setClauses = implode(', ', array_map(static fn($k) => '`' . str_replace('`', '``', $k) . '` = ?', array_keys($safeData)));
        $values = array_values($safeData);
        $values[] = $rowId;
        $types = str_repeat('s', count($safeData)) . 'i';
        $stmt = \App\Core\DB::conn()->prepare('UPDATE ' . $quotedTable . ' SET ' . $setClauses . ' WHERE id = ? LIMIT 1');
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'db_error']);
            return null;
        }
        $stmt->bind_param($types, ...$values);
        $ok = $stmt->execute();
    } else {
        // INSERT
        $cols = implode(', ', array_map(static fn($k) => '`' . str_replace('`', '``', $k) . '`', array_keys($safeData)));
        $placeholders = implode(', ', array_fill(0, count($safeData), '?'));
        $types = str_repeat('s', count($safeData));
        $stmt = \App\Core\DB::conn()->prepare('INSERT INTO ' . $quotedTable . ' (' . $cols . ') VALUES (' . $placeholders . ')');
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'db_error']);
            return null;
        }
        $stmt->bind_param($types, ...array_values($safeData));
        $ok = $stmt->execute();
    }

    echo json_encode(['ok' => (bool)$ok]);
    return null;
});
// --- End Studio Direct DB Table Editor API ---

$router->post($basePath . '/rollback', function () use ($basePath, $view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    $compileId = trim((string)($_POST['compile_id'] ?? ''));
    $rollbackResult = $compileId !== ''
        ? GuiStudioService::rollbackGeneratedSnapshot($compileId)
        : [
            'status' => 'FAILED',
            'precondition_failures' => ['no_compile_id'],
            'steps' => [],
            'summary' => ['total' => 0, 'done' => 0, 'failed' => 0, 'skipped' => 0],
        ];
    $rollbackOk = ($rollbackResult['status'] ?? '') === 'COMPLETED';

    $view->render('studio::gui_studio.php', [
        'pageTitle' => t('ops.gui_studio.page_title'),
        'csrf' => \App\Core\Auth::csrfToken(),
        'flash' => $rollbackOk ? 'ops.gui_studio.flash_rollback_executed' : '',
        'error' => $rollbackOk ? '' : 'ops.gui_studio.flash_rollback_execute_failed',
        'result' => [
            'mode' => 'rollback_execute',
            'ok' => $rollbackOk,
            'checks' => [],
            'errors' => is_array($rollbackResult['precondition_failures'] ?? null) ? $rollbackResult['precondition_failures'] : [],
            'compile_plan' => [],
            'diff_view_model' => [],
            'approval_payload' => [],
            'approval_validation' => ['valid' => false, 'errors' => []],
            'approval_summary' => [],
            'snapshot' => [],
            'snapshot_summary' => [],
            'execution' => [],
            'apply' => [],
            'rollback' => [],
            'apply_mode' => 'first_apply',
            'rollback_execute' => $rollbackResult,
            'rollback_blocked' => !$rollbackOk && empty($rollbackResult['steps']),
            'rollback_block_reason' => (string)(($rollbackResult['precondition_failures'] ?? ['rollback_failed'])[0] ?? 'rollback_failed'),
            'rollback_non_reversible_warning' => false,
        ],
        'inputs' => [],
        'studioProject' => GuiStudioService::studioProjectModel(),
        'templateLibrary' => GuiStudioService::templateLibrary(),
        'draftLifecycle' => GuiStudioService::draftLifecycle(),
    ]);
    return null;
});

$router->post($basePath . '/rollback-preview', function () use ($basePath, $view, $buildStructuredChangeContext) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    $payload = GuiStudioService::mapStructuredEditorPayload($_POST);
    $payload['se_previous_bundle'] = (string)($_POST['se_previous_bundle'] ?? '');
    $payload['reason'] = (string)($_POST['reason'] ?? '');
    $payload['risk_acknowledged'] = !empty($_POST['risk_acknowledged']) ? '1' : '0';
    $payload['migration_override'] = !empty($_POST['migration_override']) ? '1' : '0';
    $payload['migration_override_reason'] = (string)($_POST['migration_override_reason'] ?? '');
    $payload['impact_confirmation'] = !empty($_POST['impact_confirmation']) ? '1' : '0';
    $payload['impact_acknowledged'] = !empty($_POST['impact_acknowledged']) ? '1' : '0';
    $payload['simulation_override'] = !empty($_POST['simulation_override']) ? '1' : '0';
    $payload['simulation_override_reason'] = (string)($_POST['simulation_override_reason'] ?? '');
    $changeContext = $buildStructuredChangeContext($_POST, $payload);

    // Rebuild pipeline: validate → compile → approval → snapshot
    $report = GuiStudioService::validateBundle($payload);
    $compilePlan = [];
    $diffViewModel = [];
    $approvalPayload = [];
    $approvalValidation = ['valid' => false, 'errors' => []];
    $approvalSummary = [];
    $snapshot = [];
    $snapshotSummary = [];
    $rollback = [];

    if ($report['ok']) {
        $compilePlan = GuiStudioService::buildCompilePlan($report['decoded']);
        $compilePlan['change_intelligence'] = $changeContext['change_intelligence'];
        $compilePlan['dependency_graph'] = $changeContext['dependency_graph'];
        $compilePlan['impact_analysis'] = $changeContext['impact_analysis'];
        $compilePlan['impact_guard'] = $changeContext['impact_guard'];
    $compilePlan['simulation_preview'] = $changeContext['simulation_preview'];
    $compilePlan['simulation_guard'] = $changeContext['simulation_guard'];
        $compilePlan['migration_plan'] = $changeContext['migration_plan'];
        $compilePlan['migration_guard'] = $changeContext['migration_guard'];
        $compilePlan['risk_escalation'] = $changeContext['risk_escalation'];
        $diffViewModel = GuiStudioService::buildDiffViewModel($compilePlan);
        $approvedBy = (string)($user['id'] ?? $user['user_id'] ?? $user['username'] ?? 'current_user');
        $approvalPayload = GuiStudioService::buildApprovalPayload($compilePlan, [
            'approved_by' => $approvedBy,
            'decision' => (string)($_POST['decision'] ?? 'approved'),
            'reason' => (string)($_POST['reason'] ?? ''),
            'risk_acknowledged' => !empty($_POST['risk_acknowledged']),
        ], (array)($changeContext['change_intelligence'] ?? []), (array)($changeContext['migration_plan'] ?? []), (array)($changeContext['impact_analysis'] ?? []), (array)($changeContext['simulation_preview'] ?? []));
        $approvalValidation = GuiStudioService::validateApproval($approvalPayload);
        $approvalSummary = GuiStudioService::summarizeApproval($approvalPayload);
        if (!empty($approvalValidation['valid'])) {
            $snapshot = GuiStudioService::buildSnapshot($compilePlan, $approvalPayload);
            $snapshotSummary = GuiStudioService::summarizeSnapshot($snapshot);
            // Build rollback plan — no execution, no persistence
            $rollback = GuiStudioService::buildRollbackPlan($snapshot);
        }
    }

    $view->render('studio::gui_studio.php', [
        'pageTitle' => t('ops.gui_studio.page_title'),
        'csrf' => \App\Core\Auth::csrfToken(),
        'flash' => !empty($rollback) ? 'ops.gui_studio.flash_rollback_preview_ready' : '',
        'error' => $report['ok'] ? '' : 'ops.gui_studio.flash_compile_plan_blocked',
        'result' => [
            'mode' => 'rollback_preview',
            'ok' => (bool)$report['ok'],
            'checks' => $report['checks'],
            'errors' => $report['errors'],
            'compile_plan' => $compilePlan,
            'diff_view_model' => $diffViewModel,
            'approval_payload' => $approvalPayload,
            'approval_validation' => $approvalValidation,
            'approval_summary' => $approvalSummary,
            'snapshot' => $snapshot,
            'snapshot_summary' => $snapshotSummary,
            'execution' => [],
            'apply' => [],
            'rollback' => $rollback,
            'change_intelligence' => $changeContext['change_intelligence'],
            'dependency_graph' => $changeContext['dependency_graph'],
            'impact_analysis' => $changeContext['impact_analysis'],
            'impact_guard' => $changeContext['impact_guard'],
            'simulation_preview' => $changeContext['simulation_preview'],
            'simulation_guard' => $changeContext['simulation_guard'],
        'migration_plan' => $changeContext['migration_plan'],
            'migration_guard' => $changeContext['migration_guard'],
            'risk_escalation' => $changeContext['risk_escalation'],
        ],
        'inputs' => $payload,
        'studioProject' => GuiStudioService::studioProjectModel(),
        'templateLibrary' => GuiStudioService::templateLibrary(),
        'draftLifecycle' => GuiStudioService::draftLifecycle(),
    ]);
    return null;
});

$router->post($basePath . '/rollback-execute', function () use ($basePath, $view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '');

    $applyId = trim((string)($_POST['apply_id'] ?? ''));
    $applyRecord = [];
    $rollbackResult = [];
    $rollbackBlocked = false;
    $rollbackBlockReason = '';
    $nonReversibleWarning = false;

    if ($applyId === '') {
        $rollbackBlocked = true;
        $rollbackBlockReason = 'no_apply_id';
    } else {
        $applyRecord = GuiStudioService::loadApplyRecord($applyId);
        if ($applyRecord === []) {
            $rollbackBlocked = true;
            $rollbackBlockReason = 'apply_record_not_found';
        } else {
            $rollbackBinding = is_array($applyRecord['rollback_binding'] ?? null) ? $applyRecord['rollback_binding'] : [];
            if ($rollbackBinding === []) {
                $rollbackBlocked = true;
                $rollbackBlockReason = 'no_rollback_binding';
            } else {
                $bindingArtifacts = is_array($rollbackBinding['artifacts'] ?? null) ? $rollbackBinding['artifacts'] : [];
                $reversibleCount = count(array_filter($bindingArtifacts, static fn($s) => is_array($s) && !empty($s['reversible'])));
                $allNonReversible = $bindingArtifacts !== [] && $reversibleCount === 0;
                if ($allNonReversible) {
                    $rollbackBlocked = true;
                    $rollbackBlockReason = 'all_steps_non_reversible';
                } elseif ($reversibleCount < count($bindingArtifacts)) {
                    $nonReversibleWarning = true;
                }
            }
        }
    }

    if (!$rollbackBlocked) {
        $rollbackResult = GuiStudioService::executeRollback($applyRecord);
    }

    $rollbackOk = !empty($rollbackResult) && ($rollbackResult['status'] ?? '') === 'COMPLETED';

    $view->render('studio::gui_studio.php', [
        'pageTitle' => t('ops.gui_studio.page_title'),
        'csrf' => \App\Core\Auth::csrfToken(),
        'flash' => $rollbackOk ? 'ops.gui_studio.flash_rollback_executed' : '',
        'error' => $rollbackBlocked ? 'ops.gui_studio.flash_rollback_blocked' : (!$rollbackOk && !empty($rollbackResult) ? 'ops.gui_studio.flash_rollback_execute_failed' : ''),
        'result' => [
            'mode' => 'rollback_execute',
            'ok' => !$rollbackBlocked && $rollbackOk,
            'checks' => [],
            'errors' => $rollbackBlocked ? [$rollbackBlockReason] : [],
            'compile_plan' => [],
            'diff_view_model' => [],
            'approval_payload' => [],
            'approval_validation' => ['valid' => false, 'errors' => []],
            'approval_summary' => [],
            'snapshot' => [],
            'snapshot_summary' => [],
            'execution' => [],
            'apply' => $applyRecord,
            'rollback' => [],
            'apply_mode' => GuiStudioService::APPLY_MODE,
            'rollback_execute' => $rollbackResult,
            'rollback_blocked' => $rollbackBlocked,
            'rollback_block_reason' => $rollbackBlockReason,
            'rollback_non_reversible_warning' => $nonReversibleWarning,
        ],
        'inputs' => [],
        'studioProject' => GuiStudioService::studioProjectModel(),
        'templateLibrary' => GuiStudioService::templateLibrary(),
        'draftLifecycle' => GuiStudioService::draftLifecycle(),
    ]);
    return null;
});

$router->get($basePath . '/history', function () use ($basePath, $view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    $historyPageTitle = match (current_lang()) {
        'ja' => 'Studio履歴',
        'ne' => 'Studio इतिहास',
        default => 'Studio History',
    };

    $view->render('studio::gui_studio_history.php', [
        'pageTitle' => $historyPageTitle,
        'csrf' => \App\Core\Auth::csrfToken(),
        'snapshotHistory' => GuiStudioService::loadSnapshotHistory(),
        'applyHistory' => GuiStudioService::loadApplyHistory(),
        'eventHistory' => GuiStudioService::loadHistory(),
        'packageHistory' => GuiStudioService::loadPackageHistory(),
        'registryEntries' => GuiStudioService::loadRegistry(),
        'exportedPackagePath' => '',
        'registryInstalled' => false,
    ]);
    return null;
});

$router->get($basePath . '/tools/nav-composer', function () use ($view, $router) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }

    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }

    $pageTitle = match (current_lang()) {
        'ja' => 'Nav Composer',
        'ne' => 'Nav Composer',
        default => 'Nav Composer',
    };

    $candidateData = StudioNavCandidateProviderService::listCandidates($router->listRoutes());
    $candidateFields = StudioNavCandidateProviderService::contractFields();

    $view->render('studio::gui_studio_nav_composer.php', [
        'pageTitle' => $pageTitle,
        'candidateFields' => $candidateFields,
        'candidates' => is_array($candidateData['candidates'] ?? null) ? $candidateData['candidates'] : [],
        'candidateCounts' => is_array($candidateData['counts'] ?? null) ? $candidateData['counts'] : [],
        'candidateSourceCounts' => is_array($candidateData['source_counts'] ?? null) ? $candidateData['source_counts'] : [],
    ]);
    return null;
});

$router->post($basePath . '/export-package', function () use ($basePath, $view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '/history');

    // Reload snapshot from session-built snapshot or supplied snapshot_id
    $snapshotId = preg_replace('/[^a-zA-Z0-9\-]/', '', (string)($_POST['snapshot_id'] ?? ''));
    if ($snapshotId === '') {
        header('Location: ' . $basePath . '/legacy?error=export_no_snapshot_id', true, 302);
        exit;
    }

    $snapshot = GuiStudioService::loadSnapshotRecord($snapshotId);
    if (empty($snapshot)) {
        header('Location: ' . $basePath . '/legacy?error=export_snapshot_not_found', true, 302);
        exit;
    }

    $packagePath = GuiStudioService::persistSnapshotPackage($snapshot);
    if ($packagePath === null) {
        header('Location: ' . $basePath . '/legacy?error=export_package_failed', true, 302);
        exit;
    }

    $packageHistory = GuiStudioService::loadPackageHistory();
    $historyPageTitle = match (current_lang()) {
        'ja' => 'Studio履歴',
        'ne' => 'Studio इतिहास',
        default => 'Studio History',
    };

    $view->render('studio::gui_studio_history.php', [
        'pageTitle' => $historyPageTitle,
        'csrf' => \App\Core\Auth::csrfToken(),
        'snapshotHistory' => GuiStudioService::loadSnapshotHistory(),
        'applyHistory' => GuiStudioService::loadApplyHistory(),
        'eventHistory' => GuiStudioService::loadHistory(),
        'packageHistory' => $packageHistory,
        'registryEntries' => GuiStudioService::loadRegistry(),
        'exportedPackagePath' => $packagePath,
        'registryInstalled' => false,
    ]);
    return null;
});

$router->post($basePath . '/registry-install', function () use ($basePath, $view) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        header('Location: /login', true, 302);
        exit;
    }
    $user = \App\Core\Auth::user();
    $ctx = platform_user_context_contract()->resolveUserContext($user);
    $role = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
    if ($role !== 'platform_admin') {
        header('Location: /ops/dashboard', true, 302);
        exit;
    }
    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $basePath . '/history');

    $snapshotId = preg_replace('/[^a-zA-Z0-9\-]/', '', (string)($_POST['snapshot_id'] ?? ''));
    if ($snapshotId === '') {
        header('Location: ' . $basePath . '/history?error=registry_no_snapshot_id', true, 302);
        exit;
    }

    // Load snapshot + derive package manifest
    $snapshot = GuiStudioService::loadSnapshotRecord($snapshotId);
    if (empty($snapshot)) {
        header('Location: ' . $basePath . '/history?error=registry_snapshot_not_found', true, 302);
        exit;
    }

    // Ensure package exists (create if not)
    $packagePath = GuiStudioService::persistSnapshotPackage($snapshot);
    if ($packagePath === null) {
        header('Location: ' . $basePath . '/history?error=registry_package_failed', true, 302);
        exit;
    }

    $packageData = GuiStudioService::exportSnapshotPackage($snapshot);
    GuiStudioService::registerPackage($packageData['manifest'], $packagePath);

    $historyPageTitle = match (current_lang()) {
        'ja' => 'Studio履歴',
        'ne' => 'Studio इतिहास',
        default => 'Studio History',
    };

    $view->render('studio::gui_studio_history.php', [
        'pageTitle' => $historyPageTitle,
        'csrf' => \App\Core\Auth::csrfToken(),
        'snapshotHistory' => GuiStudioService::loadSnapshotHistory(),
        'applyHistory' => GuiStudioService::loadApplyHistory(),
        'eventHistory' => GuiStudioService::loadHistory(),
        'packageHistory' => GuiStudioService::loadPackageHistory(),
        'registryEntries' => GuiStudioService::loadRegistry(),
        'exportedPackagePath' => '',
        'registryInstalled' => true,
    ]);
    return null;
});
    }
}
