<?php
declare(strict_types=1);

namespace Plugins\Attendance\Controllers;

use App\Core\Auth;
use App\Core\DB;

final class AttendanceController
{
    public static function index($view): void
    {
        $rows = DB::fetchAll(
            'SELECT a.attendance_date, s.full_name, s.employee_code, a.work_start_at, a.work_end_at, a.break_start_at, a.break_end_at, a.day_marker, a.source_label, a.correction_note
             FROM sbaio_attendance_daily a
             LEFT JOIN sbaio_staff s ON s.id = a.staff_id
             ORDER BY a.attendance_date DESC, a.id DESC
             LIMIT 31'
        );
        $staffRows = DB::fetchAll("SELECT id, full_name, employee_code FROM sbaio_staff WHERE employment_status='active' OR employment_status IS NULL ORDER BY full_name ASC");

        $view->render('Attendance::index.php', [
            'pageTitle' => t('sbaio.attendance.title'),
            'moduleTitle' => t('sbaio.attendance.title'),
            'moduleDescription' => t('sbaio.attendance.description'),
            'rows' => $rows,
            'staffRows' => $staffRows,
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function saveEntry(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $staffId = (int)($_POST['staff_id'] ?? 0);
        $date = (string)($_POST['attendance_date'] ?? '');
        if ($staffId <= 0 || $date === '') {
            self::redirect('err', t('sbaio.attendance.error_required'));
        }

        $note = self::nullIfBlank((string)($_POST['correction_note'] ?? ''));
        DB::query(
            'INSERT INTO sbaio_attendance_daily (staff_id, attendance_date, work_start_at, work_end_at, break_start_at, break_end_at, day_marker, source_label, is_manual_correction, correction_note)
             VALUES (?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE work_start_at=VALUES(work_start_at), work_end_at=VALUES(work_end_at), break_start_at=VALUES(break_start_at), break_end_at=VALUES(break_end_at), day_marker=VALUES(day_marker), source_label=VALUES(source_label), is_manual_correction=VALUES(is_manual_correction), correction_note=VALUES(correction_note)',
            [
                $staffId,
                $date,
                self::combineDateTime($date, (string)($_POST['work_start_time'] ?? '')),
                self::combineDateTime($date, (string)($_POST['work_end_time'] ?? '')),
                self::combineDateTime($date, (string)($_POST['break_start_time'] ?? '')),
                self::combineDateTime($date, (string)($_POST['break_end_time'] ?? '')),
                self::nullIfBlank((string)($_POST['day_marker'] ?? '')) ?? 'workday',
                'manual',
                $note !== null ? 1 : 0,
                $note,
            ]
        );

        self::redirect('ok', t('sbaio.attendance.saved'));
    }

    private static function combineDateTime(string $date, string $time): ?string
    {
        $date = trim($date);
        $time = trim($time);
        if ($date === '' || $time === '') {
            return null;
        }
        return $date . ' ' . $time . ':00';
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/attendance?' . $key . '=' . rawurlencode($message));
        exit;
    }
}
