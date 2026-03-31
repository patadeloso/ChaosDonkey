<?php
declare(strict_types=1);

namespace Magento\Framework\App\Cache;

interface TypeListInterface
{
    public function getTypes(): array;
}
