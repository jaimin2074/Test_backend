<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MapsController extends Controller
{
    // Nominatim requires a User-Agent identifying the application
    private $userAgent = 'SchoolCabApp/1.0';

    /**
     * Search for places using Nominatim (OpenStreetMap)
     * Maps the response to match Google Places Autocomplete format
     */
    public function autocomplete(Request $request)
    {
        $input = $request->query('input');
        if (!$input) {
            return response()->json(['status' => 'error', 'message' => 'Input is required']);
        }

        $predictions = [];

        // 1. Search Saved Locations (if user is authenticated)
        // Note: MapController might need auth middleware or check user via Sanction
        // Ideally we pass user via Auth::user() but let's check input
        // Since we are mocking user mostly, let's assume if user is passed or auth
        // 1. Search Saved Locations
        $user = $request->user('sanctum');
        if (!$user) {
             // Fallback for demo/mock mode matching SavedLocationController
             $user = \App\Models\User::first(); 
        }
        
        if ($user) {
            $saved = \App\Models\SavedLocation::where('user_id', $user->id)
                ->where(function($q) use ($input) {
                    $q->where('name', 'like', "%$input%")
                      ->orWhere('address', 'like', "%$input%");
                })
                ->get();
            
            foreach ($saved as $loc) {
                $predictions[] = [
                    'description' => $loc->name, // Start with name only 
                    'place_id' => 'saved_' . $loc->id,
                    'is_saved' => true, 
                    'structured_formatting' => [
                        'main_text' => $loc->name,
                        'secondary_text' => $loc->address
                    ]
                ];
            }
        }

        // 2. Search Nominatim (if query provided)
        if (strlen($input) > 2) {
            $url = "https://nominatim.openstreetmap.org/search";
            
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
                'Referer' => 'http://localhost' 
            ])->get($url, [
                'q' => $input,
                'format' => 'json',
                'addressdetails' => 1,
                'limit' => 5,
                'countrycodes' => 'in'
            ]);

            if ($response->successful()) {
                $results = $response->json();
                foreach ($results as $item) {
                     $osmType = substr($item['osm_type'], 0, 1);
                     $placeId = $osmType . $item['osm_id'];

                     $predictions[] = [
                        'description' => $item['display_name'],
                        'place_id' => $placeId,
                        'is_saved' => false,
                        'structured_formatting' => [
                            'main_text' => explode(',', $item['display_name'])[0],
                            'secondary_text' => $item['display_name']
                        ]
                    ];
                }
            }
        }

        return response()->json([
            'status' => 'OK',
            'predictions' => $predictions
        ]);
    }

    /**
     * Reverse Geocoding (Lat/Lng -> Address)
     */
    public function reverse(Request $request) 
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (!$lat || !$lng) {
            return response()->json(['status' => 'error', 'message' => 'Lat and Lng required']);
        }

        $url = "https://nominatim.openstreetmap.org/reverse";
        
        $response = Http::withHeaders([
            'User-Agent' => $this->userAgent
        ])->get($url, [
            'lat' => $lat,
            'lon' => $lng,
            'format' => 'json',
            'addressdetails' => 1
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return response()->json([
                'status' => 'OK',
                'result' => [
                    'formatted_address' => $data['display_name'],
                    'name' => explode(',', $data['display_name'])[0],
                     // Detailed parts if needed
                ]
            ]);
        }
        
        return response()->json(['status' => 'REQUEST_DENIED']);
    }

    /**
     * Get Place Details using Nominatim Lookup
     * Maps resposne to Google Places Details format
     */
    public function details(Request $request)
    {
        $placeId = $request->query('place_id'); // Expected format "{N/W/R}{OSM_ID}"
        if (!$placeId) {
            return response()->json(['status' => 'error', 'message' => 'Place ID is required']);
        }

        if (str_starts_with($placeId, 'saved_')) {
             $id = str_replace('saved_', '', $placeId);
             $saved = \App\Models\SavedLocation::find($id);
             
             if (!$saved) return response()->json(['status' => 'ZERO_RESULTS']);

             return response()->json([
                'status' => 'OK',
                'result' => [
                    'geometry' => [
                        'location' => [
                            'lat' => (float)$saved->latitude,
                            'lng' => (float)$saved->longitude
                        ]
                    ],
                    'name' => $saved->name,
                    'formatted_address' => $saved->address,
                ]
            ]);
        }

        // Parse our custom Nominatim place_id
        $typeChar = substr($placeId, 0, 1);
        $osmId = substr($placeId, 1);
        
        // Nominatim Lookup API (supports comma separated api)
        // https://nominatim.openstreetmap.org/lookup?osm_ids=N12345
        
        $url = "https://nominatim.openstreetmap.org/lookup";

        $response = Http::withHeaders([
            'User-Agent' => $this->userAgent
        ])->get($url, [
            'osm_ids' => $typeChar . $osmId,
            'format' => 'json'
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (empty($data)) {
                 return response()->json(['status' => 'ZERO_RESULTS']);
            }
            
            $item = $data[0];

            return response()->json([
                'status' => 'OK',
                'result' => [
                    'geometry' => [
                        'location' => [
                            'lat' => (float)$item['lat'],
                            'lng' => (float)$item['lon']
                        ]
                    ],
                    'name' => explode(',', $item['display_name'])[0],
                    'formatted_address' => $item['display_name'],
                ]
            ]);
        }

        return response()->json(['status' => 'REQUEST_DENIED']);
    }

    /**
     * Get Directions using OSRM (Open Source Routing Machine)
     * Maps response to Google Directions API format
     */
    public function directions(Request $request)
    {
        $origin = $request->query('origin'); // "lat,lng"
        $destination = $request->query('destination'); // "lat,lng"

        if (!$origin || !$destination) {
            return response()->json(['status' => 'error', 'message' => 'Origin and Destination required']);
        }

        // Parse coordinates
        list($lat1, $lon1) = explode(',', $origin);
        list($lat2, $lon2) = explode(',', $destination);

        // OSRM expects "lon,lat"
        $encodedOrigin = "$lon1,$lat1";
        $encodedDest = "$lon2,$lat2";

        $url = "http://router.project-osrm.org/route/v1/driving/$encodedOrigin;$encodedDest";

        $response = Http::get($url, [
            'overview' => 'full',
            'geometries' => 'polyline', // Google encoded polyline format
            'steps' => 'false'
        ]);

        if ($response->successful()) {
            $data = $response->json();

            if (($data['code'] ?? '') !== 'Ok') {
                 return response()->json(['status' => 'ZERO_RESULTS']);
            }

            $route = $data['routes'][0];
            $distanceMeters = $route['distance'];
            $durationSeconds = $route['duration'];

            // Format text
            $distanceText = ($distanceMeters > 1000) 
                ? number_format($distanceMeters / 1000, 1) . ' km' 
                : round($distanceMeters) . ' m';
                
            $durationText = ($durationSeconds > 3600)
                ? gmdate("H \h i \m\i\n", $durationSeconds)
                : gmdate("i \m\i\n", $durationSeconds);

            // Construct Google-compatible response
            return response()->json([
                'status' => 'OK',
                'routes' => [
                    [
                        'overview_polyline' => [
                            'points' => $route['geometry'] 
                        ],
                        'legs' => [
                            [
                                'distance' => [
                                    'text' => $distanceText,
                                    'value' => $distanceMeters
                                ],
                                'duration' => [
                                    'text' => $durationText,
                                    'value' => $durationSeconds
                                ],
                                'start_address' => 'Origin', 
                                'end_address' => 'Destination'
                            ]
                        ],
                        // Bounds omitted, Frontend should handle it or calculate locally
                    ]
                ]
            ]);
        }

        return response()->json(['status' => 'REQUEST_DENIED']);
    }
}
