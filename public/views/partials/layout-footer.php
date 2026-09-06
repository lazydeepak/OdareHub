<?php
$layoutFooterShowLayerLinks = (bool)($layoutFooterShowLayerLinks ?? true);

$translateFooter = static function (string $key, string $fallback = ''): string {
    if (function_exists('t')) {
        try {
            $value = (string)t($key, $fallback);
            if ($value !== '' && $value !== $key) {
                return $value;
            }
        } catch (\Throwable $e) {
        }

        try {
            $value = (string)t($key);
            if ($value !== '' && $value !== $key) {
                return $value;
            }
        } catch (\Throwable $e) {
        }
    }

    return $fallback;
};

$footerAppName = $translateFooter('app.name', 'IPM Local');
$footerSwitchOperatorLabel = $translateFooter('wrapper.admin.footer.switch_operator', 'Switch to Operator');
$footerSwitchAdminLabel = $translateFooter('wrapper.operator.footer.switch_admin', 'Switch to Admin');

$loggedIn = \App\Core\Auth::isLoggedIn();
$user = $loggedIn ? \App\Core\Auth::user() : null;
$userUsername = $loggedIn && is_array($user) ? (string)($user['username'] ?? '') : '';
$userEmail = $loggedIn && is_array($user) ? (string)($user['email'] ?? '') : '';
$userHandle = strtolower(trim($userUsername));
if ($userHandle === '') {
    $userHandle = strtolower(trim((string)(strstr($userEmail, '@', true) ?: $userEmail)));
}

$operatorLayerUrl = $userHandle !== '' ? '/u/' . urlencode($userHandle) . '/dashboard' : '';
$adminLayerUrl = $userHandle !== '' ? '/admin/' . urlencode($userHandle) : '';

$currentPathForFooter = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
// Admin layer = every path that is NOT /u/* and NOT /displays/* (same inversion as header.php).
$isOnAdminLayer = !str_starts_with($currentPathForFooter, '/u/') && !str_starts_with($currentPathForFooter, '/displays/');
$isOnOperatorLayer = str_starts_with($currentPathForFooter, '/u/');
?>
<footer class="layout-footer">
    <div class="layout-footer-copy"><?php echo htmlspecialchars($footerAppName); ?> v<?php echo htmlspecialchars((string)APP_VERSION); ?> © <?php echo date('Y'); ?></div>
    <?php if ($layoutFooterShowLayerLinks && $loggedIn && ($operatorLayerUrl !== '' || $adminLayerUrl !== '')): ?>
    <div class="layout-footer-links">
        <?php if (!$isOnOperatorLayer && $operatorLayerUrl !== ''): ?>
        <a href="<?php echo htmlspecialchars($operatorLayerUrl, ENT_QUOTES, 'UTF-8'); ?>" class="layout-footer-link"><?php echo htmlspecialchars($footerSwitchOperatorLabel); ?> 👤</a>
        <?php endif; ?>
        <?php if (!$isOnAdminLayer && $adminLayerUrl !== ''): ?>
        <a href="<?php echo htmlspecialchars($adminLayerUrl, ENT_QUOTES, 'UTF-8'); ?>" class="layout-footer-link"><?php echo htmlspecialchars($footerSwitchAdminLabel); ?> ⚙️</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</footer>
