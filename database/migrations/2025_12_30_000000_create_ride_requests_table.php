<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_requests', function (Blueprint $table) {
            $table->id();
            // nullable parent_id for guest/test users, though ideally constrained
            $table->foreignId('parent_id')->nullable()->constrained('users');
            $table->foreignId('driver_id')->nullable()->constrained('users');
            // We can link a specific student if needed
            $table->foreignId('student_id')->nullable()->constrained('students');
            
            $table->double('pickup_lat');
            $table->double('pickup_lng');
            $table->string('pickup_address')->nullable();
            
            $table->double('drop_lat');
            $table->double('drop_lng');
            $table->string('drop_address')->nullable();

            $table->decimal('fare', 8, 2);
            $table->string('distance_text')->nullable();
            $table->string('duration_text')->nullable();
            
            // Status: pending, accepted, ongoing, completed, cancelled
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('pending');
            $table->string('ride_type')->default('daily'); // daily or monthly
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_requests');
    }
};
