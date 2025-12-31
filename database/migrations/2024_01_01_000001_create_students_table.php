<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('class_grade');
            $table->string('division')->nullable();
            $table->string('pickup_address');
            $table->double('pickup_lat')->nullable();
            $table->double('pickup_lng')->nullable();
            $table->string('drop_address')->nullable(); // Default to school if null?
            $table->string('emergency_contact')->nullable();
            $table->string('school_id_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
