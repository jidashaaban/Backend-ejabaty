<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Courses; 
use App\Models\Session;
use Carbon\Carbon;

class ScheduleGenerator {

    public function generate($type) {
    // 1. Give the script more time to run
    set_time_limit(120); 

    Schedule::where('type', $type)->delete();
    $schedule = Schedule::create(['type' => $type]);
    
    // 2. Sort courses by difficulty (Courses with most students should go first)
    $courses = Courses::withCount('students')
        ->orderBy('students_count', 'desc')
        ->get();

    foreach ($courses as $course) {
        $this->assignSession($schedule, $course, $type);
    }
    return $schedule;
}

    private function assignSession($schedule, $course, $type) {
    $requiredSessions = ($type === 'exam') ? 1 : 2;
    $sessionsCreated = 0;
    
    // 3. Define all possible slots first to avoid repetitive Carbon creation
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
    $times = ['08:00:00', '10:00:00', '12:00:00', '14:00:00'];
    
    shuffle($days);
    $times = ['08:00:00', '10:00:00', '12:00:00', '14:00:00'];

    foreach ($times as $startTime) {
        foreach ($days as $day) {
            if ($sessionsCreated >= $requiredSessions) return;

            // Run our checks
            $hasStudentConflict = $this->hasStudentConflict($course, $day, $startTime, $type);
            
            $hasTeacherConflict = false;
            if ($type === 'course') {
                $hasTeacherConflict = $this->hasTeacherConflict($course, $day, $startTime, $type);
            }

            if (!$hasStudentConflict && !$hasTeacherConflict) {
                Session::create([
                    'schedule_id' => $schedule->id,
                    'course_id' => $course->id,
                    'day' => $day,
                    'start_time' => $startTime,
                    'end_time' => date('H:i:s', strtotime($startTime . ' +2 hours')),
                ]);
                $sessionsCreated++;
            }
        }
    }
}
    // End of assignSession

    private function hasStudentConflict($course, $day, $time,$type) {
        $studentIds = $course->students()->pluck('users.id');

        return Session::where('day', $day)
            ->where(function ($query) use ($time) {
                $query->where('start_time', '<=', $time)
                      ->where('end_time', '>', $time);
            })
            ->whereHas('schedule',function($query) use ($type){
                $query->where('type',$type);
            })
            ->whereHas('course.students', function($query) use ($studentIds) {
                $query->whereIn('users.id', $studentIds);
            })->exists();
    }

    private function hasTeacherConflict($course, $day,$time,$type) {
        $teacherId = $course->teacher_id;
        if(!$teacherId) return false;

        return Session::where('day',$day)
            ->where(function ($query) use ($time){
                $query->where('start_time','<=',$time)
                      ->where('end_time', '>', $time);
            })
            ->whereHas('schedule',function($query) use ($type){
                $query->where('type',$type);
            })
            ->whereHas('course',function($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })->exists();
    }

}