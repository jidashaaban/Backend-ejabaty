<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Courses;
use App\Models\User;
use Carbon\Carbon;
use App\Notifications\SchoolNotification;

class StudentCourseController extends Controller
{
    public function availableCourses()
    {
        // جيب المستخدم الحالي
        $student = auth()->user();

        // IDs المواد اللي سجّل فيها الطالب مسبقاً
        $joinedCourseIds = $student
            ? $student->courses()->pluck('courses.id')->toArray()
            : [];

        // ✅ تم حذف شرط التصفية لجلب جميع المواد (المفعّلة وغير المفعّلة) المتاحة للتسجيل
        $courses = Courses::with('teacher')
            ->whereNotIn('id', $joinedCourseIds) // ← استبعد فقط المواد التي سجّل فيها الطالب مسبقاً
            ->get();

        $formattedCourses = $courses->map(function ($course) {
            return [
                'id'           => $course->id,
                'name'         => $course->name,
                'code'         => $course->code,
                'teacher_name' => $course->teacher->name ?? 'غير محدد',
                'is_active'    => $course->is_active, // سيمرر القيمة الفرونت إند ليميز هل هي مفعّلة أم لا
                'capacity'     => $course->capacity,
                'created_at'   => $course->created_at,
            ];
        });

        return response()->json([
            'success'           => true,
            'available_courses' => $formattedCourses
        ]);
    }

    // 2. The logic when the student presses "Join"
    public function joinCourse(Request $request, $courseId)
    {
        // 1. Get the currently logged-in student from the Token
        $student = auth()->user();

        // Security check: Ensure the user is actually a student
        if (!$student || $student->role !== 'student') {
            return response()->json([
                'message' => 'Forbidden: This is a Student-only area.'
            ], 403);
        }

        // 2. Find the course using the ID from the URL
        $course = Courses::findOrFail($courseId);

        // 3. Check if the course is already full
        $currentStudentsCount = $course->students()->count();

        if ($currentStudentsCount >= $course->capacity) {
            return response()->json([
                'message' => 'Capacity is full'
            ], 400);
        }

        // 4. Check if the student already booked this course
        $alreadyJoined = $student->courses()->where('course_id', $courseId)->exists();

        if ($alreadyJoined) {
            return response()->json(['error' => 'You have already booked a seat in this course.'], 400);
        }

        // 5. Attach the student to the course with pivot data
        $student->courses()->attach($courseId, [
            'status' => 'pending_payment',
            'booked_at' => now(),
            'is_active' => false,
        ]);

        // 6. Notify the Student
        $student->notify(new SchoolNotification(
            "Course Seat Reserved",
            "You have booked a seat in " . $course->name . ". Please visit the administration within 24 hours to complete your payment.",
            "course_joined_pending",
            $course->name
        ));

        // 7. Notify the Parent(s)
        $student->load('parents');
        foreach ($student->parents as $parent) {
            $parent->notify(new SchoolNotification(
                "New Course Booking",
                $student->name . " has reserved a seat in " . $course->name . ". Payment is required within 24 hours.",
                "child_course_booking",
                $course->name
            ));
        }

        return response()->json([
            'success' => true,
            'message' => 'Your seat is booked. Please complete the payment within 24 hours.'
        ]);
    }

    public function myCourses(Request $request)
    {
        // 1. Get the student from the token
        $student = $request->user();

        // Security Check: Ensure the user is a student
        if (!$student || $student->role !== 'student') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 2. Fetch the courses linked to this student
        $courses = $student->courses()->with('teacher')->get();

        // 3. Format the data for your React frontend
        $formatted = $courses->map(function ($course) {
            return [
                'id' => $course->id,
                'name' => $course->name,
                'code' => $course->code,
                'teacher_name' => $course->teacher->name ?? 'N/A',
                'status' => $course->pivot->status ?? 'unknown', 
                'student_course_active' => (bool) ($course->pivot->is_active ?? false),
            ];
        });

        return response()->json([
            'success' => true,
            'courses' => $formatted
        ]);
    }
}