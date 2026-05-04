<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
use App\Http\Controllers\ReportController;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PollController;

Route::post('/login',[AuthController::class,'login']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

use App\Http\Controllers\ScheduleController;
Route::post('/generate-schedule', [ScheduleController::class, 'store']);
Route::get('/admin-schedule', [ScheduleController::class, 'index']);
Route::delete('sessions/{id}',[ScheduleController::class,'destroySession']);

use App\Http\Controllers\SpecialScheduleController;
Route::get('/my-schedule', [SpecialScheduleController::class, 'getMySchedule']);
Route::get('/upcoming-exams', [SpecialScheduleController::class, 'filterExamSchedule']);
Route::middleware('auth:sanctum')->group(function(){
    Route::get('/my-schedule',[SpecialScheduleController::class,'index']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/admin/dashboard', [DashboardController::class, 'index']);
    Route::get('/admin/reports',[ReportController::class,'getUserReports']);
    Route::post('/admin/reports/save', [ReportController::class, 'generateAndSaveReport']);
    Route::get('/admin/reports/history', function() {
       return \App\Models\Report::with('admin:id,name')->orderBy('created_at', 'desc')->get();
}); 
    Route::post('/admin/create-poll', [PollController::class, 'store']);

});

use App\Http\Controllers\HallController;
Route::post('/setup-halls', [HallController::class, 'store']);
Route::get('/halls', [HallController::class, 'index']);

use App\Http\Controllers\QuizController;
Route::post('/teacher/announce-quiz', [QuizController::class, 'announceQuiz']);
Route::get('/student/upcoming-quizzes', [QuizController::class, 'studentUpcomingQuizzes']);
Route::post('/quiz/submit-points', [QuizController::class, 'addQuizMarks']);
Route::get('/student/past-quizzes', [QuizController::class, 'getPastQuizzes']);

use App\Http\Controllers\StudentCourseController;
Route::get('/available-courses', [StudentCourseController::class, 'availableCourses']);
Route::post('/courses/{courseId}/join', [StudentCourseController::class, 'joinCourse']);
Route::get('/my-courses', [StudentCourseController::class, 'myCourses']);

use App\Http\Controllers\Admin\CourseController as AdminCourseController;
Route::post('/admin/add-course', [AdminCourseController::class, 'store']);
Route::post('/admin/confirm-payment', [AdminCourseController::class, 'confirmPayment']);

Route::get('/student/polls', [PollController::class, 'index']);

use App\Http\Controllers\TeacherExamController;
Route::post('/teacher/exams/create', [TeacherExamController::class, 'createExam']);
Route::get('/teacher/exams/{examId}', [TeacherExamController::class, 'getExamForMarking']);
Route::post('/teacher/exams/{examId}/submit-marking', [TeacherExamController::class, 'submitMarkingScheme']);

use App\Http\Controllers\AdminMarkController;
Route::post('/admin/submit-mark', [AdminMarkController::class, 'submitStudentMark']); 

use App\Http\Controllers\StudentMarkingSchemeController;
Route::get('/student/exam-history', [StudentMarkingSchemeController::class, 'getMyExamsAndMarks']);

use App\Http\Controllers\StudentQuestionController;
Route::post('/student/questions/ask', [StudentQuestionController::class, 'askQuestion']);
Route::get('/student/questions', [StudentQuestionController::class, 'myQuestions']);

use App\Http\Controllers\TeacherQuestionController;
Route::get('/teacher/questions/pending', [TeacherQuestionController::class, 'pendingQuestions']);
Route::post('/teacher/questions/{questionId}/answer', [TeacherQuestionController::class, 'answerQuestion']);

use App\Http\Controllers\ParentDashboardController;
use App\Http\Controllers\ComplaintController;
Route::get('/parent/child/{childId}/progress', [ParentDashboardController::class, 'getChildProgress']);
Route::get('/parent/child/{childId}/exam-schedule', [ParentDashboardController::class, 'getChildExamSchedule']);
Route::post('/parent/complaints/submit', [ComplaintController::class, 'submitComplaint']);
Route::get('/parent/complaints', [ComplaintController::class, 'viewComplaints']);
Route::post('/admin/complaints/{complaintId}/answer', [ComplaintController::class, 'answerComplaint']);



use App\Http\Controllers\Admin\UserController;
Route::post('/admin/users', [UserController::class, 'store']);

use App\Http\Controllers\NotificationController;
// URL: /api/notifications/5?requester_id=5
Route::get('/notifications/{userId}', [NotificationController::class, 'index']);
// URL: /api/notifications/5/some-uuid-string/read?requester_id=5
Route::post('/notifications/{userId}/{notificationId}/read', [NotificationController::class, 'markAsRead']);