<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Schedule;
use App\Models\Courses; // Matches your project model naming syntax
use App\Models\Session;
use App\Models\Hall;
use App\Services\HallAssigner;

class HallAssignerTest extends TestCase
{
    use RefreshDatabase; // Safely clears out database state transitions between tests

    /**
     * Helper constructor to generate users with your strict database
     * profile constraints (father_name, last_name, phone_number)
     */
    private function createTestUser(string $role, array $extraAttributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'father_name' => 'Mock Father',
            'last_name' => 'Mock Last',
            'phone_number' => '0509998877'
        ], $extraAttributes));
    }

    /** @test */
    public function test_assigner_blocks_non_administrators()
    {
        $student = $this->createTestUser('student');
        $schedule = Schedule::create(['type' => 'course']);

        $service = new HallAssigner();
        $this->actingAs($student, 'sanctum');
        $result = $service->assignHallsToSchedule($schedule->id);

        $this->assertEquals(403, $result->getStatusCode());
    }

    /** @test */
    public function test_assigner_maps_perfect_room_fit_path_a()
    {
        $admin = $this->createTestUser('admin');
        $this->actingAs($admin, 'sanctum');

        $schedule = Schedule::create(['type' => 'course']);
        $hall = Hall::create(['name' => 'Room 101', 'capacity' => 30]);
        
        // Dynamic Teacher Generation to solve the Foreign Key constraint
        $teacher = $this->createTestUser('teacher');
        $course = Courses::create([
            'name' => 'Artificial Intelligence', 
            'code' => 'CS401', 
            'capacity' => 30, 
            'teacher_id' => $teacher->id // Fixed: Linked directly to real dynamic ID
        ]);
        
        $student1 = $this->createTestUser('student', ['email' => 's1@test.com']);
        $student2 = $this->createTestUser('student', ['email' => 's2@test.com']);
        $course->students()->attach([$student1->id, $student2->id], ['status' => 'paid']);

        $session = Session::create([
            'schedule_id' => $schedule->id,
            'course_id' => $course->id,
            'day' => 'Sunday',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00'
        ]);

        $service = new HallAssigner();
        $result = $service->assignHallsToSchedule($schedule->id);

        $this->assertTrue($result['success']);
        $this->assertEmpty($result['report']);
        $this->assertEquals($hall->id, $session->fresh()->hall_id);
    }

    /** @test */
    public function test_assigner_calculates_greedy_overflow_partial_fit_path_b()
    {
        $admin = $this->createTestUser('admin');
        $this->actingAs($admin, 'sanctum');

        $schedule = Schedule::create(['type' => 'course']);
        $smallHall = Hall::create(['name' => 'Lab A', 'capacity' => 1]);
        
        // Dynamic Teacher Generation to solve the Foreign Key constraint
        $teacher = $this->createTestUser('teacher');
        $course = Courses::create([
            'name' => 'Cyber Security', 
            'code' => 'CS409', 
            'capacity' => 30, 
            'teacher_id' => $teacher->id // Fixed: Linked directly to real dynamic ID
        ]);

        $student1 = $this->createTestUser('student', ['name' => 'Ali', 'email' => 'ali@test.com']);
        $student2 = $this->createTestUser('student', ['name' => 'Omar', 'email' => 'omar@test.com']);
        $course->students()->attach([$student1->id, $student2->id], ['status' => 'paid']);

        Session::create([
            'schedule_id' => $schedule->id,
            'course_id' => $course->id,
            'day' => 'Monday',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00'
        ]);

        $service = new HallAssigner();
        $result = $service->assignHallsToSchedule($schedule->id);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['report']);
        $this->assertStringContainsString('ALERT:', $result['report'][0]);
    }

    /** @test */
    public function test_assigner_handles_total_hall_exhaustion_path_c()
    {
        $admin = $this->createTestUser('admin');
        $this->actingAs($admin, 'sanctum');

        $schedule = Schedule::create(['type' => 'course']);
        
        // Dynamic Teacher Generation to solve the Foreign Key constraint
        $teacher = $this->createTestUser('teacher');
        $course = Courses::create([
            'name' => 'Computer Vision', 
            'code' => 'CS411', 
            'capacity' => 30, 
            'teacher_id' => $teacher->id // Fixed: Linked directly to real dynamic ID
        ]);

        Session::create([
            'schedule_id' => $schedule->id,
            'course_id' => $course->id,
            'day' => 'Tuesday',
            'start_time' => '13:00:00',
            'end_time' => '15:00:00'
        ]);

        $service = new HallAssigner();
        $result = $service->assignHallsToSchedule($schedule->id);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['report']);
        $this->assertStringContainsString('CRITICAL: No hall could be assigned', $result['report'][0]);
    }
}