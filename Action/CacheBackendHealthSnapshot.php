<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Action;

use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;
use ShaunMcManus\ChaosDonkey\Api\ChaosActionInterface;
use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeDetailRow;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeOutputFormatter;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeSnapshot;
use Throwable;
use Symfony\Component\Console\Output\OutputInterface;

class CacheBackendHealthSnapshot implements ChaosActionInterface
{
    private const UNKNOWN_SUMMARY = 'cache snapshot unavailable';

    public function __construct(
        private TypeListInterface $typeList,
        private Pool $frontendPool,
        private ProbeOutputFormatter $probeOutputFormatter,
    ) {
    }

    public function getCode(): string
    {
        return 'cache_backend_health_snapshot';
    }

    public function execute(OutputInterface $output): ChaosActionResult
    {
        $snapshot = $this->collectSnapshot();

        $output->writeln($this->probeOutputFormatter->formatLines($snapshot));

        return new ChaosActionResult(
            $this->getCode(),
            '',
            [],
            $snapshot->getStatus() !== 'unknown'
        );
    }

    private function collectSnapshot(): ProbeSnapshot
    {
        try {
            $types = $this->typeList->getTypes();
        } catch (Throwable $exception) {
            return new ProbeSnapshot(
                $this->getCode(),
                'unknown',
                self::UNKNOWN_SUMMARY,
                [
                    new ProbeDetailRow('cache', 'metadata', 'unknown', 'unavailable'),
                ]
            );
        }

        $details = [];
        $enabledTypeCount = 0;

        foreach ($types as $typeCode => $metadata) {
            $isEnabled = $this->isEnabledFromMetadata($metadata);
            if ($isEnabled) {
                $enabledTypeCount++;
            }

            $details[] = new ProbeDetailRow(
                'cache',
                (string) $typeCode,
                'ok',
                sprintf('enabled=%s', $isEnabled ? 'true' : 'false')
            );
        }

        try {
            $defaultFrontend = $this->frontendPool->get('default');
        } catch (Throwable $exception) {
            return $this->resolveBackendUnavailable($details);
        }

        if ($defaultFrontend === null) {
            return $this->resolveBackendUnavailable($details);
        }

        try {
            $backend = $defaultFrontend->getBackend();
            $adapter = $this->sanitizeAdapterLabel(get_class($backend));

            $details[] = new ProbeDetailRow('cache_backend', 'default_frontend', 'ok', $adapter);

        return new ProbeSnapshot(
            $this->getCode(),
            'ok',
            sprintf(
                    '%d cache types, %d enabled, backend adapter=%s',
                    count($types),
                    $enabledTypeCount,
                    $adapter
                ),
                $details
            );
        } catch (Throwable $exception) {
            $details[] = new ProbeDetailRow('cache_backend', 'default_frontend', 'warn', 'resolution_failed');

            return new ProbeSnapshot(
                $this->getCode(),
                'warn',
                sprintf(
                    '%d cache types, %d enabled, backend adapter resolution degraded',
                    count($types),
                    $enabledTypeCount
                ),
                $details
            );
        }
    }

    private function resolveBackendUnavailable(array $details): ProbeSnapshot
    {
        $details[] = new ProbeDetailRow('cache_backend', 'default_frontend', 'unknown', 'unavailable');
        return new ProbeSnapshot(
            $this->getCode(),
            'unknown',
            self::UNKNOWN_SUMMARY,
            $details
        );
    }

    private function isEnabledFromMetadata(mixed $metadata): bool
    {
        if (!is_array($metadata) || !array_key_exists('status', $metadata)) {
            return false;
        }

        return (int) $metadata['status'] === 1;
    }

    private function sanitizeAdapterLabel(string $adapterClass): string
    {
        $backslashPosition = strrpos($adapterClass, '\\');
        $slashPosition = strrpos($adapterClass, '/');
        $separator = max(
            $backslashPosition === false ? -1 : $backslashPosition,
            $slashPosition === false ? -1 : $slashPosition
        );

        if ($separator < 0) {
            $basename = $adapterClass;
        } else {
            $basename = substr($adapterClass, $separator + 1);
        }

        $normalized = strtolower((string) $basename);
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized) ?: '';

        return trim($normalized, '_') === '' ? 'unavailable' : trim($normalized, '_');
    }
}
