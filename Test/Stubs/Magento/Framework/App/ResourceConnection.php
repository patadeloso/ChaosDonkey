<?php
declare(strict_types=1);

namespace Magento\Framework\App;

class ResourceConnection
{
    public function getTableName(string $entityTable): string
    {
        return $entityTable;
    }

    public function getConnection(): mixed
    {
        return null;
    }
}
