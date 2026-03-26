<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class StationController extends Controller
{
  
    public function summary(string|int $station_id): JsonResponse
    {
        $stationId = (int) $station_id;

        $row = DB::table('transfer_events')
            ->where('station_id', $stationId)
            ->selectRaw(
                'COUNT(*) as events_count, ' .
                'COALESCE(SUM(CASE WHEN LOWER(status) = ? THEN amount ELSE 0 END), 0) as total_approved_amount',
                ['approved']
            )
            ->first();

        $eventsCount = (int) ($row?->events_count ?? 0);
        $totalApprovedAmount = (float) ($row?->total_approved_amount ?? 0);

        return response()->json([
            'station_id' => (string) $station_id,
            'total_approved_amount' => round($totalApprovedAmount, 2),
            'events_count' => $eventsCount,
        ], 200);
    }
}

