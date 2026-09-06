<?php
declare(strict_types=1);

namespace Platform\Style;

use Platform\Style\Contracts\ApprovedStyleReaderContract;

/**
 * Read-only placeholder for the future runtime style consumption layer.
 *
 * Ownership boundaries enforced by this placeholder:
 *   - Themes own theme values (resources/themes/**)
 *   - Shell owns Shell CSS (apps/Shell/styles/**)
 *   - Apps own app CSS (apps/{App}/styles/**)
 *   - Platform owns runtime consumption (platform/Style/**)
 *   - Studio owns governance (apps/Studio/**)
 *
 * This placeholder formalizes the future API shape and ownership boundary.
 * It must not perform runtime style consumption, apply approved values,
 * mutate Shell styling, modify themes, or change runtime behavior.
 *
 * Relationship to other components:
 *   - Read-Only Consumption Probe (scripts/platform/probe_resolved_style_consumer.php):
 *     The probe validates registry and catalog readiness for this consumer.
 *     This placeholder must not call, execute, or depend on the probe.
 *   - Platform Style Registry (apps/Platform/StyleRegistry/):
 *     Source of approved style values for diagnostic-only read access
 *     via ApprovedStyleReaderContract. This placeholder must not write
 *     registry values.
 *   - Shell Style socket catalog (apps/Shell/Style/Resources/socket-catalog/):
 *     Defines available style sockets. This placeholder must not read
 *     Shell CSS or modify selectors.
 *
 * @see docs/architecture/resolved-style-consumer-contract.md
 * @see docs/architecture/read-only-consumption-probe-contract.md
 */
final class ResolvedStyleConsumer
{
    /** Current contract version for diagnostic reporting. */
    private const CONTRACT_VERSION = '1.0.0';

    private bool $runtimeConsumptionEnabled = false;

    private ?ApprovedStyleReaderContract $reader = null;

    /**
     * Diagnostic-only reader injection.
     *
     * The reader is optional (nullable). When absent, diagnostics report
     * RSC-C004 (reader unavailable). When present, diagnostics check
     * reachability and socket values without enabling runtime consumption.
     *
     * @param ApprovedStyleReaderContract|null $reader Optional read-only registry bridge.
     */
    public function __construct(?ApprovedStyleReaderContract $reader = null)
    {
        $this->reader = $reader;
    }

    /**
     * Return full diagnostics snapshot for the consumer placeholder.
     *
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        $diagnostics = [
            [
                'code' => 'RSC-PLACEHOLDER-001',
                'severity' => 'INFO',
                'message' => 'ResolvedStyleConsumer is a read-only placeholder. Runtime consumption not enabled.',
            ],
        ];

        if ($this->reader !== null) {
            $diagnostics[] = [
                'code' => 'RSC-C005',
                'severity' => 'INFO',
                'message' => 'Reader constrained to diagnostics-only. No runtime consumption enabled.',
            ];

            $reachable = false;
            try {
                $reachable = $this->reader->isReachable();
            } catch (\Throwable) {
                $reachable = false;
            }

            if ($reachable) {
                $diagnostics[] = [
                    'code' => 'RSC-C001',
                    'severity' => 'INFO',
                    'message' => 'Style Registry reader is reachable.',
                ];

                try {
                    $value = $this->reader->readValue('radius.scale');
                    if ($value !== null) {
                        $diagnostics[] = [
                            'code' => 'RSC-C002',
                            'severity' => 'INFO',
                            'message' => "Approved radius.scale value: {$value}",
                        ];
                    } else {
                        $diagnostics[] = [
                            'code' => 'RSC-C003',
                            'severity' => 'WARN',
                            'message' => 'No approved radius.scale value found in registry.',
                        ];
                    }
                } catch (\Throwable) {
                    $diagnostics[] = [
                        'code' => 'RSC-C003',
                        'severity' => 'WARN',
                        'message' => 'Failed to read radius.scale value from registry.',
                    ];
                }
            } else {
                $diagnostics[] = [
                    'code' => 'RSC-C004',
                    'severity' => 'WARN',
                    'message' => 'Style Registry reader is not reachable.',
                ];
            }
        } else {
            $diagnostics[] = [
                'code' => 'RSC-C004',
                'severity' => 'WARN',
                'message' => 'No ApprovedStyleReaderContract injected. Registry diagnostics unavailable.',
            ];
        }

        return [
            'consumer_status' => 'placeholder',
            'contract_version' => self::CONTRACT_VERSION,
            'ownership_verified' => true,
            'runtime_consumption_enabled' => $this->runtimeConsumptionEnabled,
            'diagnostics' => $diagnostics,
            'supported_inputs' => [
                'socket_id' => 'string — e.g. radius.scale',
                'fallback_default' => 'string — optional override for default fallback value',
            ],
            'supported_outputs' => [
                'socket_id' => 'string — the requested socket identifier',
                'approved_value' => 'string|null — the approved value from registry (future)',
                'effective_value' => 'string — the value to consume (approved value or fallback)',
                'source' => 'string — registry or fallback',
                'error' => 'string|null — resolution error if any',
            ],
            'future_capabilities' => [
                'approved_style_resolution' => 'Resolve approved style values from Platform Style Registry',
                'socket_catalog_alignment' => 'Validate socket key against Shell socket catalog',
                'fallback_chain' => 'Apply fallback chain: approved value → catalog default → provided default',
                'diagnostic_reporting' => 'Emit structured diagnostics for each resolution attempt',
            ],
            'boundary_limits' => [
                'No Studio imports' => true,
                'No Shell imports' => true,
                'No route registration' => true,
                'No filesystem writes' => true,
                'No DB connections' => true,
                'No HTTP calls' => true,
                'No exec/shell_exec/proc_open/system' => true,
                'No theme compiler calls' => true,
                'No registry value writes' => true,
                'No Shell CSS reads or modifications' => true,
                'No probe dependency' => true,
                'Registry reads via ApprovedStyleReaderContract only' => true,
            ],
        ];
    }

    /**
     * Return the contract version identifier.
     */
    public function contractVersion(): string
    {
        return self::CONTRACT_VERSION;
    }

    /**
     * Indicate whether runtime consumption is enabled.
     * Currently returns false. Future implementations will return
     * true only when the full style resolution pipeline is wired.
     */
    public function isRuntimeConsumptionEnabled(): bool
    {
        return $this->runtimeConsumptionEnabled;
    }

    /**
     * Return the current placeholder status.
     */
    public function placeholderStatus(): string
    {
        return 'placeholder';
    }
}
