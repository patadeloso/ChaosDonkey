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

        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Enabled: No', $display);
        self::assertStringContainsString('Last run: Never', $display);
        self::assertStringContainsString('Last kick: Never', $display);
        self::assertStringContainsString('Last outcome: Never', $display);

    }

    public function testItDisplaysCurrentStatusWithActionAndProbeToggles(): void
    {
        $actionStates = [
            'reindex_all' => true,
            'cache_flush' => false,
            'graphql_pipeline_stress' => true,
            'indexer_status_snapshot' => true,
            'cache_backend_health_snapshot' => false,
            'cron_queue_health_snapshot' => true,
        ];

        $requestedActionCodes = [];

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
        $this->config
            ->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnCallback(function (string $code) use (&$requestedActionCodes, $actionStates): bool {
                $requestedActionCodes[] = $code;

                return $actionStates[$code];
            });

        $command = new ChaosDonkeyStatus($this->config);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);
        $display = trim($tester->getDisplay());

        $expectedOutput = <<<OUTPUT
ChaosDonkey Status
Enabled: Yes
Last run: 2026-03-28T20:22:00+00:00
Last kick: 2
Last outcome: reindex_all

Configured Action/Probe Toggles
Reindex all: Enabled
Cache flush: Disabled
GraphQL pipeline stress: Enabled
Indexer status snapshot: Enabled
Cache backend health snapshot: Disabled
Cron queue health snapshot: Enabled
OUTPUT;

        self::assertSame(0, $exitCode);
        self::assertSame($expectedOutput, $display);
        self::assertSame(
            [
                'reindex_all',
                'cache_flush',
                'graphql_pipeline_stress',
                'indexer_status_snapshot',
                'cache_backend_health_snapshot',
                'cron_queue_health_snapshot',
            ],
            $requestedActionCodes
        );
        self::assertStringNotContainsString('critical_failure', $display);
        self::assertStringNotContainsString('critical_success', $display);
        self::assertStringNotContainsString('napping', $display);
    }

    public function testItDisplaysDisabledModuleAndMixedToggles(): void
    {
        $actionStates = [
            'reindex_all' => false,
            'cache_flush' => false,
            'graphql_pipeline_stress' => true,
            'indexer_status_snapshot' => true,
            'cache_backend_health_snapshot' => false,
            'cron_queue_health_snapshot' => false,
        ];

        $requestedActionCodes = [];

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
        $this->config
            ->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnCallback(function (string $code) use (&$requestedActionCodes, $actionStates): bool {
                $requestedActionCodes[] = $code;

                return $actionStates[$code];
            });

        $command = new ChaosDonkeyStatus($this->config);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Enabled: No', $display);
        self::assertStringContainsString('Last run: Never', $display);
        self::assertStringContainsString('Last kick: Never', $display);
        self::assertStringContainsString('Last outcome: Never', $display);
        self::assertStringContainsString('Configured Action/Probe Toggles', $display);
        self::assertStringContainsString('Reindex all: Disabled', $display);
        self::assertStringContainsString('GraphQL pipeline stress: Enabled', $display);
        self::assertStringContainsString('Indexer status snapshot: Enabled', $display);
        self::assertSame(
            [
                'reindex_all',
                'cache_flush',
                'graphql_pipeline_stress',
                'indexer_status_snapshot',
                'cache_backend_health_snapshot',
                'cron_queue_health_snapshot',
            ],
            $requestedActionCodes
        );
        self::assertStringNotContainsString('critical_failure', $display);
        self::assertStringNotContainsString('critical_success', $display);
        self::assertStringNotContainsString('napping', $display);
    }

    public function testItShowsAllDisabledTogglesAsDisabled(): void
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
        $this->config
            ->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturn(false);

        $command = new ChaosDonkeyStatus($this->config);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $display = trim($tester->getDisplay());

        $expectedSection = <<<OUTPUT
Configured Action/Probe Toggles
Reindex all: Disabled
Cache flush: Disabled
GraphQL pipeline stress: Disabled
Indexer status snapshot: Disabled
Cache backend health snapshot: Disabled
Cron queue health snapshot: Disabled
OUTPUT;

        self::assertStringContainsString($expectedSection, $display);
    }
}
