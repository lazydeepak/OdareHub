<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Services;

final class VisualCustomizerDraftStorageService
{
    private const DRAFT_ID = 'visual-customizer-local-preview';
    private const EDITABLE_SOCKET_ID = 'radius.scale';
    private const DRAFT_STATUS = 'local_preview_only';
    private const DRAFT_OWNER = 'studio_customization_studio';
    private const DRAFT_SCOPE = 'studio_customization_preview';

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public static function readDraftForUser(array $user): array
    {
        $path = self::draftPathForUser($user);
        if ($path === '' || !is_file($path)) {
            return self::baseDraft();
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return self::baseDraft();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::baseDraft();
        }

        return self::normalizeDraft($decoded);
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public static function saveProposedValueForUser(array $user, string $socketId, string $defaultValue, string $proposedValue): array
    {
        if (!self::isAllowedSocket($socketId)) {
            return ['ok' => false, 'error' => 'socket_not_allowed'];
        }

        $allowedValues = ['sharp', 'soft', 'round'];
        if (!in_array($proposedValue, $allowedValues, true)) {
            return ['ok' => false, 'error' => 'proposed_value_not_allowed'];
        }

        $draft = self::readDraftForUser($user);
        $draft['updated_at'] = gmdate('c');
        $draft['selected_socket_id'] = self::EDITABLE_SOCKET_ID;
        $draftValues = isset($draft['values']) && is_array($draft['values']) ? $draft['values'] : [];
        $draftValues[self::EDITABLE_SOCKET_ID] = [
            'proposed_value' => $proposedValue,
        ];
        $draft['values'] = $draftValues;

        $writeOk = self::writeDraftForUser($user, $draft);
        if (!$writeOk) {
            return ['ok' => false, 'error' => 'write_failed'];
        }

        return ['ok' => true, 'draft' => $draft];
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public static function resetProposedToDefaultForUser(array $user, string $socketId, string $defaultValue): array
    {
        if (!self::isAllowedSocket($socketId)) {
            return ['ok' => false, 'error' => 'socket_not_allowed'];
        }

        $baseline = $defaultValue === '' ? 'soft' : $defaultValue;
        return self::saveProposedValueForUser($user, $socketId, $baseline, $baseline);
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public static function discardProposedValueForUser(array $user, string $socketId, string $defaultValue): array
    {
        if (!self::isAllowedSocket($socketId)) {
            return ['ok' => false, 'error' => 'socket_not_allowed'];
        }

        $draft = self::readDraftForUser($user);
        $draft['updated_at'] = gmdate('c');
        $draft['selected_socket_id'] = self::EDITABLE_SOCKET_ID;

        $values = isset($draft['values']) && is_array($draft['values']) ? $draft['values'] : [];
        unset($values[self::EDITABLE_SOCKET_ID]);
        $draft['values'] = $values;

        $writeOk = self::writeDraftForUser($user, $draft);
        if (!$writeOk) {
            return ['ok' => false, 'error' => 'write_failed'];
        }

        return ['ok' => true, 'draft' => $draft];
    }

    /**
     * @param array<string,mixed> $user
     */
    private static function draftPathForUser(array $user): string
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return '';
        }

        return APP_ROOT
            . '/storage/studio/customization/visual-customizer/drafts/user-'
            . $userId
            . '/'
            . self::DRAFT_ID
            . '.json';
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $draft
     */
    private static function writeDraftForUser(array $user, array $draft): bool
    {
        $path = self::draftPathForUser($user);
        if ($path === '') {
            return false;
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $encoded = json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || $encoded === '') {
            return false;
        }

        return @file_put_contents($path, $encoded . PHP_EOL) !== false;
    }

    /**
     * @return array<string,mixed>
     */
    private static function baseDraft(): array
    {
        $now = gmdate('c');
        return [
            'draft_id' => self::DRAFT_ID,
            'status' => self::DRAFT_STATUS,
            'owner' => self::DRAFT_OWNER,
            'scope' => self::DRAFT_SCOPE,
            'created_at' => $now,
            'updated_at' => $now,
            'selected_socket_id' => self::EDITABLE_SOCKET_ID,
            'values' => [],
            'constraints' => [
                'editable_socket_ids' => [self::EDITABLE_SOCKET_ID],
                'apply_enabled' => false,
                'runtime_activation_enabled' => false,
                'shell_consumption_enabled' => false,
                'platform_registry_io_enabled' => false,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $draft
     * @return array<string,mixed>
     */
    private static function normalizeDraft(array $draft): array
    {
        $base = self::baseDraft();

        $base['created_at'] = is_string($draft['created_at'] ?? null) && trim((string)$draft['created_at']) !== ''
            ? (string)$draft['created_at']
            : $base['created_at'];
        $base['updated_at'] = is_string($draft['updated_at'] ?? null) && trim((string)$draft['updated_at']) !== ''
            ? (string)$draft['updated_at']
            : $base['updated_at'];

        $values = isset($draft['values']) && is_array($draft['values']) ? $draft['values'] : [];
        if (isset($values[self::EDITABLE_SOCKET_ID]) && is_array($values[self::EDITABLE_SOCKET_ID])) {
            $entry = $values[self::EDITABLE_SOCKET_ID];
            if (isset($entry['proposed_value']) && is_string($entry['proposed_value'])) {
                $candidate = trim($entry['proposed_value']);
                if (in_array($candidate, ['sharp', 'soft', 'round'], true)) {
                    $base['values'] = [
                        self::EDITABLE_SOCKET_ID => [
                            'proposed_value' => $candidate,
                        ],
                    ];
                }
            }
        }

        return $base;
    }

    private static function isAllowedSocket(string $socketId): bool
    {
        return $socketId === self::EDITABLE_SOCKET_ID;
    }
}
