<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airport extends Model
{
    protected $fillable = [
        'code',
        'city_code',
        'name',
        'city',
        'country_code',
        'region_code',
        'latitude',
        'longitude',
        'timezone',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
}
