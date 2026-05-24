<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Courses;
use App\Models\Exam;
use App\Models\ExamQuestion;

class TeacherExamControllerTest extends TestCase
{
    use RefreshDatabase;

    // Helper to bypass schema constraints
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
    public function teacher_can_create_exam_for_owned_course()
    {
        $teacher = $this->createTestUser('teacher');
        $course = Courses::create([
            'name' => 'Web Development', 
            'code' => 'WD101', 
            'teacher_id' => $teacher->id, 
            'capacity' => 20
        ]);

        $q1 = ExamQuestion::create(['question' => 'What is Laravel?']);
        $q2 = ExamQuestion::create(['question' => 'What is React?']);

        $response = $this->actingAs($teacher, 'sanctum')->postJson('/api/teacher/exams/create', [
            'course_name' => 'Web Development',
            'title' => 'Final Exam',
            'questions' => [$q1->question, $q2->question]
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('exams', ['title' => 'Final Exam', 'course_id' => $course->id]);
    }

    /** @test */
    public function teacher_cannot_create_exam_for_course_they_do_not_teach()
    {
        $teacherA = $this->createTestUser('teacher');
        $teacherB = $this->createTestUser('teacher');
        
        Courses::create([
            'name' => 'Secret Course', 
            'code' => 'SEC999', 
            'teacher_id' => $teacherB->id, 
            'capacity' => 20
        ]);

        $response = $this->actingAs($teacherA, 'sanctum')->postJson('/api/teacher/exams/create', [
            'course_name' => 'Secret Course',
            'title' => 'Hacked Exam',
            'questions' => ['Question 1']
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function teacher_can_submit_marking_scheme_for_owned_exam()
    {
        $teacher = $this->createTestUser('teacher');
        $course = Courses::create(['name' => 'Math', 'code' => 'M1', 'teacher_id' => $teacher->id, 'capacity' => 10]);
        $exam = Exam::create(['title' => 'Math Midterm', 'course_id' => $course->id, 'is_published' => false]);
        
        $q = ExamQuestion::create(['question' => '1+1?']);
        $exam->questions()->attach($q->id);

        $response = $this->actingAs($teacher, 'sanctum')->postJson("/api/teacher/exams/{$exam->id}/submit-marking", [
            'marking_data' => [
                ['question_id' => $q->id, 'answer' => '2']
            ]
        ]);

        $response->assertStatus(200);
        $this->assertTrue((bool)$exam->fresh()->is_published);
    }
}