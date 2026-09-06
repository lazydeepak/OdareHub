<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

final class SuitePermissionTemplateService
{
    /**
     * @return array<string,array<string,mixed>>
     */
    public static function suiteRoleTemplates(): array
    {
        return [
            'sbaio_suite_admin' => [
                'label' => 'SBAIO Admin',
                'description' => 'Full SBAIO operational ownership across people, pay, and office ops.',
                'app' => 'sbaio',
                'suite' => 'sbaio',
                'template_type' => 'suite_role',
                'default_dashboard_type' => 'my_work',
                'default_app' => 'sbaio',
                'default_landing_page' => '/',
                'permissions' => ['ops.my_work.view', 'ops.notifications.view', 'ops.notifications.manage', 'ops.approval_inbox.view'],
                'module_visibility' => ['sbaio_staff', 'sbaio_attendance', 'sbaio_schedules', 'sbaio_leave', 'sbaio_timecards', 'sbaio_payroll', 'sbaio_customers', 'sbaio_tasks', 'sbaio_sales', 'sbaio_expenses', 'sbaio_notices'],
                'scope_hints' => ['task_types' => ['approval']],
            ],
            'sbaio_people_time' => [
                'label' => 'SBAIO People & Time',
                'description' => 'Staff, attendance, schedules, leave, and timecard review.',
                'app' => 'sbaio',
                'suite' => 'sbaio',
                'template_type' => 'suite_role',
                'default_dashboard_type' => 'my_work',
                'default_app' => 'sbaio',
                'default_landing_page' => '/',
                'permissions' => ['ops.my_work.view', 'ops.notifications.view'],
                'module_visibility' => ['sbaio_staff', 'sbaio_attendance', 'sbaio_schedules', 'sbaio_leave', 'sbaio_timecards'],
                'scope_hints' => ['task_types' => ['production']],
            ],
            'sbaio_payroll_review' => [
                'label' => 'SBAIO Payroll Review',
                'description' => 'Timecard to payroll review with leave and approval visibility.',
                'app' => 'sbaio',
                'suite' => 'sbaio',
                'template_type' => 'suite_role',
                'default_dashboard_type' => 'my_work',
                'default_app' => 'sbaio',
                'default_landing_page' => '/',
                'permissions' => ['ops.my_work.view', 'ops.notifications.view', 'ops.approval_inbox.view'],
                'module_visibility' => ['sbaio_timecards', 'sbaio_payroll', 'sbaio_leave', 'sbaio_attendance'],
                'scope_hints' => ['task_types' => ['approval']],
            ],
            'sbaio_office_ops' => [
                'label' => 'SBAIO Office Ops',
                'description' => 'Customers, tasks, sales, notices, and expense follow-up.',
                'app' => 'sbaio',
                'suite' => 'sbaio',
                'template_type' => 'suite_role',
                'default_dashboard_type' => 'my_work',
                'default_app' => 'sbaio',
                'default_landing_page' => '/',
                'permissions' => ['ops.my_work.view', 'ops.notifications.view'],
                'module_visibility' => ['sbaio_customers', 'sbaio_tasks', 'sbaio_sales', 'sbaio_expenses', 'sbaio_notices'],
                'scope_hints' => ['task_types' => ['dispatch']],
            ],
            'manufacturing_suite_admin' => [
                'label' => 'Manufacturing Admin',
                'description' => 'Full manufacturing oversight across planning, execution, QC, and dispatch.',
                'app' => 'manufacturing',
                'suite' => 'manufacturing',
                'template_type' => 'suite_role',
                'default_dashboard_type' => 'app_admin',
                'default_app' => 'manufacturing',
                'default_landing_page' => '/admin',
                'permissions' => ['ops.my_work.view', 'ops.notifications.view', 'ops.notifications.manage', 'ops.approval_inbox.view', 'materials.admin', 'materials.master.manage', 'materials.planning.manage', 'materials.orders.manage', 'materials.stock.adjust', 'materials.capacity.manage', 'materials.cost.manage', 'materials.stock.view', 'materials.coverage.view'],
                'module_visibility' => ['production', 'assembly', 'qc', 'dispatch', 'demands', 'coverage', 'materials', 'ops'],
                'scope_hints' => ['task_types' => ['approval']],
            ],
            'manufacturing_planning_control' => [
                'label' => 'Manufacturing Planning Control',
                'description' => 'Demand, coverage, order, and production planning ownership.',
                'app' => 'manufacturing',
                'suite' => 'manufacturing',
                'template_type' => 'suite_role',
                'default_dashboard_type' => 'production_leader',
                'default_app' => 'manufacturing',
                'default_landing_page' => '/',
                'permissions' => ['ops.my_work.view', 'products.360.view', 'daily_orders.360.view', 'workflow.production_plan.submit'],
                'module_visibility' => ['production', 'demands', 'coverage'],
                'scope_hints' => ['task_types' => ['production', 'queue_release']],
            ],
            'manufacturing_execution_control' => [
                'label' => 'Manufacturing Worker Execution',
                'description' => 'Worker-oriented floor execution without app-admin or leader-only authority.',
                'app' => 'manufacturing',
                'suite' => 'manufacturing',
                'template_type' => 'suite_role',
                'default_dashboard_type' => 'my_work',
                'default_app' => 'manufacturing',
                'default_landing_page' => '/',
                'permissions' => ['ops.my_work.view'],
                'module_visibility' => ['production', 'assembly', 'qc', 'dispatch'],
                'scope_hints' => ['task_types' => ['production', 'assembly', 'qc_check', 'dispatch']],
            ],
            'manufacturing_material_visibility' => [
                'label' => 'Material Visibility',
                'description' => 'Controlled readonly access for material stock and coverage only.',
                'app' => 'manufacturing',
                'suite' => 'manufacturing',
                'template_type' => 'suite_role',
                'default_dashboard_type' => 'my_work',
                'default_app' => 'manufacturing',
                'default_landing_page' => '/',
                'permissions' => ['ops.my_work.view', 'materials.stock.view', 'materials.coverage.view'],
                'module_visibility' => ['materials'],
                'scope_hints' => ['task_types' => []],
            ],
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function modulePermissionTemplates(): array
    {
        return [
            'sbaio_staff_module' => [
                'label' => 'SBAIO Staff',
                'description' => 'Staff and schedule records.',
                'app' => 'sbaio',
                'suite' => 'sbaio',
                'template_type' => 'module_permission',
                'module_key' => 'sbaio_staff',
                'permissions' => ['ops.my_work.view'],
                'module_visibility' => ['sbaio_staff', 'sbaio_schedules'],
            ],
            'sbaio_attendance_module' => [
                'label' => 'SBAIO Attendance',
                'description' => 'Attendance, leave, and time input follow-up.',
                'app' => 'sbaio',
                'suite' => 'sbaio',
                'template_type' => 'module_permission',
                'module_key' => 'sbaio_attendance',
                'permissions' => ['ops.my_work.view'],
                'module_visibility' => ['sbaio_attendance', 'sbaio_leave', 'sbaio_timecards'],
            ],
            'sbaio_payroll_module' => [
                'label' => 'SBAIO Payroll',
                'description' => 'Timecards, payroll runs, and parity review.',
                'app' => 'sbaio',
                'suite' => 'sbaio',
                'template_type' => 'module_permission',
                'module_key' => 'sbaio_payroll',
                'permissions' => ['ops.my_work.view', 'ops.approval_inbox.view'],
                'module_visibility' => ['sbaio_timecards', 'sbaio_payroll'],
            ],
            'sbaio_office_ops_module' => [
                'label' => 'SBAIO Office Ops',
                'description' => 'Customers, tasks, sales, expenses, and notices.',
                'app' => 'sbaio',
                'suite' => 'sbaio',
                'template_type' => 'module_permission',
                'module_key' => 'sbaio_tasks',
                'permissions' => ['ops.my_work.view'],
                'module_visibility' => ['sbaio_customers', 'sbaio_tasks', 'sbaio_sales', 'sbaio_expenses', 'sbaio_notices'],
            ],
            'manufacturing_products_module' => [
                'label' => 'Manufacturing Products',
                'description' => 'Products, parts, and order context.',
                'app' => 'manufacturing',
                'suite' => 'manufacturing',
                'template_type' => 'module_permission',
                'module_key' => 'demands',
                'permissions' => ['ops.my_work.view', 'products.360.view', 'daily_orders.360.view'],
                'module_visibility' => ['demands', 'coverage'],
            ],
            'manufacturing_machines_module' => [
                'label' => 'Manufacturing Machines',
                'description' => 'Worker production queue visibility without leader-only controls.',
                'app' => 'manufacturing',
                'suite' => 'manufacturing',
                'template_type' => 'module_permission',
                'module_key' => 'production',
                'permissions' => ['ops.my_work.view'],
                'module_visibility' => ['production'],
            ],
            'manufacturing_orders_module' => [
                'label' => 'Manufacturing Orders',
                'description' => 'Daily orders, coverage, and demand workspace.',
                'app' => 'manufacturing',
                'suite' => 'manufacturing',
                'template_type' => 'module_permission',
                'module_key' => 'demands',
                'permissions' => ['ops.my_work.view', 'daily_orders.360.view', 'products.360.view'],
                'module_visibility' => ['demands', 'coverage', 'production'],
            ],
            'manufacturing_qc_module' => [
                'label' => 'Manufacturing QC',
                'description' => 'QC queue visibility with worker submission authority only.',
                'app' => 'manufacturing',
                'suite' => 'manufacturing',
                'template_type' => 'module_permission',
                'module_key' => 'qc',
                'permissions' => ['ops.my_work.view', 'workflow.qc_entry.submit'],
                'module_visibility' => ['qc'],
            ],
            'manufacturing_dispatch_module' => [
                'label' => 'Manufacturing Dispatch',
                'description' => 'Dispatch queue visibility with worker posting and status updates.',
                'app' => 'manufacturing',
                'suite' => 'manufacturing',
                'template_type' => 'module_permission',
                'module_key' => 'dispatch',
                'permissions' => ['ops.my_work.view', 'dispatch_entries.quick_status', 'dispatch_entries.transition', 'workflow.dispatch_entry.submit'],
                'module_visibility' => ['dispatch'],
            ],
            'manufacturing_materials_readonly_module' => [
                'label' => 'Material Stock & Coverage',
                'description' => 'Readonly material stock and coverage visibility.',
                'app' => 'manufacturing',
                'suite' => 'manufacturing',
                'template_type' => 'module_permission',
                'module_key' => 'materials',
                'permissions' => ['ops.my_work.view', 'materials.stock.view', 'materials.coverage.view'],
                'module_visibility' => ['materials'],
            ],
        ];
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function defaultRoleBundles(): array
    {
        return [
            'sbaio' => [
                ['label' => 'SBAIO Core Team', 'suite_role_template' => 'sbaio_suite_admin', 'module_permission_templates' => ['sbaio_staff_module', 'sbaio_attendance_module', 'sbaio_payroll_module']],
                ['label' => 'SBAIO Office Ops', 'suite_role_template' => 'sbaio_office_ops', 'module_permission_templates' => ['sbaio_office_ops_module']],
            ],
            'manufacturing' => [
                ['label' => 'Manufacturing Floor Team', 'suite_role_template' => 'manufacturing_execution_control', 'module_permission_templates' => ['manufacturing_machines_module', 'manufacturing_qc_module', 'manufacturing_dispatch_module']],
                ['label' => 'Manufacturing Planning Team', 'suite_role_template' => 'manufacturing_planning_control', 'module_permission_templates' => ['manufacturing_products_module', 'manufacturing_orders_module']],
                ['label' => 'Material Visibility Team', 'suite_role_template' => 'manufacturing_material_visibility', 'module_permission_templates' => ['manufacturing_materials_readonly_module']],
            ],
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function accessProfileTemplates(): array
    {
        return array_merge(self::suiteRoleTemplates(), self::modulePermissionTemplates());
    }

    /**
     * @return array<string,array<string,string>>
     */
    public static function rolePacksByApp(): array
    {
        $out = [];
        foreach (self::accessProfileTemplates() as $key => $template) {
            $app = strtolower(trim((string)($template['app'] ?? '')));
            if ($app === '') {
                continue;
            }
            if (!isset($out[$app])) {
                $out[$app] = [];
            }
            $out[$app][$key] = (string)($template['label'] ?? $key);
        }
        return $out;
    }

    /**
     * @param array<int,string> $profileKeys
     * @return array{suite_role_templates:array<int,array<string,string>>,module_permission_templates:array<int,array<string,string>>}
     */
    public static function splitTemplateKeys(array $profileKeys): array
    {
        $registry = self::accessProfileTemplates();
        $suite = [];
        $modules = [];

        foreach ($profileKeys as $profileKey) {
            $profileKey = strtolower(trim((string)$profileKey));
            $template = $registry[$profileKey] ?? null;
            if (!is_array($template)) {
                continue;
            }
            $payload = [
                'key' => $profileKey,
                'label' => (string)($template['label'] ?? $profileKey),
                'suite' => (string)($template['suite'] ?? $template['app'] ?? ''),
                'module_key' => (string)($template['module_key'] ?? ''),
            ];
            if ((string)($template['template_type'] ?? '') === 'suite_role') {
                $suite[] = $payload;
            } elseif ((string)($template['template_type'] ?? '') === 'module_permission') {
                $modules[] = $payload;
            }
        }

        return [
            'suite_role_templates' => $suite,
            'module_permission_templates' => $modules,
        ];
    }

    /**
     * @param array<int,array<string,string>> $templates
     */
    public static function labels(array $templates): array
    {
        $labels = [];
        foreach ($templates as $template) {
            $label = trim((string)($template['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $labels[$label] = $label;
        }
        return array_values($labels);
    }
}
