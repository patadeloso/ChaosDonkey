<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model\Profile;

use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use ShaunMcManus\ChaosDonkey\Model\Outcome\OutcomeCatalog;
use ShaunMcManus\ChaosDonkey\Model\Profile\ExecutionProfileCatalog;
use ShaunMcManus\ChaosDonkey\Model\Profile\ProfiledRollSelector;

class ProfiledRollSelectorTest extends TestCase
{
    public function testBuildEffectiveTableForBalancedReproducesLegacySlotsExactly(): void
    {
        $selector = $this->createSelector();

        self::assertSame(
            $this->legacyBalancedExpandedTable(),
            $selector->buildEffectiveTable('balanced', (new OutcomeCatalog())->getOutcomeCodes())
        );
    }

    public function testBuildEffectiveTableForChaosProducesExactExpandedTwentySlotTable(): void
    {
        $selector = $this->createSelector();

        self::assertSame(
            [
                1 => 'critical_failure',
                2 => 'critical_failure',
                3 => 'reindex_all',
                4 => 'reindex_all',
                5 => 'reindex_all',
                6 => 'cache_flush',
                7 => 'cache_flush',
                8 => 'cache_flush',
                9 => 'graphql_pipeline_stress',
                10 => 'graphql_pipeline_stress',
                11 => 'graphql_pipeline_stress',
                12 => 'indexer_status_snapshot',
                13 => 'cache_backend_health_snapshot',
                14 => 'cron_queue_health_snapshot',
                15 => 'napping',
                16 => 'napping',
                17 => 'napping',
                18 => 'napping',
                19 => 'napping',
                20 => 'critical_success',
            ],
            $selector->buildEffectiveTable('chaos', (new OutcomeCatalog())->getOutcomeCodes())
        );
    }

    public function testBuildEffectiveTableForAllGasNoBrakesProducesExactExpandedTwentySlotTable(): void
    {
        $selector = $this->createSelector();

        self::assertSame(
            [
                1 => 'critical_failure',
                2 => 'critical_failure',
                3 => 'reindex_all',
                4 => 'reindex_all',
                5 => 'reindex_all',
                6 => 'reindex_all',
                7 => 'reindex_all',
                8 => 'cache_flush',
                9 => 'cache_flush',
                10 => 'cache_flush',
                11 => 'cache_flush',
                12 => 'cache_flush',
                13 => 'graphql_pipeline_stress',
                14 => 'graphql_pipeline_stress',
                15 => 'graphql_pipeline_stress',
                16 => 'graphql_pipeline_stress',
                17 => 'graphql_pipeline_stress',
                18 => 'napping',
                19 => 'napping',
                20 => 'critical_success',
            ],
            $selector->buildEffectiveTable('all_gas_no_brakes', (new OutcomeCatalog())->getOutcomeCodes())
        );
    }

    public function testBuildEffectiveTableFiltersDisabledOutcomesRenormalizesToTwentySlotsAndIsDeterministic(): void
    {
        $selector = $this->createSelector();
        $eligibleOutcomes = [
            'critical_failure',
            'reindex_all',
            'indexer_status_snapshot',
            'cache_backend_health_snapshot',
            'cron_queue_health_snapshot',
            'napping',
            'critical_success',
        ];

        $first = $selector->buildEffectiveTable('balanced', $eligibleOutcomes);
        $second = $selector->buildEffectiveTable('balanced', $eligibleOutcomes);

        self::assertSame(
            [
                1 => 'critical_failure',
                2 => 'reindex_all',
                3 => 'indexer_status_snapshot',
                4 => 'cache_backend_health_snapshot',
                5 => 'cron_queue_health_snapshot',
                6 => 'napping',
                7 => 'napping',
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
            ],
            $first
        );
        self::assertSame(20, count($first));
        self::assertNotContains('cache_flush', $first);
        self::assertNotContains('graphql_pipeline_stress', $first);
        self::assertSame($first, $second);
    }

