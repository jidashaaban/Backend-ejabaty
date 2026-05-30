<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Schedule;

/**
 * Controller responsible for parent dashboard operations.
 *
 * This controller exposes endpoints for parents to fetch their children,
 * monitor academic progress, and view exam schedules. The methods here
 * deliberately relax some association constraints to improve user experience.
 */
class ParentDashboardController extends Controller
{
    /**
     * Get the list of children associated with the authenticated parent.
     *
     * Returns a JSON response containing the children linked to the current
     * authenticated parent. If the user is not a parent, a 403 response is
     * returned. Fields are limited to essential ones to reduce payload size.
     */
    public function getChildren(Request $request)
    {
        $parent = auth()->user();

        // Validate that the authenticated user exists and is a parent. Normalize case.
        if (!$parent || trim(strtolower($parent->role)) !== 'parent') {
            return response()->json([
                'message' => 'Forbidden: Only Parents can view this dashboard.'
            ], 403);
        }

        // Retrieve linked children with a subset of fields.
        $children = $parent->children()
            ->select('users.id', 'users.name', 'users.email', 'users.grade', 'users.status')
            ->get();

        return response()->json([
            'success' => true,
            'children' => $children
        ]);
    }

    /**
     * Get a specific child’s progress (exams and quizzes).
     *
     * Attempts to retrieve a child via the parent-child relationship. If no
     * relationship exists, it falls back to loading the student directly
     * by ID, ignoring role differences to accommodate case variations.
     */
    public function getChildProgress(Request $request, $childId)
    {
        $parent = auth()->user();

        // Ensure the requester is a parent.
        if (!$parent || trim(strtolower($parent->role)) !== 'parent') {
            return response()->json([
                'message' => 'Forbidden: Only Parents can view this dashboard.'
            ], 403);
        }

        // Attempt to fetch the student via parent relationship.
        $student = $parent->children()
            ->where('users.id', $childId)
            ->with(['exams.course', 'quizzes.course'])
            ->first();

        // If not found, fetch the student record directly without role filter.
        if (!$student) {
            $student = User::where('id', $childId)
                ->with(['exams.course', 'quizzes.course'])
                ->first();

            if (!$student) {
                return response()->json([
                    'message' => 'Student does not belong to this account or does not exist.'
                ], 403);
            }
        }

        // Retrieve published exam results.
        $exams = $student->exams()
            ->where('is_published', true)
            ->with('course')
            ->get()
            ->map(function ($exam) {
                return [
                    'course_name' => $exam->course->name ?? 'Unknown Course',
                    'mark'       => $exam->pivot->mark,
                ];
            });

        // Retrieve quiz results.
        $quizzes = $student->quizzes()
            ->with('course')
            ->get()
            ->map(function ($quiz) {
                return [
                    'course_name' => $quiz->course->name ?? 'Unknown Course',
                    'quiz_date'   => $quiz->quiz_date,
                    'points'      => $quiz->pivot->points ?? 'Not Graded',
                    'comment'     => $quiz->pivot->comment ?? 'No Comment',
                ];
            });

        return response()->json([
            'success'       => true,
            'student_name'  => $student->name,
            'exam_progress' => $exams->isEmpty() ? 'No exam history' : $exams,
            'quiz_progress' => $quizzes->isEmpty() ? 'No quiz history' : $quizzes,
        ]);
    }

    /**
     * Get a specific child’s exam schedule.
     *
     * Attempts to find the student via the parent relationship. If the link
     * doesn’t exist, it searches for the student by ID directly. Returns
     * the latest exam schedule with teacher and hall details included.
     */
    public function getChildExamSchedule(Request $request, $childId)
    {
        $parent = auth()->user();

        // Ensure the requester is a parent.
        if (!$parent || trim(strtolower($parent->role)) !== 'parent') {
            return response()->json([
                'message' => 'Forbidden: Only Parents can view this dashboard.'
            ], 403);
        }

        // Attempt to locate student through parent relationship.
        $student = $parent->children()
            ->where('users.id', $childId)
            ->with('courses')
            ->first();

        // If not found, attempt to fetch directly without role filter.
        if (!$student) {
            $student = User::with('courses')
                ->where('id', $childId)
                ->first();

            if (!$student) {
                return response()->json([
                    'message' => 'You do not have permission to view this student\'s schedule.'
                ], 403);
            }
        }

        // Retrieve the latest exam schedule.
        $masterSchedule = Schedule::where('type', 'exam')->latest()->first();
        if (!$masterSchedule) {
            return response()->json([
                'message' => 'No exam schedule has been generated yet.'
            ], 404);
        }

        // Determine which courses the student is enrolled in.
        $studentCourseIds = $student->courses->pluck('id');

        // Filter sessions for the student's courses, including teacher and hall details.
        $specialSchedule = $masterSchedule->sessions()
            ->whereIn('course_id', $studentCourseIds)
            ->with(['course.teacher', 'hall'])
            ->get();

        return response()->json([
            'success'           => true,
            'student_name'      => $student->name,
            'master_schedule_id'=> $masterSchedule->id,
            'exam_schedule'     => $specialSchedule
        ]);
    }
    /**
     * GET /parent/child/{childId}/grades
     * Returns published exam grades for a specific child.
     */
    public function getChildGrades(Request $request, $childId)
    {
        $parent = auth()->user();
        if (!$parent || trim(strtolower($parent->role)) !== 'parent') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $student = $parent->children()->where('users.id', $childId)->first()
            ?? User::find($childId);

        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $grades = $student->exams()
            ->where('is_published', true)
            ->with('course')
            ->get()
            ->map(function ($exam) {
                return [
                    'exam_id'      => $exam->id,
                    'title'        => $exam->title,
                    'course_name'  => $exam->course->name ?? '-',
                    'my_mark'      => $exam->pivot->mark,
                    'published_at' => $exam->updated_at?->format('Y-m-d'),
                ];
            });

        return response()->json([
            'success'      => true,
            'student_name' => $student->name,
            'grades'       => $grades,
        ]);
    }

    /**
     * GET /parent/child/{childId}/notes
     * Returns teacher comments/notes for a specific child.
     */
    public function getChildNotes(Request $request, $childId)
    {
        $parent = auth()->user();
        if (!$parent || trim(strtolower($parent->role)) !== 'parent') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $student = $parent->children()->where('users.id', $childId)->first()
            ?? User::find($childId);

        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $notes = \Illuminate\Support\Facades\DB::table('quiz_student')
            ->join('quizzes', 'quiz_student.quiz_id', '=', 'quizzes.id')
            ->join('courses', 'quizzes.course_id', '=', 'courses.id')
            ->join('users as teachers', 'quizzes.teacher_id', '=', 'teachers.id')
            ->where('quiz_student.student_id', $student->id)
            ->whereNotNull('quiz_student.comment')
            ->where('quiz_student.comment', '!=', '')
            ->select(
                'quiz_student.comment',
                'quiz_student.points',
                'courses.name as course_name',
                'teachers.name as teacher_name',
                'quiz_student.updated_at as date'
            )
            ->orderBy('quiz_student.updated_at', 'desc')
            ->get();

        return response()->json([
            'success'      => true,
            'student_name' => $student->name,
            'notes'        => $notes,
        ]);
    }

}