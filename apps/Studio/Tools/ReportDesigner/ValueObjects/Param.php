<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\ValueObjects;

final class Param
{
    public readonly string $key;
    public readonly string $type;
    public readonly string $label;
    public readonly bool $required;
    public readonly array $options;
    public readonly mixed $default;

    public function __construct(array $data)
    {
        $this->key = trim((string)($data['key'] ?? ''));
        $this->type = trim((string)($data['type'] ?? 'text'));
        $this->label = trim((string)($data['label'] ?? ''));
        $this->required = !empty($data['required']);
        $this->options = array_map('strval', (array)($data['options'] ?? []));
        $this->default = array_key_exists('default', $data) ? $data['default'] : null;
    }

    public static function fromArray(array $data): ?self
    {
        $key = trim((string)($data['key'] ?? ''));
        if ($key === '') {
            return null;
        }
        return new self($data);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label,
            'required' => $this->required,
            'options' => $this->options,
            'default' => $this->default,
        ];
    }
}
