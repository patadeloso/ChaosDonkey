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

    public function isActionEnabled(string $actionCode): bool
    {
        return match ($actionCode) {
            'reindex_all' => $this->isReindexAllEnabled(),
            'cache_flush' => $this->isCacheFlushEnabled(),
            'graphql_pipeline_stress' => $this->isGraphQlPipelineStressEnabled(),
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
