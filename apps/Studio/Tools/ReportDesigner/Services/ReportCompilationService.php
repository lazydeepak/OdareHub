<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\Services;

use Apps\Studio\Tools\ReportDesigner\ValueObjects\CompilationResult;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ReportResource;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ResolvedReportContract;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ValidationResult;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ValidationStage;

final class ReportCompilationService
{
    private ReportValidationService $validator;

    public function __construct(?ReportValidationService $validator = null)
    {
        $this->validator = $validator ?? new ReportValidationService();
    }

    public function compile(array $sourceData, array $options = []): CompilationResult
    {
        $resource = $this->toReportResource($sourceData);
        $validation = $this->validator->validate($resource, $options);

        if (!$validation->passed) {
            return new CompilationResult(false, null, null, $validation);
        }

        $contract = $this->resolveToContract($resource, $options);

        return new CompilationResult(true, $resource, $contract, $validation);
    }

    public function validateOnly(array $sourceData): ValidationResult
    {
        $resource = $this->toReportResource($sourceData);
        return $this->validator->validate($resource);
    }

    public function resolve(ReportResource $resource, array $options = []): ResolvedReportContract
    {
        return $this->resolveToContract($resource, $options);
    }

    private function toReportResource(array $sourceData): ReportResource
    {
        return new ReportResource($sourceData);
    }

    private function resolveToContract(ReportResource $resource, array $options = []): ResolvedReportContract
    {
        return new ResolvedReportContract($resource, $options);
    }
}
