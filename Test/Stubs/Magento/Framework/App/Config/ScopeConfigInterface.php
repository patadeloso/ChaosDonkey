<?php
declare(strict_types=1);

namespace Magento\Framework\App\Config;

interface ScopeConfigInterface
{
    public const SCOPE_TYPE_DEFAULT = 'default';

    public function getValue(
        string $path,
        ?string $scopeType = null,
        ?string $scopeCode = null
    );

    public function isSetFlag(
        string $path,
        ?string $scopeType = null,
        ?string $scopeCode = null
    ): bool;
}
