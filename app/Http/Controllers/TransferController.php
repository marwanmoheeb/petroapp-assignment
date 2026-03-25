<?php

namespace App\Http\Controllers;

use App\Contracts\TransferEventStoreInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransferController extends Controller
{
    public function __construct(
        private TransferEventStoreInterface $store,
    ) {
    }

    public function store(Request $request)
    {
        $payload = $request->json()->all();

        if (!is_array($payload)) {
            return response()->json([
                'message' => 'Invalid JSON payload.',
                'errors' => [
                    'body' => 'Request body must be valid JSON.',
                ],
            ], 400);
        }

        $validator = Validator::make($payload, [
            'events' => ['required', 'array'],
            'events.*.event_id' => [
                'required',
                'string',
                'max:64',
                function (string $attribute, $value, \Closure $fail): void {
                    $eventId = trim((string) $value);
                    if ($eventId === '') {
                        $fail('event_id must be a non-empty string.');
                    }
                },
            ],
            'events.*.station_id' => ['required', 'integer', 'min:0'],
            'events.*.amount' => ['required', 'numeric', 'min:0'],
            'events.*.status' => [
                'required',
                'string',
                'max:100',
                function (string $attribute, $value, \Closure $fail): void {
                    $status = trim((string) $value);
                    if ($status === '') {
                        $fail('status must be a non-empty string.');
                    }
                },
            ],
            'events.*.created_at' => [
                'required',
                'string',
                function (string $attribute, $value, \Closure $fail): void {
                    $raw = trim((string) $value);

                    try {
                        Carbon::parse($raw)->utc();
                    } catch (\Throwable $e) {
                        $fail('created_at must be parseable as ISO8601.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid payload.',
                'errors' => $validator->errors()->toArray(),
            ], 400);
        }

       
        $events = $payload['events'];
        $uniqueByEventId = [];
        $totalInput = 0;

        foreach ($events as $i => $event) {
            $totalInput++;
            $eventId = trim((string) $event['event_id']);
            $status = trim((string) $event['status']);

            $normalized = [
                'event_id' => $eventId,
                'station_id' => (int) $event['station_id'],
                'amount' => number_format((float) $event['amount'], 2, '.', ''),
                'status' => $status,
                'created_at' => Carbon::parse(trim((string) $event['created_at']))->utc()->format('Y-m-d H:i:s'),
            ];

            if (array_key_exists($eventId, $uniqueByEventId)) {
                if ($uniqueByEventId[$eventId] !== $normalized) {
                    return response()->json([
                        'message' => 'Invalid payload.',
                        'errors' => [
                            "events.$i.event_id" => ['Duplicate event_id in the same request must have identical data.'],
                        ],
                    ], 400);
                }
            } else {
                $uniqueByEventId[$eventId] = $normalized;
            }

        }

        $uniqueRows = array_values($uniqueByEventId);
        $inserted = $this->store->saveBatch($uniqueRows);

        $duplicates = max(0, $totalInput - $inserted);

        return response()->json([
            'inserted' => $inserted,
            'duplicates' => $duplicates,
        ], 200);
    }
}

