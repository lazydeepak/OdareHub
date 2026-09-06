<?php
declare(strict_types=1);

namespace Platform\Engineering;

final class EngineeringWorkspaceAgentDispatcher
{
    private const MODE_IMPL = 'implementation';
    private const MODE_READ = 'read_only';
    private const SCOPE_OWNER = 'owner';
    private const SCOPE_PLATFORM = 'platform';

    private const STATE_IMPL_READY = 'implementation_ready';
    private const STATE_IMPL_DEGRADED = 'implementation_degraded';
    private const STATE_BLOCKED = 'blocked';

    public static function dispatch(
        string $taskText,
        ?string $canonicalOwnerKey,
        array $sourcePathHints,
        ?array $actor,
        string $requestedMode = self::MODE_READ,
        string $scopeMode = self::SCOPE_OWNER,
        ?callable $executor = null
    ): array {
        $executionPacket = EngineeringWorkspaceAgentExecutionGate::prepareExecution(
            $taskText,
            $canonicalOwnerKey,
            $sourcePathHints,
            $actor,
            $requestedMode,
            $scopeMode
        );

        $executionCalled = false;
        $executionResult = null;

        $executionState = $executionPacket['execution_state'] ?? '';
        $writePermitted = $executionPacket['write_permitted'] ?? false;

        if (
            $executor !== null
            && $writePermitted === true
            && $executionState !== self::STATE_BLOCKED
        ) {
            $executionCalled = true;
            $executionResult = $executor($executionPacket);
        }

        self::auditDispatch($executionPacket, $executionCalled);

        return [
            'execution_packet' => $executionPacket,
            'execution_called' => $executionCalled,
            'execution_result' => $executionResult,
            'ok' => $writePermitted === true && $executionState !== self::STATE_BLOCKED,
        ];
    }

    private static function auditDispatch(array $executionPacket, bool $executionCalled): void
    {
        $log = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'event' => 'dispatch',
            'execution_state' => $executionPacket['execution_state'] ?? 'unknown',
            'write_permitted' => $executionPacket['write_permitted'] ?? false,
            'executor_called' => $executionCalled,
            'resolved_workspace_key' => $executionPacket['resolved_workspace_key'] ?? '',
        ];
        $line = '[ENG_WS_DISPATCH] ' . json_encode($log);
        @error_log($line, 0);
    }
}
