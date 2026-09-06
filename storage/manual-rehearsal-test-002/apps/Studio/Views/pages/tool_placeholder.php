<?php
declare(strict_types=1);

// Manifest-driven placeholder identities, including report_designer, remain in
// tool_placeholder_renderer.php. This wrapper adds optional tool-owned postludes.
require __DIR__ . '/tool_placeholder_renderer.php';

$studioToolTemplate = trim((string)($toolTemplatePath ?? ''));
if ($studioToolTemplate === '' || !is_file($studioToolTemplate)) {
    return;
}

$primaryPostlude = preg_replace('/\.php$/', '.postlude.php', $studioToolTemplate);
if (is_string($primaryPostlude)
    && $primaryPostlude !== $studioToolTemplate
    && is_file($primaryPostlude)
) {
    require $primaryPostlude;
}

$additionalPattern = preg_replace('/\.php$/', '.postlude.*.php', $studioToolTemplate);
$additionalPostludes = is_string($additionalPattern) ? glob($additionalPattern) : false;
if (is_array($additionalPostludes)) {
    sort($additionalPostludes, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($additionalPostludes as $additionalPostlude) {
        if (is_string($additionalPostlude) && is_file($additionalPostlude)) {
            require $additionalPostlude;
        }
    }
}
