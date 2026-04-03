<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model\Profile;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Model\Outcome\OutcomeCatalog;
use ShaunMcManus\ChaosDonkey\Model\Profile\ExecutionProfileCatalog;

class ExecutionProfileCatalogTest extends TestCase
{
    public function testItSupportsLookupByProfileCode(): void
    {
        $catalog = new ExecutionProfileCatalog();

        self::assertSame($catalog->all()['chaos'], $catalog->getByCode('chaos'));
        self::assertNull($catalog->getByCode('unknown'));
    }

    public function testItExposesBalancedAsFallbackProfileCode(): void
    {
        $catalog = new ExecutionProfileCatalog();

        self::assertSame('balanced', $catalog->getFallbackProfileCode());
    }

    public function testItExposesStableProfileLabels(): void
    {
        $catalog = new ExecutionProfileCatalog();

        self::assertSame(
            [
                'balanced' => 'Balanced',
                'chaos' => 'Chaos',
                'all_gas_no_brakes' => 'All Gas No Brakes',
            ],
            $catalog->getProfileLabels()
        );
    }

    public function testItFailsFastWhenBuiltInProfileKeysDoNotMatchSupportedProfiles(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Built-in execution profiles are not aligned with supported profile labels.');

        new ExecutionProfileCatalog(null, [
            'balanced' => [
                'critical_failure' => 1,
                'reindex_all' => 1,
                'cache_flush' => 1,
                'graphql_pipeline_stress' => 1,
                'indexer_status_snapshot' => 1,
                'cache_backend_health_snapshot' => 1,
                'cron_queue_health_snapshot' => 1,
                'napping' => 12,
                'critical_success' => 1,
            ],
        ]);
    }

    public function testItFailsFastWhenBuiltInProfileTableIsInvalid(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Built-in execution profile "balanced" is invalid.');

        new ExecutionProfileCatalog(null, [
            'balanced' => [
                'critical_failure' => 1,
                'reindex_all' => 1,
                'cache_flush' => 1,
                'graphql_pipeline_stress' => 1,
                'indexer_status_snapshot' => 1,
                'cache_backend_health_snapshot' => 1,
                'cron_queue_health_snapshot' => 1,
                'napping' => 11,
                'critical_success' => 1,
            ],
            'chaos' => [
                'critical_failure' => 2,
                'reindex_all' => 3,
                'cache_flush' => 3,
                'graphql_pipeline_stress' => 3,
                'indexer_status_snapshot' => 1,
                'cache_backend_health_snapshot' => 1,
                'cron_queue_health_snapshot' => 1,
                'napping' => 5,
                'critical_success' => 1,
            ],
            'all_gas_no_brakes' => [
                'critical_failure' => 2,
                'reindex_all' => 5,
                'cache_flush' => 5,
                'graphql_pipeline_stress' => 5,
                'indexer_status_snapshot' => 0,
                'cache_backend_health_snapshot' => 0,
                'cron_queue_health_snapshot' => 0,
                'napping' => 2,
                'critical_success' => 1,
            ],
        ]);
    }

    public function testItReturnsOnlyTheThreeSupportedProfileKeys(): void
    {
        $catalog = new ExecutionProfileCatalog();

        self::assertSame(
            ['balanced', 'chaos', 'all_gas_no_brakes'],
            array_keys($catalog->all())
        );
    }

    public function testItReturnsBalancedSlotsInExactCanonicalOrder(): void
    {
        $catalog = new ExecutionProfileCatalog();

        self::assertSame(
            [
                'critical_failure' => 1,
                'reindex_all' => 1,
                'cache_flush' => 1,
                'graphql_pipeline_stress' => 1,
                'indexer_status_snapshot' => 1,
                'cache_backend_health_snapshot' => 1,
                'cron_queue_health_snapshot' => 1,
                'napping' => 12,
                'critical_success' => 1,
            ],
            $catalog->all()['balanced']
        );
    }

    public function testItReturnsChaosSlotsInExactCanonicalOrder(): void
    {
        $catalog = new ExecutionProfileCatalog();

        self::assertSame(
            [
                'critical_failure' => 2,
                'reindex_all' => 3,
                'cache_flush' => 3,
                'graphql_pipeline_stress' => 3,
                'indexer_status_snapshot' => 1,
                'cache_backend_health_snapshot' => 1,
                'cron_queue_health_snapshot' => 1,
                'napping' => 5,
                'critical_success' => 1,
            ],
            $catalog->all()['chaos']
        );
    }

    public function testItReturnsAllGasNoBrakesSlotsInExactCanonicalOrder(): void
    {
        $catalog = new ExecutionProfileCatalog();

        self::assertSame(
            [
                'critical_failure' => 2,
                'reindex_all' => 5,
                'cache_flush' => 5,
                'graphql_pipeline_stress' => 5,
                'indexer_status_snapshot' => 0,
                'cache_backend_health_snapshot' => 0,
                'cron_queue_health_snapshot' => 0,
                'napping' => 2,
                'critical_success' => 1,
            ],
            $catalog->all()['all_gas_no_brakes']
        );
    }

    #[DataProvider('profileNameProvider')]
    public function testEachProfileHasExactCanonicalOutcomeKeys(string $profileName): void
    {
        $outcomeCatalog = new OutcomeCatalog();
        $profileCatalog = new ExecutionProfileCatalog();

        $canonicalKeys = $outcomeCatalog->getOutcomeCodes();
        $profileTable = $profileCatalog->all()[$profileName];

        self::assertSame($canonicalKeys, array_keys($profileTable));
    }

    #[DataProvider('profileNameProvider')]
    public function testEachProfileContainsNoUnknownKeys(string $profileName): void
    {
        $outcomeCatalog = new OutcomeCatalog();
        $profileCatalog = new ExecutionProfileCatalog();

        $canonicalKeys = $outcomeCatalog->getOutcomeCodes();
        $profileTable = $profileCatalog->all()[$profileName];

        self::assertSame([], array_diff(array_keys($profileTable), $canonicalKeys));
    }

    #[DataProvider('profileNameProvider')]
    public function testEachProfileUsesOnlyNonNegativeIntegerValues(string $profileName): void
    {
        $catalog = new ExecutionProfileCatalog();
        $profileTable = $catalog->all()[$profileName];

        foreach ($profileTable as $slots) {
            self::assertIsInt($slots);
            self::assertGreaterThanOrEqual(0, $slots);
        }
    }

    #[DataProvider('profileNameProvider')]
    public function testEachProfileHasExactlyTwentyTotalSlots(string $profileName): void
    {
        $catalog = new ExecutionProfileCatalog();
        $profileTable = $catalog->all()[$profileName];

        self::assertSame(20, array_sum($profileTable));
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function profileNameProvider(): array
    {
        return [
            ['balanced'],
            ['chaos'],
            ['all_gas_no_brakes'],
        ];
    }
}
