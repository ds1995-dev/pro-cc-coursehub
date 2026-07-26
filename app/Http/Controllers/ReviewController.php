<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Course;
use App\Models\Review;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Course $course)
    {
        $this->authorize('create', [Review::class, $course]);

        $course->reviews()->create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('courses.show', $course)
            ->with('success', 'レビューを投稿しました。');
    }

    public function update(UpdateReviewRequest $request, Course $course, Review $review)
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return redirect()->route('courses.show', $course)
            ->with('success', 'レビューを更新しました。');
    }

    public function destroy(Course $course, Review $review)
    {
        $this->authorize('delete', $review);

        $review->delete();

        return redirect()->route('courses.show', $course)
            ->with('success', 'レビューを削除しました。');
    }
}
