<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

class SubmissionPolicy
{
    /**
     * 小テストの受験（再受験含む）が可能か判定する。
     * 合格済み（score >= passing_score）の Submission がある場合は再受験不可。
     */
    public function submit(User $user, Quiz $quiz): bool
    {
        $hasPassed = $quiz->submissions()
            ->where('user_id', $user->id)
            ->where('score', '>=', $quiz->passing_score)
            ->exists();

        return ! $hasPassed;
    }
}
