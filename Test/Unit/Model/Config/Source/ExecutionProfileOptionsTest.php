<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model\Config\Source;

use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Model\Config\Source\ExecutionProfileOptions;
use ShaunMcManus\ChaosDonkey\Model\Profile\ExecutionProfileCatalog;

class ExecutionProfileOptionsTest extends TestCase
{
    public function testItReturnsExactStableExecutionProfileOptions(): void
    {
        $source = new ExecutionProfileOptions();

        self::assertSame(
            [
                ['value' => 'balanced', 'label' => 'Balanced'],
                ['value' => 'chaos', 'label' => 'Chaos'],
                ['value' => 'all_gas_no_brakes', 'label' => 'All Gas No Brakes'],
            ],
            $source->toOptionArray()
        );
    }

    public function testItDerivesOptionsFromExecutionProfileCatalogMetadata(): void
    {
        $catalog = new ExecutionProfileCatalog();
        $source = new ExecutionProfileOptions($catalog);

        $expected = [];
        foreach ($catalog->getProfileLabels() as $profileCode => $label) {
            $expected[] = ['value' => $profileCode, 'label' => $label];
        }

        self::assertSame($expected, $source->toOptionArray());
    }
}
