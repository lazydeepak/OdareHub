<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

final class OperatorHeaderComposer
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $i18n
     * @param array<int,array<string,string>> $operatorLanguageOptions
     * @param array<int,array<string,string>> $operatorThemeChoices
     */
    public static function render(
        array $data,
        array $i18n,
        array $operatorLanguageOptions,
        string $operatorLanguage,
        string $operatorCurrency,
        array $operatorThemeChoices
    ): string {
        unset($operatorLanguageOptions, $operatorLanguage, $operatorCurrency, $operatorThemeChoices);

        ob_start();
        ?>
    <!-- Header -->
    <header class="topbar">
        <div class="topbar-inner">
            <div class="topbar-brand">
                <button type="button" class="btn icon-btn sidebar-toggle header-hamburger" id="hamburgerToggle" aria-label="<?php echo htmlspecialchars($i18n['menu']); ?>">☰</button>
                <?php
                    $companyLogoSvgInline = trim((string)($data['company_logo_svg_inline'] ?? ''));
                    $companyLogoSvgTheme = trim((string)($data['company_logo_svg_theme'] ?? ''));
                    $companyLogo = trim((string)($data['company_logo'] ?? ''));
                    $renderedCompanyLogoSvg = $companyLogoSvgInline;
                    if ($renderedCompanyLogoSvg !== '' && $companyLogoSvgTheme !== '' && preg_match('/\bclass="ipm-logo\b/', $renderedCompanyLogoSvg)) {
                        $safeTheme = preg_replace('/[^a-z0-9\-]/', '', strtolower($companyLogoSvgTheme));
                        if ($safeTheme !== '') {
                            $renderedCompanyLogoSvg = preg_replace('/\bclass="ipm-logo"/', 'class="ipm-logo ' . $safeTheme . '"', $renderedCompanyLogoSvg, 1);
                        }
                    }
                    if ($renderedCompanyLogoSvg !== '' && preg_match('/<svg\b/i', $renderedCompanyLogoSvg)) {
                        if (preg_match('/<svg\b[^>]*\bclass="/i', $renderedCompanyLogoSvg)) {
                            $renderedCompanyLogoSvg = preg_replace('/<svg\b([^>]*?)\bclass="([^"]*)"/i', '<svg$1class="$2 header-company-logo-svg"', $renderedCompanyLogoSvg, 1);
                        } else {
                            $renderedCompanyLogoSvg = preg_replace('/<svg\b/i', '<svg class="header-company-logo-svg"', $renderedCompanyLogoSvg, 1);
                        }
                    }
                    $companyFallbackText = trim((string)($data['company_fallback_text'] ?? ''));
                    $companyFallbackTextCompact = trim((string)($data['company_fallback_text_compact'] ?? ''));
                    $hasLogo = ($renderedCompanyLogoSvg !== '' || $companyLogo !== '');
                ?>
                <a href="<?php echo htmlspecialchars((string)($data['home_url'] ?? '/')); ?>" class="brand-link brand-link--logo header-company" aria-label="<?php echo htmlspecialchars($i18n['company_home']); ?>">
                    <?php if ($renderedCompanyLogoSvg !== ''): ?>
                        <?php echo $renderedCompanyLogoSvg; ?>
                    <?php elseif ($companyLogo !== ''): ?>
                        <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="<?php echo htmlspecialchars((string)($data['company_name'] ?? '')); ?>" class="header-company-logo">
                    <?php else: ?>
                        <span class="header-company-logo-fallback header-company-logo-fallback--desktop"><?php echo htmlspecialchars($companyFallbackText ?: 'OdareHub'); ?></span>
                        <span class="header-company-logo-fallback header-company-logo-fallback--mobile"><?php echo htmlspecialchars($companyFallbackTextCompact ?: 'S'); ?></span>
                    <?php endif; ?>
                    <?php if ($hasLogo): ?>
                    <span class="header-company-name"><strong><?php echo htmlspecialchars((string)($data['company_name'] ?? 'IPM Local')); ?></strong></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="topbar-search header-search-wrap" role="search" data-search-state="idle" aria-label="<?php echo htmlspecialchars($i18n['search']); ?>">
                <div class="topbar-search-input-wrap">
                    <span class="topbar-search-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                    </span>
                    <input type="search" class="topbar-search-input" id="headerRouteSearch" placeholder="<?php echo htmlspecialchars($i18n['search']); ?>" aria-label="<?php echo htmlspecialchars($i18n['search']); ?>" autocomplete="off" spellcheck="false">
                    <button type="button" class="topbar-search-scan-btn" id="headerOriginalScanButton" aria-label="<?php echo htmlspecialchars($i18n['scan']); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><path d="M7 12h10"></path></svg>
                    </button>
                </div>
                <div class="operator-search-results" id="operatorSearchResults" hidden></div>
            </div>

            <div class="topbar-spacer" aria-hidden="true"></div>

            <div class="topbar-actions topbar-right header-right">
                <a href="<?php echo htmlspecialchars((string)($data['notifications_url'] ?? ('/u/' . rawurlencode((string)($data['username'] ?? '')) . '/notifications'))); ?>" class="btn icon-btn header-status-btn header-notification-btn notif-bell-btn" aria-label="<?php echo htmlspecialchars($i18n['notifications']); ?>" title="<?php echo htmlspecialchars($i18n['notifications']); ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1a5 5 0 0 0-5 5v2.586l-.707.707A1 1 0 0 0 3 11h10a1 1 0 0 0 .707-1.707L13 8.586V6A5 5 0 0 0 8 1zM6.5 13a1.5 1.5 0 0 0 3 0H6.5z"></path></svg>
                    <?php if ((int)($data['notifications_count'] ?? 0) > 0): ?>
                        <span class="notif-bell-badge"><?php echo (int)($data['notifications_count'] ?? 0); ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo htmlspecialchars((string)($data['messages_url'] ?? ('/u/' . rawurlencode((string)($data['username'] ?? '')) . '/messages'))); ?>" class="btn icon-btn header-status-btn header-message-btn notif-bell-btn" aria-label="<?php echo htmlspecialchars($i18n['message']); ?>" title="<?php echo htmlspecialchars($i18n['message']); ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="12" height="10" rx="2"></rect><path d="M3.5 5L8 8.3L12.5 5"></path></svg>
                    <?php if ((int)($data['messages_count'] ?? 0) > 0): ?>
                        <span class="notif-bell-badge"><?php echo (int)($data['messages_count'] ?? 0); ?></span>
                    <?php endif; ?>
                </a>
                <button type="button" class="btn icon-btn header-avatar" id="headerAvatarButton" aria-label="<?php echo htmlspecialchars($i18n['profile']); ?>" title="<?php echo htmlspecialchars($i18n['profile']); ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1.75a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 0 0 0-6.5zM3.75 5a4.25 4.25 0 1 1 8.5 0 4.25 4.25 0 0 1-8.5 0z"></path><path d="M8 9.25c-2.59 0-4.74 1.88-5.16 4.35a.5.5 0 0 0 .49.6h9.34a.5.5 0 0 0 .49-.6c-.42-2.47-2.57-4.35-5.16-4.35z"></path></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Hamburger Menu Backdrop -->
    <div class="hamburger-backdrop" id="hamburgerBackdrop"></div>

    <!-- Hamburger Menu (uses same contextual sections as desktop sidebar) -->
    <div class="hamburger-menu" id="hamburgerMenu">
        <div class="menu-section">
            <div class="menu-section-title"><?php echo htmlspecialchars((string)($data['contextual_sidebar']['app_label'] ?? $i18n['workspace'])); ?></div>
        </div>
        <?php foreach ($data['contextual_sidebar']['sections'] ?? [] as $section): ?>
            <div class="menu-section">
                <div class="menu-section-title"><?php echo htmlspecialchars((string)($section['title'] ?? '')); ?></div>
                <?php foreach ($section['items'] ?? [] as $item): ?>
                    <?php $mItemRoute = (string)($item['route'] ?? '#'); $mIsActive = (($data['sidebar_active_route'] ?? '') !== '' && $mItemRoute === ($data['sidebar_active_route'] ?? '')); ?>
                    <a href="<?php echo htmlspecialchars($mItemRoute); ?>" class="menu-item<?php if ($mIsActive): ?> is-active<?php endif; ?>"<?php if ($mIsActive): ?> aria-current="page"<?php endif; ?>>
                        <span class="menu-item-icon"><?php echo htmlspecialchars((string)($item['icon'] ?? '')); ?></span>
                        <span class="menu-item-label"><?php echo htmlspecialchars((string)($item['label'] ?? '')); ?></span>
                        <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                            <span class="nav-badge"><?php echo (int)$item['badge']; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
        <?php
        return ob_get_clean() ?: '';
    }
}
