<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SavedLocation;
use Illuminate\Support\Facades\Auth;

class SavedLocationController extends Controller
{
    private function getUser(Request $request) {
        $user = $request->user();
        if (!$user) {
            // Fallback for demo/mock mode
             $user = \App\Models\User::first();
        }
        return $user;
    }

    public function index(Request $request)
    {
        $user = $this->getUser($request);
        if (!$user) return [];
        return SavedLocation::where('user_id', $user->id)->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user = $this->getUser($request);
        if (!$user) {
             return response()->json(['status' => 'error', 'message' => 'No user found']);
        }

        $location = SavedLocation::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'address' => $request->address,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return response()->json(['status' => 'success', 'data' => $location]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $this->getUser($request);
        if (!$user) {
             return response()->json(['status' => 'error', 'message' => 'No user found']);
        }
        
        $location = SavedLocation::where('user_id', $user->id)->where('id', $id)->firstOrFail();
        $location->delete();
        return response()->json(['status' => 'success']);
    }
}
