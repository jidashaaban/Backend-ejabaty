<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Courses;
use App\Models\User;

class CourseController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validate the incoming data from the Admin
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:courses', // e.g., CS101
            'teacher_id' => 'required|exists:users,id', // Link it to a teacher
            'capacity' => 'required|integer|min:1',
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

    public function confirmPayment(Request $request)
    {
        // 1. Validate the input
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
        ]);

        $student = User::findOrFail($request->user_id);

        // 2. Update the status in the pivot table
        // This looks for the specific row connecting this user and this course
        $student->courses()->updateExistingPivot($request->course_id, [
            'status' => 'paid'
        ]);

        return response()->json([
            'message' => 'Payment confirmed! The seat is now permanently booked for this student.'
        ]);
    }
}
