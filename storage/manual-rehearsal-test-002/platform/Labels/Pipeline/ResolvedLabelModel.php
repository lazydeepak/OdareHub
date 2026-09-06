<?php
declare(strict_types=1);

namespace Platform\Labels\Pipeline;

final class ResolvedLabelModel
{
    public readonly string $ownerKey;
    public readonly array $context;
    public readonly array $template;
    public readonly array $rules;
    public readonly array $data;
    public readonly array $resolvedBlocks;
    public readonly array $diagnostics;

    public function __construct(
        string $ownerKey,
        array $context,
        array $template,
        array $rules,
        array $data,
        array $resolvedBlocks,
        array $diagnostics
    ) {
        $this->ownerKey = $ownerKey;
        $this->context = $context;
        $this->template = $template;
        $this->rules = $rules;
        $this->data = $data;
        $this->resolvedBlocks = $resolvedBlocks;
        $this->diagnostics = $diagnostics;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ownerKey: (string)($data['owner_key'] ?? ''),
            context: (array)($data['context'] ?? []),
            template: (array)($data['template'] ?? []),
            rules: (array)($data['rules'] ?? []),
            data: (array)($data['data'] ?? []),
            resolvedBlocks: (array)($data['resolved_blocks'] ?? []),
            diagnostics: (array)($data['diagnostics'] ?? []),
        );
    }

    public function withDiagnostics(array $additionalDiagnostics): self
    {
        return new self(
            ownerKey: $this->ownerKey,
            context: $this->context,
            template: $this->template,
            rules: $this->rules,
            data: $this->data,
            resolvedBlocks: $this->resolvedBlocks,
            diagnostics: array_merge($this->diagnostics, $additionalDiagnostics),
        );
    }

    public function toArray(): array
    {
        return [
            'owner_key' => $this->ownerKey,
            'context' => $this->context,
            'template' => $this->template,
            'rules' => $this->rules,
            'data' => $this->data,
            'resolved_blocks' => $this->resolvedBlocks,
            'diagnostics' => $this->diagnostics,
        ];
    }
}
