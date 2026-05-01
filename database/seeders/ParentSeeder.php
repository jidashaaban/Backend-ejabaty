<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User; 

class ParentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parent = User::create([
            'name' => 'John Doe (Parent)',
            'email' => 'parent2@example.com',
            'password' => bcrypt('password'),
            'role' => 'parent', // Using the role column to identify them
        ]);

        // 2. Find some existing students to link to this parent
        // We look for users whose role is 'student'
        $students = User::where('role', 'student')->take(2)->get();

        if ($students->count() > 0) {
            foreach ($students as $student) {
                // Link the parent to the student using the relationship we defined
                $parent->children()->attach($student->id);
            }
            
            $this->command->info("Parent created and linked to: " . $students->pluck('name')->implode(', '));
        } else {
            $this->command->warn("Parent created, but no students found in the database to link them to.");
        }

        // 3. Create a second parent for more variety
        $parent2 = User::create([
            'name' => 'Jane Smith (Parent)',
            'email' => 'parent3@example.com',
            'password' => bcrypt('password'),
            'role' => 'parent',
        ]);
    }
    
}
