<?php

namespace App\Services;

use App\Models\Session;
use App\Models\Hall;

class CourseHallAssigner
{
    /**
     * Assigns one hall to each course session in a specific schedule.
     * Prioritizes the largest classes for the largest halls.
     */
    public function assignHallsToCourseSchedule($scheduleId)
    {
        // 1. Group sessions by their day and time slot to prevent overlaps
        $sessions = Session::with('course.students')->where('schedule_id', $scheduleId)->get();
        $groupedSessions = $sessions->groupBy(function ($session) {
            return $session->day . '_' . $session->start_time;
        });

        // 2. Fetch all halls (largest first)
        $allHalls = Hall::orderBy('capacity', 'desc')->get();
        $adminAlerts = [];

        foreach ($groupedSessions as $timeSlot => $timeSessions) {
            // Sort courses in this slot by student enrollment (largest first)
            $timeSessions = $timeSessions->sortByDesc(function ($session) {
                return $session->course->students->count();
            });

            // Available hall pool for this specific time slot
            $availableHalls = collect($allHalls->values()->all());

            foreach ($timeSessions as $session) {
                $studentCount = $session->course->students->count();
                $assignedHall = $availableHalls->first();

                if ($assignedHall) {
                    // Update the session table with the single assigned room
                    $session->update(['hall_id' => $assignedHall->id]);

                    // Check if the hall is too small (Partial Fit Logic)
                    if ($studentCount > $assignedHall->capacity) {
                        $overflowCount = $studentCount - $assignedHall->capacity;
                        
                        // Extract names of students who won't fit to show the admin
                        $missingStudents = $session->course->students
                            ->skip($assignedHall->capacity)
                            ->pluck('name')
                            ->implode(', ');

                        $adminAlerts[] = "ALERT: {$session->course->name} is in {$assignedHall->name}, but {$overflowCount} students won't fit. Missing seats for: {$missingStudents}.";
                    }

                    // Remove this hall from the pool for THIS time slot only
                    $availableHalls->shift(); 
                } else {
                    // Logic for when there are more classes than rooms at a certain time
                    $adminAlerts[] = "CRITICAL: No hall available for {$session->course->name} at {$session->day} {$session->start_time}.";
                }
            }
        }

        return $adminAlerts;
    }
}