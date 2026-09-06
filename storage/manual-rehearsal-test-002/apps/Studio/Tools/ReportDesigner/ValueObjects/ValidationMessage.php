<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\ValueObjects;

final class ValidationMessage
{
    public readonly string $rule_id;
    public readonly string $field;
    public readonly string $message;
    public readonly string $severity;
    public readonly string $stage;
    public readonly ?string $value;

    public function __construct(
        string $ruleId,
        string $field,
        string $message,
        string $severity,
        string $stage,
        ?string $value = null,
    ) {
        $this->rule_id = $ruleId;
        $this->field = $field;
        $this->message = $message;
        $this->severity = $severity;
        $this->stage = $stage;
        $this->value = $value;
    }

    public function toArray(): array
    {
        return [
            'rule_id' => $this->rule_id,
            'field' => $this->field,
            'message' => $this->message,
            'severity' => $this->severity,
            'stage' => $this->stage,
            'value' => $this->value,
        ];
    }
}
