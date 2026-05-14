<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Schedule;

class ParentDashboardController extends Controller
{
    public function getChildProgress(Request $request, $childId)
    {
        $parent = auth()->user();

        if (!$parent || $parent->role !== 'parent') {
              return response()->json([
                'message' => 'Forbidden: Only Parents can view this dashboard.'
    ], 403);
} 
        if(!$parent){
            return response()->json(['error'=>'Parent account not found'],404);
        }
        $student = $parent->children()->where('users.id', $childId)->with(['exams.course','quizzes.course'])->first();
        if (!$student) {
            return response()->json(['message' => 'Student does not belong to this account or does not exist.'], 403);
        }

        // Fetch Exam Marks (Using the pivot logic you already built)
        $exams = $student->exams()->where('is_published', true)->get()->map(function ($exam) {
            return [
                'course_name' => $exam->course->name ?? 'Unknown Course',
                'mark' => $exam->pivot->mark,
                
            ];
        });

        // Fetch Quiz Points (Using the optional pivot logic)
        $quizzes = $student->quizzes()->get()->map(function ($quiz) {
            return [
                'course_name' => $quiz->course->name ?? 'Unknown Course',
                'quiz_date'=>$quiz->quiz_date,
                'points' => $quiz->pivot->points ?? 'Not Graded',
                'comment' => $quiz->pivot->comment ?? 'No Comment',
                
            ];
        });

        return response()->json([
            'success' => true,
            'student_name' => $student->name,
            'exam_progress' => $exams->isEmpty() ? 'No exam history' : $exams,
            'quiz_progress' => $quizzes->isEmpty() ? 'No quiz history' : $quizzes,
        ]);
    }

    public function getChildExamSchedule(Request $request, $childId)
    {
        $parent = auth()->user();

       if (!$parent || $parent->role !== 'parent') {
              return response()->json([
                 'message' => 'Forbidden: Only Parents can view this dashboard.'
    ], 403);
}
    
    if (!$parent) {
        return response()->json(['error' => 'Parent account not found.'], 404);
    }

        $student = $parent->children()->where('users.id', $childId)->with('courses')->first();

        if (!$student) {
            return response()->json(['message' => 'You do not have permission to view this student\'s schedule.'], 403);
        }

        // We specifically look for the latest 'exam' schedule
        $masterSchedule = Schedule::where('type', 'exam')->latest()->first();

        if (!$masterSchedule) {
            return response()->json(['message' => 'No exam schedule has been generated yet.'], 404);
        }

        $studentCourseIds = $student->courses->pluck('id');

        $specialSchedule = $masterSchedule->sessions()
            ->whereIn('course_id', $studentCourseIds)
            ->with('course')
            ->get();

        return response()->json([
            'success' => true,
            'student_name' => $student->name,
            'master_schedule_id' => $masterSchedule->id,
            'exam_schedule' => $specialSchedule
        ]);
    }
}
