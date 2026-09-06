<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\Services;

use Apps\Studio\Tools\ReportDesigner\ValueObjects\ReportResource;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ValidationMessage;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ValidationResult;
use Apps\Studio\Tools\ReportDesigner\ValueObjects\ValidationStage;

final class ReportValidationService
{
    private const VALID_OWNERS = ['app', 'module', 'platform'];
    private const VALID_LIFECYCLES = ['active_only', 'installed', 'always'];
    private const VALID_PARAM_TYPES = ['date', 'daterange', 'select', 'text', 'boolean'];
    private const VALID_EXPORT_FORMATS = ['csv', 'pdf', 'xlsx'];

    public function validate(ReportResource $resource, array $options = []): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $info = [];

        $this->collect($errors, $warnings, $info, $this->runStage($resource, ValidationStage::DECLARATION_PARSE, $options));
        $this->collect($errors, $warnings, $info, $this->runStage($resource, ValidationStage::COMPILATION, $options));

        return new ValidationResult($errors, $warnings, $info);
    }

    public function validateStage(ReportResource $resource, ValidationStage $stage, array $options = []): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $info = [];

        $this->collect($errors, $warnings, $info, $this->runStage($resource, $stage, $options));

        return new ValidationResult($errors, $warnings, $info);
    }

    private function runStage(ReportResource $resource, ValidationStage $stage, array $options): array
    {
        return match ($stage) {
            ValidationStage::DECLARATION_PARSE => array_merge(
                $this->checkReportKeyPresence($resource),
                $this->checkReportKeyConvention($resource),
                $this->checkOwner($resource),
                $this->checkTitle($resource),
                $this->checkPermission($resource),
                $this->checkLifecycle($resource),
                $this->checkView($resource),
                $this->checkParameterTypes($resource),
                $this->checkParameterKeyUniqueness($resource),
            ),
            ValidationStage::COMPILATION => array_merge(
                $this->checkViewPathExists($resource, $options),
                $this->checkExportViewPathExists($resource, $options),
                $this->checkPdfViewPathsExist($resource, $options),
                $this->checkExportSourceTable($resource),
                $this->checkExportFormats($resource),
                $this->checkDescriptionSafety($resource),
            ),
            ValidationStage::SYSTEM_TOOLS => [],
            ValidationStage::RUNTIME => [],
        };
    }

    private function collect(array &$errors, array &$warnings, array &$info, array $messages): void
    {
        foreach ($messages as $msg) {
            match ($msg->severity) {
                'critical', 'error' => $errors[] = $msg,
                'warning' => $warnings[] = $msg,
                default => $info[] = $msg,
            };
        }
    }

    private function msg(string $ruleId, string $field, string $message, string $severity, string $stage, ?string $value = null): ValidationMessage
    {
        return new ValidationMessage($ruleId, $field, $message, $severity, $stage, $value);
    }

    private function decl(string $ruleId, string $field, string $message, string $severity, ?string $value = null): ValidationMessage
    {
        return $this->msg($ruleId, $field, $message, $severity, ValidationStage::DECLARATION_PARSE->value, $value);
    }

    private function comp(string $ruleId, string $field, string $message, string $severity, ?string $value = null): ValidationMessage
    {
        return $this->msg($ruleId, $field, $message, $severity, ValidationStage::COMPILATION->value, $value);
    }

    public function checkReportKeyPresence(ReportResource $resource): array
    {
        if ($resource->report_key === '') {
            return [$this->decl('V-001', 'report_key', 'Report key must not be empty', 'error')];
        }
        return [];
    }

    public function checkReportKeyConvention(ReportResource $resource): array
    {
        if ($resource->report_key === '') {
            return [];
        }
        if (!preg_match('/^[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+$/', $resource->report_key)) {
            return [$this->decl('V-002', 'report_key', 'Report key should follow {app}.{module}.{purpose} convention', 'warning', $resource->report_key)];
        }
        return [];
    }

    public function checkOwner(ReportResource $resource): array
    {
        if (!in_array($resource->owner, self::VALID_OWNERS, true)) {
            return [$this->decl('V-003', 'owner', 'Owner must be one of: app, module, platform', 'error', $resource->owner)];
        }
        return [];
    }

    public function checkTitle(ReportResource $resource): array
    {
        if ($resource->title === '') {
            return [$this->decl('V-004', 'title', 'Title must not be empty', 'error')];
        }
        return [];
    }

    public function checkPermission(ReportResource $resource): array
    {
        if ($resource->permission === '') {
            return [$this->decl('V-005', 'permission', 'Permission must not be empty', 'error')];
        }
        return [];
    }

    public function checkLifecycle(ReportResource $resource): array
    {
        if (!in_array($resource->lifecycle, self::VALID_LIFECYCLES, true)) {
            return [$this->decl('V-006', 'lifecycle', 'Lifecycle must be one of: active_only, installed, always', 'error', $resource->lifecycle)];
        }
        return [];
    }

    public function checkView(ReportResource $resource): array
    {
        if ($resource->view === '') {
            return [$this->decl('V-007', 'view', 'View must not be empty', 'error')];
        }
        return [];
    }

    public function checkParameterTypes(ReportResource $resource): array
    {
        $messages = [];
        foreach ($resource->parameters as $param) {
            if (!in_array($param->type, self::VALID_PARAM_TYPES, true)) {
                $messages[] = $this->decl('V-008', 'parameters', "Parameter '{$param->key}' has invalid type '{$param->type}'", 'error', $param->type);
            }
        }
        return $messages;
    }

    public function checkParameterKeyUniqueness(ReportResource $resource): array
    {
        $keys = [];
        $messages = [];
        foreach ($resource->parameters as $param) {
            if (in_array($param->key, $keys, true)) {
                $messages[] = $this->decl('V-009', 'parameters', "Duplicate parameter key '{$param->key}'", 'error', $param->key);
            }
            $keys[] = $param->key;
        }
        return $messages;
    }

    public function checkViewPathExists(ReportResource $resource, array $options = []): array
    {
        if ($resource->view === '') {
            return [];
        }
        $moduleDir = $options['moduleDir'] ?? '';
        if ($moduleDir === '') {
            return [];
        }
        $path = rtrim($moduleDir, '/') . '/Views/' . ltrim($resource->view, '/') . '.php';
        if (!file_exists($path)) {
            return [$this->comp('V-010', 'view', "View file not found: {$path}", 'error', $resource->view)];
        }
        return [];
    }

    public function checkExportViewPathExists(ReportResource $resource, array $options = []): array
    {
        if ($resource->export_view === null || $resource->export_view === '') {
            return [];
        }
        $moduleDir = $options['moduleDir'] ?? '';
        if ($moduleDir === '') {
            return [];
        }
        $path = rtrim($moduleDir, '/') . '/Views/' . ltrim($resource->export_view, '/') . '.php';
        if (!file_exists($path)) {
            return [$this->comp('V-011', 'export_view', "Export view file not found: {$path}", 'warning', $resource->export_view)];
        }
        return [];
    }

    public function checkPdfViewPathsExist(ReportResource $resource, array $options = []): array
    {
        if ($resource->pdf_views === []) {
            return [];
        }
        $moduleDir = $options['moduleDir'] ?? '';
        if ($moduleDir === '') {
            return [];
        }
        $messages = [];
        foreach ($resource->pdf_views as $pdfView) {
            $path = rtrim($moduleDir, '/') . '/Views/' . ltrim($pdfView, '/') . '.php';
            if (!file_exists($path)) {
                $messages[] = $this->comp('V-012', 'pdf_views', "PDF view file not found: {$path}", 'warning', $pdfView);
            }
        }
        return $messages;
    }

    public function checkExportSourceTable(ReportResource $resource): array
    {
        if ($resource->export_sources === null || $resource->export_sources === []) {
            return [];
        }
        $messages = [];
        foreach ($resource->export_sources as $i => $source) {
            if ($source->table === '') {
                $messages[] = $this->comp('V-013', 'export_sources', "Export source #{$i} has empty table name", 'error');
            }
        }
        return $messages;
    }

    public function checkExportFormats(ReportResource $resource): array
    {
        $messages = [];
        foreach ($resource->export_formats as $format) {
            if (!in_array($format, self::VALID_EXPORT_FORMATS, true)) {
                $messages[] = $this->comp('V-014', 'export_formats', "Invalid export format '{$format}' (expected csv, pdf, or xlsx)", 'warning', $format);
            }
        }
        return $messages;
    }

    public function checkDescriptionSafety(ReportResource $resource): array
    {
        if ($resource->description === '') {
            return [];
        }
        if (preg_match('/<\/?[a-z][\s\S]*?>/i', $resource->description)) {
            return [$this->comp('V-015', 'description', 'Description should not contain HTML tags', 'warning', $resource->description)];
        }
        return [];
    }
}
