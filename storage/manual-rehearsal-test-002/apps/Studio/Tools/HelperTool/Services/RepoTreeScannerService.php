<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\HelperTool\Services;

require_once __DIR__ . '/RepoTreeScannerOwnerTrait.php';
require_once __DIR__ . '/RepoTreeScannerScanTrait.php';
require_once __DIR__ . '/RepoTreeScannerInspectionTrait.php';
require_once __DIR__ . '/RepoTreeScannerClassificationTrait.php';
require_once __DIR__ . '/RepoTreeScannerEntityTrait.php';

final class RepoTreeScannerService
{
    private const FILE_TYPE_TOP_LIMIT = 10;
    private const SUSPICIOUS_EXAMPLE_LIMIT = 8;
    private const OVERSIZED_FILE_BYTES = 5242880;
    private const PREVIEW_MAX_BYTES = 32768;
    private const PREVIEW_MAX_LINES = 400;
    private const REVIEW_TMP_PATH_PATTERNS = [
        '#(^|/)(cache|tmp|temp|logs?|coverage|dist|build)(/|$)#i',
        '#(^|/)storage/(cache|logs?|tmp|sessions)(/|$)#i',
    ];
    private const RUNTIME_DEPENDENCY_PATH_PATTERNS = [
        '#(^|/)\.opencode/node_modules(/|$)#i',
        '#(^|/)node_modules(/|$)#i',
        '#(^|/)vendor(/|$)#i',
    ];
    private const SNAPSHOT_RECOVERY_PATH_PATTERNS = [
        '#(^|/)storage/studio-snapshots/.+\.bak$#i',
    ];
    private const BACKUP_TEMP_FILE_PATTERN = '#(~$|\.(bak|backup|old|orig|tmp|temp|swp|swo|part|crdownload)$)#i';
    private const KNOWN_EXTENSIONLESS_FILENAMES = [
        'dockerfile',
        'makefile',
        'procfile',
        'license',
        'readme',
        'changelog',
        'copying',
    ];

    public const ENTITY_TYPE_REGISTRY = [
        'controller' => [
            'key' => 'controller',
            'label' => 'Controllers',
            'path_pattern' => '/Controllers/',
            'file_extension' => '.php',
            'evidence_strategy' => 'class_declaration',
            'confidence_rule' => 'class_found',
            'sort_order' => 0,
        ],
        'service' => [
            'key' => 'service',
            'label' => 'Services',
            'path_pattern' => '/Services/',
            'file_extension' => '.php',
            'evidence_strategy' => 'class_declaration',
            'confidence_rule' => 'class_found',
            'sort_order' => 1,
        ],
        'view' => [
            'key' => 'view',
            'label' => 'Views',
            'path_pattern' => '/Views/',
            'file_extension' => '.php',
            'evidence_strategy' => 'file_path',
            'confidence_rule' => 'always_certain',
            'sort_order' => 2,
        ],
        'route' => [
            'key' => 'route',
            'label' => 'Routes',
            'path_pattern' => null,
            'file_extension' => '.php',
            'evidence_strategy' => 'route_registration',
            'confidence_rule' => 'always_certain',
            'sort_order' => 3,
            'is_routes_file' => true,
        ],
    ];

    use RepoTreeScannerOwnerTrait;
    use RepoTreeScannerScanTrait;
    use RepoTreeScannerInspectionTrait;
    use RepoTreeScannerClassificationTrait;
    use RepoTreeScannerEntityTrait;
}
