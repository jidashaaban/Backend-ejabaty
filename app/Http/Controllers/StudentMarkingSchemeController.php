<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; 
use App\Models\Exam;

class StudentMarkingSchemeController extends Controller
{
    public function getMyExamsAndMarks(Request $request)
{
    // 1. Get the authenticated student from the Token
    $student = $request->user();

    // 2. Security Check
    if (!$student || $student->role !== 'student') {
        return response()->json([
            'message' => 'Unauthorized: This area is for students only.'
        ], 403);
    }

    // 3. Fetch the data using the student's ID
    // We query the User model to load the nested relationships
    $studentData = User::with(['exams' => function($query) {
        $query->where('is_published', true) // Only show marks if the exam is published
              ->with(['course','questions' => function($q) {
                  $q->select('exam_questions.id', 'exam_questions.question')
                    ->withPivot('answer'); // The correct answer for the marking scheme
              }]); 
    }])->findOrFail($student->id);

    // 4. Handle Case: No exams found
    if ($studentData->exams->isEmpty()) {
        return response()->json([
            'message' => 'No exam history found.',
            'data' => []
        ], 200);
    }

    // 5. Format the data for your React Frontend
    $examHistory = $studentData->exams->map(function($exam) {
        return [
            'exam_id' => $exam->id,
            'title' => $exam->title,
            'course_name' => $exam->course->name ?? 'Unknown Course',
            'my_mark' => $exam->pivot->mark, // Pulled from the exam_student pivot table
            'marking_scheme' => $exam->questions->map(function($question) {
                return [
                    'question_text' => $question->question,
                    'correct_answer' => $question->pivot->answer, // From exam_question pivot
                ];
            })
        ];
    });

    return response()->json([
        'message' => 'Exam history and marking schemes retrieved successfully.',
        'data' => $examHistory
    ], 200);
}
}
