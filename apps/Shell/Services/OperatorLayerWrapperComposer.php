<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

require_once __DIR__ . '/OperatorSurfaceContributionRegistry.php';

/**
 * OperatorLayerWrapperComposer
 *
 * Wraps the /u operator workspace with:
 * - Header: company logo, company name, search bar, profile menu
 * - Sidebar: manually configured links (via OperatorLayerSidebarService)
 * - Breadcrumbs: current focus route context
 * - Footer: quick navigation links + info
 *
 * Sidebar composition rule: Manual construction from service
 */
final class OperatorLayerWrapperComposer extends LayerWrapperComposer
{
    /** @var array<string,array<string,mixed>> */
    private array $sidebarItems = [];

    public function __construct(array $context, ?OperatorLayerSidebarService $sidebarService = null, ?\App\Core\View $view = null)
    {
        parent::__construct('operator', $context, $view);
        // Sidebar items only loaded on demand via renderSidebar() to avoid
        // construction overhead when only renderBreadcrumbs()/renderFooter() are needed.
    }

    /**
     * Satisfies abstract contract from LayerWrapperComposer.
     * NOT invoked by OperatorSurfaceComposer — it uses buildHeaderMarkup() directly.
     * Reserved for future refactor where the surface composer delegates to the wrapper.
     */
    public function renderHeader(): string
    {
        $companyName = $this->getCompanyName();
        $companyLogo = $this->getCompanyLogo();
        $username = $this->getUsername();
        $searchQuery = $this->getSearchQuery();

        $menuLabel = $this->tr('wrapper.common.menu', 'Menu');
        $toggleMenuLabel = $this->tr('wrapper.common.toggle_menu', 'Toggle Menu');
        $logoAlt = $this->tr('wrapper.common.logo_alt', 'Logo');
        $searchPlaceholder = $this->tr('wrapper.operator.search_placeholder', 'Search workspace');
        $searchSubmitLabel = $this->tr('wrapper.common.search_submit', 'Search');
        $profileLabel = $this->tr('wrapper.common.profile', 'Profile');

        ob_start();
        ?>
<header class="wrapper-header">
    <button class="wrapper-header-logo" type="button" data-toggle-sidebar title="<?php echo htmlspecialchars($menuLabel); ?>" aria-label="<?php echo htmlspecialchars($toggleMenuLabel); ?>">☰</button>
    <?php if ($companyLogo !== ''): ?>
    <a href="/u/<?php echo rawurlencode($username); ?>/dashboard" class="wrapper-header-company">
        <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="<?php echo htmlspecialchars($logoAlt); ?>" class="wrapper-header-company-logo" loading="lazy" />
        <?php if ($companyName !== ''): ?>
        <span class="wrapper-header-company-name"><?php echo htmlspecialchars($companyName); ?></span>
        <?php endif; ?>
    </a>
    <?php else: ?>
    <a href="/u/<?php echo rawurlencode($username); ?>/dashboard" class="wrapper-header-company">
        <span class="wrapper-header-company-name"><?php echo htmlspecialchars($companyName); ?></span>
    </a>
    <?php endif; ?>
    <div class="wrapper-header-search">
        <form action="/u/<?php echo rawurlencode($username); ?>/dashboard" method="get" class="wrapper-header-search-form">
            <input class="input" type="search" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" class="header-search" placeholder="<?php echo htmlspecialchars($searchPlaceholder); ?>" aria-label="<?php echo htmlspecialchars($searchPlaceholder); ?>">
            <button type="submit" class="wrapper-header-avatar" aria-label="<?php echo htmlspecialchars($searchSubmitLabel); ?>" title="<?php echo htmlspecialchars($searchSubmitLabel); ?>">↵</button>
        </form>
    </div>
    <a href="/u/<?php echo rawurlencode($username); ?>/account" class="wrapper-header-avatar" title="<?php echo htmlspecialchars($profileLabel); ?>" aria-label="<?php echo htmlspecialchars($profileLabel); ?>">👤</a>
</header>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * Satisfies abstract contract from LayerWrapperComposer.
     * NOT invoked by OperatorSurfaceComposer — it uses buildSidebarMarkup() directly.
     * Reserved for future refactor where the surface composer delegates to the wrapper.
     */
    public function renderSidebar(): string
    {
        $username = $this->getUsername();
        $sidebarLabel = $this->tr('wrapper.operator.sidebar', 'Navigation');

        $service = new OperatorLayerSidebarService(['username' => $username]);
        $rawSidebar = $service->getContextualSidebar();
        $sidebar = $service->formatSidebarItems($rawSidebar, $username);
        $sections = (array)($sidebar['sections'] ?? []);

        ob_start();
        ?>
<aside class="wrapper-sidebar" role="navigation" aria-label="<?php echo htmlspecialchars($sidebarLabel); ?>">
    <?php foreach ($sections as $section): ?>
        <?php foreach ((array)($section['items'] ?? []) as $item): ?>
            <?php
            $href = (string)($item['route'] ?? '#');
            $label = (string)($item['label'] ?? 'Item');
            $icon = (string)($item['icon'] ?? '');
            $isActive = (bool)($item['active'] ?? false);
            $className = 'wrapper-sidebar-item' . ($isActive ? ' active' : '');
            ?>
        <a href="<?php echo htmlspecialchars($href); ?>" class="<?php echo $className; ?>" title="<?php echo htmlspecialchars($label); ?>">
            <?php if ($icon !== ''): ?><span class="wrapper-sidebar-item-icon"><?php echo htmlspecialchars($icon); ?></span><?php endif; ?>
            <span class="wrapper-sidebar-item-label"><?php echo htmlspecialchars($label); ?></span>
        </a>
        <?php endforeach; ?>
    <?php endforeach; ?>
</aside>
        <?php
        return ob_get_clean() ?: '';
    }

    public function renderBreadcrumbs(): string
    {
        $username = $this->getUsername();
        $currentFocus = (string)$this->getContext('current_focus', 'dashboard');
        $focusLabel = $this->getFocusLabel($currentFocus);
        $homeLabel = $this->tr('wrapper.operator.breadcrumb.home', 'Dashboard');
        $separator = '›';

        // workflow_links (pill tabs) intentionally not rendered here.

        $parentMap = OperatorSurfaceContributionRegistry::breadcrumbParents($this->context);

        $parent = null;
        $focusKey = strtolower(trim($currentFocus));
        if (isset($parentMap[$focusKey])) {
            $parent = $parentMap[$focusKey];
            $parent['route'] = str_replace('{user}', rawurlencode($username), $parent['route']);
        }

        ob_start();
        ?>
<nav class="wrapper-breadcrumbs" aria-label="Breadcrumb">
    <div class="wrapper-breadcrumbs-trail">
        <a href="/u/<?php echo rawurlencode($username); ?>/dashboard" class="wrapper-breadcrumbs-item"><?php echo htmlspecialchars($homeLabel); ?></a>
        <?php if ($parent !== null): ?>
        <span class="wrapper-breadcrumbs-separator"><?php echo htmlspecialchars($separator); ?></span>
        <a href="<?php echo htmlspecialchars($parent['route']); ?>" class="wrapper-breadcrumbs-item"><?php echo htmlspecialchars($parent['label']); ?></a>
        <?php endif; ?>
        <?php if ($currentFocus !== 'dashboard' && $focusLabel !== ''): ?>
        <span class="wrapper-breadcrumbs-separator"><?php echo htmlspecialchars($separator); ?></span>
        <span class="wrapper-breadcrumbs-item active"><?php echo htmlspecialchars($focusLabel); ?></span>
        <?php endif; ?>
    </div>
</nav>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * Satisfies abstract contract. No footer is rendered in the operator layer.
     */
    public function renderFooter(): string
    {
        return '';
    }

    /**
     * Render the shared row-click script that makes tr[data-href] and .parts-row-link rows navigable.
     * Called once before </body> by OperatorSurfaceComposer.
     */
    public function renderRowClickScript(): string
    {
        return <<<'HTML'
<script>
(function () {
    /** Navigate when clicking anywhere in a row except interactive elements. */
    function wireRows(selector, hrefFn) {
        document.querySelectorAll(selector).forEach(function (tr) {
            tr.addEventListener('click', function (e) {
                if (e.target.closest('a, button, input, select, textarea, form, label')) return;
                var href = hrefFn(tr);
                if (href) window.location.href = href;
            });
            tr.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    var href = hrefFn(tr);
                    if (href) window.location.href = href;
                }
            });
        });
    }
    wireRows('tr[data-href]', function (tr) { return tr.dataset.href || ''; });
    wireRows('.parts-row-link[data-part-detail-url]', function (tr) { return tr.dataset.partDetailUrl || ''; });
}());
</script>
HTML;
    }

    /**
     * Render mobile bottom navigation bar with centre quick-action button.
     *
     * Layout: [Home] [Work Entry] [Act] [Scan] [Account]
     *
     * The centre button opens a context-aware action tray (.mobile-action-panel)
     * that floats above the bar with 2-4 quick actions relevant to the current focus.
     * This is the ERP floor pattern: operators primarily need to ACT, not just navigate.
     *
     * Visible only at ≤820px (controlled by CSS).
     */
    public function renderBottomNav(): string
    {
        $username = $this->getUsername();
        $u = rawurlencode($username);
        $currentFocus = strtolower(trim((string)$this->getContext('current_focus', 'dashboard')));

        $iconSvg = static function (string $name): string {
            $icons = [
                'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5"></path><path d="M5.5 10.5V20h13V10.5"></path></svg>',
                'plan' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3-3a1 1 0 0 0-1.4-1.4l-2.3 2.3-.9-.9a1 1 0 0 0-1.4 0z"></path><path d="M5 19l7-7"></path><path d="M14.5 5.5a4.95 4.95 0 1 1 0 7l-9 9a1.5 1.5 0 0 1-2-2l9-9a4.95 4.95 0 0 1 2-5z"></path></svg>',
                'dispatch' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h12v8H3z"></path><path d="M15 11h3l3 3v2h-6z"></path><circle cx="7" cy="18" r="1.6"></circle><circle cx="17" cy="18" r="1.6"></circle></svg>',
                'account' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"></circle><path d="M5 20a7 7 0 0 1 14 0"></path></svg>',
                'action' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 10-13h-7l0-7z"></path></svg>',
                'work_entry' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10l3 3v13H4V4z"></path><path d="M14 4v4h4"></path><path d="M8 12h8"></path><path d="M8 16h6"></path></svg>',
                'orders' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 4h12v16H6z"></path><path d="M9 8h6"></path><path d="M9 12h6"></path><path d="M9 16h4"></path></svg>',
                'demand' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19h16"></path><path d="M7 16v-4"></path><path d="M12 16V7"></path><path d="M17 16v-7"></path></svg>',
                'machines' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="7" width="16" height="10" rx="2"></rect><path d="M9 7V4"></path><path d="M15 7V4"></path><path d="M8 12h8"></path></svg>',
                'critical' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 3 20h18L12 3z"></path><path d="M12 9v5"></path><path d="M12 17h.01"></path></svg>',
                'processing' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 1 1-4 0v-.2a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 1 1 0-4h.2a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2H9a1 1 0 0 0 .6-.9V4a2 2 0 1 1 4 0v.2a1 1 0 0 0 .6.9h.1a1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1v.1a1 1 0 0 0 .9.6H20a2 2 0 1 1 0 4h-.2a1 1 0 0 0-.9.6z"></path></svg>',
                'qc' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7 9 18l-5-5"></path></svg>',
                'scan' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V4h3"></path><path d="M3 17v3h3"></path><path d="M18 4h3v3"></path><path d="M18 20h3v-3"></path><line x1="3" y1="12" x2="21" y2="12"></line></svg>',
            ];
            return $icons[$name] ?? $icons['action'];
        };

        // Left nav tabs stay stable across operator workspaces.
        $leftTabs = [
            [
                'label'   => $this->tr('operator.bottom_nav.home', 'Home'),
                'icon'    => 'home',
                'href'    => "/u/{$u}/dashboard",
                'focuses' => ['dashboard', 'critical', 'recent', ''],
            ],
            [
                'label'   => $this->tr('operator.bottom_nav.work_entry', 'Work Entry'),
                'icon'    => 'work_entry',
                'href'    => "/u/{$u}/work-entry",
                'focuses' => ['work-entry'],
            ],
        ];

        // Right nav tabs stay stable across operator workspaces.
        $rightTabs = [
            [
                'label'   => $this->tr('operator.bottom_nav.scan', 'Scan'),
                'icon'    => 'scan',
                'href'    => null, // triggers JS scanner, no page nav
                'focuses' => [],
            ],
            [
                'label'   => $this->tr('operator.bottom_nav.account', 'Account'),
                'icon'    => 'account',
                'href'    => "/u/{$u}/account",
                'focuses' => ['account', 'notifications', 'messages'],
            ],
        ];

        $workEntryActions = [
            ['icon' => 'work_entry', 'label' => $this->tr('operator.bottom_nav.action.work_entry', 'Log Work Entry'), 'href' => "/u/{$u}/work-entry?action=update"],
            ['icon' => 'account',    'label' => $this->tr('operator.bottom_nav.action.account',    'Account'),        'href' => "/u/{$u}/account"],
        ];
        $actionSets = array_replace(
            [
                'work-entry' => $workEntryActions,
                'default' => $workEntryActions,
            ],
            OperatorSurfaceContributionRegistry::bottomActionSets($this->context)
        );

        // Resolve alias sets
        $resolveActions = function (string $focus) use ($actionSets): array {
            $entry = $actionSets[$focus] ?? $actionSets['default'];
            if (is_string($entry)) {
                $entry = $actionSets[$entry] ?? $actionSets['default'];
            }
            return is_array($entry) ? $entry : $actionSets['default'];
        };

        $quickActions = $resolveActions($currentFocus);
        foreach ($quickActions as $index => $action) {
            if (!is_array($action)) {
                unset($quickActions[$index]);
                continue;
            }
            $action['href'] = str_replace('{user}', $u, (string)($action['href'] ?? '#'));
            $quickActions[$index] = $action;
        }

        $actionLabel = $this->tr('operator.bottom_nav.action_button', 'Act');
        $actionPanelTitle = $this->tr('operator.bottom_nav.action_panel_title', 'Quick Actions');
        $navLabel = $this->tr('operator.bottom_nav.label', 'Main navigation');

        ob_start();
        ?>
<!-- Mobile action tray backdrop -->
<div class="action-panel-backdrop" id="actionPanelBackdrop"></div>
<!-- Mobile action tray (opens above the bottom bar from center action button) -->
<div class="mobile-action-panel" id="mobileActionPanel" role="dialog" aria-modal="true" aria-label="<?php echo htmlspecialchars($actionPanelTitle); ?>">
    <div class="mobile-action-panel-title"><?php echo htmlspecialchars($actionPanelTitle); ?></div>
    <div class="mobile-action-list">
        <?php foreach ($quickActions as $action): ?>
        <a href="<?php echo htmlspecialchars((string)($action['href'] ?? '#')); ?>" class="mobile-action-item">
            <span aria-hidden="true"><?php echo $iconSvg((string)($action['icon'] ?? 'action')); ?></span>
            <span><?php echo htmlspecialchars((string)($action['label'] ?? '')); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<nav class="u-bottom-nav" aria-label="<?php echo htmlspecialchars($navLabel); ?>">
    <?php foreach ($leftTabs as $tab): ?>
        <?php $isActive = in_array($currentFocus, $tab['focuses'], true); ?>
        <a href="<?php echo htmlspecialchars($tab['href']); ?>" class="u-bottom-nav-item<?php echo $isActive ? ' is-active' : ''; ?>" <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
            <span class="u-bottom-nav-icon" aria-hidden="true"><?php echo $iconSvg((string)$tab['icon']); ?></span>
            <span class="u-bottom-nav-label"><?php echo htmlspecialchars($tab['label']); ?></span>
        </a>
    <?php endforeach; ?>
    <!-- Centre action button -->
    <button
        class="u-bottom-nav-item u-bottom-nav-action"
        type="button"
        id="mobileActionBtn"
        aria-haspopup="dialog"
        aria-expanded="false"
        aria-controls="mobileActionPanel"
    >
        <span class="u-bottom-nav-icon u-bottom-nav-action-icon" aria-hidden="true"><?php echo $iconSvg('action'); ?></span>
        <span class="u-bottom-nav-label"><?php echo htmlspecialchars($actionLabel); ?></span>
    </button>
    <?php foreach ($rightTabs as $tab): ?>
        <?php $isActive = in_array($currentFocus, $tab['focuses'], true); ?>
        <?php if ($tab['href'] === null): ?>
        <button type="button" class="u-bottom-nav-item u-bottom-nav-scan-btn<?php echo $isActive ? ' is-active' : ''; ?>" data-action="scan">
            <span class="u-bottom-nav-icon" aria-hidden="true"><?php echo $iconSvg((string)$tab['icon']); ?></span>
            <span class="u-bottom-nav-label"><?php echo htmlspecialchars($tab['label']); ?></span>
        </button>
        <?php else: ?>
        <a href="<?php echo htmlspecialchars($tab['href']); ?>" class="u-bottom-nav-item<?php echo $isActive ? ' is-active' : ''; ?>" <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
            <span class="u-bottom-nav-icon" aria-hidden="true"><?php echo $iconSvg((string)$tab['icon']); ?></span>
            <span class="u-bottom-nav-label"><?php echo htmlspecialchars($tab['label']); ?></span>
        </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<script>
(function () {
    var btn = document.getElementById('mobileActionBtn');
    var panel = document.getElementById('mobileActionPanel');
    var backdrop = document.getElementById('actionPanelBackdrop');
    if (!btn || !panel) return;

    var mobileActionSheetAdapterFactory = window.OdareHubOS
        && window.OdareHubOS.ShellOverlayAdapters
        && window.OdareHubOS.ShellOverlayAdapters.createOperatorMobileActionSheetAdapter;
    var mobileActionSheetAdapter = typeof mobileActionSheetAdapterFactory === 'function'
        ? mobileActionSheetAdapterFactory({
            triggerId: 'mobileActionBtn',
            surfaceId: 'mobileActionPanel',
            backdropId: 'actionPanelBackdrop',
            scrollTargetSelector: '.main-content',
            setLegacyOpen: setPanelOpen
        })
        : null;
    window.OdareHubOS = window.OdareHubOS || {};
    window.OdareHubOS.ShellOverlayAdapters = window.OdareHubOS.ShellOverlayAdapters || {};
    window.OdareHubOS.ShellOverlayAdapters.operatorMobileActionSheetAdapter = mobileActionSheetAdapter;

    function setPanelOpen(open) {
        panel.classList.toggle('open', open);
        if (backdrop) backdrop.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function closePanel() {
        if (mobileActionSheetAdapter && typeof mobileActionSheetAdapter.syncState === 'function') {
            mobileActionSheetAdapter.syncState(false, 'close');
            return;
        }
        setPanelOpen(false);
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        // Close avatar panel and hamburger menu when opening mobile action
        if (typeof toggleAvatarPanel === 'function') {
            toggleAvatarPanel(false);
        }
        if (typeof closeHamburgerMenu === 'function') {
            closeHamburgerMenu();
        }
        var isOpen = !panel.classList.contains('open');
        if (mobileActionSheetAdapter && typeof mobileActionSheetAdapter.syncState === 'function') {
            mobileActionSheetAdapter.syncState(isOpen, isOpen ? 'trigger-open' : 'trigger-close');
        } else {
            setPanelOpen(isOpen);
        }
    });

    // Close when navigating away via an action link
    panel.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', closePanel);
    });

}());

