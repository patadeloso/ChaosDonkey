<?php
declare(strict_types=1);

namespace Magento\Framework\DB\Adapter;

interface AdapterInterface
{
    public function isTableExists(string $tableName): bool;

    /**
     * @param array<string, mixed> $bind
     */
    public function fetchOne(string $sql, array $bind = []): mixed;
}
