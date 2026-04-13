<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\Session;
use App\Models\User;

class SpecialScheduleController extends Controller
{
    public function getMySchedule($userId)
    {
        // 1. Manually find the user by the ID passed in the URL
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json(['message' => 'User ID ' . $userId . ' not found in database.'], 404);
        }

        // 2. Find the LATEST Master Schedule (Course type)
        $masterScheduleId = Schedule::where('type', 'course')->latest()->value('id');

        if (!$masterScheduleId) {
            return response()->json(['message' => 'No Master Schedule exists. Generate one first!'], 404);
        }

        // 3. Filter sessions based on the user's role
        $sessionsQuery = Session::where('schedule_id', $masterScheduleId);

        if ($user->role === 'student') {
            // Filter sessions where this student is enrolled
            $sessionsQuery->whereHas('course.students', function($query) use ($user) {
                $query->where('users.id', $user->id);
            });
        } elseif ($user->role === 'teacher') {
            // Filter sessions where this user is the teacher
            $sessionsQuery->whereHas('course', function($query) use ($user) {
                $query->where('teacher_id', $user->id);
            });
        }

        $sessions = $sessionsQuery->with('course')->get();

        return response()->json([
            'success' => true,
            'viewing_as' => [
                'name' => $user->name,
                'role' => $user->role,
                'id'   => $user->id
            ],
            'master_schedule_id' => $masterScheduleId,
            'sessions' => $sessions
        ]);
    }
}