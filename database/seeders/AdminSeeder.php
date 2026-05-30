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
            ['email' => 'admin@school.com'],
            [
                'name' => 'Admin',
                'father_name' => 'System',
                'last_name' => 'Main',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'phone_number' => '0111111111',
                'status' => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'registrar@school.com'],
            [
                'name' => 'School',
                'father_name' => 'System',
                'last_name' => 'Registrar',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'phone_number' => '00000000',
                'status' => 'active',
            ]
        );
    }
    
}
