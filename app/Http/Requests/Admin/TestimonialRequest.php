<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommonStatusEnum;
use App\Models\Testimonial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class TestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'quote' => ['required', 'string', 'min:10', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'rating' => ['nullable', 'integer', Rule::in(array_keys(Testimonial::RATINGS))],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['required', new Enum(CommonStatusEnum::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'client name',
            'quote' => 'testimonial',
        ];
    }
}
