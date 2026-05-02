<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; 
use App\Models\Exam;

class StudentMarkingSchemeController extends Controller
{
    public function getMyExamsAndMarks(Request $request,$studentId)
    {
        $requester = User::find($request->query('requester_id'));

    // Ensure the requester is a student AND they are looking at THEIR OWN data
    if (!$requester || $requester->role !== 'student' || $requester->id != $studentId) {
        return response()->json([
            'message' => 'Unauthorized: You can only view your own marking schemes.'
        ], 403);
    }
        // 1. Find the student and their exams (including the pivot 'mark')
        $student = User::with(['exams' => function($query) {
            // Only get exams that are published
            $query->where('is_published', true)
                  // Load the questions and the pivot 'answer' (the marking scheme)
                  ->with(['questions' => function($q) {
                      $q->select('exam_questions.id', 'exam_questions.question') // the "what"
                        ->withPivot('answer'); // the contextual answer
                  }]); 
        }])->findOrFail($studentId);

        if ($student->exams->isEmpty()) {
        return response()->json([
            'message' => 'no exam history',
            'data' => []
        ], 200); // Keeping it as 200 (Success) so the frontend doesn't crash, but gives the clear message
    }

        // 2. Format the data nicely for the frontend
        $examHistory = $student->exams->map(function($exam) {
            return [
                'exam_id' => $exam->id,
                'title' => $exam->title,
                'course_id' => $exam->course_id,
                'my_mark' => $exam->pivot->mark, // Pulled from the exam_student pivot
                'marking_scheme' => $exam->questions->map(function($question) {
                    return [
                        'question_id' => $question->id,
                        'question_text' => $question->question,
                        'correct_answer' => $question->pivot->answer,
                    ];
                })
            ];
        });

        // 3. Return the payload
        // Students hit one endpoint and get a list of all their subjects' marking schemes (Questions + Answers) in one clean JSON object[cite: 71].
        return response()->json([
            'message' => 'Exam history and marking schemes retrieved successfully.',
            'data' => $examHistory
        ], 200);
    }
}
