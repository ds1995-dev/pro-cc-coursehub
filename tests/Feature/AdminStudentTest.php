<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStudentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        // CategoryFactory は日本語名を Str::slug すると slug が空になり重複するため、
        // テストでは 1 件だけ作成して使い回す
        $this->category = Category::factory()->create();
    }

    public function test_admin_can_view_student_list_with_enrollment_count(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $courses = Course::factory()->count(2)->create([
            'category_id' => $this->category->id,
        ]);
        foreach ($courses as $course) {
            Enrollment::factory()->create([
                'user_id' => $student->id,
                'course_id' => $course->id,
            ]);
        }

        $response = $this->actingAs($this->admin)->get('/admin/students');

        $response->assertStatus(200);
        $response->assertSee($student->name);
        // withCount('enrollments') が enrollments_count を正しく算出していること
        $response->assertViewHas('students', function ($students) use ($student) {
            $listed = $students->firstWhere('id', $student->id);

            return $listed !== null && $listed->enrollments_count === 2;
        });
    }

    public function test_student_cannot_view_admin_student_list(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get('/admin/students');

        $response->assertStatus(403);
    }
}
