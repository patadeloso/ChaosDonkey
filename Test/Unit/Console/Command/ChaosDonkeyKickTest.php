<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Console\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Api\ChaosActionInterface;
use ShaunMcManus\ChaosDonkey\Console\Command\ChaosDonkeyKick;
use ShaunMcManus\ChaosDonkey\Model\ActionPool;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\KickRoller;
use ShaunMcManus\ChaosDonkey\Model\RollOutcomeResolver;
use ShaunMcManus\ChaosDonkey\Model\StateWriter;
use Symfony\Component\Console\Tester\CommandTester;

class ChaosDonkeyKickTest extends TestCase
{
    private Config&MockObject $config;
    private ActionPool&MockObject $actionPool;
    private RollOutcomeResolver&MockObject $resolver;
    private StateWriter&MockObject $stateWriter;
    private KickRoller&MockObject $kickRoller;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->actionPool = $this->createMock(ActionPool::class);
        $this->resolver = $this->createMock(RollOutcomeResolver::class);
        $this->stateWriter = $this->createMock(StateWriter::class);
        $this->kickRoller = $this->createMock(KickRoller::class);
    }

    public function testItExitsEarlyWhenDisabled(): void
    {
        $this->config->expects(self::once())->method('isEnabled')->willReturn(false);

        $this->kickRoller->expects(self::never())->method('rollD20');
        $this->resolver->expects(self::never())->method('resolve');
        $this->actionPool->expects(self::never())->method('get');
        $this->stateWriter->expects(self::never())->method('saveLastRun');
        $this->stateWriter->expects(self::never())->method('saveLastKick');
        $this->stateWriter->expects(self::never())->method('saveLastOutcome');

        $command = new ChaosDonkeyKick($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('ChaosDonkey is disabled', $tester->getDisplay());
    }

    public function testRollOneSavesStateAndPrintsCriticalFailure(): void
    {
        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(1);
        $this->resolver->expects(self::once())->method('resolve')->with(1)->willReturn('critical_failure');
        $this->actionPool->expects(self::once())->method('get')->with('critical_failure')->willReturn(null);

        $this->stateWriter
            ->expects(self::once())
            ->method('saveLastRun')
            ->with(self::callback(static function (string $timestamp): bool {
                $parsed = \DateTimeImmutable::createFromFormat(DATE_ATOM, $timestamp);

                return $parsed !== false && $parsed->format(DATE_ATOM) === $timestamp;
            }));
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(1);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('critical_failure');

        $command = new ChaosDonkeyKick($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('You rolled a 1', $tester->getDisplay());
        self::assertStringContainsString('Critical Failure!', $tester->getDisplay());
    }

    public function testRollThreeTriggersMappedAction(): void
    {
        $action = $this->createStub(ChaosActionInterface::class);
        $action->method('execute')->willReturn(new ChaosActionResult('cache_flush', 'ok'));

        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->config->method('isActionEnabled')->willReturn(true);
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(3);
        $this->resolver->expects(self::once())->method('resolve')->with(3)->willReturn('cache_flush');
        $this->actionPool->expects(self::once())->method('get')->with('cache_flush')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(3);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('cache_flush');

        $command = new ChaosDonkeyKick($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('You rolled a 3', $tester->getDisplay());
    }

    public function testRollFourTriggersMappedAction(): void
    {
        $action = $this->createStub(ChaosActionInterface::class);
        $action->method('execute')->willReturn(new ChaosActionResult('graphql_pipeline_stress', 'ok'));

        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->config->method('isActionEnabled')->willReturn(true);
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(4);
        $this->resolver->expects(self::once())->method('resolve')->with(4)->willReturn('graphql_pipeline_stress');
        $this->actionPool->expects(self::once())->method('get')->with('graphql_pipeline_stress')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(4);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('graphql_pipeline_stress');

        $command = new ChaosDonkeyKick($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('You rolled a 4', $tester->getDisplay());
    }

    public function testRollTwentySavesStateAndPrintsCriticalSuccess(): void
    {
        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(20);
        $this->resolver->expects(self::once())->method('resolve')->with(20)->willReturn('critical_success');
        $this->actionPool->expects(self::once())->method('get')->with('critical_success')->willReturn(null);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(20);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('critical_success');

        $command = new ChaosDonkeyKick($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('You rolled a 20', $tester->getDisplay());
        self::assertStringContainsString('Critical Success!', $tester->getDisplay());
    }

    public function testDefaultRollSavesStateAndPrintsNappingMessage(): void
    {
        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(6);
        $this->resolver->expects(self::once())->method('resolve')->with(6)->willReturn('napping');
        $this->actionPool->expects(self::once())->method('get')->with('napping')->willReturn(null);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(6);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('napping');

        $command = new ChaosDonkeyKick($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('You rolled a 6', $tester->getDisplay());
        self::assertStringContainsString('The donkeys are napping', $tester->getDisplay());
    }

    public function testDisabledActionOutcomeTriggersRerollAndPersistsFinalOutcome(): void
    {
        $action = $this->createStub(ChaosActionInterface::class);
        $action->method('execute')->willReturn(new ChaosActionResult('cache_flush', 'ok'));

        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->config->expects(self::exactly(3))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', false],
                ['cache_flush', true],
                ['graphql_pipeline_stress', true],
            ]);
        $this->kickRoller->expects(self::exactly(2))
            ->method('rollD20')
            ->willReturnOnConsecutiveCalls(2, 3);
        $resolvedRolls = [];
        $this->resolver->expects(self::exactly(2))
            ->method('resolve')
            ->willReturnCallback(static function (int $kick) use (&$resolvedRolls): string {
                $resolvedRolls[] = $kick;

                return match ($kick) {
                    2 => 'reindex_all',
                    3 => 'cache_flush',
                };
            });
        $this->actionPool->expects(self::once())->method('get')->with('cache_flush')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(3);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('cache_flush');

        $command = new ChaosDonkeyKick($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertSame([2, 3], $resolvedRolls);
        self::assertStringContainsString('You rolled a 3', $tester->getDisplay());
        self::assertStringNotContainsString('You rolled a 2', $tester->getDisplay());
    }

    public function testAllActionsDisabledWarnsOnceAndExecutesOnlyNonActionOutcome(): void
    {
        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->config->expects(self::exactly(3))
            ->method('isActionEnabled')
            ->willReturn(false);
        $this->kickRoller->expects(self::exactly(4))
            ->method('rollD20')
            ->willReturnOnConsecutiveCalls(2, 3, 4, 1);
        $resolvedRolls = [];
        $this->resolver->expects(self::exactly(4))
            ->method('resolve')
            ->willReturnCallback(static function (int $kick) use (&$resolvedRolls): string {
                $resolvedRolls[] = $kick;

                return match ($kick) {
                    1 => 'critical_failure',
                    2 => 'reindex_all',
                    3 => 'cache_flush',
                    4 => 'graphql_pipeline_stress',
                };
            });
        $this->actionPool->expects(self::once())->method('get')->with('critical_failure')->willReturn(null);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(1);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('critical_failure');

        $command = new ChaosDonkeyKick($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertSame([2, 3, 4, 1], $resolvedRolls);
        self::assertSame(
            1,
            substr_count($tester->getDisplay(), 'All configured chaos actions are disabled. Rolling non-action outcomes only.')
        );
        self::assertStringContainsString('You rolled a 1', $tester->getDisplay());
        self::assertStringContainsString('Critical Failure!', $tester->getDisplay());
    }

    public function testMaxAttemptGuardFallsBackToNapping(): void
    {
        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->config->expects(self::exactly(3))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', false],
                ['cache_flush', true],
                ['graphql_pipeline_stress', true],
            ]);
        $this->kickRoller->expects(self::exactly(20))->method('rollD20')->willReturn(2);
        $this->resolver->expects(self::exactly(20))->method('resolve')->with(2)->willReturn('reindex_all');
        $this->actionPool->expects(self::once())->method('get')->with('napping')->willReturn(null);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(2);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('napping');

        $command = new ChaosDonkeyKick($this->config, $this->actionPool, $this->resolver, $this->stateWriter, $this->kickRoller);
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertStringContainsString('Max reroll attempts reached. Falling back to napping.', $tester->getDisplay());
        self::assertStringContainsString('You rolled a 2', $tester->getDisplay());
        self::assertStringContainsString('The donkeys are napping', $tester->getDisplay());
    }
}
