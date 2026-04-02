<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model\Profile;

use LogicException;
use ShaunMcManus\ChaosDonkey\Model\Outcome\OutcomeCatalog;

final class ProfiledRollSelector
{
    private const SLOT_COUNT = 20;

    /**
     * @var array<int, string>
     */
    private const EMERGENCY_LEGACY_BALANCED_TABLE = [
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

    private OutcomeCatalog $outcomeCatalog;

    private ExecutionProfileCatalog $executionProfileCatalog;

    public function __construct(
        ?OutcomeCatalog $outcomeCatalog = null,
        ?ExecutionProfileCatalog $executionProfileCatalog = null
    ) {
        $this->outcomeCatalog = $outcomeCatalog ?? new OutcomeCatalog();
        $this->executionProfileCatalog = $executionProfileCatalog ?? new ExecutionProfileCatalog($this->outcomeCatalog);
    }

    /**
     * @param array<int, string> $eligibleOutcomeCodes
     * @return array<int, string>
     */
    public function buildEffectiveTable(string $configuredProfileCode, array $eligibleOutcomeCodes): array
    {
        $resolution = $this->resolveForSlot($configuredProfileCode, $eligibleOutcomeCodes, 1);

        return $resolution['effective_table'];
    }

    /**
     * @param array<int, string> $eligibleOutcomeCodes
     * @return array{
     *     configured_profile: string,
     *     effective_profile: string,
     *     fallback_reason: string|null,
     *     effective_table: array<int, string>,
     *     rolled_slot: int,
     *     selected_outcome: string
     * }
     */
    public function resolveForSlot(string $configuredProfileCode, array $eligibleOutcomeCodes, int $rolledSlot): array
    {
        $profileResolution = $this->resolveProfileTable($configuredProfileCode);
        $effectiveTable = $this->buildEffectiveTableFromProfile($profileResolution['profile_table'], $eligibleOutcomeCodes);

        if ($effectiveTable === null) {
            $fallbackCode = $this->executionProfileCatalog->getFallbackProfileCode();
            $fallbackTable = $this->executionProfileCatalog->getByCode($fallbackCode);

            if ($fallbackTable !== null && $this->isValidProfileTable($fallbackTable)) {
                $effectiveTable = $this->buildEffectiveTableFromProfile($fallbackTable, $eligibleOutcomeCodes);
                $profileResolution['effective_profile'] = $fallbackCode;
                $profileResolution['fallback_reason'] = $profileResolution['fallback_reason'] ?? 'invalid_effective_profile_for_eligible_outcomes';
            }

            if ($effectiveTable === null) {
                $effectiveTable = $this->buildEffectiveTableFromProfile($this->legacyBalancedProfileTable(), $eligibleOutcomeCodes);
                $profileResolution['effective_profile'] = $fallbackCode;
                $profileResolution['fallback_reason'] = $profileResolution['fallback_reason'] ?? 'invalid_effective_profile_for_eligible_outcomes';
            }
        }

        if ($effectiveTable === null) {
            throw new LogicException('No eligible canonical outcomes remain for deterministic profile selection.');
        }

        $normalizedSlot = $this->normalizeSlot($rolledSlot);

        return [
            'configured_profile' => $configuredProfileCode,
            'effective_profile' => $profileResolution['effective_profile'],
            'fallback_reason' => $profileResolution['fallback_reason'],
            'effective_table' => $effectiveTable,
            'rolled_slot' => $normalizedSlot,
            'selected_outcome' => $effectiveTable[$normalizedSlot],
        ];
    }

    /**
     * @return array{effective_profile: string, fallback_reason: string|null, profile_table: array<string, int>}
     */
    private function resolveProfileTable(string $configuredProfileCode): array
    {
        $fallbackCode = $this->executionProfileCatalog->getFallbackProfileCode();
        $configuredProfileTable = $this->executionProfileCatalog->getByCode($configuredProfileCode);

        if ($configuredProfileTable === null) {
            $fallbackTable = $this->executionProfileCatalog->getByCode($fallbackCode);

            if ($fallbackTable !== null && $this->isValidProfileTable($fallbackTable)) {
                return [
                    'effective_profile' => $fallbackCode,
                    'fallback_reason' => 'invalid_configured_profile',
                    'profile_table' => $fallbackTable,
                ];
            }

            return [
                'effective_profile' => $fallbackCode,
                'fallback_reason' => 'invalid_fallback_profile',
                'profile_table' => $this->legacyBalancedProfileTable(),
            ];
        }

        if ($this->isValidProfileTable($configuredProfileTable)) {
            return [
                'effective_profile' => $configuredProfileCode,
                'fallback_reason' => null,
                'profile_table' => $configuredProfileTable,
            ];
        }

        if ($configuredProfileCode === $fallbackCode) {
            return [
                'effective_profile' => $fallbackCode,
                'fallback_reason' => 'invalid_fallback_profile',
                'profile_table' => $this->legacyBalancedProfileTable(),
            ];
        }

        $fallbackTable = $this->executionProfileCatalog->getByCode($fallbackCode);

        if ($fallbackTable !== null && $this->isValidProfileTable($fallbackTable)) {
            return [
                'effective_profile' => $fallbackCode,
                'fallback_reason' => 'invalid_profile_table',
                'profile_table' => $fallbackTable,
            ];
        }

        return [
            'effective_profile' => $fallbackCode,
            'fallback_reason' => 'invalid_fallback_profile',
            'profile_table' => $this->legacyBalancedProfileTable(),
        ];
    }

    /**
     * @param array<string, int> $profileTable
     * @param array<int, string> $eligibleOutcomeCodes
     * @return array<int, string>|null
     */
    private function buildEffectiveTableFromProfile(array $profileTable, array $eligibleOutcomeCodes): ?array
    {
        $canonicalOutcomeCodes = $this->outcomeCatalog->getOutcomeCodes();
        $eligibleSet = array_fill_keys($eligibleOutcomeCodes, true);
        $eligibleCanonicalOutcomes = [];
        $filteredWeights = [];

        foreach ($canonicalOutcomeCodes as $outcomeCode) {
            if (!isset($eligibleSet[$outcomeCode])) {
                continue;
            }

            $eligibleCanonicalOutcomes[] = $outcomeCode;
            $filteredWeights[$outcomeCode] = $profileTable[$outcomeCode] ?? 0;
        }

        if ($eligibleCanonicalOutcomes === []) {
            return null;
        }

        $totalWeight = array_sum($filteredWeights);

        if ($totalWeight <= 0) {
            return null;
        }

        $allocations = [];
        $remainders = [];
        $slotsAssigned = 0;

        $canonicalIndexByOutcome = array_flip($canonicalOutcomeCodes);

        foreach ($eligibleCanonicalOutcomes as $outcomeCode) {
            $exactSlots = ($filteredWeights[$outcomeCode] / $totalWeight) * self::SLOT_COUNT;
            $baseSlots = (int) floor($exactSlots);

            $allocations[$outcomeCode] = $baseSlots;
            $remainders[] = [
                'outcome' => $outcomeCode,
                'remainder' => $exactSlots - $baseSlots,
                'canonical_index' => $canonicalIndexByOutcome[$outcomeCode],
            ];
            $slotsAssigned += $baseSlots;
        }

        $slotsRemaining = self::SLOT_COUNT - $slotsAssigned;

        usort($remainders, static function (array $left, array $right): int {
            $remainderCompare = $right['remainder'] <=> $left['remainder'];

            if ($remainderCompare !== 0) {
                return $remainderCompare;
            }

            return $left['canonical_index'] <=> $right['canonical_index'];
        });

        for ($i = 0; $i < $slotsRemaining; $i++) {
            $allocations[$remainders[$i]['outcome']]++;
        }

        $expandedTable = [];
        $slot = 1;

        foreach ($eligibleCanonicalOutcomes as $outcomeCode) {
            for ($count = 0; $count < $allocations[$outcomeCode]; $count++) {
                $expandedTable[$slot] = $outcomeCode;
                $slot++;
            }
        }

        return $expandedTable;
    }

    /**
     * @param array<string, mixed> $profileTable
     */
    private function isValidProfileTable(array $profileTable): bool
    {
        $canonicalOutcomeCodes = $this->outcomeCatalog->getOutcomeCodes();

        if (array_keys($profileTable) !== $canonicalOutcomeCodes) {
            return false;
        }

        $slotCount = 0;

        foreach ($profileTable as $slots) {
            if (!is_int($slots) || $slots < 0) {
                return false;
            }

            $slotCount += $slots;
        }

        return $slotCount === self::SLOT_COUNT;
    }

    /**
     * @return array<string, int>
     */
    private function legacyBalancedProfileTable(): array
    {
        $profileTable = [];

        foreach (self::EMERGENCY_LEGACY_BALANCED_TABLE as $outcomeCode) {
            if (!isset($profileTable[$outcomeCode])) {
                $profileTable[$outcomeCode] = 0;
            }

            $profileTable[$outcomeCode]++;
        }

        $canonicalOutcomeCodes = $this->outcomeCatalog->getOutcomeCodes();
        $canonicalProfileTable = [];

        foreach ($canonicalOutcomeCodes as $outcomeCode) {
            $canonicalProfileTable[$outcomeCode] = $profileTable[$outcomeCode] ?? 0;
        }

        return $canonicalProfileTable;
    }

    private function normalizeSlot(int $rolledSlot): int
    {
        $zeroBased = (($rolledSlot - 1) % self::SLOT_COUNT + self::SLOT_COUNT) % self::SLOT_COUNT;

        return $zeroBased + 1;
    }
}
