<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

use Apps\Manufacturing\Services\DisplayActivityContributionService;
use Apps\Manufacturing\Services\OperatorLayerAdapters\CoverageAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\DispatchAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\MachinesAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\QcAdapter;

/**
 * DisplaySurfaceComposer
 *
 * Composes the readonly display/kiosk layer (/displays/*).
 *
 * Design constraints:
 * - NO forms, NO buttons that mutate state, NO CSRF tokens
 * - Self-contained: no navigation links escape to /apps/* or /u/* or /admin/*
 * - Auto-refresh header (configurable via query param ?refresh=N)
 * - Minimal chrome: logo + company name + clock; no sidebar, no user menu
 * - Data from existing adapters (Coverage, Dispatch, Machines, QC)
 * - Suitable for floor TV displays and kiosk monitors
 */
final class DisplaySurfaceComposer
{
    /** @var array<string,mixed> */
    private array $context;

    /** @param array<string,mixed> $context */
    public function __construct(array $context)
    {
        $this->context = $context;
    }

    private function tr(string $key, string $fallback, array $params = []): string
    {
        if (function_exists('t')) {
            $translated = (string)t($key, $params);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }
        if ($params === []) {
            return $fallback;
        }
        $replace = [];
        foreach ($params as $k => $v) {
            $replace['{' . $k . '}'] = (string)$v;
        }
        return strtr($fallback, $replace);
    }

    public function render(): string
    {
        $companyName     = htmlspecialchars(trim((string)($this->context['company_name'] ?? 'IPM Local')));
        $branchName      = htmlspecialchars(trim((string)($this->context['branch_name'] ?? '')));
        $companyLogo     = htmlspecialchars(trim((string)($this->context['company_logo'] ?? '')));
        $companyLogoSvgInline = trim((string)($this->context['company_logo_svg_inline'] ?? ''));
        $companyLogoSvgTheme  = trim((string)($this->context['company_logo_svg_theme'] ?? ''));
        $companyFallbackText = trim((string)($this->context['company_fallback_text'] ?? 'OdareHub'));
        $companyFallbackTextCompact = trim((string)($this->context['company_fallback_text_compact'] ?? 'S'));
        $displayMode     = (string)($this->context['display_mode'] ?? 'user');
        $query           = (array)($this->context['current_query'] ?? []);
        $displayPanels   = (array)($this->context['display_panels'] ?? []);

        // Auto-refresh: default 30s (floor displays need fresh data), cap at 3600s
        $refreshSeconds = max(0, min(3600, (int)($query['refresh'] ?? 30)));

        // Slide interval: how long each slide is shown; default 8s, range 4-60s
        $slideInterval = max(4, min(60, (int)($query['slide'] ?? 8)));

        // Gather data from adapters
        $coverage = $this->safeGet('coverage', static fn() => CoverageAdapter::getData());
        $dispatch = $this->safeGet('dispatch', static fn() => DispatchAdapter::getData());
        $machines = $this->safeGet('machines', static fn() => MachinesAdapter::getData());
        $qc       = $this->safeGet('qc', static fn() => QcAdapter::getData());

        // Recent activity feed (last 2 hours)
        $activities = $this->recentActivities();

        $today = date('Y-m-d');
        $nowLabel = $this->tr('display.now', 'Now');

        ob_start();
        require __DIR__ . '/../Views/display/floor.php';
        return (string)ob_get_clean();
    }

    /**
     * Safe adapter call: returns data or error fallback.
     *
     * @param string $key  Adapter name for error logging
     * @param callable $fn Adapter call returning array
     * @return array<string,mixed>
     */
    private function safeGet(string $key, callable $fn): array
    {
        try {
            /** @var array<string,mixed> $result */
            $result = $fn();
            return $result;
        } catch (\Throwable $e) {
            error_log('DisplaySurfaceComposer::' . $key . ': ' . $e->getMessage());
            return ['error' => $e->getMessage(), 'empty' => true];
        }
    }

    /**
     * Recent activity feed: assembly completions, dispatches, QC results in the last 2 hours.
     * @return array<int, array<string,string>>
     */
    private function recentActivities(): array
    {
        if (class_exists(DisplayActivityContributionService::class) && method_exists(DisplayActivityContributionService::class, 'recentActivities')) {
            return DisplayActivityContributionService::recentActivities();
        }

        return [];
    }

    /**
     * Summary count row for today's daily orders.
     * @return array{count:int,qty:float}
     */
    public function todayOrderSummary(): array
    {
        $today = date('Y-m-d');
        if (class_exists(DisplayActivityContributionService::class) && method_exists(DisplayActivityContributionService::class, 'todayOrderSummary')) {
            return DisplayActivityContributionService::todayOrderSummary($today);
        }

        return ['count' => 0, 'qty' => 0.0];
    }
}
