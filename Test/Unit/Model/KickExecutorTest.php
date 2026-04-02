<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Api\ChaosActionInterface;
use ShaunMcManus\ChaosDonkey\Model\ActionPool;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\KickExecutor;
use ShaunMcManus\ChaosDonkey\Model\KickRoller;
use ShaunMcManus\ChaosDonkey\Model\Profile\ProfiledRollSelector;
use ShaunMcManus\ChaosDonkey\Model\StateWriter;
use Symfony\Component\Console\Output\BufferedOutput;

class KickExecutorTest extends TestCase
{
    private Config&MockObject $config;
    private ActionPool&MockObject $actionPool;
    private ProfiledRollSelector $profiledRollSelector;
    private StateWriter&MockObject $stateWriter;
    private KickRoller&MockObject $kickRoller;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->actionPool = $this->createMock(ActionPool::class);
        $this->profiledRollSelector = new ProfiledRollSelector();
        $this->stateWriter = $this->createMock(StateWriter::class);
        $this->kickRoller = $this->createMock(KickRoller::class);
    }

    public function testItExecutesSelectedActionAndCapturesActionOutput(): void
    {
        $action = $this->createMock(ChaosActionInterface::class);
        $action->expects(self::once())
            ->method('execute')
            ->willReturnCallback(static function (BufferedOutput $output): ChaosActionResult {
                $output->writeln('Cache flush started');

                return new ChaosActionResult('cache_flush', 'Cache flush completed');
            });

        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', true],
                ['cache_flush', true],
                ['graphql_pipeline_stress', true],
                ['indexer_status_snapshot', true],
                ['cache_backend_health_snapshot', true],
                ['cron_queue_health_snapshot', true],
            ]);
        $this->config->expects(self::once())->method('getExecutionProfile')->willReturn('balanced');
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(3);
        $this->actionPool->expects(self::once())
            ->method('get')
            ->with(self::callback(static function (string $selectedOutcome): bool {
                return $selectedOutcome !== '';
            }))
            ->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun')
            ->with(self::callback(static function (string $timestamp): bool {
                $parsed = \DateTimeImmutable::createFromFormat(DATE_ATOM, $timestamp);

                return $parsed !== false && $parsed->format(DATE_ATOM) === $timestamp;
            }));
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(3);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with(self::isType('string'));

        $executor = new KickExecutor($this->config, $this->actionPool, $this->profiledRollSelector, $this->stateWriter, $this->kickRoller);
        $result = $executor->execute();

        self::assertSame(3, $result['kick']);
        self::assertIsString($result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 3',
            'Cache flush started',
            'Cache flush completed',
        ], $result['messages']);
    }

    public function testItExcludesDisabledOutcomesBeforeSelectionAndExecutesSelectedAction(): void
    {
        $action = $this->createMock(ChaosActionInterface::class);
        $action->expects(self::once())
            ->method('execute')
            ->willReturnCallback(static function (BufferedOutput $output): ChaosActionResult {
                $output->writeln('GraphQL stress started');

                return new ChaosActionResult('graphql_pipeline_stress', 'GraphQL stress completed');
            });

        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', true],
                ['cache_flush', false],
                ['graphql_pipeline_stress', true],
                ['indexer_status_snapshot', true],
                ['cache_backend_health_snapshot', true],
                ['cron_queue_health_snapshot', true],
            ]);
        $this->config->expects(self::once())->method('getExecutionProfile')->willReturn('balanced');
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(3);
        $this->actionPool->expects(self::once())->method('get')->with('graphql_pipeline_stress')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(self::callback(static function (int $kick): bool {
            return $kick === 3 && $kick >= 1 && $kick <= 20;
        }));
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('graphql_pipeline_stress');

        $executor = new KickExecutor($this->config, $this->actionPool, $this->profiledRollSelector, $this->stateWriter, $this->kickRoller);
        $result = $executor->execute();

        self::assertSame(3, $result['kick']);
        self::assertSame('graphql_pipeline_stress', $result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 3',
            'GraphQL stress started',
            'GraphQL stress completed',
        ], $result['messages']);
    }

    public function testItWarnsOnceWhenAllActionsDisabledAndExecutesNonActionOutcome(): void
    {
        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturn(false);
        $this->config->expects(self::once())->method('getExecutionProfile')->willReturn('balanced');
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(2);
        $this->actionPool->expects(self::once())->method('get')->with('critical_failure')->willReturn(null);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(self::callback(static function (int $kick): bool {
            return $kick === 2 && $kick >= 1 && $kick <= 20;
        }));
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('critical_failure');

        $executor = new KickExecutor($this->config, $this->actionPool, $this->profiledRollSelector, $this->stateWriter, $this->kickRoller);
        $result = $executor->execute();

        self::assertSame(2, $result['kick']);
        self::assertSame('critical_failure', $result['outcome']);
        self::assertSame([
            'All configured chaos actions/probes are disabled. Rolling non-action outcomes only.',
            'ChaosDonkeyKick kicks your Magento. You rolled a 2',
            'Critical Failure! Better check all of your donkeys.',
        ], $result['messages']);
    }

    public function testItUsesConfiguredExecutionProfileInsteadOfLegacyStaticResolverMapping(): void
    {
        $action = $this->createMock(ChaosActionInterface::class);
        $action->expects(self::once())
            ->method('execute')
            ->willReturn(new ChaosActionResult('cache_flush', 'Cache flush completed'));

        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', true],
                ['cache_flush', true],
                ['graphql_pipeline_stress', true],
                ['indexer_status_snapshot', true],
                ['cache_backend_health_snapshot', true],
                ['cron_queue_health_snapshot', true],
            ]);
        $this->config->expects(self::once())->method('getExecutionProfile')->willReturn('all_gas_no_brakes');
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(8);
        $this->actionPool->expects(self::once())->method('get')->with('cache_flush')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(self::callback(static function (int $kick): bool {
            return $kick === 8 && $kick >= 1 && $kick <= 20;
        }));
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('cache_flush');

        $executor = new KickExecutor($this->config, $this->actionPool, $this->profiledRollSelector, $this->stateWriter, $this->kickRoller);
        $result = $executor->execute();

        self::assertSame(8, $result['kick']);
        self::assertSame('cache_flush', $result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 8',
            'Cache flush completed',
        ], $result['messages']);
    }

    public function testItOmitsEmptyActionSummaryWithoutOutcomeBranching(): void
    {
        $action = $this->createStub(ChaosActionInterface::class);
        $action
            ->method('execute')
            ->willReturnCallback(static function (BufferedOutput $output): ChaosActionResult {
                $output->writeln('Probe output');

                return new ChaosActionResult('cache_backend_health_snapshot', '');
            });

        $this->config->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnMap([
                ['reindex_all', true],
                ['cache_flush', false],
                ['graphql_pipeline_stress', false],
                ['indexer_status_snapshot', false],
                ['cache_backend_health_snapshot', true],
                ['cron_queue_health_snapshot', false],
            ]);
        $this->config->expects(self::once())->method('getExecutionProfile')->willReturn('balanced');
        $this->kickRoller->expects(self::once())->method('rollD20')->willReturn(4);
        $this->actionPool->expects(self::once())->method('get')->with('cache_backend_health_snapshot')->willReturn($action);

        $this->stateWriter->expects(self::once())->method('saveLastRun');
        $this->stateWriter->expects(self::once())->method('saveLastKick')->with(4);
        $this->stateWriter->expects(self::once())->method('saveLastOutcome')->with('cache_backend_health_snapshot');

        $executor = new KickExecutor($this->config, $this->actionPool, $this->profiledRollSelector, $this->stateWriter, $this->kickRoller);
        $result = $executor->execute();

        self::assertSame(4, $result['kick']);
        self::assertSame('cache_backend_health_snapshot', $result['outcome']);
        self::assertSame([
            'ChaosDonkeyKick kicks your Magento. You rolled a 4',
            'Probe output',
        ], $result['messages']);
    }
}
