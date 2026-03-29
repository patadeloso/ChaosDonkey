<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Action;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\Request\HttpFactory;
use ShaunMcManus\ChaosDonkey\Api\ChaosActionInterface;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use Throwable;
use Symfony\Component\Console\Output\OutputInterface;

class GraphQlInternalPipelineStress implements ChaosActionInterface
{
    /**
     * @var list<string>
     */
    private array $payloads;

    public function __construct(
        private FrontControllerInterface $frontController,
        private HttpFactory $requestFactory,
        ?array $payloads = null
    ) {
        $this->payloads = $payloads ?? [
            '{ products(search: "shirt", pageSize: 3) { items { sku name } } }',
            '{ categories(filters: { parent_id: { eq: "2" } }) { items { id name } } }',
        ];
    }

    public function getCode(): string
    {
        return 'graphql_pipeline_stress';
    }

    public function execute(OutputInterface $output): ChaosActionResult
    {
        $output->writeln('Running internal GraphQL pipeline stress...');

        $details = [];
        $hasFailure = false;

        foreach ($this->payloads as $index => $query) {
            $request = $this->requestFactory->create();

            $request->setPathInfo('/graphql');
            $request->setMethod('POST');
            $request->setContent((string) json_encode(['query' => $query]));

            try {
                $response = $this->frontController->dispatch($request);

                $statusCode = $response->getHttpResponseCode();
                $body = $response->getBody();

                if ($statusCode >= 400 || str_contains($body, '"errors"')) {
                    $message = sprintf('Failed: payload %d returned status %d', $index + 1, $statusCode);
                    $hasFailure = true;
                } else {
                    $message = sprintf('OK: payload %d dispatched internally', $index + 1);
                }

                $output->writeln($message);
                $details[] = $message;
            } catch (Throwable $exception) {
                $message = sprintf('Failed: payload %d (%s)', $index + 1, $exception->getMessage());
                $output->writeln($message);
                $details[] = $message;
                $hasFailure = true;
            }
        }

        return new ChaosActionResult(
            $this->getCode(),
            $hasFailure
                ? 'GraphQL pipeline stress completed with failures'
                : 'GraphQL internal pipeline stress completed successfully',
            $details,
            !$hasFailure
        );
    }
}
