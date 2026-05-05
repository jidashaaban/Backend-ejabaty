<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Courses;
use App\Models\User;
use App\Notifications\SchoolNotification;

class CourseController extends Controller
{
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
              return response()->json([
                 'message' => 'Forbidden: Only Administrators can perform this action.'
    ], 403);
} 
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

        $teacher = User::find($course->teacher_id);
        if ($teacher) {
              $teacher->notify(new SchoolNotification(
                 "New Course Assigned",
                 "You have been assigned to teach: " . $course->name,
                 "course_assignment"
        ));
    }
        $students = User::where('role', 'student')->get();

        foreach ($students as $student) {
             $student->notify(new SchoolNotification(
                 "New Course Available!",
                 "The course '" . $course->name . "' (Code: " . $course->code . ") is now open for enrollment. Check it out!",
                 "new_course_alert"
        ));
    }

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
        $student->notify(new SchoolNotification(
            "Payment Confirmed!",
            "Your seat in " . $course->name . " is now officially booked.",
            "payment_success"
        ));

        return response()->json([
            'message' => 'Payment confirmed! The seat is now permanently booked for this student.'
        ]);
    }
}
