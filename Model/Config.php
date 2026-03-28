<?php
declare(strict_types = 1);

namespace ShaunMcManus\ChaosDonkey\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const CONFIG_PATH_ENABLED = 'admin/chaos_donkey/enabled';
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
