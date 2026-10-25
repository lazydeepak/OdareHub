<?php
declare(strict_types=1);

namespace Apps\Procurement\Services;

/**
 * DB-free boundary validator for future cross-domain intake descriptors.
 * It validates shape and derives the server-owned replay key only; it does not
 * claim actor, organization, or source-record authorization.
 */
final class ProcurementIntakeDescriptorValidator
{
    /**
     * @param array<string,mixed> $input
     * @return array{valid:bool,errors:array<int,string>,descriptor:?ProcurementIntakeDescriptor}
     */
    public static function validate(array $input): array
    {
        try {
            $descriptor = ProcurementIntakeDescriptor::fromArray($input);
            return ['valid' => true, 'errors' => [], 'descriptor' => $descriptor];
        } catch (\InvalidArgumentException $e) {
            return ['valid' => false, 'errors' => [$e->getMessage()], 'descriptor' => null];
        }
    }
}
