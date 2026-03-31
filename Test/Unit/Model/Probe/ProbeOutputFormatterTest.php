<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Test\Unit\Model\Probe;

use PHPUnit\Framework\TestCase;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeDetailRow;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeOutputFormatter;
use ShaunMcManus\ChaosDonkey\Model\Probe\ProbeSnapshot;

class ProbeOutputFormatterTest extends TestCase
{
    private ProbeOutputFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ProbeOutputFormatter();
    }

    public function testFormatSummary(): void
    {
        $snapshot = new ProbeSnapshot(
            'db_ping',
            'warn',
            'Database response too slow'
        );

        self::assertSame(
            'Probe[db_ping] status=warn msg="Database response too slow"',
            $this->formatter->formatSummary($snapshot)
        );
    }

    public function testFormatSummaryEscapesQuotesAndNewlines(): void
    {
        $snapshot = new ProbeSnapshot(
            'db_ping',
            'warn',
            'Service "endpoint"' . "\n" . 'status 500'
        );

        $expected = 'Probe[db_ping] status=warn msg=' . json_encode(
            'Service "endpoint"' . "\n" . 'status 500',
            JSON_UNESCAPED_UNICODE
        );

        self::assertSame(
            $expected,
            $this->formatter->formatSummary($snapshot)
        );
    }

    public function testFormatTopDetailsSortsBySeveritySubsystemItemWithCap(): void
    {
        $snapshot = new ProbeSnapshot(
            'system_health',
            'warn',
            'System has issues',
            [
                new ProbeDetailRow('storage', 'free', 'ok', 'has space'),
                new ProbeDetailRow('cache', 'status', 'warn', 'flush needed'),
                new ProbeDetailRow('index', 'status', 'unavailable', 'indexer missing'),
                new ProbeDetailRow('storage', 'iops', 'warn', 'slow reads'),
                new ProbeDetailRow('cache', 'pool', 'unknown', 'warmup needed'),
                new ProbeDetailRow('search', 'status', 'ok', 'ok status'),
            ]
        );

        self::assertSame(
            "Probe[system_health] subsystem=cache item=status status=warn msg=\"flush needed\"\n"
            . "Probe[system_health] subsystem=storage item=iops status=warn msg=\"slow reads\"\n"
            . "Probe[system_health] subsystem=index item=status status=unavailable msg=\"indexer missing\"\n"
            . "Probe[system_health] subsystem=cache item=pool status=unknown msg=\"warmup needed\"\n"
            . "Probe[system_health] subsystem=search item=status status=ok msg=\"ok status\"",
            $this->formatter->formatTopDetails($snapshot)
        );
    }

    public function testFormatDetailCanonicalEnvelope(): void
    {
        $detail = new ProbeDetailRow('filesystem', 'readable', 'ok', 'All mounts readable');

        self::assertSame(
            'Probe[storage_readiness] subsystem=filesystem item=readable status=ok msg="All mounts readable"',
            $this->formatter->formatDetail('storage_readiness', $detail)
        );
    }

    public function testFormatDetailCanonicalEnvelopeEscapesQuotesAndNewlines(): void
    {
        $detail = new ProbeDetailRow('filesystem', 'readable', 'ok', 'Check "permissions"' . "\n" . 'for /var');

        $expected = 'Probe[storage_readiness] subsystem=filesystem item=readable status=ok msg=' . json_encode(
            'Check "permissions"' . "\n" . 'for /var',
            JSON_UNESCAPED_UNICODE
        );

        $expected = str_replace('\\/', '/', $expected);

        self::assertSame(
            $expected,
            $this->formatter->formatDetail('storage_readiness', $detail)
        );
    }

    public function testFormatLinesBeginsWithSummary(): void
    {
        $snapshot = new ProbeSnapshot(
            'cache',
            'warn',
            'Some cache components need attention',
            [
                new ProbeDetailRow('cache', 'frontend', 'warn', 'flush queue full'),
                new ProbeDetailRow('database', 'replica', 'ok', 'lagging but within range'),
            ]
        );

        self::assertSame(
            "Probe[cache] status=warn msg=\"Some cache components need attention\"\n"
            . "Probe[cache] subsystem=cache item=frontend status=warn msg=\"flush queue full\"\n"
            . "Probe[cache] subsystem=database item=replica status=ok msg=\"lagging but within range\"",
            $this->formatter->formatLines($snapshot)
        );
    }

    public function testFormatTopDetailsRespectsPreserveIncomingOrderWhenRequested(): void
    {
        $snapshot = new ProbeSnapshot(
            'cache',
            'warn',
            'some details',
            [
                new ProbeDetailRow('search', 'indexer', 'ok', 'first detail'),
                new ProbeDetailRow('cache', 'backend', 'warn', 'second detail'),
                new ProbeDetailRow('database', 'connection', 'unknown', 'third detail'),
                new ProbeDetailRow('queue', 'worker', 'unavailable', 'fourth detail'),
                new ProbeDetailRow('search', 'query', 'warn', 'fifth detail'),
                new ProbeDetailRow('other', 'item', 'ok', 'sixth detail'),
            ],
            true
        );

        self::assertSame(
            "Probe[cache] subsystem=search item=indexer status=ok msg=\"first detail\"\n"
            . "Probe[cache] subsystem=cache item=backend status=warn msg=\"second detail\"\n"
            . "Probe[cache] subsystem=database item=connection status=unknown msg=\"third detail\"\n"
            . "Probe[cache] subsystem=queue item=worker status=unavailable msg=\"fourth detail\"\n"
            . "Probe[cache] subsystem=search item=query status=warn msg=\"fifth detail\"",
            $this->formatter->formatTopDetails($snapshot)
        );
    }

    public function testFormatLinesWithNoDetailsReturnsSummaryOnly(): void
    {
        $snapshot = new ProbeSnapshot(
            'cache',
            'ok',
            'All cache checks passed'
        );

        self::assertSame(
            'Probe[cache] status=ok msg="All cache checks passed"',
            $this->formatter->formatLines($snapshot)
        );
    }
}
