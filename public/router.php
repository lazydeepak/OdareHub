<?php
/**
 * Router for PHP built-in server
 * Handles asset routes and rewrites to index.php for app routes
 */

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Handle app-scoped CSS: /assets/apps/{app}/styles/{file}.css
if (preg_match('#^/assets/apps/([a-z0-9._-]+)/(styles|modules)/(.+\.css)$#i', $requestPath, $matches)) {
    $appKey = $matches[1];
    $styleType = $matches[2];
    $filePath = $matches[3];
    $root = dirname(__DIR__);
    $normalizeAssetKey = static fn(string $value): string => strtolower((string)preg_replace('/[^a-z0-9]/i', '', $value));
    $resolveChildDir = static function (string $parent, string $key) use ($normalizeAssetKey): ?string {
        $target = $normalizeAssetKey($key);
        if ($target === '' || !is_dir($parent)) {
            return null;
        }
        foreach (scandir($parent) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $parent . '/' . $entry;
            if (is_dir($path) && $normalizeAssetKey($entry) === $target) {
                return $path;
            }
        }
        return null;
    };
    $appDir = $resolveChildDir($root . '/apps', $appKey);

    $candidates = [];
    if ($styleType === 'styles') {
        // Installed app: apps/<Ucfirst>/styles/<file>.css
        if ($appDir !== null) {
            $candidates[] = $appDir . '/styles/' . $filePath;
        }
        // Studio-generated app: apps/Generated/<app>/<file>.css (CSS lives at app root)
        $candidates[] = $root . '/apps/Generated/' . strtolower($appKey) . '/' . $filePath;
    } else {
        $parts = explode('/', $filePath);
        if (count($parts) >= 2 && $parts[count($parts) - 1] === 'styles.css') {
            array_pop($parts);
            $moduleKey = implode('/', $parts);
            // Installed: apps/<Ucfirst>/modules/<UcMod>/styles.css
            if ($appDir !== null) {
                $moduleDir = $resolveChildDir($appDir . '/modules', $moduleKey);
                if ($moduleDir !== null) {
                    $candidates[] = $moduleDir . '/styles.css';
                }
            }
            // Studio-generated: apps/Generated/<app>/<mod>/styles.css
            $candidates[] = $root . '/apps/Generated/' . strtolower($appKey) . '/' . strtolower($moduleKey) . '/styles.css';
        }
    }

    foreach ($candidates as $absolutePath) {
        if (is_file($absolutePath)) {
            header('Content-Type: text/css; charset=utf-8');
            header('Cache-Control: public, max-age=31536000');
            readfile($absolutePath);
            return true;
        }
    }
    http_response_code(404);
    return true;
}

// Serve physical files directly from public/ directory
$publicFilePath = __DIR__ . $requestPath;
if (is_file($publicFilePath)) {
    // Set content type for common file types
    $ext = strtolower(pathinfo($publicFilePath, PATHINFO_EXTENSION));
    $mimes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'html' => 'text/html',
        'webp' => 'image/webp',
    ];
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }
    readfile($publicFilePath);
    return true;
}

// Route everything else to index.php
require __DIR__ . '/index.php';
