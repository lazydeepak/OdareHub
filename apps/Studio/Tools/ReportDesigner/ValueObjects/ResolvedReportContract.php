<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\ValueObjects;

final class ResolvedReportContract
{
    public readonly ReportResource $resource;
    public readonly string $resolved_module_dir;
    public readonly string $resolved_owner_app;
    public readonly string $resolved_view_path;
    public readonly bool $view_exists;
    public readonly array $resolved_export_sources;

    public function __construct(ReportResource $resource, array $resolutionData)
    {
        $this->resource = $resource;
        $moduleDir = $resolutionData['moduleDir'] ?? $resource->module_dir;
        $this->resolved_module_dir = $moduleDir;
        $this->resolved_owner_app = $resolutionData['ownerApp'] ?? $resource->owner_app;
        $this->resolved_view_path = rtrim($moduleDir, '/') . '/Views/' . ltrim($resource->view, '/') . '.php';
        $this->view_exists = false;
        $this->resolved_export_sources = $resource->export_sources ?? [];
    }

    public function forNavigation(): array
    {
        return [
            'report_key' => $this->resource->report_key,
            'title' => $this->resource->title,
            'permission' => $this->resource->permission,
            'lifecycle' => $this->resource->lifecycle,
            'category' => $this->resource->category,
            'visualization' => $this->resource->visualization,
        ];
    }

    public function forRender(): array
    {
        return [
            'report_key' => $this->resource->report_key,
            'view' => $this->resource->view,
            'module_dir' => $this->resolved_module_dir,
            'resolved_view_path' => $this->resolved_view_path,
            'parameters' => array_map(fn(Param $p) => $p->toArray(), $this->resource->parameters),
            'scope' => $this->resource->scope,
        ];
    }

    public function forExport(): array
    {
        return [
            'report_key' => $this->resource->report_key,
            'export_sources' => array_map(fn(ExportSource $s) => $s->toArray(), $this->resolved_export_sources),
            'export_formats' => $this->resource->export_formats,
        ];
    }

    public function toArray(): array
    {
        return [
            'resolved_module_dir' => $this->resolved_module_dir,
            'resolved_owner_app' => $this->resolved_owner_app,
            'resolved_view_path' => $this->resolved_view_path,
            'view_exists' => $this->view_exists,
            'resolved_export_sources' => array_map(fn(ExportSource $s) => $s->toArray(), $this->resolved_export_sources),
            'navigation' => $this->forNavigation(),
            'render' => $this->forRender(),
            'export' => $this->forExport(),
        ];
    }
}
