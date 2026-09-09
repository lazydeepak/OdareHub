<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

/**
 * Read-only metadata diagnostic service for owner-owned label resources.
 * Validates canonical metadata fields (schema, owner_key, resource_key).
 *
 * Scans all discovered resources and validates that each resource declares
 * self-describing metadata (schema, owner_key, resource key) without
 * requiring discovery fallback for ownership resolution.
 *
 * No files written, no DB access, no runtime print/export/QR coupling.
 */
final class LabelDesignerResourceMetadataService
{
    private const SCHEMA_CONTEXT = 'odarehub.label.context.v1';
    private const SCHEMA_TEMPLATE = 'odarehub.label.template.v1';
    private const SCHEMA_RULE = 'odarehub.label.rule.v1';

    private const SEVERITY_RANK = [
        'PASS' => 0,
        'WARN' => 1,
        'FAIL' => 2,
        'ERROR' => 3,
    ];

    /**
     * Analyze metadata for all discovered label resources.
     *
     * @return array<string,mixed>
     */
    public static function analyzeAll(): array
    {
        $discovery = LabelDesignerDiscoveryService::discover();
        $owners = isset($discovery['owners']) && is_array($discovery['owners'])
            ? array_values(array_filter($discovery['owners'], 'is_array'))
            : [];

        $resources = [];
        $compliantCount = 0;
        $legacyCount = 0;

        foreach ($owners as $owner) {
            $ownerKey = trim((string)($owner['owner_key'] ?? ''));
            $ownerType = trim((string)($owner['owner_type'] ?? ''));
            $resourcesData = isset($owner['resources']) && is_array($owner['resources']) ? $owner['resources'] : [];

            // Contexts
            $contextGroup = isset($resourcesData['contexts']) && is_array($resourcesData['contexts']) ? $resourcesData['contexts'] : [];
            $contextFiles = isset($contextGroup['files']) && is_array($contextGroup['files'])
                ? array_values(array_filter($contextGroup['files'], 'is_array'))
                : [];
            foreach ($contextFiles as $cf) {
                $result = self::analyzeContextFile($cf, $ownerKey, $ownerType);
                $resources[] = $result;
                if ($result['compliant']) {
                    $compliantCount++;
                } else {
                    $legacyCount++;
                }
            }

            // Templates
            $templateGroup = isset($resourcesData['templates']) && is_array($resourcesData['templates']) ? $resourcesData['templates'] : [];
            $templateFiles = isset($templateGroup['files']) && is_array($templateGroup['files'])
                ? array_values(array_filter($templateGroup['files'], 'is_array'))
                : [];
            foreach ($templateFiles as $tf) {
                $result = self::analyzeTemplateFile($tf, $ownerKey, $ownerType);
                $resources[] = $result;
                if ($result['compliant']) {
                    $compliantCount++;
                } else {
                    $legacyCount++;
                }
            }

            // Rules
            $rulesGroup = isset($resourcesData['rules']) && is_array($resourcesData['rules']) ? $resourcesData['rules'] : [];
            $ruleFiles = isset($rulesGroup['files']) && is_array($rulesGroup['files'])
                ? array_values(array_filter($rulesGroup['files'], 'is_array'))
                : [];
            foreach ($ruleFiles as $rf) {
                $result = self::analyzeRuleFile($rf, $ownerKey, $ownerType);
                $resources[] = $result;
                if ($result['compliant']) {
                    $compliantCount++;
                } else {
                    $legacyCount++;
                }
            }
        }

        $overallSeverity = self::computeOverallSeverity($resources);

        // Build owner-level grouping
        $ownerGroups = [];
        foreach ($owners as $owner) {
            $ownerKey = trim((string)($owner['owner_key'] ?? ''));
            $ownerType = trim((string)($owner['owner_type'] ?? ''));
            $rootPath = trim((string)($owner['root_path'] ?? ''));
            $ownerRoutes = [];
            $ownerHasOwnerKey = true;
            $ownerCompliant = true;

            foreach ($resources as $r) {
                if (($r['owner_key'] ?? '') !== $ownerKey && ($r['metadata_owner_key'] ?? '') !== $ownerKey) {
                    continue;
                }
                $ownerRoutes[] = [
                    'type' => $r['resource_type'] ?? 'unknown',
                    'file' => $r['relative_path'] ?? '',
                    'context_key' => $r['context_key'] ?? '',
                    'template_key' => $r['template_key'] ?? '',
                    'has_owner_key' => $r['has_owner_key'] ?? false,
                ];
                if (empty($r['has_owner_key'])) {
                    $ownerHasOwnerKey = false;
                }
                if (empty($r['compliant'])) {
                    $ownerCompliant = false;
                }
            }

            $ownerGroups[] = [
                'owner_key' => $ownerKey,
                'owner_type' => $ownerType,
                'root_path' => $rootPath,
                'metadata_complete' => $ownerHasOwnerKey && $ownerCompliant,
                'routes' => $ownerRoutes,
            ];
        }

        $contextCount = count(array_filter($resources, static fn (array $r): bool =>
            ($r['resource_type'] ?? '') === 'context'
        ));
        $templateCount = count(array_filter($resources, static fn (array $r): bool =>
            ($r['resource_type'] ?? '') === 'template'
        ));

        return [
            'resources' => $resources,
            'owners' => $ownerGroups,
            'owner_count' => count($ownerGroups),
            'total_count' => count($resources),
            'context_count' => $contextCount,
            'template_count' => $templateCount,
            'compliant_count' => $compliantCount,
            'complete_count' => $compliantCount,
            'legacy_count' => $legacyCount,
            'overall_severity' => $overallSeverity,
        ];
    }

