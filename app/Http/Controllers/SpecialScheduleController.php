<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\Session;
use App\Models\User;

class SpecialScheduleController extends Controller
{
    public function getMySchedule(Request $request, $userId)
    {
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
}