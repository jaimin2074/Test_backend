<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        // Get students for logged in parent
        $students = Student::where('parent_id', $request->user()->id)->get();
        return response()->json(['data' => $students]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'class_grade' => 'required',
            'pickup_address' => 'required',
        ]);

        $student = Student::create([
            'parent_id' => $request->user()->id,
            'name' => $request->name,
            'class_grade' => $request->class_grade,
            'division' => $request->division,
            'pickup_address' => $request->pickup_address,
            'pickup_lat' => $request->pickup_lat, // Optional
            'pickup_lng' => $request->pickup_lng, // Optional
            'emergency_contact' => $request->emergency_contact,
            'school_id_number' => $request->school_id_number,
        ]);

        return response()->json(['message' => 'Student added successfully', 'data' => $student]);
    }
}
