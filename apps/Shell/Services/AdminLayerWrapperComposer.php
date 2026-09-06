<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use Apps\Studio\Services\GuiStudioService;

/**
 * AdminLayerWrapperComposer
 *
 * Wraps the /admin governance workspace with:
 * - Header: system status, company name, profile menu
 * - Sidebar: auto-generated from manifest.json routes (governance-focused)
 * - Breadcrumbs: admin home + current section
 * - Footer: quick navigation links (platform admin tools) + system info
 *
 * Sidebar composition rule: Auto-generated from app manifest routes
 */
final class AdminLayerWrapperComposer extends LayerWrapperComposer
{
    /** @var array<string,array<string,mixed>> */
    private array $sidebarItems = [];
    /** @var array<string,array<string,mixed>> */
    private array $quickLinks = [];

    public function __construct(array $context, array $quickLinks = [], ?\App\Core\View $view = null)
    {
        parent::__construct('admin', $context, $view);
        $this->quickLinks = $quickLinks;
        $this->buildAutoSidebar();
    }

    private function buildAutoSidebar(): void
    {
        // Auto-generate sidebar from manifest routes and apps
        $this->sidebarItems = [
            [
                'label' => $this->tr('wrapper.admin.sidebar.dashboard', 'Dashboard'),
                'url' => '/admin/' . rawurlencode($this->getUsername()),
                'icon' => '📊',
                'active' => $this->isCurrentPath('/admin/'),
            ],
            [
                'label' => $this->tr('wrapper.admin.sidebar.users', 'Users & Roles'),
                'url' => '/ops/user-control',
                'icon' => '👥',
                'active' => $this->isCurrentPath('/ops/user-control'),
            ],
            [
                'label' => $this->tr('wrapper.admin.sidebar.access', 'Access Control'),
                'url' => '/ops/access-control',
                'icon' => '🔐',
                'active' => $this->isCurrentPath('/ops/access-control'),
            ],
            [
                'label' => $this->tr('wrapper.admin.sidebar.display', 'Display Manager'),
                'url' => '/ops/display-manager',
                'icon' => '📺',
                'active' => $this->isCurrentPath('/ops/display-manager'),
            ],
            [
                'label' => $this->tr('wrapper.admin.sidebar.operator_tasks', 'Operator Tasks'),
                'url' => '/ops/operator-tasks',
                'icon' => '✅',
                'active' => $this->isCurrentPath('/ops/operator-tasks'),
            ],
            [
                'label' => $this->tr('wrapper.admin.sidebar.audit', 'Audit Log'),
                'url' => '/ops/audit',
                'icon' => '📋',
                'active' => $this->isCurrentPath('/ops/audit'),
            ],
            [
                'label' => $this->tr('wrapper.admin.sidebar.applications', 'Applications'),
                'url' => '/apps',
                'icon' => '📦',
                'active' => $this->isCurrentPath('/apps'),
            ],
        ];

        $studioEnabled = $this->isStudioSystemAppEnabled();

        if ($studioEnabled) {
            $studioServicePath = APP_ROOT . '/apps/Studio/Services/GuiStudioService.php';
            if (!class_exists(GuiStudioService::class) && is_file($studioServicePath)) {
                require_once $studioServicePath;
            }
        }

        if ($studioEnabled && class_exists(GuiStudioService::class)) {
            foreach (GuiStudioService::adminSidebarStudioEntries() as $entry) {
                $label = trim((string)($entry['label'] ?? ''));
                $url = trim((string)($entry['url'] ?? ''));
                if ($label === '' || $url === '') {
                    continue;
                }
                $this->sidebarItems[] = [
                    'label' => $label,
                    'url' => $url,
                    'icon' => '🧩',
                    'active' => $this->isCurrentPath($url),
                ];
            }
        }
    }

