<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model;

class RollOutcomeResolver
{
    public function resolve(int $roll): string
    {
        return match ($roll) {
            1 => 'critical_failure',
            2 => 'reindex_all',
            3 => 'cache_flush',
            4 => 'graphql_pipeline_stress',
            5 => 'indexer_status_snapshot',
            6 => 'cache_backend_health_snapshot',
            7 => 'cron_queue_health_snapshot',
            20 => 'critical_success',
            default => 'napping',
        };
    }
}
