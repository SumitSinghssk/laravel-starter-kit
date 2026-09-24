<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommonStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class BlogCategoryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('blog_categories', 'slug')->whereNull('deleted_at')],
            'parent_id' => ['nullable', Rule::exists('blog_categories', 'id')],
            'status' => ['required', 'string', new Enum(CommonStatusEnum::class)],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ];
    }
}
