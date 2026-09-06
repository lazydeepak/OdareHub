<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Services;

/**
 * Server-side validation service for Visual Customizer request readiness.
 *
 * Validates whether a Studio-local draft is eligible for future Request Approval.
 * Validation only — no request creation, approval, apply, or side effects.
 *
 * See: Contracts/visual-customizer-request-validation-service-plan.md
 */
final class VisualCustomizerRequestValidationService
{
    private const ALLOWED_SOCKET_ID = 'radius.scale';
    private const ALLOWED_VALUES = ['sharp', 'soft', 'round'];
    private const EDITABLE_SOCKET_IDS = [self::ALLOWED_SOCKET_ID];

    /**
     * Validate a draft for request readiness.
     *
     * @param array<string,mixed> $draft Studio-local draft artifact
     * @param array<string,mixed> $socketConfig Socket catalog entry for the selected socket
     * @return array{valid:bool,status:string,checks:array<string,bool>,errors:array<int,string>,warnings:array<int,string>}
     */
    public static function validate(array $draft, array $socketConfig): array
    {
        $checks = [];
        $errors = [];
        $warnings = [];

        $checks['socket_allowed'] = self::checkSocketAllowed($draft, $errors, $warnings);
        $checks['proposed_value_exists'] = self::checkProposedValueExists($draft, $errors, $warnings);
        $checks['proposed_value_allowed'] = self::checkProposedValueAllowed($draft, $errors, $warnings);
        $checks['studio_local_draft'] = self::checkStudioLocalDraft($draft, $errors, $warnings);
        $checks['draft_shape_valid'] = self::checkDraftShapeValid($draft, $errors, $warnings);
        $checks['diff_exists'] = self::checkDiffExists($draft, $socketConfig, $errors, $warnings);
        $checks['apply_disabled'] = self::checkApplyDisabled($draft, $errors, $warnings);
        $checks['runtime_untouched'] = self::checkRuntimeUntouched($draft, $errors, $warnings);

        $valid = true;
        foreach ($checks as $check => $passed) {
            if ($passed !== true) {
                $valid = false;
                break;
            }
        }

        return [
            'valid' => $valid,
            'status' => $valid ? 'eligible_for_request' : 'not_eligible',
            'checks' => $checks,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string,mixed> $draft
     * @param array<int,string> $errors
     * @param array<int,string> $warnings
     */
    private static function checkSocketAllowed(array $draft, array &$errors, array &$warnings): bool
    {
        $socketId = isset($draft['selected_socket_id']) && is_string($draft['selected_socket_id'])
            ? trim($draft['selected_socket_id'])
            : '';

        if ($socketId === self::ALLOWED_SOCKET_ID) {
            return true;
        }

        $errors[] = 'selected_socket_id must be radius.scale, got: ' . ($socketId === '' ? '(empty)' : $socketId);
        return false;
    }

    /**
     * @param array<string,mixed> $draft
     * @param array<int,string> $errors
     * @param array<int,string> $warnings
     */
    private static function checkProposedValueExists(array $draft, array &$errors, array &$warnings): bool
    {
        $value = self::extractProposedValue($draft);

        if ($value !== null && $value !== '') {
            return true;
        }

        $errors[] = 'proposed_value is missing or empty';
        return false;
    }

    /**
     * @param array<string,mixed> $draft
     * @param array<int,string> $errors
     * @param array<int,string> $warnings
     */
    private static function checkProposedValueAllowed(array $draft, array &$errors, array &$warnings): bool
    {
        $value = self::extractProposedValue($draft);

        if ($value !== null && in_array($value, self::ALLOWED_VALUES, true)) {
            return true;
        }

        $errors[] = 'proposed_value must be one of: ' . implode(', ', self::ALLOWED_VALUES)
            . ($value !== null ? ', got: ' . $value : '');
        return false;
    }

    /**
     * @param array<string,mixed> $draft
     * @param array<int,string> $errors
     * @param array<int,string> $warnings
     */
    private static function checkStudioLocalDraft(array $draft, array &$errors, array &$warnings): bool
    {
        $status = isset($draft['status']) && is_string($draft['status'])
            ? trim($draft['status'])
            : '';

        if ($status === 'local_preview_only') {
            return true;
        }

        $errors[] = 'Draft is not Studio-local (status: ' . ($status === '' ? '(empty)' : $status) . ')';
        return false;
    }

    /**
     * @param array<string,mixed> $draft
     * @param array<int,string> $errors
     * @param array<int,string> $warnings
     */
    private static function checkDraftShapeValid(array $draft, array &$errors, array &$warnings): bool
    {
        $values = isset($draft['values']) && is_array($draft['values']) ? $draft['values'] : [];

        $socketKeys = array_keys($values);
        $expectedSockets = self::EDITABLE_SOCKET_IDS;
        $unexpected = array_diff($socketKeys, $expectedSockets);
        if ($unexpected !== []) {
            $errors[] = 'Draft shape contains unexpected socket keys: ' . implode(', ', $unexpected);
            return false;
        }

        $editableSockets = isset($draft['constraints']['editable_socket_ids'])
            && is_array($draft['constraints']['editable_socket_ids'])
            ? $draft['constraints']['editable_socket_ids']
            : [];

        $editableUnexpected = array_diff($editableSockets, $expectedSockets);
        if ($editableUnexpected !== []) {
            $errors[] = 'Draft constraints contain unexpected editable socket IDs: ' . implode(', ', $editableUnexpected);
            return false;
        }

        if (!isset($values[self::ALLOWED_SOCKET_ID]) || !is_array($values[self::ALLOWED_SOCKET_ID])) {
            $errors[] = 'Draft values missing entry for ' . self::ALLOWED_SOCKET_ID;
            return false;
        }

        $socketEntry = $values[self::ALLOWED_SOCKET_ID];
        $entryKeys = array_keys($socketEntry);
        $allowedEntryKeys = ['proposed_value'];
        $extraKeys = array_diff($entryKeys, $allowedEntryKeys);
        if ($extraKeys !== []) {
            $errors[] = 'Draft socket entry contains unexpected keys: ' . implode(', ', $extraKeys);
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $draft
     * @param array<string,mixed> $socketConfig
     * @param array<int,string> $errors
     * @param array<int,string> $warnings
     */
    private static function checkDiffExists(array $draft, array $socketConfig, array &$errors, array &$warnings): bool
    {
        $proposed = self::extractProposedValue($draft);
        $default = isset($socketConfig['default_value']) && is_string($socketConfig['default_value'])
            ? trim($socketConfig['default_value'])
            : '';

        if ($proposed !== null && $proposed !== '' && $proposed !== $default) {
            return true;
        }

        $errors[] = 'Diff preview is empty or does not differ from default value';
        return false;
    }

    /**
     * @param array<string,mixed> $draft
     * @param array<int,string> $errors
     * @param array<int,string> $warnings
     */
    private static function checkApplyDisabled(array $draft, array &$errors, array &$warnings): bool
    {
        $constraints = isset($draft['constraints']) && is_array($draft['constraints'])
            ? $draft['constraints']
            : [];

        $applyEnabled = isset($constraints['apply_enabled']) && $constraints['apply_enabled'] === true;

        if (!$applyEnabled) {
            return true;
        }

        $errors[] = 'Apply must remain disabled but apply_enabled is true';
        return false;
    }

    /**
     * @param array<string,mixed> $draft
     * @param array<int,string> $errors
     * @param array<int,string> $warnings
     */
    private static function checkRuntimeUntouched(array $draft, array &$errors, array &$warnings): bool
    {
        $constraints = isset($draft['constraints']) && is_array($draft['constraints'])
            ? $draft['constraints']
            : [];

        $runtimeActivation = isset($constraints['runtime_activation_enabled'])
            && $constraints['runtime_activation_enabled'] === true;

        $shellConsumption = isset($constraints['shell_consumption_enabled'])
            && $constraints['shell_consumption_enabled'] === true;

        $registryIO = isset($constraints['platform_registry_io_enabled'])
            && $constraints['platform_registry_io_enabled'] === true;

        if (!$runtimeActivation && !$shellConsumption && !$registryIO) {
            return true;
        }

        $active = [];
        if ($runtimeActivation) {
            $active[] = 'runtime_activation_enabled';
        }
        if ($shellConsumption) {
            $active[] = 'shell_consumption_enabled';
        }
        if ($registryIO) {
            $active[] = 'platform_registry_io_enabled';
        }

        $errors[] = 'Runtime coupling detected: ' . implode(', ', $active);
        return false;
    }

    /**
     * @param array<string,mixed> $draft
     */
    private static function extractProposedValue(array $draft): ?string
    {
        $values = isset($draft['values']) && is_array($draft['values']) ? $draft['values'] : [];
        $socketEntry = isset($values[self::ALLOWED_SOCKET_ID]) && is_array($values[self::ALLOWED_SOCKET_ID])
            ? $values[self::ALLOWED_SOCKET_ID]
            : [];

        $proposed = isset($socketEntry['proposed_value']) && is_string($socketEntry['proposed_value'])
            ? trim($socketEntry['proposed_value'])
            : null;

        return $proposed;
    }
}