// Scan button — delegates to existing launchCameraScan / headerOriginalScanButton
(function () {
    document.querySelectorAll('[data-action="scan"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            // Prefer the existing scan button in the topbar (carries all event wiring)
            var existing = document.getElementById('headerOriginalScanButton') || document.getElementById('topbarScanBtn');
            if (existing) { existing.click(); return; }
            // Fallback: call window.launchCameraScan directly if exposed
            if (typeof window.launchCameraScan === 'function') { window.launchCameraScan(); }
        });
    });
}());


// Scroll-aware topbar + nav hide/show — mobile only
(function () {
    if (!window.matchMedia('(max-width: 820px)').matches) return;
    var topbar   = document.querySelector('.topbar');
    var nav      = document.querySelector('.u-bottom-nav');
    // Scroll may occur on .main-content or window — listen to both
    var scroller = document.querySelector('.main-content') || window;
    var lastY    = scroller === window ? window.scrollY : scroller.scrollTop;
    var downAccum = 0;
    var HIDE_AFTER = 60;

    function getY() { return scroller === window ? window.scrollY : scroller.scrollTop; }

    function onScroll() {
        var y     = getY();
        var delta = y - lastY;
        lastY = y;
        if (y < 8) {
            topbar && topbar.classList.remove('scroll-hidden');
            nav    && nav.classList.remove('scroll-hidden');
            downAccum = 0;
            return;
        }
        if (delta > 0) {
            downAccum += delta;
            if (downAccum > HIDE_AFTER) {
                topbar && topbar.classList.add('scroll-hidden');
                nav    && nav.classList.add('scroll-hidden');
            }
        } else {
            downAccum = 0;
            topbar && topbar.classList.remove('scroll-hidden');
            nav    && nav.classList.remove('scroll-hidden');
        }
    }

    scroller.addEventListener('scroll', onScroll, { passive: true });
    // Also listen on window in case layout changes
    if (scroller !== window) {
        window.addEventListener('scroll', onScroll, { passive: true });
    }
}());
</script>
        <?php
        return ob_get_clean() ?: '';
    }

    /**
     * Get human-readable label for focus view.
     */
    private function getFocusLabel(string $focus): string
    {
        $labels = [
            'dashboard'        => $this->tr('wrapper.operator.focus.dashboard', 'Dashboard'),
            'account'          => $this->tr('wrapper.operator.focus.account', 'My Account'),
            'notifications'    => $this->tr('wrapper.operator.focus.notifications', 'Notifications'),
            'messages'         => $this->tr('wrapper.operator.focus.messages', 'Messages'),
            'work-entry'       => $this->tr('wrapper.operator.focus.work_entry', 'Work Entry'),
            'critical'         => $this->tr('wrapper.operator.focus.critical', 'Critical Items'),
            'recent'           => $this->tr('wrapper.operator.focus.recent', 'Recent'),
        ];
        $labels = array_replace($labels, OperatorSurfaceContributionRegistry::focusLabels($this->context));

        return $labels[strtolower(trim($focus))] ?? '';
    }
}
