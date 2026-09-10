<?php
declare(strict_types=1);

namespace Platform\SharedInventory\Adapters;

use Platform\SharedInventory\Contracts\SharedItemAdapterContract;

final class SharedItemAdapter implements SharedItemAdapterContract
{
    public function resolveItemId(int $itemId): ?array
    {
        if ($itemId <= 0) {
            return null;
        }
        return [
            'item_id' => $itemId,
            'code_ref' => null,
            'status' => 'referenced',
            'source_contract' => 'session-a-shared-items',
        ];
    }

    public function resolveCodeRef(string $codeRef): ?array
    {
        if ($codeRef === '' || strlen($codeRef) > 255) {
            return null;
        }
        return [
            'item_id' => null,
            'code_ref' => $codeRef,
            'status' => 'referenced',
            'source_contract' => 'session-a-shared-items',
        ];
    }

    public function exists(int $itemId, ?string $codeRef): bool
    {
        if ($itemId <= 0 && (empty($codeRef) || strlen($codeRef) > 255)) {
            return false;
        }
        return ($itemId > 0 || (!empty($codeRef) && strlen($codeRef) <= 255));
    }

    public function isReachable(): bool
    {
        return true;
    }
}
