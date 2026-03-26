<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransferIngestionTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'EVT-1000',
            'station_id' => 1,
            'amount' => 125.50,
            'status' => 'approved',
            'created_at' => '2026-03-25T10:15:30Z',
        ], $overrides);
    }

    public function test_batch_insert_returns_correct_inserted_and_duplicates(): void
    {
        $payload = [
            'events' => [
                $this->event(['event_id' => 'EVT-1', 'station_id' => 1, 'amount' => 10, 'status' => 'approved']),
                $this->event(['event_id' => 'EVT-2', 'station_id' => 1, 'amount' => 5, 'status' => 'pending']),
                // Duplicate within payload (should be ignored; first wins)
                $this->event(['event_id' => 'EVT-2', 'station_id' => 1, 'amount' => 999, 'status' => 'approved']),
            ],
        ];

        $res = $this->postJson('/api/transfers', $payload);

        $res->assertStatus(200)->assertJson([
            'inserted' => 2,
            'duplicates' => 1,
        ]);

        $this->assertSame(2, (int) DB::table('transfer_events')->count());
    }

    public function test_duplicate_event_across_requests_does_not_change_totals(): void
    {
        $this->postJson('/api/transfers', [
            'events' => [
                $this->event(['event_id' => 'EVT-10', 'station_id' => 7, 'amount' => 10, 'status' => 'approved']),
            ],
        ])->assertStatus(200)->assertJson([
            'inserted' => 1,
            'duplicates' => 0,
        ]);

        // Same event_id with different data in a later request must not affect totals (DB unique key + insertOrIgnore)
        $this->postJson('/api/transfers', [
            'events' => [
                $this->event(['event_id' => 'EVT-10', 'station_id' => 7, 'amount' => 999, 'status' => 'approved']),
            ],
        ])->assertStatus(200)->assertJson([
            'inserted' => 0,
            'duplicates' => 1,
        ]);

        $this->getJson('/api/stations/7/summary')
            ->assertStatus(200)
            ->assertJson([
                'station_id' => '7',
                'total_approved_amount' => 10.00,
                'events_count' => 1,
            ]);
    }

    public function test_out_of_order_arrival_produces_same_totals(): void
    {
        $events = [
            $this->event(['event_id' => 'EVT-A', 'station_id' => 3, 'amount' => 10, 'status' => 'approved']),
            $this->event(['event_id' => 'EVT-B', 'station_id' => 3, 'amount' => 2.25, 'status' => 'approved']),
            $this->event(['event_id' => 'EVT-C', 'station_id' => 3, 'amount' => 5, 'status' => 'pending']),
        ];

        $this->postJson('/api/transfers', ['events' => $events])
            ->assertStatus(200)
            ->assertJson(['inserted' => 3, 'duplicates' => 0]);

        $s1 = $this->getJson('/api/stations/3/summary')->assertStatus(200)->json();

        // Send same event_ids again but shuffled; should not change summary
        $shuffled = [$events[2], $events[0], $events[1]];
        $this->postJson('/api/transfers', ['events' => $shuffled])
            ->assertStatus(200)
            ->assertJson(['inserted' => 0, 'duplicates' => 3]);

        $s2 = $this->getJson('/api/stations/3/summary')->assertStatus(200)->json();

        $this->assertSame($s1, $s2);
        $this->assertSame(12.25, (float) $s2['total_approved_amount']);
        $this->assertSame(3, (int) $s2['events_count']);
    }

    public function test_concurrent_ingestion_same_ids_does_not_double_count(): void
    {
        $batch = [
            $this->event(['event_id' => 'EVT-X', 'station_id' => 9, 'amount' => 1, 'status' => 'approved']),
            $this->event(['event_id' => 'EVT-Y', 'station_id' => 9, 'amount' => 2, 'status' => 'approved']),
        ];

        // Simulate two near-simultaneous ingestions (sequential in tests; DB constraint is the concurrency control)
        $this->postJson('/api/transfers', ['events' => $batch])
            ->assertStatus(200)
            ->assertJson(['inserted' => 2, 'duplicates' => 0]);

        $this->postJson('/api/transfers', ['events' => $batch])
            ->assertStatus(200)
            ->assertJson(['inserted' => 0, 'duplicates' => 2]);

        $this->getJson('/api/stations/9/summary')
            ->assertStatus(200)
            ->assertJson([
                'station_id' => '9',
                'total_approved_amount' => 3.00,
                'events_count' => 2,
            ]);
    }

    public function test_summary_endpoint_correctness_per_station(): void
    {
        $this->postJson('/api/transfers', [
            'events' => [
                $this->event(['event_id' => 'EVT-S1', 'station_id' => 1, 'amount' => 10, 'status' => 'approved']),
                $this->event(['event_id' => 'EVT-S2', 'station_id' => 1, 'amount' => 5, 'status' => 'pending']),
                $this->event(['event_id' => 'EVT-S3', 'station_id' => 2, 'amount' => 7.5, 'status' => 'approved']),
            ],
        ])->assertStatus(200)->assertJson(['inserted' => 3, 'duplicates' => 0]);

        $this->getJson('/api/stations/1/summary')
            ->assertStatus(200)
            ->assertJson([
                'station_id' => '1',
                'total_approved_amount' => 10.00,
                'events_count' => 2,
            ]);

        $this->getJson('/api/stations/2/summary')
            ->assertStatus(200)
            ->assertJson([
                'station_id' => '2',
                'total_approved_amount' => 7.50,
                'events_count' => 1,
            ]);
    }

    public function test_validation_failure_is_fail_fast_and_inserts_nothing(): void
    {
        $res = $this->postJson('/api/transfers', [
            'events' => [
                $this->event(['event_id' => 'EVT-OK', 'station_id' => 1, 'amount' => 1, 'status' => 'approved']),
                // Invalid: station_id missing
                [
                    'event_id' => 'EVT-BAD',
                    'amount' => 2,
                    'status' => 'approved',
                    'created_at' => '2026-03-25T10:15:30Z',
                ],
            ],
        ]);

        $res->assertStatus(400)
            ->assertJsonPath('message', 'Invalid payload.')
            ->assertJsonStructure(['errors']);

        $this->assertSame(0, (int) DB::table('transfer_events')->count());
    }
}

