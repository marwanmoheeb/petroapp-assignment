<?php

namespace App\Stores;

use App\Contracts\TransferEventStoreInterface;
use Illuminate\Support\Facades\DB;

class MysqlTransferEventStore implements TransferEventStoreInterface
{
    public function saveBatch(array $rows): int
    {
        return $this->insertIgnoreAndCountAll($rows);
    }

    private function insertIgnoreAndCountAll(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        [$sql, $bindings] = $this->buildInsertIgnoreQuery($rows);

        return (int) DB::connection()->affectingStatement($sql, $bindings);
    }

    
    private function buildInsertIgnoreQuery(array $rows): array
    {
        $table = 'transfer_events';
        $columns = ['event_id', 'station_id', 'amount', 'status', 'created_at'];

        $placeholdersPerRow = '(?, ?, ?, ?, ?)';
        $valuesSql = implode(',', array_fill(0, count($rows), $placeholdersPerRow));

        $bindings = [];
        foreach ($rows as $row) {
            $bindings[] = $row['event_id'];
            $bindings[] = $row['station_id'];
            $bindings[] = $row['amount'];
            $bindings[] = $row['status'];
            $bindings[] = $row['created_at'];
        }

        $sql = sprintf(
            'INSERT IGNORE INTO %s (%s) VALUES %s',
            $table,
            implode(',', $columns),
            $valuesSql
        );

        return [$sql, $bindings];
    }
}

