<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Model\Config;

class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
    }

    public function testItReadsEnabledFlagFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('isSetFlag')
            ->with(Config::CONFIG_PATH_ENABLED, 'store', null)
            ->willReturn(true);

        $config = new Config($this->scopeConfig);

        self::assertTrue($config->isEnabled());
    }

    public function testItReadsLastRunFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CONFIG_PATH_LAST_RUN, 'default', null)
            ->willReturn('2026-03-28 12:00:00');

        $config = new Config($this->scopeConfig);

        self::assertSame('2026-03-28 12:00:00', $config->getLastRun());
    }

    public function testItReadsLastKickFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CONFIG_PATH_LAST_KICK, 'default', null)
            ->willReturn('2');

        $config = new Config($this->scopeConfig);

        self::assertSame('2', $config->getLastKick());
    }

    public function testItReadsLastOutcomeFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CONFIG_PATH_LAST_OUTCOME, 'default', null)
            ->willReturn('reindex');

        $config = new Config($this->scopeConfig);

        self::assertSame('reindex', $config->getLastOutcome());
    }

    public function testItTreatsEmptyStateValuesAsUnset(): void
    {
        $this->scopeConfig
            ->expects(self::exactly(3))
            ->method('getValue')
            ->willReturnOnConsecutiveCalls('', '   ', '');

        $config = new Config($this->scopeConfig);

        self::assertNull($config->getLastRun());
        self::assertNull($config->getLastKick());
        self::assertNull($config->getLastOutcome());
    }
}
