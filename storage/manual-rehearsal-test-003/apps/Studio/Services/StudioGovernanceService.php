<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/**
 * StudioGovernanceService — G1–G4 No-Code GUI Transition Governance Layer
 *
 * Enforces authoring mode policy, preflight guardrails, quality gates,
 * and operating rhythm checks before any Studio publish action can proceed.
 *
 * G1: Foundation — authoring mode lock, focused-start plan, approval checkpoints
 * G2: Guardrails — role/authority matrix, CSRF, i18n-3-locales, route ownership
 * G3: Quality Gates — lint/smoke checks, publish decision record
 * G4: Operating Rhythm — canonical change request, checklist gate, rollback plan, audit snapshot
 */
final class StudioGovernanceService
{
    private const AUTHORING_MODE = 'gui_first';
    private const AUDIT_ROOT = 'storage/appstudio/audit';
    private const PUBLISH_DECISION_ROOT = 'storage/appstudio/publish_decisions';
    private const GOVERNANCE_SCHEMA = 'studio.governance.v1';
    private const REQUIRED_LOCALES = ['en', 'ja', 'ne'];
    private const ALLOWED_ROUTE_PREFIXES = ['/ops/', '/admin/', '/u/', '/displays/', '/apps/'];
    private const CROSS_LAYER_FORBIDDEN = [
        'operator' => ['/ops/', '/admin/', '/apps/'],
        'admin'    => ['/u/'],
    ];

    // ─── G1: Foundation Preparation ─────────────────────────────────────────

    /**
     * Returns the canonical authoring mode policy descriptor.
     * Policy: all app/module/view/nav creation must go through Studio GUI workflows.
     *
     * @return array{mode:string,policy:string,hotfix_bypass_allowed:bool,requires_platform_admin:bool,gui_first_enforced:bool}
     */
    public static function authoringPolicy(): array
    {
        return [
            'mode'                   => self::AUTHORING_MODE,
            'policy'                 => 'ops.studio.governance.policy.gui_first',
            'hotfix_bypass_allowed'  => true,
            'requires_platform_admin'=> true,
            'gui_first_enforced'     => true,
            'schema_version'         => self::GOVERNANCE_SCHEMA,
        ];
    }

    /**
     * Returns a focused-start plan: a guided entry that pre-seeds manifest stubs
     * and routes the user through the correct pipeline for their chosen template.
     *
     * @param array<int,array<string,mixed>> $templateLibrary
     * @return array{schema_version:string,steps:array<int,array<string,mixed>>,entry_categories:array<int,string>,guidance:string}
     */
    public static function focusedStartPlan(array $templateLibrary): array
    {
        $categories = [];
        foreach ($templateLibrary as $template) {
            $cat = (string)($template['category'] ?? 'other');
            if (!in_array($cat, $categories, true)) {
                $categories[] = $cat;
            }
        }

        $steps = [
            [
                'step'         => 1,
                'key'          => 'choose_template',
                'label_key'    => 'ops.studio.governance.start.choose_template',
                'description_key' => 'ops.studio.governance.start.choose_template_desc',
                'gate'         => 'template_selected',
                'completed'    => false,
            ],
            [
                'step'         => 2,
                'key'          => 'seed_manifests',
                'label_key'    => 'ops.studio.governance.start.seed_manifests',
                'description_key' => 'ops.studio.governance.start.seed_manifests_desc',
                'gate'         => 'manifests_populated',
                'completed'    => false,
            ],
            [
                'step'         => 3,
                'key'          => 'validate_bundle',
                'label_key'    => 'ops.studio.governance.start.validate_bundle',
                'description_key' => 'ops.studio.governance.start.validate_bundle_desc',
                'gate'         => 'validate_passed',
                'completed'    => false,
            ],
            [
                'step'         => 4,
                'key'          => 'preflight_check',
                'label_key'    => 'ops.studio.governance.start.preflight_check',
                'description_key' => 'ops.studio.governance.start.preflight_check_desc',
                'gate'         => 'preflight_passed',
                'completed'    => false,
            ],
            [
                'step'         => 5,
                'key'          => 'compile_plan',
                'label_key'    => 'ops.studio.governance.start.compile_plan',
                'description_key' => 'ops.studio.governance.start.compile_plan_desc',
                'gate'         => 'compile_plan_ready',
                'completed'    => false,
            ],
            [
                'step'         => 6,
                'key'          => 'approval_checkpoint',
                'label_key'    => 'ops.studio.governance.start.approval_checkpoint',
                'description_key' => 'ops.studio.governance.start.approval_checkpoint_desc',
                'gate'         => 'approval_granted',
                'completed'    => false,
            ],
            [
                'step'         => 7,
                'key'          => 'publish_gate',
                'label_key'    => 'ops.studio.governance.start.publish_gate',
                'description_key' => 'ops.studio.governance.start.publish_gate_desc',
                'gate'         => 'all_gates_passed',
                'completed'    => false,
            ],
        ];

        return [
            'schema_version'   => self::GOVERNANCE_SCHEMA,
            'authoring_mode'   => self::AUTHORING_MODE,
            'steps'            => $steps,
            'entry_categories' => $categories,
            'guidance'         => 'ops.studio.governance.start.guidance',
            'total_steps'      => count($steps),
        ];
    }

