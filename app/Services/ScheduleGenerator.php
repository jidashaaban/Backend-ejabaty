<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Courses; 
use App\Models\Session;
use Carbon\Carbon;

class ScheduleGenerator {
    
    public function generate($type) {
        set_time_limit(120); 

        // 1. SAFELY CLEAN OLD DATA FOR THIS TYPE ONLY
        // Find if a schedule of this type already exists (e.g., 'course')
        $schedule = \App\Models\Schedule::where('type', $type)->first();

        if ($schedule) {
            // Delete only the sessions that belong to THIS schedule
            \App\Models\Session::where('schedule_id', $schedule->id)->delete();
        } else {
            // If it doesn't exist, create a new one
            $schedule = \App\Models\Schedule::create(['type' => $type]);
        }

        // 2. GET COURSES
        $courses = \App\Models\Courses::withCount('students')
            ->orderBy('students_count', 'desc')
            ->get();
        
        $failedCourses = [];
        $required = ($type === 'exam') ? 1 : 2;

        // 3. GENERATE SESSIONS
        foreach ($courses as $course) {
            $this->assignSession($schedule, $course, $type);

            // Verify the sessions for this specific schedule
            $sessionsCount = \App\Models\Session::where('schedule_id', $schedule->id)
                                    ->where('course_id', $course->id)
                                    ->count();

            if ($sessionsCount < $required) {
                $failedCourses[] = ['id' => $course->id, 'name' => $course->name];
            }
        }

        // 4. RETURN CLEAN DATA
        return [
            'schedule' => $schedule->load('sessions'), 
            'warnings' => $failedCourses
        ];
    }
    
     private function assignSession($schedule, $course, $type) {
        $requiredSessions = ($type === 'exam') ? 1 : 2;
        $sessionsCreated = 0;
        
        // Define these BEFORE the loops
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
        $times = ['08:00:00', '09:30:00', '11:00:00', '12:30:00', '14:00:00', '15:30:00','17:00:00'];
        shuffle($days);

        foreach ($days as $day) {
            // Check if course is already on this day to prevent double-booking same day
            $alreadyOnDay = Session::where('schedule_id', $schedule->id)
                ->where('course_id', $course->id)
                ->where('day', $day)
                ->exists();

            if ($alreadyOnDay) continue; 

            foreach ($times as $time) {
                if ($sessionsCreated >= $requiredSessions) break 2;

                $hasStudentConflict = $this->hasStudentConflict($course, $day, $time, $type);
                $hasTeacherConflict = false;
                
                if ($type === 'course') {
                    $hasTeacherConflict = $this->hasTeacherConflict($course, $day, $time, $type);
                }

                if (!$hasStudentConflict && !$hasTeacherConflict) {
                    Session::create([
                        'schedule_id' => $schedule->id,
                        'course_id' => $course->id,
                        'day' => $day,
                        'start_time' => $time,
                        'end_time' => date('H:i:s', strtotime($time . ' +90 minutes')),
                    ]);
                    $sessionsCreated++;
                    
                    // After placing one session, move to the next day
                    break; 
                }
            }
        }
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