<?php
declare(strict_types=1);

namespace Plugins\Timecards\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\PackageManager;
use App\Core\PdfService;
use Plugins\Timecards\Services\TimecardGenerationService;
use Plugins\Timecards\Services\TimecardService;

final class TimecardsController
{
    public static function index($view): void
    {
        $filters = TimecardService::resolveFilters($_GET);
        $dailyRows = TimecardService::getDailyRows($filters);
        $summary = TimecardService::getMonthlySummary($filters);
        $message = (string)($_GET['ok'] ?? '');
        $error = (string)($_GET['err'] ?? '');

        if ((string)($_GET['export'] ?? '') === 'pdf') {
            if (!self::pdfService()->isReady()) {
                header('Location: ' . self::buildUrl($filters, [
                    'mode' => 'print',
                    'err' => (string)t('timecards.pdf_unavailable'),
                ]));
                exit;
            }

            $html = self::renderPdfTemplate('Timecards::pdf.php', [
                'pageTitle' => (string)t('timecards.title'),
                'filters' => $filters,
                'dailyRows' => $dailyRows,
                'summary' => $summary,
                'printUrl' => self::buildUrl($filters, ['mode' => 'print']),
                'pdfUrl' => self::buildUrl($filters, ['export' => 'pdf']),
                'screenUrl' => self::buildUrl($filters, ['mode' => 'screen']),
                'resetUrl' => '/apps/sbaio/timecards',
                'returnQuery' => http_build_query(self::filterQuery($filters)),
                'pdfReady' => true,
                'message' => $message,
                'error' => $error,
            ]);

            $pdfBinary = self::pdfService()->outputFromHtml($html, [
                'defaultFont' => 'DejaVu Sans',
                'isPhpEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'paper' => 'A4',
                'orientation' => 'portrait',
            ]);

            $filename = sprintf(
                'timecard-%s-%04d-%02d.pdf',
                self::slug((string)($summary['selected_staff_code'] ?? $summary['selected_staff_name'] ?? 'report')),
                (int)($filters['year'] ?? date('Y')),
                (int)($filters['month'] ?? date('n'))
            );
            self::pdfService()->stream($pdfBinary, $filename, false);
            return;
        }

        $view->render('Timecards::index.php', [
            'pageTitle' => (string)t('timecards.title'),
            'filters' => $filters,
            'dailyRows' => $dailyRows,
            'summary' => $summary,
            'message' => $message,
            'error' => $error,
            'printUrl' => self::buildUrl($filters, ['mode' => 'print']),
            'pdfUrl' => self::buildUrl($filters, ['export' => 'pdf']),
            'screenUrl' => self::buildUrl($filters, ['mode' => 'screen']),
            'resetUrl' => '/apps/sbaio/timecards',
            'returnQuery' => http_build_query(self::filterQuery($filters)),
            'pdfReady' => self::pdfService()->isReady(),
            'isPrintMode' => (string)($filters['mode'] ?? 'screen') === 'print',
        ]);
    }

    public static function generate(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $startDate = (string)($_POST['period_start'] ?? '');
        $endDate = (string)($_POST['period_end'] ?? '');
        $returnQuery = self::sanitizeReturnQuery((string)($_POST['return_query'] ?? ''));
        if ($startDate === '' || $endDate === '') {
            self::redirect('err', (string)t('timecards.error.period_required'), $returnQuery);
        }

        try {
            $result = TimecardGenerationService::generate($startDate, $endDate);
            self::redirect('ok', (string)t('timecards.message.generated_period', [
                'period' => (string)($result['period_key'] ?? ''),
                'count' => (int)($result['staff_count'] ?? 0),
            ]), $returnQuery);
        } catch (\Throwable $e) {
            self::redirect('err', $e->getMessage(), $returnQuery);
        }
    }

    public static function approvePeriod(): void
    {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $periodKey = (string)($_POST['period_key'] ?? '');
        $periodStart = (string)($_POST['period_start'] ?? '');
        $periodEnd = (string)($_POST['period_end'] ?? '');
        $returnQuery = self::sanitizeReturnQuery((string)($_POST['return_query'] ?? ''));
        if ($staffId <= 0 || $periodKey === '' || $periodStart === '' || $periodEnd === '') {
            self::redirect('err', (string)t('timecards.error.approval_requires_details'), $returnQuery);
        }

        try {
            DB::query(
                "UPDATE sbaio_timecards_daily SET approval_status='approved', approved_at=NOW() WHERE staff_id = ? AND work_date BETWEEN ? AND ?",
                [$staffId, $periodStart, $periodEnd]
            );
            DB::query(
                "UPDATE sbaio_timecard_periods SET approval_status='approved' WHERE staff_id = ? AND period_key = ?",
                [$staffId, $periodKey]
            );

            self::redirect('ok', (string)t('timecards.message.period_approved'), $returnQuery);
        } catch (\Throwable $e) {
            self::redirect('err', $e->getMessage(), $returnQuery);
        }
    }

    private static function redirect(string $key, string $message, string $returnQuery = ''): void
    {
        $params = [];
        if ($returnQuery !== '') {
            parse_str($returnQuery, $params);
        }
        $params[$key] = $message;
        header('Location: /apps/sbaio/timecards?' . http_build_query($params));
        exit;
    }

    /**
     * @param array<string,mixed> $filters
     * @param array<string,mixed> $overrides
     */
    private static function buildUrl(array $filters, array $overrides = []): string
    {
        $query = array_merge(self::filterQuery($filters), $overrides);
        foreach ($query as $key => $value) {
            if (
                $value === null
                || $value === ''
                || (($key === 'staff_id' || $key === 'year' || $key === 'month') && (int)$value <= 0)
                || ($key === 'attendance_type' && $value === 'all')
                || ($key === 'mode' && $value === 'screen')
            ) {
                unset($query[$key]);
            }
        }

        $queryString = http_build_query($query);
        return '/apps/sbaio/timecards' . ($queryString !== '' ? '?' . $queryString : '');
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private static function filterQuery(array $filters): array
    {
        return [
            'staff_id' => (int)($filters['staff_id'] ?? 0),
            'year' => (int)($filters['year'] ?? 0),
            'month' => (int)($filters['month'] ?? 0),
            'attendance_type' => (string)($filters['attendance_type'] ?? 'all'),
            'branch' => (string)($filters['branch'] ?? ''),
            'department' => (string)($filters['department'] ?? ''),
            'mode' => (string)($filters['mode'] ?? 'screen'),
        ];
    }

    private static function sanitizeReturnQuery(string $query): string
    {
        $query = ltrim(trim($query), '?');
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);
        $allowed = [];
        foreach (['staff_id', 'year', 'month', 'attendance_type', 'branch', 'department', 'mode'] as $key) {
            if (array_key_exists($key, $params)) {
                $allowed[$key] = $params[$key];
            }
        }

        return http_build_query($allowed);
    }

    private static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? 'report';
        $value = trim($value, '-');
        return $value !== '' ? $value : 'report';
    }

    private static function pdfService(): PdfService
    {
        static $service = null;
        if (!($service instanceof PdfService)) {
            $service = new PdfService();
        }
        return $service;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function renderPdfTemplate(string $viewKey, array $data): string
    {
        ob_start();
        extract($data);
        [$namespace, $viewFile] = array_pad(explode('::', $viewKey, 2), 2, '');
        include rtrim(PackageManager::pluginSourcePath($namespace), '/') . '/Views/' . $viewFile;
        return (string)ob_get_clean();
    }
}
