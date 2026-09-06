<?php
declare(strict_types=1);

namespace SBAIO\Controllers;

use App\Core\Auth;
use App\Core\DB;

final class SbaioDashboardController
{
    public static function index($view): void
    {
        $ctx = platform_user_context_contract()->resolveUserContext(Auth::user());
        $moduleVisibility = array_values(array_map('strval', (array)($ctx['module_visibility'] ?? [])));
        $suiteRoleLabels = array_values(array_map('strval', (array)($ctx['suite_role_template_labels'] ?? [])));
        $moduleTemplateLabels = array_values(array_map('strval', (array)($ctx['module_permission_template_labels'] ?? [])));

        $leaveRequests = self::count('sbaio_leave_requests');
        $holidayEntries = self::count('sbaio_holiday_calendar');
        $counts = [
            'staff' => self::count('sbaio_staff'),
            'attendance' => self::count('sbaio_attendance_daily'),
            'schedules' => self::count('sbaio_schedule_assignments'),
            'timecards_periods' => self::count('sbaio_timecard_periods'),
            'payroll_runs' => self::count('sbaio_payroll_runs'),
            'payroll_records' => self::count('sbaio_payroll_records'),
            'customers' => self::count('sbaio_customers'),
            'tasks' => self::count('sbaio_tasks'),
            'sales' => self::count('sbaio_sales'),
            'expenses' => self::count('sbaio_expenses'),
            'notices' => self::count('sbaio_notices'),
            'salary_rates' => self::count('sbaio_salary_monthly_rates'),
            'fixed_profiles' => self::count('sbaio_payroll_fixed_profiles'),
            'leave_requests' => $leaveRequests,
            'holiday_entries' => $holidayEntries,
        ];

        $draftTimecards = self::countWhere(
            'sbaio_timecard_periods',
            "LOWER(COALESCE(approval_status, 'draft')) IN ('draft','generated','pending')"
        );
        $draftPayroll = self::countWhere(
            'sbaio_payroll_runs',
            "LOWER(COALESCE(run_status, 'draft')) IN ('draft','generated','pending')"
        );
        $pendingLeave = self::countWhere(
            'sbaio_leave_requests',
            "LOWER(COALESCE(leave_status, 'draft')) IN ('draft','pending','submitted')"
        );
        $missingPunches = self::countWhere(
            'sbaio_attendance_daily',
            "(work_start_at IS NULL OR work_end_at IS NULL) AND LOWER(COALESCE(day_marker, 'workday')) NOT IN ('holiday','leave','off_day','closed')"
        );

        $sections = [
            [
                'title' => t('sbaio.dashboard.section.people_time'),
                'subtitle' => t('sbaio.dashboard.section.people_time_desc'),
                'module_keys' => ['sbaio_staff', 'sbaio_schedules', 'sbaio_attendance', 'sbaio_leave', 'sbaio_timecards'],
                'cards' => [
                    [
                        'label' => t('nav.sbaio_staff'),
                        'module_key' => 'sbaio_staff',
                        'url' => '/apps/sbaio/staff',
                        'desc' => t('sbaio.dashboard.card.staff_desc'),
                        'count' => $counts['staff'],
                        'state' => $counts['staff'] > 0 ? t('sbaio.common.live') : t('sbaio.common.ready'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_staff'), 'url' => '/apps/sbaio/staff'],
                        ],
                    ],
                    [
                        'label' => t('nav.sbaio_schedules'),
                        'module_key' => 'sbaio_schedules',
                        'url' => '/apps/sbaio/schedules',
                        'desc' => t('sbaio.dashboard.card.schedules_desc'),
                        'count' => $counts['schedules'],
                        'state' => $counts['schedules'] > 0 ? t('sbaio.common.assigned') : t('sbaio.common.ready'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_schedules'), 'url' => '/apps/sbaio/schedules'],
                            ['label' => t('sbaio.dashboard.quick.open_staff'), 'url' => '/apps/sbaio/staff'],
                        ],
                    ],
                    [
                        'label' => t('nav.sbaio_attendance'),
                        'module_key' => 'sbaio_attendance',
                        'url' => '/apps/sbaio/attendance',
                        'desc' => t('sbaio.dashboard.card.attendance_desc'),
                        'count' => $counts['attendance'],
                        'state' => $missingPunches > 0 ? t('sbaio.common.missing_suffix', ['count' => $missingPunches]) : t('sbaio.common.clean'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_attendance'), 'url' => '/apps/sbaio/attendance'],
                            ['label' => t('sbaio.dashboard.quick.open_timecards'), 'url' => '/apps/sbaio/timecards'],
                        ],
                    ],
                    [
                        'label' => t('nav.sbaio_leave'),
                        'module_key' => 'sbaio_leave',
                        'url' => '/apps/sbaio/leave',
                        'desc' => t('sbaio.dashboard.card.leave_desc'),
                        'count' => $counts['leave_requests'] + $counts['holiday_entries'],
                        'state' => $pendingLeave > 0 ? t('sbaio.common.pending_suffix', ['count' => $pendingLeave]) : t('sbaio.common.current'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_leave'), 'url' => '/apps/sbaio/leave'],
                            ['label' => t('sbaio.dashboard.quick.open_schedules'), 'url' => '/apps/sbaio/schedules'],
                        ],
                    ],
                    [
                        'label' => t('nav.sbaio_timecards'),
                        'module_key' => 'sbaio_timecards',
                        'url' => '/apps/sbaio/timecards',
                        'desc' => t('sbaio.dashboard.card.timecards_desc'),
                        'count' => $counts['timecards_periods'],
                        'state' => $draftTimecards > 0 ? t('sbaio.common.draft_suffix', ['count' => $draftTimecards]) : t('sbaio.common.current'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_timecards'), 'url' => '/apps/sbaio/timecards'],
                            ['label' => t('sbaio.dashboard.quick.open_attendance'), 'url' => '/apps/sbaio/attendance'],
                        ],
                    ],
                ],
            ],
            [
                'title' => t('sbaio.dashboard.section.pay_review'),
                'subtitle' => t('sbaio.dashboard.section.pay_review_desc'),
                'module_keys' => ['sbaio_payroll', 'sbaio_timecards'],
                'cards' => [
                    [
                        'label' => t('sbaio.dashboard.card.payroll_runs'),
                        'module_key' => 'sbaio_payroll',
                        'url' => '/apps/sbaio/payroll',
                        'desc' => t('sbaio.dashboard.card.payroll_runs_desc'),
                        'count' => $counts['payroll_runs'],
                        'state' => $draftPayroll > 0 ? t('sbaio.common.draft_suffix', ['count' => $draftPayroll]) : t('sbaio.common.current'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_payroll'), 'url' => '/apps/sbaio/payroll'],
                            ['label' => t('nav.sbaio_payroll_parity'), 'url' => '/apps/sbaio/payroll/parity'],
                        ],
                    ],
                    [
                        'label' => t('sbaio.dashboard.card.payroll_records'),
                        'module_key' => 'sbaio_payroll',
                        'url' => '/apps/sbaio/payroll',
                        'desc' => t('sbaio.dashboard.card.payroll_records_desc'),
                        'count' => $counts['payroll_records'],
                        'state' => $counts['payroll_records'] > 0 ? t('sbaio.common.generated') : t('sbaio.common.waiting'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_payroll'), 'url' => '/apps/sbaio/payroll'],
                        ],
                    ],
                    [
                        'label' => t('nav.sbaio_payroll_parity'),
                        'module_key' => 'sbaio_payroll',
                        'url' => '/apps/sbaio/payroll/parity',
                        'desc' => t('sbaio.dashboard.card.payroll_parity_desc'),
                        'count' => $counts['payroll_records'],
                        'state' => t('sbaio.common.review'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_parity'), 'url' => '/apps/sbaio/payroll/parity'],
                            ['label' => t('sbaio.dashboard.quick.open_payroll'), 'url' => '/apps/sbaio/payroll'],
                        ],
                    ],
                    [
                        'label' => 'Salary References',
                        'module_key' => 'sbaio_payroll',
                        'url' => '/apps/sbaio/payroll',
                        'desc' => t('sbaio.dashboard.card.salary_references_desc'),
                        'count' => $counts['salary_rates'],
                        'state' => $counts['salary_rates'] > 0 ? t('sbaio.common.imported') : t('sbaio.common.empty'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_payroll'), 'url' => '/apps/sbaio/payroll'],
                        ],
                    ],
                    [
                        'label' => 'Fixed Profiles',
                        'module_key' => 'sbaio_payroll',
                        'url' => '/apps/sbaio/payroll',
                        'desc' => t('sbaio.dashboard.card.fixed_profiles_desc'),
                        'count' => $counts['fixed_profiles'],
                        'state' => $counts['fixed_profiles'] > 0 ? t('sbaio.common.imported') : t('sbaio.common.empty'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_payroll'), 'url' => '/apps/sbaio/payroll'],
                            ['label' => t('sbaio.dashboard.quick.open_parity'), 'url' => '/apps/sbaio/payroll/parity'],
                        ],
                    ],
                ],
            ],
            [
                'title' => t('sbaio.dashboard.section.business_ops'),
                'subtitle' => t('sbaio.dashboard.section.business_ops_desc'),
                'module_keys' => ['sbaio_customers', 'sbaio_tasks', 'sbaio_sales', 'sbaio_expenses', 'sbaio_notices'],
                'cards' => [
                    [
                        'label' => t('nav.sbaio_customers'),
                        'module_key' => 'sbaio_customers',
                        'url' => '/apps/sbaio/customers',
                        'desc' => t('sbaio.dashboard.card.customers_desc'),
                        'count' => $counts['customers'],
                        'state' => $counts['customers'] > 0 ? t('sbaio.common.live') : t('sbaio.common.ready'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_customers'), 'url' => '/apps/sbaio/customers'],
                        ],
                    ],
                    [
                        'label' => t('nav.sbaio_sales'),
                        'module_key' => 'sbaio_sales',
                        'url' => '/apps/sbaio/sales',
                        'desc' => t('sbaio.dashboard.card.sales_desc'),
                        'count' => $counts['sales'],
                        'state' => $counts['sales'] > 0 ? t('sbaio.common.live') : t('sbaio.common.ready'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_sales'), 'url' => '/apps/sbaio/sales'],
                            ['label' => t('sbaio.dashboard.quick.open_customers'), 'url' => '/apps/sbaio/customers'],
                        ],
                    ],
                    [
                        'label' => t('nav.sbaio_expenses'),
                        'module_key' => 'sbaio_expenses',
                        'url' => '/apps/sbaio/expenses',
                        'desc' => t('sbaio.dashboard.card.expenses_desc'),
                        'count' => $counts['expenses'],
                        'state' => $counts['expenses'] > 0 ? t('sbaio.common.live') : t('sbaio.common.ready'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_expenses'), 'url' => '/apps/sbaio/expenses'],
                        ],
                    ],
                    [
                        'label' => t('nav.sbaio_tasks'),
                        'module_key' => 'sbaio_tasks',
                        'url' => '/apps/sbaio/tasks',
                        'desc' => t('sbaio.dashboard.card.tasks_desc'),
                        'count' => $counts['tasks'],
                        'state' => $counts['tasks'] > 0 ? t('sbaio.common.active') : t('sbaio.common.ready'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_tasks'), 'url' => '/apps/sbaio/tasks'],
                            ['label' => t('sbaio.dashboard.quick.open_notices'), 'url' => '/apps/sbaio/notices'],
                        ],
                    ],
                    [
                        'label' => t('nav.sbaio_notices'),
                        'module_key' => 'sbaio_notices',
                        'url' => '/apps/sbaio/notices',
                        'desc' => t('sbaio.dashboard.card.notices_desc'),
                        'count' => $counts['notices'],
                        'state' => $counts['notices'] > 0 ? t('sbaio.common.active') : t('sbaio.common.ready'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.open_notices'), 'url' => '/apps/sbaio/notices'],
                            ['label' => t('sbaio.dashboard.quick.open_tasks'), 'url' => '/apps/sbaio/tasks'],
                        ],
                    ],
                ],
            ],
            [
                'title' => t('sbaio.dashboard.section.needs_attention'),
                'subtitle' => t('sbaio.dashboard.section.needs_attention_desc'),
                'module_keys' => ['sbaio_attendance', 'sbaio_timecards', 'sbaio_payroll', 'sbaio_leave'],
                'cards' => [
                    [
                        'label' => t('sbaio.host.missing_punches'),
                        'module_key' => 'sbaio_attendance',
                        'url' => '/apps/sbaio/attendance',
                        'desc' => t('sbaio.dashboard.card.missing_punches_desc'),
                        'count' => $missingPunches,
                        'state' => $missingPunches > 0 ? t('sbaio.common.review') : t('sbaio.common.clear'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.fix_attendance'), 'url' => '/apps/sbaio/attendance'],
                        ],
                    ],
                    [
                        'label' => t('sbaio.host.draft_timecards'),
                        'module_key' => 'sbaio_timecards',
                        'url' => '/apps/sbaio/timecards',
                        'desc' => t('sbaio.dashboard.card.draft_timecards_desc'),
                        'count' => $draftTimecards,
                        'state' => $draftTimecards > 0 ? t('sbaio.common.needs_review') : t('sbaio.common.clear'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.review_timecards'), 'url' => '/apps/sbaio/timecards'],
                        ],
                    ],
                    [
                        'label' => t('sbaio.dashboard.card.draft_payroll'),
                        'module_key' => 'sbaio_payroll',
                        'url' => '/apps/sbaio/payroll',
                        'desc' => t('sbaio.dashboard.card.draft_payroll_desc'),
                        'count' => $draftPayroll,
                        'state' => $draftPayroll > 0 ? t('sbaio.common.needs_review') : t('sbaio.common.clear'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.review_payroll'), 'url' => '/apps/sbaio/payroll'],
                            ['label' => t('sbaio.dashboard.quick.open_parity'), 'url' => '/apps/sbaio/payroll/parity'],
                        ],
                    ],
                    [
                        'label' => t('sbaio.host.pending_leave'),
                        'module_key' => 'sbaio_leave',
                        'url' => '/apps/sbaio/leave',
                        'desc' => t('sbaio.dashboard.card.pending_leave_desc'),
                        'count' => $pendingLeave,
                        'state' => $pendingLeave > 0 ? t('sbaio.common.needs_review') : t('sbaio.common.clear'),
                        'quick_links' => [
                            ['label' => t('sbaio.dashboard.quick.review_leave'), 'url' => '/apps/sbaio/leave'],
                        ],
                    ],
                ],
            ],
        ];

        if ($moduleVisibility !== []) {
            $sections = array_values(array_filter(array_map(static function (array $section) use ($moduleVisibility): array {
                $cards = array_values(array_filter((array)($section['cards'] ?? []), static function (array $card) use ($moduleVisibility): bool {
                    $moduleKey = (string)($card['module_key'] ?? '');
                    return $moduleKey === '' || in_array($moduleKey, $moduleVisibility, true);
                }));
                $section['cards'] = $cards;
                return $section;
            }, $sections), static fn(array $section): bool => (array)($section['cards'] ?? []) !== []));
        }

        $view->render('sbaio::dashboard.php', [
            'pageTitle' => t('nav.sbaio_dashboard'),
            'heroStats' => [
                ['label' => t('nav.sbaio_staff'), 'value' => $counts['staff']],
                ['label' => t('sbaio.host.missing_punches'), 'value' => $missingPunches],
                ['label' => t('sbaio.host.draft_timecards'), 'value' => $draftTimecards],
                ['label' => t('sbaio.dashboard.card.draft_payroll'), 'value' => $draftPayroll],
            ],
            'sections' => $sections,
            'suite_role_template_labels' => $suiteRoleLabels,
            'module_permission_template_labels' => $moduleTemplateLabels,
        ]);
    }

