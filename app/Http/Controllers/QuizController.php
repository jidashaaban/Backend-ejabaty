<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quiz;
use App\Models\Session;
use App\Models\Courses;
use Carbon\Carbon;

class QuizController extends Controller
{
    // 1. Teacher Announces the Quiz
    public function announceQuiz(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'teacher_id' => 'required|exists:users,id',
            'quiz_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'included_content' => 'required|string',
        ]);

        $teacherCourse = Courses::where('id',$request->course_id)
            ->where('teacher_id',$request->teacher_id)
            ->exists();

        $quizAlreadyExists = Quiz::where('course_id', $request->course_id)
        ->where('quiz_date', $request->quiz_date)
        ->exists();

    if ($quizAlreadyExists) {
        return response()->json([
            'success' => false,
            'message' => 'Error: A quiz is already announced for this course on this specific date. You cannot add another one.'
        ], 409); // 409 Conflict is the perfect status code for duplicate data
    }
        
        if(!$teacherCourse) {
            return response()->json([
                'success'=>false,
                'message'=>'Error: you do not have permission to announce a quiz for a course you do not teach'
            ],403);
        }

        // Find what day of the week the entered date is (e.g., "Sunday")
        // Laravel's Carbon makes this very easy:
        $dayOfWeek = Carbon::parse($request->quiz_date)->format('l');

        // Check if the course actually has a session on this Day and Time
        $isValidSchedule = Session::where('course_id', $request->course_id)
            ->where('day', $dayOfWeek)
            // Assuming your start_time in sessions is stored like '08:00:00'
            ->where('start_time', '<=', $request->start_time)
            ->where('end_time', '>', $request->start_time) 
            ->exists();

        if (!$isValidSchedule) {
            // This is the error sent to the frontend if the schedule doesn't match
            return response()->json([
                'success' => false,
                'message' => 'Error: The selected date ('.$dayOfWeek.') and time do not match any scheduled sessions for this course.'
            ], 422); 
        }

        // If it passes validation, save the quiz!
        $quiz = Quiz::create([
            'course_id' => $request->course_id,
            'teacher_id' => $request->teacher_id,
            'quiz_date' => $request->quiz_date,
            'start_time' => $request->start_time,
            'included_content' => $request->included_content,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quiz announced successfully!',
            'quiz' => $quiz
        ]);
    }

    // 2. Student Views Upcoming Quizzes
    public function studentUpcomingQuizzes($studentId)
    {
        // Get all course IDs that the student is enrolled in
        $studentCourseIds = \DB::table('user_course')
            ->where('user_id', $studentId)
            ->pluck('course_id');

        // Fetch quizzes for those courses where the date is today or in the future
        $upcomingQuizzes = Quiz::with('course') // brings in the course name
            ->whereIn('course_id', $studentCourseIds)
            ->whereDate('quiz_date', '>=', Carbon::today())
            ->orderBy('quiz_date', 'asc') // closest quiz first
            ->get();

        return response()->json([
            'success' => true,
            'upcoming_quizzes' => $upcomingQuizzes
        ]);
    }
}
