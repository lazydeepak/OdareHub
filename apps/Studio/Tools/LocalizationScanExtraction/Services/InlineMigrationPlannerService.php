<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LocalizationScanExtraction\Services;

require_once __DIR__ . '/SuggestionQualityGateService.php';

final class InlineMigrationPlannerService
{
    private const STATE_READY = 'ready_to_migrate';
    private const STATE_REVIEW = 'needs_review';
    private const STATE_REJECTED = 'rejected';

    private const COMMON_UI_TEXTS = [
        'save' => 'common.save_action',
        'save & close' => 'common.save_close_action',
        'delete' => 'common.delete_action',
        'edit' => 'common.edit_action',
        'export' => 'common.export_action',
        'cancel' => 'common.cancel_action',
        'apply' => 'common.apply_action',
        'search' => 'common.search_action',
        'filter' => 'common.filter_action',
        'clear' => 'common.clear_action',
        'reset' => 'common.reset_action',
        'close' => 'common.close_action',
        'open' => 'common.open_action',
        'add' => 'common.add_action',
        'create' => 'common.create_action',
        'submit' => 'common.submit_action',
        'confirm' => 'common.confirm_action',
        'approve' => 'common.approve_action',
        'reject' => 'common.reject_action',
        'view' => 'common.view_action',
        'back' => 'common.back_link',
        'next' => 'common.next_link',
        'previous' => 'common.previous_link',
        'print' => 'common.print_action',
        'upload' => 'common.upload_action',
        'download' => 'common.download_action',
        'import' => 'common.import_action',
        'status' => 'common.status_label',
        'date' => 'common.date_label',
        'name' => 'common.name_label',
    ];

    private const KEY_QUALITY_EXCELLENT = 'excellent';
    private const KEY_QUALITY_GOOD = 'good';
    private const KEY_QUALITY_ACCEPTABLE = 'acceptable';
    private const KEY_QUALITY_NEEDS_REVIEW = 'needs_review';

    private const REVIEW_REASONS = [
        'domain_vocabulary',
        'ambiguous_context',
        'long_description',
        'duplicate_candidate',
        'technical_term',
        'naming_uncertain',
        'reusable_ui_term',
        'mixed_usage',
        'other',
    ];

    private const REVIEW_PRIORITIES = [
        'likely_migratable',
        'manual_review',
        'blocked',
    ];

    private string $ownerKey;
    private ?array $existingLocaleKeys = null;

    public function __construct(string $ownerKey)
    {
        $this->ownerKey = $ownerKey;
    }

    public function classifyFinding(array $finding): string
    {
        if (($finding['type'] ?? '') !== 'inline_text') {
            return self::STATE_REJECTED;
        }

        $text = trim((string)($finding['detected'] ?? ''));
        if ($text === '') {
            return self::STATE_REJECTED;
        }

        if ($this->isRejected($text, $finding)) {
            return self::STATE_REJECTED;
        }

        if ($this->isReadyToMigrate($text, $finding)) {
            return self::STATE_READY;
        }

        return self::STATE_REVIEW;
    }

    public function suggestKey(array $finding): string
    {
        return $this->keySuggestion($finding)['key'];
    }

    public function suggestEnglishValue(array $finding): string
    {
        return trim((string)($finding['detected'] ?? ''));
    }

