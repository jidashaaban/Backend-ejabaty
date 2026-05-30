<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Courses; 
use App\Models\Session;
use App\Models\Hall;
use App\Services\ExamHallService;
use App\Models\User;
use Carbon\Carbon;

class ScheduleGenerator {
    
    protected $hallService;

    public function __construct(ExamHallService $hallService) {
        $this->hallService = $hallService;
    }
    
    public function generate($type) {
        set_time_limit(120); 
        $requester = auth()->user();

        if (!$requester || $requester->role !== 'admin') {
               return response()->json([
                   'message' => 'Forbidden: Only Administrators can perform this action.'
    ], 403);
}

        // 1. CLEAN OLD DATA
        // Fetch the most recently created schedule for this type.  Using
        // `latest()` ensures we always operate on the newest schedule record.
        // Without this the first record would be reused, leaving newer
        // schedules untouched.  This caused the admin interface to fetch
        // an outdated schedule (with no sessions) after generation.
        $schedule = Schedule::where('type', $type)->latest()->first();

        if ($schedule) {
            // Remove any existing sessions attached to this schedule.  New
            // sessions will be generated below.  We intentionally reuse
            // the latest schedule record so its `created_at` timestamp
            // continues to indicate when it was last generated.
            Session::where('schedule_id', $schedule->id)->delete();
        } else {
            // If no schedule exists yet for this type, create one.
            $schedule = Schedule::create(['type' => $type]);
        }

        // 2. GET COURSES
        $courses = Courses::where('is_active',true)
            ->withCount('students')
            ->orderBy('students_count','desc')
            ->get();
        
        $failedCourses = [];

        // 3. GENERATE SESSIONS & ASSIGN HALLS
        foreach ($courses as $course) {
            // Solve Time & Room assignment
            $session = $this->assignSession($schedule, $course, $type);
            
            if ($session) {
                // If this is an exam, we use the complex multi-hall distribution
                if ($type === 'exam') {
                    $hallResult = $this->hallService->distributeStudents($session->id, $course->id);

                    if ($hallResult) {
                        $failedCourses[] = $hallResult;
                    } 
                } 
                // Note: For 'course' type, the room was already assigned inside assignSession()
            } else {
                $failedCourses[] = "Alert: Could not find a time slot or available hall for {$course->name}";
            }
        }

        return [
            // Load both single hall (for courses) and multiple halls (for exams)
            'schedule' => $schedule->load(['sessions.course', 'sessions.hall', 'sessions.hallAssignments.hall']), 
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

        $startDate = Carbon::parse('2026-06-21'); 
        $dayOffsets = [
           'Sunday'    => 0,
           'Monday'    => 1,
           'Tuesday'   => 2,
           'Wednesday' => 3,
           'Thursday'  => 4,
    ];

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

                // Hall assignment for courses is handled AFTER all sessions are created
                // by CourseHallAssigner::assignHallsToCourseSchedule() which distributes
                // all halls properly. We skip per-slot hall check here to avoid single-hall reuse.
                $hallId = null;

                if (!$hasStudentConflict && !$hasTeacherConflict) {
                    $calculatedDate=$startDate->copy()->addDays($dayOffsets[$day]);

                    $lastCreatedSession = Session::create([
                        'schedule_id' => $schedule->id,
                        'course_id'   => $course->id,
                        'day'         => $day,
                        'date'        =>$calculatedDate,
                        'start_time'  => $time,
                        'end_time'    => date('H:i:s', strtotime($time . ' +90 minutes')),
                        'hall_id'     => $hallId, // Assigned for courses, null for exams (exams use pivot table)
                    ]);
                    $sessionsCreated++;
                    break; 
                }
            }
        }
        return $lastCreatedSession; 
    }

    /**
     * Finds a hall that isn't already booked for another course at this time
     */
    private function getAvailableHallId($day, $time, $scheduleId) {
        // Get IDs of halls already occupied at this time in the same schedule
        $occupiedHallIds = Session::where('day', $day)
            ->where('start_time', $time)
            ->where('schedule_id', $scheduleId)
            ->whereNotNull('hall_id')
            ->pluck('hall_id');

        // Return the first hall that is NOT in the occupied list
        return Hall::whereNotIn('id', $occupiedHallIds)->first()?->id;
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