    private function isStudioSystemAppEnabled(): bool
    {
        try {
            $row = \App\Core\DB::fetchOne('SELECT status FROM core_apps WHERE app_key=? LIMIT 1', ['studio']);
            return is_array($row) && (string)($row['status'] ?? '') === 'enabled';
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isCurrentPath(string $path): bool
    {
        $currentPath = (string)$this->getContext('current_path', '');
        return $currentPath !== '' && str_starts_with($currentPath, $path);
    }

    public function renderHeader(): string
    {
        $companyName = $this->getCompanyName();
        $username = $this->getUsername();
        $searchQuery = $this->getSearchQuery();

        $menuLabel = $this->tr('wrapper.common.menu', 'Menu');
        $toggleMenuLabel = $this->tr('wrapper.common.toggle_menu', 'Toggle Menu');
        $profileLabel = $this->tr('wrapper.common.profile', 'Profile');
        $adminBadgeLabel = $this->tr('wrapper.admin.header.badge', 'Admin');
        $searchPlaceholder = $this->tr('wrapper.admin.search_placeholder', 'Search admin tools');
        $searchSubmitLabel = $this->tr('wrapper.common.search_submit', 'Search');

        ob_start();
        ?>
<header class="wrapper-header">
    <button class="wrapper-header-logo" type="button" data-toggle-sidebar title="<?php echo htmlspecialchars($menuLabel); ?>" aria-label="<?php echo htmlspecialchars($toggleMenuLabel); ?>">☰</button>
    <div class="wrapper-header-company">
        <span class="wrapper-header-company-name"><?php echo htmlspecialchars($companyName); ?></span>
        <span class="wrapper-header-badge"><?php echo htmlspecialchars($adminBadgeLabel); ?></span>
    </div>
    <div class="wrapper-header-search">
        <form action="/admin/<?php echo rawurlencode($username); ?>" method="get" class="wrapper-header-search-form">
            <input class="input" type="search" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" class="header-search" placeholder="<?php echo htmlspecialchars($searchPlaceholder); ?>" aria-label="<?php echo htmlspecialchars($searchPlaceholder); ?>">
            <button type="submit" class="wrapper-header-avatar" aria-label="<?php echo htmlspecialchars($searchSubmitLabel); ?>" title="<?php echo htmlspecialchars($searchSubmitLabel); ?>">↵</button>
        </form>
    </div>
    <a href="/u/<?php echo rawurlencode($username); ?>/account" class="wrapper-header-avatar" title="<?php echo htmlspecialchars($profileLabel); ?>" aria-label="<?php echo htmlspecialchars($profileLabel); ?>">👤</a>
</header>
        <?php
        return ob_get_clean() ?: '';
    }

    public function renderSidebar(): string
    {
        return $this->renderSidebarWithMode('vertical');
    }

    /**
     * Render sidebar as a horizontal governance pill-nav strip.
     * Designed to be embedded inline in the admin content area.
     */
    public function renderSidebarHorizontal(): string
    {
        return $this->renderSidebarWithMode('horizontal');
    }

    private function renderSidebarWithMode(string $mode): string
    {
        $sidebarLabel = $this->tr('wrapper.admin.sidebar', 'Governance Navigation');
        $cssClass = $mode === 'horizontal'
            ? 'wrapper-sidebar wrapper-sidebar-horizontal'
            : 'wrapper-sidebar';

        ob_start();
        ?>
<aside class="<?php echo $cssClass; ?>" role="navigation" aria-label="<?php echo htmlspecialchars($sidebarLabel); ?>">
    <?php foreach ($this->sidebarItems as $item): ?>
        <?php
        $href = (string)($item['url'] ?? '#');
        $label = (string)($item['label'] ?? 'Item');
        $icon = (string)($item['icon'] ?? '');
        $isActive = (bool)($item['active'] ?? false);
        $className = 'wrapper-sidebar-item' . ($isActive ? ' active' : '');
        ?>
    <a href="<?php echo htmlspecialchars($href); ?>" class="<?php echo $className; ?>" title="<?php echo htmlspecialchars($label); ?>">
        <?php if ($icon !== ''): ?>
        <span class="wrapper-sidebar-item-icon"><?php echo htmlspecialchars($icon); ?></span>
        <?php endif; ?>
        <span class="wrapper-sidebar-item-label"><?php echo htmlspecialchars($label); ?></span>
    </a>
    <?php endforeach; ?>
</aside>
        <?php
        return ob_get_clean() ?: '';
    }

    public function renderBreadcrumbs(): string
    {
        $username = $this->getUsername();
        $currentSection = (string)$this->getContext('current_section', 'dashboard');
        $sectionLabel = $this->getSectionLabel($currentSection);

        $homeLabel = $this->tr('wrapper.admin.breadcrumb.home', 'Admin Home');
        $separator = '›';

        ob_start();
        ?>
<nav class="wrapper-breadcrumbs" aria-label="Breadcrumb">
    <a href="/admin/<?php echo rawurlencode($username); ?>" class="wrapper-breadcrumbs-item"><?php echo htmlspecialchars($homeLabel); ?></a>
    <?php if ($currentSection !== 'dashboard' && $sectionLabel !== ''): ?>
    <span class="wrapper-breadcrumbs-separator"><?php echo htmlspecialchars($separator); ?></span>
    <span class="wrapper-breadcrumbs-item active"><?php echo htmlspecialchars($sectionLabel); ?></span>
    <?php endif; ?>
</nav>
        <?php
        return ob_get_clean() ?: '';
    }

    public function renderFooter(): string
    {
        if ($this->quickLinks === []) {
            return '';
        }

        ob_start();
        ?>
<footer class="wrapper-footer">
    <div class="wrapper-footer-links">
        <?php foreach ($this->quickLinks as $link): ?>
            <?php
            $href = (string)($link['url'] ?? '#');
            $label = (string)($link['label'] ?? '');
            $icon = (string)($link['icon'] ?? '');
            if ($label === '') continue;
            ?>
        <a href="<?php echo htmlspecialchars($href); ?>" class="wrapper-footer-link" title="<?php echo htmlspecialchars($label); ?>">
            <?php if ($icon !== ''): ?><?php echo htmlspecialchars($icon); ?> <?php endif; ?><?php echo htmlspecialchars($label); ?>
        </a>
        <?php endforeach; ?>
    </div>
</footer>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * Get human-readable label for section.
     */
    private function getSectionLabel(string $section): string
    {
        $labels = [
            'dashboard' => '',
            'users' => $this->tr('wrapper.admin.section.users', 'Users & Roles'),
            'access' => $this->tr('wrapper.admin.section.access', 'Access Control'),
            'display' => $this->tr('wrapper.admin.section.display', 'Display Manager'),
            'audit' => $this->tr('wrapper.admin.section.audit', 'Audit Log'),
            'applications' => $this->tr('wrapper.admin.section.applications', 'Applications'),
        ];

        return $labels[strtolower(trim($section))] ?? '';
    }
}
