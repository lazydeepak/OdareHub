<?php
declare(strict_types=1);

namespace Plugins\Schedules\Controllers;

use App\Core\Auth;
use App\Core\DB;

final class SchedulesController
{
    public static function index($view): void
    {
        $templateRows = DB::fetchAll('SELECT id, template_name, shift_code, day_of_week, range_start_date, range_end_date, expected_start_time, expected_end_time, break_start_time, break_end_time, expected_break_minutes, job_label, scheduled_minutes, is_active FROM sbaio_schedule_templates ORDER BY id DESC LIMIT 12');
        $assignmentRows = DB::fetchAll(
            'SELECT a.schedule_date, s.full_name, a.legacy_name_ref, t.template_name, a.day_of_week, a.range_start_date, a.range_end_date, a.expected_start_time, a.expected_end_time, a.break_start_time, a.break_end_time, a.expected_break_minutes, a.job_label, a.scheduled_minutes, a.assignment_status
             FROM sbaio_schedule_assignments a
             LEFT JOIN sbaio_staff s ON s.id = a.staff_id
             LEFT JOIN sbaio_schedule_templates t ON t.id = a.template_id
             ORDER BY a.schedule_date DESC, a.id DESC
             LIMIT 40'
        );
        $staffRows = DB::fetchAll("SELECT id, full_name, employee_code FROM sbaio_staff WHERE employment_status='active' OR employment_status IS NULL ORDER BY full_name ASC");

        $view->render('Schedules::index.php', [
            'pageTitle' => t('sbaio.schedules.title'),
            'moduleTitle' => t('sbaio.schedules.title'),
            'moduleDescription' => t('sbaio.schedules.description'),
            'templateRows' => $templateRows,
            'assignmentRows' => $assignmentRows,
            'staffRows' => $staffRows,
            'message' => (string)($_GET['ok'] ?? ''),
            'error' => (string)($_GET['err'] ?? ''),
        ]);
    }

    public static function createTemplate(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $templateName = trim((string)($_POST['template_name'] ?? ''));
        if ($templateName === '') {
            self::redirect('err', t('sbaio.schedules.template_required'));
        }

        DB::query(
            'INSERT INTO sbaio_schedule_templates (template_name, shift_code, day_of_week, range_start_date, range_end_date, expected_start_time, expected_end_time, break_start_time, break_end_time, expected_break_minutes, job_label, scheduled_minutes, legacy_name_ref, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $templateName,
                self::nullIfBlank((string)($_POST['shift_code'] ?? '')),
                self::nullIfBlank((string)($_POST['day_of_week'] ?? '')),
                self::nullIfBlank((string)($_POST['range_start_date'] ?? '')),
                self::nullIfBlank((string)($_POST['range_end_date'] ?? '')),
                self::nullIfBlank((string)($_POST['expected_start_time'] ?? '')),
                self::nullIfBlank((string)($_POST['expected_end_time'] ?? '')),
                self::nullIfBlank((string)($_POST['break_start_time'] ?? '')),
                self::nullIfBlank((string)($_POST['break_end_time'] ?? '')),
                (int)($_POST['expected_break_minutes'] ?? 0),
                self::nullIfBlank((string)($_POST['job_label'] ?? '')),
                (int)($_POST['scheduled_minutes'] ?? 0),
                self::nullIfBlank((string)($_POST['legacy_name_ref'] ?? '')),
                isset($_POST['is_active']) ? 1 : 0,
            ]
        );

