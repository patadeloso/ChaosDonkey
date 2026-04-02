<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model\Probe;

class ProbeOutputFormatter
{
    /**
     * @var array<string, int>
     */
    private const SEVERITY_RANKING = [
        'warn' => 4,
        'unavailable' => 3,
        'unknown' => 2,
        'ok' => 1,
    ];

    public function formatSummary(ProbeSnapshot $snapshot): string
    {
        return sprintf(
            'Probe[%s] status=%s msg="%s"',
            $snapshot->getProbeCode(),
            $snapshot->getStatus(),
            $this->normalizeMessage($snapshot->getSummary())
        );
    }

    /**
     * @return string
     */
    public function formatTopDetails(ProbeSnapshot $snapshot): string
    {
        $details = $snapshot->getDetails();

        if (!$snapshot->isPreserveDetailOrder()) {
            usort($details, [$this, 'compareDetails']);
        }

        $lines = [];

        foreach (array_slice($details, 0, 5) as $detail) {
            $lines[] = $this->formatDetail($snapshot->getProbeCode(), $detail);
        }

        return implode("\n", $lines);
    }

    public function formatDetail(string $probeCode, ProbeDetailRow $detail): string
    {
        return sprintf(
            'ProbeDetail[%s] subsystem=%s item=%s status=%s value="%s"',
            $probeCode,
            $detail->getSubsystem(),
            $detail->getItem(),
            $detail->getStatus(),
            $this->normalizeMessage($detail->getMessage())
        );
    }

    public function formatLines(ProbeSnapshot $snapshot): string
    {
        $topDetails = $this->formatTopDetails($snapshot);

        if ($topDetails === '') {
            return $this->formatSummary($snapshot);
        }

        return $this->formatSummary($snapshot) . "\n" . $topDetails;
    }

    private function severityRank(string $status): int
    {
        return self::SEVERITY_RANKING[strtolower($status)] ?? 0;
    }

    private function compareDetails(ProbeDetailRow $a, ProbeDetailRow $b): int
    {
        $severityCompare = $this->severityRank($b->getStatus()) <=> $this->severityRank($a->getStatus());

        if ($severityCompare !== 0) {
            return $severityCompare;
        }

        $subsystemCompare = $a->getSubsystem() <=> $b->getSubsystem();
        if ($subsystemCompare !== 0) {
            return $subsystemCompare;
        }

        return $a->getItem() <=> $b->getItem();
    }

    private function normalizeMessage(string $message): string
    {
        $normalized = str_replace("\r\n", "\n", $message);
        $normalized = str_replace("\r", "\n", $normalized);

        $replacements = [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\t" => '\\t',
        ];

        return strtr($normalized, $replacements);
    }
}
