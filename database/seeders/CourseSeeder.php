<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Courses; // Use your model name
use App\Models\User;

class CourseSeeder extends Seeder
{
    public function run()
    {
        // 1. Fetch teacher IDs (we need these for the teacher_id column)
        $teacherIds = User::where('role', 'teacher')->pluck('id')->toArray();

        if (empty($teacherIds)) {
            $this->command->warn("No teachers found. Run your UserSeeder first!");
            return;
        }

        // 2. Define sample course data
        $courses = [
            [
                'name' => 'Software Engineering',
                'code' => 'CRS-SE-101',
                'capacity' => 30,
                'teacher_id' => $teacherIds[0], // Assign to the first teacher
            ],
            [
                'name' => 'Advanced Database Systems',
                'code' => 'CRS-DB-202',
                'capacity' => 25,
                'teacher_id' => $teacherIds[1] ?? $teacherIds[0],
            ],
            [
                'name' => 'Mobile App Development',
                'code' => 'CRS-MOB-303',
                'capacity' => 20,
                'teacher_id' => $teacherIds[2] ?? $teacherIds[0],
            ],
        ];

        // 3. Loop and save to the database
        foreach ($courses as $data) {
            Courses::updateOrCreate(
                ['code' => $data['code']], // Use 'code' as the unique key to prevent duplicates
                $data
            );
        }

        $this->command->info("CourseSeeder: Success! Courses have been added.");
    }
}