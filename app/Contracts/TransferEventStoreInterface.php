<?php

namespace App\Contracts;

interface TransferEventStoreInterface
{
    /**
     * Save a batch of *unique* transfer events (idempotency is expected to be
     * enforced by `event_id` at the storage layer).
     *
     * @param array<int, array{
     *   event_id:string,
     *   station_id:int,
     *   amount:string,
     *   status:string,
     *   created_at:string
     * }> $rows
     */
    public function saveBatch(array $rows): int;
}

