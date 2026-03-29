<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Api;

use ShaunMcManus\ChaosDonkey\Model\ChaosActionResult;
use Symfony\Component\Console\Output\OutputInterface;

interface ChaosActionInterface
{
    public function getCode(): string;

    public function execute(OutputInterface $output): ChaosActionResult;
}
