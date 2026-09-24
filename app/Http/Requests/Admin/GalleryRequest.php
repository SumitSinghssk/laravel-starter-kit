<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommonStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class GalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->filled('slug') ? $this->slug : $this->title)),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('galleries', 'slug')->ignore($this->route('gallery')?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_date' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['required', new Enum(CommonStatusEnum::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'Another album already uses this URL. Please change the slug.',
        ];
    }

    public function albumData(): array
    {
        return [
            ...$this->validated(),
            'is_featured' => $this->boolean('is_featured'),
            'sort_order' => (int) $this->validated('sort_order', 0),
        ];
    }
}
