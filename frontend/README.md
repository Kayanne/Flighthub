# Trip Builder (PHP + Laravel + React)

Web app + API to **search**, **create**, and **navigate** trips (**one-way** / **round-trip** / **multi-city**) with **timezone-safe timestamps**.

---

## Prerequisites

- Docker Desktop (Windows / Mac / Linux)
- Git

---

## Quick start (local)

### 1) Start containers

```bash
docker compose up -d --build
```

### 2) Install backend deps + init permissions

```bash
docker compose exec php sh -lc "chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache"
```

### 3) Open the app

- Frontend (Vite dev server): http://localhost:5173
- API (Laravel): http://localhost:8080

---

## API (local)

Base URL: http://localhost:8080

### Input rules

`origin` and `destination` accept:

- Airport code (ex: `YUL`)
- City code (ex: `YMQ`) → expands to all airports having `city_code=YMQ`

### Endpoints

- `GET /api/airlines`
- `GET /api/airports?query=YUL` (also accepts partial text)
- `POST /api/trips/search` (search proposals, **not stored**)
- `POST /api/trips` (create a trip from selected flights)
- `GET /api/trips?sort=created_at&dir=desc&page=1&per_page=10`
- `GET /api/trips/{id}`

---

## Sorting & pagination

### Search (POST `/api/trips/search`)

Parameters:
- `sort`: `price` | `departure_at`
- `page`
- `per_page`

### Trips listing (GET `/api/trips`)

Parameters:
- `sort`: `created_at` | `price` | `departure_at`
- `dir`: `asc` | `desc`
- `page`
- `per_page`

---

## PowerShell examples

### 1) List airlines

```powershell
Invoke-RestMethod "http://localhost:8080/api/airlines" | ConvertTo-Json -Depth 10
```

### 2) Search one-way (airport codes)

```powershell
$body = @{ type="one_way"; origin="YUL"; destination="YVR"; departure_date="2026-01-10" } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://localhost:8080/api/trips/search" -ContentType "application/json" -Body $body | ConvertTo-Json -Depth 30
```

### 3) Search one-way (city code origin)

```powershell
$body = @{ type="one_way"; origin="YMQ"; destination="YVR"; departure_date="2026-01-10" } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://localhost:8080/api/trips/search" -ContentType "application/json" -Body $body | ConvertTo-Json -Depth 30
```

### 4) Search round-trip

```powershell
$body = @{ type="round_trip"; origin="YUL"; destination="YVR"; departure_date="2026-01-10"; return_date="2026-01-15" } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://localhost:8080/api/trips/search" -ContentType "application/json" -Body $body | ConvertTo-Json -Depth 30
```

### 5) Create a trip (persist)

```powershell
$body = @{
  type = "round_trip"
  segments = @(
    @{ flight_id = 1; departure_date = "2026-01-10" },
    @{ flight_id = 2; departure_date = "2026-01-15" }
  )
} | ConvertTo-Json -Depth 10

Invoke-RestMethod -Method Post -Uri "http://localhost:8080/api/trips" -ContentType "application/json" -Body $body | ConvertTo-Json -Depth 30
```

### 6) List trips

```powershell
Invoke-RestMethod "http://localhost:8080/api/trips?sort=created_at&dir=desc" | ConvertTo-Json -Depth 30
```

---

## Reset / wipe database

```bash
docker compose exec php php artisan migrate:fresh --seed
```

---

## Notes

- Currency is neutral (**NTR**) as per the assignment requirements.
- Times are computed using airport timezones and returned **both in local time and UTC**.
