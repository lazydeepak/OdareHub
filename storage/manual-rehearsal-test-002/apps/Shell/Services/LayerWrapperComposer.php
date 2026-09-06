<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\View;

/**
 * LayerWrapperComposer (Abstract Base)
 *
 * Normalizes wrapper composition across Display, Operator, and Admin layers.
 *
 * Each layer implements wrapper rendering via subclasses:
 * - Shared HTML structure and CSS classes
 * - Layer-specific sidebar generation (manual vs auto)
 * - Layer-aware breadcrumbs
 * - Footer visibility on all layers
 *
 * Responsibilities:
 * - Render header (logo, company name, navigation controls)
 * - Render sidebar (manual list OR auto-generated from routes)
 * - Render breadcrumbs (layer-aware navigation chain)
 * - Render footer (layer-specific context)
 * - Build CSS stylesheet (shared tokens + layer overrides)
 */
abstract class LayerWrapperComposer
{
    protected string $layerType;
    protected ?View $view;
    /** @var array<string,mixed> */
    protected array $context;

    /**
     * @param string $layerType One of: 'operator', 'admin', 'display'
     * @param array<string,mixed> $context Layer-specific context
     * @param View|null $view Optional; not used internally but available for subclasses.
     */
    public function __construct(string $layerType, array $context, ?View $view = null)
    {
        $this->layerType = strtolower(trim($layerType));
        $this->view = $view;
        $this->context = $context;
    }

    /**
     * Translate helper (layer-aware).
     */
    protected function tr(string $key, string $fallback, array $params = []): string
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

    // ─────────────────────────────────────────────────────────────────────────
    // Render Methods (to be implemented or overridden by subclasses)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Render the wrapper header (logo, company name, search, profile controls).
     * @return string HTML
     */
    abstract public function renderHeader(): string;

    /**
     * Render the wrapper sidebar (manual OR auto-generated from routes).
     * @return string HTML
     */
    abstract public function renderSidebar(): string;

    /**
     * Render breadcrumbs (layer-aware navigation chain).
     * @return string HTML
     */
    abstract public function renderBreadcrumbs(): string;

    /**
     * Render the wrapper footer (layer-specific context/info).
     * @return string HTML
     */
    abstract public function renderFooter(): string;

    /**
     * Get context value safely.
     */
    protected function getContext(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Get current username (if applicable).
     */
    protected function getUsername(): string
    {
        return (string)$this->getContext('username', 'user');
    }

    /**
     * Get company name.
     */
    protected function getCompanyName(): string
    {
        return trim((string)$this->getContext('company_name', 'Company'));
    }

    /**
     * Get company logo URL.
     */
    protected function getCompanyLogo(): string
    {
        return trim((string)$this->getContext('company_logo', ''));
    }

    /**
     * Get company logo icon URL.
     */
    protected function getCompanyLogoIcon(): string
    {
        return trim((string)$this->getContext('company_logo_icon', ''));
    }

    /**
     * Get branch name.
     */
    protected function getBranchName(): string
    {
        return trim((string)$this->getContext('branch_name', ''));
    }

    /**
     * Get current search query (q/search_query).
     */
    protected function getSearchQuery(): string
    {
        $search = (string)$this->getContext('search_query', '');
        if ($search !== '') {
            return $search;
        }
        return (string)$this->getContext('q', '');
    }

    /**
     * Get layer type (operator, admin, display).
     */
    public function getLayerType(): string
    {
        return $this->layerType;
    }
}
