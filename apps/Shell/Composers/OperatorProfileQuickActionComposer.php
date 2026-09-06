<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

final class OperatorProfileQuickActionComposer
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array{label:string,url:string,icon:string}>
     */
    public static function resolve(array $context, string $username): array
    {
        $workspaceProfile = $context['workspace_profile'] ?? null;
        if (!is_array($workspaceProfile)) {
            return [];
        }

        $rawActions = $workspaceProfile['quick_actions'] ?? null;
        if (!is_array($rawActions) || $rawActions === []) {
            return [];
        }

        $resolved = [];
        foreach ($rawActions as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string)($item['label'] ?? ''));
            $url = trim((string)($item['url'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }

            $resolved[] = [
                'label' => $label,
                'url' => self::sanitizeUrl($url, $username),
                'icon' => trim((string)($item['icon'] ?? '')),
            ];
        }

        return $resolved;
    }

    public static function sanitizeUrl(string $url, string $username): string
    {
        if ($url === '') {
            return '#';
        }

        $url = str_replace('{user}', rawurlencode($username), $url);
        if (str_starts_with($url, '/') || str_starts_with($url, 'https://') || str_starts_with($url, 'http://')) {
            return $url;
        }

        return '#';
    }
}
