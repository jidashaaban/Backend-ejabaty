<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Hall;
use App\Models\Courses;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SchoolSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Halls with varying capacities [cite: 246]
        $halls = [
            ['name' => 'Grand Hall', 'capacity' => 100],
            ['name' => 'Room 101', 'capacity' => 30],
            ['name' => 'Room 102', 'capacity' => 30],
            ['name' => 'Lecture Theater', 'capacity' => 150],
        ];
        foreach ($halls as $hall) {
            Hall::create($hall);
        }

        // 2. Create 200 Students [cite: 223, 278]
        $students = User::factory()->count(200)->create();

        // 3. Create 15 Courses [cite: 130, 224]
        $courses = [
            ['name' => 'Database Systems', 'code' => 'CS301'],
            ['name' => 'Web Development', 'code' => 'CS302'],
            ['name' => 'Data Structures', 'code' => 'CS201'],
            ['name' => 'Algorithms', 'code' => 'CS202'],
            ['name' => 'Operating Systems', 'code' => 'CS401'],
            ['name' => 'Artificial Intelligence', 'code' => 'CS405'],
            ['name' => 'Software Engineering', 'code' => 'CS305'],
            ['name' => 'Calculus I', 'code' => 'MATH101'],
            ['name' => 'Linear Algebra', 'code' => 'MATH201'],
            ['name' => 'Physics I', 'code' => 'PHYS101'],
        ];

        foreach ($courses as $courseData) {
            $course = Courses::create($courseData);

            // 4. Enroll a random number of students in each course (20-60 per course)
            // This is crucial for testing student conflicts in the generator [cite: 103, 167]
            $enrolledStudents = $students->random(rand(20, 60))->pluck('id');
            $course->students()->attach($enrolledStudents);
        }
    }
}
    
