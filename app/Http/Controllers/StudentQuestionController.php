<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question; 
use App\Models\User;
use App\Notifications\SchoolNotification;

class StudentQuestionController extends Controller
{
    /**
     * 1. Ask a Question
     * Uses Token to identify the student.
     */
    public function askQuestion(Request $request)
    {
        $student = $request->user(); // Identifies student via Token

        if (!$student || $student->role !== 'student') {
            return response()->json(['message' => 'Forbidden: Students only.'], 403);
        }

        $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'question' => 'required|string'
        ]);

        $question = Question::create([
            'student_id' => $student->id,
            'teacher_id' => $request->teacher_id,
            'question' => $request->question
        ]);

        // Notify Teacher
        $teacher = User::find($request->teacher_id);
        if ($teacher) {
            $teacher->notify(new SchoolNotification(
                "New Student Question",
                "{$student->name} has sent you a new question.",
                "student_question",
                $question->id
            ));
        }

        return response()->json(['message' => 'Question sent successfully!', 'data' => $question]);
    }

    /**
     * 2. View My Questions & Answers
     * Returns Teacher Names and Question Status.
     */
    public function myQuestions(Request $request)
    {
        $student = $request->user();

        $questions = Question::where('student_id', $student->id)
            ->with('teacher:id,name') // Eager load teacher name
            ->orderBy('created_at', 'desc')
            ->get();

        if ($questions->isEmpty()) {
            return response()->json(['message' => 'No questions found.', 'data' => []]);
        }

        // Format to show Names and Answers
        $formatted = $questions->map(function($q) {
            return [
                'id' => $q->id,
                'teacher_name' => $q->teacher->name ?? 'Unknown Teacher',
                'question_text' => $q->question,
                'answer_text' => $q->answer ?? 'Pending teacher response...', // Assumes column is 'answer'
                'status' => $q->answer ? 'Answered' : 'Waiting',
                'asked_on' => $q->created_at->format('Y-m-d H:i'),
            ];
        });

        return response()->json(['success' => true, 'data' => $formatted]);
    }

    /**
     * 3. Update a Question
     * Only allowed if the question hasn't been answered yet.
     */
    public function updateQuestion(Request $request, $id)
    {
        $student = $request->user();
        $question = Question::where('id', $id)->where('student_id', $student->id)->firstOrFail();

        // Security Check: Don't allow edits if the teacher already answered
        if ($question->answer) {
            return response()->json(['message' => 'Cannot edit a question that has already been answered.'], 403);
        }

        $request->validate(['question' => 'required|string']);
        
        $question->update(['question' => $request->question]);

        return response()->json(['message' => 'Question updated!', 'data' => $question]);
    }

    /**
     * 4. Delete a Question
     */
    public function deleteQuestion(Request $request, $id)
    {
        $student = $request->user();
        
        // Find the question and verify ownership
        $question = Question::where('id', $id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        // Security Check: Block deletion if an answer exists
        if ($question->answer) {
            return response()->json([
                'message' => 'Cannot delete a question that has already been answered.'
            ], 403);
        }

        $question->delete();

        return response()->json([
            'message' => 'Question deleted successfully.'
        ]);
    }
}