    public function buildPlan(array $findings): array
    {
        $candidates = [];
        $counts = [self::STATE_READY => 0, self::STATE_REVIEW => 0, self::STATE_REJECTED => 0];

        foreach ($findings as $i => $finding) {
            if (($finding['type'] ?? '') !== 'inline_text') {
                continue;
            }
            if (($finding['category'] ?? '') === 'internal_string') {
                continue;
            }

            $state = $this->classifyFinding($finding);
            $keySuggestion = $this->keySuggestion($finding);
            $suggestedEnglish = $this->suggestEnglishValue($finding);
            $migrationElementType = $this->migrationElementType($finding);
            $qualityBlockReason = SuggestionQualityGateService::readyBlockReason($keySuggestion['key'], $suggestedEnglish);
            $alreadyExists = $this->localeKeyExists($keySuggestion['key']);
            if ($state === self::STATE_READY && $keySuggestion['quality'] === self::KEY_QUALITY_NEEDS_REVIEW) {
                $state = self::STATE_REVIEW;
            }
            if ($state === self::STATE_READY && !$this->isApplyCompatibleElementType($migrationElementType)) {
                $state = self::STATE_REVIEW;
            }
            if ($state === self::STATE_READY && $qualityBlockReason !== '') {
                $state = self::STATE_REJECTED;
            }
            if ($state === self::STATE_READY && $alreadyExists) {
                $state = self::STATE_REVIEW;
            }
            $review = $this->reviewIntelligence($finding, $state, $keySuggestion);
            if ($alreadyExists) {
                $review = [
                    'reason' => 'other',
                    'priority' => 'manual_review',
                    'direction' => 'No migration action required; suggested key already exists in the owner locale file.',
                ];
            }
            $counts[$state]++;

            if ($state !== self::STATE_REJECTED) {
                $candidates[] = [
                    'index' => $i,
                    'state' => $state,
                    'text' => trim((string)($finding['detected'] ?? '')),
                    'suggested_key' => $keySuggestion['key'],
                    'suggested_english' => $suggestedEnglish,
                    'key_quality' => $keySuggestion['quality'],
                    'key_quality_reason' => $keySuggestion['reason'],
                    'semantic_category' => (string)($finding['semantic_category'] ?? ''),
                    'element_type' => $migrationElementType,
                    'relevance' => (string)($finding['relevance'] ?? ''),
                    'occurrence_count' => (int)($finding['occurrence_count'] ?? 1),
                    'occurrence_files' => (array)($finding['occurrence_files'] ?? []),
                    'file' => (string)($finding['file'] ?? ''),
                    'line' => (int)($finding['line'] ?? 0),
                    'confidence' => (string)($finding['confidence'] ?? 'low'),
                    'review_reason' => $review['reason'],
                    'review_priority' => $review['priority'],
                    'suggested_direction' => $review['direction'],
                    'no_action_required' => $alreadyExists,
                ];
            }
        }

        return [
            'owner_key' => $this->ownerKey,
            'counts' => $counts,
            'key_quality_counts' => $this->keyQualityCounts($candidates),
            'review_reason_counts' => $this->reviewReasonCounts($candidates),
            'review_priority_counts' => $this->reviewPriorityCounts($candidates),
            'total' => array_sum($counts),
            'candidates' => $candidates,
        ];
    }

    private function keySuggestion(array $finding): array
    {
        $text = trim((string)($finding['detected'] ?? ''));
        if ($text === '') {
            return $this->keyResult('', self::KEY_QUALITY_NEEDS_REVIEW, 'Empty text cannot produce a stable key.');
        }

        $commonKey = $this->commonUiKey($text);
        if ($commonKey !== null) {
            return $this->keyResult($commonKey, self::KEY_QUALITY_EXCELLENT, 'Exact reusable common UI text.');
        }

        $contextual = $this->contextualKeyTail($finding);
        if ($contextual !== '') {
            $quality = str_contains($contextual, 'description') || str_contains($contextual, 'message')
                ? self::KEY_QUALITY_GOOD
                : self::KEY_QUALITY_EXCELLENT;
            return $this->keyResult($this->ownerKeyPrefix() . '.' . $contextual, $quality, 'Context-derived key from file, element, and semantic metadata.');
        }

        $tail = $this->baseKeyFromText($text);
        $key = $this->ownerKeyPrefix() . '.' . $tail;
        if ($this->isLongSentenceKey($tail, $text)) {
            return $this->keyResult($key, self::KEY_QUALITY_NEEDS_REVIEW, 'Sentence-derived key is too long or too mechanical.');
        }

        return $this->keyResult($key, self::KEY_QUALITY_ACCEPTABLE, 'Short text-derived fallback key.');
    }

    private function keyResult(string $key, string $quality, string $reason): array
    {
        return ['key' => $key, 'quality' => $quality, 'reason' => $reason];
    }

