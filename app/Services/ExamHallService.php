<?php

namespace App\Services;

use App\Models\Hall;
use App\Models\Student;
use App\Models\HallAssignment;
use App\Models\Courses;

class ExamHallService
{
    public function distributeStudents($sessionId, $courseId)
    {
        // 1. Fetch the Students enrolled in this course
        $students = Courses::find($courseId)->students;
        $totalStudents = $students->count();
        
        // 2. DYNAMICALLY FETCH HALLS FROM DATABASE [cite: 442]
        // We order by capacity descending to fill largest rooms first 
        $halls = Hall::orderBy('capacity', 'desc')->get();
        
        $studentIndex = 0;

        // 3. Loop through available halls until all students are seated [cite: 13, 14]
        foreach ($halls as $hall) {
            if ($studentIndex >= $totalStudents) break;

            $capacity = $hall->capacity;
            
            // Get the slice of students that fit in this hall [cite: 29]
            $studentsForThisHall = $students->slice($studentIndex, $capacity);

            foreach ($studentsForThisHall as $student) {
                // Save the assignment to the hall_assignments table [cite: 15]
                HallAssignment::create([
                    'session_id' => $sessionId,
                    'student_id' => $student->id,
                    'hall_id'    => $hall->id,
                ]);
            }

            // Move the index forward by the number of students we just seated [cite: 31]
            $studentIndex += $studentsForThisHall->count();
        }

        // 4. Return warnings if some students didn't get a seat [cite: 76, 79]
        if ($studentIndex < $totalStudents) {
            $missing = $totalStudents - $studentIndex;
            return "Warning: Not enough hall capacity for Course ID: $courseId. $missing students have no seats.";
        }

        return null;
    }
}