    /**
     * Returns the canonical workflow todo seed: gate-based checklist entries
     * for every lifecycle stage. Used to seed per-project todo lists.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function workflowTodoSeed(): array
    {
        return [
            [
                'id'          => 'todo.draft.manifests',
                'stage'       => 'draft',
                'label_key'   => 'ops.studio.todo.draft.manifests',
                'gate'        => 'manifests_populated',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.draft.manifests_desc',
            ],
            [
                'id'          => 'todo.draft.i18n_keys',
                'stage'       => 'draft',
                'label_key'   => 'ops.studio.todo.draft.i18n_keys',
                'gate'        => 'i18n_keys_declared',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.draft.i18n_keys_desc',
            ],
            [
                'id'          => 'todo.validate.schema',
                'stage'       => 'validate',
                'label_key'   => 'ops.studio.todo.validate.schema',
                'gate'        => 'schema_valid',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.validate.schema_desc',
            ],
            [
                'id'          => 'todo.validate.csrf',
                'stage'       => 'validate',
                'label_key'   => 'ops.studio.todo.validate.csrf',
                'gate'        => 'csrf_declared',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.validate.csrf_desc',
            ],
            [
                'id'          => 'todo.preflight.role_matrix',
                'stage'       => 'preflight',
                'label_key'   => 'ops.studio.todo.preflight.role_matrix',
                'gate'        => 'role_matrix_valid',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.preflight.role_matrix_desc',
            ],
            [
                'id'          => 'todo.preflight.i18n_locales',
                'stage'       => 'preflight',
                'label_key'   => 'ops.studio.todo.preflight.i18n_locales',
                'gate'        => 'i18n_3_locales',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.preflight.i18n_locales_desc',
            ],
            [
                'id'          => 'todo.preflight.route_ownership',
                'stage'       => 'preflight',
                'label_key'   => 'ops.studio.todo.preflight.route_ownership',
                'gate'        => 'route_ownership_valid',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.preflight.route_ownership_desc',
            ],
            [
                'id'          => 'todo.preflight.wrapper_confinement',
                'stage'       => 'preflight',
                'label_key'   => 'ops.studio.todo.preflight.wrapper_confinement',
                'gate'        => 'wrapper_confined',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.preflight.wrapper_confinement_desc',
            ],
            [
                'id'          => 'todo.compile.plan_ready',
                'stage'       => 'compile_plan',
                'label_key'   => 'ops.studio.todo.compile.plan_ready',
                'gate'        => 'compile_plan_ready',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.compile.plan_ready_desc',
            ],
            [
                'id'          => 'todo.compile.lint_passed',
                'stage'       => 'compile_plan',
                'label_key'   => 'ops.studio.todo.compile.lint_passed',
                'gate'        => 'lint_passed',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.compile.lint_passed_desc',
            ],
            [
                'id'          => 'todo.approval.decision_recorded',
                'stage'       => 'approval',
                'label_key'   => 'ops.studio.todo.approval.decision_recorded',
                'gate'        => 'approval_granted',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.approval.decision_recorded_desc',
            ],
            [
                'id'          => 'todo.approval.rollback_plan_captured',
                'stage'       => 'approval',
                'label_key'   => 'ops.studio.todo.approval.rollback_plan_captured',
                'gate'        => 'rollback_plan_ready',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.approval.rollback_plan_captured_desc',
            ],
            [
                'id'          => 'todo.publish.gate_passed',
                'stage'       => 'publish',
                'label_key'   => 'ops.studio.todo.publish.gate_passed',
                'gate'        => 'publish_gate_passed',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.publish.gate_passed_desc',
            ],
            [
                'id'          => 'todo.publish.audit_snapshot_written',
                'stage'       => 'publish',
                'label_key'   => 'ops.studio.todo.publish.audit_snapshot_written',
                'gate'        => 'audit_snapshot_ready',
                'required'    => true,
                'completed'   => false,
                'description_key' => 'ops.studio.todo.publish.audit_snapshot_written_desc',
            ],
        ];
    }

    /**
     * Returns the formal approval checkpoints: draft review, preflight, and publish.
     * Each checkpoint defines its required gates and who can approve.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function approvalCheckpoints(): array
    {
        return [
            [
                'checkpoint'      => 'draft_review',
                'label_key'       => 'ops.studio.governance.checkpoint.draft_review',
                'description_key' => 'ops.studio.governance.checkpoint.draft_review_desc',
                'required_gates'  => ['manifests_populated', 'i18n_keys_declared', 'schema_valid'],
                'approver_role'   => 'platform_admin',
                'blocking'        => false,
                'lifecycle_stage' => 'validate',
            ],
            [
                'checkpoint'      => 'preflight',
                'label_key'       => 'ops.studio.governance.checkpoint.preflight',
                'description_key' => 'ops.studio.governance.checkpoint.preflight_desc',
                'required_gates'  => [
                    'role_matrix_valid',
                    'i18n_3_locales',
                    'route_ownership_valid',
                    'wrapper_confined',
                    'csrf_declared',
                    'lint_passed',
                ],
                'approver_role'   => 'platform_admin',
                'blocking'        => true,
                'lifecycle_stage' => 'approval',
            ],
            [
                'checkpoint'      => 'publish',
                'label_key'       => 'ops.studio.governance.checkpoint.publish',
                'description_key' => 'ops.studio.governance.checkpoint.publish_desc',
                'required_gates'  => [
                    'approval_granted',
                    'rollback_plan_ready',
                    'audit_snapshot_ready',
                    'publish_gate_passed',
                ],
                'approver_role'   => 'platform_admin',
                'blocking'        => true,
                'lifecycle_stage' => 'publish',
            ],
        ];
    }

    // ─── G2: Guardrails Before Publish ──────────────────────────────────────

    /**
     * Runs all G2 preflight guardrail checks on the decoded manifest bundle.
     * Extends the basic validateBundle() with role/authority matrix, i18n-3-locales,
     * and route ownership validation.
     *
     * @param array<string,array<string,mixed>> $decoded
     * @return array{ok:bool,errors:array<int,string>,checks:array<int,array<string,mixed>>}
     */
    public static function preflightCheck(array $decoded): array
    {
        $errors = [];
        $checks = [];

        // G2.1 — Wrapper confinement (surface ↔ route prefix alignment)
        $view = is_array($decoded['view_definition']['view'] ?? null) ? $decoded['view_definition']['view'] : [];
        $nav  = is_array($decoded['navigation_definition']['navigation'] ?? null) ? $decoded['navigation_definition']['navigation'] : [];
        $surface   = trim((string)($view['surface'] ?? ''));
        $routePath = trim((string)($view['route_path'] ?? ''));
        $navUrl    = trim((string)($nav['url'] ?? ''));
        $navScope  = trim((string)($nav['scope'] ?? ''));

        $wrapperOk = true;
        if ($surface !== '' && $routePath !== '') {
            foreach ((self::CROSS_LAYER_FORBIDDEN[$surface] ?? []) as $forbidden) {
                if (str_starts_with($routePath, $forbidden)) {
                    $wrapperOk = false;
                    break;
                }
            }
        }
        $checks[] = self::check('wrapper_confinement', $wrapperOk, 'ops.studio.preflight.check.wrapper_confinement', $surface . ':' . $routePath);
        if (!$wrapperOk) {
            $errors[] = 'ops.studio.preflight.error.wrapper_confinement';
        }

        // G2.2 — Route ownership: nav URL must share the same prefix as the view route
        $routeOwnershipOk = true;
        if ($routePath !== '' && $navUrl !== '') {
            $routePrefix = self::extractRoutePrefix($routePath);
            $navPrefix   = self::extractRoutePrefix($navUrl);
            $routeOwnershipOk = $routePrefix === $navPrefix || $navUrl === $routePath;
        }
        $checks[] = self::check('route_ownership', $routeOwnershipOk, 'ops.studio.preflight.check.route_ownership', $routePath . ' / ' . $navUrl);
        if (!$routeOwnershipOk) {
            $errors[] = 'ops.studio.preflight.error.route_ownership';
        }

        // G2.3 — Nav scope confinement (no admin nav pointing into /u/)
        $navScopeOk = !($navScope === 'admin' && str_starts_with($navUrl, '/u/'));
        $checks[] = self::check('nav_scope_confinement', $navScopeOk, 'ops.studio.preflight.check.nav_scope', $navScope);
        if (!$navScopeOk) {
            $errors[] = 'ops.studio.preflight.error.nav_scope';
        }

        // G2.4 — Role/authority matrix: view must declare required_role
        $requiredRole = trim((string)($view['required_role'] ?? ''));
        $roleOk = $requiredRole !== '';
        $checks[] = self::check('role_matrix', $roleOk, 'ops.studio.preflight.check.role_matrix', $requiredRole);
        if (!$roleOk) {
            $errors[] = 'ops.studio.preflight.error.role_matrix';
        }

        // G2.5 — CSRF enforcement for mutation routes
        $security = is_array($decoded['view_definition']['security'] ?? null) ? $decoded['view_definition']['security'] : [];
        $csrfOk   = !empty($security['csrf_for_mutations']);
        $checks[] = self::check('csrf_for_mutations', $csrfOk, 'ops.studio.preflight.check.csrf', 'view.security');
        if (!$csrfOk) {
            $errors[] = 'ops.studio.preflight.error.csrf';
        }

        // G2.6 — i18n completeness: all 3 locales must be declared in i18n.supported_locales
        $appI18n      = is_array($decoded['app_manifest']['i18n'] ?? null) ? $decoded['app_manifest']['i18n'] : [];
        $declaredLocales = is_array($appI18n['supported_locales'] ?? null) ? $appI18n['supported_locales'] : [];
        $missingLocales  = array_values(array_diff(self::REQUIRED_LOCALES, array_map('strval', $declaredLocales)));
        $i18nOk = $missingLocales === [];
        $checks[] = self::check(
            'i18n_3_locales',
            $i18nOk,
            'ops.studio.preflight.check.i18n_locales',
            implode(',', $missingLocales !== [] ? $missingLocales : self::REQUIRED_LOCALES)
        );
        if (!$i18nOk) {
            $errors[] = 'ops.studio.preflight.error.i18n_locales';
        }

        // G2.7 — i18n key completeness: title_key and description_key must be non-empty
        $viewTitleKey = trim((string)($view['title_key'] ?? ''));
        $viewDescKey  = trim((string)($view['description_key'] ?? ''));
        $i18nKeysOk   = $viewTitleKey !== '' && $viewDescKey !== '';
        $checks[] = self::check('i18n_keys', $i18nKeysOk, 'ops.studio.preflight.check.i18n_keys', 'view');
        if (!$i18nKeysOk) {
            $errors[] = 'ops.studio.preflight.error.i18n_keys';
        }

        // G2.8 — Route prefix must be an allowed top-level prefix
        $routePrefixOk = false;
        foreach (self::ALLOWED_ROUTE_PREFIXES as $allowed) {
            if (str_starts_with($routePath, $allowed)) {
                $routePrefixOk = true;
                break;
            }
        }
        $checks[] = self::check('route_prefix', $routePrefixOk, 'ops.studio.preflight.check.route_prefix', $routePath);
        if (!$routePrefixOk) {
            $errors[] = 'ops.studio.preflight.error.route_prefix';
        }

        return [
            'ok'     => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'checks' => $checks,
        ];
    }

