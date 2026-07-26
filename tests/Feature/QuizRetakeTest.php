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

class QuizRetakeTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Course $course;
    private Quiz $quiz;
    private Question $question;
    private int $correctOptionId;

    protected function setUp(): void
    {
        parent::setUp();

        // 結果画面の描画テストで Vite manifest に依存しないようにする
        $this->withoutVite();

        $coach = User::factory()->coach()->create();
        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->published()->create(['user_id' => $coach->id]);
        $chapter = Chapter::factory()->create(['course_id' => $this->course->id]);
        $lesson = Lesson::factory()->create(['chapter_id' => $chapter->id]);
        // passing_score 70 の Quiz に 1 問（正解で 100 点、不正解で 0 点）
        $this->quiz = Quiz::factory()->create(['lesson_id' => $lesson->id, 'passing_score' => 70]);

        $this->question = Question::factory()->create(['quiz_id' => $this->quiz->id]);
        $this->correctOptionId = Option::factory()->correct()->create(['question_id' => $this->question->id])->id;
        Option::factory()->create(['question_id' => $this->question->id]);
    }

    /**
     * 小テストを送信する。$optionId が null の場合は未回答（不正解）として送信する。
     */
    private function submit(?int $optionId)
    {
        $answer = ['question_id' => $this->question->id];
        if ($optionId !== null) {
            $answer['option_id'] = $optionId;
        }

        return $this->actingAs($this->student)
            ->post(route('courses.quizzes.submit', [$this->course, $this->quiz]), [
                'answers' => [$answer],
            ]);
    }

    private function getShow()
    {
        return $this->actingAs($this->student)
            ->get(route('courses.quizzes.show', [$this->course, $this->quiz]));
    }

    private function getResult()
    {
        return $this->actingAs($this->student)
            ->get(route('courses.quizzes.result', [$this->course, $this->quiz]));
    }

    private function submissionCount(): int
    {
        return Submission::where('user_id', $this->student->id)
            ->where('quiz_id', $this->quiz->id)
            ->count();
    }

    private function resultRoute(): string
    {
        return route('courses.quizzes.result', [$this->course, $this->quiz]);
    }

    /** 未受験の student が小テストを受験できる（Submission が作成される） */
    public function test_new_student_can_take_quiz(): void
    {
        $this->submit($this->correctOptionId)
            ->assertRedirect($this->resultRoute());

        $this->assertSame(1, $this->submissionCount());
    }

    /** 不合格の student は再受験でき、新しい Submission が作成される */
    public function test_failed_student_can_retake(): void
    {
        $this->submit(null)                        // 1 回目: 0 点（不合格）
            ->assertRedirect($this->resultRoute());
        $this->submit($this->correctOptionId)      // 2 回目: 再受験して 100 点
            ->assertRedirect($this->resultRoute());

        $this->assertSame(2, $this->submissionCount());
    }

    /** 合格済みの student が再受験しようとする（受験画面）と結果画面へリダイレクトされる */
    public function test_passed_student_is_redirected_from_quiz_show(): void
    {
        $this->submit($this->correctOptionId); // 合格

        $this->getShow()
            ->assertRedirect($this->resultRoute())
            ->assertSessionHas('error');
    }

    /** 合格済みの student が submit へ直接 POST すると Policy で拒否され 403 になる */
    public function test_passed_student_gets_forbidden_on_direct_submit(): void
    {
        $this->submit($this->correctOptionId); // 合格

        $this->submit(null)->assertForbidden(); // 403

        // 追加の Submission は作成されない
        $this->assertSame(1, $this->submissionCount());
    }

    /** 結果画面に全 Submission が新しい順に表示される */
    public function test_result_lists_all_submissions_newest_first(): void
    {
        // 作成日時に差をつけて順序を確定させる
        $this->travelTo(now()->subMinute());
        $this->submit(null);                   // 古い: 0 点
        $this->travelBack();
        $this->submit($this->correctOptionId); // 新しい: 100 点（合格）

        $this->getResult()
            ->assertOk()
            ->assertSee('受験履歴')
            ->assertSeeInOrder(['受験履歴', '100%', '0%']); // 新しい順（100% → 0%）

        $this->assertSame(2, $this->submissionCount());
    }

    /** 合格済みの結果画面には再受験ボタンが表示されない */
    public function test_retake_button_is_hidden_when_passed(): void
    {
        $this->submit($this->correctOptionId); // 合格

        $this->getResult()
            ->assertOk()
            ->assertDontSee('再受験する');
    }

    /** 不合格の結果画面には再受験ボタンが表示される */
    public function test_retake_button_is_visible_when_failed(): void
    {
        $this->submit(null); // 不合格

        $this->getResult()
            ->assertOk()
            ->assertSee('再受験する');
    }
}
