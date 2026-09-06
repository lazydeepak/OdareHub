<?php
declare(strict_types=1);

namespace Platform\Labels\Pipeline;

use Platform\Labels\Pipeline\Adapters\HtmlPreviewAdapter;

final class PipelineCoordinator
{
    public static function run(LabelRuntimeRequest $request): array
    {
        $allDiagnostics = [];

        $validationDiags = RequestValidator::validate($request);
        $blocking = array_filter(
            $validationDiags,
            fn($d) => in_array($d['severity'] ?? '', ['ERROR', 'FAIL'], true)
        );

        if (count($blocking) > 0) {
            $allDiagnostics = array_merge($allDiagnostics, $validationDiags);
            return [
                'model' => null,
                'html' => '<div class="lp-error" style="padding: 16px; background: #fff0f0; border: 1px solid #d32f2f; border-radius: 6px; color: #d32f2f; font-family: sans-serif; font-size: 13px;"><strong>Validation Failed</strong><p style="margin: 4px 0 0; font-size: 12px;">' . count($blocking) . ' blocking issue(s) prevent rendering.</p></div>',
                'diagnostics' => $allDiagnostics,
            ];
        }
        $allDiagnostics = array_merge($allDiagnostics, $validationDiags);

        $resolution = ResourceResolver::resolve($request);
        $resolutionDiags = $resolution['diagnostics'] ?? [];
        $allDiagnostics = array_merge($allDiagnostics, $resolutionDiags);

        $resolutionBlocking = array_filter(
            $resolutionDiags,
            fn($d) => in_array($d['severity'] ?? '', ['ERROR', 'FAIL'], true)
        );
        if (count($resolutionBlocking) > 0) {
            return [
                'model' => null,
                'html' => HtmlPreviewAdapter::renderError(
                    'Resource resolution failed',
                    $resolutionDiags
                ),
                'diagnostics' => $allDiagnostics,
            ];
        }

        $model = ModelBuilder::build($request, $resolution);
        $allDiagnostics = array_merge($allDiagnostics, $model->diagnostics);

        $modelDiagBlocking = array_filter(
            $model->diagnostics,
            fn($d) => in_array($d['severity'] ?? '', ['ERROR', 'FAIL'], true)
        );
        if (count($modelDiagBlocking) > 0) {
            return [
                'model' => $model,
                'html' => HtmlPreviewAdapter::renderError(
                    'Model building produced blocking diagnostics',
                    $model->diagnostics
                ),
                'diagnostics' => $allDiagnostics,
            ];
        }

        $html = HtmlPreviewAdapter::render($model);

        return [
            'model' => $model,
            'html' => $html,
            'diagnostics' => $allDiagnostics,
        ];
    }
}
