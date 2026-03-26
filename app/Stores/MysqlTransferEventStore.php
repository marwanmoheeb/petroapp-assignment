<?php

namespace App\Stores;

use App\Contracts\TransferEventStoreInterface;
use Illuminate\Support\Facades\DB;

class MysqlTransferEventStore implements TransferEventStoreInterface
{
    public function saveBatch(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        // Use query builder for cross-db compatibility (MySQL + SQLite in tests).
        return (int) DB::table('transfer_events')->insertOrIgnore($rows);
    }
}

