<?php
/**
 * Label Designer — Governed Studio workbench for app/module-owned label templates.
 *
 * Architecture:
 * - Apps/modules/plugins own label contexts and templates.
 * - Apps/modules/plugins own label rules and runtime meaning.
 * - Studio owns only this builder/editor workbench.
 * - Platform owns the shared render/export/print pipeline.
 * - Core provides governance/permissions/path-safety/contracts.
 *
 * Manufacturing QR Product Label is the first reference implementation.
 * Future label types: product, pallet, case, part, material, bin/location,
 * dispatch, QC, asset, service/job.
 *
 * Owner resource paths:
 *   {OwnerRoot}/Resources/labels/contexts/
 *   {OwnerRoot}/Resources/labels/templates/
 *   {OwnerRoot}/Resources/labels/rules/
 *
 * @see docs/architecture/label-designer-operating-contract.md
 */
return [
    'tool_key' => 'label_designer',
    'key' => 'label_designer',
    'label' => 'Label Designer',
    'name' => 'Label Designer',
    'name_key' => 'studio.tool.label_designer.name',
    'description_key' => 'studio.tool.label_designer.description',
    'category' => 'authoring',
    'home_group' => 'design_content',
    'canonical_route' => '/apps/studio/tools/label-designer',
    'placeholder' => false,
    'risk_level' => 'low',
    'default_enabled' => false,
    'can_disable' => true,
    'required_permissions' => ['studio.tools.label_designer.use'],
    'allowed_environments' => ['local', 'staging', 'production'],
    'routes' => ['/apps/studio/tools/label-designer'],
    'views' => ['apps/Studio/Tools/LabelDesigner/Views/preview.php'],
    'services' => [
        'Apps\\Studio\\Controllers\\StudioController::labelDesignerPreview',
    ],
    'owner' => 'studio',
    'status' => 'guarded_context_template_rule_create_preview_resource_readiness',
    'can_load' => ['label', 'view'],
    'can_modify' => true,
    'requires_approval' => false,
    'writes_to_owner_artifact' => true,
    'supports_diff' => true,
    'supports_snapshot' => true,
    'supports_rollback' => false,
];
