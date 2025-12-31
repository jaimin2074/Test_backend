<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RideController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\MapsController;
use App\Http\Controllers\SavedLocationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Maps Proxy Routes - Moved outside middleware
Route::get('/maps/autocomplete', [MapsController::class, 'autocomplete']);
Route::get('/maps/details', [MapsController::class, 'details']);
Route::get('/maps/reverse', [MapsController::class, 'reverse']);
Route::get('/maps/directions', [MapsController::class, 'directions']);

// Rides (Public for demo)
Route::post('/rides/search', [RideController::class, 'search']);
Route::post('/rides/book', [RideController::class, 'book']);
Route::get('/rides/pending', [RideController::class, 'pending']);
Route::post('/rides/accept', [RideController::class, 'accept']);
Route::get('/rides/status', [RideController::class, 'status']);

// Saved Locations (Public for demo/mock)
Route::post('/locations', [SavedLocationController::class, 'store']);
Route::get('/locations', [SavedLocationController::class, 'index']);
Route::delete('/locations/{id}', [SavedLocationController::class, 'destroy']);


// Protected Routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Parent Routes
    Route::get('/students', [StudentController::class, 'index']);
    Route::post('/students', [StudentController::class, 'store']);
    // User Info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
