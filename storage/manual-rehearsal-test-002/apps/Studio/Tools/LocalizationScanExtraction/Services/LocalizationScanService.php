<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationScanExtraction\Services;

use Apps\Studio\Tools\LocalizationStudio\Services\LocalizationStudioEditService;

require_once __DIR__ . '/SuggestionQualityGateService.php';

final class LocalizationScanService
{
    private const SUPPORTED_LOCALES = ['en', 'ja', 'ne'];

    private const SUPPORTED_SCOPES = ['views', 'views_controllers', 'full'];

    private const SHARED_GLOBAL_PREFIXES = [
        'common.', 'global.', 'shared.', 'shell.', 'platform.', 'studio.', 'nav.', 'ops.', 'display.', 'account.', 'identity.',
        'validation.', 'error.', 'success.', 'warning.', 'info.',
        'btn.', 'label.', 'title.', 'header.', 'footer.', 'menu.',
        'form.', 'field.', 'filter.', 'search.', 'sort.', 'page.',
    ];

    private const TECHNICAL_PATTERNS = [
        '/^[A-Z][A-Za-z0-9]+::class$/',
        '/^[A-Z][A-Za-z0-9]+::[A-Z_]+$/',
        '/^[A-Z_]+$/',
        '/^[a-z0-9._-]+$/',
        '/^[a-z]+_[a-z_]+$/',
        '/^[a-z]+-[a-z-]+$/',
        '/^[A-Z][a-z]+\.[A-Z][a-z]+/',
        '/^[a-z]+\/[a-z]+\/[a-z]+/',
        '/^[a-z]+\.[a-z]+\.[a-z]+/',
    ];

    private const INTERNAL_FILE_PATTERNS = [
        '/Services\//', '/Handlers\//', '/Providers\//', '/Repositories\//',
        '/Helpers\//', '/Utilities\//', '/Traits\//', '/Middlewares\//',
        '/Commands\//', '/Console\//', '/Exceptions\//', '/Events\//',
        '/Listeners\//', '/Jobs\//', '/Notifications\//', '/Mail\//',
    ];

    private const SEMANTIC_CATEGORIES = [
        'navigation' => ['menu', 'nav', 'breadcrumb', 'tab', 'link', 'back', 'forward', 'next', 'previous'],
        'action' => ['submit', 'save', 'delete', 'edit', 'create', 'add', 'remove', 'cancel', 'confirm', 'apply', 'approve', 'reject', 'export', 'import', 'upload', 'download', 'print', 'search', 'filter', 'clear', 'reset', 'close', 'open'],
        'heading' => ['title', 'header', 'heading', 'h1', 'h2', 'h3', 'report', 'snapshot', 'summary', 'dashboard'],
        'label' => ['label', 'field', 'column', 'attribute'],
        'status' => ['status', 'state', 'phase', 'stage', 'progress', 'complete', 'pending', 'active', 'inactive', 'enabled', 'disabled'],
        'metric' => ['count', 'total', 'sum', 'avg', 'average', 'percentage', 'rate', 'score', 'kpi'],
        'help' => ['help', 'tooltip', 'hint', 'info', 'description', 'instruction', 'guide'],
        'placeholder' => ['placeholder', 'example'],
        'confirmation' => ['confirm', 'warning', 'alert'],
        'error_message' => ['error', 'failure', 'failed', 'invalid'],
    ];

    private const ELEMENT_TAG_PATTERNS = [
        '/<h[1-6][^>]*>/i' => 'heading',
        '/<button[^>]*>/i' => 'button',
        '/<a[^>]*>/i' => 'link',
        '/<input[^>]*>/i' => 'input',
        '/<label[^>]*>/i' => 'label',
        '/<select[^>]*>/i' => 'select',
        '/<textarea[^>]*>/i' => 'textarea',
        '/<th[^>]*>/i' => 'table_header',
        '/<td[^>]*>/i' => 'table_cell',
        '/<p[^>]*>/i' => 'paragraph',
        '/<span[^>]*>/i' => 'span',
        '/<div[^>]*>/i' => 'div',
        '/<li[^>]*>/i' => 'list_item',
        '/<option[^>]*>/i' => 'option',
        '/<img[^>]*>/i' => 'image',
        '/<nav[^>]*>/i' => 'navigation',
        '/<header[^>]*>/i' => 'header',
        '/<footer[^>]*>/i' => 'footer',
        '/<main[^>]*>/i' => 'main',
        '/<section[^>]*>/i' => 'section',
    ];

    public static function scan(string $ownerKey, string $scope, string $referenceLocale): array
    {
        if (!in_array($scope, self::SUPPORTED_SCOPES, true)) {
            $scope = 'views';
        }
        if (!in_array($referenceLocale, self::SUPPORTED_LOCALES, true)) {
            $referenceLocale = 'en';
        }

        $basePath = self::resolveOwnerPath($ownerKey);
        if ($basePath === null || !is_dir($basePath)) {
            return self::emptyResult($ownerKey, $scope, 'Owner path not found: ' . ($basePath ?? 'null'));
        }

        $files = self::findScopeFiles($basePath, $scope);
        $definedKeys = self::readDefinedKeys($ownerKey, $referenceLocale);
        $definedKeysFlat = [];
        foreach ($definedKeys as $k => $v) {
            $definedKeysFlat[$k] = $v;
        }

        $findings = [];
        $keyUsages = [];
        $inlineCandidates = [];
        $usedKeys = [];
        $totalLines = 0;
        // Phase 1: Collect raw translation usages and inline text candidates per file
        $rawKeyUsages = [];
        $rawInlineCandidates = [];

        foreach ($files as $filePath) {
            $relative = str_replace(APP_ROOT . '/', '', $filePath);
            $content = @file_get_contents($filePath);
            if ($content === false) {
                continue;
            }
            $lines = explode("\n", $content);
            $totalLines += count($lines);

            $inBlockComment = false;

            foreach ($lines as $lineNum => $rawLine) {
                $line = $rawLine;
                $lineIdx = $lineNum + 1;

                if ($inBlockComment) {
                    if (str_contains($line, '*/')) {
                        $inBlockComment = false;
                    }
                    continue;
                }
                if (self::isBlockCommentStart($line)) {
                    if (!str_contains($line, '*/')) {
                        $inBlockComment = true;
                    }
                    continue;
                }
                if (self::isCommentOrDebugLine($line)) {
                    continue;
                }

                $context = self::sourceContext($lines, $lineIdx);

                $usages = self::findTranslationUsage($line, $relative, $lineIdx, $ownerKey);
                foreach ($usages as $u) {
                    $u['context'] = $context;
                    $rawKeyUsages[] = $u;
                    if (!empty($u['detected'])) {
                        $usedKeys[$u['detected']] = true;
                    }
                }

                $candidates = self::findInlineTextCandidates($line, $relative, $lineIdx, $ownerKey);
                foreach ($candidates as $c) {
                    $c['context'] = $context;
                    $rawInlineCandidates[] = $c;
                }
            }
        }

        $keyFindings = self::reconcileKeyUsages($rawKeyUsages, $definedKeysFlat, $ownerKey);
        foreach ($keyFindings as $u) {
            $findings[] = $u;
            $keyUsages[] = $u;
        }

        // Phase 3: Classify inline text candidates
        foreach ($rawInlineCandidates as $c) {
            $classified = self::classifyInlineTextCandidate($c, $c['file'] ?? '');
            $findings[] = $classified;
            $inlineCandidates[] = $classified;
        }

        self::annotateOccurrences($findings);
        self::markSuggestedKeyDuplicates($findings);
        self::markSuggestedKeyDuplicates($inlineCandidates);

        // Phase 4: Find unused keys (defined in locale but never seen in source)
        $unusedFindings = [];
        foreach ($definedKeysFlat as $key => $value) {
            if (!isset($usedKeys[$key])) {
                $f = [
                    'type' => 'unused_key',
                    'category' => 'possibly_unused_key',
                    'owner' => $ownerKey,
                    'file' => '(locale file)',
                    'line' => 0,
                    'detected' => $key,
                    'suggested_key' => '',
                    'confidence' => 'medium',
                    'status' => 'unused',
                    'context' => 'Defined as: ' . (is_string($value) ? $value : json_encode($value)),
                    'extractable' => false,
                    'reason' => 'Static scan only detects direct tr()/t() calls. Indirect, runtime, or metadata key resolution may not be visible to this scan.',
                ];
                $findings[] = $f;
                $unusedFindings[] = $f;
            }
        }

        self::applyHandoffMetadata($findings, $ownerKey, $referenceLocale, $scope);
        $missingKeyReviewPlan = self::buildMissingKeyReviewPlan($findings, $definedKeysFlat);
        $summary = self::computeCategorizedSummary($findings);

        return [
            'scanned_at' => date('Y-m-d H:i:s'),
            'owner_key' => $ownerKey,
            'scope' => $scope,
            'scanned_files' => count($files),
            'total_lines' => $totalLines,
            'scan_ok' => true,
            'error' => null,
            'summary' => $summary,
            'findings' => $findings,
            'inline_text_candidates' => $inlineCandidates,
            'loc_key_usages' => $keyUsages,
            'missing_keys' => [],
            'missing_key_review_plan' => $missingKeyReviewPlan,
            'unused_keys' => $unusedFindings,
        ];
    }

