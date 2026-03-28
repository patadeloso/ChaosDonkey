<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Console\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Action\ReindexAll;
use ShaunMcManus\ChaosDonkey\Console\Command\ChaosDonkeyKick;
use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\KickRoller;
use ShaunMcManus\ChaosDonkey\Model\StateWriter;
use Symfony\Component\Console\Tester\CommandTester;

class ChaosDonkeyKickTest extends TestCase
{
    private Config&MockObject $config;
    private ReindexAll&MockObject $reindexAll;
    private StateWriter&MockObject $stateWriter;
    private KickRoller&MockObject $kickRoller;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->reindexAll = $this->createMock(ReindexAll::class);
        $this->stateWriter = $this->createMock(StateWriter::class);
        $this->kickRoller = $this->createMock(KickRoller::class);
    }

    public function testItExitsEarlyWhenDisabled(): void
    {
        $this->config
            ->expects(self::once())
            ->method('isEnabled')
            ->willReturn(false);

        $this->kickRoller->expects(self::never())->method('rollD20');
        $this->reindexAll->expects(self::never())->method('execute');
        $this->stateWriter->expects(self::never())->method('saveLastRun');
        $this->stateWriter->expects(self::never())->method('saveLastKick');
        $this->stateWriter->expects(self::never())->method('saveLastOutcome');

        $command = new ChaosDonkeyKick($this->config, $this->reindexAll, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('ChaosDonkey is disabled', $tester->getDisplay());
    }

    public function testRollOneSavesStateAndPrintsCriticalFailure(): void
    {
        $this->config
            ->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);
        $this->kickRoller
            ->expects(self::once())
            ->method('rollD20')
            ->willReturn(1);

        $this->reindexAll->expects(self::never())->method('execute');
        $this->stateWriter
            ->expects(self::once())
            ->method('saveLastRun')
            ->with(self::callback(static function (string $timestamp): bool {
                $parsed = \DateTimeImmutable::createFromFormat(DATE_ATOM, $timestamp);
                if ($parsed === false) {
                    return false;
                }

                return $parsed->format(DATE_ATOM) === $timestamp;
            }));
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(1);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('critical_failure');

        $command = new ChaosDonkeyKick($this->config, $this->reindexAll, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('You rolled a 1', $tester->getDisplay());
        self::assertStringContainsString('Critical Failure!', $tester->getDisplay());
    }

    public function testRollTwoTriggersReindexAndPersistsOutcome(): void
    {
        $this->config
            ->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);
        $this->kickRoller
            ->expects(self::once())
            ->method('rollD20')
            ->willReturn(2);

        $this->reindexAll->expects(self::once())->method('execute');
        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(2);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('reindex_all');

        $command = new ChaosDonkeyKick($this->config, $this->reindexAll, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('You rolled a 2', $tester->getDisplay());
    }

    public function testRollTwentySavesStateAndPrintsCriticalSuccess(): void
    {
        $this->config
            ->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);
        $this->kickRoller
            ->expects(self::once())
            ->method('rollD20')
            ->willReturn(20);

        $this->reindexAll->expects(self::never())->method('execute');
        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(20);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('critical_success');

        $command = new ChaosDonkeyKick($this->config, $this->reindexAll, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('You rolled a 20', $tester->getDisplay());
        self::assertStringContainsString('Critical Success!', $tester->getDisplay());
    }

    public function testDefaultRollSavesStateAndPrintsNappingMessage(): void
    {
        $this->config
            ->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);
        $this->kickRoller
            ->expects(self::once())
            ->method('rollD20')
            ->willReturn(6);

        $this->reindexAll->expects(self::never())->method('execute');
        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(6);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('napping');

        $command = new ChaosDonkeyKick($this->config, $this->reindexAll, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('You rolled a 6', $tester->getDisplay());
        self::assertStringContainsString('The donkeys are napping', $tester->getDisplay());
    }
}
