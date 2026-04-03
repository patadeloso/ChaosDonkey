<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model;

/**
 * @deprecated Runtime selection now flows through Profile\ProfiledRollSelector.
 *             This class is retained only as a legacy balanced-table helper.
 */
class RollOutcomeResolver
{
    /**
     * Legacy balanced D20 table kept for backward-compatibility utilities/tests.
     *
     * @var array<int, string>
     */
    private const LEGACY_BALANCED_TABLE = [
        1 => 'critical_failure',
        2 => 'reindex_all',
        3 => 'cache_flush',
        4 => 'graphql_pipeline_stress',
        5 => 'indexer_status_snapshot',
        6 => 'cache_backend_health_snapshot',
        7 => 'cron_queue_health_snapshot',
        8 => 'napping',
        9 => 'napping',
        10 => 'napping',
        11 => 'napping',
        12 => 'napping',
        13 => 'napping',
        14 => 'napping',
        15 => 'napping',
        16 => 'napping',
        17 => 'napping',
        18 => 'napping',
        19 => 'napping',
        20 => 'critical_success',
    ];

    /**
     * @deprecated Use Profile\ProfiledRollSelector::resolveForSlot() for runtime selection.
     */
    public function resolve(int $roll): string
    {
        return self::LEGACY_BALANCED_TABLE[$this->normalizeSlot($roll)];
    }

    private function normalizeSlot(int $roll): int
    {
        $zeroBased = (($roll - 1) % 20 + 20) % 20;

        return $zeroBased + 1;
    }
}
