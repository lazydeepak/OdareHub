<?php
declare(strict_types=1);

namespace Plugins\Payroll\Controllers;

use App\Core\Auth;
use App\Core\DB;
use Plugins\Payroll\Services\PayrollParityValidationService;
use Plugins\Payroll\Services\PayrollScaffoldService;

final class PayrollController
{
    public static function index($view): void
    {
        $runRows = DB::fetchAll('SELECT period_key, period_start, period_end, period_year, period_month, period_month_label, payslip_variant, run_status, approval_status, locked_at FROM sbaio_payroll_runs ORDER BY period_start DESC, id DESC LIMIT 12');
        $recordRows = DB::fetchAll(
            'SELECT r.id, s.full_name, r.legacy_name_ref, r.salary_type, r.salary_rate, r.salary_reference_amount, r.payslip_variant, r.worked_days, r.workday_count, r.worked_minutes, r.worked_hours, r.leave_days, r.holiday_days, r.overtime_minutes, r.health_insurance_amount, r.pension_insurance_amount, r.employment_insurance_amount, r.total_deductions_amount, r.base_salary_amount, r.hourly_pay_amount, r.gross_amount, r.net_amount
             FROM sbaio_payroll_records r
             LEFT JOIN sbaio_staff s ON s.id = r.staff_id
             ORDER BY r.id DESC
             LIMIT 20'
        );
        $approvedPeriods = DB::fetchAll(
            "SELECT DISTINCT period_key, MIN(period_start) AS period_start, MAX(period_end) AS period_end
             FROM sbaio_timecard_periods
             WHERE approval_status = 'approved'
             GROUP BY period_key
             ORDER BY period_start DESC"
        );

        $view->render('Payroll::index.php', [
            'pageTitle' => t('sbaio.payroll.title'),
            'moduleTitle' => t('sbaio.payroll.title'),
            'moduleDescription' => t('sbaio.payroll.description'),
            'runRows' => $runRows,
            'recordRows' => $recordRows,
            'approvedPeriods' => $approvedPeriods,
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function parity($view): void
    {
        $report = PayrollParityValidationService::report(80);
        $view->render('Payroll::parity.php', [
            'pageTitle' => t('sbaio.payroll.parity_title'),
            'moduleTitle' => t('sbaio.payroll.parity_title'),
            'moduleDescription' => t('sbaio.payroll.parity_description'),
            'rows' => $report['rows'],
            'summary' => $report['summary'],
        ]);
    }

    public static function createRun(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $periodKey = (string)($_POST['period_key'] ?? '');
        if ($periodKey === '') {
            self::redirect('err', t('sbaio.payroll.period_required'));
        }

        $result = PayrollScaffoldService::createFromApprovedPeriod($periodKey);
        self::redirect('ok', t('sbaio.payroll.run_created', ['count' => (int)($result['record_count'] ?? 0)]));
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/payroll?' . $key . '=' . rawurlencode($message));
        exit;
    }
}
