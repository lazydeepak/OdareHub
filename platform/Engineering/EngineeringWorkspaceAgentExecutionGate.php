<?php
declare(strict_types=1);

namespace Platform\Engineering;

final class EngineeringWorkspaceAgentExecutionGate
{
    private const MODE_IMPL = 'implementation';
    private const MODE_READ = 'read_only';

    private const SCOPE_OWNER = 'owner';
    private const SCOPE_PLATFORM = 'platform';

    private const STATE_IMPL_READY = 'implementation_ready';
    private const STATE_IMPL_DEGRADED = 'implementation_degraded';
    private const STATE_READ_ONLY = 'read_only_only';
    private const STATE_BLOCKED = 'blocked';

    public static function prepareExecution(
        string $taskText,
        ?string $canonicalOwnerKey,
        array $sourcePathHints,
        ?array $actor,
        string $requestedMode = self::MODE_READ,
        string $scopeMode = self::SCOPE_OWNER
    ): array {
        $requestedMode = $requestedMode === self::MODE_IMPL ? self::MODE_IMPL : self::MODE_READ;
        $scopeMode = $scopeMode === self::SCOPE_PLATFORM ? self::SCOPE_PLATFORM : self::SCOPE_OWNER;

        $bootstrapResult = [];
        $bootstrapCalled = false;

        if ($requestedMode === self::MODE_IMPL) {
            $bootstrapResult = EngineeringWorkspaceAgentBootstrap::prepareImplementationContext(
                $taskText,
                $canonicalOwnerKey,
                $sourcePathHints,
                $actor
            );
            $bootstrapCalled = true;
        } elseif ($canonicalOwnerKey !== null && $canonicalOwnerKey !== '') {
            $bootstrapResult = EngineeringWorkspaceAgentBootstrap::prepareImplementationContext(
                $taskText,
                $canonicalOwnerKey,
                $sourcePathHints,
                $actor
            );
            $bootstrapCalled = true;
        }

        $decision = self::applyDecisionTable(
            $bootstrapResult,
            $requestedMode,
            $scopeMode
        );

        $executionState = $decision['execution_state'];
        $writePermitted = $decision['write_permitted'];
        $blockReason = $decision['block_reason'];

        $workspaceContextPackage = [];
        if ($executionState === self::STATE_IMPL_READY || $executionState === self::STATE_IMPL_DEGRADED) {
            $workspaceContextPackage = self::buildWorkspacePackage($bootstrapResult);
        } elseif ($executionState === self::STATE_READ_ONLY && !empty($bootstrapResult)) {
            if (($bootstrapResult['workspace_context_state'] ?? '') === 'ready' ||
                ($bootstrapResult['workspace_context_state'] ?? '') === 'degraded') {
                $workspaceContextPackage = self::buildWorkspacePackage($bootstrapResult);
            }
        }

        $instructionPrefix = self::buildInstructionPrefix(
            $executionState,
            $bootstrapResult['workspace_key'] ?? '',
            $blockReason
        );

        $resolvedWorkspaceKey = $bootstrapResult['workspace_key'] ?? '';
        $resolutionSource = $bootstrapResult['resolution_source'] ?? '';
        $bootstrapState = $bootstrapResult['workspace_context_state'] ?? '';

        self::audit([
            'actor' => $actor,
            'requested_mode' => $requestedMode,
            'scope_mode' => $scopeMode,
            'bootstrap_state' => $bootstrapState,
            'execution_state' => $executionState,
            'resolved_workspace_key' => $resolvedWorkspaceKey,
            'resolution_source' => $resolutionSource,
            'write_permitted' => $writePermitted,
        ]);

        return [
            'task_text' => $taskText,
            'requested_mode' => $requestedMode,
            'scope_mode' => $scopeMode,
            'bootstrap_result' => $bootstrapResult,
            'bootstrap_called' => $bootstrapCalled,
            'execution_state' => $executionState,
            'write_permitted' => $writePermitted,
            'block_reason' => $blockReason,
            'workspace_context_package' => $workspaceContextPackage,
            'agent_instruction_prefix' => $instructionPrefix,
            'resolved_workspace_key' => $resolvedWorkspaceKey,
            'resolution_source' => $resolutionSource,
        ];
    }

