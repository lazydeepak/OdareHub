<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * StageTransitionService — cross-stage workflow readiness for Manufacturing.
 *
 * Stage chain (product-rule-dependent):
 *   production → [assembly] → [qc] → [packaging] → dispatch
 *
 * Branching rules driven by product operational attributes:
 *   - supply_mode = third_party  → skip production; dispatch only
 *   - dispatch_as_is=1 + !requires_ipm_qc + !requires_assembly → production → dispatch (skip all intermediate)
 *   - requires_assembly=1 → adds assembly stage (before QC)
 *   - requires_ipm_qc=1  → adds qc stage (after assembly if present)
 *   - dispatch_as_is=1 (with qc/assembly present) → skips packaging, goes production→[assembly]→[qc]→dispatch
 *   - activity_type = passive → noted but not blocking
 *
 * Stage statuses:
 *   not_applicable | pending | eligible | released | in_progress | complete | blocked | skipped
 *
 * Release flow:
 *   Production → Assembly (if required), otherwise → QC
 *   Assembly → QC
 *   QC → Packaging (or direct Dispatch if dispatch_as_is=1)
 *   Packaging → Dispatch
 *
 * Release actions write to mfg_stage_readiness; override actions set blocked/skipped.
 */
final class StageTransitionService
{
    /** Good-qty / demand threshold to consider a stage complete */
    private const COMPLETE_THRESHOLD = 0.95;
    /** Good-qty / demand threshold to be eligible for downstream release */
    private const RELEASE_THRESHOLD = 0.80;

    private const VALID_STAGES = ['production', 'assembly', 'qc', 'packaging', 'dispatch'];

    // -------------------------------------------------------------------------
    // Schema bootstrap
    // -------------------------------------------------------------------------

    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        DB::query(
            "CREATE TABLE IF NOT EXISTS mfg_stage_readiness (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                ref_date DATE NOT NULL,
                stage ENUM('production','assembly','qc','packaging','dispatch') NOT NULL,
                released_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                released_by VARCHAR(190) NULL,
                released_at DATETIME NULL,
                override_status ENUM('released','skipped','blocked') NULL,
                blocked_reason TEXT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_mfg_stage_readiness (product_id, ref_date, stage),
                KEY idx_mfg_stage_readiness_date (ref_date),
                KEY idx_mfg_stage_readiness_stage (stage),
                KEY idx_mfg_stage_readiness_override (override_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            []
        );
        // Add 'assembly' and 'packaging' to the stage ENUM on existing tables (idempotent).
        try {
            DB::query(
                "ALTER TABLE mfg_stage_readiness
                 MODIFY COLUMN stage ENUM('production','assembly','qc','packaging','dispatch') NOT NULL",
                []
            );
        } catch (\Throwable $e) {
            // Ignore if column is already correct or table doesn't support the alter.
        }
        $done = true;
    }

    // -------------------------------------------------------------------------
    // Stage path determination
    // -------------------------------------------------------------------------

    /**
     * Returns the ordered list of stages this product passes through,
     * derived entirely from its operational attributes.
     *
     * @param array $product Must contain: supply_mode, requires_ipm_qc,
     *                       requires_assembly, dispatch_as_is
     */
    public static function stagePathForProduct(array $product): array
    {
        $productionSource = (string)($product['supply_mode'] ?? 'in_house');
        $requiresQc       = (int)($product['requires_ipm_qc'] ?? 0);
        $requiresAssembly = (int)($product['requires_assembly'] ?? 0);
        $dispatchAsIs     = (int)($product['dispatch_as_is'] ?? 0);

        // Third-party: no in-house production; goes straight to dispatch lane
        if ($productionSource === 'third_party') {
            return ['dispatch'];
        }

        $path = ['production'];

        // dispatch_as_is with no QC or Assembly → bypass all intermediate stages
        if ($dispatchAsIs === 1 && $requiresQc === 0 && $requiresAssembly === 0) {
            $path[] = 'dispatch';
            return $path;
        }

        // Correct stage order: Production → Assembly → QC → Packaging → Dispatch
        // Assembly comes before QC; packaging is present unless dispatch_as_is=1
        if ($requiresAssembly === 1) {
            $path[] = 'assembly';
        }

        if ($requiresQc === 1) {
            $path[] = 'qc';
        }

        // Packaging: present for all in-house products unless dispatch_as_is=1
        if ($dispatchAsIs !== 1) {
            $path[] = 'packaging';
        }

        $path[] = 'dispatch';
        return $path;
    }

