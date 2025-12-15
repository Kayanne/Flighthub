# Trip Builder (PHP + Laravel + React)

Web app + API to **search**, **create**, and **navigate** trips (**one-way** / **round-trip** / **multi-city**) with **timezone-safe timestamps**.

---

## Prerequisites

- Docker Desktop (Windows / Mac / Linux)
- Git

---

## Quick start (local)

### 1) Start containers

~~~bash
docker compose up -d --build
~~~

### 2) Install backend deps + init permissions

~~~bash
docker compose exec php sh -lc "chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache"
~~~

### 3) Open the app

- Frontend (Vite dev server): http://localhost:5173
- API (Laravel): http://localhost:8080

---

## API (local)

Base URL: http://localhost:8080

### Response shape

Most endpoints return a JSON envelope like:

- `data`: payload (array or object)
- `meta`: pagination metadata (when applicable)

### Input rules (origin / destination)

`origin` and `destination` accept:

- Airport code (ex: `YUL`)
- City code (ex: `YMQ`) → expands to all airports having `city_code=YMQ`

### Endpoints (overview)

- `GET /api/airlines`
- `GET /api/airports?query=YUL` (also accepts partial text)
- `POST /api/trips/search` (search proposals, **not stored**)
- `POST /api/trips` (create a trip from selected flights)
- `GET /api/trips?sort=created_at&dir=desc&page=1&per_page=10`
- `GET /api/trips/{id}`

---

## Sorting & pagination

### Search (POST `/api/trips/search`)

Supports:

- `sort`: `price` | `departure_at`
- `page`
- `per_page`

Notes:
- This endpoint returns *trip proposals* (not persisted).
- Pagination is useful when there are many candidate flights/proposals.

### Trips listing (GET `/api/trips`)

Supports:

- `sort`: `created_at` | `price` | `departure_at`
- `dir`: `asc` | `desc`
- `page`
- `per_page`

---

## API details

### GET `/api/airlines`

Returns the list of airlines.

### GET `/api/airports?query=...`

Search airports by:
- exact code (`YUL`)
- partial text (ex: `Montr`)

### POST `/api/trips/search`

Searches for *trip proposals*.

Common fields:
- `type`: `one_way` | `round_trip` | `multi_city`
- `sort`: `price` | `departure_at`
- `preferred_airline` (optional): airline code (ex: `AC`)
- `page` (optional)
- `per_page` (optional)

One-way body:
- `origin`
- `destination`
- `departure_date` (YYYY-MM-DD)

Round-trip body:
- `origin`
- `destination`
- `departure_date`
- `return_date` (YYYY-MM-DD)

Multi-city body:
- `legs`: array of `{ origin, destination, departure_date }`

### POST `/api/trips`

Creates (persists) a trip from selected flights.

Body:
- `type`: `one_way` | `round_trip` | `multi_city`
- `segments`: array of `{ flight_id, departure_date }`

Response includes:
- persisted trip info
- segments with computed timestamps (local + UTC) using airport timezones

### GET `/api/trips`

Returns saved trips list with pagination metadata.

### GET `/api/trips/{id}`

Returns a single saved trip.

---

## PowerShell examples

### 1) List airlines

~~~powershell
Invoke-RestMethod "http://localhost:8080/api/airlines" | ConvertTo-Json -Depth 10
~~~

### 2) Search one-way (airport codes)

~~~powershell
$body = @{ type="one_way"; origin="YUL"; destination="YVR"; departure_date="2026-01-10" } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://localhost:8080/api/trips/search" -ContentType "application/json" -Body $body | ConvertTo-Json -Depth 30
~~~

### 3) Search one-way (city code origin)

~~~powershell
$body = @{ type="one_way"; origin="YMQ"; destination="YVR"; departure_date="2026-01-10" } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://localhost:8080/api/trips/search" -ContentType "application/json" -Body $body | ConvertTo-Json -Depth 30
~~~

### 4) Search round-trip

~~~powershell
$body = @{ type="round_trip"; origin="YUL"; destination="YVR"; departure_date="2026-01-10"; return_date="2026-01-15" } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://localhost:8080/api/trips/search" -ContentType "application/json" -Body $body | ConvertTo-Json -Depth 30
~~~

### 5) Search multi-city

~~~powershell
$body = @{
  type = "multi_city"
  legs = @(
    @{ origin="YUL"; destination="YVR"; departure_date="2026-01-10" },
    @{ origin="YVR"; destination="YUL"; departure_date="2026-01-15" }
  )
  sort = "price"
} | ConvertTo-Json -Depth 10

Invoke-RestMethod -Method Post -Uri "http://localhost:8080/api/trips/search" -ContentType "application/json" -Body $body | ConvertTo-Json -Depth 30
~~~

### 6) Create a trip (persist)

~~~powershell
$body = @{
  type = "round_trip"
  segments = @(
    @{ flight_id = 1; departure_date = "2026-01-10" },
    @{ flight_id = 2; departure_date = "2026-01-15" }
  )
} | ConvertTo-Json -Depth 10

Invoke-RestMethod -Method Post -Uri "http://localhost:8080/api/trips" -ContentType "application/json" -Body $body | ConvertTo-Json -Depth 30
~~~

### 7) List trips

~~~powershell
Invoke-RestMethod "http://localhost:8080/api/trips?sort=created_at&dir=desc&page=1&per_page=10" | ConvertTo-Json -Depth 30
~~~

---

## Reset / wipe database

~~~bash
docker compose exec php php artisan migrate:fresh --seed
~~~

---
---

## Notes

- Currency is neutral (**NTR**) as per the assignment requirements.
- Times are computed using airport timezones and returned **both in local time and UTC**.
