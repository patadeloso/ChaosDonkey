<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Action;

use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShaunMcManus\ChaosDonkey\Action\CacheBackendHealthSnapshot;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeOutputFormatter;
use Symfony\Component\Console\Output\BufferedOutput;

class CacheBackendHealthSnapshotTest extends TestCase
{
    private TypeListInterface&MockObject $cacheTypeList;
    private Pool&MockObject $frontendPool;

    protected function setUp(): void
    {
        $this->cacheTypeList = $this->createMock(TypeListInterface::class);
        $this->frontendPool = $this->createMock(Pool::class);
    }

    public function testItWritesOkSummaryAndDetailRowsWhenMetadataAndDefaultBackendResolve(): void
    {
        $this->cacheTypeList
            ->expects(self::once())
            ->method('getTypes')
            ->willReturn([
                'config' => ['status' => 1],
                'layout' => ['status' => 0],
                'full_page' => ['status' => 1],
            ]);

        $this->frontendPool
            ->expects(self::once())
            ->method('get')
            ->with('default')
            ->willReturn(new CacheBackendHealthSnapshotFakeFrontend(new CacheBackendHealthSnapshotSafeBackend()));

        $result = $this->runProbe();

        self::assertSame('cache_backend_health_snapshot', $result['instance']->getOutcomeCode());
        self::assertSame('', $result['instance']->getSummary());
        self::assertTrue($result['instance']->isSuccess());

        $lines = $this->splitOutput($result['output']);
        self::assertCount(5, $lines);
        self::assertSame(
            'Probe[cache_backend_health_snapshot] status=ok msg="3 cache types, 2 enabled, backend adapter=cachebackendhealthsnapshotsafebackend"',
            $lines[0]
        );
        self::assertStringContainsString('ProbeDetail[cache_backend_health_snapshot] subsystem=cache item=config status=ok value="enabled=true"', $result['output']);
        self::assertStringContainsString('ProbeDetail[cache_backend_health_snapshot] subsystem=cache item=layout status=ok value="enabled=false"', $result['output']);
        self::assertStringContainsString('ProbeDetail[cache_backend_health_snapshot] subsystem=cache item=full_page status=ok value="enabled=true"', $result['output']);
        self::assertStringContainsString('ProbeDetail[cache_backend_health_snapshot] subsystem=cache_backend item=default_frontend status=ok value="cachebackendhealthsnapshotsafebackend"', $result['output']);
    }

    public function testItWritesWarnSummaryWhenDefaultBackendResolutionFails(): void
    {
        $this->cacheTypeList
            ->expects(self::once())
            ->method('getTypes')
            ->willReturn([
                'config' => ['status' => 1],
            ]);

        $this->frontendPool
            ->expects(self::once())
            ->method('get')
            ->with('default')
            ->willReturn(new CacheBackendHealthSnapshotFakeFrontend(null, new RuntimeException('backend failed')));

        $result = $this->runProbe();

        self::assertTrue($result['instance']->isSuccess());

        self::assertSame(
            'Probe[cache_backend_health_snapshot] status=warn msg="1 cache types, 1 enabled, backend adapter resolution degraded"',
            $this->splitOutput($result['output'])[0]
        );
        self::assertStringContainsString(
            'ProbeDetail[cache_backend_health_snapshot] subsystem=cache_backend item=default_frontend status=warn value="resolution_failed"',
            $result['output']
        );
    }

    public function testItWritesUnknownSummaryWhenMetadataCannotBeRead(): void
    {
        $this->cacheTypeList
            ->expects(self::once())
            ->method('getTypes')
            ->willThrowException(new RuntimeException('metadata unavailable'));

        $this->frontendPool
            ->expects(self::never())
            ->method('get');

        $result = $this->runProbe();

        $lines = $this->splitOutput($result['output']);

        self::assertFalse($result['instance']->isSuccess());
        self::assertSame(2, count($lines));
        self::assertSame(
            'Probe[cache_backend_health_snapshot] status=unknown msg="cache snapshot unavailable"',
            $lines[0]
        );
        self::assertSame(
            'ProbeDetail[cache_backend_health_snapshot] subsystem=cache item=metadata status=unknown value="unavailable"',
            $lines[1]
        );
    }

