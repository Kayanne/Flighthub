<?php

namespace App\Http\Controllers\Api;

use App\Models\Airport;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\Flight;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TripController extends Controller
{

    private function resolveAirportCodes(string $codeOrCity): array
    {
        $codeOrCity = strtoupper(trim($codeOrCity));

        $airport = Airport::query()->where('code', $codeOrCity)->first();
        if ($airport) {
            return [$airport->code];
        }

        $codes = Airport::query()
            ->where('city_code', $codeOrCity)
            ->pluck('code')
            ->values()
            ->all();

        if (count($codes) > 0) {
            return $codes;
        }

        throw ValidationException::withMessages([
            'location' => ["Unknown airport or city code: {$codeOrCity}"],
        ]);
    }

    private function computeDepartureUtc(string $dateYmd, string $timeHm, string $tz): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', "{$dateYmd} {$timeHm}", $tz)->utc();
    }

    private function assertTripWindow(CarbonImmutable $depUtc, CarbonImmutable $nowUtc): void
    {
        $maxUtc = $nowUtc->addDays(365);

        if ($depUtc->lte($nowUtc)) {
            throw ValidationException::withMessages([
                'departure_date' => ['Trip must depart after creation time.'],
            ]);
        }

        if ($depUtc->gt($maxUtc)) {
            throw ValidationException::withMessages([
                'departure_date' => ['Trip must depart within 365 days after creation time.'],
            ]);
        }
    }
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sort' => ['nullable', Rule::in(['created_at', 'price', 'departure_at'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $sort = $validated['sort'] ?? 'created_at';
        $dir = $validated['dir'] ?? 'desc';

        $query = Trip::query();

        if ($sort === 'departure_at') {
            $query
                ->select('trips.*')
                ->join('trip_segments as ts1', function ($join) {
                    $join->on('ts1.trip_id', '=', 'trips.id')
                        ->where('ts1.segment_index', '=', 1);
                })
                ->orderBy('ts1.departure_at_utc', $dir);
        } elseif ($sort === 'price') {
            $query->orderBy('total_price', $dir);
        } else {
            $query->orderBy('created_at', $dir);
        }

        $paginator = $query->paginate(
            perPage: (int) ($validated['per_page'] ?? 10),
            page: (int) ($validated['page'] ?? 1)
        );

        $paginator->getCollection()->load([
            'segments.flight.airline',
            'segments.flight.departureAirport',
            'segments.flight.arrivalAirport',
        ]);

        return response()->json([
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'data' => $paginator->getCollection()->map(fn (Trip $t) => $this->tripToArray($t))->values(),
        ]);
    }

    public function show(Trip $trip): JsonResponse
    {
        $trip->load([
            'segments.flight.airline',
            'segments.flight.departureAirport',
            'segments.flight.arrivalAirport',
        ]);

        return response()->json(['data' => $this->tripToArray($trip)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['one_way', 'round_trip', 'multi_city'])],
            'segments' => ['required', 'array', 'min:1', 'max:5'],
            'segments.*.flight_id' => ['required', 'integer', 'exists:flights,id'],
            'segments.*.departure_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $type = $validated['type'];
        $segmentsInput = $validated['segments'];

        if ($type === 'one_way' && count($segmentsInput) !== 1) {
            return response()->json([
                'message' => 'one_way requires exactly 1 segment',
                'errors' => ['segments' => ['one_way requires exactly 1 segment']],
            ], 422);
        }

        if ($type === 'round_trip' && count($segmentsInput) !== 2) {
            return response()->json([
                'message' => 'round_trip requires exactly 2 segments',
                'errors' => ['segments' => ['round_trip requires exactly 2 segments']],
            ], 422);
        }

        if ($type === 'multi_city' && (count($segmentsInput) < 2 || count($segmentsInput) > 5)) {
            return response()->json([
                'message' => 'multi_city requires between 2 and 5 segments',
                'errors' => ['segments' => ['multi_city requires between 2 and 5 segments']],
            ], 422);
        }

        $flightIds = array_map(fn ($s) => (int) $s['flight_id'], $segmentsInput);

        $flights = Flight::query()
            ->with(['airline', 'departureAirport', 'arrivalAirport'])
            ->whereIn('id', $flightIds)
            ->get()
            ->keyBy('id');

        $nowUtc = CarbonImmutable::now('UTC');
        $maxUtc = $nowUtc->addDays(365);

        $computed = [];
        $total = 0.0;

        foreach ($segmentsInput as $idx => $seg) {
            $flight = $flights->get((int) $seg['flight_id']);
            if (!$flight) {
                return response()->json([
                    'message' => 'Invalid flight_id',
                    'errors' => ['segments' => ['Invalid flight_id']],
                ], 422);
            }

            $timing = $this->computeTiming($flight, $seg['departure_date'], $nowUtc, $maxUtc);

            if (!$timing) {
                return response()->json([
                    'message' => 'Segment departure must be after now and within 365 days',
                    'errors' => ['segments' => ['Segment departure must be after now and within 365 days']],
                ], 422);
            }

            $price = (float) $flight->price;
            $total += $price;

            $computed[] = [
                'segment_index' => $idx + 1,
                'flight' => $flight,
                'departure_date' => $seg['departure_date'],
                'departure_at_utc' => $timing['departure_at_utc'],
                'arrival_at_utc' => $timing['arrival_at_utc'],
                'departure_tz' => $timing['departure_tz'],
                'arrival_tz' => $timing['arrival_tz'],
                'departure_at_local' => $timing['departure_at_local'],
                'arrival_at_local' => $timing['arrival_at_local'],
                'price' => $price,
            ];
        }

        if (count($computed) > 1) {
            for ($i = 1; $i < count($computed); $i++) {
                if ($computed[$i]['departure_at_utc']->lt($computed[$i - 1]['arrival_at_utc'])) {
                    return response()->json([
                        'message' => 'Next segment must depart after previous segment arrives',
                        'errors' => ['segments' => ['Next segment must depart after previous segment arrives']],
                    ], 422);
                }

                $prevArr = $computed[$i - 1]['flight']->arrival_airport_code ?? $computed[$i - 1]['flight']->arrivalAirport->code;
                $currDep = $computed[$i]['flight']->departure_airport_code ?? $computed[$i]['flight']->departureAirport->code;

                if ($currDep !== $prevArr) {
                    return response()->json([
                        'message' => 'Segments must connect (arrival airport must match next departure airport)',
                        'errors' => ['segments' => ['Segments must connect (arrival airport must match next departure airport)']],
                    ], 422);
                }
            }
        }

        if ($type === 'round_trip') {
            $a = $computed[0]['flight']->departure_airport_code ?? $computed[0]['flight']->departureAirport->code;
            $b = $computed[0]['flight']->arrival_airport_code ?? $computed[0]['flight']->arrivalAirport->code;

            $b2 = $computed[1]['flight']->departure_airport_code ?? $computed[1]['flight']->departureAirport->code;
            $a2 = $computed[1]['flight']->arrival_airport_code ?? $computed[1]['flight']->arrivalAirport->code;

            if ($b2 !== $b || $a2 !== $a) {
                return response()->json([
                    'message' => 'round_trip requires A->B then B->A',
                    'errors' => ['segments' => ['round_trip requires A->B then B->A']],
                ], 422);
            }
        }

        $trip = DB::transaction(function () use ($type, $computed, $total) {
            $trip = Trip::create([
                'type' => $type,
                'currency' => 'NTR',
                'total_price' => number_format($total, 2, '.', ''),
            ]);

            foreach ($computed as $seg) {
                $trip->segments()->create([
                    'segment_index' => $seg['segment_index'],
                    'flight_id' => $seg['flight']->id,
                    'departure_date' => $seg['departure_date'],
                    'departure_at_utc' => $seg['departure_at_utc']->format('Y-m-d H:i:s'),
                    'arrival_at_utc' => $seg['arrival_at_utc']->format('Y-m-d H:i:s'),
                    'departure_tz' => $seg['departure_tz'],
                    'arrival_tz' => $seg['arrival_tz'],
                    'departure_at_local' => $seg['departure_at_local']->format('Y-m-d H:i:s'),
                    'arrival_at_local' => $seg['arrival_at_local']->format('Y-m-d H:i:s'),
                    'price' => number_format($seg['price'], 2, '.', ''),
                ]);
            }

            return $trip;
        });

        $trip->load([
            'segments.flight.airline',
            'segments.flight.departureAirport',
            'segments.flight.arrivalAirport',
        ]);

        return response()->json(['data' => $this->tripToArray($trip)], 201);
    }

    private function computeTiming(Flight $flight, string $departureDate, CarbonImmutable $nowUtc, CarbonImmutable $maxUtc): ?array
    {
        $depTz = $flight->departureAirport->timezone;
        $arrTz = $flight->arrivalAirport->timezone;

        $depTime = substr((string) $flight->departure_time, 0, 5);
        $arrTime = substr((string) $flight->arrival_time, 0, 5);

        $depLocal = CarbonImmutable::createFromFormat('Y-m-d H:i', $departureDate . ' ' . $depTime, $depTz);
        $depUtc = $depLocal->setTimezone('UTC');

        if ($depUtc->lte($nowUtc) || $depUtc->gt($maxUtc)) {
            return null;
        }

        $arrLocal = CarbonImmutable::createFromFormat('Y-m-d H:i', $departureDate . ' ' . $arrTime, $arrTz);
        $arrUtc = $arrLocal->setTimezone('UTC');

        if ($arrUtc->lte($depUtc)) {
            $arrLocal = $arrLocal->addDay();
            $arrUtc = $arrLocal->setTimezone('UTC');
        }

        return [
            'departure_tz' => $depTz,
            'arrival_tz' => $arrTz,
            'departure_at_local' => $depLocal,
            'arrival_at_local' => $arrLocal,
            'departure_at_utc' => $depUtc,
            'arrival_at_utc' => $arrUtc,
        ];
    }

    private function tripToArray(Trip $trip): array
    {
        $segments = $trip->segments->map(function ($seg) {
            $flight = $seg->flight;

            $depTz = $seg->departure_tz;
            $arrTz = $seg->arrival_tz;

            $depLocalStr = $seg->departure_at_local instanceof \DateTimeInterface
                ? $seg->departure_at_local->format('Y-m-d H:i:s')
                : (string) $seg->departure_at_local;

            $arrLocalStr = $seg->arrival_at_local instanceof \DateTimeInterface
                ? $seg->arrival_at_local->format('Y-m-d H:i:s')
                : (string) $seg->arrival_at_local;

            $depLocal = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $depLocalStr, $depTz);
            $arrLocal = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $arrLocalStr, $arrTz);

            $depUtc = $seg->departure_at_utc instanceof \DateTimeInterface
                ? CarbonImmutable::instance($seg->departure_at_utc)->setTimezone('UTC')
                : CarbonImmutable::parse((string) $seg->departure_at_utc, 'UTC');

            $arrUtc = $seg->arrival_at_utc instanceof \DateTimeInterface
                ? CarbonImmutable::instance($seg->arrival_at_utc)->setTimezone('UTC')
                : CarbonImmutable::parse((string) $seg->arrival_at_utc, 'UTC');

            return [
                'segment_index' => (int) $seg->segment_index,
                'flight' => [
                    'id' => $flight->id,
                    'airline' => [
                        'code' => $flight->airline->code,
                        'name' => $flight->airline->name,
                    ],
                    'number' => $flight->number,
                    'departure_airport' => [
                        'code' => $flight->departureAirport->code,
                        'timezone' => $depTz,
                    ],
                    'arrival_airport' => [
                        'code' => $flight->arrivalAirport->code,
                        'timezone' => $arrTz,
                    ],
                    'price' => (string) number_format((float) $seg->price, 2, '.', ''),
                ],
                'departure' => [
                    'date' => $depLocal->format('Y-m-d'),
                    'time' => $depLocal->format('H:i'),
                    'timezone' => $depTz,
                    'at_local' => $depLocal->toIso8601String(),
                    'at_utc' => $depUtc->toIso8601ZuluString(),
                ],
                'arrival' => [
                    'date' => $arrLocal->format('Y-m-d'),
                    'time' => $arrLocal->format('H:i'),
                    'timezone' => $arrTz,
                    'at_local' => $arrLocal->toIso8601String(),
                    'at_utc' => $arrUtc->toIso8601ZuluString(),
                ],
            ];
        })->values();

        return [
            'id' => (int) $trip->id,
            'type' => $trip->type,
            'created_at' => CarbonImmutable::parse($trip->created_at, 'UTC')->toIso8601ZuluString(),
            'total_price' => (string) number_format((float) $trip->total_price, 2, '.', ''),
            'currency' => $trip->currency,
            'segments' => $segments,
        ];
    }
}