    // ─── G3: Preview + Quality Gates ────────────────────────────────────────

    /**
     * Runs structural lint on the decoded manifest bundle.
     * Validates JSON schema integrity and catches common structural errors
     * before the compile plan runs.
     *
     * @param array<string,array<string,mixed>> $decoded
     * @return array{ok:bool,errors:array<int,string>,lint_checks:array<int,array<string,mixed>>}
     */
    public static function lintGeneratedBundle(array $decoded): array
    {
        $errors = [];
        $checks = [];

        // L1 — App manifest: app_key must be snake_case or kebab-case
        $appKey = trim((string)($decoded['app_manifest']['app']['app_key'] ?? ''));
        $appKeyOk = $appKey !== '' && (bool)preg_match('/^[a-z][a-z0-9_\-]*$/', $appKey);
        $checks[] = self::check('lint.app_key_format', $appKeyOk, 'ops.studio.lint.check.app_key', $appKey);
        if (!$appKeyOk) {
            $errors[] = 'ops.studio.lint.error.app_key_format';
        }

        // L2 — Module manifest: module_key must be snake_case
        $moduleKey = trim((string)($decoded['module_manifest']['module']['module_key'] ?? ''));
        $moduleKeyOk = $moduleKey !== '' && (bool)preg_match('/^[a-z][a-z0-9_]*$/', $moduleKey);
        $checks[] = self::check('lint.module_key_format', $moduleKeyOk, 'ops.studio.lint.check.module_key', $moduleKey);
        if (!$moduleKeyOk) {
            $errors[] = 'ops.studio.lint.error.module_key_format';
        }

        // L3 — View manifest: route_path must start with / and not contain spaces
        $routePath = trim((string)($decoded['view_definition']['view']['route_path'] ?? ''));
        $routePathOk = $routePath !== '' && str_starts_with($routePath, '/') && !str_contains($routePath, ' ');
        $checks[] = self::check('lint.route_path_format', $routePathOk, 'ops.studio.lint.check.route_path', $routePath);
        if (!$routePathOk) {
            $errors[] = 'ops.studio.lint.error.route_path_format';
        }

        // L4 — Navigation URL must match a valid path pattern
        $navUrl = trim((string)($decoded['navigation_definition']['navigation']['url'] ?? ''));
        $navUrlOk = $navUrl !== '' && str_starts_with($navUrl, '/') && !str_contains($navUrl, ' ');
        $checks[] = self::check('lint.nav_url_format', $navUrlOk, 'ops.studio.lint.check.nav_url', $navUrl);
        if (!$navUrlOk) {
            $errors[] = 'ops.studio.lint.error.nav_url_format';
        }

        // L5 — No raw PHP in manifest values (injection prevention)
        $rawBundle = json_encode($decoded, JSON_UNESCAPED_SLASHES) ?: '';
        $noPhpOk = stripos($rawBundle, '<?php') === false && stripos($rawBundle, '<?=') === false;
        $checks[] = self::check('lint.no_php_injection', $noPhpOk, 'ops.studio.lint.check.no_php', 'bundle');
        if (!$noPhpOk) {
            $errors[] = 'ops.studio.lint.error.php_injection';
        }

        // L6 — App taxonomy must be a known type
        $taxonomy = trim((string)($decoded['app_manifest']['app']['app_taxonomy'] ?? ''));
        $taxonomyOk = in_array($taxonomy, ['business_app', 'system_app'], true);
        $checks[] = self::check('lint.app_taxonomy', $taxonomyOk, 'ops.studio.lint.check.taxonomy', $taxonomy);
        if (!$taxonomyOk) {
            $errors[] = 'ops.studio.lint.error.app_taxonomy';
        }

        // L7 — Module type must be a known type
        $moduleType = trim((string)($decoded['module_manifest']['module']['module_type'] ?? ''));
        $moduleTypeOk = in_array($moduleType, ['crud', 'dashboard', 'queue', 'workflow', 'queue_workflow'], true);
        $checks[] = self::check('lint.module_type', $moduleTypeOk, 'ops.studio.lint.check.module_type', $moduleType);
        if (!$moduleTypeOk) {
            $errors[] = 'ops.studio.lint.error.module_type';
        }

        // L8 — Governance flags: publish_locked must be true in draft
        $publishLocked = !empty($decoded['app_manifest']['governance']['publish_locked']);
        $checks[] = self::check('lint.publish_locked', $publishLocked, 'ops.studio.lint.check.publish_locked', 'governance');
        if (!$publishLocked) {
            $errors[] = 'ops.studio.lint.error.publish_not_locked';
        }

        return [
            'ok'          => $errors === [],
            'errors'      => array_values(array_unique($errors)),
            'lint_checks' => $checks,
        ];
    }

