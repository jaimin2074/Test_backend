<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'number_plate',
        'model',
        'capacity',
        'status',
        'assigned_driver_id',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'assigned_driver_id');
    }
}
