---
name: review
description: ブランチの全変更をコードレビューする
---

現在のブランチの変更をレビューしてください。レビュー対象・確認観点・報告形式は以下に従います。

## 1. レビュー対象を収集する

main ブランチとの差分（コミット済み＋未コミットの両方）を対象にします。

```bash
# 変更ファイル一覧
git diff main...HEAD --stat && git status --short

# 変更内容（コミット済み）
git diff main...HEAD

# 未コミットの変更があれば併せて確認
git diff
```

変更されたファイルはすべて Read で中身も確認すること（diff だけで判断しない）。

## 2. 確認観点

### 規約違反（`.claude/rules/coding.md` 準拠）
- **バリデーション**: Controller で `$request->validate()` の直書きが新規追加されていないか（新規は Form Request に統一）
- **Controller / Service**: Fat Controller になっていないか。複数モデル横断・複雑ロジックが Controller に直書きされていないか（Service へ切り出す。ただし単一モデルの単純 CRUD は Controller で可）
- **アクセス制御**: リソース操作に `$this->authorize()`（Policy）が呼ばれているか。Policy は `AuthServiceProvider` に登録されているか
- **イベント**: 重要なドメインイベントが Event/Listener で実装されているか
- **エラーハンドリング**: Blade 画面はリダイレクト＋フラッシュメッセージでエラーを返しているか
- **命名**: 変数・メソッドは camelCase、テーブル・カラムは snake_case、Controller は単数形 + Controller
- **モデル**: リレーションが明示的に定義されているか
- **N+1**: 一覧系で Eager Loading（`with` / `withCount`）が漏れていないか

### テスト不足
- 新機能・変更に対応する Feature テストが追加/更新されているか（テストメソッド名は `test_` プレフィックス）
- 変更した分岐・エラーパスがテストでカバーされているか
- テストを実行して緑か確認する: `./vendor/bin/sail test`

### セキュリティ
- 認可漏れ（他ユーザーのリソースを操作できないか）
- マスアサインメント（`$fillable`/`$guarded` の範囲、`$request->all()` の無防備な利用）
- SQL インジェクション（生クエリへの未バインド値の埋め込み）
- XSS（Blade で `{!! !!}` による未エスケープ出力）
- 機密情報のハードコード（APIキー・パスワード・トークン）
- ファイルアップロードの検証漏れ（MIME/サイズ）

### その他（不要なデバッグコード等）
- `dd()` / `dump()` / `var_dump()` / `ray()` / `console.log` の残存
- コメントアウトされた不要コード、`\Log::debug` などの一時ログ
- `.env` や設定への意図しない変更

デバッグコードは grep で機械的にも確認する:

```bash
git diff main...HEAD | grep -nE '^\+.*(\bdd\(|\bdump\(|var_dump\(|\bray\(|console\.log)'
```

## 3. 報告形式

以下のカテゴリ別に、指摘を重要度が高い順で報告してください。各指摘には `ファイル:行` と理由・修正案を添えること。

- **規約違反**
- **テスト不足**
- **セキュリティ**
- **その他**

各カテゴリで指摘が無ければ「問題なし」と明記する。最後に全体所感（マージ可否の目安）を1〜2行でまとめる。
