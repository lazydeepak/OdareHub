<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/runtime/RuntimeRenderer.php';

generated_runtime_render([
    'pageTitle' => (string)($pageTitle ?? 'Generated Runtime'),
    'generatedModule' => is_array($generatedModule ?? null) ? $generatedModule : [],
    'generatedFields' => is_array($generatedFields ?? null) ? $generatedFields : [],
    'generatedRows' => is_array($generatedRows ?? null) ? $generatedRows : [],
    'generatedEditRow' => is_array($generatedEditRow ?? null) ? $generatedEditRow : [],
    'generatedSearch' => (string)($generatedSearch ?? ''),
    'generatedFlash' => is_array($generatedFlash ?? null) ? $generatedFlash : [],
    'csrf' => (string)($csrf ?? ''),
]);
