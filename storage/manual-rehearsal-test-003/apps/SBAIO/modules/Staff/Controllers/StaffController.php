<?php
declare(strict_types=1);

namespace Plugins\Staff\Controllers;

use App\Core\Auth;
use App\Core\DB;

final class StaffController
{
    public static function index($view): void
    {
        $rows = DB::fetchAll('SELECT id, legacy_sn, employee_code, full_name, name_ref, email, staff_type, salary_type, salary_rate, employment_status, department_name, join_date, exit_date, legacy_job_details FROM sbaio_staff ORDER BY id DESC LIMIT 25');
        $view->render('Staff::index.php', [
            'pageTitle' => t('sbaio.staff.title'),
            'moduleTitle' => t('sbaio.staff.title'),
            'moduleDescription' => t('sbaio.staff.description'),
            'rows' => $rows,
            'columns' => ['Code', 'Name', 'Email', 'Type', 'Salary Type', 'Rate', 'Employment', 'Department'],
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function create(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $fullName = trim((string)($_POST['full_name'] ?? ''));
        if ($fullName === '') {
            self::redirect('err', t('sbaio.staff.full_name_required'));
        }

        DB::query(
            'INSERT INTO sbaio_staff (
                legacy_sn, employee_code, full_name, name_ref, email, role_label, staff_type, salary_type, salary_rate,
                helper_classification, nationality, gender, date_of_birth, employment_status, department_name, branch_name,
                hire_date, join_date, exit_date, legacy_job_details, residence_card_front, residence_card_back, status
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                self::nullIfBlank((string)($_POST['legacy_sn'] ?? '')),
                self::nullIfBlank((string)($_POST['employee_code'] ?? '')),
                $fullName,
                self::nullIfBlank((string)($_POST['name_ref'] ?? '')),
                self::nullIfBlank((string)($_POST['email'] ?? '')),
                self::nullIfBlank((string)($_POST['role_label'] ?? '')),
                self::nullIfBlank((string)($_POST['staff_type'] ?? '')),
                self::nullIfBlank((string)($_POST['salary_type'] ?? '')),
                self::decimalOrNull((string)($_POST['salary_rate'] ?? '')),
                self::nullIfBlank((string)($_POST['helper_classification'] ?? '')),
                self::nullIfBlank((string)($_POST['nationality'] ?? '')),
                self::nullIfBlank((string)($_POST['gender'] ?? '')),
                self::nullIfBlank((string)($_POST['date_of_birth'] ?? '')),
                self::nullIfBlank((string)($_POST['employment_status'] ?? '')) ?? 'active',
                self::nullIfBlank((string)($_POST['department_name'] ?? '')),
                self::nullIfBlank((string)($_POST['branch_name'] ?? '')),
                self::nullIfBlank((string)($_POST['hire_date'] ?? '')),
                self::nullIfBlank((string)($_POST['join_date'] ?? '')),
                self::nullIfBlank((string)($_POST['exit_date'] ?? '')),
                self::nullIfBlank((string)($_POST['legacy_job_details'] ?? '')),
                self::nullIfBlank((string)($_POST['residence_card_front'] ?? '')),
                self::nullIfBlank((string)($_POST['residence_card_back'] ?? '')),
                'active',
            ]
        );

        self::redirect('ok', t('sbaio.staff.created'));
    }

    private static function decimalOrNull(string $value): ?float
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        return (float)$value;
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/staff?' . $key . '=' . rawurlencode($message));
        exit;
    }
}
