<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Model\RollOutcomeResolver;

class RollOutcomeResolverTest extends TestCase
{
    public function testItResolvesConfiguredRollOutcomes(): void
    {
        $resolver = new RollOutcomeResolver();

        self::assertSame('reindex_all', $resolver->resolve(2));
        self::assertSame('cache_flush', $resolver->resolve(3));
        self::assertSame('graphql_pipeline_stress', $resolver->resolve(4));
        self::assertSame('critical_failure', $resolver->resolve(1));
        self::assertSame('critical_success', $resolver->resolve(20));
        self::assertSame('napping', $resolver->resolve(6));
    }
}
