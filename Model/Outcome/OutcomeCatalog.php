<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model\Outcome;

final class OutcomeCatalog
{
    private const OUTCOME_CODES = [
        'critical_failure',
        'reindex_all',
        'cache_flush',
        'graphql_pipeline_stress',
        'indexer_status_snapshot',
        'cache_backend_health_snapshot',
        'cron_queue_health_snapshot',
        'napping',
        'critical_success',
    ];

    /**
     * @return array<int, string>
     */
    public function getOutcomeCodes(): array
    {
        return self::OUTCOME_CODES;
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return $this->getOutcomeCodes();
    }
}
