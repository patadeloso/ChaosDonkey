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
            20 => 'critical_success',
            default => 'napping',
        };
    }
}
