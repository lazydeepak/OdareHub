<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\ValueObjects;

final class CompilationResult
{
    public readonly bool $succeeded;
    public readonly ?ReportResource $resource;
    public readonly ?ResolvedReportContract $contract;
    public readonly ValidationResult $validation;

    public function __construct(
        bool $succeeded,
        ?ReportResource $resource,
        ?ResolvedReportContract $contract,
        ValidationResult $validation,
    ) {
        $this->succeeded = $succeeded;
        $this->resource = $resource;
        $this->contract = $contract;
        $this->validation = $validation;
    }

    public function toArray(): array
    {
        return [
            'succeeded' => $this->succeeded,
            'resource' => $this->resource?->toArray(),
            'contract' => $this->contract?->toArray(),
            'validation' => $this->validation->toArray(),
        ];
    }
}
