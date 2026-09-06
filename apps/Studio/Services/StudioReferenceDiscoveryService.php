<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

require_once __DIR__ . '/StudioReferenceDiscoveryCoreTrait.php';
require_once __DIR__ . '/StudioReferenceDiscoveryPatternTrait.php';
require_once __DIR__ . '/StudioReferenceDiscoveryClassificationTrait.php';
require_once __DIR__ . '/StudioReferenceDiscoveryPathTrait.php';

/**
 * Canonical read-only Studio capability for discovering textual references to
 * owner paths, namespaces, manifests, navigation resources, and migration
 * targets. Tool workflows compose this evidence instead of owning traversal.
 */
final class StudioReferenceDiscoveryService
{
    private const INCLUDED_EXTENSIONS = ['php', 'json', 'js', 'css', 'md', 'sql', 'yaml', 'yml'];
    private const EXCLUDED_SEGMENTS = ['.git', 'vendor', 'node_modules', 'cache', 'tmp', 'temp'];
    private const EXCLUDED_PATH_PARTS = [
        'storage/cache',
        'storage/logs',
        'apps/Generated/runtime',
        'apps/Generated/tmp',
    ];

    public const RELEVANCE_RUNTIME_BLOCKING = 'RUNTIME_BLOCKING';
    public const RELEVANCE_STUDIO_TOOLING = 'STUDIO_TOOLING';
    public const RELEVANCE_ENGINEERING_WORKSPACE = 'ENGINEERING_WORKSPACE';
    public const RELEVANCE_DOCUMENTATION_HISTORY = 'DOCUMENTATION_HISTORY';
    public const RELEVANCE_OWNER_METADATA = 'OWNER_METADATA';
    public const RELEVANCE_SELF_REFERENCE = 'SELF_REFERENCE';
    public const RELEVANCE_LOW_CONFIDENCE_TEXT = 'LOW_CONFIDENCE_TEXT';

    public const CATEGORY_RUNTIME = 'runtime';
    public const CATEGORY_TOOLING = 'tooling';
    public const CATEGORY_DOCS = 'docs';
    public const CATEGORY_SELF_REFERENCE = 'self-reference';

    use StudioReferenceDiscoveryCoreTrait;
    use StudioReferenceDiscoveryPatternTrait;
    use StudioReferenceDiscoveryClassificationTrait;
    use StudioReferenceDiscoveryPathTrait;
}