    /**
     * Preview migration metadata for a single resource.
     *
     * @param array<string,mixed> $input Must contain 'resource_path'
     * @return array<string,mixed>
     */
    public static function previewMigration(array $input): array
    {
        $resourcePath = trim((string)($input['resource_path'] ?? ''));
        if ($resourcePath === '') {
            return [
                'ok' => false,
                'errors' => ['resource_path is required.'],
            ];
        }

        $absPath = defined('APP_ROOT') ? APP_ROOT . '/' . ltrim($resourcePath, '/') : '';
        if ($absPath === '' || !is_file($absPath)) {
            return [
                'ok' => false,
                'errors' => ['Resource file not found: ' . $resourcePath],
            ];
        }

        $raw = @file_get_contents($absPath);
        if (!is_string($raw) || $raw === '') {
            return [
                'ok' => false,
                'errors' => ['Cannot read resource file.'],
            ];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'errors' => ['Resource file is not valid JSON.'],
            ];
        }

        $schema = (string)($decoded['schema'] ?? '');
        $currentOwnerKey = trim((string)($decoded['owner_key'] ?? ''));
        $contextRefOwner = trim((string)(($decoded['context_ref'] ?? [])['owner_key'] ?? ''));

        $resourceType = self::detectResourceType($schema, $decoded);
        $proposedMetadata = self::buildProposedMetadata($decoded, $resourceType, $resourcePath);
        $diagnostics = self::validateResourceMetadata($decoded, $resourceType, $resourcePath);
        $proposedDiagnostics = self::validateResourceMetadata($proposedMetadata, $resourceType, $resourcePath);
        $compliant = self::isCompliant($diagnostics);
        $noChangeNeeded = $currentOwnerKey !== '';

