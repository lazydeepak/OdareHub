<?php
declare(strict_types=1);

namespace Platform\Labels\Pipeline\Adapters;

use Platform\Labels\Pipeline\ResolvedLabelModel;

final class HtmlPreviewAdapter
{
    private const BARCODE_PLACEHOLDER = '[Barcode Placeholder]';
    private const QR_PLACEHOLDER = '[QR Placeholder]';
    private const IMAGE_PLACEHOLDER = '[Image Placeholder]';

    public static function render(ResolvedLabelModel $model): string
    {
        $ownerKey = htmlspecialchars($model->ownerKey, ENT_QUOTES, 'UTF-8');
        $labelSize = $model->template['layout']['label_size'] ?? 'unknown';
        $orientation = $model->template['layout']['orientation'] ?? 'landscape';
        $blocks = $model->resolvedBlocks;
        $activeEffects = $model->rules['active_effects'] ?? [];

        $blockHtml = '';
        foreach ($blocks as $block) {
            $blockHtml .= self::renderBlock($block);
        }

        $badgeHtml = '';
        $warningHtml = '';
        foreach ($activeEffects as $effect) {
            $et = $effect['type'] ?? '';
            $target = htmlspecialchars($effect['target'] ?? '', ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars($effect['value'] ?? '', ENT_QUOTES, 'UTF-8');
            if ($et === 'show_badge') {
                $badgeHtml .= '<span class="lp-badge">' . $value . '</span> ';
            } elseif ($et === 'show_warning') {
                $warningHtml .= '<div class="lp-warning">⚠ ' . $value . '</div>';
            }
        }

        $styleTokenOverrides = '';
        foreach ($activeEffects as $effect) {
            if (($effect['type'] ?? '') === 'set_style_token' && !empty($effect['value'])) {
                $styleTokenOverrides .= '        ' . $effect['value'] . ';' . "\n";
            }
        }

        $diagHtml = self::renderDiagnosticsSummary($model);

        return <<<HTML
<div class="lp-preview" style="max-width: 560px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 13px; line-height: 1.5; color: #1a1a1a;">
  <div class="lp-header" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #f5f5f5; border-radius: 6px 6px 0 0; border-bottom: 1px solid #ddd;">
    <strong>Label Preview</strong>
    <span style="font-size: 11px; color: #666;">{$labelSize} · {$orientation}</span>
  </div>
  <div class="lp-label" style="border: 1px solid #ccc; border-radius: 0 0 6px 6px; padding: 16px; background: #fff;{$styleTokenOverrides}">
    <div class="lp-owner" style="font-size: 10px; color: #999; margin-bottom: 8px;">{$ownerKey}</div>
    {$badgeHtml}
    {$warningHtml}
    {$blockHtml}
  </div>
  {$diagHtml}
</div>
HTML;
    }

    private static function renderBlock(array $block): string
    {
        $role = $block['role'] ?? 'unknown';
        $blockKey = htmlspecialchars($block['block_key'] ?? '', ENT_QUOTES, 'UTF-8');

        if ($role === 'title_and_identity') {
            return '<div class="lp-block lp-block-title" style="border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 12px;"></div>';
        }

        if ($role === 'field_rows') {
            $fields = $block['fields'] ?? [];
            $rows = '';
            foreach ($fields as $field) {
                $label = htmlspecialchars($field['label'] ?? '', ENT_QUOTES, 'UTF-8');
                $value = self::renderFieldValue($field);
                $styleOverride = '';
                foreach ($block['effects'] ?? [] as $eff) {
                    if (($eff['type'] ?? '') === 'set_style_token' && ($eff['target'] ?? '') === ($field['field_key'] ?? '')) {
                        $styleOverride = ' style="' . htmlspecialchars($eff['value'] ?? '', ENT_QUOTES, 'UTF-8') . '"';
                    }
                }
                $rows .= <<<ROW
    <div class="lp-field-row" style="display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px dotted #eee;"{$styleOverride}>
      <span class="lp-field-label" style="font-weight: 600; color: #555; font-size: 11px;">{$label}</span>
      <span class="lp-field-value" style="color: #1a1a1a;">{$value}</span>
    </div>
ROW;
            }
            return '<div class="lp-block lp-block-fields" style="margin-bottom: 8px;">' . $rows . '</div>';
        }

        if ($role === 'owner_signoff_qr_placeholder') {
            return '<div class="lp-block lp-block-footer" style="margin-top: 12px; padding-top: 8px; border-top: 1px solid #ddd; text-align: center; font-size: 10px; color: #aaa;">' . self::QR_PLACEHOLDER . '</div>';
        }

        return '<div class="lp-block lp-block-' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . '" style="padding: 4px 0;">[' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . ']</div>';
    }

    private static function renderFieldValue(array $field): string
    {
        $value = $field['value'] ?? '';
        $sourceColumn = $field['source_column'] ?? '';

        if ($sourceColumn === 'barcode' || $sourceColumn === 'barcode_text') {
            return self::BARCODE_PLACEHOLDER;
        }

        if ($sourceColumn === 'qr_code' || str_contains($sourceColumn, 'qr')) {
            return self::QR_PLACEHOLDER;
        }

        if (in_array($sourceColumn, ['image', 'photo', 'signature', 'logo'], true)) {
            return self::IMAGE_PLACEHOLDER;
        }

        if ($value === '' || $value === null) {
            return '<span style="color: #ccc;">—</span>';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    public static function renderError(string $title, array $diagnostics): string
    {
        $items = '';
        foreach ($diagnostics as $d) {
            $sev = $d['severity'] ?? 'INFO';
            $code = $d['code'] ?? '';
            $msg = htmlspecialchars($d['message'] ?? '', ENT_QUOTES, 'UTF-8');
            $color = match ($sev) {
                'ERROR' => '#d32f2f',
                'FAIL' => '#e65100',
                'WARN' => '#f9a825',
                default => '#388e3c',
            };
            $items .= '<div style="font-size: 11px; padding: 2px 0;"><span style="color: ' . $color . '; font-weight: 600;">[' . $sev . ']</span> <span style="color: #666;">' . $code . ':</span> ' . $msg . '</div>';
        }

        return '<div style="padding: 16px; background: #fff0f0; border: 1px solid #d32f2f; border-radius: 6px; color: #d32f2f; font-family: sans-serif; font-size: 13px; max-width: 560px; margin: 0 auto;"><strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong><div style="margin-top: 8px;">' . $items . '</div></div>';
    }

    private static function renderDiagnosticsSummary(ResolvedLabelModel $model): string
    {
        $diags = $model->diagnostics;
        if (count($diags) === 0) {
            return '';
        }

        $errors = array_filter($diags, fn($d) => ($d['severity'] ?? '') === 'ERROR');
        $fails = array_filter($diags, fn($d) => ($d['severity'] ?? '') === 'FAIL');
        $warnings = array_filter($diags, fn($d) => ($d['severity'] ?? '') === 'WARN');

        $items = '';
        foreach ($diags as $d) {
            $sev = $d['severity'] ?? 'INFO';
            $code = $d['code'] ?? '';
            $msg = htmlspecialchars($d['message'] ?? '', ENT_QUOTES, 'UTF-8');
            $color = match ($sev) {
                'ERROR' => '#d32f2f',
                'FAIL' => '#e65100',
                'WARN' => '#f9a825',
                default => '#388e3c',
            };
            $items .= '<div style="font-size: 11px; padding: 2px 0;"><span style="color: ' . $color . '; font-weight: 600;">[' . $sev . ']</span> <span style="color: #666;">' . $code . ':</span> ' . $msg . '</div>';
        }

        $summary = '';
        if (count($errors) > 0 || count($fails) > 0) {
            $summary = '<span style="color: #d32f2f; font-weight: 600;">⚠ ' . (count($errors) + count($fails)) . ' issue(s) block rendering</span>';
        } elseif (count($warnings) > 0) {
            $summary = '<span style="color: #f9a825; font-weight: 600;">⚠ ' . count($warnings) . ' warning(s)</span>';
        } else {
            $summary = '<span style="color: #388e3c; font-weight: 600;">✓ All checks pass</span>';
        }

        return <<<HTML
<div class="lp-diagnostics" style="margin-top: 12px; padding: 8px 12px; background: #fafafa; border-radius: 6px; border: 1px solid #eee;">
  <div style="margin-bottom: 4px;">{$summary}</div>
  {$items}
</div>
HTML;
    }
}
