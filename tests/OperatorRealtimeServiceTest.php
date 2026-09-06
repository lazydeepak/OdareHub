<?php
declare(strict_types=1);

namespace Tests;

use Apps\Shell\Services\OperatorRealtimeService;
use PHPUnit\Framework\TestCase;

final class OperatorRealtimeServiceTest extends TestCase
{
    public function testParseConnectionContextAcceptsCanonicalQueryValues(): void
    {
        $service = new OperatorRealtimeService();

        $context = $service->parseConnectionContext(
            '/operator/lazy/dashboard',
            'auth=csrf123&username=lazy&view=dashboard'
        );

        $this->assertSame('csrf123', $context['auth']);
        $this->assertSame('lazy', $context['username']);
        $this->assertSame('dashboard', $context['view']);
    }

    public function testParseConnectionContextFallsBackToPathSegments(): void
    {
        $service = new OperatorRealtimeService();

        $context = $service->parseConnectionContext(
            '/operator/lazy/dispatch',
            'auth=csrf123'
        );

        $this->assertSame('csrf123', $context['auth']);
        $this->assertSame('lazy', $context['username']);
        $this->assertSame('dispatch', $context['view']);
    }

    public function testParseConnectionContextKeepsQueryValuesWhenPathDiffers(): void
    {
        $service = new OperatorRealtimeService();

        $context = $service->parseConnectionContext(
            '/operator/other/qc',
            'auth=csrf123&username=lazy&view=dashboard'
        );

        $this->assertSame('lazy', $context['username']);
        $this->assertSame('dashboard', $context['view']);
    }
}