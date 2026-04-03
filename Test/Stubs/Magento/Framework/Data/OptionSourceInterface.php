<?php
declare(strict_types=1);

namespace Magento\Framework\Data;

interface OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array;
}
