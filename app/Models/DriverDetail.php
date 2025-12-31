<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'license_number',
        'license_image_url',
        'aadhar_number',
        'police_verification_status',
        'is_verified',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