    /**
     * Records a publish decision with platform_admin approval metadata.
     * This is a G3 quality gate: no publish can proceed without a signed decision record.
     *
     * @param array<string,mixed> $compilePlan
     * @param array<string,mixed> $approvalPayload
     * @param string $adminHandle  The platform_admin handle making the publish decision
     * @return array<string,mixed>
     */
    public static function buildPublishDecisionRecord(
        array $compilePlan,
        array $approvalPayload,
        string $adminHandle
    ): array {
        $compileId  = (string)($compilePlan['compile_meta']['compile_id'] ?? $compilePlan['compile_snapshot']['compile_id'] ?? '');
        $approvalId = (string)($approvalPayload['approval_id'] ?? '');
        $decision   = strtolower((string)($approvalPayload['decision'] ?? 'rejected'));
        $artifacts  = is_array($compilePlan['artifacts'] ?? null) ? $compilePlan['artifacts'] : [];
        $highRisk   = count(is_array($approvalPayload['high_risk_items'] ?? null) ? $approvalPayload['high_risk_items'] : []);
        $blocked    = count(is_array($approvalPayload['blocked_items'] ?? null) ? $approvalPayload['blocked_items'] : []);

        return [
            'schema_version'    => self::GOVERNANCE_SCHEMA,
            'record_type'       => 'publish_decision',
            'record_id'         => self::hashId('publish_decision:' . $compileId . ':' . $adminHandle),
            'compile_id'        => $compileId,
            'approval_id'       => $approvalId,
            'decided_by'        => $adminHandle !== '' ? $adminHandle : 'unknown',
            'decided_at'        => gmdate('c'),
            'decision'          => $decision,
            'reason'            => trim((string)($approvalPayload['reason'] ?? '')),
            'risk_acknowledged' => !empty($approvalPayload['risk_acknowledged']),
            'artifact_count'    => count($artifacts),
            'high_risk_count'   => $highRisk,
            'blocked_count'     => $blocked,
            'publish_allowed'   => $decision === 'approved' && $blocked === 0,
            'authoring_mode'    => self::AUTHORING_MODE,
        ];
    }

