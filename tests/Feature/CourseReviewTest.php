<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $coach = User::factory()->coach()->create();
        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->published()->create(['user_id' => $coach->id]);
    }

    private function enroll(User $user, string $status): Enrollment
    {
        return Enrollment::factory()->create([
            'user_id' => $user->id,
            'course_id' => $this->course->id,
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);
    }

    public function test_completed_student_can_post_review(): void
    {
        $this->enroll($this->student, 'completed');

        $this->actingAs($this->student)
            ->post(route('courses.reviews.store', $this->course), [
                'rating' => 4,
                'comment' => 'とても良いコースでした',
            ])
            ->assertRedirect(route('courses.show', $this->course));

        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'rating' => 4,
            'comment' => 'とても良いコースでした',
        ]);
    }

    public function test_comment_is_optional(): void
    {
        $this->enroll($this->student, 'completed');

        $this->actingAs($this->student)
            ->post(route('courses.reviews.store', $this->course), ['rating' => 5])
            ->assertRedirect(route('courses.show', $this->course));

        $this->assertDatabaseHas('reviews', [
            'course_id' => $this->course->id,
            'rating' => 5,
            'comment' => null,
        ]);
    }

    public function test_active_but_not_completed_student_cannot_post_review(): void
    {
        $this->enroll($this->student, 'active');

        $this->actingAs($this->student)
            ->post(route('courses.reviews.store', $this->course), ['rating' => 4])
            ->assertForbidden();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_non_student_cannot_post_review(): void
    {
        $coach = User::factory()->coach()->create();

        // role:student ミドルウェアにより student 以外はルート自体を弾かれる
        $this->actingAs($coach)
            ->post(route('courses.reviews.store', $this->course), ['rating' => 4])
            ->assertForbidden();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_student_cannot_post_two_reviews_for_same_course(): void
    {
        $this->enroll($this->student, 'completed');
        Review::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);

        $this->actingAs($this->student)
            ->post(route('courses.reviews.store', $this->course), ['rating' => 3])
            ->assertForbidden();

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_review_requires_rating_between_1_and_5(): void
    {
        $this->enroll($this->student, 'completed');

        $this->actingAs($this->student)
            ->post(route('courses.reviews.store', $this->course), ['comment' => '評価なし'])
            ->assertSessionHasErrors('rating');

        $this->actingAs($this->student)
            ->post(route('courses.reviews.store', $this->course), ['rating' => 0])
            ->assertSessionHasErrors('rating');

        $this->actingAs($this->student)
            ->post(route('courses.reviews.store', $this->course), ['rating' => 6])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_owner_can_update_own_review(): void
    {
        $this->enroll($this->student, 'completed');
        $review = Review::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'rating' => 2,
        ]);

        $this->actingAs($this->student)
            ->put(route('courses.reviews.update', [$this->course, $review]), [
                'rating' => 5,
                'comment' => '見直したら良かった',
            ])
            ->assertRedirect(route('courses.show', $this->course));

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 5,
            'comment' => '見直したら良かった',
        ]);
    }

    public function test_owner_can_delete_own_review(): void
    {
        $this->enroll($this->student, 'completed');
        $review = Review::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);

        $this->actingAs($this->student)
            ->delete(route('courses.reviews.destroy', [$this->course, $review]))
            ->assertRedirect(route('courses.show', $this->course));

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_student_cannot_update_or_delete_others_review(): void
    {
        $otherStudent = User::factory()->student()->create();
        $review = Review::factory()->create([
            'user_id' => $otherStudent->id,
            'course_id' => $this->course->id,
            'rating' => 3,
        ]);

        $this->actingAs($this->student)
            ->put(route('courses.reviews.update', [$this->course, $review]), ['rating' => 1])
            ->assertForbidden();

        $this->actingAs($this->student)
            ->delete(route('courses.reviews.destroy', [$this->course, $review]))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 3]);
    }

    public function test_reviews_are_listed_on_course_show_page(): void
    {
        $reviewer = User::factory()->student()->create(['name' => 'レビュー太郎']);
        Review::factory()->create([
            'user_id' => $reviewer->id,
            'course_id' => $this->course->id,
            'rating' => 5,
            'comment' => '最高のコース',
        ]);

        $this->actingAs($this->student)
            ->get(route('courses.show', $this->course))
            ->assertOk()
            ->assertSee('レビュー太郎')
            ->assertSee('最高のコース');
    }
}
