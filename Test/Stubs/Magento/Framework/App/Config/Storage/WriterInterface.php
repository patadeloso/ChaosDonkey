<?php
declare(strict_types=1);

namespace Magento\Framework\App\Config\Storage;

interface WriterInterface
{
    public function save(
        string $path,
        string $value,
        ?string $scope = null,
        int $scopeId = 0
    ): void;
}
