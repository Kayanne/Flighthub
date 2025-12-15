<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Airline;
use Illuminate\Http\JsonResponse;

class AirlineController extends Controller
{
    public function index(): JsonResponse
    {
        $airlines = Airline::query()
            ->orderBy('code')
            ->get(['code', 'name']);

        return response()->json(['data' => $airlines]);
    }
}
