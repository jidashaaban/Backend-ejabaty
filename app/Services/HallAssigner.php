<?php

namespace App\Services;

use App\Models\Session;
use App\Models\Hall;

class HallAssigner 
{
    public function assignHallsToSchedule($scheduleId)
    {
        // Fetch sessions with enrolled students [cite: 5, 8]
        $sessions = Session::with('course.students')->where('schedule_id', $scheduleId)->get();
        $halls = Hall::all();
        
        $adminMessages = [];

        // Group sessions by Day and Time to prevent overlaps [cite: 10]
        $groupedSessions = $sessions->groupBy(function($session) {
            return $session->day . '_' . $session->start_time;
        });

        foreach ($groupedSessions as $timeSlot => $concurrentSessions) {
            // 1. Sort concurrent sessions by student count (Largest classes get rooms first) 
            $concurrentSessions = $concurrentSessions->sortByDesc(function($session) {
                return $session->course->students->count();
            })->values();

            // 2. Prepare available halls for this specific time slot
            $availableHalls = $halls->sortByDesc('capacity')->values()->toArray();

            foreach ($concurrentSessions as $session) {
                $students = $session->course->students;
                $studentCount = $students->count();
                $assignedHall = null;
                $hallIndex = -1;

                // Step A: Find the best available hall that fits everyone perfectly [cite: 25]
                foreach ($availableHalls as $index => $hall) {
                    if ($hall['capacity'] >= $studentCount) {
                        $assignedHall = $hall;
                        $hallIndex = $index;
                        break;
                    }
                }

                // Step B: Partial Fit Logic - If no hall is big enough, take the largest one left 
                if (!$assignedHall && count($availableHalls) > 0) {
                    $assignedHall = $availableHalls[0];
                    $hallIndex = 0;
                }

                // Step C: Assign Hall and Report Overflow [cite: 29, 31]
                if ($assignedHall) {
                    $session->update(['hall_id' => $assignedHall['id']]);
                    
                    // Check if there is an overflow
                    if ($assignedHall['capacity'] < $studentCount) {
                        $overflowCount = $studentCount - $assignedHall['capacity'];
                        
                        // Extract names of students who don't fit (starting from the capacity limit) [cite: 30]
                        $overflowNames = $students->skip($assignedHall['capacity'])->pluck('name')->implode(', ');
                        
                        $adminMessages[] = "ALERT: {$session->course->name} was assigned to {$assignedHall['name']}, but {$overflowCount} students do not fit. Missing seats for: {$overflowNames}.";
                    }

                    // Remove this hall from the pool for this time slot [cite: 14]
                    unset($availableHalls[$hallIndex]);
                    $availableHalls = array_values($availableHalls);
                } else {
                    // Total failure: No physical halls left in the building for this time slot 
                    $adminMessages[] = "CRITICAL: No hall could be assigned to {$session->course->name} on {$session->day} at {$session->start_time} because all halls are occupied.";
                }
            }
        }

        return [
            'success' => true,
            'report' => $adminMessages
        ];
    }
}