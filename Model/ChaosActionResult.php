<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model;

class ChaosActionResult
{
    /**
     * @param list<string> $details
     */
    public function __construct(
        private string $outcomeCode,
        private string $summary,
        private array $details = [],
        private bool $success = true
    ) {
    }

    public function getOutcomeCode(): string
    {
        return $this->outcomeCode;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * @return list<string>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}
