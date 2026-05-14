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
    $user = auth()->user();

    if (!$user || $user->role !== 'teacher') {
        return response()->json([
            'message' => 'Forbidden: Only Teachers can access this feature.'
        ], 403);
    }

    // 1. Updated Validation to require strings instead of IDs
    $request->validate([
        'course_name' => 'required|string',
        'quiz_date' => 'required|date',
        'start_time' => 'required|date_format:H:i',
        'included_content' => 'required|string',
    ]);

    // 2. Lookup the Teacher by name
    $teacher = User::where('name', $request->teacher_name)
        ->where('role', 'teacher')
        ->first();

    if (!$teacher) {
        return response()->json([
            'success' => false,
            'message' => 'Error: Teacher "' . $request->teacher_name . '" does not exist.'
        ], 404);
    }

    // 3. Lookup the Course by name
    $course = Courses::where('name', $request->course_name)->first();

    if (!$course) {
        return response()->json([
            'success' => false,
            'message' => 'Error: Course "' . $request->course_name . '" does not exist.'
        ], 404);
    }

    // 4. Verify the Teacher teaches this Course
    // (This replaces your $teacherCourse = Courses::where... check)
    if ($course->teacher_id !== $teacher->id) {
        return response()->json([
            'success' => false,
            'message' => 'Error: You (or the specified teacher) do not have permission to announce a quiz for a course you do not teach.'
        ], 403);
    }

    // 5. Check if Quiz already exists on this date (Using $course->id)
    $quizAlreadyExists = Quiz::where('course_id', $course->id)
        ->where('quiz_date', $request->quiz_date)
        ->exists();

    if ($quizAlreadyExists) {
        return response()->json([
            'success' => false,
            'message' => 'Error: A quiz is already announced for this course on this specific date.'
        ], 409);
    }

    // 6. Schedule Validation logic (Using $course->id)
    $dayOfWeek = Carbon::parse($request->quiz_date)->format('l');

    $isValidSchedule = Session::where('course_id', $course->id)
        ->where('day', $dayOfWeek)
        ->where('start_time', '<=', $request->start_time)
        ->where('end_time', '>', $request->start_time) 
        ->exists();

    if (!$isValidSchedule) {
        return response()->json([
            'success' => false,
            'message' => 'Error: The selected date ('.$dayOfWeek.') and time do not match any scheduled sessions for this course.'
        ], 422); 
    }

    // 7. Save the Quiz (Using the looked-up IDs)
    $quiz = Quiz::create([
        'course_id' => $course->id,
        'teacher_id' => $teacher->id,
        'quiz_date' => $request->quiz_date,
        'start_time' => $request->start_time,
        'included_content' => $request->included_content,
    ]);

    // 8. Notifications logic
    // We already have the $course model from Step 3, so we can load students directly
    $course->load('students');
    foreach ($course->students as $student) {
        $student->notify(new SchoolNotification(
            "Upcoming Quiz!",
            "A new quiz for '" . $course->name . "' has been scheduled for " . $request->quiz_date,
            "quiz_announcement",
            $quiz->id
        ));
    }

    return response()->json([
        'success' => true,
        'message' => 'Quiz announced successfully!',
        'quiz' => [
            'course_name' => $course->name, // Replaced course_id
            'quiz_date' => $quiz->quiz_date,
            'start_time' => $quiz->start_time,
            'included_content' => $quiz->included_content,
            'created_at' => $quiz->created_at,
            'updated_at' => $quiz->updated_at,
        ]
    ]);
}

    // 2. Student Views Upcoming Quizzes
    public function studentUpcomingQuizzes(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'student') {
             return response()->json([
                 'message' => 'Forbidden: You can only access your own records'
    ], 403);
}
        $studentId = $user->id;
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

    public function addQuizMarks(Request $request) {
    $user = auth()->user();

    if (!$user || $user->role !== 'teacher') {
        return response()->json([
            'message' => 'Forbidden: Only Teachers can access this feature.'
        ], 403);
    }

    $request->validate([
        'course_name'  => 'required|string',
        'student_name' => 'required|string',
        'points'       => 'required|numeric',
        'comment'      => 'nullable|string'
    ]);

    // 1. Check if the Course exists AND if this Teacher teaches it
    $course = Courses::where('name', $request->course_name)
        ->where('teacher_id', $user->id)
        ->first();

    if (!$course) {
        return response()->json([
            'success' => false,
            'message' => 'Error: Course not found or you are not the assigned teacher for this course.'
        ], 404);
    }

    // 2. Check if the Student exists
    $student = User::where('name', $request->student_name)
        ->where('role', 'student')
        ->first();

    if (!$student) {
        return response()->json([
            'success' => false,
            'message' => 'Error: Student "' . $request->student_name . '" does not exist.'
        ], 404);
    }

    // 3. Check if this specific Student is enrolled in this specific Course
    $isEnrolled = DB::table('user_course') // Ensure this matches your pivot table name
        ->where('user_id', $student->id)
        ->where('course_id', $course->id)
        ->exists();

    if (!$isEnrolled) {
        return response()->json([
            'success' => false,
            'message' => 'Error: This student is not enrolled in ' . $course->name
        ], 403);
    }

    // 4. Find the Quiz for this course 
    // Since we aren't using quiz_id, we fetch the most recent quiz created for this course
    $quiz = Quiz::where('course_id', $course->id)
        ->where('teacher_id', $user->id)
        ->latest()
        ->first();

    if (!$quiz) {
        return response()->json([
            'success' => false,
            'message' => 'Error: No quiz found for this course to add marks to.'
        ], 404);
    }

    // 5. Update the pivot table (marks and comments)
    $quiz->students()->syncWithoutDetaching([
        $student->id => [
            'points' => $request->points,
            'comment' => $request->comment
        ]
    ]);

    // 6. Notify the student
    if ($student) {
        $student->notify(new SchoolNotification(
            "Quiz Result Published",
            "Your marks for the quiz in '" . $course->name . "' are now available. Points: " . $request->points,
            "quiz_mark",
            $quiz->id
        ));
    }

    return response()->json([
        'success' => true,
        'message' => 'Points and comment added successfully!',
        'data' => [
            'student' => $student->name,
            'course' => $course->name,
            'points' => $request->points
        ]
    ]);
}

    public function getPastQuizzes(Request $request) {
        $user = $request->user();

        if (!$user || $user->role !== 'student') {
            return response()->json([
               'message' => 'Forbidden: You can only access your own records.'
    ], 403);
} 
    $studentId = $user->id;
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
// Retrieve all quizzes announced by this teacher
public function getTeacherQuizzes()
{
    $user = auth()->user();

    // Security Check
    if (!$user || $user->role !== 'teacher') {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $quizzes = Quiz::where('teacher_id', $user->id)
        ->with('course:id,name') // Include course name
        ->orderBy('quiz_date', 'desc')
        ->get();

    return response()->json([
        'success' => true,
        'quizzes' => $quizzes
    ]);
}
// Update an existing quiz announcement
public function updateQuiz(Request $request, $quizId)
{
    $user = auth()->user();
    $quiz = Quiz::where('id', $quizId)->where('teacher_id', $user->id)->firstOrFail();

    $request->validate([
        'quiz_date' => 'required|date',
        'start_time' => 'required|date_format:H:i',
        'included_content' => 'required|string',
    ]);

    // Schedule Validation Logic
    $dayOfWeek = Carbon::parse($request->quiz_date)->format('l');
    $isValidSchedule = Session::where('course_id', $quiz->course_id)
        ->where('day', $dayOfWeek)
        ->where('start_time', '<=', $request->start_time)
        ->where('end_time', '>', $request->start_time)
        ->exists();

    if (!$isValidSchedule) {
        return response()->json([
            'success' => false,
            'message' => 'Error: The new date/time does not match the course schedule.'
        ], 422);
    }

    $quiz->update([
        'quiz_date' => $request->quiz_date,
        'start_time' => $request->start_time,
        'included_content' => $request->included_content,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Quiz updated successfully!',
        'quiz' => $quiz
    ]);
}
// Retrieve students and their marks for a specific quiz
public function getQuizMarksForTeacher($quizId)
{
    $user = auth()->user();
    
    // Ensure the teacher owns this quiz
    $quiz = Quiz::where('id', $quizId)->where('teacher_id', $user->id)->first();

    if (!$quiz) {
        return response()->json(['message' => 'Quiz not found or unauthorized'], 404);
    }

    // Fetch all students enrolled in the course linked to this quiz
    // and include their pivot data (points/comment) for this specific quiz
    $studentsWithMarks = User::whereHas('courses', function($query) use ($quiz) {
            $query->where('courses.id', $quiz->course_id);
        })
        ->with(['quizzes' => function($query) use ($quizId) {
            $query->where('quiz_id', $quizId); // Only get marks for THIS quiz
        }])
        ->get()
        ->map(function($student) {
            $quizData = $student->quizzes->first();
            return [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'points' => $quizData ? $quizData->pivot->points : null,
                'comment' => $quizData ? $quizData->pivot->comment : null,
            ];
        });

    return response()->json([
        'success' => true,
        'quiz_content' => $quiz->included_content,
        'students' => $studentsWithMarks
    ]);
}
// Update an existing mark for a specific student
public function updateStudentMark(Request $request, $quizId, $studentId)
{
    $user = auth()->user();

    // 1. Security Check: Ensure only the teacher who created the quiz can edit marks
    $quiz = Quiz::where('id', $quizId)
                ->where('teacher_id', $user->id)
                ->first();

    if (!$quiz) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: You can only edit marks for quizzes you announced.'
        ], 403);
    }

    // 2. Validation
    $request->validate([
        'points'  => 'required|numeric',
        'comment' => 'nullable|string'
    ]);

    // 3. Update the Pivot Table (quiz_student)
    // syncWithoutDetaching updates the points if the student is already linked
    $quiz->students()->syncWithoutDetaching([
        $studentId => [
            'points' => $request->points,
            'comment' => $request->comment
        ]
    ]);

    // 4. Optional: Notify the student that their grade was updated
    $student = User::find($studentId);
    if ($student) {
        $student->notify(new SchoolNotification(
            "Quiz Mark Updated",
            "Your marks for the quiz in '" . $quiz->course->name . "' have been updated to: " . $request->points,
            "quiz_mark_update",
            $quiz->id
        ));
    }

    return response()->json([
        'success' => true,
        'message' => 'Mark updated successfully!',
        'updated_data' => [
            'student' => $student->name,
            'new_points' => $request->points
        ]
    ]);
}

}
