<?php
declare(strict_types=1);

namespace Apps\Hospitality\Services\OperatorLayerAdapters;

use Apps\Hospitality\Services\FrontDeskService;
use Apps\Hospitality\Services\HousekeepingService;

final class HospitalityBoardAdapter
{
    /**
     * Read-only operator payload over existing Hospitality foundation services.
     *
     * @return array<string,mixed>|null
     */
    public static function summary(): ?array
    {
        $frontDesk = FrontDeskService::board();
        $housekeeping = HousekeepingService::board();
        if ($frontDesk === null || $housekeeping === null) {
            return null;
        }

        $folioSummaries = [];
        try {
            $folioSummaries = FrontDeskService::folioSummaries();
        } catch (\Throwable) {
            $folioSummaries = [];
        }

        $arrivals = array_values((array)($frontDesk['arrivals'] ?? []));
        $inhouse = array_values((array)($frontDesk['inhouse'] ?? []));
        $rooms = array_values($housekeeping);

        return [
            'kpis' => [
                'arrivals_today' => self::arrivalsToday($arrivals),
                'inhouse' => count($inhouse),
                'rooms_attention' => self::roomsNeedingAttention($rooms),
                'open_folio_total' => self::openFolioTotal($folioSummaries),
            ],
            'arrivals' => array_slice($arrivals, 0, 12),
            'inhouse' => array_slice($inhouse, 0, 12),
            'housekeeping' => [
                'rooms' => array_slice($rooms, 0, 80),
                'counts' => self::housekeepingCounts($rooms),
            ],
            'folio_summaries' => $folioSummaries,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $arrivals
     */
    private static function arrivalsToday(array $arrivals): int
    {
        $today = date('Y-m-d');
        $count = 0;
        foreach ($arrivals as $row) {
            if ((string)($row['check_in_date'] ?? '') === $today) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param array<int,array<string,mixed>> $rooms
     */
    private static function roomsNeedingAttention(array $rooms): int
    {
        $attention = ['dirty' => true, 'maintenance' => true, 'out_of_service' => true];
        $count = 0;
        foreach ($rooms as $room) {
            $status = strtolower(trim((string)($room['hk_status'] ?? '')));
            if (isset($attention[$status])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param array<int,array<string,mixed>> $summaries
     */
    private static function openFolioTotal(array $summaries): float
    {
        $total = 0.0;
        foreach ($summaries as $summary) {
            $total += (float)($summary['total'] ?? 0);
        }
        return round($total, 2);
    }

    /**
     * @param array<int,array<string,mixed>> $rooms
     * @return array<string,int>
     */
    private static function housekeepingCounts(array $rooms): array
    {
        $counts = [
            'pending' => 0,
            'clean' => 0,
            'dirty' => 0,
            'inspected' => 0,
            'maintenance' => 0,
            'out_of_service' => 0,
        ];
        foreach ($rooms as $room) {
            $status = strtolower(trim((string)($room['hk_status'] ?? '')));
            if ($status === '') {
                $status = 'pending';
            }
            if (!array_key_exists($status, $counts)) {
                $status = 'pending';
            }
            $counts[$status]++;
        }
        return $counts;
    }
}
