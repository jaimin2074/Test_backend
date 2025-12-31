<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_route_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
            $table->foreignId('pickup_stop_id')->nullable()->constrained('route_stops');
            $table->foreignId('drop_stop_id')->nullable()->constrained('route_stops');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_route_assignments');
    }
};
