<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model;

use ShaunMcManus\ChaosDonkey\Api\ChaosActionInterface;

class ActionPool
{
    /**
     * @param array<string, ChaosActionInterface> $actions
     */
    public function __construct(private array $actions)
    {
    }

    public function get(string $code): ?ChaosActionInterface
    {
        return $this->actions[$code] ?? null;
    }
}
