<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripSegment extends Model
{
    protected $fillable = [
        'trip_id',
        'segment_index',
        'flight_id',
        'departure_date',
        'departure_at_utc',
        'arrival_at_utc',
        'departure_tz',
        'arrival_tz',
        'departure_at_local',
        'arrival_at_local',
        'price',
    ];

    protected $casts = [
        'departure_date' => 'date:Y-m-d',
        'departure_at_utc' => 'datetime',
        'arrival_at_utc' => 'datetime',
        'departure_at_local' => 'string',
        'arrival_at_local' => 'string',
        'price' => 'decimal:2',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class);
    }
}