    public static function reports($view): void
    {
        $attendance = DB::fetchAll(
            "SELECT DATE_FORMAT(attendance_date, '%Y-%m') AS ym,
                    COUNT(*) AS rows_count,
                    SUM(CASE WHEN work_start_at IS NULL OR work_end_at IS NULL THEN 1 ELSE 0 END) AS missing_count
             FROM sbaio_attendance_daily
             GROUP BY DATE_FORMAT(attendance_date, '%Y-%m')
             ORDER BY ym DESC
             LIMIT 12"
        );

        $timecards = DB::fetchAll(
            "SELECT DATE_FORMAT(period_start, '%Y-%m') AS ym,
                    COUNT(*) AS periods,
                    SUM(total_worked_minutes) AS worked_minutes,
                    SUM(overtime_minutes) AS overtime_minutes
             FROM sbaio_timecard_periods
             GROUP BY DATE_FORMAT(period_start, '%Y-%m')
             ORDER BY ym DESC
             LIMIT 12"
        );

        $payroll = DB::fetchAll(
            "SELECT DATE_FORMAT(r.period_start, '%Y-%m') AS ym,
                    COUNT(pr.id) AS records,
                    SUM(pr.gross_amount) AS gross_total,
                    SUM(pr.net_amount) AS net_total
             FROM sbaio_payroll_runs r
             LEFT JOIN sbaio_payroll_records pr ON pr.payroll_run_id = r.id
             GROUP BY DATE_FORMAT(r.period_start, '%Y-%m')
             ORDER BY ym DESC
             LIMIT 12"
        );

        $needsAttention = [
            ['label' => t('sbaio.host.missing_punches'), 'value' => self::countWhere('sbaio_attendance_daily', "(work_start_at IS NULL OR work_end_at IS NULL) AND LOWER(COALESCE(day_marker, 'workday')) NOT IN ('holiday','leave','off_day','closed')"), 'url' => '/apps/sbaio/attendance'],
            ['label' => t('sbaio.host.draft_timecards'), 'value' => self::countWhere('sbaio_timecard_periods', "LOWER(COALESCE(approval_status, 'draft')) IN ('draft','generated','pending')"), 'url' => '/apps/sbaio/timecards'],
            ['label' => t('sbaio.dashboard.card.draft_payroll'), 'value' => self::countWhere('sbaio_payroll_runs', "LOWER(COALESCE(run_status, 'draft')) IN ('draft','generated','pending')"), 'url' => '/apps/sbaio/payroll'],
            ['label' => t('sbaio.host.pending_leave'), 'value' => self::countWhere('sbaio_leave_requests', "LOWER(COALESCE(leave_status, 'draft')) IN ('draft','pending','submitted')"), 'url' => '/apps/sbaio/leave'],
        ];

        $view->render('sbaio::reports.php', [
            'pageTitle' => t('sbaio.reports.title'),
            'moduleTitle' => t('sbaio.reports.title'),
            'moduleDescription' => t('sbaio.reports.description'),
            'attendanceSeries' => array_reverse($attendance),
            'timecardSeries' => array_reverse($timecards),
            'payrollSeries' => array_reverse($payroll),
            'needsAttention' => $needsAttention,
        ]);
    }

    private static function count(string $table): int
    {
        if (!self::tableExists($table)) {
            return 0;
        }
        $row = DB::fetchOne("SELECT COUNT(*) AS total FROM {$table}");
        return (int)($row['total'] ?? 0);
    }

    private static function countWhere(string $table, string $where): int
    {
        if (!self::tableExists($table)) {
            return 0;
        }
        $row = DB::fetchOne("SELECT COUNT(*) AS total FROM {$table} WHERE {$where}");
        return (int)($row['total'] ?? 0);
    }

    private static function tableExists(string $table): bool
    {
        try {
            return DB::fetchOne(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            ) !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
