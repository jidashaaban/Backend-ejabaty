<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\Session;
use App\Models\User;
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
                $query->where('users.id', $user->id);
            });

            // UPDATE: Load 'hall' for courses and 'hallAssignments' for exams [cite: 48, 144]
            if ($type === 'course') {
                $sessionsQuery->with(['course', 'hall']); // Direct relationship for courses [cite: 43, 48]
            } else {
                $sessionsQuery->with(['course', 'hallAssignments' => function($query) use ($userId) {
                    $query->where('student_id', $userId)->with('hall');
                }]);
            }

        } elseif ($user->role === 'teacher') {
            $sessionsQuery->whereHas('course', function($query) use ($user) {
                $query->where('teacher_id', $user->id);
            })->with(['course', 'hall']); // Teachers also see the direct hall for courses 
        }

        $sessions = $sessionsQuery->get();

        $formattedSessions = $sessions->map(function($session) use ($user, $type) {
            // Determine the hall name based on the schedule type [cite: 52]
            $hallName = 'No Hall Assigned';

            if ($type === 'course') {
                // For courses, use the direct relationship on the session [cite: 43, 52]
                $hallName = $session->hall->name ?? 'No Hall Assigned';
            } else {
                // For exams, use the student-specific assignment [cite: 146]
                $hallName = $session->hallAssignments->first()->hall->name ?? 'No Hall Assigned';
            }

            return [
                'id' => $session->id,
                'course' => $session->course->name,
                'day' => $session->day,
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'hall' => ($user->role === 'teacher' && $type === 'exam') ? 'Check Hall List' : $hallName,
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
    if (!$user ) {
        return response()->json(['message' => 'Unauthorized'], 403);
    } 
    $userId = $user->id;
    // 1. Find the student and their enrolled course IDs [cite: 129]
    $studentCourseIds = $user->courses()->pluck('courses.id');

    // 2. Find the latest "exam" type master schedule [cite: 22, 124]
    $latestExamSchedule = Schedule::where('type', 'exam')->latest()->first();

    if (!$latestExamSchedule) {
        return response()->json(['message' => 'No exam schedule found.'], 404);
    }

    // 3. Fetch only the sessions matching those courses and simplify the output [cite: 64, 120]
    $tasks = Session::where('schedule_id', $latestExamSchedule->id)
        ->whereIn('course_id', $studentCourseIds)
        ->with('course')
        ->get()
        ->map(function ($session) {
            return [
                'exam_name' => $session->course->name, // Displays only the name [cite: 23, 27]
                'date' => Carbon::parse($session->date)->format('d/m/Y') // Displays only the day/date [cite: 22, 122]
            ];
        });

    return response()->json($tasks);
}
    public function index(Request $request)
    {
        // SECURE: Automatically detects user from the Bearer Token
        $user = $request->user(); 
        $type = $request->query('type', 'course');
        
        $masterScheduleId = Schedule::where('type', $type)->latest()->value('id');

        if (!$masterScheduleId) {
            return response()->json(['message' => "No $type schedule found."], 404);
        }

        $sessionsQuery = Session::where('schedule_id', $masterScheduleId);

        if ($user->role === 'student') {
            $sessionsQuery->whereHas('course.students', function($query) use ($user) {
                $query->where('users.id', $user->id);
            });

            if ($type === 'course') {
                $sessionsQuery->with(['course', 'hall']);
            } else {
                // Gets student-specific exam hall assignments
                $sessionsQuery->with(['course', 'hallAssignments' => function($query) use ($user) {
                    $query->where('student_id', $user->id)->with('hall');
                }]);
            }
        } elseif ($user->role === 'teacher') {
            $sessionsQuery->whereHas('course', function($query) use ($user) {
                $query->where('teacher_id', $user->id);
            })->with(['course', 'hall']); 
        }

        $sessions = $sessionsQuery->get();

        $formattedSessions = $sessions->map(function($session) use ($user, $type) {
            $hallName = 'No Hall Assigned';
            if ($type === 'course') {
                $hallName = $session->hall->name ?? 'No Hall Assigned';
            } else {
                $hallName = $session->hallAssignments->first()->hall->name ?? 'Check Hall List';
            }

            return [
                'id' => $session->id,
                'course' => $session->course->name,
                'day' => $session->day,
                'date' => $session->date ? Carbon::parse($session->date)->format('d/m/Y') : null,
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'hall' => ($user->role === 'teacher' && $type === 'exam') ? 'Check Hall List' : $hallName,
            ];
        });

        return response()->json([
            'success' => true,
            'viewing_as' => ['name' => $user->name, 'role' => $user->role],
            'sessions' => $formattedSessions
        ]);
    }

}