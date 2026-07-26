<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * レビュー投稿の可否。
     * 受講完了（status=completed）した student で、かつ未レビューの場合のみ許可する。
     */
    public function create(User $user, Course $course): bool
    {
        if (! $user->isStudent()) {
            return false;
        }

        $completed = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->exists();

        if (! $completed) {
            return false;
        }

        $alreadyReviewed = Review::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->exists();

        return ! $alreadyReviewed;
    }

    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }
}
