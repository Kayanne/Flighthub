<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use App\Models\Flight;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TripSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $base = $request->validate([
            'type' => ['required', Rule::in(['one_way', 'round_trip', 'multi_city'])],
            'preferred_airline' => ['nullable', 'string', 'max:3'],
            'sort' => ['nullable', Rule::in(['price', 'departure_at'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $type = $base['type'];

        if ($type === 'multi_city') {
            $extra = $request->validate([
                'legs' => ['required', 'array', 'min:2', 'max:5'],
                'legs.*.origin' => ['required', 'string', 'max:5'],
                'legs.*.destination' => ['required', 'string', 'max:5'],
                'legs.*.departure_date' => ['required', 'date_format:Y-m-d'],
            ]);
            $validated = array_merge($base, $extra);
        } else {
            $extra = $request->validate([
                'origin' => ['required', 'string', 'max:5'],
                'destination' => ['required', 'string', 'max:5'],
                'departure_date' => ['required', 'date_format:Y-m-d'],
                'return_date' => ['nullable', 'date_format:Y-m-d'],
            ]);
            $validated = array_merge($base, $extra);

            if ($type === 'round_trip' && empty($validated['return_date'])) {
                return response()->json([
                    'message' => 'return_date is required for round_trip',
                    'errors' => ['return_date' => ['return_date is required for round_trip']],
                ], 422);
            }
        }

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $sort = $validated['sort'] ?? 'price';
        $preferredAirline = isset($validated['preferred_airline']) ? strtoupper($validated['preferred_airline']) : null;

        $nowUtc = CarbonImmutable::now('UTC');
        $maxUtc = $nowUtc->addDays(365);

        if ($type === 'multi_city') {
            $proposals = $this->buildMultiCityProposals($validated['legs'], $preferredAirline, $nowUtc, $maxUtc);
            $proposals = $this->sortProposals($proposals, $sort);
            return $this->paginate($proposals, $page, $perPage);
        }

        $originCodes = $this->resolveAirportCodes($validated['origin'], 'origin');
        $destinationCodes = $this->resolveAirportCodes($validated['destination'], 'destination');

        if ($type === 'one_way') {
            $proposals = $this->buildOneWayProposals($originCodes, $destinationCodes, $validated['departure_date'], $preferredAirline, $nowUtc, $maxUtc);
            $proposals = $this->sortProposals($proposals, $sort);
            return $this->paginate($proposals, $page, $perPage);
        }

        $outbounds = $this->buildOneWayProposals($originCodes, $destinationCodes, $validated['departure_date'], $preferredAirline, $nowUtc, $maxUtc);
        $inbounds = $this->buildOneWayProposals($destinationCodes, $originCodes, $validated['return_date'], $preferredAirline, $nowUtc, $maxUtc);

        $pairs = [];
        foreach ($outbounds as $o) {
            foreach ($inbounds as $i) {
                $oArrivalUtc = CarbonImmutable::parse($o['segments'][0]['arrival']['at_utc'], 'UTC');
                $iDepartUtc = CarbonImmutable::parse($i['segments'][0]['departure']['at_utc'], 'UTC');
                if ($iDepartUtc->lt($oArrivalUtc)) {
                    continue;
                }

                $pairs[] = [
                    'type' => 'round_trip',
                    'total_price' => (string) number_format(((float) $o['total_price']) + ((float) $i['total_price']), 2, '.', ''),
                    'currency' => 'NTR',
                    'segments' => [
                        array_merge($o['segments'][0], ['segment_index' => 1]),
                        array_merge($i['segments'][0], ['segment_index' => 2]),
                    ],
                ];
            }
        }

        $pairs = $this->sortProposals($pairs, $sort);
        return $this->paginate($pairs, $page, $perPage);
    }

    private function resolveAirportCodes(string $codeOrCity, string $field): array
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
            $field => ["Unknown airport or city code: {$codeOrCity}"],
        ]);
    }

    private function buildOneWayProposals(
        array $originCodes,
        array $destinationCodes,
        string $departureDate,
        ?string $preferredAirline,
        CarbonImmutable $nowUtc,
        CarbonImmutable $maxUtc
    ): array {
        $q = Flight::query()
            ->with(['airline', 'departureAirport', 'arrivalAirport'])
            ->whereIn('departure_airport_code', $originCodes)
            ->whereIn('arrival_airport_code', $destinationCodes);

        if ($preferredAirline) {
            $q->where('airline_code', $preferredAirline);
        }

        $flights = $q->get();
        $results = [];

        foreach ($flights as $flight) {
            $depTz = $flight->departureAirport->timezone;
            $arrTz = $flight->arrivalAirport->timezone;

            $depLocal = CarbonImmutable::createFromFormat(
                'Y-m-d H:i',
                $departureDate . ' ' . substr((string) $flight->departure_time, 0, 5),
                $depTz
            );
            $depUtc = $depLocal->setTimezone('UTC');

            if ($depUtc->lte($nowUtc) || $depUtc->gt($maxUtc)) {
                continue;
            }

            $arrLocal = CarbonImmutable::createFromFormat(
                'Y-m-d H:i',
                $departureDate . ' ' . substr((string) $flight->arrival_time, 0, 5),
                $arrTz
            );
            $arrUtc = $arrLocal->setTimezone('UTC');

            if ($arrUtc->lte($depUtc)) {
                $arrLocal = $arrLocal->addDay();
                $arrUtc = $arrLocal->setTimezone('UTC');
            }

            $results[] = [
                'type' => 'one_way',
                'total_price' => (string) number_format((float) $flight->price, 2, '.', ''),
                'currency' => 'NTR',
                'segments' => [
                    [
                        'segment_index' => 1,
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
                            'price' => (string) number_format((float) $flight->price, 2, '.', ''),
                        ],
                        'departure' => [
                            'date' => $departureDate,
                            'time' => substr((string) $flight->departure_time, 0, 5),
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
                    ],
                ],
            ];
        }

        return $results;
    }

    private function buildMultiCityProposals(
        array $legs,
        ?string $preferredAirline,
        CarbonImmutable $nowUtc,
        CarbonImmutable $maxUtc
    ): array {
        $resolved = [];

        foreach ($legs as $i => $leg) {
            $originCodes = $this->resolveAirportCodes($leg['origin'], "legs.$i.origin");
            $destinationCodes = $this->resolveAirportCodes($leg['destination'], "legs.$i.destination");

            if ($i > 0) {
                $prevDestCodes = $resolved[$i - 1]['destination_codes'];
                if (count(array_intersect($prevDestCodes, $originCodes)) === 0) {
                    throw ValidationException::withMessages([
                        "legs.$i.origin" => ['multi_city legs must connect (previous destination must match next origin)'],
                    ]);
                }
            }

            $resolved[] = [
                'origin_codes' => $originCodes,
                'destination_codes' => $destinationCodes,
                'departure_date' => $leg['departure_date'],
            ];
        }

        $legOptions = [];
        foreach ($resolved as $i => $leg) {
            $opts = $this->buildOneWayProposals(
                $leg['origin_codes'],
                $leg['destination_codes'],
                $leg['departure_date'],
                $preferredAirline,
                $nowUtc,
                $maxUtc
            );

            usort($opts, fn (array $a, array $b): int => ((float) $a['total_price']) <=> ((float) $b['total_price']));
            $opts = array_slice($opts, 0, 20);

            if (count($opts) === 0) {
                return [];
            }

            $legOptions[] = [
                'origin_codes' => $leg['origin_codes'],
                'options' => $opts,
            ];
        }

        $paths = [[
            'segments' => [],
            'total' => 0.0,
            'last_arrival_utc' => null,
            'last_arrival_code' => null,
        ]];

        foreach ($legOptions as $idx => $leg) {
            $next = [];
            $originCodes = $leg['origin_codes'];

            foreach ($paths as $p) {
                foreach ($leg['options'] as $opt) {
                    $seg = $opt['segments'][0];

                    $depCode = $seg['flight']['departure_airport']['code'];
                    $arrCode = $seg['flight']['arrival_airport']['code'];

                    if ($idx > 0) {
                        if ($p['last_arrival_code'] !== $depCode) {
                            continue;
                        }

                        if (!in_array($depCode, $originCodes, true)) {
                            continue;
                        }

                        $depUtc = CarbonImmutable::parse($seg['departure']['at_utc'], 'UTC');
                        $prevArrUtc = CarbonImmutable::parse($p['last_arrival_utc'], 'UTC');
                        if ($depUtc->lt($prevArrUtc)) {
                            continue;
                        }
                    }

                    $newSeg = $seg;
                    $newSeg['segment_index'] = $idx + 1;

                    $next[] = [
                        'segments' => array_merge($p['segments'], [$newSeg]),
                        'total' => $p['total'] + (float) $opt['total_price'],
                        'last_arrival_utc' => $seg['arrival']['at_utc'],
                        'last_arrival_code' => $arrCode,
                    ];
                }
            }

            usort($next, fn (array $a, array $b): int => $a['total'] <=> $b['total']);
            $paths = array_slice($next, 0, 200);

            if (count($paths) === 0) {
                return [];
            }
        }

        $results = [];
        foreach ($paths as $p) {
            $results[] = [
                'type' => 'multi_city',
                'total_price' => (string) number_format($p['total'], 2, '.', ''),
                'currency' => 'NTR',
                'segments' => $p['segments'],
            ];
        }

        return $results;
    }

    private function sortProposals(array $proposals, string $sort): array
    {
        usort($proposals, function (array $a, array $b) use ($sort): int {
            if ($sort === 'departure_at') {
                $aUtc = $a['segments'][0]['departure']['at_utc'];
                $bUtc = $b['segments'][0]['departure']['at_utc'];
                return strcmp($aUtc, $bUtc);
            }

            return ((float) $a['total_price']) <=> ((float) $b['total_price']);
        });

        return $proposals;
    }

    private function paginate(array $items, int $page, int $perPage): JsonResponse
    {
        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($items, $offset, $perPage);

        return response()->json([
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'data' => $slice,
        ]);
    }
}
