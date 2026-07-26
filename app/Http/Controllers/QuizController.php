<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Option;
use App\Models\Quiz;
use App\Models\Submission;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function show(Course $course, Quiz $quiz)
    {
        $this->authorize('view', $course);

        // 合格済みの場合は再受験不可。結果画面へ誘導する
        if (auth()->user()->cannot('submit', [Submission::class, $quiz])) {
            return redirect()
                ->route('courses.quizzes.result', [$course, $quiz])
                ->with('error', 'この小テストは既に合格済みのため、再受験できません。');
        }

        $quiz->load('questions.options');

        return view('quizzes.show', compact('course', 'quiz'));
    }

    public function submit(Request $request, Course $course, Quiz $quiz)
    {
        // 合格済みの場合は再受験不可（SubmissionPolicy::submit）→ 403
        $this->authorize('submit', [Submission::class, $quiz]);

        $answers = $request->input('answers', []);

        $correctCount = 0;
        foreach ($quiz->questions as $question) {
            $userAnswer = collect($answers)->firstWhere('question_id', $question->id);
            if (! $userAnswer || ! isset($userAnswer['option_id'])) {
                continue;
            }
            $selectedOption = Option::find($userAnswer['option_id']);
            if ($selectedOption && $selectedOption->is_correct) {
                $correctCount++;
            }
        }

        $totalQuestions = $quiz->questions->count();
        $score = $totalQuestions > 0 ? (int) round($correctCount / $totalQuestions * 100) : 0;

        $submission = Submission::create([
            'user_id' => auth()->id(),
            'quiz_id' => $quiz->id,
            'score' => $score,
            'answers' => $answers,
            'submitted_at' => now(),
        ]);

        return redirect()->route('courses.quizzes.result', [$course, $quiz]);
    }

    public function result(Course $course, Quiz $quiz)
    {
        $this->authorize('view', $course);

        $quiz->load('questions.options');

        // 受験履歴（新しい順）。$submission は最新の受験結果
        $submissions = Submission::where('user_id', auth()->id())
            ->where('quiz_id', $quiz->id)
            ->latest()
            ->get();

        abort_if($submissions->isEmpty(), 404);
        $submission = $submissions->first();

        // 合格済みなら再受験ボタンを表示しない
        $hasPassed = $submissions->contains(fn ($s) => $s->score >= $quiz->passing_score);

        return view('quizzes.result', compact('course', 'quiz', 'submission', 'submissions', 'hasPassed'));
    }
}
