<?php
namespace App\Services;

use App\Models\Schedule; // Assuming your generated exams are stored here
use App\Models\Hall;
use App\Models\HallAssignment;
use App\Models\Courses;

class ExamHallService
{
    public function distributeStudents($sessionId, $courseId)
    {
        // 1. Fetch the course and its enrolled students
        $course = Courses::with('students')->find($courseId);
        $students = $course->students; 
        
        // 2. Fetch available halls, ordered from biggest to smallest
        $halls = Hall::orderBy('capacity', 'desc')->get();
        
        // Trackers
        $studentIndex = 0;
        $totalStudents = $students->count();

        // 3. Loop through the available halls
        foreach ($halls as $hall) {
            $capacity = $hall->capacity;

            // Take a "chunk" of students equal to this hall's capacity
            $studentsForThisHall = $students->slice($studentIndex, $capacity);

            // Assign this chunk of students to the current hall
            foreach ($studentsForThisHall as $student) {
                HallAssignment::create([
                    'session_id' => $sessionId,
                    'student_id' => $student->id,
                    'hall_id'    => $hall->id
                ]);
                $studentIndex++; // Move to the next student
            }

            // 4. Check if we have assigned all students. If yes, stop checking halls!
            if ($studentIndex >= $totalStudents) {
                break;
            }
        }
        if($studentIndex < $totalStudents) {
            $unassignedCount = $totalStudents - $studentIndex;
            return [
                'status' => 'warning',
                'message' => "Course {$course->name} has {$unassignedCount} students without a hall assignmnet."
            ];
        }
        return ['status' => 'success'];        
    }
}