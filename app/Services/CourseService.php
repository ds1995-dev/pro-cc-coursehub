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

    /**
     * コース画像を保存し、保存先パスを返す。
     * 画像が無い場合は null を返し、保存に失敗した場合は例外を投げる。
     */
    public function storeCourseImage(?UploadedFile $image): ?string
    {
        if ($image === null) {
            return null;
        }

        // ファイル名を生成（ユニークにするため timestamp を付与）
        $fileName = time().'_'.Str::random(10).'.'.$image->getClientOriginalExtension();

        // storage/app/public/courses ディレクトリに保存
        $imagePath = $image->storeAs('courses', $fileName, 'public');

        if (! $imagePath) {
            throw new \RuntimeException('画像のアップロードに失敗しました。');
        }

        return $imagePath;
    }
}
