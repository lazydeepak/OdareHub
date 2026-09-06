<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services;

final class CssLiveEditorTargetService
{
    /**
     * @return array{valid:bool,target:array<string,mixed>,error:string}
     */
    public static function resolveSelection(string $targetId, string $manualRoute, string $templateTargetId = ''): array
    {
        $target = null;
        if (trim($templateTargetId) !== '') {
            $target = CssLiveEditorTemplateTargetService::find($templateTargetId);
        } elseif (trim($manualRoute) !== '') {
            $target = CssLiveEditorTargetProvider::findByRoute($manualRoute);
            if ($target === null) {
                return [
                    'valid' => false,
                    'target' => [],
                    'error' => 'target_not_registered',
                ];
            }
        } else {
            $target = CssLiveEditorTargetProvider::find($targetId);
        }

        if ($target === null && trim($targetId) === '' && trim($manualRoute) === '' && trim($templateTargetId) === '') {
            $target = CssLiveEditorTargetProvider::defaultTarget();
        }

        if ($target === null) {
            return [
                'valid' => false,
                'target' => [],
                'error' => 'target_not_registered',
            ];
        }

        return [
            'valid' => !empty($target['eligible']),
            'target' => $target,
            'error' => !empty($target['eligible']) ? '' : (string)($target['reason'] ?? 'target_unavailable'),
        ];
    }
}
