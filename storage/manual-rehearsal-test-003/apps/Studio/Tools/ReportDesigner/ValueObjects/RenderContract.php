<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\ValueObjects;

final class RenderContract
{
    public readonly string $report_key;
    public readonly string $view;
    public readonly string $module_dir;
    public readonly string $resolved_view_path;
    public readonly array $parameters;

    public function __construct(ReportResource $r, array $resolutionData)
    {
        $this->report_key = $r->report_key;
        $this->view = $r->view;
        $moduleDir = $resolutionData['moduleDir'] ?? $r->module_dir;
        $this->module_dir = $moduleDir;
        $this->resolved_view_path = rtrim($moduleDir, '/') . '/Views/' . ltrim($r->view, '/') . '.php';
        $this->parameters = $r->parameters;
    }

    public function toArray(): array
    {
        return [
            'report_key' => $this->report_key,
            'view' => $this->view,
            'module_dir' => $this->module_dir,
            'resolved_view_path' => $this->resolved_view_path,
            'parameters' => array_map(fn(Param $p) => $p->toArray(), $this->parameters),
        ];
    }
}
