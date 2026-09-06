<?php
declare(strict_types=1);

namespace Plugins\Leave\Controllers;

use App\Core\Auth;
use App\Core\DB;

final class LeaveController
{
    public static function index($view): void
    {
        $leaveRows = DB::fetchAll(
            'SELECT l.start_date, l.end_date, s.full_name, l.legacy_name_ref, l.leave_code, l.leave_type, l.partial_day_unit, l.leave_status, l.approved_at
             FROM sbaio_leave_requests l
             LEFT JOIN sbaio_staff s ON s.id = l.staff_id
             ORDER BY l.start_date DESC, l.id DESC
             LIMIT 20'
        );
        $holidayRows = DB::fetchAll('SELECT holiday_date, holiday_name, holiday_type, holiday_code, is_closed_day, notes, is_active FROM sbaio_holiday_calendar ORDER BY holiday_date DESC, id DESC LIMIT 20');
        $staffRows = DB::fetchAll("SELECT id, full_name, employee_code FROM sbaio_staff WHERE employment_status='active' OR employment_status IS NULL ORDER BY full_name ASC");

        $view->render('Leave::index.php', [
            'pageTitle' => t('sbaio.leave.title'),
            'moduleTitle' => t('sbaio.leave.title'),
            'moduleDescription' => t('sbaio.leave.description'),
            'leaveRows' => $leaveRows,
            'holidayRows' => $holidayRows,
            'staffRows' => $staffRows,
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function createLeave(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $startDate = (string)($_POST['start_date'] ?? '');
        $endDate = (string)($_POST['end_date'] ?? '');
        if ($staffId <= 0 || $startDate === '' || $endDate === '') {
            self::redirect('err', t('sbaio.leave.required'));
        }

        DB::query(
            'INSERT INTO sbaio_leave_requests (staff_id, legacy_name_ref, leave_code, leave_type, start_date, end_date, partial_day_unit, leave_status, notes, approved_by, approved_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $staffId,
                self::nullIfBlank((string)($_POST['legacy_name_ref'] ?? '')),
                self::nullIfBlank((string)($_POST['leave_code'] ?? '')),
                trim((string)($_POST['leave_type'] ?? '')) ?: 'leave',
                $startDate,
                $endDate,
                self::nullIfBlank((string)($_POST['partial_day_unit'] ?? '')),
                trim((string)($_POST['leave_status'] ?? '')) ?: 'approved',
                self::nullIfBlank((string)($_POST['notes'] ?? '')),
                trim((string)($_POST['leave_status'] ?? '')) === 'approved' ? 'system' : null,
                trim((string)($_POST['leave_status'] ?? '')) === 'approved' ? date('Y-m-d H:i:s') : null,
            ]
        );

        self::redirect('ok', t('sbaio.leave.saved'));
    }

    public static function createHoliday(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $holidayDate = (string)($_POST['holiday_date'] ?? '');
        $holidayName = trim((string)($_POST['holiday_name'] ?? ''));
        if ($holidayDate === '' || $holidayName === '') {
            self::redirect('err', t('sbaio.leave.holiday_required'));
        }

        DB::query(
            'INSERT INTO sbaio_holiday_calendar (holiday_date, holiday_name, holiday_type, holiday_code, is_closed_day, notes, is_active)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE holiday_name=VALUES(holiday_name), holiday_type=VALUES(holiday_type), holiday_code=VALUES(holiday_code), is_closed_day=VALUES(is_closed_day), notes=VALUES(notes), is_active=VALUES(is_active)',
            [
                $holidayDate,
                $holidayName,
                trim((string)($_POST['holiday_type'] ?? '')) ?: 'public_holiday',
                self::nullIfBlank((string)($_POST['holiday_code'] ?? '')),
                isset($_POST['is_closed_day']) ? 1 : 0,
                self::nullIfBlank((string)($_POST['notes'] ?? '')),
                isset($_POST['is_active']) ? 1 : 0,
            ]
        );

        self::redirect('ok', t('sbaio.leave.holiday_saved'));
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/leave?' . $key . '=' . rawurlencode($message));
        exit;
    }
}
