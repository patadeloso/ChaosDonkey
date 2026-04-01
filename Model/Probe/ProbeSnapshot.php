<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model\Probe;

class ProbeSnapshot
{
    /**
     * @param list<ProbeDetailRow> $details
     */
    public function __construct(
        private string $probeCode,
        private string $status,
        private string $summary,
        private array $details,
        private bool $preserveDetailOrder = false
    ) {
    }

    public function getProbeCode(): string
    {
        return $this->probeCode;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * @return list<ProbeDetailRow>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function isPreserveDetailOrder(): bool
    {
        return $this->preserveDetailOrder;
    }
}
