<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Hall;

class HallSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $halls = [
            ['name' => 'Hall 1', 'capacity' => 1],
            ['name' => 'Hall 2', 'capacity' => 1],
            ['name' => 'Hall 3', 'capacity' => 1],
            ['name' => 'Hall 4', 'capacity' => 1],
            ['name' => 'Lab A', 'capacity' => 1],
        ];

        foreach ($halls as $hall) {
            Hall::create($hall);
        }
    
    }
}
