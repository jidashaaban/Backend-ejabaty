<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question; 
use App\Models\User;
use App\Notifications\SchoolNotification;

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
        // 1. Find the Teacher who needs to answer
        $teacher = User::find($request->teacher_id);
    
    // 2. Find the Student who is asking (for the message)
        $student = User::find($request->student_id);

        if ($teacher && $student) {
            $teacher->notify(new SchoolNotification(
               "New Student Question",
               $student->name . " has sent you a new question regarding your course.",
               "student_question"
        ));
    }

        return response()->json(['message' => 'Question sent successfully!', 'data' => $question]);
    }

    // 2. Student views their asked questions and the answers
    public function myQuestions(Request $request,$studentId)
    {
        $requester = auth()->user();

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