    /**
     * Persists a publish decision record to storage/appstudio/publish_decisions/.
     * Publish approval is not durable unless this write succeeds.
     *
     * @param array<string,mixed> $publishDecision
     */
    public static function writePublishDecisionRecord(array $publishDecision): bool
    {
        $recordId  = trim((string)($publishDecision['record_id'] ?? ''));
        $compileId = trim((string)($publishDecision['compile_id'] ?? ''));
        if ($recordId === '' || $compileId === '') {
            return false;
        }

        $decisionRoot = defined('APP_ROOT')
            ? rtrim((string)APP_ROOT, '/') . '/' . self::PUBLISH_DECISION_ROOT
            : self::PUBLISH_DECISION_ROOT;
        if (!is_dir($decisionRoot) && !mkdir($decisionRoot, 0775, true)) {
            return false;
        }

        $slug = preg_replace('/[^a-z0-9_\-]/i', '_', $recordId) ?: 'publish_decision';
        $filename = $decisionRoot . '/' . $slug . '.json';
        $record = $publishDecision;
        $record['persisted_at'] = gmdate('c');

        $written = file_put_contents(
            $filename,
            json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return $written !== false && $written > 0;
    }

    public static function hasPublishDecisionRecord(string $recordId): bool
    {
        $safeId = preg_replace('/[^a-z0-9_\-]/i', '_', trim($recordId));
        if ($safeId === '') {
            return false;
        }

        $decisionRoot = defined('APP_ROOT')
            ? rtrim((string)APP_ROOT, '/') . '/' . self::PUBLISH_DECISION_ROOT
            : self::PUBLISH_DECISION_ROOT;

        return is_file($decisionRoot . '/' . $safeId . '.json');
    }

    // ─── G4: Operating Rhythm ───────────────────────────────────────────────

    /**
     * Builds a canonical change request record for a Studio workflow.
     * Every GUI-authored change must be tracked through this structure.
     *
     * @param array<string,array<string,mixed>> $decoded
     * @param string $adminHandle
     * @param string $projectId
     * @return array<string,mixed>
     */
    public static function buildChangeRequest(
        array $decoded,
        string $adminHandle,
        string $projectId = ''
    ): array {
        $appKey    = trim((string)($decoded['app_manifest']['app']['app_key'] ?? 'draft_app'));
        $moduleKey = trim((string)($decoded['module_manifest']['module']['module_key'] ?? 'draft_module'));
        $routePath = trim((string)($decoded['view_definition']['view']['route_path'] ?? ''));
        $requestId = self::hashId('change_request:' . $appKey . ':' . $moduleKey . ':' . $adminHandle . ':' . gmdate('Y-m-d'));

        return [
            'schema_version'    => self::GOVERNANCE_SCHEMA,
            'record_type'       => 'change_request',
            'request_id'        => $requestId,
            'project_id'        => $projectId !== '' ? $projectId : 'studio_project:' . $appKey,
            'app_key'           => $appKey,
            'module_key'        => $moduleKey,
            'route_path'        => $routePath,
            'requested_by'      => $adminHandle !== '' ? $adminHandle : 'unknown',
            'requested_at'      => gmdate('c'),
            'authoring_mode'    => self::AUTHORING_MODE,
            'status'            => 'open',
            'lifecycle_stage'   => 'draft',
            'checklist'         => self::workflowTodoSeed(),
            'gates'             => [
                'manifests_populated' => false,
                'schema_valid'        => false,
                'preflight_passed'    => false,
                'lint_passed'         => false,
                'compile_plan_ready'  => false,
                'approval_granted'    => false,
                'rollback_plan_ready' => false,
                'audit_snapshot_ready'=> false,
                'publish_gate_passed' => false,
            ],
        ];
    }

    /**
     * Runs the final G4 publish gate check: all required G2/G3/G4 gates must pass
     * before the publish action can be enabled.
     *
     * @param array<string,mixed> $compilePlan
     * @param array<string,mixed> $approvalPayload
     * @param array<string,mixed> $preflightResult   Result from preflightCheck()
     * @param array<string,mixed> $lintResult        Result from lintGeneratedBundle()
     * @return array{ok:bool,errors:array<int,string>,gate_checks:array<int,array<string,mixed>>}
     */
    public static function publishGateCheck(
        array $compilePlan,
        array $approvalPayload,
        array $preflightResult,
        array $lintResult
    ): array {
        $errors = [];
        $checks = [];

        // Gate 1: Preflight must have passed (G2)
        $preflightOk = !empty($preflightResult['ok']);
        $checks[] = self::check('gate.preflight', $preflightOk, 'ops.studio.gate.preflight', 'G2');
        if (!$preflightOk) {
            $errors[] = 'ops.studio.gate.error.preflight_failed';
        }

        // Gate 2: Lint must have passed (G3)
        $lintOk = !empty($lintResult['ok']);
        $checks[] = self::check('gate.lint', $lintOk, 'ops.studio.gate.lint', 'G3');
        if (!$lintOk) {
            $errors[] = 'ops.studio.gate.error.lint_failed';
        }

        // Gate 3: Approval decision must be 'approved' (G3)
        $approved = strtolower((string)($approvalPayload['decision'] ?? '')) === 'approved';
        $checks[] = self::check('gate.approval', $approved, 'ops.studio.gate.approval', 'decision');
        if (!$approved) {
            $errors[] = 'ops.studio.gate.error.not_approved';
        }

        // Gate 4: No blocked items (G3)
        $blockedCount = count(is_array($approvalPayload['blocked_items'] ?? null) ? $approvalPayload['blocked_items'] : []);
        $noBlocked = $blockedCount === 0;
        $checks[] = self::check('gate.no_blocked', $noBlocked, 'ops.studio.gate.no_blocked', (string)$blockedCount);
        if (!$noBlocked) {
            $errors[] = 'ops.studio.gate.error.blocked_present';
        }

        // Gate 5: Risk acknowledged if high-risk items present (G3)
        $highRisk = count(is_array($approvalPayload['high_risk_items'] ?? null) ? $approvalPayload['high_risk_items'] : []);
        $riskAck  = $highRisk === 0 || !empty($approvalPayload['risk_acknowledged']);
        $checks[] = self::check('gate.risk_ack', $riskAck, 'ops.studio.gate.risk_ack', (string)$highRisk);
        if (!$riskAck) {
            $errors[] = 'ops.studio.gate.error.risk_not_acked';
        }

        // Gate 6: Compile plan must have no blocked artifacts (G3)
        $artifacts    = is_array($compilePlan['artifacts'] ?? null) ? $compilePlan['artifacts'] : [];
        $blockedArtifacts = count(array_filter($artifacts, static fn(array $a): bool => strtolower((string)($a['risk_level'] ?? '')) === 'blocked'));
        $noBlockedArtifacts = $blockedArtifacts === 0;
        $checks[] = self::check('gate.compile_no_blocked', $noBlockedArtifacts, 'ops.studio.gate.compile_blocked', (string)$blockedArtifacts);
        if (!$noBlockedArtifacts) {
            $errors[] = 'ops.studio.gate.error.blocked_artifacts';
        }

        // Gate 7: APPLY_MODE must not be 'disabled' (G4)
        $applyMode   = GuiStudioService::APPLY_MODE;
        $applyModeOk = in_array($applyMode, ['simulation', 'real'], true);
        $checks[] = self::check('gate.apply_mode', $applyModeOk, 'ops.studio.gate.apply_mode', $applyMode);
        if (!$applyModeOk) {
            $errors[] = 'ops.studio.gate.error.apply_mode_disabled';
        }

        return [
            'ok'          => $errors === [],
            'errors'      => array_values(array_unique($errors)),
            'gate_checks' => $checks,
            'apply_mode'  => $applyMode,
        ];
    }

    /**
     * Captures a rollback plan for the given compile plan.
     * Required before publish can proceed (G4).
     *
     * @param array<string,mixed> $compilePlan
     * @return array<string,mixed>
     */
    public static function captureRollbackPlan(array $compilePlan): array
    {
        $compileId = (string)($compilePlan['compile_meta']['compile_id'] ?? $compilePlan['compile_snapshot']['compile_id'] ?? '');
        $artifacts = is_array($compilePlan['artifacts'] ?? null) ? $compilePlan['artifacts'] : [];

        $rollbackArtifacts = [];
        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }
            $changeType = strtolower((string)($artifact['change_type'] ?? ''));
            $rollbackArtifacts[] = [
                'artifact_id'   => (string)($artifact['artifact_id'] ?? ''),
                'artifact_type' => (string)($artifact['artifact_type'] ?? ''),
                'target_path'   => (string)($artifact['target_path'] ?? ''),
                'rollback_action' => $changeType === 'create' ? 'delete' : 'restore_from_backup',
                'risk_level'    => (string)($artifact['risk_level'] ?? ''),
                'before_hash'   => (string)($artifact['before_hash'] ?? ''),
                'reversible'    => strtolower((string)($artifact['risk_level'] ?? '')) !== 'blocked',
            ];
        }

