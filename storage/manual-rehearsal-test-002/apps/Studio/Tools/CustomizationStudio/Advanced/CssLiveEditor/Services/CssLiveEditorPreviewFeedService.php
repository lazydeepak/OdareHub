<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services;

use Apps\Shell\Services\StyleRegistryService;

require_once APP_ROOT . '/apps/Shell/Services/StyleRegistryService.php';

final class CssLiveEditorPreviewFeedService
{
    /**
     * @param array<string,mixed> $target
     */
    public static function render(array $target, string $themePreference): string
    {
        if (empty($target['eligible'])) {
            throw new \RuntimeException('Preview target is not eligible.');
        }

        $adapterId = trim((string)($target['adapter_id'] ?? ''));
        $body = match ($adapterId) {
            'studio.static-component-fixture.v1' => self::componentFixture(),
            'studio.static-workflow-fixture.v1' => self::workflowFixture(),
            'studio.static-shell-fixture.v1' => self::shellFixture(),
            'studio.direct-php-template.v1' => (new CssLiveEditorTemplateRenderer())->render($target),
            default => throw new \RuntimeException('Preview adapter is not registered.'),
        };

        [$mode, $style, $effectiveTheme] = self::themeParts($themePreference);
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $title = $escape((string)($target['title'] ?? 'Preview'));

        return '<!doctype html>'
            . '<html lang="en" data-theme="' . $escape($effectiveTheme) . '" data-theme-mode="' . $escape($mode)
            . '" data-color-style="' . $escape($style) . '" data-theme-preference="' . $escape($themePreference)
            . '" data-css-live-editor-feed="' . $escape($adapterId) . '">'
            . '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $title . '</title>'
            . self::previewStylesheetLinks('admin')
            . '<link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css">'
            . '<style>' . self::fixtureCss() . '</style></head>'
            . '<body><main class="cle-feed cle-feed--' . $escape((string)($target['target_type'] ?? 'provider'))
            . '">' . $body . '</main></body></html>';
    }

    private static function previewStylesheetLinks(string $surface): string
    {
        $links = '';
        foreach (StyleRegistryService::previewChain($surface) as $style) {
            $url = htmlspecialchars((string)($style['url'] ?? ''), ENT_QUOTES, 'UTF-8');
            $version = rawurlencode((string)($style['version'] ?? '1'));
            if ($url !== '') {
                $links .= '<link rel="stylesheet" href="' . $url . '?v=' . $version . '">';
            }
        }
        return $links;
    }

    private static function componentFixture(): string
    {
        return <<<'HTML'
<section class="cle-feed__hero">
  <div><span class="badge">Static fixture</span><h1>Component library</h1><p>Safe sample content for inspecting shared controls and surfaces.</p></div>
  <button type="button" class="btn btn-primary">Primary action</button>
</section>
<section class="cle-feed__grid">
  <article class="card"><h2>Controls</h2><label>Reference field<input class="input" value="Sample value" readonly></label><div class="cle-feed__actions"><button type="button" class="btn">Secondary</button><button type="button" class="btn btn-primary">Continue</button></div></article>
  <article class="card"><h2>Status</h2><div class="cle-feed__kpis"><div><span>Ready</span><strong>12</strong></div><div><span>Review</span><strong>3</strong></div><div><span>Blocked</span><strong>1</strong></div></div></article>
</section>
<section class="card"><h2>Static fixture rows</h2><div class="table-wrap"><table><thead><tr><th>Reference</th><th>Owner</th><th>Status</th></tr></thead><tbody><tr><td>DEMO-001</td><td>Example team</td><td><span class="badge">Ready</span></td></tr><tr><td>DEMO-002</td><td>Sample group</td><td><span class="badge">Review</span></td></tr></tbody></table></div></section>
HTML;
    }

    private static function workflowFixture(): string
    {
        return <<<'HTML'
<section class="cle-feed__hero"><div><span class="badge">Static fixture</span><h1>Analyze workflow</h1><p>A safe workflow scaffold with no connected actions or runtime records.</p></div></section>
<ol class="cle-feed__steps">
  <li class="is-complete"><span>1</span><div><strong>Select owner</strong><p>Example owner selected.</p></div></li>
  <li class="is-current"><span>2</span><div><strong>Analyze changes</strong><p>Fixture-only diagnostics are ready.</p></div></li>
  <li><span>3</span><div><strong>Review diff</strong><p>No runtime artifact is connected.</p></div></li>
  <li><span>4</span><div><strong>Apply</strong><p>Disabled in this preview feed.</p></div></li>
</ol>
<section class="card"><h2>Fixture diagnostics</h2><div class="cle-feed__notice">No customer data, database rows, downloads, or mutations are loaded.</div></section>
HTML;
    }