    public function testItWritesUnknownSummaryWhenDefaultFrontendUnavailable(): void
    {
        $this->cacheTypeList
            ->expects(self::once())
            ->method('getTypes')
            ->willReturn([
                'config' => ['status' => 1],
                'full_page' => ['status' => 0],
            ]);

        $this->frontendPool
            ->expects(self::once())
            ->method('get')
            ->with('default')
            ->willThrowException(new RuntimeException('default frontend missing'));

        $result = $this->runProbe();

        self::assertFalse($result['instance']->isSuccess());
        self::assertSame(
            'Probe[cache_backend_health_snapshot] status=unknown msg="cache snapshot unavailable"',
            $this->splitOutput($result['output'])[0]
        );
        self::assertStringContainsString(
            'ProbeDetail[cache_backend_health_snapshot] subsystem=cache item=config status=ok value="enabled=true"',
            $result['output']
        );
        self::assertStringContainsString(
            'ProbeDetail[cache_backend_health_snapshot] subsystem=cache item=full_page status=ok value="enabled=false"',
            $result['output']
        );
        self::assertStringContainsString(
            'ProbeDetail[cache_backend_health_snapshot] subsystem=cache_backend item=default_frontend status=unknown value="unavailable"',
            $result['output']
        );
    }

    public function testItLimitsOutputToSummaryPlusFiveDetails(): void
    {
        $types = [];
        for ($i = 1; $i <= 7; $i++) {
            $types['type_' . $i] = ['status' => 1];
        }

        $this->cacheTypeList
            ->expects(self::once())
            ->method('getTypes')
            ->willReturn($types);

        $this->frontendPool
            ->expects(self::once())
            ->method('get')
            ->with('default')
            ->willReturn(new CacheBackendHealthSnapshotFakeFrontend(new CacheBackendHealthSnapshotSafeBackend()));

        $result = $this->runProbe();

        self::assertCount(7, $types);
        self::assertLessThanOrEqual(6, count($this->splitOutput($result['output'])));
        self::assertSame(
            'Probe[cache_backend_health_snapshot] status=ok msg="7 cache types, 7 enabled, backend adapter=cachebackendhealthsnapshotsafebackend"',
            $this->splitOutput($result['output'])[0]
        );
    }

    public function testItHandlesMalformedMetadataRowsWithoutThrowing(): void
    {
        $this->cacheTypeList
            ->expects(self::once())
            ->method('getTypes')
            ->willReturn([
                'config' => ['status' => 1],
                'weird_object' => new \stdClass(),
                'missing_status' => [],
                'layout' => ['status' => 0],
            ]);

        $this->frontendPool
            ->expects(self::once())
            ->method('get')
            ->with('default')
            ->willReturn(new CacheBackendHealthSnapshotFakeFrontend(new CacheBackendHealthSnapshotSafeBackend()));

        $result = $this->runProbe();

        self::assertTrue($result['instance']->isSuccess());
        self::assertSame(
            'Probe[cache_backend_health_snapshot] status=ok msg="4 cache types, 1 enabled, backend adapter=cachebackendhealthsnapshotsafebackend"',
            $this->splitOutput($result['output'])[0]
        );
        self::assertStringContainsString('ProbeDetail[cache_backend_health_snapshot] subsystem=cache item=config status=ok value="enabled=true"', $result['output']);
        self::assertStringContainsString('ProbeDetail[cache_backend_health_snapshot] subsystem=cache item=weird_object status=ok value="enabled=false"', $result['output']);
        self::assertStringContainsString('ProbeDetail[cache_backend_health_snapshot] subsystem=cache item=missing_status status=ok value="enabled=false"', $result['output']);
        self::assertStringContainsString('ProbeDetail[cache_backend_health_snapshot] subsystem=cache item=layout status=ok value="enabled=false"', $result['output']);
    }

