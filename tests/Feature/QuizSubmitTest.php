<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizSubmitTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Course $course;
    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $coach = User::factory()->coach()->create();
        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->published()->create(['user_id' => $coach->id]);
        $chapter = Chapter::factory()->create(['course_id' => $this->course->id]);
        $lesson = Lesson::factory()->create(['chapter_id' => $chapter->id]);
        $this->quiz = Quiz::factory()->create(['lesson_id' => $lesson->id]);
    }

    /**
     * 正解の選択肢を持つ問題を作成し、[question, 正解 option_id] を返す。
     *
     * @return array{0: Question, 1: int}
     */
    private function createQuestion(): array
    {
        $question = Question::factory()->create(['quiz_id' => $this->quiz->id]);
        $correct = Option::factory()->correct()->create(['question_id' => $question->id]);
        Option::factory()->create(['question_id' => $question->id]);

        return [$question, $correct->id];
    }

    private function submit(array $answers)
    {
        return $this->actingAs($this->student)
            ->post(route('courses.quizzes.submit', [$this->course, $this->quiz]), [
                'answers' => $answers,
            ]);
    }

    private function latestScore(): int
    {
        return Submission::where('user_id', $this->student->id)
            ->where('quiz_id', $this->quiz->id)
            ->latest()
            ->firstOrFail()
            ->score;
    }

    public function test_score_is_100_when_all_answers_correct(): void
    {
        [$q1, $correct1] = $this->createQuestion();
        [$q2, $correct2] = $this->createQuestion();

        $this->submit([
            ['question_id' => $q1->id, 'option_id' => $correct1],
            ['question_id' => $q2->id, 'option_id' => $correct2],
        ])->assertRedirect(route('courses.quizzes.result', [$this->course, $this->quiz]));

        $this->assertSame(100, $this->latestScore());
    }

    public function test_unanswered_question_is_treated_as_incorrect_without_error(): void
    {
        [$q1, $correct1] = $this->createQuestion();
        [$q2] = $this->createQuestion();

        // q1 のみ正解を回答、q2 は未回答（answers に含めない）
        $this->submit([
            ['question_id' => $q1->id, 'option_id' => $correct1],
        ])->assertRedirect(route('courses.quizzes.result', [$this->course, $this->quiz]));

        // 2 問中 1 問正解 → 50
        $this->assertSame(50, $this->latestScore());
    }

    public function test_score_is_0_when_all_questions_unanswered(): void
    {
        $this->createQuestion();
        $this->createQuestion();

        $this->submit([])
            ->assertRedirect(route('courses.quizzes.result', [$this->course, $this->quiz]));

        $this->assertSame(0, $this->latestScore());
    }

    public function test_no_error_when_quiz_has_no_questions(): void
    {
        // 問題 0 件の Quiz（setUp の $this->quiz をそのまま使用）
        $this->submit([])
            ->assertRedirect(route('courses.quizzes.result', [$this->course, $this->quiz]));

        $this->assertSame(0, $this->latestScore());
    }
}
