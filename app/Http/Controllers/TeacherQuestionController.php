<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question; 
use App\Models\User;

class TeacherQuestionController extends Controller
{
    public function pendingQuestions(Request $request)
    {
        $requester = auth()->user();
        if (!$requester || $requester->role !== 'teacher') {
              return response()->json([
                 'message' => 'Forbidden: Only Teachers can access this feature.'
    ], 403);
}
        // Fetch questions that haven't been answered yet
        $questions = Question::where('teacher_id', $requester->id)
            ->where('is_answered', false)
            ->with('student:id,name') 
            ->get();

        if ($questions->isEmpty()) {
             return response()->json(['message' => 'No new questions.']);
        }

        return response()->json($questions);
    }

    // 2. Teacher answers a specific question
    public function answerQuestion(Request $request, $questionId)
    {
        $requester = auth()->user();
        if (!$requester || $requester->role !== 'teacher') {
              return response()->json([
                  'message' => 'Forbidden: Only Teachers can access this feature.'
    ], 403);
}
        $request->validate([
            'answer' => 'required|string'
        ]);

        $question = Question::findOrFail($questionId);

        $question->update([
            'answer' => $request->answer,
            'is_answered' => true
        ]);
        // --- NOTIFICATION LOGIC ---
       // 1. Find the Student who asked the question
       $student = User::find($question->student_id);
    
       // 2. Find the Teacher (for the message)
       $teacher = User::find($request->query('requester_id'));

       if ($student) {
           $student->notify(new SchoolNotification(
               "Question Answered!",
               "Teacher " . ($teacher->name ?? 'assigned to the course') . " has responded to your question.",
               "question_answered",
               $question->id
        ));
    }

        return response()->json(['message' => 'Answer submitted successfully!', 'data' => $question]);
    }
}
