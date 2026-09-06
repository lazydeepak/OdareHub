<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDataContractService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../apps/Studio/Services/StudioDataContractService.php';

final class StudioDataContractExtractionTest extends TestCase
{
    public function testExtractsLayoutItemBindingsAndStructuredDataSources(): void
    {
        $contract = StudioDataContractService::extract([
            'module_manifest' => [
                'fields' => [
                    ['key' => 'part_name', 'type' => 'string'],
                    ['key' => 'qty', 'type' => 'integer'],
                ],
            ],
            'view_definition' => [
                'layout' => [
                    'items' => [
                        ['id' => 'orders_table', 'component' => 'table', 'data_binding' => 'module.rows'],
                        ['id' => 'qty_kpi', 'component' => 'kpi', 'props' => ['source' => 'module.metrics.qty_total']],
                    ],
                ],
                'data_contract' => [
                    'required_fields' => ['part_name'],
                    'data_sources' => [
                        ['key' => 'module.rows'],
                        ['path' => 'module.metrics'],
                    ],
                ],
            ],
        ]);

        self::assertSame('full', $contract['confidence']);
        self::assertSame(2, $contract['summary']['bindings']);
        self::assertSame(
            ['module.rows', 'module.metrics'],
            $contract['data_sources']
        );
        self::assertContains(
            ['item_id' => 'qty_kpi', 'path' => 'module.metrics.qty_total'],
            $contract['bindings']
        );
    }

    public function testExtractsNestedViewFieldsAndRequiredFieldFallbacks(): void
    {
        $contract = StudioDataContractService::extract([
            'view_definition' => [
                'view' => [
                    'fields' => ['order_id', 'status'],
                ],
                'layout' => [
                    'structure' => [
                        [
                            'items' => [
                                ['id' => 'status_form', 'component' => 'form', 'data_binding' => 'module.fields'],
                            ],
                        ],
                    ],
                ],
                'data_contract' => [
                    'required_fields' => ['status'],
                ],
            ],
        ]);

        self::assertSame('full', $contract['confidence']);
        self::assertSame(2, $contract['summary']['fields']);
        self::assertSame(['status'], $contract['required_fields']);
        self::assertSame([], $contract['issues']);
        self::assertContains(
            ['key' => 'status', 'type' => 'string', 'required' => true],
            $contract['fields']
        );
    }
}