    public function testItSanitizesUnsafeAdapterClassNameWhenResolvingBackend(): void
    {
        $this->cacheTypeList
            ->expects(self::once())
            ->method('getTypes')
            ->willReturn([
                'config' => ['status' => 1],
            ]);

        $this->frontendPool
            ->expects(self::once())
            ->method('get')
            ->with('default')
            ->willReturn(new CacheBackendHealthSnapshotFakeFrontend(new ÄBackend()));

        $result = $this->runProbe();

        self::assertSame(
            'Probe[cache_backend_health_snapshot] status=ok msg="1 cache types, 1 enabled, backend adapter=backend"',
            $this->splitOutput($result['output'])[0]
        );
        self::assertStringContainsString(
            'ProbeDetail[cache_backend_health_snapshot] subsystem=cache_backend item=default_frontend status=ok value="backend"',
            $result['output']
        );
    }

    public function testAdapterLabelFallsBackToUnavailableWhenUnsanitizable(): void
    {
        $this->cacheTypeList
            ->expects(self::once())
            ->method('getTypes')
            ->willReturn([
                'config' => ['status' => 1],
            ]);

        $this->frontendPool
            ->expects(self::once())
            ->method('get')
            ->with('default')
            ->willReturn(new CacheBackendHealthSnapshotFakeFrontend(new Ä()));

        $result = $this->runProbe();

        self::assertSame(
            'Probe[cache_backend_health_snapshot] status=ok msg="1 cache types, 1 enabled, backend adapter=unavailable"',
            $this->splitOutput($result['output'])[0]
        );
        self::assertStringContainsString(
            'ProbeDetail[cache_backend_health_snapshot] subsystem=cache_backend item=default_frontend status=ok value="unavailable"',
            $result['output']
        );
    }

    public function testItTreatsNullDefaultFrontendAsUnavailable(): void
    {
        $this->cacheTypeList
            ->expects(self::once())
            ->method('getTypes')
            ->willReturn([
                'config' => ['status' => 1],
                'layout' => ['status' => 0],
            ]);

        $this->frontendPool
            ->expects(self::once())
            ->method('get')
            ->with('default')
            ->willReturn(null);

        $result = $this->runProbe();

        self::assertFalse($result['instance']->isSuccess());
        self::assertSame(
            'Probe[cache_backend_health_snapshot] status=unknown msg="cache snapshot unavailable"',
            $this->splitOutput($result['output'])[0]
        );
        self::assertSame(4, count($this->splitOutput($result['output'])));
        self::assertStringContainsString(
            'ProbeDetail[cache_backend_health_snapshot] subsystem=cache item=config status=ok value="enabled=true"',
            $result['output']
        );
        self::assertStringContainsString(
            'ProbeDetail[cache_backend_health_snapshot] subsystem=cache item=layout status=ok value="enabled=false"',
            $result['output']
        );
        self::assertStringContainsString(
            'ProbeDetail[cache_backend_health_snapshot] subsystem=cache_backend item=default_frontend status=unknown value="unavailable"',
            $result['output']
        );
    }

    private function runProbe(): array
    {
        $output = new BufferedOutput();
        $action = new CacheBackendHealthSnapshot(
            $this->cacheTypeList,
            $this->frontendPool,
            new ProbeOutputFormatter(),
        );

        $result = $action->execute($output);

        return [
            'output' => trim($output->fetch()),
            'instance' => $result,
        ];
    }

    private function splitOutput(string $output): array
    {
        if ($output === '') {
            return [];
        }

        return preg_split('/\r\n|\r|\n/', $output);
    }
}

final class CacheBackendHealthSnapshotFakeFrontend
{
    public function __construct(
        private ?object $backend = null,
        private ?\Throwable $backendFailure = null
    ) {
    }

    public function getBackend(): object
    {
        if ($this->backendFailure !== null) {
            throw $this->backendFailure;
        }

        return $this->backend ?? new class {
        };
    }
}

final class CacheBackendHealthSnapshotSafeBackend
{
}

final class ÄBackend
{
}

final class Ä
{
}
