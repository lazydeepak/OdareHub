window.gsLoadedResourceContextDefaults = {
  defaultPreviewBackend: <?= json_encode($gs('studio_tools_preview_backend_linked')) ?>,
  actionState: <?= json_encode($gs('studio_workbench_context_action_preview_only') . ' · ' . $gs('studio_workbench_context_action_readonly_analysis') . ' · ' . $gs('studio_workbench_context_action_no_apply')) ?>
};
