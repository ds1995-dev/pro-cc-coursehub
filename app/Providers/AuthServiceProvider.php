<?php

namespace App\Providers;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Review;
use App\Policies\ChapterPolicy;
use App\Policies\CoursePolicy;
use App\Policies\LessonPolicy;
use App\Policies\QuizPolicy;
use App\Policies\ReviewPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Course::class => CoursePolicy::class,
        Chapter::class => ChapterPolicy::class,
        Lesson::class => LessonPolicy::class,
        Quiz::class => QuizPolicy::class,
        Review::class => ReviewPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
