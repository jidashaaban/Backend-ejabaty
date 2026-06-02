<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SpecialScheduleController extends Controller
{
    public function getMySchedule(Request $request, $userId)
    {
        $requester = $request->user();

        if (!$requester || !in_array($requester->role, ['student', 'teacher'])) {
             return response()->json([
                 'message' => 'Unauthorized: Please provide a valid Student or Teacher ID.'
             ], 403);
        }

        $user = User::find($userId);
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $type = $request->query('type', 'course');
        $masterScheduleId = Schedule::where('type', $type)->latest()->value('id');

        if (!$masterScheduleId) {
            return response()->json(['message' => "No $type schedule found."], 404);
        }

        $sessionsQuery = Session::where('schedule_id', $masterScheduleId);

        if ($user->role === 'student') {
            $sessionsQuery->whereHas('course.students', function($query) use ($user) {
                $query->where('users.id', $user->id)
                      ->where('user_course.is_active', true);
            });

            if ($type === 'course') {
                $sessionsQuery->with(['course', 'hall']); 
            } else {
                $sessionsQuery->with(['course', 'hallAssignments' => function($query) use ($user) {
                    $query->where('student_id', $user->id)->with('hall');
                }]);
            }

        } elseif ($user->role === 'teacher') {
            $sessionsQuery->whereHas('course', function($query) use ($user) {
                $query->where('teacher_id', $user->id);
            });

            if ($type === 'course') {
                $sessionsQuery->with(['course', 'hall']);
            } else {
                $sessionsQuery->with(['course', 'hallAssignments.hall']);
            }
        }

        $sessions = $sessionsQuery->get();

        $formattedSessions = $sessions->map(function($session) use ($user, $type) {
            $hallName = 'غير محدد';

            if ($type === 'course') {
                // Course schedules always check the direct relation column link
                $hallName = $session->hall->name ?? 'غير محدد';
            } else {
                // Exam schedules handle multi-hall distributions cleanly
                if ($user->role === 'student') {
                    $hallName = $session->hallAssignments->first()?->hall?->name ?? 'غير محدد';
                } else {
                    // Extract all unique room tags assigned to this exam layout row
                    $halls = $session->hallAssignments->pluck('hall.name')->unique()->filter()->implode(', ');
                    $hallName = !empty($halls) ? $halls : 'غير محدد';
                }
            }

            return [
                'id'         => $session->id,
                'course'     => $session->course->name ?? '-',
                'day'        => $session->day,
                'date'       => $session->date ? Carbon::parse($session->date)->format('d/m/Y') : '-',
                'start_time' => $session->start_time,
                'end_time'   => $session->end_time,
                'hall'       => $hallName,
                'room_name'  => $hallName,
            ];
        });

        return response()->json([
            'success' => true,
            'viewing_as' => [
                'name' => $user->name,
                'role' => $user->role
            ],
            'type' => $type,
            'sessions' => $formattedSessions
        ]);
    }

    public function filterExamSchedule(Request $request) 
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $userId = $user->id;

        $studentCourseIds = $user->courses()
            ->wherePivot('is_active', true)
            ->pluck('courses.id');

        $latestExamSchedule = Schedule::where('type', 'exam')->latest()->first();

        if (!$latestExamSchedule) {
            return response()->json(['message' => 'No exam schedule found.'], 404);
        }

        $sessions = Session::where('schedule_id', $latestExamSchedule->id)
            ->whereIn('course_id', $studentCourseIds)
            ->with(['course', 'hallAssignments' => function($query) use ($userId) {
                $query->where('student_id', $userId)->with('hall');
            }])
            ->get()
            ->map(function ($session) {
                $hall = $session->hallAssignments->first()?->hall?->name ?? 'غير محدد';
                return [
                    'id'         => $session->id,
                    'course'     => $session->course->name ?? '-',
                    'day'        => $session->day,
                    'date'       => $session->date ? Carbon::parse($session->date)->format('d/m/Y') : '-',
                    'start_time' => $session->start_time,
                    'end_time'   => $session->end_time,
                    'hall'       => $hall,
                    'room_name'  => $hall,
                ];
            });

        return response()->json([
            'success' => true,
            'exams'   => $sessions,
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user(); 
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $this->getMySchedule($request, $user->id);
    }
}