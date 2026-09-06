<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

$compatSidebarBuilderPath = APP_ROOT . '/app/Core/SidebarBuilder.php';
if (is_file($compatSidebarBuilderPath)) {
    require_once $compatSidebarBuilderPath;
}

/**
 * ShellRuntimeMenuComposer
 *
 * Shell-owned runtime menu composition boundary.
 *
 * Current compatibility implementation delegates to the existing Core SidebarBuilder
 * so runtime behavior remains unchanged while naming and ownership move toward
 * the Navigation/Menu Naming Conventions baseline.
 *
 * Ownership rules:
 * - Shell owns runtime menu composition and rendering boundary.
 * - Apps/modules/plugins own navigation contributions.
 * - Core SidebarBuilder remains a compatibility implementation until it can be
 *   safely retired or moved with explicit Core-change approval.
 * - This composer must not hardcode business-domain menu items.
 */
final class ShellRuntimeMenuComposer
{
    /**
     * Compose runtime menu payload for Shell chrome.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function compose(array $context): array
    {
        if (!class_exists('\\App\\Core\\SidebarBuilder')) {
            return [
                'sections' => [],
                'meta' => [
                    'composer' => self::class,
                    'compatibility_builder' => 'missing',
                ],
            ];
        }

        $payload = \App\Core\SidebarBuilder::build($context);
        if (!is_array($payload)) {
            return [
                'sections' => [],
                'meta' => [
                    'composer' => self::class,
                    'compatibility_builder' => 'invalid_payload',
                ],
            ];
        }

        $meta = is_array($payload['meta'] ?? null) ? (array)$payload['meta'] : [];
        $meta['composer'] = self::class;
        $meta['compatibility_builder'] = '\\App\\Core\\SidebarBuilder';
        $payload['meta'] = $meta;

        return $payload;
    }
}
