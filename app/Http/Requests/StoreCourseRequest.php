<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['required', 'string'],
            'difficulty' => ['required', 'in:beginner,intermediate,advanced'],
            'status' => ['required', 'in:draft,published'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
            'new_tags' => ['nullable', 'string'], // カンマ区切りで新規タグを指定可能
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'コースタイトルは必須です。',
            'category_id.required' => 'カテゴリを選択してください。',
            'description.required' => 'コース説明は必須です。',
            'difficulty.required' => '難易度を選択してください。',
            'status.required' => 'ステータスを選択してください。',
            'image.image' => '画像ファイルを指定してください。',
            'image.max' => '画像サイズは2MB以下にしてください。',
        ];
    }
}
