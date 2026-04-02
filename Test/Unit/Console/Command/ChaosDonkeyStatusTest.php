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
        self::assertStringContainsString('Configured profile: balanced', $display);
        self::assertStringContainsString('Effective profile: balanced', $display);
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

        $command = new ChaosDonkeyStatus($this->config);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Enabled: No', $display);
        self::assertStringContainsString('Last run: Never', $display);
        self::assertStringContainsString('Last kick: Never', $display);
        self::assertStringContainsString('Last outcome: Never', $display);
        self::assertStringContainsString('Configured profile: balanced', $display);
        self::assertStringContainsString('Effective profile: balanced', $display);

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
Configured profile: balanced
Effective profile: balanced

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
        self::assertStringContainsString('Configured profile: balanced', $display);
        self::assertStringContainsString('Effective profile: balanced', $display);
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

    public function testItDisplaysFallbackStatusWhenConfiguredAndEffectiveProfilesDiverge(): void
    {
        $config = $this->createProfileStatusConfigDouble(
            configuredProfile: 'chaos',
            effectiveProfile: 'balanced',
            fallbackReason: 'invalid_profile_table'
        );

        $command = new ChaosDonkeyStatus($config);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Configured profile: chaos', $display);
        self::assertStringContainsString('Effective profile: balanced', $display);
        self::assertStringContainsString('Fallback reason: invalid_profile_table', $display);
    }

    public function testItDisplaysEmergencyFallbackContractForCorruptBalancedProfile(): void
    {
        $config = $this->createProfileStatusConfigDouble(
            configuredProfile: 'balanced',
            effectiveProfile: 'balanced',
            fallbackReason: 'invalid_fallback_profile'
        );

        $command = new ChaosDonkeyStatus($config);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Configured profile: balanced', $display);
        self::assertStringContainsString('Effective profile: balanced', $display);
        self::assertStringContainsString('Fallback reason: invalid_fallback_profile', $display);
        self::assertStringContainsString('Fallback mode: emergency_legacy_balanced_table', $display);
    }

    private function createProfileStatusConfigDouble(
        string $configuredProfile,
        string $effectiveProfile,
        ?string $fallbackReason
    ): Config
    {
        return new class ($configuredProfile, $effectiveProfile, $fallbackReason) extends Config {
            private string $configuredProfile;

            private string $effectiveProfile;

            private ?string $fallbackReason;

            public function __construct(string $configuredProfile, string $effectiveProfile, ?string $fallbackReason)
            {
                $this->configuredProfile = $configuredProfile;
                $this->effectiveProfile = $effectiveProfile;
                $this->fallbackReason = $fallbackReason;
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
