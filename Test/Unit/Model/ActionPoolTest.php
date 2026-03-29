<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Api\ChaosActionInterface;
use ShaunMcManus\ChaosDonkey\Model\ActionPool;

class ActionPoolTest extends TestCase
{
    public function testItReturnsActionForKnownCode(): void
    {
        $reindexAction = $this->createStub(ChaosActionInterface::class);

        $pool = new ActionPool([
            'reindex_all' => $reindexAction,
        ]);

        self::assertSame($reindexAction, $pool->get('reindex_all'));
    }

    public function testItReturnsNullForUnknownCode(): void
    {
        $reindexAction = $this->createStub(ChaosActionInterface::class);

        $pool = new ActionPool([
            'reindex_all' => $reindexAction,
        ]);

        self::assertNull($pool->get('not_registered'));
    }
}
