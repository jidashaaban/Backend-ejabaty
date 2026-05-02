<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question; 

class StudentQuestionController extends Controller
{
    public function askQuestion(Request $request)
    {
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'student') {
                 return response()->json([
                    'message' => 'Forbidden: This is a Student-only area.'
    ], 403);
}
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'teacher_id' => 'required|exists:users,id',
            'question' => 'required|string'
        ]);

        $question = Question::create([
            'student_id' => $request->student_id,
            'teacher_id' => $request->teacher_id,
            'question' => $request->question
        ]);

        return response()->json(['message' => 'Question sent successfully!', 'data' => $question]);
    }

    // 2. Student views their asked questions and the answers
    public function myQuestions($studentId)
    {
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'student') {
                  return response()->json([
                       'message' => 'Forbidden: This is a Student-only area.'
    ], 403);
}
        $questions = Question::where('student_id', $studentId)
            ->with('teacher:id,name') // Get the teacher's name so the student knows who it was sent to
            ->orderBy('created_at', 'desc')
            ->get();

        if ($questions->isEmpty()) {
             return response()->json(['message' => 'You have not asked any questions yet.']);
        }

        return response()->json($questions);
    }
}
