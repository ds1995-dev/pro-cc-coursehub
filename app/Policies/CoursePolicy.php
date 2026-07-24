<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Course $course): bool
    {
        // admin・所有コーチは status を問わず閲覧可
        if ($user->role === 'admin' || $course->user_id === $user->id) {
            return true;
        }

        // それ以外（受講生など）は公開済みのみ閲覧可
        return $course->status === 'published';
    }

    public function create(User $user): bool
    {
        return $user->role === 'coach';
    }

    public function update(User $user, Course $course): bool
    {
        return $user->id === $course->user_id;
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->id === $course->user_id;
    }
}
