<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model\Outcome;

use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Model\Outcome\OutcomeCatalog;

class OutcomeCatalogTest extends TestCase
{
    public function testItReturnsCanonicalOutcomeOrder(): void
    {
        $catalog = new OutcomeCatalog();

        self::assertSame(
            [
                'critical_failure',
                'reindex_all',
                'cache_flush',
                'graphql_pipeline_stress',
                'indexer_status_snapshot',
                'cache_backend_health_snapshot',
                'cron_queue_health_snapshot',
                'napping',
                'critical_success',
            ],
            $catalog->getOutcomeCodes()
        );
    }
}
