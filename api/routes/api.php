<?php

use App\Http\Controllers\Api\AirlineController;
use App\Http\Controllers\Api\AirportController;
use App\Http\Controllers\Api\TripSearchController;
use App\Http\Controllers\Api\TripController;


use Illuminate\Support\Facades\Route;

Route::get('/airlines', [AirlineController::class, 'index']);
Route::get('/airports', [AirportController::class, 'index']);
Route::post('/trips/search', [TripSearchController::class, 'search']);
Route::post('/trips', [TripController::class, 'store']);
Route::get('/trips', [TripController::class, 'index']);
Route::get('/trips/{trip}', [TripController::class, 'show']);
