<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PollController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SpecialScheduleController;
use App\Http\Controllers\HallController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\StudentCourseController;
use App\Http\Controllers\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\TeacherExamController;
use App\Http\Controllers\AdminMarkController;
use App\Http\Controllers\StudentMarkingSchemeController;
use App\Http\Controllers\StudentQuestionController;
use App\Http\Controllers\TeacherQuestionController;
use App\Http\Controllers\ParentDashboardController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\NotificationController;

/* --- Public Routes --- */
Route::post('/login', [AuthController::class, 'login']);

/* --- Protected Routes (Token Required) --- */
Route::middleware('auth:sanctum')->group(function () {

    // Auth & General
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) { return $request->user(); });

    // Admin Specific Routes
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/reports', [ReportController::class, 'getUserReports']);
        Route::post('/reports/save', [ReportController::class, 'generateAndSaveReport']);
        Route::get('/reports/history', [ReportController::class, 'getHistory']);
        
        // Course Management (FIXED: Used correct class name AdminCourseController)
        Route::get('/courses', [AdminCourseController::class, 'index']);
        Route::get('/courses/{id}', [AdminCourseController::class, 'show']);
        Route::put('/courses/{id}', [AdminCourseController::class, 'update']);
        Route::delete('/courses/{id}', [AdminCourseController::class, 'destroy']);
        Route::post('/add-course', [AdminCourseController::class, 'store']);
        Route::post('/confirm-payment', [AdminCourseController::class, 'confirmPayment']);

        // User & Hall Management
        Route::get('/simple-students', function() {
            return App\Models\User::where('role','student')->select('id','name','email')->get();
        });
        Route::post('/users', [UserController::class, 'store']);
        Route::post('/setup-halls', [HallController::class, 'store']);
        Route::get('/halls', [HallController::class, 'index']);
        Route::put('/halls/{id}', [HallController::class, 'update']);
        Route::delete('/halls/{id}', [HallController::class, 'destroy']);

        // Complaint Management
        Route::post('/complaints/{complaintId}/answer', [ComplaintController::class, 'answerComplaint']);
        Route::put('/complaints/{id}/answer', [ComplaintController::class, 'updateAnswer']);
        Route::get('/complaints', [ComplaintController::class, 'getAllComplaints']);
        
        // --- POLL MANAGEMENT (FIXED: Removed redundant /admin paths) ---
        Route::post('/create-poll', [PollController::class, 'store']);
        Route::get('/polls', [PollController::class, 'index']);
        Route::get('/polls/{id}', [PollController::class, 'show']); 
        Route::put('/polls/{id}', [PollController::class, 'update']); 
        Route::delete('/polls/{id}', [PollController::class, 'destroy']);

        Route::put('/polls/{id}/questions/{questionId}', [PollController::class, 'updateQuestion']);
        Route::delete('/polls/{id}/questions/{questionId}', [PollController::class, 'destroyQuestion']);
        Route::put('/polls/{id}/questions/{questionId}/options/{optionId}', [PollController::class, 'updateOption']);
        Route::delete('/polls/{id}/questions/{questionId}/options/{optionId}', [PollController::class, 'destroyOption']);
    });

    // Schedule Management (Now correctly inside Sanctum middleware)
    Route::post('/schedule/generate', [ScheduleController::class, 'store']);
    Route::get('/admin-schedule', [ScheduleController::class, 'index']);
    Route::delete('sessions/{id}', [ScheduleController::class, 'destroySession']);

    // Teacher Specific Routes
    Route::prefix('teacher')->group(function () {
        Route::post('/announce-quiz', [QuizController::class, 'announceQuiz']);
        Route::post('/quiz/submit-points', [QuizController::class, 'addQuizMarks']);
        Route::post('/exams/create', [TeacherExamController::class, 'createExam']);
        Route::get('/exams/{examId}', [TeacherExamController::class, 'getExamForMarking']);
        Route::post('/exams/{examId}/submit-marking', [TeacherExamController::class, 'submitMarkingScheme']);
        Route::get('/questions/pending', [TeacherQuestionController::class, 'pendingQuestions']);
        Route::post('/questions/{questionId}/answer', [TeacherQuestionController::class, 'answerQuestion']);
    });

    // Student Specific Routes
    Route::prefix('student')->group(function () {
        Route::get('/my-schedule', [SpecialScheduleController::class, 'index']);
        Route::get('/upcoming-exams', [SpecialScheduleController::class, 'filterExamSchedule']);
        Route::get('/upcoming-quizzes', [QuizController::class, 'studentUpcomingQuizzes']);
        Route::get('/past-quizzes', [QuizController::class, 'getPastQuizzes']);
        Route::get('/available-courses', [StudentCourseController::class, 'availableCourses']);
        Route::post('/courses/{courseId}/join', [StudentCourseController::class, 'joinCourse']);
        Route::get('/my-courses', [StudentCourseController::class, 'myCourses']);
        Route::get('/polls', [PollController::class, 'index']);
        Route::get('/exam-history', [StudentMarkingSchemeController::class, 'getMyExamsAndMarks']);
        Route::post('/questions/ask', [StudentQuestionController::class, 'askQuestion']);
        Route::get('/questions', [StudentQuestionController::class, 'myQuestions']);
    });

    // Parent Specific Routes
    Route::prefix('parent')->group(function () {
        Route::get('/child/{childId}/progress', [ParentDashboardController::class, 'getChildProgress']);
        Route::get('/child/{childId}/exam-schedule', [ParentDashboardController::class, 'getChildExamSchedule']);
        Route::post('/complaints/submit', [ComplaintController::class, 'submitComplaint']);
        Route::get('/complaints', [ComplaintController::class, 'viewComplaints']);
    });

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);

}); // <-- THE AUTH GROUP NOW CORRECTLY ENDS HERE