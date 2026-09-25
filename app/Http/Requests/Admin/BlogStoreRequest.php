<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommonStatusEnum;
use App\Support\LocalTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class BlogStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('published_at')) {
            $this->merge(['published_at' => LocalTime::fromInput((string) $this->input('published_at'))?->format('Y-m-d H:i:s') ?? $this->input('published_at')]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('blogs', 'slug')->whereNull('deleted_at')],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'featured_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:blog_categories,id'],
            'status' => ['required', 'string', new Enum(CommonStatusEnum::class)],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
