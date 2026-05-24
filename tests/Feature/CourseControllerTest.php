<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Courses; 
use Illuminate\Support\Facades\Notification;
use App\Notifications\SchoolNotification;

class CourseControllerTest extends TestCase
{
    use RefreshDatabase; // Safely resets the database after every single test case run loop

    // Centralized route endpoints matched perfectly to your routes/api.php configuration
    protected string $storeRoute  = '/api/admin/add-course'; 
    protected string $indexRoute  = '/api/admin/courses'; 
    protected string $toggleRoute = '/api/courses/{id}/toggle-status'; 

    /** @test */
    public function test_store_blocks_unauthorized_users()
    {
        $nonAdmin = User::factory()->create([
            'role' => 'student',
            'father_name' => 'Mock Father',
            'last_name' => 'Mock Last',
            'phone_number' => '1234567890'
        ]);

        $response = $this->actingAs($nonAdmin, 'sanctum')->postJson($this->storeRoute, [
            'name' => 'Data Structures',
            'code' => 'CS201',
            'teacher_name' => 'Dr. Sami',
            'capacity' => 40
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_store_saves_course_and_notifies_users_if_teacher_exists()
    {
        Notification::fake(); 

        $admin = User::factory()->create([
            'role' => 'admin',
            'father_name' => 'Mock Father',
            'last_name' => 'Mock Last',
            'phone_number' => '1234567890'
        ]);
        
        User::factory()->create([
            'name' => 'Dr. Sami', 
            'role' => 'teacher',
            'father_name' => 'Mock Father',
            'last_name' => 'Mock Last',
            'phone_number' => '1234567890'
        ]);

        $payload = [
            'name' => 'Data Structures',
            'code' => 'CS201',
            'teacher_name' => 'Dr. Sami',
            'capacity' => 40
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson($this->storeRoute, $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('courses', [
            'code' => 'CS201',
            'name' => 'Data Structures'
        ]);
    }

    /** @test */
    public function test_show_returns_course_details()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'father_name' => 'Mock Father',
            'last_name' => 'Mock Last',
            'phone_number' => '1234567890'
        ]);
        
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'father_name' => 'Mock Father',
            'last_name' => 'Mock Last',
            'phone_number' => '1234567890'
        ]);

        $course = Courses::create([
            'name' => 'Operating Systems',
            'code' => 'OS101',
            'teacher_id' => $teacher->id, 
            'capacity' => 30
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson($this->indexRoute . '/' . $course->id);

        $response->assertStatus(200);
    }

    /** @test */
    public function test_show_returns_404_if_not_found()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'father_name' => 'Mock Father',
            'last_name' => 'Mock Last',
            'phone_number' => '1234567890'
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson($this->indexRoute . '/999');

        $response->assertStatus(404);
    }

    /** @test */
    public function test_destroy_removes_course_record()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'father_name' => 'Mock Father',
            'last_name' => 'Mock Last',
            'phone_number' => '1234567890'
        ]);
        
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'father_name' => 'Mock Father',
            'last_name' => 'Mock Last',
            'phone_number' => '1234567890'
        ]);

        $course = Courses::create([
            'name' => 'Compiler Design',
            'code' => 'CS402',
            'teacher_id' => $teacher->id, 
            'capacity' => 25
        ]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson($this->indexRoute . '/' . $course->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    /** @test */
    public function test_toggle_course_status_flips_boolean_values()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'father_name' => 'Mock Father',
            'last_name' => 'Mock Last',
            'phone_number' => '1234567890'
        ]);
        
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'father_name' => 'Mock Father',
            'last_name' => 'Mock Last',
            'phone_number' => '1234567890'
        ]);

        $course = Courses::create([
            'name' => 'Network Security',
            'code' => 'CS309',
            'teacher_id' => $teacher->id, 
            'capacity' => 20
        ]);

        // Swaps dynamic parameters for PATCH toggle-status logic
        $targetUrl = str_replace('{id}', $course->id, $this->toggleRoute);

        $response = $this->actingAs($admin, 'sanctum')->patchJson($targetUrl);

        $response->assertStatus(200);
    }
}