<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testItReadsReindexAllEnabledFlagFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('isSetFlag')
            ->with(Config::CONFIG_PATH_ENABLE_REINDEX_ALL, 'default', null)
            ->willReturn(true);

        $config = new Config($this->scopeConfig);

        self::assertTrue($config->isReindexAllEnabled());
    }

    public function testItReadsCacheFlushEnabledFlagFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('isSetFlag')
            ->with(Config::CONFIG_PATH_ENABLE_CACHE_FLUSH, 'default', null)
            ->willReturn(true);

        $config = new Config($this->scopeConfig);

        self::assertTrue($config->isCacheFlushEnabled());
    }

    public function testItReadsGraphQlPipelineStressEnabledFlagFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('isSetFlag')
            ->with(Config::CONFIG_PATH_ENABLE_GRAPHQL_PIPELINE_STRESS, 'default', null)
            ->willReturn(true);

        $config = new Config($this->scopeConfig);

        self::assertTrue($config->isGraphQlPipelineStressEnabled());
    }

    public function testItReadsIndexerStatusSnapshotEnabledFlagFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('isSetFlag')
            ->with(Config::CONFIG_PATH_ENABLE_INDEXER_STATUS_SNAPSHOT, 'default', null)
            ->willReturn(true);

        $config = new Config($this->scopeConfig);

        self::assertTrue($config->isIndexerStatusSnapshotEnabled());
    }

    public function testItReadsCacheBackendHealthSnapshotEnabledFlagFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('isSetFlag')
            ->with(Config::CONFIG_PATH_ENABLE_CACHE_BACKEND_HEALTH_SNAPSHOT, 'default', null)
            ->willReturn(true);

        $config = new Config($this->scopeConfig);

        self::assertTrue($config->isCacheBackendHealthSnapshotEnabled());
    }

    public function testItReadsCronQueueHealthSnapshotEnabledFlagFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('isSetFlag')
            ->with(Config::CONFIG_PATH_ENABLE_CRON_QUEUE_HEALTH_SNAPSHOT, 'default', null)
            ->willReturn(true);

        $config = new Config($this->scopeConfig);

        self::assertTrue($config->isCronQueueHealthSnapshotEnabled());
    }

    public function testItReadsCronEnabledFlagFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('isSetFlag')
            ->with(Config::CONFIG_PATH_CRON_ENABLED, 'default', null)
            ->willReturn(true);

        $config = new Config($this->scopeConfig);

        self::assertTrue($config->isCronEnabled());
    }

    public function testItReadsTrimmedCronExpressionFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CONFIG_PATH_CRON_EXPRESSION, 'default', null)
            ->willReturn('  */30 * * * *  ');

        $config = new Config($this->scopeConfig);

        self::assertSame('*/30 * * * *', $config->getCronExpression());
    }

    public function testItTreatsEmptyCronExpressionAsUnset(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CONFIG_PATH_CRON_EXPRESSION, 'default', null)
            ->willReturn('   ');

        $config = new Config($this->scopeConfig);

        self::assertNull($config->getCronExpression());
    }

    public function testItReadsTrimmedCronAllowedHoursFromExpectedPath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CONFIG_PATH_CRON_ALLOWED_HOURS, 'default', null)
            ->willReturn(' 1, 2, 3 ');

        $config = new Config($this->scopeConfig);

        self::assertSame('1, 2, 3', $config->getCronAllowedHoursRaw());
    }

    public function testItTreatsEmptyCronAllowedHoursAsUnset(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CONFIG_PATH_CRON_ALLOWED_HOURS, 'default', null)
            ->willReturn('   ');

        $config = new Config($this->scopeConfig);

        self::assertNull($config->getCronAllowedHoursRaw());
    }

    public function testItReturnsEmptyAllowedHoursWhenUnset(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CONFIG_PATH_CRON_ALLOWED_HOURS, 'default', null)
            ->willReturn(null);

        $config = new Config($this->scopeConfig);

        self::assertSame([], $config->getCronAllowedHours());
    }

    public function testItParsesAllowedHoursIntoUniqueSortedIntegers(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CONFIG_PATH_CRON_ALLOWED_HOURS, 'default', null)
            ->willReturn(' 5, 2, 5, 23, 0, 18 ');

        $config = new Config($this->scopeConfig);

        self::assertSame([0, 2, 5, 18, 23], $config->getCronAllowedHours());
    }

    public function testItIgnoresInvalidAllowedHourTokens(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CONFIG_PATH_CRON_ALLOWED_HOURS, 'default', null)
            ->willReturn('foo, -1, 24, 7, 03, 12bar, 8, , 11');

        $config = new Config($this->scopeConfig);

        self::assertSame([3, 7, 8, 11], $config->getCronAllowedHours());
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

    public function testItReadsExecutionProfileKeyFromDefaultScopePath(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CONFIG_PATH_EXECUTION_PROFILE, 'default', null)
            ->willReturn('chaos');

        $config = new Config($this->scopeConfig);

        self::assertSame('chaos', $config->getExecutionProfile());
    }

    public function testItDefaultsExecutionProfileToBalancedWhenUnset(): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CONFIG_PATH_EXECUTION_PROFILE, 'default', null)
            ->willReturn(null);

        $config = new Config($this->scopeConfig);

        self::assertSame('balanced', $config->getExecutionProfile());
    }

    #[DataProvider('requiredExecutionProfileStatusMethodProvider')]
    public function testItExposesExecutionProfileMethodsRequiredByStatusCommand(
        string $methodName,
        string $expectedReturnType
    ): void
    {
        $reflection = new \ReflectionClass(Config::class);

        self::assertTrue(
            $reflection->hasMethod($methodName),
            sprintf('Expected Config to expose status profile method: %s', $methodName)
        );

        $method = $reflection->getMethod($methodName);

        self::assertTrue(
            $method->isPublic(),
            sprintf('Expected Config status profile method to be public: %s', $methodName)
        );

        self::assertSame(
            0,
            $method->getNumberOfRequiredParameters(),
            sprintf('Expected Config status profile method to be callable with no args: %s', $methodName)
        );

        self::assertLessThanOrEqual(
            2,
            $method->getNumberOfParameters(),
            sprintf('Expected Config status profile method to use optional scope-style args: %s', $methodName)
        );

        $parameters = $method->getParameters();

        if (isset($parameters[0])) {
            self::assertTrue($parameters[0]->isOptional());
            self::assertSame('scopeType', $parameters[0]->getName());
            self::assertSame('string', (string) $parameters[0]->getType());
        }

        if (isset($parameters[1])) {
            self::assertTrue($parameters[1]->isOptional());
            self::assertSame('scopeCode', $parameters[1]->getName());
            self::assertSame('string', $parameters[1]->getType()?->getName());
            self::assertTrue($parameters[1]->allowsNull());
        }

        self::assertTrue($method->hasReturnType());
        self::assertSame($expectedReturnType, (string) $method->getReturnType());
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

    #[DataProvider('actionCodeMappingProvider')]
    public function testItMapsActionCodesToTheirMatchingToggle(string $actionCode, string $expectedConfigPath): void
    {
        $this->scopeConfig
            ->expects(self::once())
            ->method('isSetFlag')
            ->with($expectedConfigPath, 'default', null)
            ->willReturn(true);

        $config = new Config($this->scopeConfig);

        self::assertTrue($config->isActionEnabled($actionCode));
    }

    public function testItAllowsUnknownActionCodesByDefault(): void
    {
        $this->scopeConfig
            ->expects(self::never())
            ->method('isSetFlag');

        $config = new Config($this->scopeConfig);

        self::assertTrue($config->isActionEnabled('napping'));
    }

    public static function actionCodeMappingProvider(): array
    {
        return [
            'reindex all' => [
                'reindex_all',
                Config::CONFIG_PATH_ENABLE_REINDEX_ALL,
            ],
            'cache flush' => [
                'cache_flush',
                Config::CONFIG_PATH_ENABLE_CACHE_FLUSH,
            ],
            'graphql pipeline stress' => [
                'graphql_pipeline_stress',
                Config::CONFIG_PATH_ENABLE_GRAPHQL_PIPELINE_STRESS,
            ],
            'indexer status snapshot' => [
                'indexer_status_snapshot',
                Config::CONFIG_PATH_ENABLE_INDEXER_STATUS_SNAPSHOT,
            ],
            'cache backend health snapshot' => [
                'cache_backend_health_snapshot',
                Config::CONFIG_PATH_ENABLE_CACHE_BACKEND_HEALTH_SNAPSHOT,
            ],
            'cron queue health snapshot' => [
                'cron_queue_health_snapshot',
                Config::CONFIG_PATH_ENABLE_CRON_QUEUE_HEALTH_SNAPSHOT,
            ],
        ];
    }

    public static function requiredExecutionProfileStatusMethodProvider(): array
    {
        return [
            'configured profile getter' => ['getExecutionProfile', 'string'],
            'effective profile getter' => ['getEffectiveExecutionProfile', 'string'],
            'fallback reason getter' => ['getExecutionProfileFallbackReason', '?string'],
        ];
    }
}
