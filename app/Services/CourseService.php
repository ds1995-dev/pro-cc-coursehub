<?php

namespace App\Services;

use App\Events\CourseCreated;
use App\Models\Course;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /**
     * コースを作成する一連の処理をまとめて実行する。
     *
     * 画像保存 → Course 作成 → タグ同期 → 初期チャプター作成までを行う。
     * DB 書き込みは単一トランザクションで原子的に処理し、ロールバック時は
     * アップロード済み画像（filesystem は非トランザクション）を削除する。
     */
    public function createForCoach(User $coach, array $validated): Course
    {
        // 画像はトランザクション外で先に保存し、DB ロールバック時に削除する
        $imagePath = $this->storeCourseImage($validated['image'] ?? null);

        try {
            $course = DB::transaction(function () use ($coach, $validated, $imagePath) {
                $course = Course::create([
                    'user_id' => $coach->id,
                    'category_id' => $validated['category_id'],
                    'title' => $validated['title'],
                    'slug' => $this->generateUniqueSlug($validated['title']),
                    'description' => $validated['description'],
                    'difficulty' => $validated['difficulty'],
                    'image_path' => $imagePath,
                    'status' => $validated['status'],
                    // 公開ステータスの場合のみ公開日時を設定（archived は新規作成不可）
                    'published_at' => $validated['status'] === 'published' ? now() : null,
                ]);

                // タグの同期（既存タグ + 新規タグ）
                $tagIds = $this->resolveTagIds($validated);
                if (! empty($tagIds)) {
                    $course->tags()->sync($tagIds);
                }

                return $course;
            });
        } catch (\Throwable $e) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $e;
        }

        // 作成後の副作用（初期チャプター生成など）はイベントで処理する
        event(new CourseCreated($course));

        return $course;
    }
}
