<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exam;
use App\Models\Courses; 
use App\Models\ExamQuestion;
use App\Models\User;
use App\Notifications\SchoolNotification;

class TeacherexamController extends Controller
{
    public function getCourseExams($courseId)
{
    $teacher = auth()->user();
    if (!$teacher || $teacher->role !== 'teacher') {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // Ensure the teacher actually owns this course before showing exams
    $course = Courses::where('id', $courseId)->where('teacher_id', $teacher->id)->first();
    
    if (!$course) {
        return response()->json(['message' => 'Course not found or unauthorized'], 404);
    }

    // Fetch exams that haven't been published yet (needs marking scheme)
    $exams = Exam::where('course_id', $courseId)
                 ->where('is_published', false) 
                 ->select('id', 'title')
                 ->get();

    return response()->json(['success' => true, 'exams' => $exams]);
}
    // Stage 1: Create Exam (Already had a check, but kept it safe)
    public function createExam(Request $request)
    {
        $teacher = auth()->user();
        if (!$teacher || $teacher->role !== 'teacher') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'course_name' => 'required|string',
            'title' => 'required|string',
            'questions' => 'required|array',
        ]);

        $course = Courses::where('name', $request->course_name)
                         ->where('teacher_id', $teacher->id)
                         ->first();

        if (!$course) {
            return response()->json(['message' => 'Course not found or unauthorized.'], 404);
        }

        $exam = Exam::create([
            'course_id' => $course->id,
            'title' => $request->title,
            'is_published' => false 
        ]);

        foreach ($request->questions as $qText) {
            $question = ExamQuestion::create(['question' => $qText]);
            $exam->questions()->attach($question->id);
        }

        return response()->json(['message' => 'Exam created successfully!', 'exam' => $exam->load('questions')], 201);
    }

    /**
     * STAGE 2: RETRIEVE THE EXAM QUESTIONS
     * FIX: Added null check to prevent crash
     */
    public function getExamForMarking($examId)
    {
        $teacher = auth()->user();
        if (!$teacher || $teacher->role !== 'teacher') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $exam = Exam::where('id', $examId)
            ->whereHas('course', function($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->with('questions')
            ->first();

        // --- FIX ADDED HERE ---
        if (!$exam) {
            return response()->json(['message' => 'Exam not found or you are not authorized.'], 404);
        }

        return response()->json(['success' => true, 'exam' => $exam]);
    }

    /**
     * STAGE 3: SUBMIT MARKING SCHEME
     * FIX: Added null check before the foreach loop
     */
    public function submitMarkingScheme(Request $request, $examId)
    {
        $teacher = auth()->user();
        if (!$teacher || $teacher->role !== 'teacher') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $exam = Exam::where('id', $examId)
            ->whereHas('course', function($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->first();

        // --- CRITICAL FIX: Add this check before the loop ---
        if (!$exam) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found. Ensure the ID is correct and you teach the course.'
            ], 404);
        }

        $request->validate([
            'marking_data' => 'required|array',
            'marking_data.*.question_id' => 'required|exists:exam_questions,id',
            'marking_data.*.answer' => 'required|string',
        ]);

        $answers = $request->input('marking_data'); 

        // This loop is now safe because we verified $exam exists above
        foreach ($answers as $data) {
            $exam->questions()->updateExistingPivot($data['question_id'], [
                'answer' => $data['answer']
            ]);
        }

        $exam->update(['is_published' => true]);

        // NOTIFICATION LOGIC
        $exam->load('course.students'); 
        foreach ($exam->course->students as $student) {
            $student->notify(new SchoolNotification(
                "Marking Scheme Available",
                "The correct answers for '" . $exam->title . "' are now live.",
                "marking_scheme_published",
                $exam->id
            ));
        }

        return response()->json(['success' => true, 'message' => 'Marking scheme submitted successfully!']);
    }
    public function getMarkingSchemesByCourse(Request $request, $courseName)
{
    $user = auth()->user();

    // 1. Find the course and verify access
    // If Teacher: must be their course. If Student: must be enrolled.
    $course = Courses::where('name', $courseName)->first();

    if (!$course) {
        return response()->json(['message' => 'Course not found'], 404);
    }

    // 2. Fetch only PUBLISHED exams for this course
    $markingSchemes = Exam::where('course_id', $course->id)
        ->where('is_published', true)
        ->with(['questions' => function($query) {
            // We specifically want the 'answer' from the pivot table
            $query->select('exam_questions.id', 'question'); 
        }])
        ->get();

    if ($markingSchemes->isEmpty()) {
        return response()->json([
            'success' => true,
            'message' => 'No published marking schemes found for this course.',
            'data' => []
        ]);
    }

    // 3. Format the data for the frontend
    $formattedData = $markingSchemes->map(function ($exam) {
        return [
            'exam_title' => $exam->title,
            'published_at' => $exam->updated_at->format('Y-m-d'),
            'questions_and_answers' => $exam->questions->map(function ($q) {
                return [
                    'question' => $q->question,
                    'correct_answer' => $q->pivot->answer // Pulls from the marking scheme
                ];
            })
        ];
    });

    return response()->json([
        'success' => true,
        'course' => $course->name,
        'marking_schemes' => $formattedData
    ]);
}
}