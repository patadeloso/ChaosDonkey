<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Console\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Console\Command\ChaosDonkeyKick;
use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\KickExecutor;
use Symfony\Component\Console\Tester\CommandTester;

class ChaosDonkeyKickTest extends TestCase
{
    private Config&MockObject $config;
    private KickExecutor&MockObject $kickExecutor;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->kickExecutor = $this->createMock(KickExecutor::class);
    }

    public function testItExitsEarlyWhenDisabled(): void
    {
        $this->config->expects(self::once())->method('isEnabled')->willReturn(false);
        $this->kickExecutor->expects(self::never())->method('execute');

        $command = new ChaosDonkeyKick($this->config, $this->kickExecutor);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('ChaosDonkey is disabled', $tester->getDisplay());
    }

    public function testItDelegatesEnabledExecutionAndPrintsReturnedMessages(): void
    {
        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->kickExecutor
            ->expects(self::once())
            ->method('execute')
            ->willReturn([
                'kick' => 3,
                'outcome' => 'cache_flush',
                'messages' => [
                    'ChaosDonkeyKick kicks your Magento. You rolled a 3',
                    'Cache flush started',
                    'Cache flush completed',
                ],
            ]);

        $command = new ChaosDonkeyKick($this->config, $this->kickExecutor);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('ChaosDonkeyKick kicks your Magento. You rolled a 3', $tester->getDisplay());
        self::assertStringContainsString('Cache flush started', $tester->getDisplay());
        self::assertStringContainsString('Cache flush completed', $tester->getDisplay());
    }
}
