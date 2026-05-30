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
        // أظهر الامتحانات اللي للطالب علامة فيها (mark مش null)
        $query->wherePivotNotNull('mark')
              ->with(['course']); 
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
            'exam_id'     => $exam->id,
            'title'       => $exam->title,
            'course_name' => $exam->course->name ?? 'Unknown Course',
            'my_mark'     => $exam->pivot->mark,
            'published_at'=> $exam->updated_at ? $exam->updated_at->format('Y-m-d') : null,
        ];
    });

    return response()->json([
        'message' => 'Exam history and marking schemes retrieved successfully.',
        'data' => $examHistory
    ], 200);
}

    /**
     * GET /student/exam-papers
     * Returns all published exams for the student's enrolled active courses,
     * including exam questions and correct answers (marking scheme).
     */
    public function getMyExamPapers(Request $request)
    {
        $student = $request->user();

        if (!$student || $student->role !== 'student') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get active course IDs for this student
        $activeCourseIds = $student->courses()
            ->wherePivot('is_active', true)
            ->pluck('courses.id');

        // Get published exams for those courses with questions + answers
        $exams = \App\Models\Exam::whereIn('course_id', $activeCourseIds)
            ->where('is_published', true)
            ->with([
                'course:id,name,code',
                'questions' => function ($q) {
                    $q->select('exam_questions.id', 'question');
                }
            ])
            ->orderBy('updated_at', 'desc')
            ->get();

        $formatted = $exams->map(function ($exam) use ($student) {
            // Check if student has a mark for this exam
            $markRecord = $exam->students()->where('student_id', $student->id)->first();

            return [
                'exam_id'      => $exam->id,
                'title'        => $exam->title,
                'course_name'  => $exam->course->name ?? '-',
                'course_code'  => $exam->course->code ?? '-',
                'published_at' => $exam->updated_at->format('Y-m-d'),
                'my_mark'      => $markRecord ? $markRecord->pivot->mark : null,
                'questions'    => $exam->questions->map(function ($q) {
                    return [
                        'id'             => $q->id,
                        'question'       => $q->question,
                        'correct_answer' => $q->pivot->answer,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $formatted,
        ]);
    }

}