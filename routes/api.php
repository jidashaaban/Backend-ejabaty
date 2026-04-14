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