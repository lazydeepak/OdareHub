<?php
declare(strict_types=1);

namespace Plugins\MaterialManagement\Services;

use Apps\Manufacturing\Services\ModuleSurfaceWidgetFactory;

final class MaterialManagementWidgetRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(string $region, array $context = []): array
    {
        return ModuleSurfaceWidgetFactory::build($region, $context, [
            'key' => 'materials',
            'title' => 'Materials',
            'url' => '/apps/manufacturing/materials',
            'report_url' => '/apps/manufacturing/materials/report',
            'export_url' => '/apps/manufacturing/materials/export',
            'table' => 'materials',
            'priority' => 32,
            'include_summary' => true,
            'summary_value' => static fn (): int => (int)self::summary()['shortage_risk_count'],
            'summary_meta' => static fn (): string => 'Delayed inbound: ' . self::summary()['incoming_delay_count'] . ' · Overflow risk: ' . self::summary()['storage_overflow_count'],
            'summary_tone' => static fn (): string => ((int)self::summary()['shortage_risk_count'] > 0 || (int)self::summary()['incoming_delay_count'] > 0) ? 'warn' : 'info',
            'summary_widget_type' => static fn (): string => ((int)self::summary()['shortage_risk_count'] > 0 || (int)self::summary()['incoming_delay_count'] > 0) ? 'alert' : 'informative',
            'chart_title' => 'Materials · Supply Pressure',
            'chart_description' => 'Material risk across shortage, inbound delay, and overflow conditions.',
            'chart_rows' => static fn (): array => [
                ['label' => 'Shortage Risk', 'value' => self::summary()['shortage_risk_count'], 'meta' => 'Critical + low coverage materials'],
                ['label' => 'Delayed Inbound', 'value' => self::summary()['incoming_delay_count'], 'meta' => 'Late purchase or inbound supply'],
                ['label' => 'Action Now', 'value' => self::summary()['action_now_count'], 'meta' => 'Materials needing buy / plan now'],
                ['label' => 'Overflow Risk', 'value' => self::summary()['storage_overflow_count'], 'meta' => 'Capacity attention required'],
            ],
            'table_title' => 'Materials · Control Surfaces',
            'table_description' => 'Use the correct material surface for shortage, inbound, and storage control.',
            'table_rows' => static fn (): array => [
                [
                    'cells' => ['Coverage', 'Material coverage and shortage outlook', (string)self::summary()['shortage_risk_count']],
                    'url' => '/apps/manufacturing/materials/coverage',
                    'url_label' => 'Open Coverage',
                ],
                [
                    'cells' => ['Planning', '30-day projected net need and delayed inbound', (string)self::summary()['action_now_count']],
                    'url' => '/apps/manufacturing/materials/planning',
                    'url_label' => 'Open Planning',
                ],
                [
                    'cells' => ['Capacity', 'Storage occupancy and overflow watch', (string)self::summary()['storage_overflow_count']],
                    'url' => '/apps/manufacturing/materials/capacity',
                    'url_label' => 'Open Capacity',
                ],
            ],
            'form_title' => 'Materials · Filter',
            'form_description' => 'Jump into material analysis by state or material id.',
            'form_fields' => [
                [
                    'type' => 'text',
                    'name' => 'q',
                    'label' => 'Search',
                    'value' => '',
                ],
                [
                    'type' => 'text',
                    'name' => 'status',
                    'label' => 'Status',
                    'value' => '',
                ],
                [
                    'type' => 'text',
                    'name' => 'material_id',
                    'label' => 'Material ID',
                    'value' => '',
                ],
            ],
            'form_actions' => [
                ['type' => 'submit', 'label' => 'Open Materials Dashboard', 'primary' => true],
            ],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private static function summary(): array
    {
        static $summary = null;
        if (is_array($summary)) {
            return $summary;
        }

        try {
            $summary = MaterialManagementService::summary();
        } catch (\Throwable $e) {
            $summary = [
                'shortage_risk_count' => 0,
                'incoming_delay_count' => 0,
                'action_now_count' => 0,
                'storage_overflow_count' => 0,
            ];
        }

        return $summary;
    }
}
