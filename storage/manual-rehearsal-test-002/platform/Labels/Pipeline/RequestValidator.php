<?php
declare(strict_types=1);

namespace Platform\Labels\Pipeline;

final class RequestValidator
{
    private const ALLOWED_OUTPUT_TARGETS = ['html_preview'];
    private const FORBIDDEN_OUTPUT_TARGETS = [
        'pdf', 'png', 'svg', 'zpl', 'thermal', 'browser_print',
    ];
    private const PATH_TRAVERSAL_RE = '/\.\.|\0/';

    public static function validate(LabelRuntimeRequest $request): array
    {
        $diagnostics = [];

        self::checkOwnerKey($request, $diagnostics);
        self::checkContextKey($request, $diagnostics);
        self::checkTemplateKey($request, $diagnostics);
        self::checkDataPayload($request, $diagnostics);
        self::checkOutputTarget($request, $diagnostics);
        self::checkPathTraversal($request, $diagnostics);

        return $diagnostics;
    }

    private static function diag(
        string $severity,
        string $code,
        string $message,
        string $field = ''
    ): array {
        return [
            'stage' => 'request_validation',
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'field' => $field,
        ];
    }

    private static function checkOwnerKey(LabelRuntimeRequest $request, array &$diagnostics): void
    {
        if ($request->ownerKey === '') {
            $diagnostics[] = self::diag('ERROR', 'RQ-001', 'owner_key is required', 'owner_key');
        }
    }

    private static function checkContextKey(LabelRuntimeRequest $request, array &$diagnostics): void
    {
        if ($request->contextKey === '') {
            $diagnostics[] = self::diag('ERROR', 'RQ-002', 'context_key is required', 'context_key');
        }
    }

    private static function checkTemplateKey(LabelRuntimeRequest $request, array &$diagnostics): void
    {
        if ($request->templateKey === '') {
            $diagnostics[] = self::diag('ERROR', 'RQ-003', 'template_key is required', 'template_key');
        }
    }

    private static function checkDataPayload(LabelRuntimeRequest $request, array &$diagnostics): void
    {
        if (!is_array($request->dataPayload)) {
            $diagnostics[] = self::diag('ERROR', 'RQ-004', 'data_payload must be an array', 'data_payload');
        }
    }

    private static function checkOutputTarget(LabelRuntimeRequest $request, array &$diagnostics): void
    {
        $target = $request->outputTarget;

        if ($target === '') {
            $diagnostics[] = self::diag('FAIL', 'RQ-005', 'output_target is required', 'output_target');
            return;
        }

        $lower = strtolower($target);

        foreach (self::FORBIDDEN_OUTPUT_TARGETS as $forbidden) {
            if ($lower === $forbidden) {
                $diagnostics[] = self::diag(
                    'ERROR',
                    'RQ-006',
                    "output_target '{$forbidden}' is forbidden in Phase 1",
                    'output_target'
                );
                return;
            }
        }

        if (!in_array($lower, self::ALLOWED_OUTPUT_TARGETS, true)) {
            $diagnostics[] = self::diag(
                'FAIL',
                'RQ-007',
                "output_target must be one of: " . implode(', ', self::ALLOWED_OUTPUT_TARGETS),
                'output_target'
            );
        }
    }

    private static function checkPathTraversal(LabelRuntimeRequest $request, array &$diagnostics): void
    {
        foreach (['ownerKey' => 'owner_key', 'contextKey' => 'context_key', 'templateKey' => 'template_key'] as $prop => $field) {
            $value = $request->$prop;
            if ($value !== '' && preg_match(self::PATH_TRAVERSAL_RE, $value)) {
                $diagnostics[] = self::diag(
                    'ERROR',
                    'RQ-008',
                    "{$field} contains path traversal tokens",
                    $field
                );
            }
        }
    }
}