    private static function applyDecisionTable(
        array $bootstrapResult,
        string $requestedMode,
        string $scopeMode
    ): array {
        if ($requestedMode !== self::MODE_IMPL) {
            return [
                'execution_state' => self::STATE_READ_ONLY,
                'write_permitted' => false,
                'block_reason' => null,
            ];
        }

        $bootstrapState = $bootstrapResult['workspace_context_state'] ?? '';

        if ($bootstrapState === 'resolution_required') {
            return [
                'execution_state' => self::STATE_BLOCKED,
                'write_permitted' => false,
                'block_reason' => 'Workspace resolution is required but no matching workspace was found. Provide an explicit owner key or workspace hint.',
            ];
        }

        if ($bootstrapState === 'rejected') {
            $error = $bootstrapResult['error'] ?? 'Owner key was rejected by the preflight safety check';
            return [
                'execution_state' => self::STATE_BLOCKED,
                'write_permitted' => false,
                'block_reason' => $error,
            ];
        }

        if ($bootstrapState === 'no_workspace_context') {
            return [
                'execution_state' => self::STATE_BLOCKED,
                'write_permitted' => false,
                'block_reason' => 'No Engineering Workspace contract was resolved. A workspace contract is required before implementation work can proceed.',
            ];
        }

        if ($bootstrapState === 'ready') {
            return [
                'execution_state' => self::STATE_IMPL_READY,
                'write_permitted' => true,
                'block_reason' => null,
            ];
        }

        if ($bootstrapState === 'degraded') {
            return [
                'execution_state' => self::STATE_IMPL_DEGRADED,
                'write_permitted' => true,
                'block_reason' => null,
            ];
        }

        return [
            'execution_state' => self::STATE_BLOCKED,
            'write_permitted' => false,
            'block_reason' => 'Unexpected bootstrap state: ' . $bootstrapState,
        ];
    }

    private static function buildWorkspacePackage(array $bootstrapResult): array
    {
        return [
            'workspace_key' => $bootstrapResult['workspace_key'] ?? '',
            'resolution_source' => $bootstrapResult['resolution_source'] ?? '',
            'workspace_context_state' => $bootstrapResult['workspace_context_state'] ?? '',
            'documents' => $bootstrapResult['documents'] ?? [],
            'created_missing_documents' => $bootstrapResult['created_missing_documents'] ?? [],
            'repair_required_documents' => $bootstrapResult['repair_required_documents'] ?? [],
            'instruction' => $bootstrapResult['instruction'] ?? '',
        ];
    }

    private static function buildInstructionPrefix(
        string $executionState,
        string $workspaceKey,
        ?string $blockReason
    ): string {
        if ($executionState === self::STATE_BLOCKED) {
            return 'Execution is blocked. Reason: ' . ($blockReason ?? 'Unknown');
        }

        if ($executionState === self::STATE_READ_ONLY) {
            return 'This execution gate is active. You are in read-only mode. Resolve and use the Engineering Workspace contract when available, then inspect repository truth and report findings only. Do not write, modify, or mutate any file.';
        }

        if ($executionState === self::STATE_IMPL_DEGRADED) {
            $repairDocs = 'degraded workspace documents';
            return 'This execution gate is active. You are working within workspace \'' . $workspaceKey . '\' in implementation mode with ' . $repairDocs . '. First use the loaded Engineering Workspace contract as intent and governance truth, then inspect repository truth and compare intent vs reality. You are authorized to write within the resolved workspace scope. Repair workspace documents before or during your work. Do not modify files outside the resolved workspace scope.';
        }

        if ($workspaceKey !== '') {
            return 'This execution gate is active. You are working within workspace \'' . $workspaceKey . '\' in implementation mode. First use the loaded Engineering Workspace contract as intent and governance truth, then inspect repository truth and compare intent vs reality. You are authorized to write within the resolved workspace scope. Do not modify files outside the resolved workspace scope.';
        }

        return 'This execution gate is active, but no Engineering Workspace contract was resolved. Implementation must remain blocked until a workspace contract is resolved.';
    }

    private static function audit(array $event): void
    {
        $log = array_merge(
            ['timestamp' => gmdate('Y-m-d\TH:i:s\Z')],
            $event
        );
        $line = '[ENG_WS_GATE] ' . json_encode($log);
        @error_log($line, 0);
    }
}
