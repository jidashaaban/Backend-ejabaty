<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Courses;
use App\Models\Quiz;
use App\Models\Exam;
use App\Models\Session;
use App\Models\Schedule;
use App\Models\User;

class TeacherDashboardController extends Controller
{
    
        public function getDashboardStats(Request $request)
    {
        
        $teacher = $request->user();
        if (!$teacher) {
            return response()->json(['error' => 'Unauthenticated. Missing or invalid Bearer Token.'], 401);
        }
        // Security Check: Ensure the logged-in user is actually a teacher [cite: 482]
        if ($teacher->role !== 'teacher') {
            return response()->json(['error' => 'Unauthorized Access'], 403);
        }

        $coursesCount = Courses::where('teacher_id', $teacher->id)->count();

        $quizzesCount = Quiz::where('teacher_id', $teacher->id)->count();

        // 3. Metric: Number of Marking Schemes submitted [cite: 113, 529]
        // We count exams where is_published is true
        $markingSchemesCount = Exam::where('is_published', true)
            ->whereHas('course', function($query) use ($teacher){
                $query->where('teacher_id',$teacher->id);
            })->count();

        // 4. Metric: Special Course Schedule [cite: 192, 193, 210]
        $latestSchedule = Schedule::where('type', 'course')->latest()->first();
        $mySchedule = [];
    
        if ($latestSchedule) {
            $mySchedule = Session::where('schedule_id', $latestSchedule->id)
                ->whereHas('course', function ($query) use ($teacher) {
                    $query->where('teacher_id', $teacher->id); // Filter sessions by teacher 
                })
                ->with(['course:id,name,code','hall:id,name']) 
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'teacher_name' => $teacher->name,
                'metrics' => [
                    'courses_count' => $coursesCount,
                    'quizzes_count' => $quizzesCount,
                    'marking_schemes_count' => $markingSchemesCount,
                ],
                'schedule' => $mySchedule
            ]
        ]);
    }
}
