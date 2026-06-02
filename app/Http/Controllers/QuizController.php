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
    // 1. Teacher Announces a New Quiz (Secure Token Verification & Dynamic Teacher Name Notifications)
    public function announceQuiz(Request $request)
    {
        // Identify authenticated teacher directly from the secure bearer token
        $teacher = auth()->user();

        if (!$teacher || $teacher->role !== 'teacher') {
            return response()->json([
                'message' => 'Forbidden: Only Teachers can access this feature.'
            ], 403);
        }

        // Clean validation payload definition; teacher_name input field completely dropped
        $request->validate([
            'course_name'      => 'required|string',
            'quiz_date'        => 'required|date',
            'start_time'       => 'required|date_format:H:i',
            'included_content' => 'required|string',
        ]);

        // Lookup the Course entity record by string name match
        $course = Courses::where('name', $request->course_name)->first();

        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Error: Course "' . $request->course_name . '" does not exist.'
            ], 404);
        }

        // Integrity Check: Restrict access if the requesting user isn't assigned to this class
        if ($course->teacher_id !== $teacher->id) {
            return response()->json([
                'success' => false,
                'message' => 'Error: You do not have permission to announce a quiz for a course you do not teach.'
            ], 403);
        }

        // Guard Condition: Enforce a unique day allocation rule constraint per course layout
        $quizAlreadyExists = Quiz::where('course_id', $course->id)
            ->where('quiz_date', $request->quiz_date)
            ->exists();

        if ($quizAlreadyExists) {
            return response()->json([
                'success' => false,
                'message' => 'Error: A quiz is already announced for this course on this specific date.'
            ], 409);
        }

        // Calendar Timetable Timematch Constraint Verification Check
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

        // Create the entity dataset model record context safely
        $quiz = Quiz::create([
            'course_id'        => $course->id,
            'teacher_id'       => $teacher->id,
            'quiz_date'        => $request->quiz_date,
            'start_time'       => $request->start_time,
            'included_content' => $request->included_content,
        ]);

        // Dispatches background messages capturing the Teacher's Identity dynamically
        $course->load('students');
        foreach ($course->students as $student) {
            $student->notify(new SchoolNotification(
                "New Quiz Announced!",
                "Teacher " . $teacher->name . " has scheduled a new quiz for '" . $course->name . "' on " . $request->quiz_date,
                "quiz_announcement",
                $quiz->id
            ));
        }

        return response()->json([
            'success' => true,
            'message' => 'Quiz announced successfully!',
            'quiz' => [
                'course_name'      => $course->name,
                'quiz_date'        => $quiz->quiz_date,
                'start_time'       => $quiz->start_time,
                'included_content' => $quiz->included_content,
                'created_at'       => $quiz->created_at,
                'updated_at'       => $quiz->updated_at,
            ]
        ]);
    }

    // 2. Student Views Upcoming Quizzes (FIXED: Eager Loads nested course teacher values)
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
        $studentCourseIds = DB::table('user_course')
            ->where('user_id', $studentId)
            ->pluck('course_id');

        // FIXED: Eager load course details along with the nested teacher relationship
        $upcomingQuizzes = Quiz::with(['course.teacher:id,name'])
            ->whereIn('course_id', $studentCourseIds)
            ->orderBy('quiz_date', 'asc') 
            ->get();
        
        if ($upcomingQuizzes->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'no upcoming quizzes',
                'upcoming_quizzes' => []
            ]);
        }

        return response()->json([
            'success' => true,
            'upcoming_quizzes' => $upcomingQuizzes
        ]);
    }

    // 3. Teacher Submits Quiz Marks and Feedback Comments
    public function addQuizMarks(Request $request) 
    {
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

        // Check if the Course exists AND if this Teacher teaches it
        $course = Courses::where('name', $request->course_name)
            ->where('teacher_id', $user->id)
            ->first();

        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Error: Course not found or you are not the assigned teacher for this course.'
            ], 404);
        }

        // Check if the Student exists
        $student = User::where('name', $request->student_name)
            ->where('role', 'student')
            ->first();

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Error: Student "' . $request->student_name . '" does not exist.'
            ], 404);
        }

        // Check if this specific Student is enrolled in this specific Course
        $isEnrolled = DB::table('user_course') 
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->exists();

        if (!$isEnrolled) {
            return response()->json([
                'success' => false,
                'message' => 'Error: This student is not enrolled in ' . $course->name
            ], 403);
        }

        // Fetch the most recent quiz record matching variables
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

        // Sync points evaluation on pivot model map table indices
        $quiz->students()->syncWithoutDetaching([
            $student->id => [
                'points'  => $request->points,
                'comment' => $request->comment
            ]
        ]);

        // Send grade update out directly
        $student->notify(new SchoolNotification(
            "Quiz Result Published",
            "Your marks for the quiz in '" . $course->name . "' are now available. Points: " . $request->points,
            "quiz_mark",
            $quiz->id
        ));

        return response()->json([
            'success' => true,
            'message' => 'Points and comment added successfully!',
            'data' => [
                'student' => $student->name,
                'course'  => $course->name,
                'points'  => $request->points
            ]
        ]);
    }

    // 4. Student Reviews Academic Performance History
    public function getPastQuizzes(Request $request) 
    {
        $user = $request->user();

        if (!$user || $user->role !== 'student') {
            return response()->json([
               'message' => 'Forbidden: You can only access your own records.'
            ], 403);
        } 

        $studentId = $user->id;
        
        $enrolledCourseIds = DB::table('user_course') 
            ->where('user_id', $studentId)
            ->pluck('course_id');

        // Capture previous historically matched timelines
        $pastQuizzes = Quiz::whereIn('course_id', $enrolledCourseIds)
            ->whereDate('quiz_date', '<', Carbon::today())
            ->with(['course', 'students' => function($query) use ($studentId) {
                $query->where('student_id', $studentId); 
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
                'comment'     => $pivot ? $pivot->comment : null 
            ];
        });

        return response()->json([
            'success'      => true,
            'past_quizzes' => $formattedQuizzes 
        ]);
    }

    // 5. Retrieve all Quizzes Announced by this Teacher (Eager Loads Teacher Relations)
    public function getTeacherQuizzes()
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'teacher') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Nested Eager Loading captures course metadata + assigned instructor columns
        $quizzes = Quiz::where('teacher_id', $user->id)
            ->with(['course' => function($query) {
                $query->select('id', 'name', 'teacher_id')->with('teacher:id,name');
            }])
            ->orderBy('quiz_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'quizzes' => $quizzes
        ]);
    }

    // 6. Update an Existing Quiz Announcement
    public function updateQuiz(Request $request, $quizId)
    {
        $user = auth()->user();
        $quiz = Quiz::where('id', $quizId)->where('teacher_id', $user->id)->firstOrFail();

        $request->validate([
            'quiz_date'        => 'required|date',
            'start_time'       => 'required|date_format:H:i',
            'included_content' => 'required|string',
        ]);

        // Recalculate schedule validity vectors during change request actions
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
            'quiz_date'        => $request->quiz_date,
            'start_time'       => $request->start_time,
            'included_content' => $request->included_content,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quiz updated successfully!',
            'quiz'    => $quiz
        ]);
    }

    // 7. Retrieve Grade Matrix Context Records for Classroom Evaluations
    public function getQuizMarksForTeacher($quizId)
    {
        $user = auth()->user();
        
        $quiz = Quiz::where('id', $quizId)->where('teacher_id', $user->id)->first();

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found or unauthorized'], 404);
        }

        $studentsWithMarks = User::whereHas('courses', function($query) use ($quiz) {
                $query->where('courses.id', $quiz->course_id);
            })
            ->with(['quizzes' => function($query) use ($quizId) {
                $query->where('quiz_id', $quizId); 
            }])
            ->get()
            ->map(function($student) {
                $quizData = $student->quizzes->first();
                return [
                    'student_id'   => $student->id,
                    'student_name' => $student->name,
                    'points'       => $quizData ? $quizData->pivot->points : null,
                    'comment'      => $quizData ? $quizData->pivot->comment : null,
                ];
            });

        return response()->json([
            'success'      => true,
            'quiz_content' => $quiz->included_content,
            'students'     => $studentsWithMarks
        ]);
    }

    // 8. Update specific Student Mark Evaluation Performance
    public function updateStudentMark(Request $request, $quizId, $studentId)
    {
        $user = auth()->user();

        $quiz = Quiz::where('id', $quizId)
                    ->where('teacher_id', $user->id)
                    ->first();

        if (!$quiz) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You can only edit marks for quizzes you announced.'
            ], 403);
        }

        $request->validate([
            'points'  => 'required|numeric',
            'comment' => 'nullable|string'
        ]);

        $quiz->students()->syncWithoutDetaching([
            $studentId => [
                'points'  => $request->points,
                'comment' => $request->comment
            ]
        ]);

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
                'student'    => $student->name,
                'new_points' => $request->points
            ]
        ]);
    }

    // 9. Cumulative Points Summary for Student Dashboard Widget
    public function getMyPoints(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'student') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $total = DB::table('quiz_student')->where('student_id', $user->id)->sum('points');
        return response()->json(['success' => true, 'points' => (int) $total]);
    }

    // 10. Fetch Remarks and Feedbacks Notebook for Logged-In Student
    public function getMyNotes(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'student') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $records = DB::table('quiz_student')
            ->join('quizzes', 'quiz_student.quiz_id', '=', 'quizzes.id')
            ->join('courses', 'quizzes.course_id', '=', 'courses.id')
            ->join('users as teachers', 'quizzes.teacher_id', '=', 'teachers.id')
            ->where('quiz_student.student_id', $user->id)
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
            'success' => true,
            'notes'   => $records,
        ]);
    }
}