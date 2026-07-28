<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Str;

/**
 * コース作成に関するビジネスロジックを集約するサービスクラス
 *
 * CoachCourseController@store から切り出して責務を分離した。
 * スラッグ生成・画像アップロード・タグ解決・永続化を担当する。
 */
class CourseService
{
    /**
     * タイトルから一意なスラッグを生成する。
     * 日本語タイトルなどで空になる場合はタイムスタンプで代替し、
     * 既存スラッグと衝突する場合は連番を付与する。
     */
    public function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);

        // 空のスラッグ対策（日本語タイトルの場合）
        if (empty($slug)) {
            $slug = 'course-'.time();
        }

        // スラッグの重複チェック
        $originalSlug = $slug;
        $slugCount = 1;
        while (Course::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$slugCount;
            $slugCount++;
        }

        return $slug;
    }
}
