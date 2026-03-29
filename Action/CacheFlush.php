<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Action;

use Magento\Framework\App\Cache\Manager;
use ShaunMcManus\ChaosDonkey\Api\ChaosActionInterface;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use Throwable;
use Symfony\Component\Console\Output\OutputInterface;

class CacheFlush implements ChaosActionInterface
{
    public function __construct(private Manager $cacheManager)
    {
    }

    public function getCode(): string
    {
        return 'cache_flush';
    }

    public function execute(OutputInterface $output): ChaosActionResult
    {
        $output->writeln('Flushing cache types...');

        $details = [];
        $hasFailure = false;

        foreach (array_keys($this->cacheManager->getAvailableTypes()) as $typeCode) {
            try {
                $this->cacheManager->clean([$typeCode]);
                $message = sprintf('Flushed: %s', $typeCode);
                $output->writeln($message);
                $details[] = $message;
            } catch (Throwable $exception) {
                $message = sprintf('Failed: %s (%s)', $typeCode, $exception->getMessage());
                $output->writeln($message);
                $details[] = $message;
                $hasFailure = true;
            }
        }

        return new ChaosActionResult(
            $this->getCode(),
            $hasFailure ? 'Cache flush completed with failures' : 'Flushed all cache types successfully',
            $details,
            !$hasFailure
        );
    }
}
