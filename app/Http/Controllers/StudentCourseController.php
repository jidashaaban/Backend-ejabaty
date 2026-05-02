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
    $requester = User::find($request->query('requester_id'));

    if (!$requester || $requester->role !== 'student') {
           return response()->json([
              'message' => 'Forbidden: This is a Student-only area.'
    ], 403);
}
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
    $student->notify(new SchoolNotification(
        "Course Seat Reserved",
        "You have booked a seat in " . $course->name . ". Please visit the administration within 24 hours to complete your payment.",
        "course_joined_pending"
    ));

    // 2. Notify the Parent
    // We load the parents relationship to find who to alert
    $student->load('parents');
    foreach ($student->parents as $parent) {
        $parent->notify(new SchoolNotification(
            "New Course Booking",
            $student->name . " has reserved a seat in " . $course->name . ". Payment is required within 24 hours.",
            "child_course_booking"
        ));
    }

    return response()->json([
        'message' => 'your seat is booked in this course you have 24 hours to come and pay in person'
    ]);
}

public function myCourses(Request $request, $studentId)
    {
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'student') {
              return response()->json([
                 'message' => 'Forbidden: This is a Student-only area.'
    ], 403);
}
    
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