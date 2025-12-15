<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    protected $fillable = ['type', 'currency', 'total_price'];

    public function segments(): HasMany
    {
        return $this->hasMany(TripSegment::class)->orderBy('segment_index');
    }
}
