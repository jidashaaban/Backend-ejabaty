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
        // 1. Manually find the user by ID
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // 2. Get the schedule type (default to course)
        $type = $request->query('type', 'course');

        // 3. Find the latest Master Schedule of that type
        $masterScheduleId = Schedule::where('type', $type)->latest()->value('id');

        if (!$masterScheduleId) {
            return response()->json(['message' => "No $type schedule found."], 404);
        }

        // 4. Filter sessions based on Role and include Hall Assignments
        $sessionsQuery = Session::where('schedule_id', $masterScheduleId);

        if ($user->role === 'student') {
            // Filter sessions where the student is enrolled
            $sessionsQuery->whereHas('course.students', function($query) use ($user) {
                $query->where('users.id', $user->id);
            })
            // IMPORTANT: Only pull the hall assigned to THIS specific student [cite: 184, 194]
            ->with(['course', 'hallAssignments' => function($query) use ($userId) {
                $query->where('student_id', $userId)->with('hall');
            }]);
        } elseif ($user->role === 'teacher') {
            // Teachers usually don't have specific hall assignments in this logic, 
            // but we fetch the course info normally.
            $sessionsQuery->whereHas('course', function($query) use ($user) {
                $query->where('teacher_id', $user->id);
            })->with('course');
        }

        $sessions = $sessionsQuery->get();

        // 5. Clean up the output so the hall name is easy for the frontend to read 
        $formattedSessions = $sessions->map(function($session) use ($user) {
            return [
                'id' => $session->id,
                'course' => $session->course->name,
                'day' => $session->day,
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                // Extract the hall name if it exists, otherwise return 'No Hall' 
                'hall' => ($user->role === 'student') 
                    ? ($session->hallAssignments->first()->hall->name ?? 'No Hall Assigned') 
                    : 'N/A',
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