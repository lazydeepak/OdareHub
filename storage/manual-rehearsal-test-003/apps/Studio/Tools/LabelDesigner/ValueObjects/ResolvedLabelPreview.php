<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\ValueObjects;

final class ResolvedLabelPreview
{
    public readonly array $context;
    public readonly array $template;
    public readonly array $sampleData;
    public readonly array $resolvedFields;
    public readonly array $layoutBlocks;
    public readonly array $diagnostics;

    public function __construct(
        array $context,
        array $template,
        array $sampleData,
        array $resolvedFields,
        array $layoutBlocks,
        array $diagnostics
    ) {
        $this->context = $context;
        $this->template = $template;
        $this->sampleData = $sampleData;
        $this->resolvedFields = $resolvedFields;
        $this->layoutBlocks = $layoutBlocks;
        $this->diagnostics = $diagnostics;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            context: $data['context'] ?? [],
            template: $data['template'] ?? [],
            sampleData: $data['sampleData'] ?? [],
            resolvedFields: $data['resolvedFields'] ?? [],
            layoutBlocks: $data['layoutBlocks'] ?? [],
            diagnostics: $data['diagnostics'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'context' => $this->context,
            'template' => $this->template,
            'sampleData' => $this->sampleData,
            'resolvedFields' => $this->resolvedFields,
            'layoutBlocks' => $this->layoutBlocks,
            'diagnostics' => $this->diagnostics,
        ];
    }
}
