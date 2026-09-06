<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

use Apps\Shell\Services\DataExchangeService;
use Apps\Shell\Services\OperatorSurfaceContributionRegistry;

require_once __DIR__ . '/../Services/DataExchangeService.php';
require_once __DIR__ . '/../Services/OperatorSurfaceContributionRegistry.php';

final class DataExchangeComposer
{
    private string $username;
    /** @var array<string,mixed> */
    private array $context;

    /** @param array<string,mixed> $context */
    public function __construct(string $username, array $context)
    {
        $this->username = $username;
        $this->context = $context;
    }

    private function tr(string $key, string $fallback, array $params = []): string
    {
        if (function_exists('t')) {
            $translated = (string)t($key, $params);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }
        if ($params === []) {
            return $fallback;
        }
        $replace = [];
        foreach ($params as $paramKey => $paramValue) {
            $replace['{' . $paramKey . '}'] = (string)$paramValue;
        }
        return strtr($fallback, $replace);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function build(array $query = []): array
    {
        $definitions = OperatorSurfaceContributionRegistry::dataExchangeDefinitions($this->context);
        $selected = strtolower(trim((string)($query['adapter'] ?? '')));
        $intent = strtolower(trim((string)($query['intent'] ?? 'import')));
        if (!in_array($intent, ['import', 'export'], true)) {
            $intent = 'import';
        }

        $selectedDefinition = null;
        foreach ($definitions as $definition) {
            if (strtolower(trim((string)($definition['key'] ?? ''))) === $selected) {
                $selectedDefinition = $definition;
                break;
            }
        }
        if ($selectedDefinition === null && $definitions !== []) {
            $selectedDefinition = $definitions[0];
        }

        $authorityRole = strtolower(trim((string)($this->context['authority_role'] ?? '')));
        $canApprove = in_array($authorityRole, ['platform_admin', 'app_admin'], true);
        $userId = (int)($this->context['user_id'] ?? 0);
        $selectedKey = strtolower(trim((string)($selectedDefinition['key'] ?? '')));
        $queueJobs = DataExchangeService::listQueueJobs($userId, $canApprove, $selectedKey, 20);

        return [
            'title' => $this->tr('operator.data_exchange.title', 'Data Exchange'),
            'subtitle' => $this->tr('operator.data_exchange.subtitle', 'System-governed import/export adapters from assigned apps and modules.'),
            'governance_note' => $this->tr('operator.data_exchange.governance_note', 'All operations are policy-governed and fully audited before execution.'),
            'empty_title' => $this->tr('operator.data_exchange.empty_title', 'No adapters available'),
            'empty_hint' => $this->tr('operator.data_exchange.empty_hint', 'Ask an admin to assign apps/modules that publish data exchange adapters.'),
            'definitions' => $definitions,
            'selected' => $selectedDefinition,
            'selected_intent' => $intent,
            'queue_jobs' => $queueJobs,
            'can_approve' => $canApprove,
            'username' => $this->username,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $query
     */
    public static function renderFragment(string $username, array $context, array $query = []): void
    {
        $composer = new self($username, $context);
        $dataExchangeData = $composer->build($query);
        include APP_ROOT . '/apps/Shell/Views/operator/data-exchange.php';
    }
}