    public static function scanFixture(string $ownerKey, array $referenceKeys, array $files): array
    {
        $definedKeysFlat = [];
        foreach ($referenceKeys as $key => $value) {
            if (is_int($key)) {
                $definedKeysFlat[(string)$value] = (string)$value;
            } else {
                $definedKeysFlat[(string)$key] = $value;
            }
        }

        $findings = [];
        $keyUsages = [];
        $inlineCandidates = [];
        $usedKeys = [];
        $totalLines = 0;
        $rawKeyUsages = [];
        $rawInlineCandidates = [];

        foreach ($files as $relative => $content) {
            $lines = explode("\n", (string)$content);
            $totalLines += count($lines);

            foreach ($lines as $lineNum => $line) {
                $lineIdx = $lineNum + 1;
                if (self::isCommentOrDebugLine($line)) {
                    continue;
                }

                $context = self::sourceContext($lines, $lineIdx);
                foreach (self::findTranslationUsage($line, (string)$relative, $lineIdx, $ownerKey) as $u) {
                    $u['context'] = $context;
                    $rawKeyUsages[] = $u;
                    if (!empty($u['detected'])) {
                        $usedKeys[$u['detected']] = true;
                    }
                }
                foreach (self::findInlineTextCandidates($line, (string)$relative, $lineIdx, $ownerKey) as $c) {
                    $c['context'] = $context;
                    $rawInlineCandidates[] = $c;
                }
            }
        }

        foreach (self::reconcileKeyUsages($rawKeyUsages, $definedKeysFlat, $ownerKey) as $u) {
            $findings[] = $u;
            $keyUsages[] = $u;
        }

        foreach ($rawInlineCandidates as $c) {
            $classified = self::classifyInlineTextCandidate($c, $c['file'] ?? '');
            $findings[] = $classified;
            $inlineCandidates[] = $classified;
        }

        self::annotateOccurrences($findings);
        self::markSuggestedKeyDuplicates($findings);
        self::markSuggestedKeyDuplicates($inlineCandidates);

        $unusedFindings = [];
        foreach ($definedKeysFlat as $key => $value) {
            if (!isset($usedKeys[$key])) {
                $f = [
                    'type' => 'unused_key',
                    'category' => 'possibly_unused_key',
                    'owner' => $ownerKey,
                    'file' => '(locale file)',
                    'line' => 0,
                    'detected' => $key,
                    'suggested_key' => '',
                    'confidence' => 'medium',
                    'status' => 'unused',
                    'context' => 'Defined as: ' . (is_string($value) ? $value : json_encode($value)),
                    'extractable' => false,
                    'reason' => 'Static scan only detects direct translation helper calls. Indirect, runtime, or metadata key resolution may not be visible to this scan.',
                ];
                $findings[] = $f;
                $unusedFindings[] = $f;
            }
        }

        self::applyHandoffMetadata($findings, $ownerKey, 'en', 'views');
        $missingKeyReviewPlan = self::buildMissingKeyReviewPlan($findings, $definedKeysFlat);

        return [
            'scanned_at' => date('Y-m-d H:i:s'),
            'owner_key' => $ownerKey,
            'scope' => 'fixture',
            'scanned_files' => count($files),
            'total_lines' => $totalLines,
            'scan_ok' => true,
            'error' => null,
            'summary' => self::computeCategorizedSummary($findings),
            'findings' => $findings,
            'inline_text_candidates' => $inlineCandidates,
            'loc_key_usages' => $keyUsages,
            'missing_keys' => [],
            'missing_key_review_plan' => $missingKeyReviewPlan,
            'unused_keys' => $unusedFindings,
        ];
    }

    public static function buildEditorHandoffUrl(string $ownerKey, string $locale, string $key, string $returnTo): string
    {
        $safeReturnTo = self::safeInternalReturnTo($returnTo);
        return '/apps/studio/tools/localization-studio/edit'
            . '?owner=' . rawurlencode($ownerKey)
            . '&locale=' . rawurlencode($locale)
            . '&key=' . rawurlencode($key)
            . '&return_to=' . rawurlencode($safeReturnTo);
    }

