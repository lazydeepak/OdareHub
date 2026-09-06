<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

use Apps\Studio\ActionHandlers\OrderSubmitHandler;
use Apps\Studio\ActionHandlers\OrderArchiveHandler;
use Apps\Studio\ActionHandlers\OrderDeleteHandler;
use Apps\Studio\ActionHandlers\OrderRestoreHandler;
use Apps\Studio\ActionHandlers\OrderUpdateHandler;
use Apps\Studio\ActionHandlers\OrderTransitionHandler;
use Apps\Studio\ActionHandlers\StudioActionHandler;
use Apps\Studio\Adapters\FormAdapter;
use Apps\Studio\Adapters\KpiAdapter;
use Apps\Studio\Adapters\StudioViewAdapter;
use Apps\Studio\Adapters\TableAdapter;
use Apps\Studio\DataProviders\OrdersProvider;
use Apps\Studio\DataProviders\PartsProvider;
use Apps\Studio\DataProviders\StudioDataProvider;

require_once __DIR__ . '/../ActionHandlers/StudioActionHandler.php';
require_once __DIR__ . '/../ActionHandlers/OrderSubmitHandler.php';
require_once __DIR__ . '/../ActionHandlers/OrderUpdateHandler.php';
require_once __DIR__ . '/../ActionHandlers/OrderArchiveHandler.php';
require_once __DIR__ . '/../ActionHandlers/OrderDeleteHandler.php';
require_once __DIR__ . '/../ActionHandlers/OrderRestoreHandler.php';
require_once __DIR__ . '/../ActionHandlers/OrderTransitionHandler.php';
require_once __DIR__ . '/../Adapters/StudioViewAdapter.php';
require_once __DIR__ . '/../Adapters/TableAdapter.php';
require_once __DIR__ . '/../Adapters/FormAdapter.php';
require_once __DIR__ . '/../Adapters/KpiAdapter.php';
require_once __DIR__ . '/../DataProviders/StudioDataProvider.php';
require_once __DIR__ . '/../DataProviders/OrdersProvider.php';
require_once __DIR__ . '/../DataProviders/PartsProvider.php';

final class StudioRuntimeBindingService
{
    /**
     * @param array<string,mixed> $studioApp
     * @param array<string,mixed> $context
     */
    public static function renderStudioApp(array $studioApp, array $context): string
    {
        try {
            $messages = self::messages($context);
            $viewManifest = self::resolveViewManifest($studioApp);
            $adapterName = (string)($viewManifest['adapter'] ?? '');
            $adapter = self::resolveAdapter($adapterName);

            if (!$adapter instanceof StudioViewAdapter) {
                return self::renderFallback($messages['unsupported_view']);
            }

            $dataSource = strtolower(trim((string)($viewManifest['data_source'] ?? 'static')));
            if (!in_array($dataSource, ['static', 'mock', 'provider'], true)) {
                return self::renderFallback($messages['unsupported_data_source']);
            }

            $data = self::resolveData($viewManifest, $dataSource, $context);
            $adapterContext = [
                'app_key' => (string)($context['app_key'] ?? ''),
                'user' => is_array($context['user'] ?? null) ? $context['user'] : [],
                'mode' => (string)($context['mode'] ?? 'runtime'),
                'action_url' => (string)($context['action_url'] ?? ''),
                'csrf' => (string)($context['csrf'] ?? ''),
                'data' => $data,
                'context' => $context,
                'msg_empty' => $messages['view_empty'],
                'msg_no_rows' => $messages['table_no_rows'],
                'msg_form_readonly' => $messages['form_readonly'],
                'msg_submit' => $messages['form_submit'],
            ];

            return $adapter->render($viewManifest, $adapterContext);
        } catch (\Throwable) {
            return self::renderFallback(self::messages($context)['view_render_error']);
        }
    }

