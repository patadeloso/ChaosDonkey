<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Cron;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ShaunMcManus\ChaosDonkey\Cron\ChaosDonkeyKickCron;
use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\KickExecutor;

class ChaosDonkeyKickCronTest extends TestCase
{
    private Config&MockObject $config;
    private KickExecutor&MockObject $kickExecutor;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->kickExecutor = $this->createMock(KickExecutor::class);
        $this->logger = new NullLoggerStub();
    }

    public function testItSkipsWhenModuleDisabled(): void
    {
        $cron = $this->createCron(12);

        $this->config->expects(self::once())->method('isEnabled')->willReturn(false);
        $this->config->expects(self::never())->method('isCronEnabled');
        $this->config->expects(self::never())->method('getCronExpression');
        $this->kickExecutor->expects(self::never())->method('execute');

        $cron->execute();

        self::assertSame([
            'ChaosDonkey cron started.',
            'Skipping ChaosDonkey cron because the module is disabled.',
        ], $cron->messages);
    }

    public function testItSkipsWhenCronDisabled(): void
    {
        $cron = $this->createCron(12);

        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->config->expects(self::once())->method('isCronEnabled')->willReturn(false);
        $this->config->expects(self::never())->method('getCronExpression');
        $this->kickExecutor->expects(self::never())->method('execute');

        $cron->execute();

        self::assertSame([
            'ChaosDonkey cron started.',
            'Skipping ChaosDonkey cron because cron execution is disabled.',
        ], $cron->messages);
    }

    public function testItSkipsOutsideAllowedHours(): void
    {
        $cron = $this->createCron(9);

        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->config->expects(self::once())->method('isCronEnabled')->willReturn(true);
        $this->config->expects(self::once())->method('getCronExpression')->willReturn('*/30 * * * *');
        $this->config->expects(self::once())->method('getCronAllowedHoursRaw')->willReturn('1, 5, 12');
        $this->config->expects(self::once())->method('getCronAllowedHours')->willReturn([1, 5, 12]);
        $this->kickExecutor->expects(self::never())->method('execute');

        $cron->execute();

        self::assertSame([
            'ChaosDonkey cron started.',
            'Skipping ChaosDonkey cron because current hour 9 is not in the allowed window.',
        ], $cron->messages);
    }

    public function testItExecutesWhenEnabledAndInsideAllowedHours(): void
    {
        $cron = $this->createCron(5);

        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->config->expects(self::once())->method('isCronEnabled')->willReturn(true);
        $this->config->expects(self::once())->method('getCronExpression')->willReturn('*/30 * * * *');
        $this->config->expects(self::once())->method('getCronAllowedHoursRaw')->willReturn('1, 5, 12');
        $this->config->expects(self::once())->method('getCronAllowedHours')->willReturn([1, 5, 12]);
        $this->kickExecutor
            ->expects(self::once())
            ->method('execute')
            ->willReturn([
                'kick' => 5,
                'outcome' => 'napping',
                'messages' => ['ChaosDonkeyKick kicks your Magento. You rolled a 5', 'The donkeys are napping'],
            ]);

        $cron->execute();

        self::assertSame([
            'ChaosDonkey cron started.',
            'Executing ChaosDonkey cron at hour 5.',
            'ChaosDonkey cron completed with kick 5 and outcome napping.',
        ], $cron->messages);
    }

    public function testItSkipsWhenAllowedHoursConfigIsInvalid(): void
    {
        $cron = $this->createCron(8);

        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->config->expects(self::once())->method('isCronEnabled')->willReturn(true);
        $this->config->expects(self::once())->method('getCronExpression')->willReturn('*/30 * * * *');
        $this->config->expects(self::once())->method('getCronAllowedHoursRaw')->willReturn('foo, 99');
        $this->config->expects(self::once())->method('getCronAllowedHours')->willReturn([]);
        $this->kickExecutor->expects(self::never())->method('execute');

        $cron->execute();

        self::assertSame([
            'ChaosDonkey cron started.',
            'Skipping ChaosDonkey cron because cron_allowed_hours is invalid.',
        ], $cron->messages);
    }

    public function testItSkipsWhenCronExpressionIsInvalid(): void
    {
        $cron = $this->createCron(10);

        $this->config->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->config->expects(self::once())->method('isCronEnabled')->willReturn(true);
        $this->config->expects(self::once())->method('getCronExpression')->willReturn('invalid cron');
        $this->config->expects(self::never())->method('getCronAllowedHoursRaw');
        $this->config->expects(self::never())->method('getCronAllowedHours');
        $this->kickExecutor->expects(self::never())->method('execute');

        $cron->execute();

        self::assertSame([
            'ChaosDonkey cron started.',
            'Skipping ChaosDonkey cron because cron_expression is invalid.',
        ], $cron->messages);
    }

    private function createCron(int $currentHour): CronHarness
    {
        return new CronHarness($this->config, $this->kickExecutor, $this->logger, $currentHour);
    }
}

class CronHarness extends ChaosDonkeyKickCron
{
    /**
     * @var list<string>
     */
    public array $messages = [];

    public function __construct(
        Config $config,
        KickExecutor $kickExecutor,
        LoggerInterface $logger,
        private int $currentHour
    ) {
        parent::__construct($config, $kickExecutor, $logger);
    }

    protected function getCurrentHour(): int
    {
        return $this->currentHour;
    }

    protected function logMessage(string $message): void
    {
        $this->messages[] = $message;
    }
}

class NullLoggerStub implements LoggerInterface
{
    public function emergency($message, array $context = []): void
    {
    }

    public function alert($message, array $context = []): void
    {
    }

    public function critical($message, array $context = []): void
    {
    }

    public function error($message, array $context = []): void
    {
    }

    public function warning($message, array $context = []): void
    {
    }

    public function notice($message, array $context = []): void
    {
    }

    public function info($message, array $context = []): void
    {
    }

    public function debug($message, array $context = []): void
    {
    }

    public function log($level, $message, array $context = []): void
    {
    }
}
