<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Courses;
use Illuminate\Support\Facades\Hash;

class SchoolSystemSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create an Admin
        User::updateOrCreate(
            ['email' => 'admin@school.com'],
            [
                'name' => 'System',
                'father_name' => 'Admin',
                'last_name' => 'Manager',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'phone_number' => '12345678',
                'health_state' => null,
                'status' => 'active',
                // Non-student fields set to null
                'grade' => null,
                'past_education' => null,
                'last_years_mark' => null,
            ]
        );

        // 2. Create a Teacher
        User::updateOrCreate(
            ['email' => 'sam@school.com'],
            [
                'name' => 'Sam',
                'father_name' => 'Robert',
                'last_name' => 'Smith',
                'password' => Hash::make('password123'),
                'role' => 'teacher',
                'phone_number' => '87654321',
                'health_state' => 'None',
                'status' => 'active',
                'grade' => null,
                'past_education' => 'PhD in Computer Science',
                'last_years_mark' => null,
            ]
        );

        // 3. Create a Student (The "Child")
        $student = User::updateOrCreate(
            ['email' => 'jida@school.com'],
            [
                'name' => 'Jida',
                'father_name' => 'Ahmad',
                'last_name' => 'Shaaban',
                'password' => Hash::make('password123'),
                'role' => 'student',
                'phone_number' => '55566677',
                'health_state' => 'Allergy: Peanuts',
                'status' => 'active',
                'grade' => '12th Grade',
                'past_education' => '11th Grade - High School',
                'last_years_mark' => 95.5,
            ]
        );

        // 4. Create a Parent
        $parent = User::updateOrCreate(
            ['email' => 'ahmad@parent.com'],
            [
                'name' => 'Ahmad',
                'father_name' => 'Ibrahim',
                'last_name' => 'Shaaban',
                'password' => Hash::make('password123'),
                'role' => 'parent',
                'phone_number' => '44455566',
                'health_state' => null,
                'status' => 'active',
                'grade' => null,
                'past_education' => null,
                'last_years_mark' => null,
            ]
        );

        // Link Parent to Student in the pivot table [cite: 73, 74]
        // This ensures the parent can see Jida's data on their dashboard
        $parent->children()->sync([$student->id]);
    }
}