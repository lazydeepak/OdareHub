<?php
declare(strict_types=1);

namespace Platform\Style\Adapters;

use Platform\Style\Contracts\ApprovedStyleReaderContract;
use Apps\Platform\StyleRegistry\Services\ApprovedStyleRegistry;

/**
 * Thin adapter wrapping ApprovedStyleRegistry behind the segregated
 * read-only ApprovedStyleReaderContract.
 *
 * This is the SINGLE bridge between platform/Style and
 * apps/Platform/StyleRegistry. No other file under platform/Style/
 * may reference ApprovedStyleRegistry or call getValue() directly.
 *
 * Ownership boundaries:
 *   - readValue() delegates to ApprovedStyleRegistry::getValue()
 *   - isReachable() checks the storage directory independently
 *   - No write, governance, draft, approval, or mutation methods exposed
 *   - No Shell, Studio, theme, DB, HTTP, or route dependencies
 *
 * @see docs/architecture/registry-read-contract.md
 */
final class ApprovedStyleReaderAdapter implements ApprovedStyleReaderContract
{
    private ?ApprovedStyleRegistry $registry = null;

    public function readValue(string $socketKey): ?string
    {
        $registry = $this->loadRegistry();
        if ($registry === null) {
            return null;
        }

        return $registry->getValue($socketKey);
    }

    public function isReachable(): bool
    {
        $storagePath = $this->resolveStoragePath();
        if ($storagePath === null) {
            return false;
        }

        return is_dir($storagePath);
    }

    private function loadRegistry(): ?ApprovedStyleRegistry
    {
        if (!class_exists(ApprovedStyleRegistry::class)) {
            return null;
        }

        try {
            $this->registry ??= new ApprovedStyleRegistry();
        } catch (\Throwable) {
            return null;
        }

        return $this->registry;
    }

    private function resolveStoragePath(): ?string
    {
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 3);
        $resolved = realpath($root);

        if ($resolved === false) {
            return null;
        }

        $candidate = $resolved . '/storage/platform/style-registry/approved-values';

        $real = realpath($candidate);
        if ($real === false || strncmp($real, $resolved . '/storage/platform/style-registry/approved-values', strlen($resolved . '/storage/platform/style-registry/approved-values')) !== 0) {
            return null;
        }

        return $real;
    }
}
