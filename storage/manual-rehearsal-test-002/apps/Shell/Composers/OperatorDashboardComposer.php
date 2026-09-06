<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

use Apps\Shell\Services\OperatorSurfaceContributionRegistry;

require_once __DIR__ . '/../Services/OperatorSurfaceContributionRegistry.php';

/**
 * Owns operator dashboard and focus-view rendering.
 *
 * Focus views are legacy PHP partials that rely on composer-scope variables.
 * This composer preserves that contract while keeping OperatorSurfaceComposer
 * as the orchestration facade.
 */
final class OperatorDashboardComposer
{
    /** @var array<string,mixed> */
    private array $context;

    /** @var callable(array<string,mixed>):array<string,mixed> */
    private $partsDetailPlanActionResolver;

    /** @var callable(array<string,mixed>):string */
    private $accountPanelRenderer;

    /**
     * @param array<string,mixed> $context
     * @param callable(array<string,mixed>):array<string,mixed> $partsDetailPlanActionResolver
     * @param callable(array<string,mixed>):string $accountPanelRenderer
     */
    private function __construct(array $context, callable $partsDetailPlanActionResolver, callable $accountPanelRenderer)
    {
        $this->context = $context;
        $this->partsDetailPlanActionResolver = $partsDetailPlanActionResolver;
        $this->accountPanelRenderer = $accountPanelRenderer;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $i18n
     * @param array<string,mixed> $context
     * @param callable(array<string,mixed>):array<string,mixed> $partsDetailPlanActionResolver
     * @param callable(array<string,mixed>):string $accountPanelRenderer
     */
    public static function renderFocusStack(
        array $data,
        array $i18n,
        array $context,
        callable $partsDetailPlanActionResolver,
        callable $accountPanelRenderer
    ): string {
        $composer = new self($context, $partsDetailPlanActionResolver, $accountPanelRenderer);
        return $composer->render($data, $i18n);
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $i18n
     */
    private function render(array $data, array $i18n): string
    {
        ob_start();
        $currentQuery = (array)($data['current_query'] ?? []);
        $focus = trim((string)($currentQuery['focus'] ?? ''));
        $isWorkEntryFocus = ($focus === 'work-entry');
        $isDataExchangeFocus = ($focus === 'data-exchange');
        $isCriticalFocus = ($focus === 'critical');
        $isRecentFocus = ($focus === 'recent');
        $isPartsFocus = ($focus === 'parts');
        $isPartsDetailFocus = ($focus === 'parts-detail');
        $isProductionFocus = ($focus === 'production');
        $isDemandFocus = ($focus === 'demand');
        $isOrdersFocus = ($focus === 'orders');
        $isProcessingFocus = ($focus === 'processing');
        $isPreparationFocus = ($focus === 'preparation');
        $isDispatchFocus = ($focus === 'dispatch');
        $isFulfillmentFocus = ($focus === 'fulfillment');
        $isDispatchDetailFocus = ($focus === 'dispatch-detail');
        $isCoverageFocus = ($focus === 'coverage');
        $isQcFocus = ($focus === 'qc');
        $isDispatchAdapterFocus = ($focus === 'dispatch-adapter');
        $isMachinesFocus = ($focus === 'machines');
        $isAssemblyFocus = ($focus === 'assembly');
        $isMaterialsFocus = ($focus === 'materials');
        $isSbaioFocus = ($focus === 'sbaio');
        $isAccountFocus = ($focus === 'account');
        $isNotificationsFocus = ($focus === 'notifications');
        $isMessagesFocus = ($focus === 'messages');
        $isHandoffFocus = ($focus === 'handoff');
        $isPrefsFocus = ($focus === 'preferences');
        $isTasksFocus = ($focus === 'tasks');
        $contributedFocusViewMap = OperatorSurfaceContributionRegistry::focusViewMap($data);
        $resolveFocusView = static function (string $focusKey, string $fallbackPath) use ($contributedFocusViewMap): string {
            $normalized = strtolower(trim($focusKey));
            $candidate = trim((string)($contributedFocusViewMap[$normalized] ?? ''));
            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
            return $fallbackPath;
        };

        $partsTable = (array)($data['parts_table'] ?? []);
        $partsRows = (array)($partsTable['rows'] ?? []);
        $partsBaseUrl = '/u/' . urlencode((string)$data['username']) . '/parts';
        $partsDetailBaseUrl = '/u/' . urlencode((string)$data['username']) . '/parts/detail';

        $partIdQuery = (int)($currentQuery['part_id'] ?? 0);
        $partNumberQuery = trim((string)($currentQuery['part_number'] ?? ''));
        $partQuery = trim((string)($currentQuery['q'] ?? ($currentQuery['part_q'] ?? '')));
        $partQueryNorm = strtolower($partQuery);

        $partsDetailRow = null;
        foreach ($partsRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowId = (int)($row['id'] ?? 0);
            $rowNumber = trim((string)($row['parts_number'] ?? ''));
            $rowName = trim((string)($row['parts_name'] ?? ''));
            $rowModel = trim((string)($row['model'] ?? ''));

            if ($partIdQuery > 0 && $rowId === $partIdQuery) {
                $partsDetailRow = $row;
                break;
            }

            if ($partIdQuery <= 0 && $partNumberQuery !== '' && strcasecmp($rowNumber, $partNumberQuery) === 0) {
                $partsDetailRow = $row;
                break;
            }

            if ($partIdQuery <= 0 && $partNumberQuery === '' && $partQueryNorm !== '') {
                $haystack = strtolower(trim($rowName . ' ' . $rowNumber . ' ' . $rowModel));
                if ($haystack !== '' && strpos($haystack, $partQueryNorm) !== false) {
                    $partsDetailRow = $row;
                    break;
                }
            }
        }

        $partsDetailPlanAction = is_array($partsDetailRow)
            ? ($this->partsDetailPlanActionResolver)($partsDetailRow)
            : [
                'show_action' => false,
                'action_label' => '',
                'action_url' => '',
                'action_helper' => '',
                'show_status' => false,
                'status_label' => '',
                'status_url' => '',
                'status_helper' => '',
            ];
        ?>
            <div class="app-stack">
                <?php
                $__pqa = (array)($data['profile_quick_actions'] ?? []);
                if (count($__pqa) > 0):
                ?>
                <div class="profile-quick-actions-strip" role="navigation" aria-label="<?php echo htmlspecialchars($i18n['quick_actions']); ?>">
                    <?php foreach ($__pqa as $__qa): ?>
                    <a href="<?php echo htmlspecialchars((string)($__qa['url'] ?? '#')); ?>" class="profile-qa-btn">
                        <?php if (trim((string)($__qa['icon'] ?? '')) !== ''): ?>
                            <span class="profile-qa-icon" aria-hidden="true"><?php echo htmlspecialchars((string)$__qa['icon']); ?></span>
                        <?php endif; ?>
                        <span class="profile-qa-label"><?php echo htmlspecialchars((string)($__qa['label'] ?? '')); ?></span>
                    </a>
                    <?php endforeach; unset($__pqa, $__qa); ?>
                </div>
                <?php endif; ?>
                <?php if (!$isWorkEntryFocus && !$isDataExchangeFocus && !$isCriticalFocus && !$isRecentFocus && !$isPartsFocus && !$isPartsDetailFocus && !$isProductionFocus && !$isDemandFocus && !$isOrdersFocus && !$isProcessingFocus && !$isPreparationFocus && !$isDispatchFocus && !$isDispatchDetailFocus && !$isFulfillmentFocus && !$isCoverageFocus && !$isQcFocus && !$isDispatchAdapterFocus && !$isMachinesFocus && !$isAssemblyFocus && !$isMaterialsFocus && !$isSbaioFocus && !$isAccountFocus && !$isNotificationsFocus && !$isMessagesFocus && !$isHandoffFocus && !$isPrefsFocus && !$isTasksFocus): include APP_ROOT . '/apps/Shell/Views/operator/dashboard.php'; endif; ?>

                <?php if ($isCriticalFocus): include APP_ROOT . '/apps/Shell/Views/operator/critical.php'; endif; ?>

                <?php if ($isRecentFocus): include APP_ROOT . '/apps/Shell/Views/operator/recent.php'; endif; ?>

                <?php if ($isProductionFocus): $__focusView = $resolveFocusView('production', APP_ROOT . '/apps/Shell/Views/operator/production.php'); include $__focusView; endif; ?>

                <?php if ($isDemandFocus): $__focusView = $resolveFocusView('demand', APP_ROOT . '/apps/Shell/Views/operator/demand.php'); include $__focusView; endif; ?>

                <?php if ($isOrdersFocus): $__focusView = $resolveFocusView('orders', APP_ROOT . '/apps/Shell/Views/operator/orders.php'); include $__focusView; endif; ?>

                <?php if ($isProcessingFocus): $__focusView = $resolveFocusView('processing', APP_ROOT . '/apps/Shell/Views/operator/processing.php'); include $__focusView; endif; ?>

                <?php if ($isFulfillmentFocus): $__focusView = $resolveFocusView('fulfillment', APP_ROOT . '/apps/Shell/Views/operator/fulfillment.php'); include $__focusView; endif; ?>

                <?php if ($isPreparationFocus): $__focusView = $resolveFocusView('preparation', APP_ROOT . '/apps/Shell/Views/operator/preparation.php'); include $__focusView; endif; ?>

                <?php if ($isDispatchFocus): $__focusView = $resolveFocusView('dispatch', APP_ROOT . '/apps/Shell/Views/operator/dispatch.php'); include $__focusView; endif; ?>

                <?php if ($isDispatchDetailFocus): $__focusView = $resolveFocusView('dispatch_detail', APP_ROOT . '/apps/Shell/Views/operator/dispatch-detail.php'); include $__focusView; endif; ?>

                <?php if ($isWorkEntryFocus): include APP_ROOT . '/apps/Shell/Views/operator/work-entry.php'; endif; ?>

                <?php if ($isDataExchangeFocus): include APP_ROOT . '/apps/Shell/Views/operator/data-exchange.php'; endif; ?>

                <?php if ($isPartsDetailFocus): include APP_ROOT . '/apps/Shell/Views/operator/parts-detail.php'; endif; ?>

                <?php if ($isPartsFocus): include APP_ROOT . '/apps/Shell/Views/operator/parts.php'; endif; ?>

                <?php if ($isAccountFocus): include APP_ROOT . '/apps/Shell/Views/operator/account.php'; endif; ?>

                <?php if ($isCoverageFocus): $__focusView = $resolveFocusView('coverage', APP_ROOT . '/apps/Shell/Views/operator/coverage.php'); include $__focusView; endif; ?>

                <?php if ($isQcFocus): $__focusView = $resolveFocusView('qc', APP_ROOT . '/apps/Shell/Views/operator/qc.php'); include $__focusView; endif; ?>

                <?php if ($isDispatchAdapterFocus): $__focusView = $resolveFocusView('dispatch_adapter', APP_ROOT . '/apps/Shell/Views/operator/dispatch-adapter.php'); include $__focusView; endif; ?>

                <?php if ($isMachinesFocus): $__focusView = $resolveFocusView('machines', APP_ROOT . '/apps/Shell/Views/operator/machines.php'); include $__focusView; endif; ?>

                <?php if ($isAssemblyFocus): $__focusView = $resolveFocusView('assembly', APP_ROOT . '/apps/Shell/Views/operator/assembly.php'); include $__focusView; endif; ?>

                <?php if ($isMaterialsFocus): $__focusView = $resolveFocusView('materials', APP_ROOT . '/apps/Shell/Views/operator/materials.php'); include $__focusView; endif; ?>
                <?php if ($isSbaioFocus): $__focusView = $resolveFocusView('sbaio', APP_ROOT . '/apps/Shell/Views/operator/dashboard.php'); include $__focusView; endif; ?>
                <?php if ($isNotificationsFocus): include APP_ROOT . '/apps/Shell/Views/operator/notifications.php'; endif; ?>
                <?php if ($isMessagesFocus): include APP_ROOT . '/apps/Shell/Views/operator/messages.php'; endif; ?>
                <?php if ($isHandoffFocus): $__focusView = $resolveFocusView('handoff', APP_ROOT . '/apps/Shell/Views/operator/handoff.php'); include $__focusView; endif; ?>
                <?php if ($isPrefsFocus): include APP_ROOT . '/apps/Shell/Views/operator/preferences.php'; endif; ?>
                <?php if ($isTasksFocus): include APP_ROOT . '/apps/Shell/Views/operator/tasks.php'; endif; ?>


        </div>
        <?php
        return ob_get_clean() ?: '';
    }

    public function tr(string $key, string $fallback, array $params = []): string
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
     * @param array<string,mixed> $panel
     */
    private function renderAccountPanelMarkup(array $panel): string
    {
        return ($this->accountPanelRenderer)($panel);
    }
}
