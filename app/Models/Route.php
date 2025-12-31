<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_location',
        'end_location',
        'start_time',
        'default_vehicle_id',
    ];

    public function stops()
    {
        return $this->hasMany(RouteStop::class)->orderBy('sequence_order');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'default_vehicle_id');
    }
}
