<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model;

use Magento\Framework\App\Config\Storage\WriterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Model\Config;
use ShaunMcManus\ChaosDonkey\Model\StateWriter;

class StateWriterTest extends TestCase
{
    private WriterInterface&MockObject $writer;

    protected function setUp(): void
    {
        $this->writer = $this->createMock(WriterInterface::class);
    }

    public function testItPersistsLastRun(): void
    {
        $this->writer
            ->expects(self::once())
            ->method('save')
            ->with(Config::CONFIG_PATH_LAST_RUN, '2026-03-28 12:00:00');

        $stateWriter = new StateWriter($this->writer);

        $stateWriter->saveLastRun('2026-03-28 12:00:00');
    }

    public function testItPersistsLastKick(): void
    {
        $this->writer
            ->expects(self::once())
            ->method('save')
            ->with(Config::CONFIG_PATH_LAST_KICK, '20');

        $stateWriter = new StateWriter($this->writer);

        $stateWriter->saveLastKick(20);
    }

    public function testItPersistsLastOutcome(): void
    {
        $this->writer
            ->expects(self::once())
            ->method('save')
            ->with(Config::CONFIG_PATH_LAST_OUTCOME, 'critical_success');

        $stateWriter = new StateWriter($this->writer);

        $stateWriter->saveLastOutcome('critical_success');
    }
}
