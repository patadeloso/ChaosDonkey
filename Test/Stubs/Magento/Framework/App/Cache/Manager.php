<?php
declare(strict_types=1);

namespace Magento\Framework\App\Cache;

class Manager
{
    /**
     * @return array<string, mixed>
     */
    public function getAvailableTypes(): array
    {
        return [];
    }

    /**
     * @param list<string> $types
     */
    public function clean(array $types): void
    {
    }
}
