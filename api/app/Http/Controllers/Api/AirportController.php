<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Airport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AirportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('query', ''));

        $query = Airport::query();

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $like = '%' . $q . '%';
                $sub->where('code', 'like', $like)
                    ->orWhere('city_code', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('name', 'like', $like);
            });
        }

        $airports = $query
            ->orderBy('code')
            ->limit(25)
            ->get([
                'code',
                'city_code',
                'name',
                'city',
                'country_code',
                'region_code',
                'latitude',
                'longitude',
                'timezone',
            ]);

        return response()->json(['data' => $airports]);
    }
}
