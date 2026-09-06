<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\ValueObjects;

final class ExportSource
{
    public readonly string $table;
    public readonly string $order_by;
    public readonly string $direction;
    public readonly string $filename_prefix;

    public function __construct(array $data)
    {
        $this->table = trim((string)($data['table'] ?? ''));
        $this->order_by = trim((string)($data['order_by'] ?? ''));
        $this->direction = strtoupper(trim((string)($data['direction'] ?? 'ASC')));
        $this->filename_prefix = trim((string)($data['filename_prefix'] ?? ''));
    }

    public static function fromArray(array $data): ?self
    {
        $table = trim((string)($data['table'] ?? ''));
        if ($table === '') {
            return null;
        }
        return new self($data);
    }

    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'order_by' => $this->order_by,
            'direction' => $this->direction,
            'filename_prefix' => $this->filename_prefix,
        ];
    }
}
