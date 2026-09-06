<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

final class OperatorAvatarMenuComposer
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $i18n
     * @param array<int|string,string> $operatorLanguageOptions
     * @param array<int|string,string> $operatorThemeChoices
     */
    public static function render(
        array $data,
        array $i18n,
        array $operatorLanguageOptions,
        string $operatorLanguage,
        string $operatorCurrency,
        array $operatorThemeChoices
    ): string {
        ob_start();
        ?>
    <!-- Shell overlay — fixed viewport container for portal-rendered popups/panels (backdrop + panel as siblings, outside any parent stacking context) -->
    <div class="shell-overlay" id="shellOverlay">
        <div class="avatar-backdrop" id="avatarBackdrop"></div>
        <div class="header-avatar-panel" id="headerAvatarPanel">

            <!-- Identity card -->
            <div class="avatar-identity">
                <div class="avatar-initials" aria-hidden="true"><?php
                    $un = (string)($data['username'] ?? '');
                    echo htmlspecialchars(mb_strtoupper(mb_substr($un, 0, 2)));
                ?></div>
                <div class="avatar-identity-text">
                    <div class="avatar-identity-name"><?php echo htmlspecialchars((string)($data['username'] ?? '')); ?></div>
                    <div class="avatar-identity-email"><?php echo htmlspecialchars((string)($data['user_email'] ?? '')); ?></div>
                    <div class="avatar-identity-company"><?php
                        $co = htmlspecialchars((string)($data['company_name'] ?? 'IPM Local'));
                        $br = trim((string)($data['branch_name'] ?? ''));
                        echo $co . ($br !== '' ? ' &middot; ' . htmlspecialchars($br) : '');
                    ?></div>
                </div>
            </div>

            <!-- Quick links -->
            <div class="avatar-section">
                <a href="<?php echo htmlspecialchars((string)($data['notifications_url'] ?? '/u/' . rawurlencode((string)($data['username'] ?? '')) . '/notifications')); ?>" class="avatar-quicklink">
                    <span class="avatar-quicklink-icon">
                        <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1a5 5 0 0 0-5 5v2.17l-.71 1.42A1 1 0 0 0 3.18 11H6a2 2 0 0 0 4 0h2.82a1 1 0 0 0 .89-1.45L13 8.17V6a5 5 0 0 0-5-5z"/></svg>
                    </span>
                    <span class="avatar-quicklink-label"><?php echo htmlspecialchars($i18n['notifications']); ?></span>
                    <?php $nc = (int)($data['notifications_count'] ?? 0); if ($nc > 0): ?>
                        <span class="avatar-quicklink-badge"><?php echo $nc; ?></span>
                    <?php endif; ?>
                    <span class="avatar-quicklink-arrow">&#8250;</span>
                </a>
                <a href="<?php echo htmlspecialchars((string)($data['messages_url'] ?? '/u/' . rawurlencode((string)($data['username'] ?? '')) . '/messages')); ?>" class="avatar-quicklink">
                    <span class="avatar-quicklink-icon">
                        <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M1.5 3A1.5 1.5 0 0 0 0 4.5v8A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-8A1.5 1.5 0 0 0 14.5 3zm.94 1h11.12L8 8.79 1.44 4zM1 5.67l6.13 4.27a1.5 1.5 0 0 0 1.74 0L15 5.67V12.5a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5z"/></svg>
                    </span>
                    <span class="avatar-quicklink-label"><?php echo htmlspecialchars($i18n['message']); ?></span>
                    <?php $mc = (int)($data['messages_count'] ?? 0); if ($mc > 0): ?>
                        <span class="avatar-quicklink-badge"><?php echo $mc; ?></span>
                    <?php endif; ?>
                    <span class="avatar-quicklink-arrow">&#8250;</span>
                </a>
                <a href="<?php echo htmlspecialchars((string)($data['my_account_url'] ?? '')); ?>" class="avatar-quicklink">
                    <span class="avatar-quicklink-icon">
                        <svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1.75a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 0 0 0-6.5zM3.75 5a4.25 4.25 0 1 1 8.5 0 4.25 4.25 0 0 1-8.5 0z"/><path d="M8 9.25c-2.59 0-4.74 1.88-5.16 4.35a.5.5 0 0 0 .49.6h9.34a.5.5 0 0 0 .49-.6c-.42-2.47-2.57-4.35-5.16-4.35z"/></svg>
                    </span>
                    <span class="avatar-quicklink-label"><?php echo htmlspecialchars($i18n['my_account']); ?></span>
                    <span class="avatar-quicklink-arrow">&#8250;</span>
                </a>
            </div>

            <!-- Preferences -->
            <div class="avatar-section">
                <div class="avatar-pref-row">
                    <label class="avatar-pref-label" for="operatorLangSelect"><?php echo htmlspecialchars($i18n['language']); ?></label>
                    <select class="avatar-select" id="operatorLangSelect">
                        <?php foreach ($operatorLanguageOptions as $languageCode => $languageLabel): ?>
                            <option value="<?php echo htmlspecialchars((string)$languageCode); ?>" <?php echo $operatorLanguage === (string)$languageCode ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$languageLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="avatar-pref-row">
                    <label class="avatar-pref-label" for="operatorCurrencySelect"><?php echo htmlspecialchars($i18n['currency']); ?></label>
                    <select class="avatar-select" id="operatorCurrencySelect">
                        <option value="usd" <?php echo $operatorCurrency === 'usd' ? 'selected' : ''; ?>>USD</option>
                        <option value="jpy" <?php echo $operatorCurrency === 'jpy' ? 'selected' : ''; ?>>JPY</option>
                        <option value="npr" <?php echo $operatorCurrency === 'npr' ? 'selected' : ''; ?>>NPR</option>
                    </select>
                </div>
                <div class="avatar-pref-row">
                    <label class="avatar-pref-label" for="operatorThemeSelect"><?php echo htmlspecialchars($i18n['theme']); ?></label>
                    <select class="avatar-select" id="operatorThemeSelect">
                        <?php foreach ($operatorThemeChoices as $themeValue => $themeLabel): ?>
                            <option value="<?php echo htmlspecialchars((string)$themeValue); ?>"><?php echo htmlspecialchars((string)$themeLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="avatar-pref-row avatar-pref-row--range">
                    <label class="avatar-pref-label" for="operatorOverlayEffectStrength">Overlay effect</label>
                    <div class="shell-overlay-strength-control">
                        <input
                            class="shell-overlay-strength-slider"
                            id="operatorOverlayEffectStrength"
                            type="range"
                            min="0"
                            max="100"
                            step="1"
                            data-overlay-effect-strength
                            aria-label="Overlay effect strength"
                        >
                        <output class="shell-overlay-strength-value" data-overlay-effect-strength-value for="operatorOverlayEffectStrength">40</output>
                    </div>
                </div>
            </div>

            <!-- Bottom actions -->
            <div class="avatar-section avatar-section--actions">
                <?php if ((bool)($data['can_switch_to_admin'] ?? false)): ?>
                <a href="<?php echo htmlspecialchars((string)($data['switch_admin_url'] ?? ('/admin/' . rawurlencode((string)($data['username'] ?? ''))))); ?>" class="avatar-action-link">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M11 1H5a1 1 0 0 0-1 1v2H1.5a.5.5 0 0 0 0 1H4v2H1.5a.5.5 0 0 0 0 1H4v2H1.5a.5.5 0 0 0 0 1H4v2a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zm0 13H5V2h6v12z"/></svg>
                    <?php echo htmlspecialchars($i18n['switch_to_me']); ?>
                </a>
                <?php endif; ?>
                <a href="/logout" class="avatar-action-link avatar-action-link--danger" onclick="return confirm('<?php echo htmlspecialchars($i18n['sign_out_confirm']); ?>');">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M10 1.5H5.5A1.5 1.5 0 0 0 4 3v2.5h1V3a.5.5 0 0 1 .5-.5H10a.5.5 0 0 1 .5.5v10a.5.5 0 0 1-.5.5H5.5A.5.5 0 0 1 5 13v-2.5H4V13a1.5 1.5 0 0 0 1.5 1.5H10A1.5 1.5 0 0 0 11.5 13V3A1.5 1.5 0 0 0 10 1.5z"/><path d="M1.146 8.354a.5.5 0 0 1 0-.708l2-2a.5.5 0 1 1 .708.708L2.707 7.5H8.5a.5.5 0 0 1 0 1H2.707l1.147 1.146a.5.5 0 0 1-.708.708l-2-2z"/></svg>
                    <?php echo htmlspecialchars($i18n['sign_out']); ?>
                </a>
            </div>
        </div>
    </div>
        <?php
        return ob_get_clean() ?: '';
    }
}
