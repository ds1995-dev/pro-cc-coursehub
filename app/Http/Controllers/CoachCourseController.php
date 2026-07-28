<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Models\Category;
use App\Models\Course;
use App\Models\Tag;
use App\Services\CourseService;
use Illuminate\Http\Request;

class CoachCourseController extends Controller
{
    public function __construct(
        private CourseService $courseService
    ) {}

    public function index()
    {
        $courses = Course::where('user_id', auth()->id())
            ->withCount('chapters', 'enrollments')
            ->latest()
            ->get();

        return view('coach.courses.index', compact('courses'));
    }

    public function dashboard()
    {
        $user = auth()->user();
        $courses = Course::where('user_id', $user->id)->get();
        $totalStudents = 0;
        foreach ($courses as $course) {
            $totalStudents += $course->enrollments()->where('status', 'active')->count();
        }

        return view('coach.dashboard', [
            'courseCount' => $courses->count(),
            'publishedCount' => $courses->where('status', 'published')->count(),
            'totalStudents' => $totalStudents,
        ]);
    }

    public function create()
    {
        $categories = Category::all();
        $tags = Tag::all();

        return view('coach.courses.create', compact('categories', 'tags'));
    }

    /**
     * コース新規作成処理
     *
     * バリデーションは StoreCourseRequest、永続化ロジック（スラッグ生成・
     * 画像アップロード・タグ同期・初期チャプター作成）は CourseService に委譲する。
     */
    public function store(StoreCourseRequest $request)
    {
        // 認可（try の外に置き、AuthorizationException を 403 として返す）
        $this->authorize('create', Course::class);

        try {
            $this->courseService->createForCoach(auth()->user(), $request->validated());
        } catch (\Exception $e) {
            \Log::error('コース作成エラー: '.$e->getMessage(), [
                'user_id' => auth()->id(),
                'request_data' => $request->except(['image']),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->withErrors([
                'error' => 'コースの作成中にエラーが発生しました。もう一度お試しください。',
            ]);
        }

        return redirect()->route('coach.courses.index')
            ->with('success', 'コースを作成しました。');
    }

    public function edit(Course $course)
    {
        $this->authorize('update', $course);

        $categories = Category::all();
        $tags = Tag::all();
        $course->load('tags');

        return view('coach.courses.edit', compact('course', 'categories', 'tags'));
    }

    public function update(Request $request, Course $course)
    {
        $this->authorize('update', $course);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['required', 'string'],
            'difficulty' => ['required', 'in:beginner,intermediate,advanced'],
            'status' => ['required', 'in:draft,published,archived'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
        ]);

        $course->update([
            'title' => $validated['title'],
            'category_id' => $validated['category_id'],
            'description' => $validated['description'],
            'difficulty' => $validated['difficulty'],
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'published' && ! $course->published_at ? now() : $course->published_at,
        ]);

        $course->tags()->sync($validated['tags'] ?? []);

        return redirect()->route('coach.courses.index')
            ->with('success', 'コースを更新しました。');
    }

    public function destroy(Course $course)
    {
        $this->authorize('delete', $course);

        $course->delete();

        return redirect()->route('coach.courses.index')
            ->with('success', 'コースを削除しました。');
    }
}
