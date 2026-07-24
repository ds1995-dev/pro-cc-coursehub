<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseProgressRateTest extends TestCase
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

    private function createCourse(): Course
    {
        return Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
        ]);
    }

    private function complete(Lesson $lesson): void
    {
        LessonProgress::factory()->create([
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
        ]);
    }

    public function test_progress_rate_is_100_when_all_published_lessons_completed_ignoring_unpublished(): void
    {
        $course = $this->createCourse();
        $chapter = Chapter::factory()->create(['course_id' => $course->id]);

        // 公開レッスン 3 件（受講生がアクセスできる）
        $publishedLessons = Lesson::factory()->count(3)->create(['chapter_id' => $chapter->id]);
        // 非公開レッスン 2 件（進捗計算の対象外であるべき）
        Lesson::factory()->count(2)->unpublished()->create(['chapter_id' => $chapter->id]);

        // 公開レッスンを全て完了
        foreach ($publishedLessons as $lesson) {
            $this->complete($lesson);
        }

        $this->assertSame(100, $course->getProgressRate($this->student->id));
    }

    public function test_progress_rate_reflects_partial_completion(): void
    {
        $course = $this->createCourse();
        $chapter = Chapter::factory()->create(['course_id' => $course->id]);

        // 公開レッスン 4 件のうち 1 件だけ完了 → 25%
        $publishedLessons = Lesson::factory()->count(4)->create(['chapter_id' => $chapter->id]);
        // 非公開レッスンを完了扱いにしても進捗率に影響しないこと
        $unpublished = Lesson::factory()->unpublished()->create(['chapter_id' => $chapter->id]);

        $this->complete($publishedLessons->first());
        $this->complete($unpublished);

        $this->assertSame(25, $course->getProgressRate($this->student->id));
    }

    public function test_progress_rate_is_0_when_course_has_no_lessons(): void
    {
        $course = $this->createCourse();
        // レッスン 0 件（章はあるがレッスンなし）
        Chapter::factory()->create(['course_id' => $course->id]);

        // 0 除算にならず 0% を返すこと
        $this->assertSame(0, $course->getProgressRate($this->student->id));
    }
}