        return [
            'schema_version'  => self::GOVERNANCE_SCHEMA,
            'record_type'     => 'rollback_plan',
            'plan_id'         => self::hashId('rollback_plan:' . $compileId),
            'compile_id'      => $compileId,
            'created_at'      => gmdate('c'),
            'artifact_count'  => count($rollbackArtifacts),
            'artifacts'       => $rollbackArtifacts,
            'rollback_method' => 'GuiStudioService::rollbackGeneratedSnapshot',
            'is_reversible'   => !in_array(false, array_column($rollbackArtifacts, 'reversible'), true),
            'authoring_mode'  => self::AUTHORING_MODE,
        ];
    }

    /**
     * Writes an audit snapshot of the generated manifest + nav + route outputs
     * to storage/appstudio/audit/. Required for G4 operating rhythm.
     *
     * @param array<string,mixed> $compilePlan
     * @param array<string,mixed> $approvalPayload
     * @param string $adminHandle
     * @return bool True if written successfully
     */
    public static function writeAuditSnapshot(
        array $compilePlan,
        array $approvalPayload,
        string $adminHandle
    ): bool {
        $compileId = (string)($compilePlan['compile_meta']['compile_id'] ?? $compilePlan['compile_snapshot']['compile_id'] ?? '');
        if ($compileId === '') {
            return false;
        }

        $auditRoot = defined('APP_ROOT') ? rtrim((string)APP_ROOT, '/') . '/' . self::AUDIT_ROOT : self::AUDIT_ROOT;
        if (!is_dir($auditRoot) && !mkdir($auditRoot, 0775, true)) {
            return false;
        }

        $slug     = preg_replace('/[^a-z0-9_\-]/i', '_', $compileId) ?: 'audit';
        $filename = $auditRoot . '/' . $slug . '_' . gmdate('Ymd_His') . '.json';

        $record = [
            'schema_version'   => self::GOVERNANCE_SCHEMA,
            'record_type'      => 'audit_snapshot',
            'audit_id'         => self::hashId('audit:' . $compileId . ':' . gmdate('c')),
            'compile_id'       => $compileId,
            'admin_handle'     => $adminHandle !== '' ? $adminHandle : 'unknown',
            'recorded_at'      => gmdate('c'),
            'authoring_mode'   => self::AUTHORING_MODE,
            'decision'         => strtolower((string)($approvalPayload['decision'] ?? 'unknown')),
            'publish_decision_id' => (string)($approvalPayload['publish_decision_id'] ?? ''),
            'approved_by'      => (string)($approvalPayload['approved_by'] ?? $adminHandle),
            'approval_timestamp' => (string)($approvalPayload['approval_timestamp'] ?? $approvalPayload['approved_at'] ?? gmdate('c')),
            'publish_approved' => !empty($approvalPayload['publish_approved']),
            'compile_snapshot' => is_array($compilePlan['compile_snapshot'] ?? null) ? $compilePlan['compile_snapshot'] : [],
            'artifact_summary' => self::auditArtifactSummary($compilePlan),
            'approval_summary' => [
                'approval_id'     => (string)($approvalPayload['approval_id'] ?? ''),
                'decided_by'      => (string)($approvalPayload['approved_by'] ?? $adminHandle),
                'decision'        => (string)($approvalPayload['decision'] ?? ''),
                'reason'          => (string)($approvalPayload['reason'] ?? ''),
                'risk_acknowledged' => !empty($approvalPayload['risk_acknowledged']),
            ],
        ];

        $written = file_put_contents($filename, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $written !== false && $written > 0;
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /** @param array<string,mixed> $compilePlan @return array<string,mixed> */
    private static function auditArtifactSummary(array $compilePlan): array
    {
        $artifacts = is_array($compilePlan['artifacts'] ?? null) ? $compilePlan['artifacts'] : [];
        $summary   = ['total' => count($artifacts), 'by_type' => [], 'by_risk' => [], 'paths' => []];
        foreach ($artifacts as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }
            $type = (string)($artifact['artifact_type'] ?? 'unknown');
            $risk = strtolower((string)($artifact['risk_level'] ?? 'low'));
            $summary['by_type'][$type] = ($summary['by_type'][$type] ?? 0) + 1;
            $summary['by_risk'][$risk] = ($summary['by_risk'][$risk] ?? 0) + 1;
            $summary['paths'][] = (string)($artifact['target_path'] ?? '');
        }
        return $summary;
    }

    /**
     * @param array<int,mixed> $params
     * @return array<string,mixed>
     */
    private static function check(string $name, bool $pass, string $messageKey, string $context = ''): array
    {
        return [
            'name'        => $name,
            'pass'        => $pass,
            'message_key' => $messageKey,
            'context'     => $context,
        ];
    }

    private static function hashId(string $seed): string
    {
        $hash = sha1($seed);
        return sprintf(
            '%s-%s-5%s-%s%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            dechex((hexdec($hash[16]) & 0x3) | 0x8),
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }

    private static function extractRoutePrefix(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }
        $parts = explode('/', ltrim($path, '/'), 3);
        return '/' . ($parts[0] ?? '');
    }
}
