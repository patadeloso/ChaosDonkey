<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Action;

use Magento\Framework\App\Cache\Manager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShaunMcManus\ChaosDonkey\Action\CacheFlush;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use Symfony\Component\Console\Output\BufferedOutput;

class CacheFlushTest extends TestCase
{
    private Manager&MockObject $cacheManager;

    protected function setUp(): void
    {
        $this->cacheManager = $this->createMock(Manager::class);
    }

    public function testItFlushesAllCacheTypesSuccessfully(): void
    {
        $this->cacheManager
            ->expects(self::once())
            ->method('getAvailableTypes')
            ->willReturn(['config' => 1, 'full_page' => 1]);

        $this->cacheManager
            ->expects(self::exactly(2))
            ->method('clean')
            ->willReturnCallback(static function (array $types): void {
                static $calls = 0;
                $calls++;

                if ($calls === 1) {
                    self::assertSame(['config'], $types);
                }

                if ($calls === 2) {
                    self::assertSame(['full_page'], $types);
                }
            });

        $action = new CacheFlush($this->cacheManager);
        $output = new BufferedOutput();

        $result = $action->execute($output);

        self::assertInstanceOf(ChaosActionResult::class, $result);
        self::assertTrue($result->isSuccess());
        self::assertSame('cache_flush', $result->getOutcomeCode());
        self::assertSame('Flushed all cache types successfully', $result->getSummary());
        self::assertCount(2, $result->getDetails());
    }

    public function testItContinuesWhenOneCacheTypeFails(): void
    {
        $this->cacheManager
            ->expects(self::once())
            ->method('getAvailableTypes')
            ->willReturn(['config' => 1, 'full_page' => 1]);

        $invocation = 0;
        $this->cacheManager
            ->expects(self::exactly(2))
            ->method('clean')
            ->willReturnCallback(static function (array $types) use (&$invocation): void {
                $invocation++;

                if ($invocation === 2) {
                    throw new RuntimeException('flush failed');
                }
            });

        $action = new CacheFlush($this->cacheManager);
        $output = new BufferedOutput();

        $result = $action->execute($output);

        self::assertInstanceOf(ChaosActionResult::class, $result);
        self::assertFalse($result->isSuccess());
        self::assertSame('cache_flush', $result->getOutcomeCode());
        self::assertSame('Cache flush completed with failures', $result->getSummary());
        self::assertCount(2, $result->getDetails());
        self::assertStringContainsString('Failed: full_page', implode("\n", $result->getDetails()));
    }
}
