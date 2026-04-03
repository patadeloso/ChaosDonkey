<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Console\Command;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShaunMcManus\ChaosDonkey\Console\Command\ChaosDonkeyStatus;
use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\ExecutionHistoryStorage;
use Symfony\Component\Console\Tester\CommandTester;

class ChaosDonkeyStatusTest extends TestCase
{
    private Config&MockObject $config;

    private ExecutionHistoryStorage&MockObject $executionHistoryStorage;

    protected function setUp(): void
    {
        $this->executionHistoryStorage = $this->createMock(ExecutionHistoryStorage::class);
    }

    public function testItDisplaysCurrentStatusValues(): void
    {
        $this->config = $this->createMock(Config::class);
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
            ->expects(self::once())
            ->method('getExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getEffectiveExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getExecutionProfileFallbackReason')
            ->willReturn(null);
        $this->config
            ->expects(self::once())
            ->method('isCronEnabled')
            ->willReturn(false);
        $this->expectHistoryReads([], null, null);

        $tester = $this->createCommandTester();

        $exitCode = $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('ChaosDonkey Status', $display);
        self::assertStringContainsString('Enabled: Yes', $display);
        self::assertStringContainsString('Last run: 2026-03-28T20:22:00+00:00', $display);
        self::assertStringContainsString('Last kick: 2', $display);
        self::assertStringContainsString('Last outcome: reindex_all', $display);
        self::assertStringContainsString('Configured profile: balanced', $display);
        self::assertStringContainsString('Effective profile: balanced', $display);
        self::assertStringContainsString('Last CLI execution: Never recorded.', $display);
        self::assertStringContainsString('Last cron execution: Never recorded.', $display);
        self::assertStringContainsString('Recent execution history', $display);
        self::assertStringContainsString('None recorded.', $display);
    }

    public function testItDisplaysUnknownWhenNoSavedStateExists(): void
    {
        $this->config = $this->createMock(Config::class);
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
            ->expects(self::once())
            ->method('getExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getEffectiveExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getExecutionProfileFallbackReason')
            ->willReturn(null);
        $this->config
            ->expects(self::once())
            ->method('isCronEnabled')
            ->willReturn(false);
        $this->expectHistoryReads([], null, null);

        $tester = $this->createCommandTester();

        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Enabled: No', $display);
        self::assertStringContainsString('Last run: Never', $display);
        self::assertStringContainsString('Last kick: Never', $display);
        self::assertStringContainsString('Last outcome: Never', $display);
        self::assertStringContainsString('Configured profile: balanced', $display);
        self::assertStringContainsString('Effective profile: balanced', $display);
        self::assertStringContainsString('Last CLI execution: Never recorded.', $display);
        self::assertStringContainsString('Last cron execution: Never recorded.', $display);
        self::assertStringContainsString('Recent execution history', $display);
        self::assertStringContainsString('None recorded.', $display);

    }

