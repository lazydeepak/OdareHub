<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

/**
 * DisplayLayerWrapperComposer
 *
 * Wraps the /displays readonly kiosk layer with:
 * - Header: minimal (logo, company name, live clock only)
 * - Sidebar: none (readonly kiosk)
 * - Breadcrumbs: none (readonly kiosk)
 * - Footer: minimal (refresh indicator, readonly label)
 *
 * Sidebar composition rule: None (readonly display)
 */
final class DisplayLayerWrapperComposer extends LayerWrapperComposer
{
    public function __construct(array $context, ?\App\Core\View $view = null)
    {
        parent::__construct('display', $context, $view);
    }

    public function renderHeader(): string
    {
        $companyName = $this->getCompanyName();
        $companyLogo = $this->getCompanyLogo();
        $branchName = $this->getBranchName();

        ob_start();
        ?>
<header class="wrapper-header wrapper-header-display">
    <?php if ($companyLogo !== ''): ?>
    <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="Logo" class="wrapper-header-company-logo-display" loading="lazy" />
    <?php else: ?>
    <div class="wrapper-header-display-icon">🏭</div>
    <?php endif; ?>
    <div class="wrapper-header-company">
        <span class="wrapper-header-company-name"><?php echo htmlspecialchars($companyName); ?></span>
        <?php if ($branchName !== ''): ?>
        <span class="wrapper-header-badge"><?php echo htmlspecialchars($branchName); ?></span>
        <?php endif; ?>
    </div>
    <div class="wrapper-header-right">
        <span class="wrapper-header-clock" data-live-time="HH:MM:SS">—:—:—</span>
        <span class="wrapper-header-live-badge">🔄 <?php echo htmlspecialchars($this->tr('wrapper.display.header.live', 'LIVE')); ?></span>
    </div>
</header>
        <?php
        return ob_get_clean() ?: '';
    }

    public function renderSidebar(): string
    {
        // Display layer has no sidebar (readonly kiosk)
        return '';
    }

    public function renderBreadcrumbs(): string
    {
        // Display layer has no breadcrumbs (readonly kiosk)
        return '';
    }

    public function renderFooter(): string
    {
        $footerLabel = $this->tr('wrapper.display.footer', 'Floor Display — Readonly Kiosk');
        $readonlyLabel = $this->tr('wrapper.display.footer.readonly', 'No user interaction');
        $refreshLabel = $this->tr('wrapper.display.footer.refresh', 'Auto-refreshing');

        ob_start();
        ?>
<footer class="wrapper-footer wrapper-footer-display">
    <div class="wrapper-footer-info">
        <span class="wrapper-footer-info-label"><?php echo htmlspecialchars($footerLabel); ?></span>
        <span class="wrapper-footer-info-meta">🔒 <?php echo htmlspecialchars($readonlyLabel); ?> • 🔄 <?php echo htmlspecialchars($refreshLabel); ?></span>
    </div>
</footer>
        <?php
        return ob_get_clean() ?: '';
    }
}
