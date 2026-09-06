<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    private string $basePath;
    private array $namespaces = [];
    private const LAYOUT_APP = 'app';
    private const LAYOUT_AUTH = 'auth';
    private const LAYOUT_RAW = 'raw';

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function addNamespace(string $ns, string $path): void
    {
        // Case-insensitive namespace key
        $this->namespaces[strtolower($ns)] = rtrim($path, '/');
    }

    public function render(string $key, array $data = [], array $options = []): void
    {
        // key format: Namespace::path/to/view.php OR plain path under /public/views
        $file = $this->resolve($key);

        $layout = strtolower(trim((string)($options['layout'] ?? ($data['_layout'] ?? ''))));
        if ($layout === '') {
            $layout = $this->defaultLayoutFor($key, $file);
        }

        if (!in_array($layout, [self::LAYOUT_APP, self::LAYOUT_AUTH, self::LAYOUT_RAW], true)) {
            $layout = self::LAYOUT_APP;
        }

        // Backward compatibility: legacy views that include layout partials should render raw.
        $legacyAutoRaw = (bool)($options['legacyAutoRaw'] ?? true);
        if ($layout !== self::LAYOUT_RAW && $legacyAutoRaw && $this->hasEmbeddedLayoutIncludes($file)) {
            $layout = self::LAYOUT_RAW;
        }

        unset($data['_layout']);

        if ($layout === self::LAYOUT_RAW) {
            $this->renderRawFile($file, $data);
            return;
        }

        if ($layout === self::LAYOUT_AUTH) {
            $this->renderRawFile(APP_ROOT . '/public/views/layouts/auth_header.php', $data);
            $this->renderRawFile($file, $data);
            $this->renderRawFile(APP_ROOT . '/public/views/layouts/auth_footer.php', $data);
            return;
        }

        $this->renderRawFile(APP_ROOT . '/public/views/layouts/header.php', $data);
        $this->renderRawFile($file, $data);
        $this->renderRawFile(APP_ROOT . '/public/views/layouts/footer.php', $data);
    }

    public function renderRaw(string $key, array $data = []): void
    {
        $this->render($key, $data, ['layout' => self::LAYOUT_RAW, 'legacyAutoRaw' => false]);
    }

    public function renderAuth(string $key, array $data = []): void
    {
        $this->render($key, $data, ['layout' => self::LAYOUT_AUTH]);
    }

    private function renderRawFile(string $file, array $data): void
    {
        extract($data, EXTR_SKIP);
        require $file;
    }

    private function defaultLayoutFor(string $key, string $file): string
    {
        $normalizedKey = strtolower(str_replace('\\', '/', trim($key)));
        $normalizedFile = strtolower(str_replace('\\', '/', $file));

        if (str_contains($normalizedKey, '::auth/') || str_contains($normalizedKey, '/auth/')) {
            return self::LAYOUT_AUTH;
        }

        if (str_contains($normalizedFile, '/views/auth/')) {
            return self::LAYOUT_AUTH;
        }

        return self::LAYOUT_APP;
    }

    private function hasEmbeddedLayoutIncludes(string $file): bool
    {
        $content = @file_get_contents($file);
        if ($content === false || $content === '') {
            return false;
        }

        return str_contains($content, "public/views/layouts/header.php")
            || str_contains($content, "public/views/layouts/footer.php")
            || str_contains($content, "public/views/layouts/admin-wrapper-open.php")
            || str_contains($content, "public/views/layouts/admin-wrapper-close.php")
            || str_contains($content, "public/views/layouts/auth_header.php")
            || str_contains($content, "public/views/layouts/auth_footer.php");
    }

    private function resolve(string $key): string
    {
        if (str_contains($key, '::')) {
            [$ns, $path] = explode('::', $key, 2);
            $nsKey = strtolower(trim($ns));
            if (!isset($this->namespaces[$nsKey])) {
                throw new \RuntimeException("View namespace not found: {$ns}");
            }
            $full = $this->namespaces[$nsKey] . '/' . ltrim($path, '/');
        } else {
            $full = $this->basePath . '/' . ltrim($key, '/');
        }

        if (!is_file($full)) {
            throw new \RuntimeException("View file not found: {$full}");
        }
        return $full;
    }
}
