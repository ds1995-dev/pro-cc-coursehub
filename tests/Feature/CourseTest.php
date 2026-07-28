<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private User $student;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coach = User::factory()->create(['role' => 'coach']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->category = Category::factory()->create();
    }

    public function test_student_can_view_course_list(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->student)->get('/courses');

        $response->assertStatus(200);
        $response->assertSee($course->title);
    }

    public function test_student_can_view_published_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->student)->get("/courses/{$course->id}");

        $response->assertStatus(200);
        $response->assertSee($course->title);
    }

    public function test_coach_can_create_course(): void
    {
        $tags = Tag::factory()->count(2)->create();

        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => 'テストコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
            'tags' => $tags->pluck('id')->toArray(),
        ]);

        $response->assertRedirect('/coach/courses');
        $this->assertDatabaseHas('courses', [
            'title' => 'テストコース',
            'user_id' => $this->coach->id,
        ]);
    }

    public function test_coach_can_create_course_syncs_existing_tags(): void
    {
        $tags = Tag::factory()->count(2)->create();

        $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '既存タグ紐付けコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
            'tags' => $tags->pluck('id')->toArray(),
        ]);

        $course = Course::where('title', '既存タグ紐付けコース')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            $tags->pluck('id')->toArray(),
            $course->tags->pluck('id')->toArray()
        );
    }

    public function test_published_course_sets_published_at(): void
    {
        $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '公開コース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'published',
        ]);

        $course = Course::where('title', '公開コース')->firstOrFail();
        $this->assertNotNull($course->published_at);
    }

    public function test_draft_course_does_not_set_published_at(): void
    {
        $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '下書きコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ]);

        $course = Course::where('title', '下書きコース')->firstOrFail();
        $this->assertNull($course->published_at);
    }

    public function test_japanese_title_generates_non_empty_slug(): void
    {
        $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '日本語タイトル',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ]);

        $course = Course::where('title', '日本語タイトル')->firstOrFail();
        $this->assertNotEmpty($course->slug);
        $this->assertStringStartsWith('course-', $course->slug);
    }

    public function test_coach_can_create_course_with_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '画像付きコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
            'image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $course = Course::where('title', '画像付きコース')->firstOrFail();
        $this->assertNotNull($course->image_path);
        Storage::disk('public')->assertExists($course->image_path);
    }

    public function test_course_creation_creates_initial_chapter(): void
    {
        $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => 'チャプター付きコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ]);

        $course = Course::where('title', 'チャプター付きコース')->firstOrFail();
        $this->assertDatabaseHas('chapters', [
            'course_id' => $course->id,
            'title' => 'はじめに',
            'order' => 1,
        ]);
    }

    public function test_coach_can_create_course_with_new_tags(): void
    {
        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '新規タグコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'published',
            'new_tags' => 'PHP, Laravel',
        ]);

        $response->assertRedirect('/coach/courses');

        $course = Course::where('title', '新規タグコース')->firstOrFail();
        $this->assertNotNull($course->published_at);
        $this->assertDatabaseHas('tags', ['name' => 'PHP']);
        $this->assertDatabaseHas('tags', ['name' => 'Laravel']);
        $this->assertEqualsCanonicalizing(
            ['PHP', 'Laravel'],
            $course->tags->pluck('name')->toArray()
        );
    }

    public function test_coach_cannot_create_duplicate_title_course(): void
    {
        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => '重複コース',
        ]);

        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => '重複コース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertEquals(1, Course::where('title', '重複コース')->count());
    }

    public function test_coach_can_update_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->put("/coach/courses/{$course->id}", [
            'title' => '更新されたタイトル',
            'category_id' => $this->category->id,
            'description' => '更新された説明文です。',
            'difficulty' => 'intermediate',
            'status' => 'published',
        ]);

        $response->assertRedirect('/coach/courses');
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => '更新されたタイトル',
        ]);
    }

    public function test_coach_can_update_course_keeping_same_title(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => '同じタイトル',
        ]);

        $response = $this->actingAs($this->coach)->put("/coach/courses/{$course->id}", [
            'title' => '同じタイトル',
            'category_id' => $this->category->id,
            'description' => '更新された説明文です。',
            'difficulty' => 'intermediate',
            'status' => 'draft',
        ]);

        $response->assertRedirect('/coach/courses');
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => '同じタイトル',
            'description' => '更新された説明文です。',
        ]);
    }

    public function test_coach_cannot_update_course_to_duplicate_title(): void
    {
        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => '既存コース',
        ]);
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => '編集対象コース',
        ]);

        $response = $this->actingAs($this->coach)->put("/coach/courses/{$course->id}", [
            'title' => '既存コース',
            'category_id' => $this->category->id,
            'description' => '更新された説明文です。',
            'difficulty' => 'intermediate',
            'status' => 'draft',
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => '編集対象コース',
        ]);
    }

    public function test_coach_can_update_course_to_archived(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->coach)->put("/coach/courses/{$course->id}", [
            'title' => $course->title,
            'category_id' => $this->category->id,
            'description' => '更新された説明文です。',
            'difficulty' => 'intermediate',
            'status' => 'archived',
        ]);

        $response->assertRedirect('/coach/courses');
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'status' => 'archived',
        ]);
    }

    public function test_coach_can_delete_own_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->delete("/coach/courses/{$course->id}");

        $response->assertRedirect('/coach/courses');
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_coach_cannot_delete_other_coaches_course(): void
    {
        $otherCoach = User::factory()->create(['role' => 'coach']);
        $course = Course::factory()->create([
            'user_id' => $otherCoach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->delete("/coach/courses/{$course->id}");

        $response->assertStatus(403);
    }

    public function test_student_cannot_create_course(): void
    {
        $response = $this->actingAs($this->student)->get('/coach/courses/create');

        $response->assertStatus(403);
    }

    public function test_student_cannot_store_course(): void
    {
        $response = $this->actingAs($this->student)->post('/coach/courses', [
            'title' => 'テストコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('courses', ['title' => 'テストコース']);
    }

    public function test_course_list_can_be_filtered_by_category(): void
    {
        $otherCategory = Category::factory()->create();

        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
            'title' => 'カテゴリAのコース',
        ]);

        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $otherCategory->id,
            'status' => 'published',
            'title' => 'カテゴリBのコース',
        ]);

        $response = $this->actingAs($this->student)
            ->get("/courses?category={$this->category->id}");

        $response->assertStatus(200);
        $response->assertSee('カテゴリAのコース');
        $response->assertDontSee('カテゴリBのコース');
    }

    public function test_course_list_can_be_searched(): void
    {
        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
            'title' => 'Laravel入門',
        ]);

        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
            'title' => 'React基礎',
        ]);

        $response = $this->actingAs($this->student)
            ->get('/courses?search=Laravel');

        $response->assertStatus(200);
        $response->assertSee('Laravel入門');
        $response->assertDontSee('React基礎');
    }
}
