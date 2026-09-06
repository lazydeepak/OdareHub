<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services;

final class CssLiveEditorTemplateRenderer
{
    /**
     * @param array<string,mixed> $target
     */
    public function render(array $target): string
    {
        $templateFile = CssLiveEditorTemplateTargetService::absolutePath($target);
        $preview = true;
        $readOnly = true;
        $cssLiveEditorPreview = true;
        $model = [];
        $data = [];
        $items = [];
        $rows = [];
        $errors = [];

        ob_start();
        set_error_handler(static function (int $severity): bool {
            return in_array($severity, [E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_WARNING, E_USER_NOTICE, E_USER_DEPRECATED], true);
        });
        try {
            require $templateFile;
            return (string)ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            return '<section class="cle-template-unavailable"><h1>Template feed unavailable</h1>'
                . '<p>This approved template needs controller context that is not available in the direct development feed.</p>'
                . '<code>' . htmlspecialchars((string)($target['id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</code></section>';
        } finally {
            restore_error_handler();
        }
    }

    public function tr(string $key, string $fallback = ''): string
    {
        if (function_exists('t')) {
            $translated = (string)t($key);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }

        return $fallback !== '' ? $fallback : $key;
    }
}
