<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Action;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\HttpFactory;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\Response\HttpFactory as HttpResponseFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShaunMcManus\ChaosDonkey\Action\GraphQlInternalPipelineStress;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use Symfony\Component\Console\Output\BufferedOutput;

class GraphQlInternalPipelineStressTest extends TestCase
{
    private FrontControllerInterface&MockObject $frontController;
    private HttpFactory&MockObject $requestFactory;
    private HttpResponseFactory&MockObject $responseFactory;

    protected function setUp(): void
    {
        $this->frontController = $this->createMock(FrontControllerInterface::class);
        $this->requestFactory = $this->createMock(HttpFactory::class);
        $this->responseFactory = $this->createMock(HttpResponseFactory::class);
    }

    public function testItDispatchesMultipleInternalGraphQlRequests(): void
    {
        $requestOne = $this->createMock(Http::class);
        $requestOne->expects(self::once())->method('setPathInfo')->with('/graphql');
        $requestOne->expects(self::once())->method('setMethod')->with('POST');
        $requestOne->expects(self::once())->method('setContent')->with(self::isString());

        $requestTwo = $this->createMock(Http::class);
        $requestTwo->expects(self::once())->method('setPathInfo')->with('/graphql');
        $requestTwo->expects(self::once())->method('setMethod')->with('POST');
        $requestTwo->expects(self::once())->method('setContent')->with(self::isString());

        $responseOne = $this->createStub(HttpResponse::class);
        $responseOne->method('getHttpResponseCode')->willReturn(200);
        $responseOne->method('getBody')->willReturn('{"data":{}}');

        $responseTwo = $this->createStub(HttpResponse::class);
        $responseTwo->method('getHttpResponseCode')->willReturn(200);
        $responseTwo->method('getBody')->willReturn('{"data":{}}');

        $this->requestFactory
            ->expects(self::exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($requestOne, $requestTwo);

        $this->responseFactory
            ->expects(self::exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($responseOne, $responseTwo);

        $this->frontController
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::isInstanceOf(Http::class));

        $action = new GraphQlInternalPipelineStress($this->frontController, $this->requestFactory, $this->responseFactory);
        $output = new BufferedOutput();

        $result = $action->execute($output);

        self::assertInstanceOf(ChaosActionResult::class, $result);
        self::assertTrue($result->isSuccess());
        self::assertSame('graphql_pipeline_stress', $result->getOutcomeCode());
        self::assertSame('GraphQL internal pipeline stress completed successfully', $result->getSummary());
        self::assertCount(2, $result->getDetails());
    }

    public function testItContinuesWhenOneDispatchFails(): void
    {
        $requestOne = $this->createMock(Http::class);
        $requestOne->expects(self::once())->method('setPathInfo')->with('/graphql');
        $requestOne->expects(self::once())->method('setMethod')->with('POST');
        $requestOne->expects(self::once())->method('setContent')->with(self::isString());

        $requestTwo = $this->createMock(Http::class);
        $requestTwo->expects(self::once())->method('setPathInfo')->with('/graphql');
        $requestTwo->expects(self::once())->method('setMethod')->with('POST');
        $requestTwo->expects(self::once())->method('setContent')->with(self::isString());

        $responseOne = $this->createStub(HttpResponse::class);
        $responseOne->method('getHttpResponseCode')->willReturn(200);
        $responseOne->method('getBody')->willReturn('{"data":{}}');

        $responseTwo = $this->createStub(HttpResponse::class);
        $responseTwo->method('getHttpResponseCode')->willReturn(500);
        $responseTwo->method('getBody')->willReturn('{"errors":[{"message":"boom"}]}');

        $this->requestFactory
            ->expects(self::exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($requestOne, $requestTwo);

        $this->responseFactory
            ->expects(self::exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($responseOne, $responseTwo);

        $calls = 0;
        $this->frontController
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (Http $request) use (&$calls): void {
                $calls++;

                if ($calls === 2) {
                    throw new RuntimeException('dispatch failed');
                }
            });

        $action = new GraphQlInternalPipelineStress($this->frontController, $this->requestFactory, $this->responseFactory);
        $output = new BufferedOutput();

        $result = $action->execute($output);

        self::assertInstanceOf(ChaosActionResult::class, $result);
        self::assertFalse($result->isSuccess());
        self::assertSame('graphql_pipeline_stress', $result->getOutcomeCode());
        self::assertSame('GraphQL pipeline stress completed with failures', $result->getSummary());
        self::assertCount(2, $result->getDetails());
        self::assertStringContainsString('Failed:', implode("\n", $result->getDetails()));
    }
}
