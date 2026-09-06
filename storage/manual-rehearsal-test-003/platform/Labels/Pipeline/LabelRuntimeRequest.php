<?php
declare(strict_types=1);

namespace Platform\Labels\Pipeline;

final class LabelRuntimeRequest
{
    public readonly string $ownerKey;
    public readonly string $contextKey;
    public readonly string $templateKey;
    public readonly array $dataPayload;
    public readonly string $outputTarget;
    public readonly array $options;
    public readonly array $diagnostics;

    public function __construct(
        string $ownerKey,
        string $contextKey,
        string $templateKey,
        array $dataPayload,
        string $outputTarget,
        array $options = [],
        array $diagnostics = []
    ) {
        $this->ownerKey = $ownerKey;
        $this->contextKey = $contextKey;
        $this->templateKey = $templateKey;
        $this->dataPayload = $dataPayload;
        $this->outputTarget = $outputTarget;
        $this->options = $options;
        $this->diagnostics = $diagnostics;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ownerKey: (string)($data['owner_key'] ?? ''),
            contextKey: (string)($data['context_key'] ?? ''),
            templateKey: (string)($data['template_key'] ?? ''),
            dataPayload: (array)($data['data_payload'] ?? []),
            outputTarget: (string)($data['output_target'] ?? ''),
            options: (array)($data['options'] ?? []),
            diagnostics: (array)($data['diagnostics'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'owner_key' => $this->ownerKey,
            'context_key' => $this->contextKey,
            'template_key' => $this->templateKey,
            'data_payload' => $this->dataPayload,
            'output_target' => $this->outputTarget,
            'options' => $this->options,
            'diagnostics' => $this->diagnostics,
        ];
    }
}
