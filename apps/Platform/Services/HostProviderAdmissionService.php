<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

/**
 * Platform-owned admission check for trusted /me host providers.
 *
 * This class does not execute provider code and does not claim to prove that
 * arbitrary PHP is non-mutating. Descriptors are necessary evidence only;
 * approved providers still require static review and isolated behavioral
 * proof.
 */
final class HostProviderAdmissionService
{
    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $context
     * @return array{allowed:bool,reason:string,descriptor?:array<string,mixed>}
     */
    public static function admit(array $row, array $payload, string $surface, array $context = []): array
    {
        if (strtolower(trim($surface)) !== 'me') {
            return ['allowed' => false, 'reason' => 'unsupported_surface'];
        }

        $appKey = self::normalizeKey((string)($row['app_key'] ?? ''));
        $hookKey = trim((string)($row['hook_key'] ?? ''));
        $installPath = trim((string)($row['install_path'] ?? ''));
        if ($appKey === '' || $hookKey === '' || $installPath === '') {
            return ['allowed' => false, 'reason' => 'missing_registration_identity'];
        }

        $manifestPath = rtrim($installPath, '/') . '/manifest.json';
        if (!is_file($manifestPath)) {
            return ['allowed' => false, 'reason' => 'missing_trusted_manifest'];
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            return ['allowed' => false, 'reason' => 'invalid_trusted_manifest'];
        }

        $manifestOwner = self::normalizeKey((string)($manifest['app_key'] ?? $manifest['id'] ?? ''));
        if ($manifestOwner === '' || $manifestOwner !== $appKey) {
            return ['allowed' => false, 'reason' => 'manifest_owner_mismatch'];
        }

        $descriptors = [];
        foreach ((array)($manifest['host_provider_capabilities'] ?? []) as $descriptor) {
            if (!is_array($descriptor)) {
                continue;
            }
            if (trim((string)($descriptor['key'] ?? '')) === $hookKey) {
                $descriptors[] = $descriptor;
            }
        }

        if (count($descriptors) !== 1) {
            return ['allowed' => false, 'reason' => $descriptors === [] ? 'missing_capability' : 'conflicting_capabilities'];
        }

        $descriptor = $descriptors[0];
        if (($descriptor['enabled'] ?? false) !== true) {
            return ['allowed' => false, 'reason' => 'disabled_capability'];
        }
        if (self::normalizeKey((string)($descriptor['owner_app'] ?? '')) !== $appKey) {
            return ['allowed' => false, 'reason' => 'descriptor_owner_mismatch'];
        }
        if (strtolower(trim((string)($descriptor['surface'] ?? ''))) !== 'me'
            || strtolower(trim((string)($descriptor['region'] ?? ''))) !== strtolower(trim((string)($payload['region'] ?? '')))
            || strtolower(trim((string)($payload['surface'] ?? ''))) !== 'me'
        ) {
            return ['allowed' => false, 'reason' => 'descriptor_surface_mismatch'];
        }

        $descriptorProvider = trim((string)($descriptor['provider'] ?? ''));
        $payloadProvider = trim((string)($payload['provider'] ?? ''));
        if ($descriptorProvider === '' || $payloadProvider === '' || $descriptorProvider !== $payloadProvider) {
            return ['allowed' => false, 'reason' => 'provider_binding_mismatch'];
        }

        $providerFile = self::normalizeRelativePath((string)($descriptor['provider_file'] ?? ''));
        $payloadFile = self::normalizeRelativePath((string)($payload['provider_file'] ?? ''));
        if ($providerFile === '' || $providerFile !== $payloadFile) {
            return ['allowed' => false, 'reason' => 'provider_file_binding_mismatch'];
        }

        $canonicalInstall = realpath($installPath);
        $canonicalProvider = realpath(rtrim($installPath, '/') . '/' . $providerFile);
        if ($canonicalInstall === false || $canonicalProvider === false || !is_file($canonicalProvider)) {
            return ['allowed' => false, 'reason' => 'provider_file_missing'];
        }
        $installPrefix = rtrim($canonicalInstall, '/') . '/';
        if (!str_starts_with($canonicalProvider, $installPrefix)) {
            return ['allowed' => false, 'reason' => 'provider_path_escape'];
        }

        [$class, $method] = self::splitProvider($descriptorProvider);
        if ($class === '' || $method === '' || !self::validClassName($class) || !self::validMethodName($method)) {
            return ['allowed' => false, 'reason' => 'malformed_provider_binding'];
        }
        if (!self::fileDeclaresClass($canonicalProvider, $class)
            || !self::fileDeclaresMethod($canonicalProvider, $method)
        ) {
            return ['allowed' => false, 'reason' => 'provider_class_mismatch'];
        }

        $effect = strtolower(trim((string)($descriptor['effect'] ?? '')));
        $output = trim((string)($descriptor['output'] ?? ''));
        $recordScope = strtolower(trim((string)($descriptor['record_scope'] ?? '')));
        $recordAuthorization = trim((string)($descriptor['record_authorization'] ?? ''));
        $requiredModules = array_values(array_filter(
            array_map(static fn($module): string => trim((string)$module), (array)($descriptor['required_modules'] ?? [])),
            static fn(string $module): bool => $module !== ''
        ));
        $contract = $effect === 'read_only_navigation'
            && $output === 'navigation_items'
            && $recordScope === 'route_authority_only'
            && $recordAuthorization === 'route_authority';
        $aggregateContract = $effect === 'read_only_aggregate'
            && $output === 'summary_widgets'
            && $recordScope === 'module_authority_aggregate'
            && $requiredModules !== []
            && preg_match('/^[a-z][a-z0-9_.-]+$/', strtolower($recordAuthorization)) === 1;
        if (trim((string)($descriptor['required_app_authority'] ?? '')) !== 'existing_app_assignment'
            || trim((string)($descriptor['failure_behavior'] ?? '')) !== 'suppress'
            || (!$contract && !$aggregateContract)
        ) {
            return ['allowed' => false, 'reason' => 'incomplete_capability_contract'];
        }

        $review = is_array($descriptor['review'] ?? null) ? $descriptor['review'] : [];
        if (($review['static'] ?? '') !== 'approved' || ($review['isolated'] ?? '') !== 'approved') {
            return ['allowed' => false, 'reason' => 'missing_read_only_review'];
        }

        $authority = self::authorizeOwner($appKey, $descriptor, $context);
        if (!$authority['allowed']) {
            return $authority;
        }

        return ['allowed' => true, 'reason' => 'admitted', 'descriptor' => $descriptor];
    }

