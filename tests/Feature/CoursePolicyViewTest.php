<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoursePolicyViewTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coach = User::factory()->coach()->create();
    }

    private function draftCourse(): Course
    {
        return Course::factory()->draft()->create(['user_id' => $this->coach->id]);
    }

    public function test_admin_can_view_draft_course(): void
    {
        $admin = User::factory()->admin()->create();
        $course = $this->draftCourse();

        $this->actingAs($admin)
            ->get(route('courses.show', $course))
            ->assertOk();
    }

    public function test_owner_coach_can_view_own_draft_course(): void
    {
        $course = $this->draftCourse();

        $this->actingAs($this->coach)
            ->get(route('courses.show', $course))
            ->assertOk();
    }

    public function test_student_can_view_published_course(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->published()->create(['user_id' => $this->coach->id]);

        $this->actingAs($student)
            ->get(route('courses.show', $course))
            ->assertOk();
    }

    public function test_student_cannot_view_draft_course(): void
    {
        $student = User::factory()->student()->create();
        $course = $this->draftCourse();

        $this->actingAs($student)
            ->get(route('courses.show', $course))
            ->assertForbidden();
    }

    public function test_student_cannot_view_archived_course(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->archived()->create(['user_id' => $this->coach->id]);

        $this->actingAs($student)
            ->get(route('courses.show', $course))
            ->assertForbidden();
    }
}
