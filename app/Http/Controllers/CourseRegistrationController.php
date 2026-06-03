<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Courses;
use App\Models\User;
use App\Notifications\SchoolNotification;

class CourseRegistrationController extends Controller
{

    public function registerCourse(Request $request, $courseId)
    {
        $student = auth()->user();
        if (!$student || $student->role !== 'student') {
            return response()->json([
                'message' => 'ممنوع: هذا المجال مخصص للطلاب فقط'
            ], 403);
        }

        $course = Courses::findOrFail($courseId);

        $existingRegistration = $student->courses()
            ->where('course_id', $courseId)
            ->exists();

        if ($existingRegistration) {
            return response()->json([
                'message' => 'أنت مسجل بالفعل على هذه المادة'
            ], 400);
        }

        $student->courses()->attach($courseId, [
            'status' => 'pending',
            'booked_at' => now(),
            'is_active' => false 
        ]);

        $student->notify(new SchoolNotification(
            "تم استقبال طلب التسجيل",
            "تم استقبال طلب تسجيلك على مادة '{$course->name}'. سيتم تفعيلها بعد موافقة الإدارة.",
            "course_registration_pending",
            $course->name
        ));

        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new SchoolNotification(
                "طلب تسجيل مادة جديد",
                "الطالب '{$student->name}' يطلب التسجيل على مادة '{$course->name}'",
                "course_registration_request",
                $course->name
            ));
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال طلب التسجيل بنجاح. سيتم تفعيل المادة بعد موافقة الإدارة.'
        ]);
    }

    public function getPendingRegistrations()
    {
        $admin = auth()->user();
        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'message' => 'ممنوع: هذا المجال مخصص للمسؤولين فقط'
            ], 403);
        }

        $pendingRegistrations = \DB::table('user_course')
            ->join('users', 'user_course.user_id', '=', 'users.id')
            ->join('courses', 'user_course.course_id', '=', 'courses.id')
            ->where('user_course.is_active', false)
            ->where('user_course.status', 'pending')
            ->select(
                'user_course.id',
                'user_course.user_id',
                'user_course.course_id',
                'users.name as student_name',
                'users.email as student_email',
                'courses.name as course_name',
                'courses.code as course_code',
                'user_course.booked_at',
                'user_course.status'
            )
            ->orderBy('user_course.booked_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'pending_registrations' => $pendingRegistrations,
            'count' => count($pendingRegistrations)
        ]);
    }


    public function activateCourse(Request $request)
    {
        $admin = auth()->user();
        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'message' => 'ممنوع: هذا المجال مخصص للمسؤولين فقط'
            ], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
        ]);

        $student = User::findOrFail($request->user_id);
        $course = Courses::findOrFail($request->course_id);

        $registration = $student->courses()
            ->where('course_id', $request->course_id)
            ->first();

        if (!$registration) {
            return response()->json([
                'message' => 'لم يتم العثور على تسجيل لهذا الطالب على هذه المادة'
            ], 404);
        }

        $student->courses()->updateExistingPivot($request->course_id, [
            'is_active' => true,
            'status' => 'active'
        ]);

        $student->notify(new SchoolNotification(
            "تم تفعيل المادة",
            "تم تفعيل مادة '{$course->name}' في برنامجك. يمكنك الآن الوصول إليها.",
            "course_activated",
            $course->name
        ));

        $student->load('parents');
        foreach ($student->parents as $parent) {
            $parent->notify(new SchoolNotification(
                "تم تفعيل مادة جديدة",
                "تم تفعيل مادة '{$course->name}' لـ {$student->name}",
                "child_course_activated",
                $course->name
            ));
        }

        return response()->json([
            'success' => true,
            'message' => "تم تفعيل مادة '{$course->name}' للطالب '{$student->name}' بنجاح."
        ]);
    }

    public function rejectRegistration(Request $request)
    {
        $admin = auth()->user();
        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'message' => 'ممنوع: هذا المجال مخصص للمسؤولين فقط'
            ], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
            'reason' => 'nullable|string|max:500'
        ]);

        $student = User::findOrFail($request->user_id);
        $course = Courses::findOrFail($request->course_id);

        $student->courses()->detach($request->course_id);

        $reason = $request->reason ?? 'لم يتم تحديد سبب';
        $student->notify(new SchoolNotification(
            "تم رفض طلب التسجيل",
            "تم رفض طلب تسجيلك على مادة '{$course->name}'. السبب: {$reason}",
            "course_registration_rejected",
            $course->name
        ));

        return response()->json([
            'success' => true,
            'message' => "تم رفض طلب التسجيل للطالب '{$student->name}' بنجاح."
        ]);
    }

    public function getActiveCourses()
    {
        $student = auth()->user();
        if (!$student || $student->role !== 'student') {
            return response()->json([
                'message' => 'ممنوع: هذا المجال مخصص للطلاب فقط'
            ], 403);
        }

        $activeCourses = $student->courses()
            ->with('teacher')
            ->wherePivot('is_active', true)
            ->wherePivot('status', 'active')
            ->get()
            ->map(function ($course) {
                return [
                    'id'           => $course->id,
                    'name'         => $course->name,
                    'code'         => $course->code,
                    'teacher_id'   => $course->teacher_id,
                    'teacher_name' => $course->teacher->name ?? 'غير محدد',
                    'status'       => $course->pivot->status,
                    'is_active'    => $course->pivot->is_active,
                    'booked_at'    => $course->pivot->booked_at,
                ];
            });

        return response()->json([
            'success'        => true,
            'active_courses' => $activeCourses,
            'count'          => count($activeCourses),
        ]);
    }

    public function getPendingCourses()
    {
        $student = auth()->user();
        if (!$student || $student->role !== 'student') {
            return response()->json([
                'message' => 'ممنوع: هذا المجال مخصص للطلاب فقط'
            ], 403);
        }

        $pendingCourses = $student->courses()
            ->wherePivot('is_active', false)
            ->with('teacher')
            ->get()
            ->map(function ($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'code' => $course->code,
                    'teacher_name' => $course->teacher->name ?? 'غير محدد',
                    'status' => $course->pivot->status,
                    'is_active' => $course->pivot->is_active,
                    'booked_at' => $course->pivot->booked_at
                ];
            });

        return response()->json([
            'success' => true,
            'pending_courses' => $pendingCourses,
            'count' => count($pendingCourses)
        ]);
    }
}
