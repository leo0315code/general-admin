<?php

namespace App\Http\Requests;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 创建文章请求校验
 */
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('post.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'status' => ['required', Rule::in([Post::STATUS_DRAFT, Post::STATUS_PUBLISHED])],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => '请输入文章标题。',
            'content.required' => '请输入文章内容。',
            'status.in' => '文章状态不合法。',
            'published_at.date' => '发布时间格式不正确。',
        ];
    }
}
