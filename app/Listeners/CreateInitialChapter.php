<?php

namespace App\Listeners;

use App\Events\CourseCreated;

/**
 * コース作成時に最初のチャプター「はじめに」を自動生成するリスナー
 *
 * これにより、コーチが作成直後からレッスンを追加できる。
 */
class CreateInitialChapter
{
    public function handle(CourseCreated $event): void
    {
        $event->course->chapters()->create([
            'title' => 'はじめに',
            'order' => 1,
        ]);
    }
}
