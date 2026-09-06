<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\ValueObjects;

final class ReportResource implements \ArrayAccess
{
    private const FIELD_MAP = [
        'report_key', 'owner', 'owner_app', 'owner_module', 'module_dir',
        'title', 'description', 'category', 'visualization',
        'permission', 'lifecycle',
        'view', 'export_view', 'pdf_views',
        'parameters', 'export_formats', 'export_sources',
        'scope',
    ];

    public readonly string $report_key;
    public readonly string $owner;

    public readonly string $owner_app;
    public readonly string $owner_module;
    public readonly string $module_dir;

    public readonly string $title;
    public readonly string $description;
    public readonly string $category;
    public readonly string $visualization;

    public readonly string $permission;
    public readonly string $lifecycle;

    public readonly string $view;
    public readonly ?string $export_view;
    public readonly array $pdf_views;

    public readonly array $parameters;
    public readonly array $export_formats;
    public readonly ?array $export_sources;

    public readonly string $scope;

    public function __construct(array $data)
    {
        $this->report_key = trim((string)($data['report_key'] ?? ''));
        $this->owner = strtolower(trim((string)($data['owner'] ?? 'module')));
        $this->owner_app = trim((string)($data['owner_app'] ?? ''));
        $this->owner_module = trim((string)($data['owner_module'] ?? ''));
        $this->module_dir = trim((string)($data['module_dir'] ?? ''));
        $this->title = trim((string)($data['title'] ?? ''));
        $this->description = trim((string)($data['description'] ?? ''));
        $this->category = trim((string)($data['category'] ?? 'operational'));
        $this->visualization = trim((string)($data['visualization'] ?? 'table'));
        $this->permission = trim((string)($data['permission'] ?? ''));
        $this->lifecycle = strtolower(trim((string)($data['lifecycle'] ?? 'active_only')));
        $this->view = trim((string)($data['view'] ?? ''));
        $this->export_view = self::nullifyEmpty(trim((string)($data['export_view'] ?? '')));
        $this->pdf_views = $this->parseStringArray($data['pdf_views'] ?? []);
        $this->parameters = $this->parseParams($data['parameters'] ?? []);
        $this->export_formats = $this->parseExportFormats($data);
        $this->export_sources = $this->parseExportSources($data['export_sources'] ?? null);
        $this->scope = trim((string)($data['scope'] ?? ''));
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return [
            'report_key' => $this->report_key,
            'owner' => $this->owner,
            'owner_app' => $this->owner_app,
            'owner_module' => $this->owner_module,
            'module_dir' => $this->module_dir,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'visualization' => $this->visualization,
            'permission' => $this->permission,
            'lifecycle' => $this->lifecycle,
            'view' => $this->view,
            'export_view' => $this->export_view,
            'pdf_views' => $this->pdf_views,
            'parameters' => array_map(fn(Param $p) => $p->toArray(), $this->parameters),
            'export_formats' => $this->export_formats,
            'export_sources' => $this->export_sources !== null
                ? array_map(fn(ExportSource $s) => $s->toArray(), $this->export_sources)
                : null,
            'scope' => $this->scope,
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset)) {
            return false;
        }
        if (!in_array($offset, self::FIELD_MAP, true)) {
            return false;
        }
        return $this->offsetGet($offset) !== null;
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!is_string($offset)) {
            return null;
        }
        return match ($offset) {
            'report_key' => $this->report_key,
            'owner' => $this->owner,
            'owner_app' => $this->owner_app,
            'owner_module' => $this->owner_module,
            'module_dir' => $this->module_dir,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'visualization' => $this->visualization,
            'permission' => $this->permission,
            'lifecycle' => $this->lifecycle,
            'view' => $this->view,
            'export_view' => $this->export_view,
            'pdf_views' => $this->pdf_views,
            'parameters' => $this->parameters,
            'export_formats' => $this->export_formats,
            'export_sources' => $this->export_sources,
            'scope' => $this->scope,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('ReportResource is read-only');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('ReportResource is read-only');
    }

    private function parseStringArray(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }
        return array_values(array_map('strval', $input));
    }

    private function parseParams(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }
        $params = [];
        foreach ($input as $entry) {
            if (is_array($entry)) {
                $param = Param::fromArray($entry);
                if ($param !== null) {
                    $params[] = $param;
                }
            }
        }
        return $params;
    }

    private function parseExportFormats(array $data): array
    {
        if (isset($data['export_formats']) && is_array($data['export_formats'])) {
            return $this->parseStringArray($data['export_formats']);
        }
        $hasExportView = isset($data['export_view'])
            && trim((string)$data['export_view']) !== '';
        return $hasExportView ? ['csv'] : [];
    }

    private function parseExportSources(mixed $input): ?array
    {
        if ($input === null) {
            return null;
        }
        if (is_array($input) && isset($input['table'])) {
            return [new ExportSource($input)];
        }
        if (is_array($input)) {
            $sources = [];
            foreach ($input as $entry) {
                if (is_array($entry)) {
                    $sources[] = new ExportSource($entry);
                }
            }
            return $sources !== [] ? $sources : null;
        }
        return null;
    }

    private static function nullifyEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
