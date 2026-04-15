<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Courses;

class CourseController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validate the incoming data from the Admin
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:courses', // e.g., CS101
            'teacher_id' => 'required|exists:users,id', // Link it to a teacher
            // Add any other fields you need like description or credit_hours
        ]);

        // 2. Create the course in the database
        $course = Courses::create($validatedData);

        // 3. Return a success message
        return response()->json([
            'message' => 'New course added and announced successfully!',
            'course' => $course
        ], 201);
    }
}