    private function keyQualityCounts(array $candidates): array
    {
        $counts = [
            self::KEY_QUALITY_EXCELLENT => 0,
            self::KEY_QUALITY_GOOD => 0,
            self::KEY_QUALITY_ACCEPTABLE => 0,
            self::KEY_QUALITY_NEEDS_REVIEW => 0,
        ];

        foreach ($candidates as $candidate) {
            $quality = (string)($candidate['key_quality'] ?? self::KEY_QUALITY_NEEDS_REVIEW);
            if (!isset($counts[$quality])) {
                $quality = self::KEY_QUALITY_NEEDS_REVIEW;
            }
            $counts[$quality]++;
        }

        return $counts;
    }

    private function reviewReasonCounts(array $candidates): array
    {
        $counts = array_fill_keys(self::REVIEW_REASONS, 0);
        foreach ($candidates as $candidate) {
            if (($candidate['state'] ?? '') !== self::STATE_REVIEW) {
                continue;
            }
            $reason = (string)($candidate['review_reason'] ?? 'other');
            if (!isset($counts[$reason])) {
                $reason = 'other';
            }
            $counts[$reason]++;
        }
        return $counts;
    }

    private function reviewPriorityCounts(array $candidates): array
    {
        $counts = array_fill_keys(self::REVIEW_PRIORITIES, 0);
        foreach ($candidates as $candidate) {
            if (($candidate['state'] ?? '') !== self::STATE_REVIEW) {
                continue;
            }
            $priority = (string)($candidate['review_priority'] ?? 'manual_review');
            if (!isset($counts[$priority])) {
                $priority = 'manual_review';
            }
            $counts[$priority]++;
        }
        return $counts;
    }

    private function reviewIntelligence(array $finding, string $state, array $keySuggestion): array
    {
        if ($state !== self::STATE_REVIEW) {
            return [
                'reason' => 'other',
                'priority' => 'manual_review',
                'direction' => 'No review action required for ready candidates.',
            ];
        }

        $text = trim((string)($finding['detected'] ?? ''));
        $semantic = (string)($finding['semantic_category'] ?? '');
        $element = $this->elementTypeForDetectedText($finding);
        $occurrences = (int)($finding['occurrence_count'] ?? 1);
        $relevance = (string)($finding['relevance'] ?? '');
        $quality = (string)($keySuggestion['quality'] ?? self::KEY_QUALITY_NEEDS_REVIEW);

        if ($this->isTechnicalString($text) || $this->isKnownTechnicalTerm($text) || $this->looksLikeConfigFragment($text)) {
            return [
                'reason' => 'technical_term',
                'priority' => 'blocked',
                'direction' => 'Keep as fixed technical vocabulary unless product copy requires translation.',
            ];
        }

        if ($this->looksLikeSentence($text) || strlen($text) > 80) {
            return [
                'reason' => 'long_description',
                'priority' => 'manual_review',
                'direction' => 'Rewrite or approve concise copy before migration.',
            ];
        }

        if ($occurrences > 1 && count((array)($finding['occurrence_files'] ?? [])) > 1) {
            return [
                'reason' => 'mixed_usage',
                'priority' => 'manual_review',
                'direction' => 'Confirm one shared meaning across files before extracting.',
            ];
        }

        if ($occurrences > 1) {
            return [
                'reason' => 'duplicate_candidate',
                'priority' => 'manual_review',
                'direction' => 'Check whether duplicate text should use one shared key.',
            ];
        }

        if ($this->commonUiKey($text) !== null || $this->looksReusableUiTerm($text, $semantic, $element)) {
            return [
                'reason' => 'reusable_ui_term',
                'priority' => 'likely_migratable',
                'direction' => 'Create or reuse a shared UI key instead of an owner-specific key.',
            ];
        }

        if ($this->looksLikeDomainVocabulary($text)) {
            return [
                'reason' => 'domain_vocabulary',
                'priority' => 'likely_migratable',
                'direction' => 'Create shared manufacturing terminology key or owner terminology key.',
            ];
        }

        if ($quality === self::KEY_QUALITY_NEEDS_REVIEW || $element === 'unknown') {
            return [
                'reason' => 'naming_uncertain',
                'priority' => 'manual_review',
                'direction' => 'Choose a stable key name before migration.',
            ];
        }

        if ($relevance === 'low' || $semantic === 'unknown' || $semantic === '') {
            return [
                'reason' => 'ambiguous_context',
                'priority' => 'manual_review',
                'direction' => 'Inspect nearby UI context before deciding key scope.',
            ];
        }

        return [
            'reason' => 'other',
            'priority' => 'manual_review',
            'direction' => 'Review manually before migration.',
        ];
    }

