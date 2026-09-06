<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

final class StudioToolCatalogService
{
    /**
     * Studio owns tool catalog metadata for Studio tools only.
     * This catalog describes Studio workbench tools and does not own business app/module resources.
     *
     * @var array<int,string>
     */
    private const GROUP_ORDER = [
        'studio_tools_group_explore',
        'studio_tools_group_build',
        'studio_tools_group_validate',
        'studio_tools_group_govern',
        'studio_tools_group_history',
    ];

    /**
     * @var array<int,array<string,mixed>>
     */
    private const TOOLS = [
        [
            'id' => 'resource_explorer',
            'name_key' => 'studio_tool_resource_explorer',
            'group_key' => 'studio_tools_group_explore',
            'status_key' => 'studio_tools_status_available',
            'purpose_key' => 'studio_tool_purpose_resource_explorer',
            'works_on_key' => 'studio_tools_boundary_owner_resources',
            'must_not_own_key' => 'studio_tools_boundary_not_owner',
            'first_safe_key' => 'studio_tools_preview_first_safe_linked',
            'backend_key' => 'studio_tools_preview_backend_linked',
            'card_boundary_key' => 'studio_tools_boundary_owner_resources',
            'href' => '/apps/studio/library',
            'link_hint_key' => 'studio_tools_open_library',
            'is_disabled' => false,
        ],
        [
            'id' => 'app_builder',
            'name_key' => 'studio_tool_app_builder',
            'group_key' => 'studio_tools_group_build',
            'status_key' => 'studio_tools_status_planned',
            'purpose_key' => 'studio_tool_purpose_app_builder',
            'works_on_key' => 'studio_tools_boundary_owner_resources',
            'must_not_own_key' => 'studio_tools_boundary_not_owner',
            'first_safe_key' => 'studio_tools_preview_first_safe_governed',
            'backend_key' => 'studio_tools_preview_backend_unwired',
            'card_boundary_key' => 'studio_tools_boundary_not_owner',
            'href' => null,
            'link_hint_key' => null,
            'is_disabled' => true,
        ],
        [
            'id' => 'module_builder',
            'name_key' => 'studio_tool_module_builder',
            'group_key' => 'studio_tools_group_build',
            'status_key' => 'studio_tools_status_planned',
            'purpose_key' => 'studio_tool_purpose_module_builder',
            'works_on_key' => 'studio_tools_boundary_owner_resources',
            'must_not_own_key' => 'studio_tools_boundary_not_owner',
            'first_safe_key' => 'studio_tools_preview_first_safe_governed',
            'backend_key' => 'studio_tools_preview_backend_unwired',
            'card_boundary_key' => 'studio_tools_boundary_not_owner',
            'href' => null,
            'link_hint_key' => null,
            'is_disabled' => true,
        ],
        [
            'id' => 'view_layout_builder',
            'name_key' => 'studio_tool_view_layout_builder',
            'group_key' => 'studio_tools_group_build',
            'status_key' => 'studio_tools_status_planned',
            'purpose_key' => 'studio_tool_purpose_view_layout_builder',
            'works_on_key' => 'studio_tools_boundary_owner_resources',
            'must_not_own_key' => 'studio_tools_boundary_not_owner',
            'first_safe_key' => 'studio_tools_preview_first_safe_governed',
            'backend_key' => 'studio_tools_preview_backend_unwired',
            'card_boundary_key' => 'studio_tools_boundary_owner_resources',
            'href' => null,
            'link_hint_key' => null,
            'is_disabled' => true,
        ],
        [
            'id' => 'navigation_menu_tool',
            'name_key' => 'studio_tool_navigation_menu',
            'group_key' => 'studio_tools_group_build',
            'status_key' => 'studio_tools_status_read_only',
            'purpose_key' => 'studio_tool_purpose_navigation_menu',
            'works_on_key' => 'studio_tools_boundary_owner_resources',
            'must_not_own_key' => 'studio_tools_boundary_not_owner',
            'first_safe_key' => 'studio_tools_preview_first_safe_readonly',
            'backend_key' => 'studio_tools_preview_backend_unwired',
            'card_boundary_key' => 'studio_tools_boundary_not_owner',
            'href' => '/apps/studio/tools/nav-composer',
            'link_hint_key' => null,
            'is_disabled' => false,
        ],
        [
            'id' => 'widget_card_builder',
            'name_key' => 'studio_tool_widget_card_builder',
            'group_key' => 'studio_tools_group_build',
            'status_key' => 'studio_tools_status_planned',
            'purpose_key' => 'studio_tool_purpose_widget_card_builder',
            'works_on_key' => 'studio_tools_boundary_owner_resources',
            'must_not_own_key' => 'studio_tools_boundary_not_owner',
            'first_safe_key' => 'studio_tools_preview_first_safe_governed',
            'backend_key' => 'studio_tools_preview_backend_unwired',
            'card_boundary_key' => 'studio_tools_boundary_owner_resources',
            'href' => null,
            'link_hint_key' => null,
            'is_disabled' => true,
        ],
        [
            'id' => 'report_builder',
            'name_key' => 'studio_tool_report_builder',
            'group_key' => 'studio_tools_group_build',
            'status_key' => 'studio_tools_status_planned',
            'purpose_key' => 'studio_tool_purpose_report_builder',
            'works_on_key' => 'studio_tools_boundary_owner_resources',
            'must_not_own_key' => 'studio_tools_boundary_not_owner',
            'first_safe_key' => 'studio_tools_preview_first_safe_governed',
            'backend_key' => 'studio_tools_preview_backend_unwired',
            'card_boundary_key' => 'studio_tools_boundary_not_owner',
            'href' => null,
            'link_hint_key' => null,
            'is_disabled' => true,
        ],
        [
            'id' => 'data_model_schema_tool',
            'name_key' => 'studio_tool_data_model_schema',
            'group_key' => 'studio_tools_group_build',
            'status_key' => 'studio_tools_status_planned',
            'purpose_key' => 'studio_tool_purpose_data_model_schema',
            'works_on_key' => 'studio_tools_boundary_owner_resources',
            'must_not_own_key' => 'studio_tools_boundary_not_owner',
            'first_safe_key' => 'studio_tools_preview_first_safe_governed',
            'backend_key' => 'studio_tools_preview_backend_unwired',
            'card_boundary_key' => 'studio_tools_boundary_no_runtime_without_apply',
            'href' => null,
            'link_hint_key' => null,
            'is_disabled' => true,
        ],
        [
            'id' => 'validation_preview_center',
            'name_key' => 'studio_tool_validation_preview_center',
            'group_key' => 'studio_tools_group_validate',
            'status_key' => 'studio_tools_status_read_only',
            'purpose_key' => 'studio_tool_purpose_validation_preview_center',
            'works_on_key' => 'studio_tools_boundary_owner_resources',
            'must_not_own_key' => 'studio_tools_boundary_not_owner',
            'first_safe_key' => 'studio_tools_preview_first_safe_readonly',
            'backend_key' => 'studio_tools_preview_backend_unwired',
            'card_boundary_key' => 'studio_tools_boundary_no_runtime_without_apply',
            'href' => null,
            'link_hint_key' => null,
            'is_disabled' => true,
        ],
        [
            'id' => 'approval_apply_center',
            'name_key' => 'studio_tool_approval_apply_center',
            'group_key' => 'studio_tools_group_govern',
            'status_key' => 'studio_tools_status_requires_governed',
            'purpose_key' => 'studio_tool_purpose_approval_apply_center',
            'works_on_key' => 'studio_tools_boundary_owner_resources',
            'must_not_own_key' => 'studio_tools_boundary_not_owner',
            'first_safe_key' => 'studio_tools_preview_first_safe_governed',
            'backend_key' => 'studio_tools_preview_backend_unwired',
            'card_boundary_key' => 'studio_tools_boundary_no_runtime_without_apply',
            'href' => null,
            'link_hint_key' => null,
            'is_disabled' => true,
        ],
        [
            'id' => 'change_history_snapshots',
            'name_key' => 'studio_tool_change_history_snapshots',
            'group_key' => 'studio_tools_group_history',
            'status_key' => 'studio_tools_status_available',
            'purpose_key' => 'studio_tool_purpose_change_history_snapshots',
            'works_on_key' => 'studio_tools_boundary_owner_resources',
            'must_not_own_key' => 'studio_tools_boundary_not_owner',
            'first_safe_key' => 'studio_tools_preview_first_safe_linked',
            'backend_key' => 'studio_tools_preview_backend_linked',
            'card_boundary_key' => 'studio_tools_boundary_not_owner',
            'href' => '/apps/studio/history',
            'link_hint_key' => 'studio_tools_open_history',
            'is_disabled' => false,
        ],
    ];

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listTools(): array
    {
        return self::TOOLS;
    }

    /**
     * @return array<int,string>
     */
    public static function groupOrder(): array
    {
        return self::GROUP_ORDER;
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function listToolsByGroup(): array
    {
        $grouped = [];
        foreach (self::GROUP_ORDER as $groupKey) {
            $grouped[$groupKey] = [];
        }
        foreach (self::TOOLS as $tool) {
            $groupKey = (string)($tool['group_key'] ?? '');
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [];
            }
            $grouped[$groupKey][] = $tool;
        }
        return $grouped;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findTool(string $toolId): ?array
    {
        foreach (self::TOOLS as $tool) {
            if ((string)($tool['id'] ?? '') === $toolId) {
                return $tool;
            }
        }
        return null;
    }
}
