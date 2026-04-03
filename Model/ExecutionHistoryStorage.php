<?php
declare(strict_types=1);

namespace ShaunMcManus\ChaosDonkey\Model;

use Magento\Framework\App\ResourceConnection;

class ExecutionHistoryStorage
{
    private const TABLE_NAME = 'shaunmcmanus_chaosdonkey_execution_history';

    private const SELECT_COLUMNS = 'history_id, executed_at, source, kick, outcome, configured_profile, effective_profile, fallback_reason';

    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function append(
        string $executedAt,
        string $source,
        int $kick,
        string $outcome,
        string $configuredProfile,
        string $effectiveProfile,
        ?string $fallbackReason
    ): void {
        $this->resourceConnection
            ->getConnection()
            ->insert($this->resourceConnection->getTableName(self::TABLE_NAME), [
                'executed_at' => $executedAt,
                'source' => $source,
                'kick' => $kick,
                'outcome' => $outcome,
                'configured_profile' => $configuredProfile,
                'effective_profile' => $effectiveProfile,
                'fallback_reason' => $fallbackReason,
            ]);
    }

    public function getRecent(int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);
        $sql = sprintf(
            'SELECT %s FROM %s ORDER BY history_id DESC LIMIT %d',
            self::SELECT_COLUMNS,
            $tableName,
            $limit
        );

        return $this->resourceConnection->getConnection()->fetchAll($sql);
    }

    public function getLatestForSource(string $source): ?array
    {
        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);
        $sql = sprintf(
            'SELECT %s FROM %s WHERE source = :source ORDER BY history_id DESC LIMIT 1',
            self::SELECT_COLUMNS,
            $tableName
        );

        $row = $this->resourceConnection->getConnection()->fetchRow($sql, ['source' => $source]);

        return is_array($row) ? $row : null;
    }
}
