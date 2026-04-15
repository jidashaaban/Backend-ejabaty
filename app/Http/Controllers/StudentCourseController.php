<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Courses;
use App\Models\User;
use Carbon\Carbon;

class StudentCourseController extends Controller
{
    public function availableCourses()
    {
        // Fetch all courses. You can add a where() clause here if you 
        // only want to show courses for the current semester.
        $courses = Courses::all();
        
        return response()->json([
            'available_courses' => $courses
        ]);
    }

    // 2. The logic when the student presses "Join"
    public function joinCourse(Request $request, $courseId)
{
    $studentId = $request->input('student_id'); 
    $student = User::findOrFail($studentId);
    
    // Note: Using 'Courses' as per your snippet
    $course = Courses::findOrFail($courseId);

    // 1. Check if the course is already full
    // This counts rows in user_course for this course_id
    $currentStudentsCount = $course->students()->count();

    if ($currentStudentsCount >= $course->capacity) {
        return response()->json([
            'message' => 'Capacity is full'
        ], 400);
    }

    // 2. Check if the student already booked this
    $alreadyJoined = $student->courses()->where('course_id', $courseId)->exists();

    if ($alreadyJoined) {
        return response()->json(['error' => 'You have already booked a seat.'], 400);
    }

    // 3. Attach using the relationship
    $student->courses()->attach($courseId, [
        'status' => 'pending_payment',
        'booked_at' => now(),
    ]);

    return response()->json([
        'message' => 'your seat is booked in this course you have 24 hours to come and pay in person'
    ]);
}

public function myCourses($studentId)
    {
    
        // 1. Find the student
        $student = User::findOrFail($studentId);

        // 2. Fetch the courses linked to this student via the user_course table
        // We include 'withPivot' so you can see the payment status and booking time
        $myCourses = $student->courses()->get();

        // 3. Return the data
        if ($myCourses->isEmpty()) {
            return response()->json([
                'message' => 'You have not joined any courses yet.'
            ], 200);
        }

        return response()->json([
            'student_name' => $student->name,
            'enrolled_courses' => $myCourses
        ]);
    }
}