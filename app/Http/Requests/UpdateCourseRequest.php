<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:255',
                // 同じコーチ内でのタイトル重複を禁止（編集中のコース自身は除外）
                Rule::unique('courses', 'title')
                    ->where(fn ($query) => $query->where('user_id', auth()->id()))
                    ->ignore($this->route('course')),
            ],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['required', 'string'],
            'difficulty' => ['required', 'in:beginner,intermediate,advanced'],
            'status' => ['required', 'in:draft,published,archived'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'コースタイトルは必須です。',
            'title.unique' => '同じタイトルのコースが既に存在します。',
            'category_id.required' => 'カテゴリを選択してください。',
            'description.required' => 'コース説明は必須です。',
            'difficulty.required' => '難易度を選択してください。',
            'status.required' => 'ステータスを選択してください。',
        ];
    }
}
