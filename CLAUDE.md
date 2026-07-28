# CourseHub

オンライン学習プラットフォーム。コーチがコースを作成し、受講生が学習・進捗管理できる。

## 技術スタック

- PHP 8.2
- Laravel 10
- Laravel Sail（Docker 開発環境）
- MySQL 8.0
- Blade + Tailwind CSS

## 開発環境

コマンドは Sail 経由（`./vendor/bin/sail`）で実行する。

起動:

```bash
./vendor/bin/sail up -d
```

初回のみ、依存インストール（vendor が無いと sail 自体が動かないためコンテナ内で実行）:

```bash
docker compose exec laravel.test composer install
```

マイグレーション・シーディング:

```bash
./vendor/bin/sail artisan migrate --seed
```

## コース構造

コンテンツは以下の階層で構成される: **Course > Chapter > Lesson > Quiz**

- **Course**（コース）: coach が作成。category を1つ持ち、tags（多対多）を持つ。student は enrollment で受講する
- **Chapter**（章）: Course に属する
- **Lesson**（レッスン）: Chapter に属する。受講進捗は lesson_progress で管理
- **Quiz**（小テスト）: Lesson に紐づく。Question > Option を持ち、回答結果は submission に記録される

## ユーザーロール

- admin: 管理者
- coach: コーチ（コース作成）
- student: 受講生

## テスト

```bash
./vendor/bin/sail test
```

## コーディング規約・設計方針

コーディング規約と設計方針は README.md の該当セクションに記載。要点（Claude Code 向け）は `.claude/rules/coding.md` を参照する。
