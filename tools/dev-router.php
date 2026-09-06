<?php
declare(strict_types=1);

$publicRoot = dirname(__DIR__) . '/public';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$resolvedPath = realpath($publicRoot . $requestPath);

if ($resolvedPath !== false && str_starts_with($resolvedPath, $publicRoot) && (is_file($resolvedPath) || is_dir($resolvedPath))) {
    return false;
}

require $publicRoot . '/index.php';