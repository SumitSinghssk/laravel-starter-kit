<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SeoStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('seos', 'slug'),
            ],

            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',

            'og_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'schema' => 'nullable|string',
            'faqs' => 'nullable|string',

            'header_scripts' => 'nullable|string',
            'footer_scripts' => 'nullable|string',
            'custom_css' => 'nullable|string',
            'index' => 'required|boolean',
        ];
    }
}
