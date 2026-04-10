<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Courses; 
use App\Models\Session;
use Carbon\Carbon;

class ScheduleGenerator {

    public function generate($type) {
        Schedule::where('type', $type)->delete();
        $schedule = Schedule::create(['type' => $type]);
        $courses = Courses::all()->shuffle(); 

        foreach ($courses as $course) {
            $this->assignSession($schedule, $course, $type);
        }
        return $schedule;
    }

    private function assignSession($schedule, $course, $type) {
        $requiredSessions = ($type === 'exam') ? 1 : 2; 
        $sessionsCreated = 0;

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
        $startWindow = Carbon::createFromTimeString('08:00');
        $endWindow = Carbon::createFromTimeString('15:00');

        $currentTime = $startWindow->copy();

        while ($currentTime->lt($endWindow) && $sessionsCreated < $requiredSessions) {
            $startTime = $currentTime->toTimeString();
            shuffle($days); 

            foreach ($days as $day) {
                if ($sessionsCreated >= $requiredSessions) break;

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
            $currentTime->addMinutes(30);
        }
    } // End of assignSession

    private function hasStudentConflict($course, $day, $time) {
        $studentIds = $course->students()->pluck('users.id');

        return Session::where('day', $day)
            ->where(function ($query) use ($time) {
                $query->where('start_time', '<=', $time)
                      ->where('end_time', '>', $time);
            })
            ->whereHas('course.students', function($query) use ($studentIds) {
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