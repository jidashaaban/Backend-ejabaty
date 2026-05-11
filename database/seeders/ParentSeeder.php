<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ParentSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create or Update the Parent
        $parent = User::updateOrCreate(
            ['email' => 'parent@school.com'],
            [
                'name' => 'Ahmad',
                'father_name' => 'Ibrahim',
                'last_name' => 'Shaaban',
                'password' => Hash::make('password123'),
                'role' => 'parent',
                'phone_number' => '44455566',
                'status' => 'active',
            ]
        );

        // 2. Find a student who DOES NOT have a parent linked yet
        // We look for students who aren't in the parent_student table
        $availableStudent = User::where('role', 'student')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('parent_student')
                      ->whereColumn('parent_student.student_id', 'users.id');
            })
            ->first();

        // 3. Link them only if we found an "unclaimed" student
        if ($availableStudent) {
            $parent->children()->syncWithoutDetaching([$availableStudent->id]);
            $this->command->info("Parent linked to available student: " . $availableStudent->name);
        } else {
            // Check if this specific parent is already linked to someone
            if ($parent->children()->count() > 0) {
                $this->command->info("Parent already has their child linked.");
            } else {
                $this->command->warn("No unassigned students found. Link manually or create a new student first.");
            }
        }
    }
}