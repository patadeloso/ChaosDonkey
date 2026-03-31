<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model\Probe;

class ProbeDetailRow
{
    public function __construct(
        private string $subsystem,
        private string $item,
        private string $status,
        private string $message
    ) {
    }

    public function getSubsystem(): string
    {
        return $this->subsystem;
    }

    public function getItem(): string
    {
        return $this->item;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