    // -------------------------------------------------------------------------
    // Batch computation for a date
    // -------------------------------------------------------------------------

    /**
     * Compute cross-stage readiness for all products with demand on a given date.
     * Returns array keyed by product_id (int).
     *
     * Each value is the result of computeForProduct() plus parts_name / parts_number.
     */
    public static function computeForDate(string $date): array
    {
        self::ensureSchema();
        DemandEngineService::ensureSchema();
        AssemblyPlanService::ensureSchema();

        $productRows = DB::fetchAll(
            "SELECT p.id, p.parts_name, p.parts_number,
                    p.supply_mode, p.requires_ipm_qc, p.requires_assembly,
                    p.dispatch_as_is, p.fulfillment_mode, p.activity_type,
                    COALESCE(MAX(CASE WHEN d.demand_type='production'
                        THEN COALESCE(d.approved_qty, d.adjusted_qty, d.system_qty)
                        ELSE NULL END), 0) AS effective_demand
             FROM products p
             INNER JOIN mfg_part_demands d ON d.product_id = p.id AND d.demand_date = ?
             GROUP BY p.id",
            [$date]
        );

        if (empty($productRows)) {
            return [];
        }

        $productIds = array_map('intval', array_column($productRows, 'id'));
        $ph = implode(',', array_fill(0, count($productIds), '?'));

        // Production actuals
        $prodRows = DB::fetchAll(
            "SELECT product_id, ROUND(COALESCE(SUM(good_qty),0),2) AS good_qty
             FROM production_entries
             WHERE production_date=? AND product_id IN ({$ph})
             GROUP BY product_id",
            array_merge([$date], $productIds)
        );
        $prodMap = [];
        foreach ($prodRows as $pr) {
            $prodMap[(int)$pr['product_id']] = (float)$pr['good_qty'];
        }

        // QC actuals
        $qcRows = DB::fetchAll(
            "SELECT product_id,
                    ROUND(COALESCE(SUM(pass_qty),0),2) AS pass_qty,
                    ROUND(COALESCE(SUM(checked_qty),0),2) AS checked_qty
             FROM qc_entries
             WHERE COALESCE(DATE(updated_at), DATE(created_at), CURRENT_DATE())=?
               AND product_id IN ({$ph})
             GROUP BY product_id",
            array_merge([$date], $productIds)
        );
        $qcMap = [];
        foreach ($qcRows as $qr) {
            $qcMap[(int)$qr['product_id']] = [
                'pass_qty'    => (float)$qr['pass_qty'],
                'checked_qty' => (float)$qr['checked_qty'],
            ];
        }

        // Assembly actuals from dedicated execution table
        $assemblyRows = DB::fetchAll(
            "SELECT product_id,
                    ROUND(COALESCE(SUM(completed_qty),0),2) AS completed_qty
             FROM mfg_assembly_entries
             WHERE assembly_date=?
               AND product_id IN ({$ph})
               AND LOWER(status) <> 'cancelled'
             GROUP BY product_id",
            array_merge([$date], $productIds)
        );
        $assemblyMap = [];
        foreach ($assemblyRows as $ar) {
            $assemblyMap[(int)$ar['product_id']] = (float)$ar['completed_qty'];
        }

        // Dispatch + Packaging actuals from dispatch_entries.
        // Packaging stage: 'ready'=started, 'prepared'=packaging complete.
        // Dispatch stage:  'completed'=dispatched.
        $dispRows = DB::fetchAll(
            "SELECT product_id,
                    ROUND(COALESCE(SUM(
                        CASE WHEN LOWER(COALESCE(completion_status,'draft'))='completed'
                             THEN dispatchable_qty ELSE 0 END
                    ),0),2) AS completed_qty,
                    ROUND(COALESCE(SUM(
                        CASE WHEN LOWER(COALESCE(completion_status,'draft')) IN ('prepared','completed')
                             THEN dispatchable_qty ELSE 0 END
                    ),0),2) AS packaging_prepared_qty,
                    ROUND(COALESCE(SUM(
                        CASE WHEN LOWER(COALESCE(completion_status,'draft')) IN ('ready','prepared','completed')
                             THEN dispatchable_qty ELSE 0 END
                    ),0),2) AS packaging_started_qty,
                    MAX(COALESCE(completion_status,'draft')) AS best_status
             FROM dispatch_entries
             WHERE dispatch_date=? AND product_id IN ({$ph})
             GROUP BY product_id",
            array_merge([$date], $productIds)
        );
        $dispMap = [];
        foreach ($dispRows as $dr) {
            $dispMap[(int)$dr['product_id']] = [
                'completed_qty'          => (float)$dr['completed_qty'],
                'packaging_prepared_qty' => (float)$dr['packaging_prepared_qty'],
                'packaging_started_qty'  => (float)$dr['packaging_started_qty'],
                'best_status'            => (string)$dr['best_status'],
            ];
        }

        // Stage readiness rows
        $readinessRows = DB::fetchAll(
            "SELECT * FROM mfg_stage_readiness WHERE ref_date=? AND product_id IN ({$ph})",
            array_merge([$date], $productIds)
        );
        $readinessMap = [];
        foreach ($readinessRows as $rr) {
            $readinessMap[(int)$rr['product_id']][(string)$rr['stage']] = $rr;
        }

        $result = [];
        foreach ($productRows as $product) {
            $productId = (int)$product['id'];
            $actuals = [
                'effective_demand'           => (float)$product['effective_demand'],
                'produced_qty'               => $prodMap[$productId] ?? 0.0,
                'assembly_completed_qty'     => $assemblyMap[$productId] ?? 0.0,
                'qc_pass_qty'                => $qcMap[$productId]['pass_qty'] ?? 0.0,
                'qc_checked_qty'             => $qcMap[$productId]['checked_qty'] ?? 0.0,
                'packaging_prepared_qty'     => $dispMap[$productId]['packaging_prepared_qty'] ?? 0.0,
                'packaging_started_qty'      => $dispMap[$productId]['packaging_started_qty'] ?? 0.0,
                'dispatch_completed_qty'     => $dispMap[$productId]['completed_qty'] ?? 0.0,
                'dispatch_best_status'       => $dispMap[$productId]['best_status'] ?? 'draft',
            ];

            $computed = self::computeForProduct(
                $productId,
                $date,
                $product,
                $actuals,
                $readinessMap[$productId] ?? []
            );
            $computed['parts_name']   = (string)$product['parts_name'];
            $computed['parts_number'] = (string)$product['parts_number'];
            $result[$productId] = $computed;
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Single-product computation
    // -------------------------------------------------------------------------

    /**
     * Compute stage readiness for one product given pre-loaded actuals and readiness rows.
     *
     * @param array $readinessRows Keyed by stage name → row from mfg_stage_readiness
     */
    public static function computeForProduct(
        int $productId,
        string $date,
        array $product,
        array $actuals,
        array $readinessRows
    ): array {
        $stagePath    = self::stagePathForProduct($product);
        $demand       = (float)($actuals['effective_demand'] ?? 0.0);
        $isPassive    = ((string)($product['activity_type'] ?? 'active') === 'passive');
        $isThirdParty = ((string)($product['supply_mode'] ?? 'in_house') === 'third_party');

        $stages       = [];
        $blockReasons = [];
        $prevReady    = true; // tracks whether previous stage is sufficiently done

        foreach (self::VALID_STAGES as $stage) {
            if (!in_array($stage, $stagePath, true)) {
                $stages[$stage] = ['status' => 'not_applicable'];
                continue;
            }

            $rrow           = $readinessRows[$stage] ?? null;
            $overrideStatus = $rrow !== null ? (string)($rrow['override_status'] ?? '') : '';

            // Manual block/skip overrides take full priority
            if ($overrideStatus === 'blocked') {
                $stages[$stage] = [
                    'status'         => 'blocked',
                    'pct'            => 0.0,
                    'blocked_reason' => (string)($rrow['blocked_reason'] ?? 'Manually blocked'),
                    'released_qty'   => 0.0,
                    'released_by'    => null,
                    'released_at'    => null,
                ];
                $prevReady = false;
                $blockReasons[] = "Stage '{$stage}' manually blocked";
                continue;
            }

            if ($overrideStatus === 'skipped') {
                $stages[$stage] = [
                    'status'       => 'skipped',
                    'pct'          => 100.0,
                    'notes'        => (string)($rrow['notes'] ?? ''),
                    'released_qty' => 0.0,
                    'released_by'  => null,
                    'released_at'  => null,
                ];
                // Skipped counts as done for downstream
                continue;
            }

            $isExplicitReleased = $rrow !== null && (float)($rrow['released_qty'] ?? 0) > 0;

            switch ($stage) {
                case 'production':
                    $goodQty = (float)($actuals['produced_qty'] ?? 0.0);
                    $pct     = $demand > 0
                        ? min(999.0, round($goodQty / $demand * 100, 1))
                        : ($goodQty > 0 ? 100.0 : 100.0);

                    if ($isThirdParty) {
                        $status = 'not_applicable';
                    } elseif ($pct >= self::COMPLETE_THRESHOLD * 100) {
                        $status = 'complete';
                    } elseif ($goodQty > 0) {
                        $status = 'in_progress';
                    } else {
                        $status = 'pending';
                    }

                    $stages[$stage] = [
                        'status'       => $status,
                        'qty'          => $goodQty,
                        'demand'       => $demand,
                        'pct'          => $pct,
                        'is_passive'   => $isPassive,
                        'released_qty' => (float)($rrow['released_qty'] ?? 0),
                        'released_by'  => $rrow['released_by'] ?? null,
                        'released_at'  => $rrow['released_at'] ?? null,
                    ];

                    $prevReady = $status === 'complete'
                        || $status === 'not_applicable'
                        || $isExplicitReleased
                        || ($status === 'in_progress' && $pct >= self::RELEASE_THRESHOLD * 100);
                    break;

                case 'assembly':
                    // Use dedicated assembly execution qty only (dispatch proxy removed; packaging is now a separate stage).
                    $assembledQty = (float)($actuals['assembly_completed_qty'] ?? 0.0);
                    $pct          = $demand > 0
                        ? min(999.0, round($assembledQty / $demand * 100, 1))
                        : ($assembledQty > 0 ? 100.0 : 100.0);

                    if ($pct >= self::COMPLETE_THRESHOLD * 100) {
                        $status = 'complete';
                    } elseif ($assembledQty > 0) {
                        $status = 'in_progress';
                    } elseif ($isExplicitReleased) {
                        $status = 'released';
                    } elseif ($prevReady) {
                        $status = 'eligible';
                    } else {
                        $status = 'pending';
                    }

                    $stages[$stage] = [
                        'status'        => $status,
                        'assembled_qty' => $assembledQty,
                        'assembly_completed_qty' => $assembledQty,
                        'demand'        => $demand,
                        'pct'           => $pct,
                        'released_qty'  => (float)($rrow['released_qty'] ?? 0),
                        'released_by'   => $rrow['released_by'] ?? null,
                        'released_at'   => $rrow['released_at'] ?? null,
                    ];

                    $prevReady = $status === 'complete'
                        || $isExplicitReleased
                        || ($status === 'in_progress' && $pct >= self::RELEASE_THRESHOLD * 100);
                    break;

                case 'qc':
                    $passQty    = (float)($actuals['qc_pass_qty'] ?? 0.0);
                    $checkedQty = (float)($actuals['qc_checked_qty'] ?? 0.0);
                    $failQty    = round(max(0.0, $checkedQty - $passQty), 2);
                    $pct        = $demand > 0
                        ? min(999.0, round($passQty / $demand * 100, 1))
                        : ($passQty > 0 ? 100.0 : 100.0);
                    $passRatio  = $demand > 0 ? ($passQty / $demand) : ($passQty > 0 ? 1.0 : 0.0);
                    $qcBlockedReason = null;

                    if ($checkedQty > 0 && $passQty <= 0) {
                        $qcBlockedReason = 'QC checked with zero pass qty';
                    } elseif ($failQty > $passQty && $passRatio < self::RELEASE_THRESHOLD) {
                        $qcBlockedReason = 'QC fail qty exceeds pass qty';
                    }

                    if ($qcBlockedReason !== null) {
                        $status = 'blocked';
                    } elseif ($pct >= self::COMPLETE_THRESHOLD * 100) {
                        $status = 'complete';
                    } elseif ($passQty > 0 || $checkedQty > 0) {
                        $status = 'in_progress';
                    } elseif ($isExplicitReleased) {
                        $status = 'released';
                    } elseif ($prevReady) {
                        $status = 'eligible';
                    } else {
                        $status = 'pending';
                    }

                    if ($qcBlockedReason !== null) {
                        $blockReasons[] = $qcBlockedReason;
                    }

                    $stages[$stage] = [
                        'status'       => $status,
                        'blocked_reason' => $qcBlockedReason,
                        'pass_qty'     => $passQty,
                        'checked_qty'  => $checkedQty,
                        'fail_qty'     => $failQty,
                        'demand'       => $demand,
                        'pct'          => $pct,
                        'released_qty' => (float)($rrow['released_qty'] ?? 0),
                        'released_by'  => $rrow['released_by'] ?? null,
                        'released_at'  => $rrow['released_at'] ?? null,
                    ];

                    $prevReady = in_array($status, ['complete', 'eligible'], true)
                        || $isExplicitReleased
                        || ($status === 'in_progress' && $pct >= self::RELEASE_THRESHOLD * 100);
                    break;

                case 'packaging':
                    $preparedQty = (float)($actuals['packaging_prepared_qty'] ?? 0.0);
                    $startedQty  = (float)($actuals['packaging_started_qty'] ?? 0.0);
                    $pct         = $demand > 0
                        ? min(999.0, round($preparedQty / $demand * 100, 1))
                        : ($preparedQty > 0 ? 100.0 : 100.0);

                    if ($pct >= self::COMPLETE_THRESHOLD * 100) {
                        $status = 'complete';
                    } elseif ($startedQty > 0) {
                        $status = 'in_progress';
                    } elseif ($isExplicitReleased) {
                        $status = 'released';
                    } elseif ($prevReady) {
                        $status = 'eligible';
                    } else {
                        $status = 'pending';
                    }

                    $stages[$stage] = [
                        'status'          => $status,
                        'prepared_qty'    => $preparedQty,
                        'started_qty'     => $startedQty,
                        'demand'          => $demand,
                        'pct'             => $pct,
                        'released_qty'    => (float)($rrow['released_qty'] ?? 0),
                        'released_by'     => $rrow['released_by'] ?? null,
                        'released_at'     => $rrow['released_at'] ?? null,
                    ];

                    $prevReady = $status === 'complete'
                        || $isExplicitReleased
                        || ($status === 'in_progress' && $pct >= self::RELEASE_THRESHOLD * 100);
                    break;

                case 'dispatch':
                    $bestStatus   = (string)($actuals['dispatch_best_status'] ?? 'draft');
                    $completedQty = (float)($actuals['dispatch_completed_qty'] ?? 0.0);

                    if ($bestStatus === 'completed') {
                        $status = 'complete';
                    } elseif (in_array($bestStatus, ['ready', 'prepared'], true)) {
                        $status = 'in_progress';
                    } elseif ($isExplicitReleased || $prevReady) {
                        $status = 'eligible';
                    } else {
                        $status = 'pending';
                    }

                    $stages[$stage] = [
                        'status'                      => $status,
                        'dispatch_completion_status'  => $bestStatus,
                        'completed_qty'               => $completedQty,
                        'demand'                      => $demand,
                        'pct'                         => $demand > 0
                            ? min(100.0, round($completedQty / $demand * 100, 1))
                            : ($completedQty > 0 ? 100.0 : 0.0),
                        'released_qty'  => (float)($rrow['released_qty'] ?? 0),
                        'released_by'   => $rrow['released_by'] ?? null,
                        'released_at'   => $rrow['released_at'] ?? null,
                    ];
                    break;
            }
        }

        // Determine which stage needs attention next
        $nextStage = null;
        foreach ($stagePath as $s) {
            $st = $stages[$s]['status'] ?? 'not_applicable';
            if (!in_array($st, ['complete', 'not_applicable', 'skipped'], true)) {
                $nextStage = $s;
                break;
            }
        }

        $canReleaseTo = self::computeCanReleaseTo($stagePath, $stages, $isThirdParty);

        foreach (self::VALID_STAGES as $stageName) {
            if (!isset($stages[$stageName])) {
                continue;
            }

            $status = (string)($stages[$stageName]['status'] ?? 'not_applicable');
            $maxReleasable = self::maxReleasableQtyForStage($stageName, $stagePath, $stages, $actuals, $demand);
            $blockedReason = (string)($stages[$stageName]['blocked_reason'] ?? '');
            if ($blockedReason === '' && $status === 'blocked') {
                $blockedReason = 'Blocked by workflow rule';
            }

            $stages[$stageName]['current_status'] = $status;
            $stages[$stageName]['releasable_qty'] = $maxReleasable;
            $stages[$stageName]['blocked_reason'] = $blockedReason;
            $stages[$stageName]['next_allowed_action'] = self::nextAllowedActionForStage(
                $stageName,
                $status,
                $canReleaseTo,
                $maxReleasable,
                $stagePath
            );
        }

        return [
            'stage_path'       => $stagePath,
            'stages'           => $stages,
            'next_stage'       => $nextStage,
            'can_release_to'   => $canReleaseTo,
            'is_fully_blocked' => !empty($blockReasons),
            'block_reasons'    => $blockReasons,
            'is_passive'       => $isPassive,
            'is_third_party'   => $isThirdParty,
        ];
    }

    // -------------------------------------------------------------------------
    // Release / override actions
    // -------------------------------------------------------------------------

    /**
     * Explicitly release a stage for a product/date (handoff action).
     */
    public static function releaseStage(
        int $productId,
        string $date,
        string $stage,
        float $qty,
        string $notes = ''
    ): void {
        if (!in_array($stage, self::VALID_STAGES, true)) {
            throw new \InvalidArgumentException("Invalid stage: {$stage}");
        }
        if ($productId <= 0) {
            throw new \InvalidArgumentException('Invalid product_id.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \InvalidArgumentException('Invalid date format.');
        }

        self::ensureSchema();

        $snapshotByDate = self::computeForDate($date);
        $snapshot = $snapshotByDate[$productId] ?? null;
        if (!is_array($snapshot)) {
            throw new \RuntimeException('No workflow snapshot available for this product/date.');
        }

        if (!in_array($stage, (array)($snapshot['can_release_to'] ?? []), true)) {
            $status = (string)($snapshot['stages'][$stage]['status'] ?? 'pending');
            $reason = (string)($snapshot['stages'][$stage]['blocked_reason'] ?? '');
            if ($reason === '') {
                $reason = 'Current stage status: ' . $status;
            }
            throw new \RuntimeException('Stage release not allowed for ' . $stage . '. ' . $reason);
        }

        $maxReleasable = (float)($snapshot['stages'][$stage]['releasable_qty'] ?? 0.0);
        $qty = round(max(0.0, $qty), 2);
        if ($qty <= 0.0) {
            throw new \RuntimeException('Release qty must be greater than zero.');
        }
        if ($qty - $maxReleasable > 0.0001) {
            throw new \RuntimeException('Release qty exceeds stage releasable qty (' . $maxReleasable . ').');
        }

        DB::query(
            "INSERT INTO mfg_stage_readiness
                (product_id, ref_date, stage, released_qty, released_by, released_at, override_status, notes)
             VALUES (?, ?, ?, ?, ?, NOW(), 'released', ?)
             ON DUPLICATE KEY UPDATE
                released_qty     = VALUES(released_qty),
                released_by      = VALUES(released_by),
                released_at      = NOW(),
                override_status  = 'released',
                notes            = VALUES(notes),
                updated_at       = NOW()",
            [$productId, $date, $stage, round(max(0.0, $qty), 2), self::currentUserLabel(), $notes !== '' ? $notes : null]
        );
    }

    /**
     * Override a stage to blocked or skipped (ignores actuals for that stage).
     */
    public static function overrideStageStatus(
        int $productId,
        string $date,
        string $stage,
        string $overrideStatus,
        string $reason = ''
    ): void {
        if (!in_array($stage, self::VALID_STAGES, true)) {
            throw new \InvalidArgumentException("Invalid stage: {$stage}");
        }
        if (!in_array($overrideStatus, ['blocked', 'skipped'], true)) {
            throw new \InvalidArgumentException("Invalid override status: {$overrideStatus}");
        }

        self::ensureSchema();

        DB::query(
            "INSERT INTO mfg_stage_readiness
                (product_id, ref_date, stage, released_qty, override_status, blocked_reason)
             VALUES (?, ?, ?, 0, ?, ?)
             ON DUPLICATE KEY UPDATE
                override_status  = VALUES(override_status),
                blocked_reason   = VALUES(blocked_reason),
                updated_at       = NOW()",
            [$productId, $date, $stage, $overrideStatus, $reason !== '' ? $reason : null]
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Determine which stages can be explicitly released right now.
     * A stage can be released when the preceding stage is sufficiently complete
     * and the target stage has not already started or been released.
     */
    private static function computeCanReleaseTo(array $stagePath, array $stages, bool $isThirdParty): array
    {
        $canReleaseTo = [];

        // Third party → dispatch is always releasable unless already progressing
        if ($isThirdParty) {
            $dispStatus = $stages['dispatch']['status'] ?? 'pending';
            if (!in_array($dispStatus, ['in_progress', 'complete'], true)) {
                $canReleaseTo[] = 'dispatch';
            }
            return $canReleaseTo;
        }

        $pathLen = count($stagePath);
        for ($i = 1; $i < $pathLen; $i++) {
            $prevStage = $stagePath[$i - 1];
            $thisStage = $stagePath[$i];

            $prevStatus = $stages[$prevStage]['status'] ?? 'not_applicable';
            $prevPct    = (float)($stages[$prevStage]['pct'] ?? ($prevStatus === 'not_applicable' ? 100.0 : 0.0));
            $thisStatus = $stages[$thisStage]['status'] ?? 'pending';

            // Already past this stage — no release button needed
            if (in_array($thisStatus, ['in_progress', 'complete', 'released', 'skipped', 'blocked'], true)) {
                continue;
            }

            $prevSufficient = in_array($prevStatus, ['complete', 'not_applicable', 'skipped'], true)
                || ($prevStatus === 'in_progress' && $prevPct >= self::RELEASE_THRESHOLD * 100);

            if ($prevSufficient) {
                $canReleaseTo[] = $thisStage;
            }
        }

        return $canReleaseTo;
    }

    private static function maxReleasableQtyForStage(
        string $stage,
        array $stagePath,
        array $stages,
        array $actuals,
        float $demand
    ): float {
        if (!in_array($stage, $stagePath, true)) {
            return 0.0;
        }

        $incomingQty = 0.0;
        $pathIndex = array_search($stage, $stagePath, true);
        if ($pathIndex === 0) {
            $incomingQty = max(0.0, $demand);
        } else {
            $prevStage = (string)$stagePath[$pathIndex - 1];
            if ($prevStage === 'production') {
                $incomingQty = min(max(0.0, $demand), max(0.0, (float)($actuals['produced_qty'] ?? 0.0)));
            } elseif ($prevStage === 'assembly') {
                $incomingQty = min(max(0.0, $demand), max(0.0, (float)($actuals['assembly_completed_qty'] ?? 0.0)));
            } elseif ($prevStage === 'qc') {
                $incomingQty = min(max(0.0, $demand), max(0.0, (float)($actuals['qc_pass_qty'] ?? 0.0)));
            } elseif ($prevStage === 'packaging') {
                $incomingQty = min(max(0.0, $demand), max(0.0, (float)($actuals['packaging_prepared_qty'] ?? 0.0)));
            }
        }

        $explicitReleaseQty = (float)($stages[$stage]['released_qty'] ?? 0.0);
        if ($explicitReleaseQty > 0.0) {
            $incomingQty = min($incomingQty, $explicitReleaseQty);
        }

        return round(max(0.0, $incomingQty), 2);
    }

    private static function nextAllowedActionForStage(
        string $stage,
        string $status,
        array $canReleaseTo,
        float $releasableQty,
        array $stagePath
    ): string {
        if (!in_array($stage, $stagePath, true)) {
            return 'none';
        }
        if ($status === 'blocked') {
            return 'resolve_block';
        }
        if ($status === 'pending') {
            return 'wait_upstream';
        }
        if ($status === 'eligible' && in_array($stage, $canReleaseTo, true) && $releasableQty > 0.0) {
            return $stage === 'dispatch' ? 'prepare_dispatch' : 'release_stage';
        }
        if ($status === 'in_progress') {
            return $stage === 'dispatch' ? 'complete_dispatch' : 'record_execution';
        }
        if ($status === 'complete') {
            return $stage === 'dispatch' ? 'none' : 'release_stage';
        }
        if ($status === 'released') {
            return $stage === 'dispatch' ? 'prepare_dispatch' : 'record_execution';
        }
        if ($status === 'skipped' || $status === 'not_applicable') {
            return 'none';
        }

        return 'wait_upstream';
    }

    private static function currentUserLabel(): string
    {
        $u     = Auth::user();
        $email = trim((string)($u['email'] ?? ''));
        return $email !== '' ? $email : 'system';
    }
}
