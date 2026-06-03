<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question; 
use App\Models\User;
use App\Notifications\SchoolNotification;
use Illuminate\Support\Facades\DB;
use App\Models\Courses;


class StudentQuestionController extends Controller
{
    public function getTeachersByCourse(Request $request, $courseId)
    {
        $student = $request->user();

        // 1. Check if student is actually enrolled in this course
        $isEnrolled = DB::table('user_course')
            ->where('user_id', $student->id)
            ->where('course_id', $courseId)
            ->where('is_active', true)
            ->exists();

        if (!$isEnrolled) {
            return response()->json(['message' => 'Forbidden: Not enrolled in this course.'], 403);
        }

        // 2. Fetch the teacher assigned to this course
        $course = Courses::find($courseId);
        if (!$course || !$course->teacher_id) {
            return response()->json(['message' => 'No teacher assigned to this course.'], 404);
        }

        $teacher = User::find($course->teacher_id);

        return response()->json([
            'success' => true,
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name
            ]
        ]);
    }
    /**
     * 1. Ask a Question
     * Uses Token to identify the student.
     */
    public function askQuestion(Request $request)
    {
        $student = $request->user();

        if (!$student || $student->role !== 'student') {
            return response()->json(['message' => 'Forbidden: Students only.'], 403);
        }

        $request->validate([
            'course_id'  => 'required|exists:courses,id',
            'teacher_id' => 'required|exists:users,id',
            'question'   => 'required|string'
        ]);

        // SECURITY VALIDATION: 
        // Ensure the student is enrolled in the course AND the teacher teaches it.
        $isEnrolled = DB::table('user_course')
            ->where('user_id', $student->id)
            ->where('course_id', $request->course_id)
            ->where('is_active', true)
            ->exists();

        $isCorrectTeacher = Courses::where('id', $request->course_id)
            ->where('teacher_id', $request->teacher_id)
            ->exists();

        if (!$isEnrolled || !$isCorrectTeacher) {
            return response()->json([
                'success' => false,
                'message' => 'Security Error: You are not authorized to ask this teacher about this specific course.'
            ], 403);
        }

        // Create the question record
        $question = Question::create([
            'student_id' => $student->id,
            'teacher_id' => $request->teacher_id,
            'course_id'  => $request->course_id,
            'question'   => $request->question
        ]);

        // Notify Teacher
        $teacher = User::find($request->teacher_id);
        $teacher->notify(new SchoolNotification(
            "New Student Question",
            "{$student->name} has sent you a new question for your course.",
            "student_question",
            $question->id
        ));

        return response()->json([
            'success' => true, 
            'message' => 'Question sent successfully!'
        ]);
    }
    public function myQuestions(Request $request)
    {
        $student = $request->user();

        $questions = Question::where('student_id', $student->id)
            ->with(['teacher:id,name', 'course:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        $formatted = $questions->map(function($q) {
            return [
                'id'           => $q->id,
                'course_id'    => $q->course_id,
                'course_name'  => $q->course->name ?? null,
                'teacher_name' => $q->teacher->name ?? 'Unknown Teacher',
                'question_text' => $q->question,
                'answer_text'  => $q->answer ?? 'Pending teacher response...',
                'status'       => $q->answer ? 'Answered' : 'Waiting',
                'asked_on'     => $q->created_at->format('Y-m-d H:i'),
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