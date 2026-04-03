<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use ShaunMcManus\ChaosDonkey\Model\Profile\ExecutionProfileCatalog;

final class ExecutionProfileOptions implements OptionSourceInterface
{
    private ExecutionProfileCatalog $executionProfileCatalog;

    public function __construct(?ExecutionProfileCatalog $executionProfileCatalog = null)
    {
        $this->executionProfileCatalog = $executionProfileCatalog ?? new ExecutionProfileCatalog();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];

        foreach ($this->executionProfileCatalog->getProfileLabels() as $profileCode => $label) {
            $options[] = [
                'value' => $profileCode,
                'label' => $label,
            ];
        }

        return $options;
    }
}
