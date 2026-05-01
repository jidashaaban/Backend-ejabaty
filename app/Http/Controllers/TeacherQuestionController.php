<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question; 

class TeacherQuestionController extends Controller
{
    public function pendingQuestions($teacherId)
    {
        // Fetch questions that haven't been answered yet
        $questions = Question::where('teacher_id', $teacherId)
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
        $request->validate([
            'answer' => 'required|string'
        ]);

        $question = Question::findOrFail($questionId);

        $question->update([
            'answer' => $request->answer,
            'is_answered' => true
        ]);

        return response()->json(['message' => 'Answer submitted successfully!', 'data' => $question]);
    }
}