    public static function safeInternalReturnTo(string $returnTo): string
    {
        $returnTo = trim($returnTo);
        if ($returnTo === ''
            || !str_starts_with($returnTo, '/')
            || str_starts_with($returnTo, '//')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $returnTo) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $returnTo) === 1) {
            return '/apps/studio/tools/localization-scan-extraction';
        }

        return $returnTo;
    }

    private static function applyHandoffMetadata(array &$findings, string $ownerKey, string $locale, string $scope): void
    {
        $returnTo = self::scanReturnPath($ownerKey, $scope, $locale);
        foreach ($findings as &$finding) {
            $finding['action_kind'] = 'view_details';
            $finding['action_label'] = 'View details';

            if (($finding['category'] ?? '') !== 'missing_owner_key') {
                continue;
            }

            $key = (string)($finding['detected'] ?? '');
            $finding['action_kind'] = 'open_localization_studio';
            $finding['action_label'] = 'Open in Localization Studio';
            $finding['handoff_return_to'] = $returnTo;
            $finding['handoff_url'] = self::buildEditorHandoffUrl($ownerKey, $locale, $key, $returnTo);
        }
        unset($finding);
    }

    private static function scanReturnPath(string $ownerKey, string $scope, string $locale): string
    {
        return '/apps/studio/tools/localization-scan-extraction'
            . '?owner=' . rawurlencode($ownerKey)
            . '&scope=' . rawurlencode($scope)
            . '&locale=' . rawurlencode($locale);
    }

    private static function emptyResult(string $ownerKey, string $scope, string $error): array
    {
        return [
            'scanned_at' => date('Y-m-d H:i:s'),
            'owner_key' => $ownerKey,
            'scope' => $scope,
            'scanned_files' => 0,
            'total_lines' => 0,
            'scan_ok' => false,
            'error' => $error,
            'summary' => [
                'human_facing_candidates' => 0,
                'already_localized_usages' => 0,
                'missing_owner_keys' => 0,
                'external_shared_key_usages' => 0,
                'possibly_unused_keys' => 0,
                'internal_strings' => 0,
                'ambiguous_strings' => 0,
                'ignored_strings' => 0,
            ],
            'findings' => [],
            'inline_text_candidates' => [],
            'loc_key_usages' => [],
            'missing_keys' => [],
            'missing_key_review_plan' => [
                'groups' => [],
                'rows' => [],
                'tsv' => '',
            ],
            'unused_keys' => [],
        ];
    }

    private static function buildMissingKeyReviewPlan(array &$findings, array $definedKeysFlat = []): array
    {
        $rowsByKey = [];
        $findingIndexesByKey = [];

        foreach ($findings as $idx => $finding) {
            if (($finding['category'] ?? '') !== 'missing_owner_key') {
                continue;
            }

            $key = (string)($finding['detected'] ?? '');
            if ($key === '') {
                continue;
            }

            if (!isset($rowsByKey[$key])) {
                $suggestion = self::suggestEnglishValueForMissingKey($finding);
                $rowsByKey[$key] = [
                    'group' => self::missingKeyGroup($key),
                    'key' => $key,
                    'first_file' => (string)($finding['file'] ?? ''),
                    'first_line' => (int)($finding['line'] ?? 0),
                    'usage_count' => 0,
                    'suggested_english_value' => $suggestion['value'],
                    'suggestion_confidence' => $suggestion['confidence'],
                    'suggestion_tier' => $suggestion['tier'],
                    'suggestion_ready' => $suggestion['ready'],
                    'suggestion_rejection_reason' => $suggestion['rejection_reason'],
                    'suggestion_evidence_type' => $suggestion['evidence_type'],
                    'suggestion_evidence_source' => $suggestion['evidence_source'],
                    'suggestion_reason' => $suggestion['reason'],
                    'status' => (string)($finding['status'] ?? 'unresolved'),
                    'action_kind' => (string)($finding['action_kind'] ?? 'open_localization_studio'),
                    'action_label' => (string)($finding['action_label'] ?? 'Open in Localization Studio'),
                    'handoff_url' => (string)($finding['handoff_url'] ?? ''),
                ];
                $findingIndexesByKey[$key] = [];
            }

            $rowsByKey[$key]['usage_count']++;
            $findingIndexesByKey[$key][] = $idx;
        }

        $rows = self::applyContextAwareSuggestions(array_values($rowsByKey), $findings, $definedKeysFlat);
        $rows = self::demoteDuplicateSuggestedLabels($rows);
        $rows = self::applyPostPlanQualityGate($rows);
        usort($rows, static function (array $a, array $b): int {
            return [$a['group'], $a['key']] <=> [$b['group'], $b['key']];
        });

        $rowByKey = [];
        foreach ($rows as $row) {
            $rowByKey[$row['key']] = $row;
        }

        foreach ($findingIndexesByKey as $key => $indexes) {
            if (!isset($rowByKey[$key])) {
                continue;
            }

            foreach ($indexes as $idx) {
                $findings[$idx]['review_group'] = $rowByKey[$key]['group'];
                $findings[$idx]['usage_count'] = $rowByKey[$key]['usage_count'];
                $findings[$idx]['suggested_english_value'] = $rowByKey[$key]['suggested_english_value'];
                $findings[$idx]['suggestion_confidence'] = $rowByKey[$key]['suggestion_confidence'];
                $findings[$idx]['suggestion_tier'] = $rowByKey[$key]['suggestion_tier'];
                $findings[$idx]['suggestion_ready'] = $rowByKey[$key]['suggestion_ready'];
                $findings[$idx]['suggestion_rejection_reason'] = $rowByKey[$key]['suggestion_rejection_reason'];
                $findings[$idx]['suggestion_evidence_type'] = $rowByKey[$key]['suggestion_evidence_type'];
                $findings[$idx]['suggestion_evidence_source'] = $rowByKey[$key]['suggestion_evidence_source'];
                $findings[$idx]['suggestion_reason'] = $rowByKey[$key]['suggestion_reason'];
            }
        }

        $groups = [];
        foreach ($rows as $row) {
            $group = (string)$row['group'];
            if (!isset($groups[$group])) {
                $groups[$group] = [
                    'group' => $group,
                    'count' => 0,
                    'rows' => [],
                ];
            }
            $groups[$group]['count']++;
            $groups[$group]['rows'][] = $row;
        }

        return [
            'groups' => array_values($groups),
            'rows' => $rows,
            'tsv' => self::missingKeyReviewPlanTsv($rows),
        ];
    }

    private static function missingKeyGroup(string $key): string
    {
        $segments = array_values(array_filter(explode('.', $key), static fn(string $segment): bool => $segment !== ''));
        if (count($segments) <= 1) {
            return $key;
        }

        array_pop($segments);
        return implode('.', $segments);
    }

    private static function suggestEnglishValueForMissingKey(array $finding): array
    {
        $key = (string)($finding['detected'] ?? '');
        $keyRejection = self::suggestionRejectionReason($key, '');
        if ($keyRejection !== '') {
            return self::suggestionResult('', 'none', 'rejected', false, $keyRejection, 'Ambiguous', 'rejection layer', 'Rejected before value generation.');
        }

        $canonicalValue = SuggestionQualityGateService::canonicalEnglishValue($key);
        if ($canonicalValue !== null) {
            $canonicalRejection = self::suggestionRejectionReason($key, $canonicalValue);
            if ($canonicalRejection !== '') {
                return self::suggestionResult($canonicalValue, 'none', 'rejected', false, $canonicalRejection, 'Ambiguous', 'canonical app/Locale/en.php', 'Canonical value was rejected as unsafe.');
            }

            return self::suggestionResult($canonicalValue, 'auto_safe', 'auto_safe', true, '', 'Deterministic', 'canonical app/Locale/en.php', 'Existing canonical English locale value reused.');
        }

        $tail = self::missingKeyTail($key);
        $tailSuggestion = self::contextIndependentTailSuggestion($tail);
        if ($tailSuggestion !== null) {
            $valueRejection = self::suggestionRejectionReason($key, $tailSuggestion['value']);
            if ($valueRejection !== '') {
                return self::suggestionResult($tailSuggestion['value'], 'none', 'rejected', false, $valueRejection, 'Ambiguous', 'rejection layer', 'Rejected before auto-apply.');
            }
            return self::suggestionResult(
                $tailSuggestion['value'],
                $tailSuggestion['confidence'],
                $tailSuggestion['tier'],
                $tailSuggestion['ready'],
                $tailSuggestion['rejection_reason'],
                $tailSuggestion['evidence_type'],
                $tailSuggestion['evidence_source'],
                $tailSuggestion['reason']
            );
        }

        $nearby = self::nearbyRenderedTextSuggestion($finding);
        if ($nearby !== '') {
            $valueRejection = self::suggestionRejectionReason($key, $nearby);
            if ($valueRejection !== '') {
                return self::suggestionResult($nearby, 'none', 'rejected', false, $valueRejection, 'Ambiguous', 'nearby source text', 'Nearby source text was rejected as unsafe.');
            }

            return self::suggestionResult($nearby, 'high', 'high', true, '', 'Context Derived', 'nearby source text', 'Derived from nearby rendered source text.');
        }

        if (self::isNoisySuggestionTail($tail)) {
            return self::suggestionResult('', 'none', 'rejected', false, 'unsafe_key', 'Ambiguous', 'key tail', 'Key tail looks technical or unsafe.');
        }

        $humanized = self::humanizeKeyTail($tail);
        if ($humanized === '') {
            return self::suggestionResult('', 'none', 'rejected', false, 'empty_value', 'Ambiguous', 'key tail', 'No value could be generated.');
        }

        $valueRejection = self::suggestionRejectionReason($key, $humanized);
        if ($valueRejection !== '') {
            return self::suggestionResult($humanized, 'none', 'rejected', false, $valueRejection, 'Ambiguous', 'rejection layer', 'Generated value was rejected as unsafe.');
        }

        if (self::isAmbiguousLongKey($tail)) {
            return self::suggestionResult($humanized, 'low', 'review', false, 'ambiguous_key', 'Ambiguous', 'key tail', 'Long key tail needs review.');
        }

        if (self::isWeakGenericTail($tail)) {
            return self::suggestionResult($humanized, 'low', 'review', false, 'low_confidence', 'Ambiguous', 'generic key tail', 'Generic key tail needs context.');
        }

        if (self::isAutoSafeKeyTail($tail)) {
            return self::suggestionResult($humanized, 'auto_safe', 'auto_safe', true, '', 'Deterministic', 'auto-safe key tail', 'Known safe UI label.');
        }

        if (self::isNaturalUiKeyTail($tail)) {
            return self::suggestionResult($humanized, 'medium', 'medium', true, '', 'Pattern Derived', 'key tail', 'Derived from key naming pattern.');
        }

        return self::suggestionResult($humanized, 'low', 'review', false, 'low_confidence', 'Ambiguous', 'key tail', 'Pattern is too weak for auto-fix.');
    }

    private static function suggestionResult(string $value, string $confidence, string $tier, bool $ready, string $rejectionReason, string $evidenceType, string $evidenceSource, string $reason): array
    {
        $qualityReason = SuggestionQualityGateService::readyBlockReason('', $value);
        if ($ready && $qualityReason !== '') {
            $ready = false;
            $confidence = 'low';
            $tier = 'review';
            $rejectionReason = $qualityReason;
            $evidenceType = 'Ambiguous';
            $evidenceSource = 'post-plan quality gate';
            $reason = 'Suggestion was blocked from ready because it matched the quality gate.';
        }

        return [
            'value' => $value,
            'confidence' => $confidence,
            'tier' => $tier,
            'ready' => $ready,
            'rejection_reason' => $rejectionReason,
            'evidence_type' => $evidenceType,
            'evidence_source' => $evidenceSource,
            'reason' => $reason,
        ];
    }

    private static function suggestionRejectionReason(string $key, string $value): string
    {
        $keyTail = self::missingKeyTail($key);
        $probe = trim($value !== '' ? $value : $keyTail);
        if ($probe === '') {
            return 'empty_value';
        }

        $qualityGateReason = SuggestionQualityGateService::rejectionReason($key, $value !== '' ? $value : $keyTail);
        if ($qualityGateReason !== '') {
            return $qualityGateReason;
        }

        if (self::containsPathOrUrl($key) || self::containsPathOrUrl($value)) {
            return 'path_url';
        }

        if (self::containsExpressionFragment($key) || self::containsExpressionFragment($value)) {
            return 'expression_fragment';
        }

        if (self::containsHtmlTag($key) || self::containsHtmlTag($value)) {
            return 'html_tag';
        }

        if (self::isObviousConfigRouteClassIdValue($keyTail, $value)) {
            return 'config_route_class_id';
        }

        return '';
    }

    private static function containsPathOrUrl(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        return preg_match('#(?:https?://|^/apps/|^apps/|^storage/|/storage/|^public/|^resources/|^vendor/|\.php(?:\b|$)|\.js(?:\b|$)|\.css(?:\b|$))#i', $trimmed) === 1;
    }

    private static function containsExpressionFragment(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return preg_match('/(?:<\?=|<\?php|\$[A-Za-z_][A-Za-z0-9_]*|[\'"]\s*\+\s*[A-Za-z_$]|[A-Za-z_$][A-Za-z0-9_$]*\s*\+\s*[\'"]|=>|::|->|\{\{|\}\})/', $value) === 1;
    }

    private static function containsHtmlTag(string $value): bool
    {
        return preg_match('/<\s*\/?\s*[A-Za-z][^>]*>/', $value) === 1;
    }

    private static function isObviousConfigRouteClassIdValue(string $tail, string $value): bool
    {
        $tail = strtolower(trim($tail));
        $value = trim($value);
        if ($tail === '') {
            return true;
        }

        if (preg_match('/(?:^|_)(route|uri|path|file|dir|directory|class|classname|css_class|html_id|dom_id|selector|module_key|plugin_key|controller|handler|service|provider|middleware|permission|policy|guard|schema|table|column|config|setting|env|secret|token|csrf|uuid|json|payload|endpoint|api)(?:_|$)/', $tail) === 1) {
            return true;
        }

        if ($value !== '' && preg_match('/^[a-z0-9_.:-]+$/', $value) === 1 && preg_match('/[_:.:-]/', $value) === 1) {
            return true;
        }

        return false;
    }

    private static function isAmbiguousLongKey(string $tail): bool
    {
        $tail = trim($tail);
        if (strlen($tail) > 48) {
            return true;
        }

        $parts = preg_split('/[_\s-]+/', $tail);
        $parts = array_values(array_filter(is_array($parts) ? $parts : [], static fn(string $part): bool => $part !== ''));
        return count($parts) > 5;
    }

    private static function isAutoSafeKeyTail(string $tail): bool
    {
        $tail = strtolower(trim($tail));
        $autoSafe = [
            'apply',
            'approve',
            'reject',
            'cancel',
            'reason',
            'errors',
            'checks',
            'created_at',
            'updated_at',
            'request_id',
            'owner_label',
            'current_status',
            'default_value',
        ];

        return in_array($tail, $autoSafe, true);
    }

    private static function isWeakGenericTail(string $tail): bool
    {
        return in_array(strtolower(trim($tail)), ['title', 'subtitle', 'empty', 'empty_note'], true);
    }

    private static function contextIndependentTailSuggestion(string $tail): ?array
    {
        $tail = strtolower(trim($tail));
        $intentionalValue = SuggestionQualityGateService::intentionalTailValue($tail);
        if ($intentionalValue !== null) {
            return [
                'value' => $intentionalValue,
                'confidence' => 'auto_safe',
                'tier' => 'auto_safe',
                'ready' => true,
                'rejection_reason' => '',
                'evidence_type' => 'Deterministic',
                'evidence_source' => 'intentional key-tail mapping',
                'reason' => 'Known generated identifier mapped to intended UI copy.',
            ];
        }

        $map = [
            'today' => ['value' => 'Today', 'confidence' => 'auto_safe', 'tier' => 'auto_safe', 'ready' => true, 'evidence_type' => 'Deterministic', 'source' => 'calendar key tail', 'reason' => 'Known calendar filter label.'],
            'coverage_window' => ['value' => 'Coverage Window', 'confidence' => 'medium', 'tier' => 'medium', 'ready' => true, 'evidence_type' => 'Pattern Derived', 'source' => 'key tail', 'reason' => 'Derived from specific key tail.'],
        ];

        if (!isset($map[$tail])) {
            return null;
        }

        return [
            'value' => $map[$tail]['value'],
            'confidence' => $map[$tail]['confidence'],
            'tier' => $map[$tail]['tier'],
            'ready' => $map[$tail]['ready'],
            'rejection_reason' => '',
            'evidence_type' => $map[$tail]['evidence_type'],
            'evidence_source' => $map[$tail]['source'],
            'reason' => $map[$tail]['reason'],
        ];
    }

    private static function applyContextAwareSuggestions(array $rows, array $findings, array $definedKeysFlat): array
    {
        $ownerSubject = self::deriveOwnerSubject($rows, $findings, $definedKeysFlat);
        $contextTerms = self::deriveContextTerms($rows, $findings, $definedKeysFlat);

        foreach ($rows as $idx => $row) {
            $tail = strtolower(self::missingKeyTail((string)($row['key'] ?? '')));
            $contextSuggestion = self::contextSuggestionForGenericTail($tail, $ownerSubject, $contextTerms);
            if ($contextSuggestion === null) {
                continue;
            }

            $valueRejection = self::suggestionRejectionReason((string)$row['key'], $contextSuggestion['value']);
            if ($valueRejection !== '') {
                $rows[$idx]['suggestion_rejection_reason'] = $valueRejection;
                $rows[$idx]['suggestion_ready'] = false;
                $rows[$idx]['suggestion_confidence'] = 'none';
                $rows[$idx]['suggestion_tier'] = 'rejected';
                $rows[$idx]['suggestion_evidence_type'] = 'Ambiguous';
                $rows[$idx]['suggestion_evidence_source'] = 'rejection layer';
                $rows[$idx]['suggestion_reason'] = 'Context-derived suggestion was rejected as unsafe.';
                continue;
            }

            $rows[$idx]['suggested_english_value'] = $contextSuggestion['value'];
            $rows[$idx]['suggestion_confidence'] = 'medium';
            $rows[$idx]['suggestion_tier'] = 'medium';
            $rows[$idx]['suggestion_ready'] = true;
            $rows[$idx]['suggestion_rejection_reason'] = '';
            $rows[$idx]['suggestion_evidence_type'] = 'Context Derived';
            $rows[$idx]['suggestion_evidence_source'] = $contextSuggestion['source'];
            $rows[$idx]['suggestion_reason'] = $contextSuggestion['reason'];
        }

        return $rows;
    }

    private static function applyPostPlanQualityGate(array $rows): array
    {
        foreach ($rows as $idx => $row) {
            if (empty($row['suggestion_ready'])) {
                continue;
            }

            $reason = SuggestionQualityGateService::readyBlockReason(
                (string)($row['key'] ?? ''),
                (string)($row['suggested_english_value'] ?? '')
            );
            if ($reason === '') {
                continue;
            }

            $rows[$idx]['suggestion_confidence'] = 'low';
            $rows[$idx]['suggestion_tier'] = 'review';
            $rows[$idx]['suggestion_ready'] = false;
            $rows[$idx]['suggestion_rejection_reason'] = $reason;
            $rows[$idx]['suggestion_evidence_type'] = 'Ambiguous';
            $rows[$idx]['suggestion_evidence_source'] = 'post-plan quality gate';
            $rows[$idx]['suggestion_reason'] = 'Ready suggestion was blocked by final quality scan.';
        }

        return $rows;
    }

    private static function contextSuggestionForGenericTail(string $tail, string $ownerSubject, array $contextTerms): ?array
    {
        if ($ownerSubject === '') {
            return null;
        }

        $hasShortage = in_array('shortage', $contextTerms, true) || in_array('shortages', $contextTerms, true);
        $hasOrders = in_array('order', $contextTerms, true) || in_array('orders', $contextTerms, true);
        $hasFilter = in_array('filter', $contextTerms, true) || in_array('filters', $contextTerms, true);
        $hasDate = in_array('date', $contextTerms, true) || in_array('range', $contextTerms, true);

        if ($tail === 'title') {
            return [
                'value' => $ownerSubject . ' Dashboard',
                'source' => 'owner namespace and section keys',
                'reason' => 'Generic title resolved from owner subject.',
            ];
        }
        if ($tail === 'subtitle') {
            $detail = $hasOrders && $hasShortage ? 'Monitor order coverage and shortages' : 'Monitor ' . strtolower($ownerSubject) . ' activity';
            return [
                'value' => $detail,
                'source' => 'neighboring section keys and source text',
                'reason' => 'Generic subtitle resolved from nearby domain terms.',
            ];
        }
        if ($tail === 'empty') {
            return [
                'value' => 'No ' . strtolower($ownerSubject) . ' records found',
                'source' => 'owner namespace and empty-state key',
                'reason' => 'Generic empty state resolved from owner subject.',
            ];
        }
        if ($tail === 'empty_note') {
            $value = $hasFilter || $hasDate ? 'Try adjusting filters or date range' : 'Try adjusting your search criteria';
            return [
                'value' => $value,
                'source' => 'nearby filter/date context',
                'reason' => 'Generic empty-state note resolved from nearby filter context.',
            ];
        }

        return null;
    }

    private static function deriveOwnerSubject(array $rows, array $findings, array $definedKeysFlat): string
    {
        foreach ($definedKeysFlat as $key => $value) {
            if (preg_match('/(?:^|\.)(title|dashboard_title)$/', (string)$key) === 1 && is_string($value) && trim($value) !== '') {
                $clean = self::normalizeAdvisoryValue($value);
                if ($clean !== '' && !self::isWeakGenericContextValue($clean)) {
                    if (preg_match('/\b(Coverage|Payroll|Studio|Organization|Sales)\b/i', $clean, $m) === 1) {
                        return ucfirst(strtolower($m[1]));
                    }
                    return preg_replace('/\s+(Dashboard|Overview|Report)$/i', '', $clean) ?: $clean;
                }
            }
        }

        foreach ($findings as $finding) {
            if (($finding['category'] ?? '') !== 'human_facing_candidate') {
                continue;
            }
            $detected = self::normalizeAdvisoryValue((string)($finding['detected'] ?? ''));
            if ($detected !== '' && preg_match('/\b(Coverage|Payroll|Studio|Organization|Sales)\b/i', $detected, $m) === 1) {
                return ucfirst(strtolower($m[1]));
            }
        }

        foreach ($rows as $row) {
            $key = (string)($row['key'] ?? '');
            if (str_contains($key, '.cov.')) {
                return 'Coverage';
            }
        }

        return '';
    }

    private static function deriveContextTerms(array $rows, array $findings, array $definedKeysFlat): array
    {
        $blobParts = [];
        foreach ($rows as $row) {
            $blobParts[] = (string)($row['key'] ?? '');
            $blobParts[] = (string)($row['suggested_english_value'] ?? '');
        }
        foreach ($definedKeysFlat as $key => $value) {
            $blobParts[] = (string)$key;
            if (is_scalar($value)) {
                $blobParts[] = (string)$value;
            }
        }
        foreach ($findings as $finding) {
            if (($finding['category'] ?? '') === 'human_facing_candidate') {
                $blobParts[] = (string)($finding['detected'] ?? '');
            }
        }

        $blob = strtolower(implode(' ', $blobParts));
        preg_match_all('/[a-z]+/', $blob, $matches);
        return array_values(array_unique($matches[0] ?? []));
    }

    private static function isWeakGenericContextValue(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['title', 'subtitle', 'empty', 'empty note'], true);
    }

    private static function demoteDuplicateSuggestedLabels(array $rows): array
    {
        $keysByValue = [];
        foreach ($rows as $row) {
            $value = trim((string)($row['suggested_english_value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $keysByValue[$value][(string)($row['key'] ?? '')] = true;
        }

        foreach ($rows as $idx => $row) {
            $value = trim((string)($row['suggested_english_value'] ?? ''));
            if ($value === '' || count($keysByValue[$value] ?? []) <= 1) {
                continue;
            }

            if (in_array((string)($row['suggestion_confidence'] ?? ''), ['auto_safe', 'high'], true)
                && in_array((string)($row['suggestion_tier'] ?? ''), ['auto_safe', 'high'], true)) {
                continue;
            }

            $rows[$idx]['suggestion_confidence'] = 'low';
            $rows[$idx]['suggestion_tier'] = 'review';
            $rows[$idx]['suggestion_ready'] = false;
            $rows[$idx]['suggestion_rejection_reason'] = 'duplicate_label';
            $rows[$idx]['suggestion_evidence_type'] = 'Ambiguous';
            $rows[$idx]['suggestion_evidence_source'] = 'duplicate generated label';
            $rows[$idx]['suggestion_reason'] = 'Different keys generated the same label and still need human review.';
        }

        return $rows;
    }

    private static function nearbyRenderedTextSuggestion(array $finding): string
    {
        $line = (int)($finding['line'] ?? 0);
        $contextLines = self::numberedContextLines((string)($finding['context'] ?? ''));
        foreach ($contextLines as $contextLineNumber => $contextLine) {
            if ($line > 0 && abs($contextLineNumber - $line) > 1) {
                continue;
            }
            if (self::isLocCallLine($contextLine) || self::isCommentOrDebugLine($contextLine)) {
                continue;
            }

            $candidates = array_merge(
                self::htmlTextCandidates($contextLine),
                self::attributeTextCandidates($contextLine),
                self::quotedRenderedTextCandidates($contextLine)
            );
            foreach ($candidates as $candidate) {
                $candidate = self::normalizeAdvisoryValue($candidate);
                if ($candidate !== '' && !self::isExcludedString($candidate)) {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private static function numberedContextLines(string $context): array
    {
        $result = [];
        foreach (explode("\n", $context) as $line) {
            if (preg_match('/^\s*(\d+):\s*(.*)$/', $line, $m) === 1) {
                $result[(int)$m[1]] = (string)$m[2];
            }
        }

        return $result;
    }

    private static function quotedRenderedTextCandidates(string $line): array
    {
        $results = [];
        if (preg_match_all('/(?:echo\s+|=>\s*)([\'"])([A-Z][A-Za-z0-9\s\/,.\'!?:;\-()]{2,}?)\1/', $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $results[] = $match[2];
            }
        }

        return $results;
    }

    private static function missingKeyTail(string $key): string
    {
        $segments = explode('.', $key);
        return (string)end($segments);
    }

    private static function isNoisySuggestionTail(string $tail): bool
    {
        $tail = strtolower(trim($tail));
        if ($tail === '' || self::isAttributeFragment($tail) || self::isSqlFragment($tail)) {
            return true;
        }

        if (preg_match('/^(data|data_|html|sql|where|class|id|href|src|method|action|csrf|token|uuid|json|api)/', $tail) === 1) {
            return true;
        }

        return preg_match('/[^a-z0-9_ -]/', $tail) === 1;
    }

    private static function humanizeKeyTail(string $tail): string
    {
        $tail = trim($tail);
        if ($tail === '') {
            return '';
        }

        $tailLower = strtolower($tail);
        $semanticMap = [
            'coverage_pct' => 'Coverage %',
            'avg_coverage_pct' => 'Average Coverage %',
            'demand_qty' => 'Demand Quantity',
            'shortage_qty' => 'Shortage Quantity',
            'covered_qty' => 'Covered Quantity',
            'fully_covered_count' => 'Fully Covered Orders',
            'critical_count' => 'Critical Orders',
            'priorities_desc' => 'Priority Orders Requiring Attention',
            '3day' => 'Next 3 Days',
            '7day' => 'Next 7 Days',
        ];
        if (isset($semanticMap[$tailLower])) {
            return $semanticMap[$tailLower];
        }

        $words = preg_split('/[_\s-]+/', $tail);
        $words = array_values(array_filter(is_array($words) ? $words : [], static fn(string $word): bool => $word !== ''));
        if ($words === []) {
            return '';
        }

        $valueWords = [];
        foreach ($words as $word) {
            $lowerWord = strtolower($word);
            if ($lowerWord === 'pct') {
                $valueWords[] = '%';
            } elseif ($lowerWord === 'qty') {
                $valueWords[] = 'Quantity';
            } elseif ($lowerWord === 'avg') {
                $valueWords[] = 'Average';
            } elseif ($lowerWord === 'desc') {
                $valueWords[] = 'Description';
            } elseif ($lowerWord === 'id') {
                $valueWords[] = 'ID';
            } elseif (preg_match('/^([0-9]+)day$/', $lowerWord, $m) === 1) {
                $valueWords[] = 'Next';
                $valueWords[] = $m[1];
                $valueWords[] = 'Days';
            } elseif (preg_match('/^[0-9]+[a-z]*$/', $word) === 1) {
                $valueWords[] = strtoupper($word);
            } else {
                $valueWords[] = ucfirst(strtolower($word));
            }
        }

        return implode(' ', $valueWords);
    }

    private static function isNaturalUiKeyTail(string $tail): bool
    {
        if (preg_match('/^[a-z0-9]+(?:_[a-z0-9]+){0,3}$/', $tail) !== 1) {
            return false;
        }

        return !self::isNoisySuggestionTail($tail);
    }

    private static function normalizeAdvisoryValue(string $value): string
    {
        $value = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = (string)preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private static function missingKeyReviewPlanTsv(array $rows): string
    {
        $lines = [
            implode("\t", ['Group', 'Key', 'First file', 'First line', 'Usage count', 'Suggested English value', 'Confidence', 'Action']),
        ];

        foreach ($rows as $row) {
            $lines[] = implode("\t", [
                self::tsvCell((string)($row['group'] ?? '')),
                self::tsvCell((string)($row['key'] ?? '')),
                self::tsvCell((string)($row['first_file'] ?? '')),
                (string)(int)($row['first_line'] ?? 0),
                (string)(int)($row['usage_count'] ?? 0),
                self::tsvCell((string)($row['suggested_english_value'] ?? '')),
                self::tsvCell((string)($row['suggestion_confidence'] ?? 'none')),
                self::tsvCell((string)($row['action_label'] ?? 'Open in Localization Studio')),
            ]);
        }

        return implode("\n", $lines);
    }

    private static function tsvCell(string $value): string
    {
        return str_replace(["\t", "\r", "\n"], ' ', $value);
    }

    private static function detectSemanticCategory(string $text): string
    {
        $lower = strtolower(trim($text));
        $scores = [];
        foreach (self::SEMANTIC_CATEGORIES as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $scores[$category] = $score;
            }
        }
        if ($scores === []) {
            return 'general';
        }
        arsort($scores);
        return (string) key($scores);
    }

    private static function detectElementType(array $finding): string
    {
        $context = (string)($finding['context'] ?? '');
        if ($context === '') {
            return 'unknown';
        }
        foreach (self::ELEMENT_TAG_PATTERNS as $pattern => $type) {
            if (preg_match($pattern, $context) === 1) {
                return $type;
            }
        }
        return 'unknown';
    }

    private static function computeRelevance(string $semanticCategory, string $elementType): string
    {
        $highPriority = ['heading', 'action', 'navigation', 'confirmation', 'error_message'];
        $mediumPriority = ['label', 'status', 'metric', 'placeholder'];
        $highElements = ['heading', 'button', 'link', 'navigation', 'main', 'header'];
        $mediumElements = ['label', 'input', 'select', 'textarea', 'table_header', 'table_cell', 'paragraph'];

        $categoryScore = in_array($semanticCategory, $highPriority, true) ? 2
            : (in_array($semanticCategory, $mediumPriority, true) ? 1 : 0);
        $elementScore = in_array($elementType, $highElements, true) ? 2
            : (in_array($elementType, $mediumElements, true) ? 1 : 0);

        $total = $categoryScore + $elementScore;
        if ($total >= 3) return 'high';
        if ($total >= 1) return 'medium';
        return 'low';
    }

    private static function annotateOccurrences(array &$findings): void
    {
        $occurrenceMap = [];
        foreach ($findings as $f) {
            if (($f['type'] ?? '') !== 'inline_text') {
                continue;
            }
            $detected = $f['detected'] ?? '';
            if ($detected === '') {
                continue;
            }
            if (!isset($occurrenceMap[$detected])) {
                $occurrenceMap[$detected] = ['count' => 0, 'files' => []];
            }
            $occurrenceMap[$detected]['count']++;
            $file = $f['file'] ?? '';
            if ($file !== '' && !in_array($file, $occurrenceMap[$detected]['files'], true)) {
                $occurrenceMap[$detected]['files'][] = $file;
            }
        }
        foreach ($findings as &$f) {
            if (($f['type'] ?? '') !== 'inline_text') {
                continue;
            }
            $detected = $f['detected'] ?? '';
            if ($detected === '' || !isset($occurrenceMap[$detected])) {
                continue;
            }
            $f['occurrence_count'] = $occurrenceMap[$detected]['count'];
            $f['occurrence_files'] = $occurrenceMap[$detected]['files'];
        }
        unset($f);
    }

    private static function isFalsePositive(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }
        if (preg_match('/^\?[a-z_]+=/', $trimmed)) {
            return true;
        }
        if (preg_match('#^/[a-z0-9_/-]+#', $trimmed)) {
            return true;
        }
        if (preg_match('/^[a-z-]+=[\'"][^\'"]*[\'"]$/', $trimmed)) {
            return true;
        }
        if (preg_match('/^[a-z_]+\$[a-z_]+/', $trimmed)) {
            return true;
        }
        if (preg_match('/\{\{[^}]+\}\}/', $trimmed)) {
            return true;
        }
        if (preg_match('/^[a-z_]+\s*\(/', $trimmed)) {
            return true;
        }
        return false;
    }

    private static function classifyInlineTextCandidate(array $finding, string $relative): array
    {
        $finding['extractable'] = false;

        $text = $finding['detected'] ?? '';
        $isInternalPath = self::isInternalFilePath($relative);
        $isTechnical = self::isTechnicalString($text);

        if ($isTechnical || $isInternalPath) {
            $finding['suggested_key'] = '';
            if ($isTechnical && !$isInternalPath) {
                $finding['category'] = 'internal_string';
                $finding['reason'] = 'Text matches technical patterns (attributes, SQL fragments, route names, or code identifiers) and is excluded from extraction suggestions.';
            } else {
                $finding['category'] = 'internal_string';
                $finding['reason'] = $isInternalPath && !$isTechnical
                    ? 'Text appears in a non-view file (service/handler/provider) and is unlikely to be user-facing.'
                    : 'Text matches technical patterns and appears in a non-view file. Safe to exclude from extraction.';
            }
        } else {
            $finding['category'] = 'human_facing_candidate';
            $finding['reason'] = 'Text appears in a view file and does not match known technical patterns. Likely user-facing.';
        }

        $finding['confidence_original'] = $finding['confidence'];
        $finding['semantic_category'] = self::detectSemanticCategory($text);
        $finding['element_type'] = self::detectElementType($finding);
        $finding['relevance'] = self::computeRelevance($finding['semantic_category'], $finding['element_type']);
        return $finding;
    }

    private static function reconcileKeyUsages(array $rawKeyUsages, array $definedKeysFlat, string $ownerKey): array
    {
        $findings = [];
        $observedKeys = [];
        foreach ($rawKeyUsages as $usage) {
            if (!empty($usage['detected'])) {
                $observedKeys[] = (string)$usage['detected'];
            }
        }
        $aliases = self::ownerKeyAliases($ownerKey, array_merge(array_keys($definedKeysFlat), $observedKeys));

        foreach ($rawKeyUsages as $u) {
            $key = $u['detected'] ?? '';
            $isDefined = isset($definedKeysFlat[$key]);

            if ($isDefined) {
                $u['status'] = 'matched';
                $u['category'] = 'already_localized_usage';
                $u['confidence'] = 'high';
                $u['reason'] = 'Key is defined in the selected owner reference locale file; presence beats namespace.';
            } elseif (self::keyBelongsToOwner($key, $ownerKey, $aliases)) {
                $u['status'] = 'unresolved';
                $u['category'] = 'missing_owner_key';
                $u['confidence'] = 'high';
                $u['reason'] = 'Key is absent from the owner reference locale file but matches an owner-local namespace alias.';
            } elseif (self::isSharedKey($key, $ownerKey, $aliases)) {
                $u['status'] = 'shared';
                $u['category'] = 'external_shared_key_usage';
                $u['confidence'] = 'medium';
                $u['reason'] = 'Key is absent from the owner reference locale file and appears to use a shared/global or different-owner namespace.';
            } else {
                $u['status'] = 'unresolved';
                $u['category'] = 'missing_owner_key';
                $u['confidence'] = 'medium';
                $u['reason'] = 'Key is absent from the owner reference locale file and does not clearly match a shared namespace.';
            }

            $u['suggested_key'] = '';
            $u['extractable'] = false;
            $findings[] = $u;
        }

        return $findings;
    }

    private static function isTechnicalString(string $text): bool
    {
        if (self::isAttributeFragment($text) || self::isSqlFragment($text)) {
            return true;
        }

        foreach (self::TECHNICAL_PATTERNS as $pattern) {
            if (preg_match($pattern, trim($text))) {
                return true;
            }
        }

        if (preg_match('/^[A-Z][a-z]+[A-Z]/', $text)) {
            return true;
        }

        if (preg_match('/^[a-z]+(_[a-z]+)+$/', $text) && substr_count($text, '_') >= 2) {
            return true;
        }

        $upper = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            if (ctype_upper($text[$i])) $upper++;
        }
        if ($len > 1 && $upper / $len > 0.6) {
            return true;
        }

        return false;
    }

    private static function isInternalFilePath(string $relative): bool
    {
        foreach (self::INTERNAL_FILE_PATTERNS as $pattern) {
            if (preg_match($pattern, $relative)) {
                return true;
            }
        }
        return !preg_match('/\/Views\//', $relative);
    }

    private static function computeCategorizedSummary(array $findings): array
    {
        $summary = [
            'human_facing_candidates' => 0,
            'already_localized_usages' => 0,
            'missing_owner_keys' => 0,
            'external_shared_key_usages' => 0,
            'possibly_unused_keys' => 0,
            'internal_strings' => 0,
            'ambiguous_strings' => 0,
            'ignored_strings' => 0,
        ];

        foreach ($findings as $f) {
            $cat = $f['category'] ?? '';
            switch ($cat) {
                case 'human_facing_candidate':
                    $summary['human_facing_candidates']++;
                    break;
                case 'already_localized_usage':
                    $summary['already_localized_usages']++;
                    break;
                case 'missing_owner_key':
                    $summary['missing_owner_keys']++;
                    break;
                case 'external_shared_key_usage':
                    $summary['external_shared_key_usages']++;
                    break;
                case 'possibly_unused_key':
                    $summary['possibly_unused_keys']++;
                    break;
                case 'internal_string':
                    $summary['internal_strings']++;
                    break;
                case 'ambiguous_string':
                    $summary['ambiguous_strings']++;
                    break;
                default:
                    $summary['ignored_strings']++;
                    break;
            }
        }

        return $summary;
    }

    private static function resolveOwnerPath(string $ownerKey): ?string
    {
        $owner = self::findDiscoveredOwner($ownerKey);
        if ($owner === null) {
            return null;
        }

        $paths = isset($owner['paths']) && is_array($owner['paths']) ? $owner['paths'] : [];
        foreach ($paths as $localePath) {
            $root = self::ownerRootFromLocalePath((string)$localePath);
            if ($root !== null) {
                return $root;
            }
        }

        return null;
    }

    private static function findDiscoveredOwner(string $ownerKey): ?array
    {
        try {
            $owners = LocalizationStudioEditService::getOwnersWithPaths();
        } catch (\Throwable $e) {
            return null;
        }

        foreach ($owners as $owner) {
            if (($owner['key'] ?? '') === $ownerKey) {
                return $owner;
            }
        }

        return null;
    }

    private static function ownerRootFromLocalePath(string $localePath): ?string
    {
        $realLocale = realpath($localePath);
        $appRoot = realpath(APP_ROOT);
        if ($realLocale === false || $appRoot === false || !str_starts_with($realLocale, $appRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        $normalized = str_replace('\\', '/', $realLocale);
        if (preg_match('#^(.+)/Resources/lang/[a-z]{2}\.php$#', $normalized, $m) !== 1
            && preg_match('#^(.+)/lang/[a-z]{2}\.php$#', $normalized, $m) !== 1) {
            return null;
        }

        $root = realpath($m[1]);
        if ($root === false || !str_starts_with($root, $appRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $root;
    }

    private static function findScopeFiles(string $basePath, string $scope): array
    {
        $files = [];
        $root = realpath($basePath);
        if ($root === false) {
            return [];
        }

        $ite = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($ite as $entry) {
            if (!$entry->isFile()) {
                continue;
            }

            $p = $entry->getPathname();
            if (!self::isSafeSourceFile($p, $root, $scope)) {
                continue;
            }

            $files[$p] = true;
        }

        $files = array_keys($files);
        sort($files);
        return $files;
    }

    private static function isSafeSourceFile(string $path, string $root, string $scope): bool
    {
        $real = realpath($path);
        if ($real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return false;
        }

        $relative = str_replace('\\', '/', substr($real, strlen($root) + 1));
        $segments = explode('/', $relative);
        $blocked = [
            'vendor', 'storage', 'cache', 'snapshots', '.backups', 'node_modules',
            'public', 'assets', 'dist', 'build', 'compiled', 'tmp', 'tests', 'test',
            'Resources', 'lang',
        ];
        foreach ($segments as $segment) {
            if (in_array($segment, $blocked, true)) {
                return false;
            }
        }

        $extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        if (!in_array($extension, ['php', 'phtml', 'html'], true)) {
            return false;
        }

        $isView = str_contains('/' . $relative, '/Views/');
        $isController = str_contains('/' . $relative, '/Controllers/');

        if ($scope === 'views') {
            return $isView;
        }
        if ($scope === 'views_controllers') {
            return $isView || $isController;
        }

        return true;
    }

    public static function pathGuardProbe(string $ownerKey): array
    {
        $base = self::resolveOwnerPath($ownerKey);
        $appRoot = realpath(APP_ROOT);

        return [
            'owner_key' => $ownerKey,
            'resolved' => $base,
            'inside_app_root' => $base !== null
                && $appRoot !== false
                && str_starts_with((string)realpath($base), $appRoot . DIRECTORY_SEPARATOR),
            'uses_discovery' => self::findDiscoveredOwner($ownerKey) !== null,
        ];
    }

    private static function sourceContext(array $lines, int $lineIdx): string
    {
        $start = max(1, $lineIdx - 2);
        $end = min(count($lines), $lineIdx + 2);
        $snippet = [];

        for ($i = $start; $i <= $end; $i++) {
            $snippet[] = str_pad((string)$i, 4, ' ', STR_PAD_LEFT) . ': ' . rtrim((string)($lines[$i - 1] ?? ''));
        }

        return implode("\n", $snippet);
    }

    private static function contextLine(array $finding): string
    {
        $context = (string)($finding['context'] ?? '');
        if ($context === '') {
            return '';
        }

        $lines = explode("\n", $context);
        foreach ($lines as $line) {
            if (preg_match('/^\s*\d+:\s*(.*)$/', $line, $m) === 1 && trim($m[1]) !== '') {
                return trim($m[1]);
            }
        }

        return trim($context);
    }

    private static function isLocCallLine(string $line): bool
    {
        return preg_match('/(?<![A-Za-z0-9_])(?:tr|t|__|trans|lang|i18n)\s*\(/', $line) === 1
            || preg_match('/(?<![A-Za-z0-9_])\$t\s*\(/', $line) === 1;
    }

    private static function pushInlineCandidate(array &$results, string $text, string $relative, int $lineIdx, string $ownerKey, string $context, string $confidence): void
    {
        $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/', ' ', $text);
        if (!is_string($text) || self::isExcludedString($text)) {
            return;
        }

        $key = $relative . ':' . $lineIdx . ':' . $text;
        if (isset($results[$key])) {
            return;
        }

        $results[$key] = [
            'type' => 'inline_text',
            'owner' => $ownerKey,
            'file' => $relative,
            'line' => $lineIdx,
            'detected' => $text,
            'suggested_key' => self::suggestKey($text, $ownerKey),
            'confidence' => $confidence,
            'status' => 'pending',
            'context' => $context,
        ];
    }

    private static function isUserFacingAttribute(string $attribute): bool
    {
        return in_array(strtolower($attribute), ['aria-label', 'title', 'placeholder', 'alt'], true);
    }

    private static function isHtmlViewFile(string $relative): bool
    {
        return str_contains($relative, '/Views/')
            || preg_match('/\.(phtml|html)$/', $relative) === 1;
    }

    private static function ownerKeyPrefix(string $ownerKey): string
    {
        $parts = explode('/', $ownerKey);
        $last = (string)end($parts);
        $snake = self::slugPart($last);

        return $snake !== '' ? $snake : 'owner';
    }

    private static function baseKeyFromText(string $text): string
    {
        $clean = strtolower((string)preg_replace('/[^A-Za-z0-9\s]/', ' ', $text));
        $words = preg_split('/\s+/', trim($clean));
        $words = array_values(array_filter(is_array($words) ? $words : []));
        $words = array_slice($words, 0, 6);
        $key = implode('_', $words);
        $key = substr($key, 0, 56);
        $key = trim($key, '_');

        return $key !== '' ? $key : 'text';
    }

    private static function keyBelongsToOwner(string $key, string $ownerKey, ?array $aliases = null): bool
    {
        $aliases = $aliases ?? self::ownerKeyAliases($ownerKey, []);
        foreach ($aliases as $alias) {
            if ($key === $alias || str_starts_with($key, $alias . '.')) {
                return true;
            }
        }

        return false;
    }

    private static function isSharedKey(string $key, string $ownerKey, ?array $aliases = null): bool
    {
        $aliases = $aliases ?? self::ownerKeyAliases($ownerKey, []);
        if (self::keyBelongsToOwner($key, $ownerKey, $aliases)) {
            return false;
        }

        foreach (self::SHARED_GLOBAL_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        if (!str_contains($key, '.')) {
            return false;
        }

        $first = strtok($key, '.');
        return is_string($first) && $first !== '' && !in_array($first, $aliases, true);
    }

    private static function ownerKeyAliases(string $ownerKey, array $definedKeys): array
    {
        $parts = array_values(array_filter(explode('/', $ownerKey), static fn(string $p): bool => $p !== ''));
        $slugs = [];
        foreach ($parts as $part) {
            $slugs[] = self::slugPart($part);
        }
        $slugs = array_values(array_filter($slugs, static fn(string $p): bool => $p !== ''));

        $aliases = [];
        if ($slugs !== []) {
            $aliases[] = implode('.', $slugs);
            $aliases[] = (string)end($slugs);
        }

        if (count($slugs) >= 2 && $slugs[0] === 'studio' && $slugs[1] === 'tools') {
            $aliases[] = implode('.', $slugs);
            $aliases[] = (string)end($slugs);
        }

        foreach ($definedKeys as $key) {
            $segments = explode('.', (string)$key);
            if (count($segments) < 2) {
                continue;
            }

            $twoSegment = $segments[0] . '.' . $segments[1];
            if (self::aliasLooksOwnerLocal($twoSegment, $slugs)) {
                $aliases[] = $twoSegment;
            }

            if (count($segments) >= 3) {
                $threeSegment = $segments[0] . '.' . $segments[1] . '.' . $segments[2];
                if (self::aliasLooksOwnerLocal($threeSegment, $slugs)) {
                    $aliases[] = $threeSegment;
                }
            }
        }

        $aliases = array_values(array_unique(array_filter($aliases, static fn(string $alias): bool => $alias !== '')));
        usort($aliases, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        return $aliases;
    }

    private static function aliasLooksOwnerLocal(string $alias, array $ownerSlugs): bool
    {
        if ($ownerSlugs === []) {
            return false;
        }

        $last = (string)end($ownerSlugs);
        if ($alias === $last || str_ends_with($alias, '.' . $last)) {
            return true;
        }

        $aliasSegments = explode('.', $alias);
        $lastInitials = implode('', array_map(static fn(string $segment): string => substr($segment, 0, 1), explode('_', $last)));
        $lastInitials = $lastInitials !== '' ? $lastInitials : substr($last, 0, 3);

        return in_array($lastInitials, $aliasSegments, true)
            || in_array(substr($last, 0, 3), $aliasSegments, true);
    }

    private static function slugPart(string $part): string
    {
        $snake = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $part));
        $snake = preg_replace('/[^a-z0-9]+/', '_', $snake);
        return trim((string)$snake, '_');
    }

    private static function markSuggestedKeyDuplicates(array &$findings): void
    {
        $seen = [];
        foreach ($findings as &$finding) {
            $suggested = (string)($finding['suggested_key'] ?? '');
            if ($suggested === '') {
                continue;
            }

            if (!isset($seen[$suggested])) {
                $seen[$suggested] = 0;
                continue;
            }

            $seen[$suggested]++;
            $finding['suggested_key'] = $suggested . '_' . ($seen[$suggested] + 1);
        }
        unset($finding);
    }

    private static function htmlTextCandidates(string $line): array
    {
        $matches = [];
        if (preg_match_all('/>([^<>{}][^<>{}]*[A-Za-z][^<>{}]*)</', $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $matches[] = $match[1];
            }
        }

        return $matches;
    }

    private static function attributeTextCandidates(string $line): array
    {
        $matches = [];
        if (preg_match_all('/\b(aria-label|title|placeholder|alt)\s*=\s*([\'"])(.*?)\2/i', $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                if (self::isUserFacingAttribute($match[1])) {
                    $value = trim($match[3]);
                    if ($value !== '' && !self::isAttributeFragment($value)) {
                        $matches[] = $value;
                    }
                }
            }
        }

        return $matches;
    }

    private static function isAttributeFragment(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return true;
        }

        if (preg_match('/\b(data-[a-z0-9_-]*|class|id|name|value|href|src|type|method|action)\s*=/i', $trimmed)) {
            return true;
        }
        if (preg_match('/\b(data-[a-z0-9_-]+|selected|checked|disabled|readonly)\b/i', $trimmed)) {
            return true;
        }
        if (preg_match('/^[\'"]?\s*[a-z0-9_-]+\s*=\s*[\'"]?/i', $trimmed)) {
            return true;
        }
        if (str_contains($trimmed, '="') || str_contains($trimmed, "='")) {
            return true;
        }
        if (preg_match('/[<][a-z][^>]*$/i', $trimmed) || preg_match('/^[^<]*[a-z-]+=["\'][^"\']*$/i', $trimmed)) {
            return true;
        }

        return false;
    }

    private static function isSqlFragment(string $text): bool
    {
        $trimmed = trim($text);
        if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|JOIN|AND|OR|COALESCE|COUNT|SUM|AVG|MIN|MAX|CASE|WHEN|THEN|ELSE|ORDER\s+BY|GROUP\s+BY|HAVING)\b/', $trimmed)) {
            return true;
        }
        if (preg_match('/\b[A-Za-z_][A-Za-z0-9_]*\s*(>=|<=|<>|!=|=|>|<)\s*[A-Za-z0-9_\'"]+/i', $trimmed)) {
            return true;
        }
        if (preg_match('/\b[A-Z_]+\s*\([^)]*\)/i', $trimmed) && preg_match('/(>=|<=|<>|!=|=|>|<|\bAND\b|\bOR\b|,)/i', $trimmed)) {
            return true;
        }

        return false;
    }

    private static function isBlockCommentStart(string $line): bool
    {
        $trimmed = trim($line);
        return str_starts_with($trimmed, '/*');
    }

    private static function isCommentOrDebugLine(string $line): bool
    {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
            return true;
        }
        if (preg_match('/\b(var_dump|print_r|error_log|debug_backtrace|debug_print_backtrace|console\.log)\s*\(/', $trimmed)) {
            return true;
        }
        return false;
    }

    private static function findTranslationUsage(string $line, string $relative, int $lineIdx, string $ownerKey): array
    {
        $results = [];

        if (preg_match_all('/(?<![A-Za-z0-9_])((?:tr|t|__|trans|lang|i18n)|\$t)\s*\(\s*([\'"])([^\'"]+?)\2\s*/', $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $key = $match[3];
                if (trim($key) === '') continue;
                $results[] = [
                    'type' => 'loc_key_usage',
                    'owner' => $ownerKey,
                    'file' => $relative,
                    'line' => $lineIdx,
                    'detected' => $key,
                    'suggested_key' => '',
                    'confidence' => 'high',
                    'status' => 'detected',
                    'context' => trim($line),
                ];
            }
        }

        if (preg_match_all('/(?:\$this->)?tr\(\s*([\'"])([^\'"]+?)\1\s*/', $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $key = $match[2];
                if (trim($key) === '') continue;
                $already = false;
                foreach ($results as $r) {
                    if ($r['detected'] === $key) { $already = true; break; }
                }
                if (!$already) {
                    $results[] = [
                        'type' => 'loc_key_usage',
                        'owner' => $ownerKey,
                        'file' => $relative,
                        'line' => $lineIdx,
                        'detected' => $key,
                        'suggested_key' => '',
                        'confidence' => 'high',
                        'status' => 'detected',
                        'context' => trim($line),
                    ];
                }
            }
        }

        return $results;
    }

    private static function findInlineTextCandidates(string $line, string $relative, int $lineIdx, string $ownerKey): array
    {
        $results = [];
        $contextLine = trim($line);

        if (self::isLocCallLine($line)) {
            return [];
        }

        if (preg_match_all('/echo\s+([\'"])([A-Z][A-Za-z0-9\s\/,.\'!?:;\-()]{2,}?)\1\s*[;.]/', $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                self::pushInlineCandidate($results, $match[2], $relative, $lineIdx, $ownerKey, $contextLine, 'high');
            }
        }

        if (preg_match_all('/=>\s*([\'"])([A-Z][A-Za-z0-9\s\/,.\'!?:;\-()]{3,}?)\1\s*[,)]/', $line, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                self::pushInlineCandidate($results, $match[2], $relative, $lineIdx, $ownerKey, $contextLine, 'medium');
            }
        }

        if (self::isHtmlViewFile($relative)) {
            foreach (self::htmlTextCandidates($line) as $text) {
                self::pushInlineCandidate($results, $text, $relative, $lineIdx, $ownerKey, $contextLine, 'high');
            }
            foreach (self::attributeTextCandidates($line) as $text) {
                self::pushInlineCandidate($results, $text, $relative, $lineIdx, $ownerKey, $contextLine, 'medium');
            }
        }

        return array_values($results);
    }

    private static function isExcludedString(string $text): bool
    {
        $text = trim($text);
        if ($text === '' || $text === ' ') return true;
        if (self::isAttributeFragment($text) || self::isSqlFragment($text)) return true;
        if (preg_match('/^[0-9\s,.%+\-*\/]+$/', $text)) return true;
        if (preg_match('/^(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD|true|false|null|yes|no|on|off|en|ja|ne|csrf_token)$/i', $text)) return true;
        if (preg_match('#^(https?://|/|[A-Za-z0-9_/-]+\.(css|js|png|jpg|jpeg|gif|svg|webp|ico|json|php))#', $text)) return true;
        if (self::isFalsePositive($text)) return true;
        if (preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $text)) return true;
        if (preg_match('/^(id|created_at|updated_at|deleted_at|source_id|owner_id|user_id|tenant_id)$/i', $text)) return true;
        if (preg_match('/^[a-z0-9._-]+$/', $text)) return true;
        if (preg_match('/^[A-Z]{2,5}$/', $text)) return true;
        if (strlen($text) < 3) return true;
        return false;
    }

    private static function suggestKey(string $text, string $ownerKey): string
    {
        return self::ownerKeyPrefix($ownerKey) . '.' . self::baseKeyFromText($text);
    }

    private static function readDefinedKeys(string $ownerKey, string $locale): array
    {
        $localePath = self::resolveLocalePath($ownerKey, $locale);
        if ($localePath === null || !is_file($localePath)) {
            return [];
        }

        self::invalidatePhpCache($localePath);
        $data = @require $localePath;
        if (!is_array($data)) {
            return [];
        }

        return self::flattenWithKeys($data);
    }

    private static function resolveLocalePath(string $ownerKey, string $locale): ?string
    {
        $owner = self::findDiscoveredOwner($ownerKey);
        if ($owner === null) {
            return null;
        }

        $paths = isset($owner['paths']) && is_array($owner['paths']) ? $owner['paths'] : [];
        $path = isset($paths[$locale]) ? (string)$paths[$locale] : '';
        $real = $path !== '' ? realpath($path) : false;
        $appRoot = realpath(APP_ROOT);
        if ($real === false || $appRoot === false || !str_starts_with($real, $appRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    private static function flattenWithKeys(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix !== '' ? $prefix . '.' . $key : (string)$key;
            if (is_array($value)) {
                foreach (self::flattenWithKeys($value, $fullKey) as $subKey => $subValue) {
                    $result[$subKey] = $subValue;
                }
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }

    private static function invalidatePhpCache(string $path): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
        clearstatcache(true, $path);
    }
}
