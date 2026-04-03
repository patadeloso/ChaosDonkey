<?php
declare(strict_types=1);

namespace Magento\Framework\DB\Adapter;

interface AdapterInterface
{
    public function isTableExists(string $tableName): bool;

    /**
     * @param array<string, mixed> $bind
     */
    public function insert(string $tableName, array $bind): void;

    /**
     * @param array<string, mixed> $bind
     */
    public function fetchOne(string $sql, array $bind = []): mixed;

    /**
     * @param array<string, mixed> $bind
     */
    public function fetchRow(string $sql, array $bind = []): array|false;

    public function fetchAll(string $sql, array $bind = []): array;
}