    private static function shellFixture(): string
    {
        return <<<'HTML'
<div class="cle-feed__shell">
  <aside><strong>Preview navigation</strong><span>Overview</span><span>Library</span><span>Diagnostics</span></aside>
  <section><header><strong>Studio workspace</strong><span class="badge">Fixture</span></header><div class="cle-feed__grid"><article class="card"><h2>Workspace card</h2><p>Static shell structure for layout and theme inspection.</p></article><article class="card"><h2>Activity</h2><p>Example activity content. No account or business records.</p></article></div></section>
</div>
HTML;
    }

    private static function fixtureCss(): string
    {
        return <<<'CSS'
html,body{margin:0;min-height:100%;background:var(--style-shell-bg,var(--bg,#f4f7fb));color:var(--text,#172033);font-family:var(--font-sans,system-ui,sans-serif)}
*{box-sizing:border-box}.cle-feed{display:grid;gap:16px;padding:24px}.cle-feed__hero{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.cle-feed h1,.cle-feed h2,.cle-feed p{margin:0}.cle-feed__hero div{display:grid;gap:8px}.cle-feed__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.card{display:grid;gap:14px;border:1px solid var(--style-border,#d8deea);border-radius:var(--radius-lg,14px);padding:18px;background:var(--style-content-bg,#fff);box-shadow:var(--shadow-sm,0 2px 8px rgb(15 23 42 / 8%))}.card label{display:grid;gap:7px}.cle-feed__actions{display:flex;gap:8px}.cle-feed__kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.cle-feed__kpis div{display:grid;gap:4px;border-radius:10px;padding:12px;background:var(--style-subtle-bg,#eef2f7)}.cle-feed__kpis strong{font-size:24px}.table-wrap{overflow:auto}.cle-feed table{width:100%;border-collapse:collapse}.cle-feed th,.cle-feed td{border-bottom:1px solid var(--style-border,#d8deea);padding:10px;text-align:left}.cle-feed__steps{display:grid;gap:10px;margin:0;padding:0;list-style:none}.cle-feed__steps li{display:flex;gap:12px;border:1px solid var(--style-border,#d8deea);border-radius:12px;padding:14px;background:var(--style-content-bg,#fff)}.cle-feed__steps li>span{display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:var(--style-subtle-bg,#eef2f7);font-weight:700}.cle-feed__steps li div{display:grid;gap:4px}.cle-feed__steps .is-current{border-color:var(--accent,#2563eb)}.cle-feed__notice{border-left:4px solid var(--accent,#2563eb);padding:12px;background:var(--style-subtle-bg,#eef2f7)}.cle-feed__shell{display:grid;grid-template-columns:190px minmax(0,1fr);min-height:520px;border:1px solid var(--style-border,#d8deea);border-radius:14px;overflow:hidden;background:var(--style-content-bg,#fff)}.cle-feed__shell aside{display:flex;flex-direction:column;gap:14px;padding:18px;background:var(--style-sidebar-bg,var(--style-subtle-bg,#eef2f7))}.cle-feed__shell section{display:grid;align-content:start;gap:18px;padding:18px}.cle-feed__shell header{display:flex;justify-content:space-between;gap:12px}@media(max-width:700px){.cle-feed__grid{grid-template-columns:1fr}.cle-feed__shell{grid-template-columns:1fr}.cle-feed__shell aside{display:none}}
CSS;
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private static function themeParts(string $preference): array
    {
        $normalized = strtolower(trim($preference));
        if (!preg_match('/^(system|dark|light)-([a-z0-9._-]+)$/', $normalized, $matches)) {
            $normalized = 'system-liquid-glass';
            $matches = ['system-liquid-glass', 'system', 'liquid-glass'];
        }

        $mode = (string)$matches[1];
        $style = (string)$matches[2];
        $effective = $mode === 'system' ? 'light' : $mode;
        return [$mode, $style, $effective];
    }
}
