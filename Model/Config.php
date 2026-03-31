<?php
declare(strict_types = 1);

namespace ShaunMcManus\ChaosDonkey\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const CONFIG_PATH_ENABLED = 'admin/chaos_donkey/enabled';
    public const CONFIG_PATH_ENABLE_REINDEX_ALL = 'admin/chaos_donkey/enable_reindex_all';
    public const CONFIG_PATH_ENABLE_CACHE_FLUSH = 'admin/chaos_donkey/enable_cache_flush';
    public const CONFIG_PATH_ENABLE_GRAPHQL_PIPELINE_STRESS = 'admin/chaos_donkey/enable_graphql_pipeline_stress';
    public const CONFIG_PATH_ENABLE_INDEXER_STATUS_SNAPSHOT = 'admin/chaos_donkey/enable_indexer_status_snapshot';
    public const CONFIG_PATH_ENABLE_CACHE_BACKEND_HEALTH_SNAPSHOT = 'admin/chaos_donkey/enable_cache_backend_health_snapshot';
    public const CONFIG_PATH_ENABLE_CRON_QUEUE_HEALTH_SNAPSHOT = 'admin/chaos_donkey/enable_cron_queue_health_snapshot';
    public const CONFIG_PATH_CRON_ENABLED = 'admin/chaos_donkey/cron_enabled';
    public const CONFIG_PATH_CRON_EXPRESSION = 'admin/chaos_donkey/cron_expression';
    public const CONFIG_PATH_CRON_ALLOWED_HOURS = 'admin/chaos_donkey/cron_allowed_hours';
    public const CONFIG_PATH_LAST_RUN = 'admin/chaos_donkey/last_run';
    public const CONFIG_PATH_LAST_KICK = 'admin/chaos_donkey/last_kick';
    public const CONFIG_PATH_LAST_OUTCOME = 'admin/chaos_donkey/last_outcome';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnabled(string $scopeType = ScopeInterface::SCOPE_STORE, ?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_ENABLED, $scopeType, $scopeCode);
    }

    public function isReindexAllEnabled(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_ENABLE_REINDEX_ALL, $scopeType, $scopeCode);
    }

    public function isCacheFlushEnabled(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_ENABLE_CACHE_FLUSH, $scopeType, $scopeCode);
    }

    public function isGraphQlPipelineStressEnabled(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_ENABLE_GRAPHQL_PIPELINE_STRESS, $scopeType, $scopeCode);
    }

    public function isIndexerStatusSnapshotEnabled(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_ENABLE_INDEXER_STATUS_SNAPSHOT, $scopeType, $scopeCode);
    }

    public function isCacheBackendHealthSnapshotEnabled(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_ENABLE_CACHE_BACKEND_HEALTH_SNAPSHOT, $scopeType, $scopeCode);
    }

    public function isCronQueueHealthSnapshotEnabled(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_ENABLE_CRON_QUEUE_HEALTH_SNAPSHOT, $scopeType, $scopeCode);
    }

    public function isCronEnabled(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_CRON_ENABLED, $scopeType, $scopeCode);
    }

    public function getCronExpression(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): ?string
    {
        $value = $this->scopeConfig->getValue(self::CONFIG_PATH_CRON_EXPRESSION, $scopeType, $scopeCode);

        if ($value === null) {
            return null;
        }

        $normalizedValue = trim((string) $value);

        return $normalizedValue === '' ? null : $normalizedValue;
    }

    public function getCronAllowedHoursRaw(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): ?string
    {
        $value = $this->scopeConfig->getValue(self::CONFIG_PATH_CRON_ALLOWED_HOURS, $scopeType, $scopeCode);

        if ($value === null) {
            return null;
        }

        $normalizedValue = trim((string) $value);

        return $normalizedValue === '' ? null : $normalizedValue;
    }

    /**
     * @return array<int>
     */
    public function getCronAllowedHours(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): array
    {
        $rawValue = $this->getCronAllowedHoursRaw($scopeType, $scopeCode);
        if ($rawValue === null) {
            return [];
        }

        $allowedHours = [];

        foreach (explode(',', $rawValue) as $token) {
            $normalizedToken = trim($token);

            if ($normalizedToken === '' || !ctype_digit($normalizedToken)) {
                continue;
            }

            $hour = (int) $normalizedToken;
            if ($hour < 0 || $hour > 23) {
                continue;
            }

            $allowedHours[] = $hour;
        }

        $allowedHours = array_values(array_unique($allowedHours));
        sort($allowedHours);

        return $allowedHours;
    }

    public function isActionEnabled(string $actionCode): bool
    {
        return match ($actionCode) {
            'reindex_all' => $this->isReindexAllEnabled(),
            'cache_flush' => $this->isCacheFlushEnabled(),
            'graphql_pipeline_stress' => $this->isGraphQlPipelineStressEnabled(),
            'indexer_status_snapshot' => $this->isIndexerStatusSnapshotEnabled(),
            'cache_backend_health_snapshot' => $this->isCacheBackendHealthSnapshotEnabled(),
            'cron_queue_health_snapshot' => $this->isCronQueueHealthSnapshotEnabled(),
            default => true,
        };
    }

    public function getLastRun(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): ?string
    {
        $value = $this->scopeConfig->getValue(self::CONFIG_PATH_LAST_RUN, $scopeType, $scopeCode);

        if ($value === null) {
            return null;
        }

        $normalizedValue = trim((string) $value);

        return $normalizedValue === '' ? null : $normalizedValue;
    }

    public function getLastKick(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): ?string
    {
        $value = $this->scopeConfig->getValue(self::CONFIG_PATH_LAST_KICK, $scopeType, $scopeCode);

        if ($value === null) {
            return null;
        }

        $normalizedValue = trim((string) $value);

        return $normalizedValue === '' ? null : $normalizedValue;
    }

    public function getLastOutcome(string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, ?string $scopeCode = null): ?string
    {
        $value = $this->scopeConfig->getValue(self::CONFIG_PATH_LAST_OUTCOME, $scopeType, $scopeCode);

        if ($value === null) {
            return null;
        }

        $normalizedValue = trim((string) $value);

        return $normalizedValue === '' ? null : $normalizedValue;
    }
}
