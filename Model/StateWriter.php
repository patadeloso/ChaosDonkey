<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model;

use Magento\Framework\App\Config\Storage\WriterInterface;

class StateWriter
{
    private WriterInterface $writer;

    public function __construct(WriterInterface $writer)
    {
        $this->writer = $writer;
    }

    public function saveLastRun(string $timestamp): void
    {
        $this->writer->save(Config::CONFIG_PATH_LAST_RUN, $timestamp);
    }

    public function saveLastKick(int $kick): void
    {
        $this->writer->save(Config::CONFIG_PATH_LAST_KICK, (string) $kick);
    }

    public function saveLastOutcome(string $outcome): void
    {
        $this->writer->save(Config::CONFIG_PATH_LAST_OUTCOME, $outcome);
    }
}
