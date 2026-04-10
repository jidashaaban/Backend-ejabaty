<?php
namespace App\Services;

use App\Models\Schedule;
use App\Models\Courses; // Ensure this matches your plural model name
use App\Models\Session;
use Carbon\Carbon;

class ScheduleGenerator {
    public function generate($type) {
        Schedule::where('type',$type)->delete();

        $schedule = Schedule::create(['type'=>$type]);
        
        // FIX 1: Use 'Courses' to match your model name 
        $courses = Courses::all()->shuffle(); 

        foreach($courses as $course) {
            $this->assignSession($schedule,$course,$type);
        }
        return $schedule;
    }

    private function assignSession($schedule, $course, $type) {
    // Define how many times this course should appear per week
    $requiredSessions = ($type === 'exam') ? 1 : 2; 
    $sessionsCreated = 0;

    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
    $startWindow = Carbon::createFromTimeString('08:00');
    $endWindow = Carbon::createFromTimeString('15:00');

    // To spread courses, we check every TIME slot across all DAYS first
    $currentTime = $startWindow->copy();
    while ($currentTime->lt($endWindow) && $sessionsCreated < $requiredSessions) {
        $startTime = $currentTime->toTimeString();
        
        // Shuffle days for each time slot to ensure random horizontal distribution
        shuffle($days); 

        foreach ($days as $day) {
            if ($sessionsCreated >= $requiredSessions) break;

            // Check if student is free AND course hasn't already been scheduled TODAY
            if (!$this->hasStudentConflict($course, $day, $startTime) && 
                !$this->isCourseAlreadyScheduledToday($schedule, $course, $day)) {
                
                Session::create([
                    'schedule_id' => $schedule->id,
                    'course_id' => $course->id,
                    'day' => $day,
                    'start_time' => $startTime,
                    'end_time' => $currentTime->copy()->addHours(2)->toTimeString()
                ]);
                
                $sessionsCreated++;
            }
        }
        // Move to the next 30-minute block after checking all days
        $currentTime->addMinutes(30);
    }
}

    private function hasStudentConflict($course, $day, $time) {
        $studentIds = $course->students()->pluck('users.id');

        return Session::where('day',$day)
            ->where('start_time',$time)
            ->whereHas('course.students',function($query) use ($studentIds) {
                // FIX 3: Variable name mismatch. Changed '$studentsIds' to '$studentIds' 
                $query->whereIn('users.id', $studentIds); 
            })->exists();
    }

    private function isCourseAlreadyScheduledToday($schedule, $course, $day) {
    return Session::where('schedule_id', $schedule->id)
        ->where('course_id', $course->id)
        ->where('day', $day)
        ->exists();
}
}