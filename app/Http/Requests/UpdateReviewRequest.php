<?php

namespace App\Http\Requests;

/**
 * レビュー更新のバリデーション。
 * 受け付けるフィールド・ルールは投稿時と同一のため StoreReviewRequest を継承する。
 */
class UpdateReviewRequest extends StoreReviewRequest {}