    private function contextualKeyTail(array $finding): string
    {
        $text = trim((string)($finding['detected'] ?? ''));
        $lower = strtolower($text);
        $fileStem = $this->fileStem($finding);
        $element = $this->elementTypeForDetectedText($finding);
        $semantic = (string)($finding['semantic_category'] ?? '');

        $verb = $this->leadingActionVerb($text);
        if ($element === 'heading') {
            if ($verb !== null) {
                return $verb . '_title';
            }
            if (!$this->looksLikeSentence($text)) {
                return $this->shortTextStem($text) . '_title';
            }
            if ($fileStem !== '') {
                return $fileStem . '_title';
            }
            return 'title';
        }

        if ($element === 'table_header') {
            return $this->shortTextStem($text) . '_column';
        }

        if ($element === 'link') {
            if ($verb !== null) {
                return $verb . '_link';
            }
            if (str_contains($lower, 'queue')) {
                return 'queue_link';
            }
            if ($fileStem !== '') {
                return $fileStem . '_link';
            }
        }

        if ($element === 'button') {
            if ($verb !== null) {
                return $verb . '_action';
            }
            return $this->shortTextStem($text) . '_action';
        }

        if ($this->looksLikeColonLabel($text) || $semantic === 'label' || $element === 'label') {
            return $this->shortTextStem($text) . '_label';
        }

        if (str_contains($lower, 'no recent') || str_contains($lower, 'no ') && str_contains($lower, 'found')) {
            $subject = $this->subjectFromText($text);
            return ($subject !== '' ? $subject : ($fileStem !== '' ? $fileStem : 'records')) . '_empty_state';
        }

        if ($this->looksLikeSentence($text)) {
            if (str_contains($lower, 'unavailable')) {
                return ($fileStem !== '' ? $fileStem : 'resource') . '_unavailable_message';
            }
            if (str_contains($lower, 'active')) {
                return ($fileStem !== '' ? $fileStem : 'resource') . '_active_message';
            }
            if (str_contains($lower, 'export')) {
                return 'export_description';
            }
            if (str_contains($lower, 'form')) {
                return 'form_description';
            }
            if ($semantic === 'error_message' || $semantic === 'confirmation') {
                return ($fileStem !== '' ? $fileStem : 'status') . '_message';
            }
            if ($fileStem !== '') {
                return $fileStem . '_description';
            }
            return 'description';
        }

        if ($semantic === 'status') {
            return $this->shortTextStem($text) . '_message';
        }

        if ($semantic === 'action') {
            if ($verb !== null) {
                return $verb . '_action';
            }
            if (str_contains($lower, 'export')) {
                return 'export_message';
            }
            return $this->shortTextStem($text) . '_action';
        }

        if ($element === 'paragraph' || $element === 'div' || $semantic === 'help') {
            return ($fileStem !== '' ? $fileStem : $this->shortTextStem($text)) . '_description';
        }

        return '';
    }

    private function fileStem(array $finding): string
    {
        $file = strtolower((string)($finding['file'] ?? ''));
        $base = pathinfo($file, PATHINFO_FILENAME);
        $base = preg_replace('/[^a-z0-9]+/', '_', (string)$base);
        $base = trim((string)$base, '_');
        if ($base === '' || in_array($base, ['index', 'view'], true)) {
            return '';
        }
        return $base;
    }

