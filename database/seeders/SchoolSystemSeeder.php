<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Courses;
use Illuminate\Support\Facades\Hash;

class SchoolSystemSeeder extends Seeder
{
    public function run()
    {
        
        // 1. Create 5 Teachers
        $teachers = [];
        for ($i = 1; $i <= 5; $i++) {
            $teachers[] = User::create([
                'name' => "Teacher $i",
                'email' => "teacher$i@uni.edu",
                'password' => Hash::make('password'),
                'role' => 'teacher'
            ]);
        }

        // 2. Create 20 Courses and assign them to random teachers
        $courseList = [
            'Math 101', 'Physics 101', 'Chemistry 101', 'Biology 101', 'History 101',
            'English 201', 'Calculus II', 'Database Systems', 'Web Development', 'Mobile Apps',
            'Cyber Security', 'AI Basics', 'Software Engineering', 'Data Structures', 'Algorithms',
            'Network Security', 'Operating Systems', 'Cloud Computing', 'UI/UX Design', 'Discrete Math'
        ];

        $createdCourses = [];
        foreach ($courseList as $index => $name) {
    $createdCourses[] = Courses::create([
        'name' => $name,
        // ADD THIS LINE BELOW:
        'code' => 'CRS-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT), 
        'teacher_id' => $teachers[array_rand($teachers)]->id 
    ]);

        }

        // 3. Create 50 Students
        $students = [];
        for ($i = 1; $i <= 50; $i++) {
            $students[] = User::create([
                'name' => "Student $i",
                'email' => "student$i@student.edu",
                'password' => Hash::make('password'),
                'role' => 'student'
            ]);
        }

        // 4. Randomly Enroll Students into Courses (5 courses per student)
        foreach ($students as $student) {
            // Pick 5 random unique courses for each student
            $randomCourses = array_rand($createdCourses, 5);
            foreach ($randomCourses as $courseIndex) {
                $student->courses()->attach($createdCourses[$courseIndex]->id);
            }
        }
    }
}