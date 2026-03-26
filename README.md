# PetroApp Assignment

### Tech Stack + Requirements
- PHP `8.2+`
- Laravel `^12.0`
- Database: MySQL or SQLite
- PHPUnit (for `php artisan test`)

### How to run locally
1. Install dependencies
   - `composer install`
2. Configure environment
   - Copy `.env.example` to `.env`
   - Set DB configuration:
     - For SQLite: `DB_CONNECTION=sqlite` and `DB_DATABASE=database/database.sqlite`
     - For MySQL: `DB_CONNECTION=mysql` plus `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
3. Run migrations
   - `php artisan migrate`
4. Start the server
   - `php artisan serve`



### How to run with Docker
Prerequisites:
- Install Docker Desktop (Windows/macOS) or Docker Engine (Linux).
- Make sure the `docker` command works in your terminal: `docker --version`

Start the app (build + run containers):
- `docker compose up --build -d`

Initialize Laravel (first run):
- `docker compose exec app php artisan key:generate`
- `docker compose exec app php artisan migrate --force`

Use the API:
- Base URL: `http://localhost:8000`





### How to run tests
- `composer install`
- `php artisan test` (or `composer test`)

### API Examples (curl)
#### 1. Create/Idempotently insert transfers
`POST /api/transfers`

Request body:
```json
{
  "events": [
    {
      "event_id": "EVT-1000",
      "station_id": 1,
      "amount": 125.5,
      "status": "approved",
      "created_at": "2026-03-25T10:15:30Z"
    },
    {
      "event_id": "EVT-1001",
      "station_id": 2,
      "amount": 10,
      "status": "pending",
      "created_at": "2026-03-25T11:20:00Z"
    }
  ]
}
```

curl:
```bash
curl -X POST "http://localhost:8000/api/transfers" \
  -H "Content-Type: application/json" \
  -d '{
    "events": [
      {
        "event_id": "EVT-1000",
        "station_id": 1,
        "amount": 125.5,
        "status": "approved",
        "created_at": "2026-03-25T10:15:30Z"
      }
    ]
  }'
```

Response:
```json
{ "inserted": 7, "duplicates": 3 }
```

Validation behavior:
- The server validates the entire batch and returns `400` if any event is invalid (fail-fast for the batch).

#### 2. Station summary
`GET /api/stations/{station_id}/summary`

curl:
```bash
curl "http://localhost:8000/api/stations/1/summary"
```

Response:
```json
{
  "station_id": "1",
  "total_approved_amount": 450.25,
  "events_count": 12
}
```

Chosen Strategies:
- fail-fast in the sync api
- `events_count` counts all events for the station (all statuses).


### Design Notes
#### Idempotency strategy
- Storage idempotency is enforced by a unique primary key on `event_id` (see the `transfer_events` migration).
- Writes are performed with a single multi-row insert using MySQL `INSERT IGNORE`, so duplicate `event_id` rows are ignored by the database.

#### Concurrency strategy
- Concurrency control is delegated to the database unique constraint on `event_id`.
- Under concurrent requests, the first successful insert wins; subsequent inserts for the same `event_id` are ignored atomically by the database (no application-level locking required).

#### Tradeoffs

- The `inserted`/`duplicates` counters rely on database insert behavior (`affectedStatement` result for `INSERT IGNORE` can be driver-specific).
- “Approved” matching is handled case-insensitively for the station summary (and only affects the summary sum, not the insert itself).

