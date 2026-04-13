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

        // 4. Filter sessions based on Role
        $sessionsQuery = Session::where('schedule_id', $masterScheduleId);

        if ($user->role === 'student') {
            // Filter sessions where the student is enrolled in the course
            $sessionsQuery->whereHas('course.students', function($query) use ($user) {
                $query->where('users.id', $user->id);
            });
        } elseif ($user->role === 'teacher') {
            // Filter sessions where the user is the assigned teacher
            $sessionsQuery->whereHas('course', function($query) use ($user) {
                $query->where('teacher_id', $user->id);
            });
        }

        $sessions = $sessionsQuery->with('course')->get();

        return response()->json([
            'success' => true,
            'viewing_as' => [
                'name' => $user->name,
                'role' => $user->role
            ],
            'type' => $type,
            'sessions' => $sessions
        ]);
    }
}