        self::redirect('ok', t('sbaio.schedules.template_created'));
    }

    public static function assignRange(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $staffId = (int)($_POST['staff_id'] ?? 0);
        $startDate = (string)($_POST['start_date'] ?? '');
        $endDate = (string)($_POST['end_date'] ?? '');
        if ($staffId <= 0 || $startDate === '' || $endDate === '') {
            self::redirect('err', t('sbaio.schedules.assignment_required'));
        }

        $start = new \DateTimeImmutable($startDate);
        $end = new \DateTimeImmutable($endDate);
        if ($end < $start) {
            self::redirect('err', t('sbaio.schedules.assignment_date_error'));
        }

        $templateId = (int)($_POST['template_id'] ?? 0);
        $template = $templateId > 0
            ? DB::fetchOne('SELECT * FROM sbaio_schedule_templates WHERE id = ? LIMIT 1', [$templateId])
            : null;

        $expectedStart = self::nullIfBlank((string)($_POST['expected_start_time'] ?? '')) ?? (string)($template['expected_start_time'] ?? '');
        $expectedEnd = self::nullIfBlank((string)($_POST['expected_end_time'] ?? '')) ?? (string)($template['expected_end_time'] ?? '');
        $breakStart = self::nullIfBlank((string)($_POST['break_start_time'] ?? '')) ?? (string)($template['break_start_time'] ?? '');
        $breakEnd = self::nullIfBlank((string)($_POST['break_end_time'] ?? '')) ?? (string)($template['break_end_time'] ?? '');
        $breakMinutes = trim((string)($_POST['expected_break_minutes'] ?? ''));
        $resolvedBreak = $breakMinutes !== '' ? (int)$breakMinutes : (int)($template['expected_break_minutes'] ?? 0);
        $jobLabel = self::nullIfBlank((string)($_POST['job_label'] ?? '')) ?? self::nullIfBlank((string)($template['job_label'] ?? ''));
        $legacyNameRef = self::nullIfBlank((string)($_POST['legacy_name_ref'] ?? '')) ?? self::nullIfBlank((string)($template['legacy_name_ref'] ?? ''));
        $dayOfWeek = self::nullIfBlank((string)($_POST['day_of_week'] ?? '')) ?? self::nullIfBlank((string)($template['day_of_week'] ?? ''));
        $rangeStart = self::nullIfBlank((string)($_POST['range_start_date'] ?? '')) ?? $startDate;
        $rangeEnd = self::nullIfBlank((string)($_POST['range_end_date'] ?? '')) ?? $endDate;
        $scheduledMinutes = (int)($_POST['scheduled_minutes'] ?? 0);
        if ($scheduledMinutes <= 0) {
            $scheduledMinutes = (int)($template['scheduled_minutes'] ?? 0);
        }

        $period = new \DatePeriod($start, new \DateInterval('P1D'), $end->modify('+1 day'));
        foreach ($period as $date) {
            DB::query(
                'INSERT INTO sbaio_schedule_assignments (staff_id, legacy_name_ref, schedule_date, template_id, expected_start_time, expected_end_time, break_start_time, break_end_time, expected_break_minutes, day_of_week, range_start_date, range_end_date, job_label, scheduled_minutes, assignment_status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE legacy_name_ref=VALUES(legacy_name_ref), template_id=VALUES(template_id), expected_start_time=VALUES(expected_start_time), expected_end_time=VALUES(expected_end_time), break_start_time=VALUES(break_start_time), break_end_time=VALUES(break_end_time), expected_break_minutes=VALUES(expected_break_minutes), day_of_week=VALUES(day_of_week), range_start_date=VALUES(range_start_date), range_end_date=VALUES(range_end_date), job_label=VALUES(job_label), scheduled_minutes=VALUES(scheduled_minutes), assignment_status=VALUES(assignment_status)',
                [
                    $staffId,
                    $legacyNameRef,
                    $date->format('Y-m-d'),
                    $templateId > 0 ? $templateId : null,
                    $expectedStart !== '' ? $expectedStart : null,
                    $expectedEnd !== '' ? $expectedEnd : null,
                    $breakStart !== '' ? $breakStart : null,
                    $breakEnd !== '' ? $breakEnd : null,
                    $resolvedBreak,
                    $dayOfWeek,
                    $rangeStart,
                    $rangeEnd,
                    $jobLabel,
                    $scheduledMinutes,
                    'planned',
                ]
            );
        }

        self::redirect('ok', t('sbaio.schedules.assignment_saved'));
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private static function redirect(string $key, string $message): void
    {
        header('Location: /apps/sbaio/schedules?' . $key . '=' . rawurlencode($message));
        exit;
    }
}
