<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StudioManifestReportDesignerRouteTest extends TestCase
{
    public function testManifestIncludesReportDesignerCanonicalRoute(): void
    {
        $manifestPath = dirname(__DIR__) . '/apps/Studio/manifest.json';
        $raw = file_get_contents($manifestPath);
        $this->assertIsString($raw);

        $manifest = json_decode($raw, true);
        $this->assertIsArray($manifest);

        $routeRows = is_array($manifest['routes'] ?? null) ? $manifest['routes'] : [];
        $runtimeRows = is_array($manifest['runtime_contract']['routes'] ?? null) ? $manifest['runtime_contract']['routes'] : [];

        $this->assertTrue($this->hasRoute($routeRows, '/apps/studio/tools/report-designer', 'studio_report_designer'));
        $this->assertTrue($this->hasRoute($runtimeRows, '/apps/studio/tools/report-designer', 'studio_report_designer'));
        $this->assertTrue($this->hasRoute($routeRows, '/apps/studio/tools/label-designer', 'studio_label_designer'));
        $this->assertTrue($this->hasRoute($runtimeRows, '/apps/studio/tools/label-designer', 'studio_label_designer'));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function hasRoute(array $rows, string $path, string $featureKey): bool
    {
        foreach ($rows as $row) {
            if ((string)($row['path'] ?? '') !== $path) {
                continue;
            }
            return (string)($row['feature_key'] ?? '') === $featureKey;
        }

        return false;
    }
}
