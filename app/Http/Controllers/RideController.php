<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RideController extends Controller
{
    private $apiKey = 'AIzaSyB6HOYpyMcGri3boelIClVKoymCWF8bHFQ';

    public function search(Request $request)
    {
        // validate input
        $request->validate([
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'drop_lat' => 'required|numeric',
            'drop_lng' => 'required|numeric',
        ]);

        // 1. Fetch exact route distance/duration from Google Maps (Server-Side)
        $origin = "{$request->pickup_lat},{$request->pickup_lng}";
        $destination = "{$request->drop_lat},{$request->drop_lng}";
        $distanceKm = 0;
        $durationMin = 0;

        try {
            $response = Http::get("https://maps.googleapis.com/maps/api/directions/json", [
                'origin' => $origin,
                'destination' => $destination,
                'key' => $this->apiKey
            ]);

            if ($response->successful() && !empty($response['routes'])) {
                $leg = $response['routes'][0]['legs'][0];
                $distanceKm = $leg['distance']['value'] / 1000; // meters to km
                $durationMin = $leg['duration']['value'] / 60; // seconds to min
            } else {
                // Fallback: Calculate linear distance * 1.4 (approx road factor)
                $distanceKm = $this->calculateHaversine(
                    $request->pickup_lat, $request->pickup_lng,
                    $request->drop_lat, $request->drop_lng
                ) * 1.4;
                $durationMin = $distanceKm * 4; // Approx 15km/h in city traffic
            }
        } catch (\Exception $e) {
            // Fallback on error
            $distanceKm = 1;
            $durationMin = 5;
        }

        // 2. Pricing Configuration (Rapido-like logic)
        // Formula: (Base + (Dist * RateKm) + (Time * RateMin)) * Surge
        $surgeMultiplier = 1.0; // Can be dynamic based on time/demand
        
        $vehicles = [
            [
                'id' => 'bike',
                'name' => 'Bike',
                'desc' => 'Fastest way',
                'base_fare' => 15,
                'per_km' => 8,      // User requested 8 rs/km
                'per_min' => 1.0
            ],
            [
                'id' => 'auto',
                'name' => 'Auto',
                'desc' => 'Doorstep pick',
                'base_fare' => 25,
                'per_km' => 12,
                'per_min' => 1.5
            ],
            [
                'id' => 'cab',
                'name' => 'Cab',
                'desc' => 'Comfy AC Ride',
                'base_fare' => 45,
                'per_km' => 16,
                'per_min' => 2.5
            ],
            [
                'id' => 'van',
                'name' => 'School Van',
                'desc' => 'Student Pool',
                'base_fare' => 30,
                'per_km' => 10,
                'per_min' => 1.2
            ]
        ];

        // 3. Generate Options
        $options = [];
        foreach ($vehicles as $v) {
            $cost = ($v['base_fare'] + ($distanceKm * $v['per_km']) + ($durationMin * $v['per_min'])) * $surgeMultiplier;
            
            // formatting
            $options[] = [
                'id' => $v['id'],
                'name' => $v['name'],
                'desc' => $v['desc'],
                'time' => round($durationMin) . ' mins',
                'price' => round($cost), // round to nearest integer
                'original_price' => round($cost * 1.2), // Fake 'struck-through' price if needed
                'distance' => number_format($distanceKm, 1) . ' km'
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $options
        ]);
    }

    /**
     * Book a specific ride.
     */
    public function book(Request $request)
    {
        $request->validate([
            'ride_id'  => 'required|string',
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'drop_lat' => 'required|numeric',
            'drop_lng' => 'required|numeric',
            'pickup_address' => 'nullable|string',
            'drop_address' => 'nullable|string',
            'fare'     => 'nullable|numeric', 
            'distance' => 'nullable|string',
            'duration' => 'nullable|string',
        ]);

        // Insert into DB
        $ride = \DB::table('ride_requests')->insertGetId([
            'parent_id' => $request->user() ? $request->user()->id : null, // handle guest
            'driver_id' => null, // No driver yet
            'pickup_lat' => $request->pickup_lat,
            'pickup_lng' => $request->pickup_lng,
            'pickup_address' => $request->pickup_address,
            'drop_lat' => $request->drop_lat,
            'drop_lng' => $request->drop_lng,
            'drop_address' => $request->drop_address,
            'fare' => $request->fare ?? 0,
            'distance_text' => $request->distance,
            'duration_text' => $request->duration,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Ride request created',
            'ride_id' => $ride
        ]);
    }

    // Driver: Get Pending Requests
    public function pending(Request $request)
    {
        // For demo, return all pending rides. In reality, filter by geo-location radius.
        $requests = \DB::table('ride_requests')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $requests
        ]);
    }

    // Driver: Accept Request
    public function accept(Request $request)
    {
        $request->validate([
            //'driver_id' => 'required', // In real auth, use $request->user()->id
            'ride_request_id' => 'required'
        ]);

        // Mock Driver ID if not auth
        $driverId = $request->user() ? $request->user()->id : 99; // 99 default mock driver

        $affected = \DB::table('ride_requests')
            ->where('id', $request->ride_request_id)
            ->where('status', 'pending')
            ->update([
                'status' => 'accepted',
                'driver_id' => $driverId,
                'updated_at' => now()
            ]);

        if ($affected) {
            return response()->json(['status' => 'success', 'message' => 'Ride Accepted']);
        }
        return response()->json(['status' => 'error', 'message' => 'Ride already taken or not found'], 400);
    }

    // Parent: Check Status
    public function status(Request $request)
    {
        $rideId = $request->query('ride_id');
        
        $ride = \DB::table('ride_requests')->where('id', $rideId)->first();
        
        if (!$ride) {
             return response()->json(['status' => 'error', 'message' => 'Ride not found'], 404);
        }

        $response = [
            'status' => 'success',
            'ride_status' => $ride->status,
        ];

        if ($ride->status == 'accepted') {
            // Attach driver details
            // In a real app, join 'users' table. Here we mock or fetch if exists.
            // Using logic from your prompt "Driver name, vehicle, photo"
            $driverUser = \DB::table('users')->where('id', $ride->driver_id)->first();
            
            $response['driver'] = [
                'name' => $driverUser ? $driverUser->name : 'Ramesh Kumar',
                'phone' => $driverUser ? $driverUser->phone : '+91 9876543210',
                'vehicle_model' => 'Maruti Swift', // Mock/Fetch from driver_details table
                'vehicle_number' => 'GJ 01 AB 1234',
                'rating' => 4.8,
                'otp' => '4591'
            ];
        }

        return response()->json($response);
    }


    // Helper: Haversine Formula for fallback distance
    private function calculateHaversine($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }
}