        return [
            'ok' => true,
            'errors' => [],
            'no_change_needed' => $noChangeNeeded,
            'valid' => !$noChangeNeeded && $resourceType !== 'unknown',
            'resource_path' => $resourcePath,
            'resource_type' => $resourceType,
            'schema' => $schema,
            'current_metadata' => [
                'owner_key' => $currentOwnerKey,
                'owner_type' => trim((string)($decoded['owner_type'] ?? '')),
                'owner_root' => trim((string)($decoded['owner_root'] ?? '')),
                'resource_type' => trim((string)($decoded['resource_type'] ?? '')),
                'context_ref_owner_key' => $contextRefOwner,
                'has_owner_key' => $currentOwnerKey !== '',
                'has_schema' => $schema !== '',
            ],
            'proposed_metadata' => $proposedMetadata,
            'current_json' => $raw,
            'diagnostics' => $diagnostics,
            'proposed_diagnostics' => $proposedDiagnostics,
            'compliant' => $compliant,
        ];
    }

    /**
     * @param array<string,mixed> $file
     * @return array<string,mixed>
     */
    private static function analyzeContextFile(array $file, string $ownerKey, string $ownerType): array
    {
        $relPath = trim((string)($file['path'] ?? ''));
        $absPath = defined('APP_ROOT') && $relPath !== '' ? APP_ROOT . '/' . ltrim($relPath, '/') : '';

        $decoded = self::readJsonFile($absPath);
        $checks = self::validateResourceMetadata($decoded ?? [], 'context', $relPath);

        $schema = (string)($decoded['schema'] ?? '');
        $resourceOwnerKey = trim((string)($decoded['owner_key'] ?? ''));
        $resourceContextKey = trim((string)($decoded['context_key'] ?? ''));
        $compliant = self::isCompliant($checks);

        return [
            'resource_path' => $relPath,
            'relative_path' => $relPath,
            'resource_name' => trim((string)($file['name'] ?? basename($relPath))),
            'resource_type' => 'context',
            'owner_key' => $ownerKey,
            'schema' => $schema,
            'resource_key' => $resourceContextKey,
            'context_key' => $resourceContextKey,
            'template_key' => '',
            'has_owner_key' => $resourceOwnerKey !== '',
            'metadata_owner_key' => $resourceOwnerKey,
            'metadata_status' => $compliant ? 'Compliant' : 'Legacy',
            'migration_required' => !$compliant,
            'compliant' => $compliant,
            'diagnostics' => $checks,
        ];
    }

    /**
     * @param array<string,mixed> $file
     * @return array<string,mixed>
     */
    private static function analyzeTemplateFile(array $file, string $ownerKey, string $ownerType): array
    {
        $relPath = trim((string)($file['path'] ?? ''));
        $absPath = defined('APP_ROOT') && $relPath !== '' ? APP_ROOT . '/' . ltrim($relPath, '/') : '';

        $decoded = self::readJsonFile($absPath);
        $checks = self::validateResourceMetadata($decoded ?? [], 'template', $relPath);

        $schema = (string)($decoded['schema'] ?? '');
        $resourceOwnerKey = trim((string)($decoded['owner_key'] ?? ''));
        $contextRefOwner = trim((string)(($decoded['context_ref'] ?? [])['owner_key'] ?? ''));
        $resourceTemplateKey = trim((string)($decoded['template_key'] ?? ''));
        $compliant = self::isCompliant($checks);

        return [
            'resource_path' => $relPath,
            'relative_path' => $relPath,
            'resource_name' => trim((string)($file['name'] ?? basename($relPath))),
            'resource_type' => 'template',
            'owner_key' => $ownerKey,
            'schema' => $schema,
            'resource_key' => $resourceTemplateKey,
            'context_key' => '',
            'template_key' => $resourceTemplateKey,
            'has_owner_key' => $resourceOwnerKey !== '',
            'metadata_owner_key' => $resourceOwnerKey,
            'context_ref_owner_key' => $contextRefOwner,
            'metadata_status' => $compliant ? 'Compliant' : 'Legacy',
            'migration_required' => !$compliant,
            'compliant' => $compliant,
            'diagnostics' => $checks,
        ];
    }

    /**
     * @param array<string,mixed> $file
     * @return array<string,mixed>
     */
    private static function analyzeRuleFile(array $file, string $ownerKey, string $ownerType): array
    {
        $relPath = trim((string)($file['path'] ?? ''));
        $absPath = defined('APP_ROOT') && $relPath !== '' ? APP_ROOT . '/' . ltrim($relPath, '/') : '';

        $decoded = self::readJsonFile($absPath);
        $checks = self::validateResourceMetadata($decoded ?? [], 'rule', $relPath);

        $schema = (string)($decoded['schema'] ?? '');
        $resourceOwnerKey = trim((string)($decoded['owner_key'] ?? ''));
        $resourceRuleKey = trim((string)($decoded['rule_key'] ?? ''));
        $compliant = self::isCompliant($checks);

        return [
            'resource_path' => $relPath,
            'relative_path' => $relPath,
            'resource_name' => trim((string)($file['name'] ?? basename($relPath))),
            'resource_type' => 'rule',
            'owner_key' => $ownerKey,
            'schema' => $schema,
            'resource_key' => $resourceRuleKey,
            'context_key' => '',
            'template_key' => '',
            'has_owner_key' => $resourceOwnerKey !== '',
            'metadata_owner_key' => $resourceOwnerKey,
            'metadata_status' => $compliant ? 'Compliant' : 'Legacy',
            'migration_required' => !$compliant,
            'compliant' => $compliant,
            'diagnostics' => $checks,
        ];
    }

    /**
     * @param array<string,mixed> $decoded
     * @param string $resourceType
     * @param string $relPath
     * @return array<int,array<string,mixed>>
     */
    private static function validateResourceMetadata(array $decoded, string $resourceType, string $relPath): array
    {
        $checks = [];

        if ($decoded === []) {
            $checks[] = self::makeCheck('MD01', 'resource_readable', 'Resource file is readable JSON', 'ERROR', 'Cannot read or parse resource file.');
            return $checks;
        }

        // Schema present
        $schema = (string)($decoded['schema'] ?? '');
        $expectedSchema = match ($resourceType) {
            'context' => self::SCHEMA_CONTEXT,
            'template' => self::SCHEMA_TEMPLATE,
            'rule' => self::SCHEMA_RULE,
            default => '',
        };

        if ($schema === '') {
            $checks[] = self::makeCheck('MD02', 'schema_present', 'Schema present', 'ERROR', 'Missing schema field.');
        } elseif ($schema !== $expectedSchema) {
            $checks[] = self::makeCheck('MD03', 'schema_match', 'Schema matches expected', 'FAIL', 'Expected ' . $expectedSchema . ', got ' . $schema);
        } else {
            $checks[] = self::makeCheck('MD02', 'schema_present', 'Schema present', 'PASS', $schema);
        }

        // owner_key present
        $ownerKey = trim((string)($decoded['owner_key'] ?? ''));
        if ($ownerKey === '') {
            // For templates, check context_ref.owner_key as legacy fallback
            $contextRefOwner = trim((string)(($decoded['context_ref'] ?? [])['owner_key'] ?? ''));
            if ($contextRefOwner !== '' && $resourceType === 'template') {
                $checks[] = self::makeCheck('MD04', 'owner_key_present', 'Top-level owner_key present', 'WARN', 'Missing top-level owner_key (found in context_ref.owner_key — legacy resource).');
            } else {
                $checks[] = self::makeCheck('MD04', 'owner_key_present', 'Top-level owner_key present', 'FAIL', 'Missing owner_key.');
            }
        } else {
            $checks[] = self::makeCheck('MD04', 'owner_key_present', 'Top-level owner_key present', 'PASS', $ownerKey);
        }

        // Resource key present
        $resourceKey = match ($resourceType) {
            'context' => trim((string)($decoded['context_key'] ?? '')),
            'template' => trim((string)($decoded['template_key'] ?? '')),
            'rule' => trim((string)($decoded['rule_key'] ?? '')),
            default => '',
        };

        $keyField = match ($resourceType) {
            'context' => 'context_key',
            'template' => 'template_key',
            'rule' => 'rule_key',
            default => 'resource_key',
        };

        if ($resourceKey === '') {
            $checks[] = self::makeCheck('MD05', 'resource_key_present', $keyField . ' present', 'ERROR', 'Missing ' . $keyField . '.');
        } else {
            $checks[] = self::makeCheck('MD05', 'resource_key_present', $keyField . ' present', 'PASS', $resourceKey);
        }

        // Ownership validation: owner_key matches path owner (approximate check via prefix)
        if ($ownerKey !== '') {
            $pathOwner = self::inferOwnerFromPath($relPath);
            $ownerMatch = $pathOwner !== '' && self::ownerKeysEqual($ownerKey, $pathOwner);
            if ($ownerMatch) {
                $checks[] = self::makeCheck('MD06', 'ownership_valid', 'Owner key matches resource path', 'PASS', $ownerKey);
            } else {
                $checks[] = self::makeCheck('MD06', 'ownership_valid', 'Owner key matches resource path', 'WARN', 'Owner key "' . $ownerKey . '" may not match path owner "' . $pathOwner . '".');
            }
        } else {
            $checks[] = self::makeCheck('MD06', 'ownership_valid', 'Owner key matches resource path', 'WARN', 'Cannot validate ownership — owner_key is missing.');
        }

        // Known schema version
        if ($schema !== '' && $schema !== $expectedSchema) {
            $checks[] = self::makeCheck('MD07', 'known_schema', 'Known schema version', 'WARN', 'Unrecognized schema: ' . $schema);
        } elseif ($schema !== '') {
            $checks[] = self::makeCheck('MD07', 'known_schema', 'Known schema version', 'PASS', $schema);
        }

        return $checks;
    }

    /**
     * @param array<string,mixed> $decoded
     * @param string $resourceType
     * @param string $resourcePath
     * @return array<string,mixed>
     */
    private static function buildProposedMetadata(array $decoded, string $resourceType, string $resourcePath): array
    {
        $proposed = $decoded;
        $ownership = self::resolveOwnershipMetadata($resourcePath, $resourceType);

        $currentOwnerKey = trim((string)($decoded['owner_key'] ?? ''));
        if ($currentOwnerKey === '' && $ownership !== []) {
            $proposed['owner_key'] = $ownership['owner_key'];
            $proposed['owner_type'] = $ownership['owner_type'];
            $proposed['owner_root'] = $ownership['owner_root'];
            $proposed['resource_type'] = $ownership['resource_type'];
        }

        return $proposed;
    }

    /**
     * @return array{owner_key:string,owner_type:string,owner_root:string,resource_type:string}|array{}
     */
    private static function resolveOwnershipMetadata(string $resourcePath, string $resourceType): array
    {
        $pathOwner = self::inferOwnerFromPath($resourcePath);
        if ($pathOwner === '' || !in_array($resourceType, ['context', 'template', 'rule'], true)) {
            return [];
        }

        $discovery = LabelDesignerDiscoveryService::discover();
        $owners = isset($discovery['owners']) && is_array($discovery['owners']) ? $discovery['owners'] : [];
        foreach ($owners as $owner) {
            if (!is_array($owner) || !self::ownerKeysEqual((string)($owner['owner_key'] ?? ''), $pathOwner)) {
                continue;
            }

            return [
                'owner_key' => (string)$owner['owner_key'],
                'owner_type' => (string)($owner['owner_type'] ?? ''),
                'owner_root' => (string)($owner['root_path'] ?? ''),
                'resource_type' => $resourceType,
            ];
        }

        return [];
    }

    private static function detectResourceType(string $schema, array $decoded): string
    {
        return match ($schema) {
            self::SCHEMA_CONTEXT => 'context',
            self::SCHEMA_TEMPLATE => 'template',
            self::SCHEMA_RULE => 'rule',
            default => 'unknown',
        };
    }

    /**
     * @param array<int,array<string,mixed>> $checks
     */
    private static function isCompliant(array $checks): bool
    {
        foreach ($checks as $check) {
            $severity = (string)($check['severity'] ?? 'PASS');
            if ($severity === 'WARN' || $severity === 'FAIL' || $severity === 'ERROR') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<int,array<string,mixed>> $resources
     */
    private static function computeOverallSeverity(array $resources): string
    {
        $worst = 'PASS';
        foreach ($resources as $r) {
            $diagnostics = isset($r['diagnostics']) && is_array($r['diagnostics'])
                ? array_values(array_filter($r['diagnostics'], 'is_array'))
                : [];
            foreach ($diagnostics as $d) {
                $sev = (string)($d['severity'] ?? 'PASS');
                if ((self::SEVERITY_RANK[$sev] ?? 0) > (self::SEVERITY_RANK[$worst] ?? 0)) {
                    $worst = $sev;
                }
            }
        }
        return $worst;
    }

    public static function inferOwnerFromPath(string $relPath): string
    {
        $parts = explode('/', $relPath);
        if (count($parts) < 2) {
            return '';
        }

        // Path like: apps/{App}/... or apps/{App}/modules/{Module}/...
        if ($parts[0] === 'apps' && isset($parts[1])) {
            $appKey = $parts[1];
            // Check for module path: apps/{App}/modules/{Module}/...
            for ($i = 2; $i < count($parts) - 1; $i++) {
                if ($parts[$i] === 'modules' && isset($parts[$i + 1])) {
                    return $appKey . '/' . $parts[$i + 1];
                }
            }
            return $appKey;
        }

        // Path like: plugins/{Plugin}/...
        if ($parts[0] === 'plugins' && isset($parts[1])) {
            return $parts[1];
        }

        return '';
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function readJsonFile(string $absPath): ?array
    {
        if ($absPath === '' || !is_file($absPath)) {
            return null;
        }
        $raw = @file_get_contents($absPath);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function ownerKeysEqual(string $a, string $b): bool
    {
        $normalize = static fn (string $key): string => strtolower(str_replace('\\', '/', trim($key)));
        $left = $normalize($a);
        $right = $normalize($b);
        return $left !== '' && $right !== '' && hash_equals($left, $right);
    }

    /**
     * @return array{rule_id:string,check_key:string,label:string,severity:string,message:string}
     */
    private static function makeCheck(
        string $ruleId,
        string $checkKey,
        string $label,
        string $severity = 'PASS',
        string $message = ''
    ): array {
        return [
            'rule_id' => $ruleId,
            'check_key' => $checkKey,
            'label' => $label,
            'severity' => $severity,
            'message' => $message,
        ];
    }
}
