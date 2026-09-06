<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\ValueObjects;

final class ValidationResult
{
    public readonly bool $passed;
    public readonly array $errors;
    public readonly array $warnings;
    public readonly array $info;

    private array $all;

    public function __construct(array $errors, array $warnings, array $info)
    {
        $this->errors = array_values($errors);
        $this->warnings = array_values($warnings);
        $this->info = array_values($info);
        $this->all = array_merge($this->errors, $this->warnings, $this->info);
        $this->passed = $this->hasErrors() === false && $this->hasCritical() === false;
    }

    public function hasCritical(): bool
    {
        foreach ($this->all as $msg) {
            if ($msg->severity === 'critical') {
                return true;
            }
        }
        return false;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function allMessages(): array
    {
        return $this->all;
    }

    public function forField(string $field): array
    {
        return array_values(array_filter(
            $this->all,
            fn(ValidationMessage $m) => $m->field === $field,
        ));
    }

    public function forSeverity(string $severity): array
    {
        return array_values(array_filter(
            $this->all,
            fn(ValidationMessage $m) => $m->severity === $severity,
        ));
    }

    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'errors' => array_map(fn(ValidationMessage $m) => $m->toArray(), $this->errors),
            'warnings' => array_map(fn(ValidationMessage $m) => $m->toArray(), $this->warnings),
            'info' => array_map(fn(ValidationMessage $m) => $m->toArray(), $this->info),
        ];
    }
}
