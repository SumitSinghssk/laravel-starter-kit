<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommonStatusEnum;
use App\Models\Redirect;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class RedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_path' => filled($this->source_path) ? Redirect::normalizePath($this->source_path) : null,
            'target_url' => filled($this->target_url) ? $this->normalizeTarget($this->target_url) : null,
        ]);
    }

    public function rules(): array
    {
        $redirect = $this->route('redirect');

        return [
            'source_path' => [
                'required',
                'string',
                'max:500',
                'not_in:/',
                function (string $attribute, string $value, Closure $fail) {
                    if ($value === '/admin' || str_starts_with($value, '/admin/')) {
                        $fail('Admin panel URLs cannot be redirected.');
                    }
                },
                Rule::unique('redirects', 'source_path')->ignore($redirect?->id),
            ],
            'target_url' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, string $value, Closure $fail) {
                    $isPath = str_starts_with($value, '/') && ! str_starts_with($value, '//');
                    $isUrl = preg_match('#^https?://[^\s/]+#i', $value) && filter_var($value, FILTER_VALIDATE_URL);

                    if (! $isPath && ! $isUrl) {
                        $fail('Enter a path on this site (e.g. /new-page) or a full URL starting with http:// or https://.');
                    }
                },
            ],
            'status_code' => ['required', 'integer', Rule::in(array_keys(Redirect::STATUS_CODES))],
            'status' => ['required', new Enum(CommonStatusEnum::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'source_path.not_in' => 'The home page cannot be redirected.',
            'source_path.unique' => 'A redirect for this URL already exists.',
        ];
    }

    public function attributes(): array
    {
        return [
            'source_path' => 'old URL',
            'target_url' => 'new URL',
            'status_code' => 'redirect type',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($validator->errors()->hasAny(['source_path', 'target_url'])) {
                    return;
                }

                $loop = Redirect::loopChain($this->input('source_path'), $this->input('target_url'), $this->route('redirect')?->id);

                if ($loop) {
                    $validator->errors()->add('target_url', 'This would create a redirect loop: '.implode(' → ', $loop).'.');
                }
            },
        ];
    }

    private function normalizeTarget(string $target): string
    {
        $target = trim($target);

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $target) || str_starts_with($target, '//')) {
            return $target;
        }

        return '/'.ltrim($target, '/');
    }
}
