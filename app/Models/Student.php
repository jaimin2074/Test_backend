<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'class_grade',
        'division',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'drop_address',
        'emergency_contact',
        'school_id_number',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }
}
