<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Schedule;
use App\Models\Session;
use App\Models\Courses;

class SpecialScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    // Aligned to your api.php route structure
    protected string $myScheduleRoute = '/api/student/my-schedule';
    protected string $upcomingExamsRoute = '/api/student/upcoming-exams';

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
    public function test_my_schedule_returns_404_when_no_master_schedule_exists()
    {
        $student = $this->createTestUser('student');

        // Acts as an authenticated student
        $response = $this->actingAs($student, 'sanctum')
                         ->getJson($this->myScheduleRoute . '?type=course');

        $response->assertStatus(404)
                 ->assertJsonFragment(['message' => 'No course schedule found.']);
    }

    /** @test */
    public function test_upcoming_exams_filters_and_returns_only_enrolled_courses()
    {
        $student = $this->createTestUser('student');
        $teacher = $this->createTestUser('teacher');

        // 1. Create a Master Exam Schedule container
        $examSchedule = Schedule::create(['type' => 'exam']);

        // 2. Create course profiles
        $enrolledCourse = Courses::create([
            'name' => 'Machine Learning',
            'code' => 'AI401',
            'teacher_id' => $teacher->id,
            'capacity' => 30
        ]);

        $otherCourse = Courses::create([
            'name' => 'History 101',
            'code' => 'HIS101',
            'teacher_id' => $teacher->id,
            'capacity' => 30
        ]);

        // 3. Link student ONLY to Machine Learning
        $student->courses()->attach($enrolledCourse->id, ['status' => 'paid']);

        // 4. Create exam sessions
        Session::create([
            'schedule_id' => $examSchedule->id,
            'course_id' => $enrolledCourse->id,
            'day' => 'Monday',
            'date' => '2026-05-25',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00'
        ]);

        Session::create([
            'schedule_id' => $examSchedule->id,
            'course_id' => $otherCourse->id,
            'day' => 'Tuesday',
            'date' => '2026-05-26',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00'
        ]);

        // 5. Run structural request
        $response = $this->actingAs($student, 'sanctum')->getJson($this->upcomingExamsRoute);

        // Assert success and that only the enrolled course (1 session) is returned
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['exam_name' => 'Machine Learning']);
    }
}