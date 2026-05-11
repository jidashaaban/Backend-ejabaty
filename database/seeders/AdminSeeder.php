<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash; 
class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'registrar@school.com'],
            [
                'name' => 'School',
                'father_name' => 'System', // Added to prevent DB error
                'last_name' => 'Registrar', // Added to prevent DB error
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'phone_number' => '00000000', // Added to prevent DB error
                'status' => 'active',        // Added to prevent DB error
                'health_state' => null,
                'grade' => null,
                'past_education' => null,
                'last_years_mark' => null,
            ]
        );
    }
    
}