    public function testItDisplaysCurrentStatusWithActionAndProbeToggles(): void
    {
        $this->config = $this->createMock(Config::class);
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
            ->expects(self::once())
            ->method('getExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getEffectiveExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getExecutionProfileFallbackReason')
            ->willReturn(null);
        $this->config
            ->expects(self::once())
            ->method('isCronEnabled')
            ->willReturn(false);
        $this->expectHistoryReads([], null, null);
        $this->config
            ->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnCallback(function (string $code) use (&$requestedActionCodes, $actionStates): bool {
                $requestedActionCodes[] = $code;

                return $actionStates[$code];
            });

        $tester = $this->createCommandTester();

        $exitCode = $tester->execute([]);
        $display = trim($tester->getDisplay());

        $expectedOutput = <<<OUTPUT
ChaosDonkey Status
Enabled: Yes
Last run: 2026-03-28T20:22:00+00:00
Last kick: 2
Last outcome: reindex_all
Configured profile: balanced
Effective profile: balanced
Last CLI execution: Never recorded.
Last cron execution: Never recorded.

Recent execution history
None recorded.

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
        $this->config = $this->createMock(Config::class);
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
            ->expects(self::once())
            ->method('getExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getEffectiveExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getExecutionProfileFallbackReason')
            ->willReturn(null);
        $this->config
            ->expects(self::once())
            ->method('isCronEnabled')
            ->willReturn(false);
        $this->expectHistoryReads([], null, null);
        $this->config
            ->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnCallback(function (string $code) use (&$requestedActionCodes, $actionStates): bool {
                $requestedActionCodes[] = $code;

                return $actionStates[$code];
            });

        $tester = $this->createCommandTester();

        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Enabled: No', $display);
        self::assertStringContainsString('Last run: Never', $display);
        self::assertStringContainsString('Last kick: Never', $display);
        self::assertStringContainsString('Last outcome: Never', $display);
        self::assertStringContainsString('Configured profile: balanced', $display);
        self::assertStringContainsString('Effective profile: balanced', $display);
        self::assertStringContainsString('Last CLI execution: Never recorded.', $display);
        self::assertStringContainsString('Last cron execution: Never recorded.', $display);
        self::assertStringContainsString('Recent execution history', $display);
        self::assertStringContainsString('None recorded.', $display);
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

    public function testItDisplaysSafeHistoryPlaceholderWhenRecentHistoryReadFails(): void
    {
        $this->config = $this->createMock(Config::class);
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
            ->expects(self::once())
            ->method('getExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getEffectiveExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getExecutionProfileFallbackReason')
            ->willReturn(null);
        $this->config
            ->expects(self::once())
            ->method('isCronEnabled')
            ->willReturn(true);
        $this->executionHistoryStorage
            ->expects(self::once())
            ->method('getLatestForSource')
            ->with('cli')
            ->willThrowException(new RuntimeException('history query failed'));
        $this->config
            ->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturnCallback(function (string $code) use (&$requestedActionCodes, $actionStates): bool {
                $requestedActionCodes[] = $code;

                return $actionStates[$code];
            });

        $tester = $this->createCommandTester();

        $exitCode = $tester->execute([]);
        $display = trim($tester->getDisplay());

        $expectedOutput = <<<OUTPUT
ChaosDonkey Status
Enabled: Yes
Last run: 2026-03-28T20:22:00+00:00
Last kick: 2
Last outcome: reindex_all
Configured profile: balanced
Effective profile: balanced
Last CLI execution: History unavailable.
Last cron execution: History unavailable.

Recent execution history
History unavailable.

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
        self::assertStringNotContainsString('Cron notice:', $display);
    }

    public function testItShowsAllDisabledTogglesAsDisabled(): void
    {
        $this->config = $this->createMock(Config::class);
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
            ->expects(self::once())
            ->method('getExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getEffectiveExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getExecutionProfileFallbackReason')
            ->willReturn(null);
        $this->config
            ->expects(self::once())
            ->method('isCronEnabled')
            ->willReturn(false);
        $this->expectHistoryReads([], null, null);
        $this->config
            ->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturn(false);

        $tester = $this->createCommandTester();

        $tester->execute([]);

        $display = trim($tester->getDisplay());

        $expectedSection = <<<OUTPUT
Last CLI execution: Never recorded.
Last cron execution: Never recorded.

Recent execution history
None recorded.

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

    public function testItDisplaysFallbackStatusWhenConfiguredAndEffectiveProfilesDiverge(): void
    {
        $latestCron = [
            'executed_at' => '2026-04-02 10:15:00',
            'source' => 'cron',
            'kick' => '7',
            'outcome' => 'napping',
            'configured_profile' => 'balanced',
            'effective_profile' => 'balanced',
            'fallback_reason' => null,
        ];
        $latestCli = [
            'executed_at' => '2026-04-02 09:45:00',
            'source' => 'cli',
            'kick' => '3',
            'outcome' => 'cache_flush',
            'configured_profile' => 'chaos',
            'effective_profile' => 'balanced',
            'fallback_reason' => 'invalid_profile_table',
        ];
        $config = $this->createProfileStatusConfigDouble(
            configuredProfile: 'chaos',
            effectiveProfile: 'balanced',
            fallbackReason: 'invalid_profile_table',
            cronEnabled: true
        );
        $this->expectHistoryReads([$latestCron, $latestCli], $latestCli, $latestCron);

        $tester = new CommandTester(new ChaosDonkeyStatus($config, $this->executionHistoryStorage));

        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Configured profile: chaos', $display);
        self::assertStringContainsString('Effective profile: balanced', $display);
        self::assertStringContainsString('Fallback reason: invalid_profile_table', $display);
        self::assertStringContainsString('Last CLI execution: 2026-04-02 09:45:00 | kick 3 | cache_flush | profile chaos -> balanced | fallback invalid_profile_table', $display);
        self::assertStringContainsString('Last cron execution: 2026-04-02 10:15:00 | kick 7 | napping | profile balanced', $display);
        self::assertStringContainsString('Recent execution history', $display);
        self::assertStringContainsString('- 2026-04-02 10:15:00 | cron | kick 7 | napping | profile balanced', $display);
        self::assertStringContainsString('- 2026-04-02 09:45:00 | cli | kick 3 | cache_flush | profile chaos -> balanced | fallback invalid_profile_table', $display);
    }

    public function testItDisplaysEmergencyFallbackContractForCorruptBalancedProfile(): void
    {
        $config = $this->createProfileStatusConfigDouble(
            configuredProfile: 'balanced',
            effectiveProfile: 'balanced',
            fallbackReason: 'invalid_fallback_profile'
        );
        $this->expectHistoryReads([], null, null);

        $tester = new CommandTester(new ChaosDonkeyStatus($config, $this->executionHistoryStorage));

        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Configured profile: balanced', $display);
        self::assertStringContainsString('Effective profile: balanced', $display);
        self::assertStringContainsString('Fallback reason: invalid_fallback_profile', $display);
        self::assertStringContainsString('Fallback mode: emergency_legacy_balanced_table', $display);
        self::assertStringContainsString('Last CLI execution: Never recorded.', $display);
        self::assertStringContainsString('Last cron execution: Never recorded.', $display);
        self::assertStringContainsString('Recent execution history', $display);
        self::assertStringContainsString('None recorded.', $display);
    }

    public function testItShowsSoftCronNoticeWhenCronIsEnabledWithoutCronHistory(): void
    {
        $this->config = $this->createMock(Config::class);
        $latestCli = [
            'executed_at' => '2026-04-03 09:45:00',
            'source' => 'cli',
            'kick' => '11',
            'outcome' => 'cache_flush',
            'configured_profile' => 'balanced',
            'effective_profile' => 'balanced',
            'fallback_reason' => null,
        ];

        $this->config
            ->expects(self::once())
            ->method('isEnabled')
            ->willReturn(true);
        $this->config
            ->expects(self::once())
            ->method('getLastRun')
            ->willReturn('2026-04-03T09:45:00+00:00');
        $this->config
            ->expects(self::once())
            ->method('getLastKick')
            ->willReturn('11');
        $this->config
            ->expects(self::once())
            ->method('getLastOutcome')
            ->willReturn('cache_flush');
        $this->config
            ->expects(self::once())
            ->method('getExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getEffectiveExecutionProfile')
            ->willReturn('balanced');
        $this->config
            ->expects(self::once())
            ->method('getExecutionProfileFallbackReason')
            ->willReturn(null);
        $this->config
            ->expects(self::once())
            ->method('isCronEnabled')
            ->willReturn(true);
        $this->expectHistoryReads([$latestCli], $latestCli, null);
        $this->config
            ->expects(self::exactly(6))
            ->method('isActionEnabled')
            ->willReturn(true);

        $tester = $this->createCommandTester();

        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Last CLI execution: 2026-04-03 09:45:00 | kick 11 | cache_flush | profile balanced', $display);
        self::assertStringContainsString('Last cron execution: Never recorded.', $display);
        self::assertStringContainsString('Cron notice: Cron is enabled but no cron execution has been recorded yet.', $display);
        self::assertStringContainsString('- 2026-04-03 09:45:00 | cli | kick 11 | cache_flush | profile balanced', $display);
    }

    private function expectHistoryReads(array $historyRows, ?array $latestCli, ?array $latestCron): void
    {
        $this->executionHistoryStorage
            ->expects(self::exactly(2))
            ->method('getLatestForSource')
            ->willReturnMap([
                ['cli', $latestCli],
                ['cron', $latestCron],
            ]);

        $this->executionHistoryStorage
            ->expects(self::once())
            ->method('getRecent')
            ->with(5)
            ->willReturn($historyRows);
    }

    private function createCommandTester(): CommandTester
    {
        return new CommandTester(new ChaosDonkeyStatus($this->config, $this->executionHistoryStorage));
    }

    private function createProfileStatusConfigDouble(
        string $configuredProfile,
        string $effectiveProfile,
        ?string $fallbackReason,
        bool $cronEnabled = false
    ): Config
    {
        return new class ($configuredProfile, $effectiveProfile, $fallbackReason, $cronEnabled) extends Config {
            private string $configuredProfile;

            private string $effectiveProfile;

            private ?string $fallbackReason;

            private bool $cronEnabled;

            public function __construct(string $configuredProfile, string $effectiveProfile, ?string $fallbackReason, bool $cronEnabled)
            {
                $this->configuredProfile = $configuredProfile;
                $this->effectiveProfile = $effectiveProfile;
                $this->fallbackReason = $fallbackReason;
                $this->cronEnabled = $cronEnabled;
            }

            public function isEnabled(string $scopeType = 'store', ?string $scopeCode = null): bool
            {
                return true;
            }

            public function getLastRun(string $scopeType = 'default', ?string $scopeCode = null): ?string
            {
                return '2026-04-01T12:00:00+00:00';
            }

            public function getLastKick(string $scopeType = 'default', ?string $scopeCode = null): ?string
            {
                return '8';
            }

            public function getLastOutcome(string $scopeType = 'default', ?string $scopeCode = null): ?string
            {
                return 'critical_failure';
            }

            public function isActionEnabled(string $actionCode): bool
            {
                return true;
            }

            public function isCronEnabled(string $scopeType = 'default', ?string $scopeCode = null): bool
            {
                return $this->cronEnabled;
            }

            public function getExecutionProfile(string $scopeType = 'default', ?string $scopeCode = null): string
            {
                return $this->configuredProfile;
            }

            public function getEffectiveExecutionProfile(string $scopeType = 'default', ?string $scopeCode = null): string
            {
                return $this->effectiveProfile;
            }

            public function getExecutionProfileFallbackReason(string $scopeType = 'default', ?string $scopeCode = null): ?string
            {
                return $this->fallbackReason;
            }
        };
    }
}