    private function elementTypeForDetectedText(array $finding): string
    {
        $text = trim((string)($finding['detected'] ?? ''));
        $context = (string)($finding['context'] ?? '');
        if ($text !== '' && $context !== '') {
            foreach (explode("\n", $context) as $line) {
                if (!str_contains($line, $text)) {
                    continue;
                }
                if (preg_match('/<h[1-6][^>]*>.*' . preg_quote($text, '/') . '.*<\/h[1-6]>/i', $line) === 1) {
                    return 'heading';
                }
                if (preg_match('/<button[^>]*>.*' . preg_quote($text, '/') . '.*<\/button>/i', $line) === 1) {
                    return 'button';
                }
                if (preg_match('/<a[^>]*>.*' . preg_quote($text, '/') . '.*<\/a>/i', $line) === 1) {
                    return 'link';
                }
                if (preg_match('/<th[^>]*>.*' . preg_quote($text, '/') . '.*<\/th>/i', $line) === 1) {
                    return 'table_header';
                }
                if (preg_match('/<label[^>]*>.*' . preg_quote($text, '/') . '.*<\/label>/i', $line) === 1) {
                    return 'label';
                }
                if (preg_match('/<p[^>]*>.*' . preg_quote($text, '/') . '.*<\/p>/i', $line) === 1) {
                    return 'paragraph';
                }
                if (preg_match('/<div[^>]*>.*' . preg_quote($text, '/') . '.*<\/div>/i', $line) === 1) {
                    return 'div';
                }
                break;
            }
        }

        return (string)($finding['element_type'] ?? 'unknown');
    }

    private function migrationElementType(array $finding): string
    {
        $text = trim((string)($finding['detected'] ?? ''));
        $semantic = (string)($finding['semantic_category'] ?? '');
        $element = $this->elementTypeForDetectedText($finding);

        if ($this->looksLikeColonLabel($text) || $semantic === 'label' || $element === 'label') {
            return 'label';
        }

        if (in_array($semantic, ['status', 'confirmation', 'error_message'], true)) {
            return $semantic;
        }

        if ($element === 'table_header') {
            return 'table_header';
        }

        if (in_array($element, ['heading', 'button', 'link'], true)) {
            return $element;
        }

        if ($semantic === 'help' || $this->looksLikeSentence($text) || in_array($element, ['paragraph', 'div'], true)) {
            return 'description';
        }

        return $element;
    }

    private function isApplyCompatibleElementType(string $elementType): bool
    {
        return in_array($elementType, ['heading', 'button', 'link', 'description', 'empty_state', 'table_header', 'label', 'status', 'confirmation', 'error_message'], true);
    }

    private function leadingActionVerb(string $text): ?string
    {
        if (preg_match('/^(Add|Edit|Create|Open|View|Export|Import|Save|Delete|Cancel|Apply|Approve|Reject|Back|Next|Previous)\b/i', trim($text), $m) !== 1) {
            return null;
        }
        return strtolower($m[1]);
    }

    private function shortTextStem(string $text): string
    {
        $clean = strtolower((string)preg_replace('/[^A-Za-z0-9\s]/', ' ', $text));
        $words = preg_split('/\s+/', trim($clean));
        $words = array_values(array_filter(is_array($words) ? $words : [], static fn(string $word): bool => $word !== ''));
        $words = array_slice($words, 0, 3);
        $stem = implode('_', $words);
        return $stem !== '' ? $stem : 'text';
    }

    private function subjectFromText(string $text): string
    {
        $lower = strtolower($text);
        if (str_contains($lower, 'assembly entr')) {
            return 'entries';
        }
        if (str_contains($lower, 'record')) {
            return 'records';
        }
        return '';
    }

    private function looksLikeSentence(string $text): bool
    {
        $trimmed = trim($text);
        if (str_contains($trimmed, '.')) {
            return true;
        }
        $words = preg_split('/\s+/', $trimmed);
        if (count(is_array($words) ? array_filter($words) : []) > 5) {
            return true;
        }
        $lower = strtolower($trimmed);
        return str_contains($lower, ' is ') || str_contains($lower, ' are ') || str_contains($lower, ' because ');
    }

    private function looksLikeColonLabel(string $text): bool
    {
        return str_ends_with(trim($text), ':');
    }

    private function isLongSentenceKey(string $tail, string $text): bool
    {
        return strlen($tail) > 36 || substr_count($tail, '_') >= 5 || $this->looksLikeSentence($text);
    }

