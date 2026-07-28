---
description: コーディング規約
globs: "app/**/*.php"
---

新規・変更するコードは以下に従う。詳細は README.md の「コーディング規約」「設計方針」を参照。

## バリデーション
- Controller ではバリデーションに Form Request を使用する（`app/Http/Requests/` に配置）
- 既存にインライン `$request->validate()` が残るが、新規コードでは Form Request に統一する

## Controller / Service
- Controller は薄く保つ。複数モデルにまたがる処理・複雑なロジックは `app/Services/` の Service クラスに切り出す（例: EnrollmentService）
- 既存に Fat Controller があるが、新規コードではロジックを Controller に直書きしない

## アクセス制御
- リソースのアクセス制御は Policy で実装し、Controller で `$this->authorize()` を呼ぶ（使用する Policy は `AuthServiceProvider` に登録）

## イベント
- 重要なドメインイベントは Event/Listener パターンで実装する

## エラーハンドリング
- Blade 画面はリダイレクト＋フラッシュメッセージでエラーを返す

## 命名
- 変数名・メソッド名は camelCase、テーブル名・カラム名は snake_case
- Controller 名は単数形 + Controller（例: CourseController）

## モデル
- リレーションは明示的に定義する

## テスト
- 新機能には Feature テストを書く。テストメソッド名は `test_` プレフィックス
