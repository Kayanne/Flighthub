<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flight extends Model
{
    protected $fillable = [
        'airline_code',
        'number',
        'departure_airport_code',
        'arrival_airport_code',
        'departure_time',
        'arrival_time',
        'price',
    ];

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class, 'airline_code', 'code');
    }

    public function departureAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'departure_airport_code', 'code');
    }

    public function arrivalAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'arrival_airport_code', 'code');
    }
}