    private function isReadyToMigrate(string $text, array $finding): bool
    {
        $cat = (string)($finding['semantic_category'] ?? '');
        $elem = $this->migrationElementType($finding);
        $conf = (string)($finding['confidence'] ?? 'low');

        if ($conf === 'low') {
            return false;
        }

        $readyCategories = ['heading', 'action', 'label', 'status', 'confirmation', 'error_message'];
        if (!in_array($cat, $readyCategories, true)) {
            return false;
        }

        if ($elem === 'unknown') {
            return false;
        }

        if ($this->hasTemplateExpression($text)) {
            return false;
        }

        if (strlen($text) <= 2) {
            return false;
        }

        if ($this->isNumericOnly($text)) {
            return false;
        }

        if ($this->isTechnicalString($text)) {
            return false;
        }

        return true;
    }

    private function isRejected(string $text, array $finding): bool
    {
        if (SuggestionQualityGateService::rejectionReason('', $text) !== '') {
            return true;
        }

        if (SuggestionQualityGateService::generatedJunkReason($text) !== '') {
            return true;
        }

        if ($this->hasTemplateExpression($text)) {
            return true;
        }

        if ($this->isUrlOrPath($text)) {
            return true;
        }

        if ($this->isTechnicalString($text)) {
            return true;
        }

        if (strlen($text) <= 2) {
            return true;
        }

        if ($this->isNumericOnly($text)) {
            return true;
        }

        $cat = (string)($finding['semantic_category'] ?? '');
        $elem = (string)($finding['element_type'] ?? '');

        $lowPriorityCats = ['placeholder', 'help'];
        if (in_array($cat, $lowPriorityCats, true) && $elem === 'unknown') {
            return true;
        }

        return false;
    }

    private function localeKeyExists(string $key): bool
    {
        $key = trim($key);
        if ($key === '') {
            return false;
        }

        return array_key_exists($key, $this->loadExistingLocaleKeys());
    }

    private function loadExistingLocaleKeys(): array
    {
        if ($this->existingLocaleKeys !== null) {
            return $this->existingLocaleKeys;
        }

        $path = $this->resolveLocalePath('en');
        if ($path === null || !is_file($path)) {
            $this->existingLocaleKeys = [];
            return $this->existingLocaleKeys;
        }

        $loaded = require $path;
        $this->existingLocaleKeys = is_array($loaded) ? $loaded : [];
        return $this->existingLocaleKeys;
    }

    private function resolveLocalePath(string $locale): ?string
    {
        $base = $this->resolveOwnerPath();
        if ($base === null) {
            return null;
        }

        $candidates = [
            $base . '/Resources/lang/' . $locale . '.php',
            $base . '/lang/' . $locale . '.php',
        ];
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $base . '/Resources/lang/' . $locale . '.php';
    }

    private function resolveOwnerPath(): ?string
    {
        $appRoot = $this->appRoot();
        if (str_starts_with($this->ownerKey, 'Plugin/')) {
            return $appRoot . '/plugins/' . substr($this->ownerKey, 7);
        }

        $remainder = $this->ownerKey;
        if (str_starts_with($remainder, 'Studio/Tools/')) {
            return $appRoot . '/apps/Studio/Tools/' . substr($remainder, 13);
        }

        if (str_contains($remainder, '/')) {
            $parts = explode('/', $remainder, 2);
            return $appRoot . '/apps/' . $parts[0] . '/modules/' . $parts[1];
        }

        return $appRoot . '/apps/' . $remainder;
    }

    private function appRoot(): string
    {
        if (defined('APP_ROOT')) {
            return (string)constant('APP_ROOT');
        }

        return dirname(__DIR__, 5);
    }

    private function hasTemplateExpression(string $text): bool
    {
        if (str_contains($text, '$') || str_contains($text, '{') || str_contains($text, '}')) {
            return true;
        }
        if (str_contains($text, '<?') || str_contains($text, '%>')) {
            return true;
        }
        if (preg_match('/\{\{.*?\}\}/', $text)) {
            return true;
        }
        if (preg_match('/\{%.*?%\}/', $text)) {
            return true;
        }
        return false;
    }