    /**
     * @param array<string,mixed> $studioApp
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function handleStudioAction(array $studioApp, string $actionKey, array $payload, array $context): array
    {
        $messages = self::messages($context);
        $resolvedActionKey = trim($actionKey);
        if ($resolvedActionKey === '') {
            return self::safeActionResponse(false, 'invalid_action', $messages['invalid_action'], [], []);
        }

        try {
            $viewManifest = self::resolveViewManifest($studioApp);
            $actions = array_values(array_filter((array)($viewManifest['actions'] ?? []), 'is_array'));
            $actionDef = null;
            foreach ($actions as $candidate) {
                $candidateKey = trim((string)($candidate['action_key'] ?? $candidate['key'] ?? ''));
                if ($candidateKey !== '' && $candidateKey === $resolvedActionKey) {
                    $actionDef = $candidate;
                    break;
                }
            }

            if (!is_array($actionDef)) {
                return self::safeActionResponse(false, 'invalid_action', $messages['invalid_action'], [], []);
            }

            $handlerName = trim((string)($actionDef['handler'] ?? ''));
            $handler = self::resolveHandler($handlerName);
            if (!$handler instanceof StudioActionHandler) {
                return self::safeActionResponse(false, 'invalid_handler', $messages['invalid_handler'], [], []);
            }

            $result = $handler->handle($payload, $context);
            $ok = !empty($result['ok']);
            $message = trim((string)($result['message'] ?? ''));
            if ($message === '') {
                $message = $ok ? $messages['action_ok'] : $messages['action_rejected'];
            }

            $code = trim((string)($result['code'] ?? ''));
            if ($code === '') {
                $code = $ok ? 'ok' : 'rejected';
            }

            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            $data = is_array($result['data'] ?? null) ? $result['data'] : [];
            return self::safeActionResponse($ok, $code, $message, $errors, $data);
        } catch (\Throwable) {
            return self::safeActionResponse(false, 'handler_error', $messages['action_rejected'], [], []);
        }
    }

    /**
     * @param array<string,mixed> $studioApp
     * @return array<string,mixed>
     */
    public static function resolveViewManifest(array $studioApp): array
    {
        $raw = is_array($studioApp['view_manifest'] ?? null) ? $studioApp['view_manifest'] : [];
        $viewKey = trim((string)($raw['view_key'] ?? 'default_view'));
        $viewKind = strtolower(trim((string)($raw['view_kind'] ?? 'table')));
        $dataSource = strtolower(trim((string)($raw['data_source'] ?? 'static')));
        $dataProvider = trim((string)($raw['data_provider'] ?? ''));
        $adapter = trim((string)($raw['adapter'] ?? 'TableAdapter'));

        if (!in_array($viewKind, ['table', 'form', 'kpi', 'cards'], true)) {
            $viewKind = 'table';
        }
        if (!in_array($dataSource, ['static', 'mock', 'provider'], true)) {
            $dataSource = 'static';
        }

        $fields = array_values(array_filter((array)($raw['fields'] ?? []), 'is_array'));
        $actions = array_values(array_filter((array)($raw['actions'] ?? []), 'is_array'));
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];

