<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@school.com'], // Search by email
            [
                'name'     => 'Headmaster Admin',
                'password' => bcrypt('password'),
                'role'     => 'admin', // Essential for your logic
            ]
        );

        // 2. Create a secondary Admin (Registrar)
        User::updateOrCreate(
            ['email' => 'registrar@school.com'],
            [
                'name'     => 'School Registrar',
                'password' => bcrypt('password'),
                'role'     => 'admin',
            ]
        );

        $this->command->info("Admin accounts seeded successfully!");
    }
    
}