    private function isUrlOrPath(string $text): bool
    {
        if (preg_match('#^(https?://|/|[A-Za-z0-9_/-]+\.(css|js|png|jpg|jpeg|gif|svg|webp|ico))#', $text)) {
            return true;
        }
        if (str_contains($text, '/apps/') || str_contains($text, '/ops/')) {
            return true;
        }
        if (preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $text)) {
            return true;
        }
        return false;
    }

    private function isTechnicalString(string $text): bool
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]+::class$/', $text)) return true;
        if (preg_match('/^[A-Z_]+$/', $text) && strlen($text) >= 3) return true;
        if (preg_match('/^[a-z0-9._-]+$/', $text)) return true;
        if (preg_match('/^[a-z]+-[a-z-]+$/', $text) && substr_count($text, '-') >= 2) return true;
        if (preg_match('/^[A-Z]{2,5}$/', $text)) return true;
        if (preg_match('/^[a-z]+_[a-z_]+$/', $text) && substr_count($text, '_') >= 2) return true;
        if (preg_match('/^[A-Z][a-z]+[A-Z]/', $text)) return true;

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

    private function isKnownTechnicalTerm(string $text): bool
    {
        $normalized = strtoupper(trim($text));
        $terms = ['API', 'CSV', 'JSON', 'PDF', 'SKU', 'ID', 'IDs', 'UUID', 'SQL', 'URL', 'URI', 'HTML', 'CSS', 'JS', 'PHP'];
        return in_array($normalized, $terms, true);
    }

    private function looksReusableUiTerm(string $text, string $semantic, string $element): bool
    {
        $words = preg_split('/\s+/', trim($text));
        $wordCount = count(is_array($words) ? array_filter($words) : []);
        if ($wordCount > 3) {
            return false;
        }

        $lower = strtolower(trim($text));
        $uiWords = ['status', 'date', 'name', 'count', 'total', 'owner', 'scope', 'type', 'source', 'target', 'enabled', 'disabled'];
        if (in_array($lower, $uiWords, true)) {
            return true;
        }

        return in_array($semantic, ['action', 'label', 'status'], true)
            && in_array($element, ['button', 'link', 'label', 'table_header', 'unknown'], true);
    }

    private function looksLikeConfigFragment(string $text): bool
    {
        $trimmed = trim($text);
        if (preg_match('/^[&?][A-Za-z0-9_.-]+=/', $trimmed) === 1) {
            return true;
        }
        if (preg_match('/^[A-Za-z0-9_.-]+=([A-Za-z0-9_.-]*)$/', $trimmed) === 1) {
            return true;
        }
        return false;
    }

    private function looksLikeDomainVocabulary(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '' || $this->looksLikeSentence($trimmed)) {
            return false;
        }

        $lower = strtolower($trimmed);
        $domainWords = [
            'assembly', 'coverage', 'dispatch', 'part', 'parts', 'material', 'machine', 'shortage',
            'demand', 'planned', 'completed', 'rejected', 'order', 'orders', 'qc', 'production',
            'ledger', 'supplier', 'procurement', 'inventory', 'stock', 'workload', 'queue',
        ];
        foreach ($domainWords as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $lower) === 1) {
                return true;
            }
        }

        $words = preg_split('/\s+/', $trimmed);
        $wordCount = count(is_array($words) ? array_filter($words) : []);
        return $wordCount >= 1 && $wordCount <= 3 && preg_match('/^[A-Z][A-Za-z0-9]*(\s+[A-Z][A-Za-z0-9]*){0,2}$/', $trimmed) === 1;
    }

    private function isNumericOnly(string $text): bool
    {
        return preg_match('/^[0-9\s,.%+\-*\/]+$/', $text) === 1;
    }

    private function commonUiKey(string $text): ?string
    {
        $trimmed = strtolower(trim($text));
        return self::COMMON_UI_TEXTS[$trimmed] ?? null;
    }

    private function ownerKeyPrefix(): string
    {
        $parts = explode('/', $this->ownerKey);
        $last = (string)end($parts);
        $snake = $this->slugPart($last);
        return $snake !== '' ? $snake : 'owner';
    }

    private function baseKeyFromText(string $text): string
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

    private function slugPart(string $part): string
    {
        $snake = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $part));
        $snake = preg_replace('/[^a-z0-9]+/', '_', $snake);
        return trim((string)$snake, '_');
    }
}
