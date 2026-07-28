<?php

namespace App\Events;

use App\Models\Course;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * コース新規作成時に発火するイベント
 *
 * 初期チャプターの自動生成など、作成後の副作用を
 * Listener 側に切り出すために用いる。
 */
class CourseCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Course $course,
    ) {}
}
