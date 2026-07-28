<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Http\UploadedFile;
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

    /**
     * 既存タグ（tags）と新規タグ（new_tags, カンマ区切り）を解決し、
     * 同期対象のタグ ID 配列を返す。新規タグは firstOrCreate で作成する。
     *
     * @param  array  $validated  バリデーション済みの入力値
     * @return array<int> タグ ID の配列
     */
    public function resolveTagIds(array $validated): array
    {
        // 既存タグのIDリスト
        $tagIds = $validated['tags'] ?? [];

        // 新規タグが入力されている場合は作成して追加
        if (! empty($validated['new_tags'])) {
            $newTagNames = array_map('trim', explode(',', $validated['new_tags']));

            foreach ($newTagNames as $tagName) {
                if (empty($tagName)) {
                    continue;
                }

                // 既存のタグを検索、なければ新規作成
                $tag = Tag::firstOrCreate(
                    ['slug' => Str::slug($tagName)],
                    ['name' => $tagName]
                );

                // 重複しないようにIDを追加
                if (! in_array($tag->id, $tagIds)) {
                    $tagIds[] = $tag->id;
                }
            }
        }

        return $tagIds;
    }
}
