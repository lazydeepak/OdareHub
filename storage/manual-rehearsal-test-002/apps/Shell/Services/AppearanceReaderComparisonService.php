<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

/**
 * Shell-owned read-only appearance-reader comparison service.
 *
 * Compares an existing appearance reader's server-resolved output with
 * Shell's canonical AppearanceStateResolver server intent.
 *
 * This is a diagnostic-only parallel comparison. It does not change,
 * persist, write, or enforce appearance behavior. It does not claim
 * browser-effective state is visible to PHP.
 */
final class AppearanceReaderComparisonService
{
    private const CONTRACT_VERSION = '1.0';
    private const LEDGER_CONTRACT_VERSION = '1.0';

    private const AUTH_LEDGER_CASES = [
        'auth-default-system-liquid-glass' => ['label' => 'Auth default: system liquid glass', 'input_kind' => 'known_mode', 'input_mode' => 'system-liquid-glass'],
        'auth-default-system-paper' => ['label' => 'Auth default: system paper', 'input_kind' => 'known_mode', 'input_mode' => 'system-paper'],
        'auth-default-dark-liquid-glass' => ['label' => 'Auth default: dark liquid glass', 'input_kind' => 'known_mode', 'input_mode' => 'dark-liquid-glass'],
        'auth-default-dark-paper' => ['label' => 'Auth default: dark paper', 'input_kind' => 'known_mode', 'input_mode' => 'dark-paper'],
        'auth-default-light-liquid-glass' => ['label' => 'Auth default: light liquid glass', 'input_kind' => 'known_mode', 'input_mode' => 'light-liquid-glass'],
        'auth-default-light-paper' => ['label' => 'Auth default: light paper', 'input_kind' => 'known_mode', 'input_mode' => 'light-paper'],
        'auth-default-invalid-value' => ['label' => 'Auth default: invalid value', 'input_kind' => 'invalid', 'input_mode' => 'invalid-auth-default'],
        'auth-default-missing-value' => ['label' => 'Auth default: missing value', 'input_kind' => 'missing', 'input_mode' => null],
    ];

