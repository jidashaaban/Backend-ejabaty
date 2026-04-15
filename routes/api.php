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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

use App\Http\Controllers\ScheduleController;
Route::post('/generate-schedule', [ScheduleController::class, 'store']);

use App\Http\Controllers\SpecialScheduleController;
Route::get('/my-schedule/{userId}', [SpecialScheduleController::class, 'getMySchedule']);


use App\Http\Controllers\HallController;
Route::post('/setup-halls', [HallController::class, 'store']);

Route::get('/halls', [HallController::class, 'index']);

use App\Http\Controllers\QuizController;
Route::post('/teacher/announce-quiz', [QuizController::class, 'announceQuiz']);
Route::get('/student/{studentId}/upcoming-quizzes', [QuizController::class, 'studentUpcomingQuizzes']);

use App\Http\Controllers\StudentCourseController;
Route::get('/available-courses', [StudentCourseController::class, 'availableCourses']);
Route::post('/courses/{courseId}/join', [StudentCourseController::class, 'joinCourse']);
Route::get('/my-courses/{studentId}', [StudentCourseController::class, 'myCourses']);

use App\Http\Controllers\Admin\CourseController as AdminCourseController;
Route::post('/admin/add-course', [AdminCourseController::class, 'store']);

use App\Http\Controllers\Admin\PollController;
Route::post('/admin/create-poll', [PollController::class, 'store']);
Route::get('/student/polls', [PollController::class, 'index']);