        return [
            'view_key' => $viewKey !== '' ? $viewKey : 'default_view',
            'view_kind' => $viewKind,
            'data_source' => $dataSource,
            'data_provider' => $dataProvider,
            'adapter' => $adapter !== '' ? $adapter : 'TableAdapter',
            'fields' => $fields,
            'actions' => $actions,
            'data' => $data,
        ];
    }

    private static function resolveAdapter(string $name): ?StudioViewAdapter
    {
        $allowed = [
            'TableAdapter' => TableAdapter::class,
            'FormAdapter' => FormAdapter::class,
            'KpiAdapter' => KpiAdapter::class,
        ];

        $className = $allowed[$name] ?? null;
        if (!is_string($className) || $className === '') {
            return null;
        }

        $instance = new $className();
        return $instance instanceof StudioViewAdapter ? $instance : null;
    }

    /**
     * @param array<string,mixed> $viewManifest
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function resolveData(array $viewManifest, string $dataSource, array $context): array
    {
        if ($dataSource === 'provider') {
            $providerName = trim((string)($viewManifest['data_provider'] ?? ''));
            $provider = self::resolveProvider($providerName);
            if (!$provider instanceof StudioDataProvider) {
                return [];
            }

            try {
                $result = $provider->fetch($context);
                return is_array($result) ? $result : [];
            } catch (\Throwable) {
                return [];
            }
        }

        if ($dataSource === 'static') {
            $staticData = is_array($viewManifest['data'] ?? null) ? $viewManifest['data'] : [];
            return $staticData;
        }

        $fields = array_values(array_filter((array)($viewManifest['fields'] ?? []), 'is_array'));
        $kind = strtolower(trim((string)($viewManifest['view_kind'] ?? 'table')));
        $data = [];

        if ($kind === 'kpi') {
            foreach ($fields as $idx => $field) {
                $key = trim((string)($field['key'] ?? 'kpi_' . ($idx + 1)));
                if ($key === '') {
                    continue;
                }
                $data[$key] = (string)(100 + ($idx * 15));
            }
            return $data;
        }

        if ($kind === 'form') {
            $rows = [];
            foreach ($fields as $idx => $field) {
                $key = trim((string)($field['key'] ?? 'field_' . ($idx + 1)));
                if ($key === '') {
                    continue;
                }
                $rows[$key] = (string)($field['value'] ?? '');
            }
            return $rows;
        }

        $mockRows = [];
        for ($i = 1; $i <= 3; $i++) {
            $row = [];
            foreach ($fields as $idx => $field) {
                $key = trim((string)($field['key'] ?? 'col_' . ($idx + 1)));
                if ($key === '') {
                    continue;
                }
                $row[$key] = strtoupper($key) . ' ' . $i;
            }
            if ($row !== []) {
                $mockRows[] = $row;
            }
        }

        return ['rows' => $mockRows];
    }

    private static function resolveProvider(string $name): ?StudioDataProvider
    {
        $allowed = [
            'OrdersProvider' => OrdersProvider::class,
            'PartsProvider' => PartsProvider::class,
        ];

        $className = $allowed[$name] ?? null;
        if (!is_string($className) || $className === '') {
            return null;
        }

        $instance = new $className();
        return $instance instanceof StudioDataProvider ? $instance : null;
    }

    private static function resolveHandler(string $name): ?StudioActionHandler
    {
        $allowed = [
            'OrderSubmitHandler' => OrderSubmitHandler::class,
            'OrderUpdateHandler' => OrderUpdateHandler::class,
            'OrderArchiveHandler' => OrderArchiveHandler::class,
            'OrderDeleteHandler' => OrderDeleteHandler::class,
            'OrderRestoreHandler' => OrderRestoreHandler::class,
            'OrderTransitionHandler' => OrderTransitionHandler::class,
        ];

        $className = $allowed[$name] ?? null;
        if (!is_string($className) || $className === '') {
            return null;
        }

        $instance = new $className();
        return $instance instanceof StudioActionHandler ? $instance : null;
    }

    /**
     * @param array<string,mixed> $errors
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private static function safeActionResponse(bool $ok, string $code, string $message, array $errors, array $data): array
    {
        return [
            'ok' => $ok,
            'code' => $code,
            'message' => $message,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    /** @return array<string,string> */
    private static function messages(array $context): array
    {
        $locale = strtolower(trim((string)($context['locale'] ?? ($_SESSION['locale'] ?? 'en'))));
        $dict = [
            'en' => [
                'unsupported_view' => 'Unsupported view type',
                'unsupported_data_source' => 'Unsupported data source',
                'view_render_error' => 'View rendering error',
                'view_empty' => 'No view fields configured.',
                'table_no_rows' => 'No rows available.',
                'form_readonly' => 'Read-only Preview',
                'form_submit' => 'Submit',
                'invalid_action' => 'Invalid action',
                'invalid_handler' => 'Invalid action handler',
                'action_rejected' => 'Action rejected',
                'action_ok' => 'Action accepted',
            ],
            'ja' => [
                'unsupported_view' => '未対応のビュータイプです',
                'unsupported_data_source' => '未対応のデータソースです',
                'view_render_error' => 'ビュー描画エラー',
                'view_empty' => 'ビュー項目が設定されていません。',
                'table_no_rows' => '表示する行がありません。',
                'form_readonly' => '読み取り専用プレビュー',
                'form_submit' => '送信',
                'invalid_action' => '不正なアクションです',
                'invalid_handler' => '不正なアクションハンドラーです',
                'action_rejected' => 'アクションは拒否されました',
                'action_ok' => 'アクションを受け付けました',
            ],
            'ne' => [
                'unsupported_view' => 'असमर्थित दृश्य प्रकार',
                'unsupported_data_source' => 'असमर्थित डाटा स्रोत',
                'view_render_error' => 'दृश्य रेन्डरिङ त्रुटि',
                'view_empty' => 'दृश्य फाँटहरू सेट गरिएको छैन।',
                'table_no_rows' => 'देखाउन लाइनहरू उपलब्ध छैनन्।',
                'form_readonly' => 'पढ्न-मात्र पूर्वावलोकन',
                'form_submit' => 'पेश गर्नुहोस्',
                'invalid_action' => 'अवैध कार्य',
                'invalid_handler' => 'अवैध कार्य ह्यान्डलर',
                'action_rejected' => 'कार्य अस्वीकार गरियो',
                'action_ok' => 'कार्य स्वीकार गरियो',
            ],
        ];

        return $dict[$locale] ?? $dict['en'];
    }

    private static function renderFallback(string $message): string
    {
        return '<div class="note warning">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}
