<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services;

final class CssLiveEditorPreviewSanitizer
{
    /**
     * @return array<string,mixed>
     */
    public static function policy(): array
    {
        return [
            'remove_selectors' => [
                'script',
                'noscript',
                'meta[http-equiv="refresh"]',
            ],
            'disable_selectors' => [
                'button',
                'input',
                'select',
                'textarea',
                'option',
                '[contenteditable]',
            ],
            'block_navigation_selectors' => [
                'a[href]',
                'form',
            ],
            'hide_selectors' => [
                'a[href*="/logout"]',
                '[data-action*="delete"]',
                '[data-action*="save"]',
                '[data-action*="apply"]',
                '[data-action*="approve"]',
                '[data-action*="reject"]',
                '[formaction]',
                '.btn-danger',
                '.danger',
                '.destructive',
            ],
            'blocked_url_attributes' => ['action', 'formaction', 'href', 'srcdoc', 'target'],
            'inspector_attribute' => 'data-css-live-editor-node-id',
        ];
    }
}
