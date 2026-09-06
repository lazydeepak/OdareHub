-- Normalize user assignment schema currently assumed by runtime services.

ALTER TABLE users
    ADD COLUMN role_tier VARCHAR(20) NULL AFTER role,
    ADD COLUMN account_type VARCHAR(40) NULL AFTER role_tier,
    ADD COLUMN display_name VARCHAR(190) NULL AFTER email,
    ADD COLUMN username VARCHAR(120) NULL AFTER display_name,
    ADD COLUMN department VARCHAR(120) NULL AFTER username,
    ADD COLUMN account_status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER account_type,
    ADD COLUMN verification_status VARCHAR(40) NOT NULL DEFAULT 'ready' AFTER account_status,
    ADD COLUMN security_status VARCHAR(40) NOT NULL DEFAULT 'standard' AFTER verification_status;

ALTER TABLE user_dashboard_assignments
    ADD COLUMN authority_role VARCHAR(40) NULL AFTER dashboard_type,
    ADD COLUMN assigned_apps TEXT NULL AFTER default_landing_page,
    ADD COLUMN access_profiles TEXT NULL AFTER assigned_apps,
    ADD COLUMN permissions TEXT NULL AFTER access_profiles,
    ADD COLUMN view_access TEXT NULL AFTER permissions,
    ADD COLUMN table_access TEXT NULL AFTER view_access,
    ADD COLUMN chart_access TEXT NULL AFTER table_access,
    ADD COLUMN module_visibility TEXT NULL AFTER chart_access,
    ADD COLUMN dashboard_mode VARCHAR(16) NOT NULL DEFAULT 'auto' AFTER chart_access,
    ADD COLUMN default_app_mode VARCHAR(16) NOT NULL DEFAULT 'auto' AFTER dashboard_mode,
    ADD COLUMN landing_mode VARCHAR(16) NOT NULL DEFAULT 'auto' AFTER default_app_mode,
    ADD COLUMN access_profiles_mode VARCHAR(16) NOT NULL DEFAULT 'auto' AFTER landing_mode,
    ADD COLUMN module_visibility_mode VARCHAR(16) NOT NULL DEFAULT 'auto' AFTER access_profiles_mode,
    ADD COLUMN account_class VARCHAR(40) NULL AFTER module_visibility_mode,
    ADD COLUMN selected_role_packs TEXT NULL AFTER account_class,
    ADD COLUMN cross_functional_access TEXT NULL AFTER module_visibility_mode,
    ADD COLUMN me_dashboard_blocks TEXT NULL AFTER cross_functional_access,
    ADD COLUMN me_plugin_cards TEXT NULL AFTER me_dashboard_blocks,
    ADD COLUMN display_surfaces TEXT NULL AFTER me_plugin_cards,
    ADD COLUMN operator_views TEXT NULL AFTER display_surfaces,
    ADD COLUMN workspace_profile_key VARCHAR(100) NULL AFTER me_plugin_cards;

CREATE TABLE IF NOT EXISTS user_role_duties (
    user_id INT PRIMARY KEY,
    duty_codes TEXT NULL,
    duty_notes TEXT NULL,
    updated_by VARCHAR(190) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