    /**
     * Compare the existing Auth server-render path with the Shell resolver
     * server intent.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function compareAuthServerIntent(array $context): array
    {
        $readerId = self::authReaderId();
        $authLegacyMode = (string)($context['auth_legacy_mode'] ?? '');
        $authResolutionSource = (string)($context['auth_resolution_source'] ?? 'defaultPreference');
        $systemDefault = (string)($context['system_default'] ?? '');
        $evidenceBasis = in_array((string)($context['evidence_basis'] ?? ''), ['source_contract', 'fixture', 'observed_server_render'], true)
            ? (string)$context['evidence_basis']
            : 'source_contract';

        $comparisonId = self::computeComparisonId($authLegacyMode, $systemDefault);

        $authModeValid = $authLegacyMode !== '' && AppearanceStateResolver::isValidLegacyMode($authLegacyMode);
        $authModeStatus = $authModeValid ? 'valid' : ($authLegacyMode !== '' ? 'invalid' : 'unknown');

        // Resolve the Shell server intent for the auth surface using the
        // same system default that auth_header.php would use.
        $shellInput = [
            'surface' => 'auth',
            'current_user_id_available' => false,
        ];
        if ($systemDefault !== '') {
            $shellInput['system_default_combined_mode'] = $systemDefault;
        }
        $shellResult = AppearanceStateResolver::resolve($shellInput);
        $shellIntent = $shellResult['server_resolved_intent'] ?? [];
        $shellMode = (string)($shellIntent['combined_mode'] ?? '');
        $shellSource = (string)($shellIntent['resolution_source'] ?? 'absolute_fallback');
        $shellIntentConfirmed = (string)($shellIntent['status'] ?? '') === 'confirmed'
            && !str_ends_with($shellSource, '_normalized');

        // Determine comparison status.
        $compStatus = 'unknown';
        $reason = '';
        $mismatchFields = [];

        if (!$authModeValid) {
            $reason = 'Auth server-default mode could not be established from the available source evidence';
        } elseif ($shellMode === '' || !$shellIntentConfirmed) {
            $reason = 'Shell server intent could not be resolved from the available source evidence';
        } else {
            $authNormalized = AppearanceStateResolver::normalizeLegacyMode($authLegacyMode);
            $shellNormalized = AppearanceStateResolver::normalizeLegacyMode($shellMode);
            if ($authNormalized === $shellNormalized) {
                $compStatus = 'aligned';
                $reason = 'The current Auth server-default mode matches Shell server intent. Browser runtime override is not included.';
            } else {
                $compStatus = 'mismatch';
                $reason = 'The current Auth server-default mode differs from Shell server intent. This is diagnostic only; Auth rendering is unchanged.';
                $mismatchFields = [
                    [
                        'field' => 'legacy_auth_mode',
                        'legacy_value' => $authNormalized,
                        'shell_value' => $shellNormalized,
                    ],
                ];
            }
        }

        return [
            'comparison_contract_version' => self::CONTRACT_VERSION,
            'comparison_id' => $comparisonId,
            'reader_id' => $readerId,
            'surface' => 'auth',
            'comparison_kind' => 'server_mode',
            'evidence_basis' => $evidenceBasis,
            'is_browser_observation' => false,
            'legacy_auth_mode' => $authLegacyMode,
            'legacy_auth_mode_status' => $authModeStatus,
            'shell_server_intent_mode' => $shellMode,
            'shell_resolution_source' => $shellSource,
            'status' => $compStatus,
            'reason' => $reason,
            'mismatch_fields' => $mismatchFields,
            'legacy_source_trace' => [
                [
                    'source_name' => $authResolutionSource,
                    'candidate_value' => $authLegacyMode,
                ],
            ],
            'shell_source_trace' => $shellResult['source_trace'] ?? [],
            'browser_override_included' => false,
            'browser_effective_state_claimed' => false,
            'diagnostic_only' => true,
            'cutover_status' => 'not_ready',
            'cutover_blockers' => [
                'browser runtime override alters effective state after server render',
                'auth_server_header is a parallel-comparison candidate only',
            ],
        ];
    }

    /**
     * Build a compact read-only Auth parity evidence ledger.
     *
     * @param array<string,array<string,mixed>> $cases
     * @return array<int,array<string,mixed>>
     */
    public static function authParityEvidenceLedger(array $cases = []): array
    {
        $definitions = self::normalizeLedgerCases($cases);
        $rows = [];

        foreach ($definitions as $caseId => $case) {
            $inputKind = (string)($case['input_kind'] ?? 'missing');
            $inputMode = isset($case['input_mode']) ? (string)$case['input_mode'] : null;
            $inputModeForComparison = $inputMode ?? '';

            $authStatus = self::authSourceContractStatus($inputKind, $inputMode);
            $authMode = $authStatus === 'valid' ? $inputModeForComparison : null;
            $evidenceBasis = $inputKind === 'known_mode' ? 'fixture_source_contract' : 'source_contract';
            $sourceEvidenceStatus = $inputKind === 'known_mode' ? 'confirmed' : 'unknown';
            $fallbackEvidence = self::fallbackEvidence($inputKind);

            $comparison = self::compareAuthServerIntent([
                'auth_legacy_mode' => $inputModeForComparison,
                'auth_resolution_source' => 'public/views/layouts/auth_header.php::ThemePreferenceService::defaultPreference()',
                'system_default' => $inputModeForComparison,
                'evidence_basis' => 'source_contract',
            ]);

            $rows[] = [
                'ledger_contract_version' => self::LEDGER_CONTRACT_VERSION,
                'case_id' => (string)$caseId,
                'label' => (string)($case['label'] ?? $caseId),
                'reader_id' => 'auth_server_header',
                'surface' => 'auth',
                'comparison_kind' => 'server_mode',
                'input_kind' => $inputKind,
                'input_mode' => $inputMode,
                'auth_source_contract_mode' => $authMode,
                'auth_source_contract_status' => $authStatus,
                'shell_server_intent_mode' => (string)($comparison['shell_server_intent_mode'] ?? ''),
                'shell_resolution_source' => (string)($comparison['shell_resolution_source'] ?? ''),
                'status' => (string)($comparison['status'] ?? 'unknown'),
                'reason' => self::ledgerReason($inputKind, $comparison),
                'evidence_basis' => $evidenceBasis,
                'source_evidence_status' => $sourceEvidenceStatus,
                'rendered_auth_response_observed' => false,
                'browser_override_included' => false,
                'browser_effective_state_claimed' => false,
                'fallback_evidence' => $fallbackEvidence,
                'comparison_id' => self::ledgerComparisonId((string)$caseId, $inputMode),
                'diagnostic_only' => true,
                'cutover_status' => 'not_ready',
                'cutover_blockers' => (array)($comparison['cutover_blockers'] ?? []),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $ledger
     * @return array<string,mixed>
     */
    public static function summarizeAuthParityEvidenceLedger(array $ledger): array
    {
        $basis = [];
        $summary = [
            'ledger_case_count' => count($ledger),
            'ledger_aligned_count' => 0,
            'ledger_mismatch_count' => 0,
            'ledger_unknown_count' => 0,
            'ledger_observed_render_count' => 0,
            'ledger_evidence_basis' => '',
        ];

        foreach ($ledger as $row) {
            $status = (string)($row['status'] ?? 'unknown');
            if ($status === 'aligned') {
                $summary['ledger_aligned_count']++;
            } elseif ($status === 'mismatch') {
                $summary['ledger_mismatch_count']++;
            } else {
                $summary['ledger_unknown_count']++;
            }
            if (!empty($row['rendered_auth_response_observed'])) {
                $summary['ledger_observed_render_count']++;
            }
            $rowBasis = (string)($row['evidence_basis'] ?? '');
            if ($rowBasis !== '' && !in_array($rowBasis, $basis, true)) {
                $basis[] = $rowBasis;
            }
        }

        $summary['ledger_evidence_basis'] = implode('|', $basis);
        return $summary;
    }

    /**
     * Compute a stable deterministic comparison identifier.
     */
    private static function computeComparisonId(string $legacyMode, string $systemDefault): string
    {
        $raw = 'auth-server-default'
            . ':' . ($legacyMode !== '' ? $legacyMode : 'absent')
            . ':' . ($systemDefault !== '' ? $systemDefault : 'absent');
        return 'auth-cmp-' . substr(md5($raw), 0, 12);
    }

    /**
     * @param array<string,array<string,mixed>> $cases
     * @return array<string,array<string,mixed>>
     */
    private static function normalizeLedgerCases(array $cases): array
    {
        if ($cases === []) {
            return self::AUTH_LEDGER_CASES;
        }

        $normalized = self::AUTH_LEDGER_CASES;
        foreach ($cases as $caseId => $case) {
            if (!is_string($caseId) || !isset($normalized[$caseId]) || !is_array($case)) {
                continue;
            }
            $normalized[$caseId] = array_merge($normalized[$caseId], $case);
        }
        return $normalized;
    }

    private static function authSourceContractStatus(string $inputKind, ?string $inputMode): string
    {
        if ($inputKind === 'known_mode' && $inputMode !== null && AppearanceStateResolver::isValidLegacyMode($inputMode)) {
            return 'valid';
        }
        if ($inputKind === 'invalid') {
            return 'invalid';
        }
        return 'unknown';
    }

    /**
     * @return array{status:string,reason:string}
     */
    private static function fallbackEvidence(string $inputKind): array
    {
        if ($inputKind === 'known_mode') {
            return [
                'status' => 'not_applicable',
                'reason' => 'Known valid mode requires no fallback evidence.',
            ];
        }
        if ($inputKind === 'invalid') {
            return [
                'status' => 'unknown',
                'reason' => 'The fixture proves invalid input handling stays diagnostic; it does not observe a live Auth default fallback.',
            ];
        }
        return [
            'status' => 'unknown',
            'reason' => 'The fixture proves missing input remains diagnostic; it does not observe a live Auth default fallback.',
        ];
    }

    /**
     * @param array<string,mixed> $comparison
     */
    private static function ledgerReason(string $inputKind, array $comparison): string
    {
        if ($inputKind === 'known_mode') {
            return (string)($comparison['reason'] ?? 'Known Auth source-contract fixture compared with Shell server intent.');
        }
        return 'Auth server-default parity could not be established safely from this source-contract fixture; no rendered Auth response was observed.';
    }

    private static function ledgerComparisonId(string $caseId, ?string $inputMode): string
    {
        return 'auth-ledger-' . substr(md5($caseId . ':' . ($inputMode ?? 'missing')), 0, 12);
    }

    private static function authReaderId(): string
    {
        foreach (AppearanceReaderInventoryService::inventory() as $reader) {
            if (($reader['reader_id'] ?? '') === 'auth_server_header') {
                return 'auth_server_header';
            }
        }
        return 'auth_server_header';
    }
}
