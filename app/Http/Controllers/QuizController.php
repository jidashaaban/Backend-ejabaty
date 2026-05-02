<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Quiz;
use App\Models\Session;
use App\Models\Courses;
use App\Models\User;
use App\Notifications\SchoolNotification;
use Carbon\Carbon;

class QuizController extends Controller
{
    // 1. Teacher Announces the Quiz
    public function announceQuiz(Request $request)
    {
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'teacher') {
                 return response()->json([
                      'message' => 'Forbidden: Only Teachers can access this feature.'
    ], 403);
}
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
        $course = Courses::with('students')->find($request->course_id);
        foreach ($course->students as $student) {
        $student->notify(new SchoolNotification(
            "Upcoming Quiz!",
            "A new quiz for '" . $course->name . "' has been scheduled for " . $request->quiz_date,
            "quiz_announcement"
        ));
    }

        return response()->json([
            'success' => true,
            'message' => 'Quiz announced successfully!',
            'quiz' => $quiz
        ]);
    }

    // 2. Student Views Upcoming Quizzes
    public function studentUpcomingQuizzes($studentId)
    {
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'student') {
             return response()->json([
                 'message' => 'Forbidden: You can only access your own records'
    ], 403);
}
        // Get all course IDs that the student is enrolled in
        $studentCourseIds = \DB::table('user_course')
            ->where('user_id', $studentId)
            ->pluck('course_id');

        // Fetch quizzes for those courses where the date is today or in the future
        $upcomingQuizzes = Quiz::with('course') // brings in the course name
            ->whereIn('course_id', $studentCourseIds)
            //->whereDate('quiz_date', '>=', Carbon::today())
            ->orderBy('quiz_date', 'asc') // closest quiz first
            ->get();
        
        if ($upcomingQuizzes->isEmpty()) {
        return response()->json([
            'success' => true,
            'message' => 'no upcoming quizzes',
            'upcoming_quizzes' => [] // We still return an empty array so the frontend doesn't crash
        ]);
    }

        return response()->json([
            'success' => true,
            'upcoming_quizzes' => $upcomingQuizzes
        ]);
    }

    public function addQuizMarks(Request $request){
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'teacher') {
             return response()->json([
                'message' => 'Forbidden: Only Teachers can access this feature.'
    ], 403);
}
        $request->validate([
            'quiz_id'=>'required|exists:quizzes,id',
            'student_id'=>'required|exists:users,id',
            'points'=>'required|numeric',
            'comment'=>'nullable|string'
        ]);

        $quiz = Quiz::findOrFail($request->quiz_id);
        if ($quiz->teacher_id !== $request->teacher_id) {
           return response()->json(['message' => 'Unauthorized'], 403); 
       }
       $quiz->students()->syncWithoutDetaching([
        $request->student_id => [
            'points' => $request->points,
            'comment' => $request->comment
        ]
     ]);
        $student = User::find($request->student_id);
        if ($student) {
           $student->notify(new SchoolNotification(
               "Quiz Result Published",
               "Your marks for the quiz in '" . $quiz->course->name . "' are now available. Points: " . $request->points,
               "quiz_mark"
        ));
    }
     return response()->json(['message' => 'Points and comment added successfully!']);

    }

    public function getPastQuizzes($studentId) {
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'student') {
            return response()->json([
               'message' => 'Forbidden: You can only access your own records.'
    ], 403);
} 
    // 1. Get IDs of courses the student is enrolled in
    $enrolledCourseIds = DB::table('user_course') // or course_student
        ->where('user_id', $studentId)
        ->pluck('course_id');

    // 2. Fetch quizzes for those courses that happened before today
    $pastQuizzes = Quiz::whereIn('course_id', $enrolledCourseIds)
        ->whereDate('quiz_date', '<', Carbon::today())
        ->with(['course', 'students' => function($query) use ($studentId) {
            $query->where('student_id', $studentId); // Only fetch marks for THIS student
        }])
        ->get();

    if ($pastQuizzes->isEmpty()) {
        return response()->json(['message' => 'no past quizzes'], 200);
    }

    $formattedQuizzes = $pastQuizzes->map(function ($quiz) {
        $pivot = $quiz->students->first()?->pivot;
        return [
            'course_name' => $quiz->course->name ?? 'N/A',
            'quiz_name'   => $quiz->included_content, 
            'date'        => $quiz->quiz_date,
            'points'      => $pivot ? $pivot->points : null, 
            'comment'     => $pivot ? $pivot->comment : null  //null if no comment
        ];

    });
    return response()->json([
        'success' => true,
        'past_quizzes' => $formattedQuizzes 
    ]);
}

}
