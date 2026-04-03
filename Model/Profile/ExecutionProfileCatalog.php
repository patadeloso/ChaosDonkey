<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model\Profile;

use LogicException;
use ShaunMcManus\ChaosDonkey\Model\Outcome\OutcomeCatalog;

final class ExecutionProfileCatalog
{
    private const FALLBACK_PROFILE_CODE = 'balanced';

    private const PROFILE_LABELS = [
        'balanced' => 'Balanced',
        'chaos' => 'Chaos',
        'all_gas_no_brakes' => 'All Gas No Brakes',
    ];

    private const BUILT_IN_PROFILES = [
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
    ];

    private OutcomeCatalog $outcomeCatalog;

    /**
     * @var array<string, array<string, int>>
     */
    private array $builtInProfiles;

    /**
     * @param array<string, array<string, int>>|null $builtInProfiles
     */
    public function __construct(?OutcomeCatalog $outcomeCatalog = null, ?array $builtInProfiles = null)
    {
        $this->outcomeCatalog = $outcomeCatalog ?? new OutcomeCatalog();
        $this->builtInProfiles = $builtInProfiles ?? self::BUILT_IN_PROFILES;

        $this->assertBuiltInProfilesAreValid();
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function all(): array
    {
        return $this->builtInProfiles;
    }

    /**
     * @return array<string, string>
     */
    public function getProfileLabels(): array
    {
        $labels = [];

        foreach (array_keys($this->builtInProfiles) as $profileCode) {
            $labels[$profileCode] = self::PROFILE_LABELS[$profileCode];
        }

        return $labels;
    }

    /**
     * @return array<string, int>|null
     */
    public function getByCode(string $profileCode): ?array
    {
        $profiles = $this->all();

        return $profiles[$profileCode] ?? null;
    }

    /**
     * @return array<string, int>|null
     */
    public function getProfileTable(string $profileCode): ?array
    {
        return $this->getByCode($profileCode);
    }

    public function getFallbackProfileCode(): string
    {
        return self::FALLBACK_PROFILE_CODE;
    }

    /**
     * @param array<string, mixed> $profileTable
     */
    private function isValidProfileTable(array $profileTable): bool
    {
        $canonicalOutcomeCodes = $this->outcomeCatalog->getOutcomeCodes();
        $profileOutcomeCodes = array_keys($profileTable);

        if ($profileOutcomeCodes !== $canonicalOutcomeCodes) {
            return false;
        }

        $slotCount = 0;

        foreach ($profileTable as $slots) {
            if (!is_int($slots) || $slots < 0) {
                return false;
            }

            $slotCount += $slots;
        }

        return $slotCount === 20;
    }

    private function assertBuiltInProfilesAreValid(): void
    {
        if (array_keys($this->builtInProfiles) !== array_keys(self::PROFILE_LABELS)) {
            throw new LogicException('Built-in execution profiles are not aligned with supported profile labels.');
        }

        foreach ($this->builtInProfiles as $profileCode => $profileTable) {
            if (!$this->isValidProfileTable($profileTable)) {
                throw new LogicException(
                    sprintf('Built-in execution profile "%s" is invalid.', $profileCode)
                );
            }
        }
    }
}