    /** @return array{allowed:bool,reason:string} */
    private static function authorizeOwner(string $appKey, array $descriptor, array $context): array
    {
        if (!function_exists('platform_user_access_policy_contract')) {
            return ['allowed' => false, 'reason' => 'missing_access_policy'];
        }

        $user = is_array($context['user'] ?? null) ? (array)$context['user'] : [];
        $resolved = is_array($context['context'] ?? null) ? (array)$context['context'] : [];
        $authorityRole = self::normalizeKey((string)($resolved['authority_role'] ?? $user['authority_role'] ?? $user['role'] ?? ''));
        $assigned = array_map([self::class, 'normalizeKey'], (array)($resolved['active_assigned_apps'] ?? $resolved['assigned_apps'] ?? []));
        $assigned = array_values(array_filter($assigned, static fn(string $value): bool => $value !== ''));
        if (!in_array($appKey, $assigned, true) && !in_array($authorityRole, ['platform_admin', 'system_admin', 'sysadmin'], true)) {
            return ['allowed' => false, 'reason' => 'missing_app_assignment'];
        }

        try {
            $policy = platform_user_access_policy_contract();
            if (!is_object($policy) || !$policy->isAppEnabled($appKey)) {
                return ['allowed' => false, 'reason' => 'app_not_enabled'];
            }

            foreach ((array)($descriptor['required_modules'] ?? []) as $module) {
                $module = trim((string)$module);
                if ($module !== '' && !$policy->canAccessModule($user, $module)) {
                    return ['allowed' => false, 'reason' => 'missing_module_authority'];
                }
            }
        } catch (\Throwable) {
            return ['allowed' => false, 'reason' => 'access_policy_failure'];
        }

        return ['allowed' => true, 'reason' => 'authorized'];
    }

    /** @return array{0:string,1:string} */
    private static function splitProvider(string $provider): array
    {
        $parts = explode('::', $provider, 2);
        return [trim((string)($parts[0] ?? '')), trim((string)($parts[1] ?? ''))];
    }

    private static function normalizeKey(string $value): string
    {
        return strtolower(trim($value));
    }

    private static function normalizeRelativePath(string $value): string
    {
        $value = str_replace('\\', '/', trim($value));
        if ($value === '' || str_starts_with($value, '/') || preg_match('#(^|/)\.\.?(/|$)#', $value)) {
            return '';
        }
        return ltrim($value, './');
    }

    private static function validClassName(string $value): bool
    {
        return (bool)preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*$/', $value);
    }

    private static function validMethodName(string $value): bool
    {
        return (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value);
    }

    private static function fileDeclaresClass(string $file, string $expectedClass): bool
    {
        $source = @file_get_contents($file);
        if (!is_string($source) || $source === '') {
            return false;
        }

        $namespace = '';
        $tokens = token_get_all($source);
        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $name = '';
                for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                    $part = $tokens[$cursor];
                    if (is_string($part) && ($part === ';' || $part === '{')) {
                        break;
                    }
                    if (is_array($part) && in_array($part[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $name .= $part[1];
                    }
                }
                $namespace = trim($name, " \\t\\r\\n\\\\");
                continue;
            }

            if (!is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }

            $previous = $tokens[$index - 1] ?? null;
            if (is_array($previous) && $previous[0] === T_NEW) {
                continue;
            }
            for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                $part = $tokens[$cursor];
                if (is_array($part) && $part[0] === T_STRING) {
                    $declared = $namespace === '' ? $part[1] : $namespace . '\\' . $part[1];
                    if ($declared === $expectedClass) {
                        return true;
                    }
                    break;
                }
                if (is_string($part) && $part === '{') {
                    break;
                }
            }
        }

        return false;
    }

    private static function fileDeclaresMethod(string $file, string $expectedMethod): bool
    {
        $source = @file_get_contents($file);
        if (!is_string($source) || $source === '') {
            return false;
        }

        $tokens = token_get_all($source);
        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            for ($cursor = $index + 1; $cursor < $count; $cursor++) {
                $part = $tokens[$cursor] ?? null;
                if (is_array($part) && $part[0] === T_STRING) {
                    if ($part[1] === $expectedMethod) {
                        return true;
                    }
                    break;
                }
                if (is_string($part) && in_array($part, ['(', '{', ';'], true)) {
                    break;
                }
            }
        }

        return false;
    }
}
