<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Console\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Console\Command\ChaosDonkeyStatus;
use ShaunMcManus\ChaosDonkey\Model\Config;
use Symfony\Component\Console\Tester\CommandTester;

class ChaosDonkeyStatusTest extends TestCase
{
    private Config&MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
    }

    public function testItDisplaysCurrentStatusValues(): void
    {
        $this->config
            ->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);
        $this->config
            ->expects(self::once())
            ->method('getLastRun')
            ->willReturn('2026-03-28T20:22:00+00:00');
        $this->config
            ->expects(self::once())
            ->method('getLastKick')
            ->willReturn('2');
        $this->config
            ->expects(self::once())
            ->method('getLastOutcome')
            ->willReturn('reindex_all');

        $command = new ChaosDonkeyStatus($this->config);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('ChaosDonkey Status', $display);
        self::assertStringContainsString('Enabled: Yes', $display);
        self::assertStringContainsString('Last run: 2026-03-28T20:22:00+00:00', $display);
        self::assertStringContainsString('Last kick: 2', $display);
        self::assertStringContainsString('Last outcome: reindex_all', $display);
    }

    public function testItDisplaysUnknownWhenNoSavedStateExists(): void
    {
        $this->config
            ->expects(self::once())
            ->method('isEnabled')
            ->willReturn(false);
        $this->config
            ->expects(self::once())
            ->method('getLastRun')
            ->willReturn(null);
        $this->config
            ->expects(self::once())
            ->method('getLastKick')
            ->willReturn(null);
        $this->config
            ->expects(self::once())
            ->method('getLastOutcome')
            ->willReturn(null);

        $command = new ChaosDonkeyStatus($this->config);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Enabled: No', $display);
        self::assertStringContainsString('Last run: Never', $display);
        self::assertStringContainsString('Last kick: Never', $display);
        self::assertStringContainsString('Last outcome: Never', $display);
    }
}
