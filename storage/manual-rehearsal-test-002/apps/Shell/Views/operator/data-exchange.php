<?php
// Operator Layer View: data-exchange
// Renders system-governed import/export adapters contributed by assigned apps.
?>
<?php
    $dx = isset($dataExchangeData) && is_array($dataExchangeData) ? $dataExchangeData : [];
    if ($dx === []) {
        $dxComposer = new \Apps\Shell\Composers\DataExchangeComposer(
            (string)($data['username'] ?? ''),
            (array)$this->context
        );
        $dx = $dxComposer->build((array)($data['current_query'] ?? []));
    }
    $dxDefinitions = is_array($dx['definitions'] ?? null) ? $dx['definitions'] : [];
    $dxSelected = is_array($dx['selected'] ?? null) ? $dx['selected'] : null;
    $dxQueueJobs = array_values((array)($dx['queue_jobs'] ?? []));
    $dxCanApprove = !empty($dx['can_approve']);
    $dxIntent = strtolower(trim((string)($dx['selected_intent'] ?? 'import')));
    $dxUsername = rawurlencode((string)($dx['username'] ?? ($data['username'] ?? '')));
    $dxBase = '/u/' . $dxUsername . '/data-exchange';
    $dxFlashOk = trim((string)($_SESSION['operator_data_exchange_flash_ok'] ?? ''));
    $dxFlashErr = trim((string)($_SESSION['operator_data_exchange_flash_err'] ?? ''));
    $dxDailyOrderAdapter = null;
    foreach ($dxDefinitions as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        $candidateKey = strtolower(trim((string)($candidate['key'] ?? '')));
        if ($candidateKey === 'mfg.daily_orders') {
            $dxDailyOrderAdapter = $candidate;
            break;
        }
    }
    unset($_SESSION['operator_data_exchange_flash_ok'], $_SESSION['operator_data_exchange_flash_err']);
