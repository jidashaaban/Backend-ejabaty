<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HallSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Hall::create(['name' => 'Auditorium A', 'capacity' => 10]);
    \App\Models\Hall::create(['name' => 'Room 101', 'capacity' => 30]);
    }
}