    public function testBuildEffectiveTableUsesCanonicalOrderToBreakLargestRemainderTies(): void
    {
        $selector = $this->createSelector();
        $eligibleOutcomesInNonCanonicalOrder = ['critical_success', 'reindex_all', 'critical_failure'];

        self::assertSame(
            [
                1 => 'critical_failure',
                2 => 'critical_failure',
                3 => 'critical_failure',
                4 => 'critical_failure',
                5 => 'critical_failure',
                6 => 'critical_failure',
                7 => 'critical_failure',
                8 => 'reindex_all',
                9 => 'reindex_all',
                10 => 'reindex_all',
                11 => 'reindex_all',
                12 => 'reindex_all',
                13 => 'reindex_all',
                14 => 'reindex_all',
                15 => 'critical_success',
                16 => 'critical_success',
                17 => 'critical_success',
                18 => 'critical_success',
                19 => 'critical_success',
                20 => 'critical_success',
            ],
            $selector->buildEffectiveTable('balanced', $eligibleOutcomesInNonCanonicalOrder)
        );
    }

    public function testBuildEffectiveTableFallsBackToBalancedWhenConfiguredProfileKeyIsInvalid(): void
    {
        $selector = $this->createSelector();

        self::assertSame(
            $this->legacyBalancedExpandedTable(),
            $selector->buildEffectiveTable('definitely_not_a_real_profile', (new OutcomeCatalog())->getOutcomeCodes())
        );
    }

    public function testBuildEffectiveTableFallsBackToBalancedWhenNonBalancedBuiltInProfileIsInvalid(): void
    {
        $outcomeCatalog = new OutcomeCatalog();
        $profileCatalog = new ExecutionProfileCatalog($outcomeCatalog);
        $profiles = $profileCatalog->all();
        $profiles['chaos']['napping'] = 4;

        $this->setBuiltInProfiles($profileCatalog, $profiles);
        $selector = $this->createSelector($outcomeCatalog, $profileCatalog);

        self::assertSame(
            $this->legacyBalancedExpandedTable(),
            $selector->buildEffectiveTable('chaos', $outcomeCatalog->getOutcomeCodes())
        );
    }

    public function testBuildEffectiveTableUsesEmergencyLegacyTableWhenBalancedProfileIsInvalid(): void
    {
        $outcomeCatalog = new OutcomeCatalog();
        $profileCatalog = new ExecutionProfileCatalog($outcomeCatalog);
        $profiles = $profileCatalog->all();
        $profiles['balanced']['napping'] = 11;

        $this->setBuiltInProfiles($profileCatalog, $profiles);
        $selector = $this->createSelector($outcomeCatalog, $profileCatalog);

        self::assertSame(
            $this->legacyBalancedExpandedTable(),
            $selector->buildEffectiveTable('balanced', $outcomeCatalog->getOutcomeCodes())
        );
    }

    public function testBuildEffectiveTableFailsSafeWhenNoEligibleCanonicalOutcomesRemain(): void
    {
        $selector = $this->createSelector();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No eligible canonical outcomes remain for deterministic profile selection.');

        $selector->buildEffectiveTable('balanced', []);
    }

    public function testResolveForSlotFailsSafeWhenEligibilityContainsNoCanonicalOutcomes(): void
    {
        $selector = $this->createSelector();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No eligible canonical outcomes remain for deterministic profile selection.');

        $selector->resolveForSlot('balanced', ['not_a_real_outcome'], 7);
    }

    private function createSelector(?OutcomeCatalog $outcomeCatalog = null, ?ExecutionProfileCatalog $profileCatalog = null): ProfiledRollSelector
    {
        $outcomeCatalog = $outcomeCatalog ?? new OutcomeCatalog();
        $profileCatalog = $profileCatalog ?? new ExecutionProfileCatalog($outcomeCatalog);

        return new ProfiledRollSelector($outcomeCatalog, $profileCatalog);
    }

    /**
     * @param array<string, array<string, int>> $profiles
     */
    private function setBuiltInProfiles(ExecutionProfileCatalog $profileCatalog, array $profiles): void
    {
        $property = new ReflectionProperty(ExecutionProfileCatalog::class, 'builtInProfiles');
        $property->setValue($profileCatalog, $profiles);
    }

    /**
     * @return array<int, string>
     */
    private function legacyBalancedExpandedTable(): array
    {
        return [
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
    }
}