?>
<section class="coverage-focus-section" aria-label="<?php echo htmlspecialchars((string)($dx['title'] ?? $this->tr('operator.data_exchange.title', 'Data Exchange'))); ?>">
    <div class="coverage-focus-header">
        <h2 class="surface-title"><?php echo htmlspecialchars((string)($dx['title'] ?? $this->tr('operator.data_exchange.title', 'Data Exchange'))); ?></h2>
    </div>

    <p class="coverage-focus-caption"><?php echo htmlspecialchars((string)($dx['subtitle'] ?? $this->tr('operator.data_exchange.subtitle', 'System-governed import/export adapters from assigned apps and modules.'))); ?></p>
    <p class="coverage-focus-saved"><?php echo htmlspecialchars((string)($dx['governance_note'] ?? $this->tr('operator.data_exchange.governance_note', 'All operations are policy-governed and fully audited before execution.'))); ?></p>

    <?php if ($dxFlashOk !== ''): ?>
        <p class="coverage-focus-saved"><?php echo htmlspecialchars($dxFlashOk); ?></p>
    <?php endif; ?>
    <?php if ($dxFlashErr !== ''): ?>
        <p class="coverage-focus-error"><?php echo htmlspecialchars($dxFlashErr); ?></p>
    <?php endif; ?>

    <?php if ($dxDefinitions === []): ?>
        <div class="coverage-orders-section">
            <h3 class="coverage-orders-title"><?php echo htmlspecialchars((string)($dx['empty_title'] ?? $this->tr('operator.data_exchange.empty_title', 'No adapters available'))); ?></h3>
            <p class="coverage-focus-empty"><?php echo htmlspecialchars((string)($dx['empty_hint'] ?? $this->tr('operator.data_exchange.empty_hint', 'Ask an admin to assign apps/modules that publish data exchange adapters.'))); ?></p>
        </div>
    <?php else: ?>
        <div class="coverage-orders-section">
            <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.data_exchange.daily_orders.title', 'Daily Order Import')); ?></h3>
            <?php if (is_array($dxDailyOrderAdapter)): ?>
                <?php
                    $dailyOrderKey = (string)($dxDailyOrderAdapter['key'] ?? 'mfg.daily_orders');
                    $dailyOrderTitle = (string)($dxDailyOrderAdapter['title'] ?? $this->tr('operator.data_exchange.daily_orders.title', 'Daily Order Import'));
                    $dailyOrderDesc = (string)($dxDailyOrderAdapter['description'] ?? $this->tr('operator.data_exchange.daily_orders.hint', 'Upload customer daily order demand files before other exchanges.'));
                ?>
                <div class="operator-task-card">
                    <div class="operator-task-card__header">
                        <span class="operator-task-status operator-task-status--in_progress"><?php echo htmlspecialchars(strtoupper((string)($dxDailyOrderAdapter['app_key'] ?? 'manufacturing'))); ?></span>
                        <span class="operator-task-priority task-priority--medium"><?php echo htmlspecialchars((string)($dxDailyOrderAdapter['module_key'] ?? 'daily_orders')); ?></span>
                    </div>
                    <div class="operator-task-card__body">
                        <p class="operator-task-title"><?php echo htmlspecialchars($dailyOrderTitle); ?></p>
                        <p class="coverage-focus-caption"><?php echo htmlspecialchars($dailyOrderDesc); ?></p>
                    </div>
                    <div class="operator-task-card__actions">
                        <a class="btn-sm btn-primary" href="<?php echo htmlspecialchars($dxBase . '?adapter=' . rawurlencode($dailyOrderKey) . '&intent=import'); ?>"><?php echo htmlspecialchars($this->tr('operator.data_exchange.daily_orders.action', 'Open Daily Order Import')); ?></a>
                    </div>
                </div>
            <?php else: ?>
                <p class="coverage-focus-caption"><?php echo htmlspecialchars($this->tr('operator.data_exchange.daily_orders.unavailable', 'Daily Order import is unavailable for your current workspace assignments.')); ?></p>
            <?php endif; ?>
        </div>

        <div class="coverage-orders-section">
            <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.data_exchange.adapters', 'Adapters')); ?></h3>
            <div class="operator-tasks-list">
                <?php foreach ($dxDefinitions as $adapter): ?>
                    <?php
                        $adapterKey = (string)($adapter['key'] ?? '');
                        $appKey = (string)($adapter['app_key'] ?? '');
                        $moduleKey = (string)($adapter['module_key'] ?? '');
                        $title = (string)($adapter['title'] ?? $adapterKey);
                        $description = (string)($adapter['description'] ?? '');
                        $supportsImport = !empty($adapter['supports_import']);
                        $supportsExport = !empty($adapter['supports_export']);
                    ?>
                    <div class="operator-task-card">
                        <div class="operator-task-card__header">
                            <span class="operator-task-status operator-task-status--in_progress"><?php echo htmlspecialchars(strtoupper($appKey)); ?></span>
                            <span class="operator-task-priority task-priority--medium"><?php echo htmlspecialchars($moduleKey); ?></span>
                        </div>
                        <div class="operator-task-card__body">
                            <p class="operator-task-title"><?php echo htmlspecialchars($title); ?></p>
                            <p class="coverage-focus-caption"><?php echo htmlspecialchars($description); ?></p>
                            <div class="operator-task-meta">
                                <?php if ($supportsImport): ?>
                                    <span class="operator-task-meta-item"><?php echo htmlspecialchars($this->tr('operator.data_exchange.support.import', 'Import supported')); ?></span>
                                <?php endif; ?>
                                <?php if ($supportsExport): ?>
                                    <span class="operator-task-meta-item"><?php echo htmlspecialchars($this->tr('operator.data_exchange.support.export', 'Export supported')); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="operator-task-card__actions">
                            <?php if ($supportsImport): ?>
                                <a class="btn-sm" href="<?php echo htmlspecialchars($dxBase . '?adapter=' . rawurlencode($adapterKey) . '&intent=import'); ?>"><?php echo htmlspecialchars($this->tr('operator.data_exchange.action.import', 'Start Import')); ?></a>
                            <?php endif; ?>
                            <?php if ($supportsExport): ?>
                                <a class="btn-sm" href="<?php echo htmlspecialchars($dxBase . '?adapter=' . rawurlencode($adapterKey) . '&intent=export'); ?>"><?php echo htmlspecialchars($this->tr('operator.data_exchange.action.export', 'Start Export')); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (is_array($dxSelected)): ?>
            <?php
                $sTitle = (string)($dxSelected['title'] ?? '');
                $sDescription = (string)($dxSelected['description'] ?? '');
                $sTemplate = (string)($dxSelected['template_name'] ?? '');
                $sGovernance = (string)($dxSelected['governance_mode'] ?? 'audited');
                $sAdapterKey = (string)($dxSelected['key'] ?? '');
                $sImportColumns = array_values((array)($dxSelected['import_columns'] ?? []));
                $sExportColumns = array_values((array)($dxSelected['export_columns'] ?? []));
                $sSupportsImport = !empty($dxSelected['supports_import']);
                $sSupportsExport = !empty($dxSelected['supports_export']);
            ?>
            <div class="coverage-orders-section">
                <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.data_exchange.selection.title', 'Selected Adapter')); ?></h3>
                <p class="coverage-focus-caption"><?php echo htmlspecialchars($sTitle); ?></p>
                <p class="coverage-focus-caption"><?php echo htmlspecialchars($sDescription); ?></p>
                <?php if ($sTemplate !== ''): ?>
                    <p class="operator-task-meta-item"><?php echo htmlspecialchars($this->tr('operator.data_exchange.selection.template', 'Template:')); ?> <?php echo htmlspecialchars($sTemplate); ?></p>
                <?php endif; ?>
                <p class="operator-task-meta-item"><?php echo htmlspecialchars($this->tr('operator.data_exchange.selection.mode', 'Current mode:')); ?> <?php echo htmlspecialchars($dxIntent); ?></p>
                <p class="operator-task-meta-item"><?php echo htmlspecialchars($this->tr('operator.data_exchange.selection.governance', 'Governance:')); ?> <?php echo htmlspecialchars($sGovernance); ?></p>

                <?php if ($sImportColumns !== []): ?>
                    <p class="operator-task-meta-item"><?php echo htmlspecialchars($this->tr('operator.data_exchange.selection.import_columns', 'Import columns:')); ?> <?php echo htmlspecialchars(implode(', ', $sImportColumns)); ?></p>
                <?php endif; ?>
                <?php if ($sExportColumns !== []): ?>
                    <p class="operator-task-meta-item"><?php echo htmlspecialchars($this->tr('operator.data_exchange.selection.export_columns', 'Export columns:')); ?> <?php echo htmlspecialchars(implode(', ', $sExportColumns)); ?></p>
                <?php endif; ?>

                <?php if ($sSupportsImport): ?>
                    <form method="post" action="<?php echo htmlspecialchars($dxBase . '/import'); ?>" enctype="multipart/form-data" class="operator-task-card operator-task-card--spaced">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                        <input type="hidden" name="adapter" value="<?php echo htmlspecialchars($sAdapterKey); ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($dxBase . '?adapter=' . rawurlencode($sAdapterKey) . '&intent=import'); ?>">
                        <div class="operator-task-card__body">
                            <p class="operator-task-title"><?php echo htmlspecialchars($this->tr('operator.data_exchange.form.import_title', 'Upload Import File')); ?></p>
                            <input class="input" type="file" name="import_file" accept=".csv,.tsv,.txt" required>
                            <p class="coverage-focus-caption"><?php echo htmlspecialchars($this->tr('operator.data_exchange.form.import_hint', 'Accepted formats: CSV/TSV/TXT, maximum 5MB.')); ?></p>
                        </div>
                        <div class="operator-task-card__actions">
                            <button type="submit" class="btn-sm btn-primary"><?php echo htmlspecialchars($this->tr('operator.data_exchange.form.import_submit', 'Validate and Stage Import')); ?></button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($dxQueueJobs !== []): ?>
                    <div class="coverage-orders-section u-style-88b9d5c614">
                        <h3 class="coverage-orders-title"><?php echo htmlspecialchars($this->tr('operator.data_exchange.queue.title', 'Queue Jobs')); ?></h3>
                        <div class="operator-tasks-list">
                            <?php foreach ($dxQueueJobs as $job): ?>
                                <?php
                                    $jobId = (int)($job['id'] ?? 0);
                                    $jobStatus = trim((string)($job['status'] ?? 'pending'));
                                    $jobRows = (int)($job['row_count'] ?? 0);
                                    $jobFile = trim((string)($job['file_name'] ?? ''));
                                    $jobCreatedAt = trim((string)($job['created_at'] ?? ''));
                                    $jobResult = is_array($job['result'] ?? null) ? (array)$job['result'] : [];
                                    $jobExecution = is_array($jobResult['execution'] ?? null) ? (array)$jobResult['execution'] : [];
                                ?>
                                <div class="operator-task-card">
                                    <div class="operator-task-card__header">
                                        <span class="operator-task-status operator-task-status--in_progress"><?php echo htmlspecialchars(strtoupper((string)($job['app_key'] ?? ''))); ?></span>
                                        <span class="operator-task-priority task-priority--medium"><?php echo htmlspecialchars((string)($job['module_key'] ?? '')); ?></span>
                                    </div>
                                    <div class="operator-task-card__body">
                                        <p class="operator-task-title"><?php echo htmlspecialchars($this->tr('operator.data_exchange.queue.job', 'Job #{id}', ['id' => (string)$jobId])); ?></p>
                                        <p class="coverage-focus-caption"><?php echo htmlspecialchars($jobFile); ?></p>
                                        <p class="operator-task-meta-item"><?php echo htmlspecialchars($this->tr('operator.data_exchange.queue.rows', 'Rows: {rows}', ['rows' => (string)$jobRows])); ?></p>
                                        <p class="operator-task-meta-item"><?php echo htmlspecialchars($this->tr('operator.data_exchange.queue.status', 'Status: {status}', ['status' => $jobStatus])); ?></p>
                                        <?php if ($jobCreatedAt !== ''): ?>
                                            <p class="operator-task-meta-item"><?php echo htmlspecialchars($this->tr('operator.data_exchange.queue.created', 'Created: {created_at}', ['created_at' => $jobCreatedAt])); ?></p>
                                        <?php endif; ?>
                                        <?php if ($jobExecution !== []): ?>
                                            <p class="operator-task-meta-item"><?php echo htmlspecialchars($this->tr('operator.data_exchange.queue.execution', 'Execution inserted {inserted}, skipped {skipped}', [
                                                'inserted' => (string)((int)($jobExecution['inserted_rows'] ?? 0)),
                                                'skipped' => (string)((int)($jobExecution['skipped_rows'] ?? 0)),
                                            ])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($dxCanApprove && $jobStatus === 'pending'): ?>
                                        <div class="operator-task-card__actions">
                                            <form method="post" action="<?php echo htmlspecialchars($dxBase . '/job/approve'); ?>">
                                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
                                                <input type="hidden" name="job_id" value="<?php echo htmlspecialchars((string)$jobId); ?>">
                                                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($dxBase . '?adapter=' . rawurlencode($sAdapterKey) . '&intent=import'); ?>">
                                                <button type="submit" class="btn-sm btn-primary"><?php echo htmlspecialchars($this->tr('operator.data_exchange.queue.approve', 'Approve and Apply')); ?></button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($sSupportsExport): ?>
                    <div class="operator-task-card u-style-9374e84210">
                        <div class="operator-task-card__body">
                            <p class="operator-task-title"><?php echo htmlspecialchars($this->tr('operator.data_exchange.form.export_title', 'Download Export Template')); ?></p>
                            <p class="coverage-focus-caption"><?php echo htmlspecialchars($this->tr('operator.data_exchange.form.export_hint', 'Use this template to prepare governed bulk import/export files.')); ?></p>
                        </div>
                        <div class="operator-task-card__actions">
                            <a class="btn-sm" href="<?php echo htmlspecialchars($dxBase . '/export-template?adapter=' . rawurlencode($sAdapterKey)); ?>"><?php echo htmlspecialchars($this->tr('operator.data_exchange.form.export_submit', 'Download Template CSV')); ?></a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
