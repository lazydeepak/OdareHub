<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services;

final class CssLiveEditorPlaceholderService
{
    /**
     * @return array<string,mixed>
     */
    public static function model(array $context = []): array
    {
        $locale = self::locale();

        return [
            'title' => self::text($locale, 'title'),
            'status' => self::text($locale, 'status'),
            'message' => self::text($locale, 'message'),
            'target_title' => self::text($locale, 'target_title'),
            'target_description' => self::text($locale, 'target_description'),
            'target_mode_label' => self::text($locale, 'target_mode_label'),
            'target_mode_route' => self::text($locale, 'target_mode_route'),
            'target_mode_view' => self::text($locale, 'target_mode_view'),
            'route_label' => self::text($locale, 'route_label'),
            'route_placeholder' => self::text($locale, 'route_placeholder'),
            'view_label' => self::text($locale, 'view_label'),
            'view_placeholder' => self::text($locale, 'view_placeholder'),
            'load_preview' => self::text($locale, 'load_preview'),
            'target_value' => trim((string)($context['target'] ?? '')),
            'target_error' => trim((string)($context['target_error'] ?? '')),
            'target_options' => is_array($context['target_options'] ?? null) ? $context['target_options'] : [],
            'template_options' => is_array($context['template_options'] ?? null) ? $context['template_options'] : [],
            'selected_target' => is_array($context['selected_target'] ?? null) ? $context['selected_target'] : [],
            'target_selector_label' => self::text($locale, 'target_selector_label'),
            'target_selector_hint' => self::text($locale, 'target_selector_hint'),
            'target_unavailable_label' => self::text($locale, 'target_unavailable_label'),
            'target_none_selected' => self::text($locale, 'target_none_selected'),
            'template_search_label' => self::text($locale, 'template_search_label'),
            'template_search_placeholder' => self::text($locale, 'template_search_placeholder'),
            'template_search_hint' => self::text($locale, 'template_search_hint'),
            'template_results_label' => self::text($locale, 'template_results_label'),
            'template_none_selected' => self::text($locale, 'template_none_selected'),
            'template_file_label' => self::text($locale, 'template_file_label'),
            'template_canvas_status' => self::text($locale, 'template_canvas_status'),
            'preview_frame_url' => trim((string)($context['preview_frame_url'] ?? '')),
            'sanitizer_policy' => is_array($context['sanitizer_policy'] ?? null) ? $context['sanitizer_policy'] : [],
            'style_source_catalog' => is_array($context['style_source_catalog'] ?? null) ? $context['style_source_catalog'] : [],
            'css_source_resolution_catalog' => is_array($context['css_source_resolution_catalog'] ?? null)
                ? $context['css_source_resolution_catalog']
                : [],
            'canvas_title' => self::text($locale, 'canvas_title'),
            'canvas_description' => self::text($locale, 'canvas_description'),
            'preview_theme_label' => self::text($locale, 'preview_theme_label'),
            'preview_theme_unavailable' => self::text($locale, 'preview_theme_unavailable'),
            'metadata_title' => self::text($locale, 'metadata_title'),
            'metadata_target' => self::text($locale, 'metadata_target'),
            'metadata_owner' => self::text($locale, 'metadata_owner'),
            'metadata_source' => self::text($locale, 'metadata_source'),
            'metadata_adapter' => self::text($locale, 'metadata_adapter'),
            'metadata_data_mode' => self::text($locale, 'metadata_data_mode'),
            'metadata_theme' => self::text($locale, 'metadata_theme'),
            'theme_options' => is_array($context['theme_options'] ?? null) ? $context['theme_options'] : [],
            'theme_option_provider_available' => !empty($context['theme_option_provider_available']),
            'runtime_theme_preference' => trim((string)($context['runtime_theme_preference'] ?? '')),
            'canvas_empty' => self::text($locale, 'canvas_empty'),
            'preview_loading' => self::text($locale, 'preview_loading'),
            'preview_ready' => self::text($locale, 'preview_ready'),
            'preview_failed' => self::text($locale, 'preview_failed'),
            'preview_error_invalid_target' => self::text($locale, 'preview_error_invalid_target'),
            'inspector_title' => self::text($locale, 'inspector_title'),
            'inspector_description' => self::text($locale, 'inspector_description'),
            'selection_label' => self::text($locale, 'selection_label'),
            'selection_empty' => self::text($locale, 'selection_empty'),
            'selection_tag_label' => self::text($locale, 'selection_tag_label'),
            'selection_id_label' => self::text($locale, 'selection_id_label'),
            'selection_classes_label' => self::text($locale, 'selection_classes_label'),
            'selection_feed_label' => self::text($locale, 'selection_feed_label'),
            'selection_source_label' => self::text($locale, 'selection_source_label'),
            'owner_label' => self::text($locale, 'owner_label'),
            'owner_unknown' => trim((string)($context['source_owner'] ?? '')) ?: self::text($locale, 'owner_unknown'),
            'source_target_label' => self::text($locale, 'source_target_label'),
            'source_target_unresolved' => trim((string)($context['source_target'] ?? '')) ?: self::text($locale, 'source_target_unresolved'),
            'css_target_label' => self::text($locale, 'css_target_label'),
            'css_target_unresolved' => trim((string)($context['css_target'] ?? '')) ?: self::text($locale, 'css_target_unresolved'),
            'css_resolution_title' => self::text($locale, 'css_resolution_title'),
            'css_resolution_status_label' => self::text($locale, 'css_resolution_status_label'),
            'css_resolution_found' => self::text($locale, 'css_resolution_found'),
            'css_resolution_none' => self::text($locale, 'css_resolution_none'),
            'css_resolution_candidates_label' => self::text($locale, 'css_resolution_candidates_label'),
            'css_resolution_selectors_label' => self::text($locale, 'css_resolution_selectors_label'),
            'css_resolution_editable_label' => self::text($locale, 'css_resolution_editable_label'),
            'css_resolution_editing_disabled' => self::text($locale, 'css_resolution_editing_disabled'),
            'css_resolution_candidate_label' => self::text($locale, 'css_resolution_candidate_label'),
            'css_resolution_likely_selector' => self::text($locale, 'css_resolution_likely_selector'),
            'css_resolution_class_match' => self::text($locale, 'css_resolution_class_match'),
            'css_resolution_owner_stylesheet' => self::text($locale, 'css_resolution_owner_stylesheet'),
            'token_title' => self::text($locale, 'token_title'),
            'token_empty' => self::text($locale, 'token_empty'),
            'token_name_label' => self::text($locale, 'token_name_label'),
            'token_value_label' => self::text($locale, 'token_value_label'),
            'token_property_label' => self::text($locale, 'token_property_label'),
            'token_owner_label' => self::text($locale, 'token_owner_label'),
            'token_source_label' => self::text($locale, 'token_source_label'),
            'save_label' => self::text($locale, 'save_label'),
            'save_disabled' => self::text($locale, 'save_disabled'),
            'back' => self::text($locale, 'back'),
            'selector_preview_status' => self::text($locale, 'selector_preview_status'),
            'clear_highlight' => self::text($locale, 'clear_highlight'),
            'matches_label' => self::text($locale, 'matches_label'),
            'declaration_title' => self::text($locale, 'declaration_title'),
            'declaration_empty' => self::text($locale, 'declaration_empty'),
            'declaration_status' => self::text($locale, 'declaration_status'),
            'declaration_source_label' => self::text($locale, 'declaration_source_label'),
            'declaration_selector_label' => self::text($locale, 'declaration_selector_label'),
            'declaration_unresolved' => self::text($locale, 'declaration_unresolved'),
            'match_label' => self::text($locale, 'match_label'),
            'broad_selector_label' => self::text($locale, 'broad_selector_label'),
            'selector_unsafe' => self::text($locale, 'selector_unsafe'),
            'read_only' => true,
            'runtime_behavior' => false,
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function locale(): array
    {
        $lang = function_exists('current_lang') ? (string)current_lang() : 'en';
        $safeLang = in_array($lang, ['en', 'ja', 'ne'], true) ? $lang : 'en';
        $fallback = self::loadLocale('en');

        if ($safeLang === 'en') {
            return $fallback;
        }

        return array_replace($fallback, self::loadLocale($safeLang));
    }

    /**
     * @return array<string,string>
     */
    private static function loadLocale(string $lang): array
    {
        $path = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/Resources/lang/' . $lang . '.php';
        if (!is_file($path)) {
            return [];
        }

        $loaded = require $path;
        return is_array($loaded) ? $loaded : [];
    }

    /**
     * @param array<string,string> $locale
     */
    private static function text(array $locale, string $key): string
    {
        return (string)($locale[$key] ?? $key);
    }
}
