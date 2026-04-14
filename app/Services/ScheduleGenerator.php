<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Courses; 
use App\Models\Session;
use App\Services\ExamHallService;
use Carbon\Carbon;

class ScheduleGenerator {
    
    protected $hallService;

    public function __construct(ExamHallService $hallService) {
        $this->hallService = $hallService;
    }
    
    public function generate($type) {
        set_time_limit(120); 

        // 1. CLEAN OLD DATA
        $schedule = Schedule::where('type', $type)->first();

        if ($schedule) {
            Session::where('schedule_id', $schedule->id)->delete();
        } else {
            $schedule = Schedule::create(['type' => $type]);
        }

        // 2. GET COURSES
        $courses = Courses::withCount('students')
            ->orderBy('students_count', 'desc')
            ->get();
        
        $failedCourses = [];

        // 3. GENERATE SESSIONS & ASSIGN HALLS
        foreach ($courses as $course) {
            // Step 1: Time Assignment
            $session = $this->assignSession($schedule, $course, $type);
            
            if ($session) {
                // Step 2: Hall Assignment (Exams Only)
                if ($type === 'exam') {
                    $hallResult = $this->hallService->distributeStudents($session->id, $course->id);

                    if ($hallResult) {
                        $failedCourses[] = $hallResult;
                    } 
                }
            } else {
                $failedCourses[] = "Alert: Could not find a time slot for {$course->name}";
            }
        }

        return [
            'schedule' => $schedule->load('sessions.hallAssignments.hall'), 
            'admin_alerts' => $failedCourses
        ];
    }

    private function assignSession($schedule, $course, $type) {
        $requiredSessions = ($type === 'exam') ? 1 : 2;
        $sessionsCreated = 0;
        $lastCreatedSession = null;
        
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
        $times = ['08:00:00', '09:30:00', '11:00:00', '12:30:00', '14:00:00', '15:30:00','17:00:00'];
        shuffle($days);

        foreach ($days as $day) {
            $alreadyOnDay = Session::where('schedule_id', $schedule->id)
                ->where('course_id', $course->id)
                ->where('day', $day)
                ->exists();

            if ($alreadyOnDay) continue; 

            foreach ($times as $time) {
                if ($sessionsCreated >= $requiredSessions) break 2;

                $hasStudentConflict = $this->hasStudentConflict($course, $day, $time, $type);
                $hasTeacherConflict = ($type === 'course') ? $this->hasTeacherConflict($course, $day, $time, $type) : false;

                if (!$hasStudentConflict && !$hasTeacherConflict) {
                    $lastCreatedSession = Session::create([
                        'schedule_id' => $schedule->id,
                        'course_id'   => $course->id,
                        'day'         => $day,
                        'start_time'  => $time,
                        'end_time'    => date('H:i:s', strtotime($time . ' +90 minutes')),
                    ]);
                    $sessionsCreated++;
                    break; 
                }
            }
        }
        return $lastCreatedSession; 
    }

    private function hasStudentConflict($course, $day, $time, $type) {
        $studentIds = $course->students()->pluck('users.id');

        return Session::where('day', $day)
            ->where(function ($query) use ($time) {
                $query->where('start_time', '<=', $time)
                      ->where('end_time', '>', $time);
            })
            ->whereHas('schedule', function($query) use ($type){
                $query->where('type', $type);
            })
            ->whereHas('course.students', function($query) use ($studentIds) {
                $query->whereIn('users.id', $studentIds);
            })->exists();
    }

    private function hasTeacherConflict($course, $day, $time, $type) {
        $teacherId = $course->teacher_id;
        if(!$teacherId) return false;

        return Session::where('day', $day)
            ->where(function ($query) use ($time){
                $query->where('start_time', '<=', $time)
                      ->where('end_time', '>', $time);
            })
            ->whereHas('schedule', function($query) use ($type){
                $query->where('type', $type);
            })
            ->whereHas('course', function($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })->exists();
    }
}