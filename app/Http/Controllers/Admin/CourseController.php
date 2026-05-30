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
    $admin = auth()->user();
    if (!$admin || $admin->role !== 'admin') {
        return response()->json(['message' => 'Forbidden: Only Administrators can perform this action.'], 403);
    }

    $request->validate([
        'name'         => 'required|string|max:255',
        'code'         => 'required|string|unique:courses',
        'teacher_name' => 'required|string',
        'capacity'     => 'required|integer|min:1',
    ]);

    $teacher = User::where('name', $request->teacher_name)
                   ->where('role', 'teacher')
                   ->first();

    if (!$teacher) {
        return response()->json([
            'success' => false,
            'message' => 'Error: Teacher "' . $request->teacher_name . '" not found.'
        ], 404);
    }

    // ← هنا الإصلاح: نبني البيانات يدوياً بدون teacher_name، ونضيف is_active
    $course = Courses::create([
        'name'       => $request->name,
        'code'       => $request->code,
        'capacity'   => $request->capacity,
        'teacher_id' => $teacher->id,
        'is_active'  => true,   // ← تفعيل المادة فوراً
    ]);

    $teacher->notify(new SchoolNotification(
        "New Course Assigned",
        "Hello " . $teacher->name . ", you have been assigned to teach " . $course->name,
        "course_assignment",
        $course->name
    ));

    $students = User::where('role', 'student')->get();
    foreach ($students as $student) {
        $student->notify(new SchoolNotification(
            "New Course Available!",
            "The course '" . $course->name . "' (Code: " . $course->code . ") is now open for enrollment.",
            "new_course_alert",
            $course->name
        ));
    }

    return response()->json([
        'success' => true,
        'message' => 'Course created successfully and ' . $teacher->name . ' has been notified.',
        'course'  => [
            'id'      => $course->id,
            'name'    => $course->name,
            'teacher' => $teacher->name
        ]
    ]);
}

public function confirmPayment(Request $request)
{
    $request->validate([
        'user_id'   => 'required|exists:users,id',
        'course_id' => 'required|exists:courses,id',
    ]);

    $student = User::findOrFail($request->user_id);
    $course  = Courses::findOrFail($request->course_id);  // ← أضف هاد

    $student->courses()->updateExistingPivot($request->course_id, [
        'status' => 'paid'
    ]);

    $student->notify(new SchoolNotification(
        "Payment Confirmed!",
        "Your seat in " . $course->name . " is now officially booked.",
        "payment_success",
        $course->name
    ));

    return response()->json([
        'message' => 'Payment confirmed! The seat is now permanently booked.'
    ]);
}
    public function index() {
    return response()->json(Courses::all(), 200); // Retrieve all courses [cite: 15]
    }
    public function show($id) {
    $course = Courses::find($id);
    if (!$course) return response()->json(['message' => 'Course not found'], 404);
    return response()->json($course, 200); // Retrieve course by ID
    }
    public function update(Request $request, $id) {
    $course = Courses::find($id);
    if (!$course) return response()->json(['message' => 'Course not found'], 404);
    
    // Ensure fields are in your $fillable array in Course.php [cite: 69, 70]
    $course->update($request->all()); 
    $students = User::where('role', 'student')->get();
    foreach ($students as $student) {
        $student->notify(new SchoolNotification(
            "Course Updated", 
            "The details for {$course->name} have been updated.", 
            "course_update",
            $course->name

        ));
    return response()->json(['message' => 'Course updated successfully', 'course' => $course], 200);

    }
    }
    public function destroy($id) {
    $course = Courses::find($id);
    if (!$course) return response()->json(['message' => 'Course not found'], 404);

    
    $course->delete(); // Delete course by ID
    return response()->json(['message' => 'Course deleted successfully'], 200);
    }
    public function toggleCourseStatus($id)
{
    $course = Courses::findOrFail($id);
    $course->is_active = !$course->is_active; // Flips true to false, or false to true
    $course->save();

    return response()->json([
        'message' => 'Course status updated!',
        'status' => $course->is_active ? 'Active' : 'Inactive',
        'is_active'=>$course->is_active
    ]);
}
}
