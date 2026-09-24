<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommonStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Enquiry;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Seo;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BulkActionController extends Controller
{
    public const MAX = 500;

    private const PROTECTED_ROLES = ['super admin', 'super-admin'];

    public static function types(): array
    {
        $status = fn (string $module) => [
            'activate' => "admin.{$module}.toogle-status",
            'deactivate' => "admin.{$module}.toogle-status",
            'delete' => "admin.{$module}.delete",
        ];

        return [
            'blogs' => [Blog::class, 'post', 'posts', $status('blogs')],
            'blog-categories' => [BlogCategory::class, 'category', 'categories', $status('blog-categories')],
            'pages' => [Page::class, 'page', 'pages', $status('pages')],
            'testimonials' => [Testimonial::class, 'testimonial', 'testimonials', $status('testimonials')],
            'redirects' => [Redirect::class, 'redirect', 'redirects', $status('redirects')],
            'seo' => [Seo::class, 'SEO record', 'SEO records', ['delete' => 'admin.seo.delete']],
            'users' => [User::class, 'user', 'users', $status('users')],
            'enquiries' => [Enquiry::class, 'enquiry', 'enquiries', [
                'mark-new' => 'admin.enquiries.edit',
                'mark-seen' => 'admin.enquiries.edit',
                'mark-pending' => 'admin.enquiries.edit',
                'mark-closed' => 'admin.enquiries.edit',
                'delete' => 'admin.enquiries.delete',
            ]],
        ];
    }

    public static function allowed(string $type, $user): array
    {
        return array_keys(array_filter(self::types()[$type][3] ?? [], fn ($permission) => $user?->can($permission)));
    }

    public function __invoke(Request $request, string $type)
    {
        abort_unless(isset(self::types()[$type]), 404);
        [$modelClass, $singular, $plural, $actions] = self::types()[$type];

        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys($actions))],
            'ids' => ['required', 'array', 'max:'.self::MAX],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Select at least one row first.']);

        Gate::authorize($actions[$data['action']]);

        $done = 0;
        $skipped = [];

        foreach ($modelClass::whereKey(array_unique($data['ids']))->get() as $model) {
            if ($reason = $this->refuse($type, $model, $request->user())) {
                $skipped[] = $reason;

                continue;
            }

            if ($this->apply($data['action'], $model, $request->user())) {
                $done++;
            }
        }

        $trash = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
        $noun = $done === 1 ? $singular : $plural;
        $message = match ($data['action']) {
            'activate' => "{$done} {$noun} activated.",
            'deactivate' => "{$done} {$noun} deactivated.",
            'delete' => $trash ? "{$done} {$noun} moved to the Trash." : "{$done} {$noun} deleted.",
            default => "{$done} {$noun} marked as ".Str::after($data['action'], 'mark-').'.',
        };

        if ($skipped) {
            $message .= ' Skipped: '.implode(' ', array_unique($skipped));
        }

        return back()->with($done ? 'success' : 'error', $message);
    }

    private function refuse(string $type, Model $model, User $actor): ?string
    {
        if ($type !== 'users') {
            return null;
        }

        if ($model->is($actor)) {
            return 'your own account.';
        }

        if ($model->hasRole(self::PROTECTED_ROLES) && ! $actor->hasRole(self::PROTECTED_ROLES)) {
            return 'super admin accounts.';
        }

        return null;
    }

    private function apply(string $action, Model $model, User $actor): bool
    {
        switch ($action) {
            case 'activate':
            case 'deactivate':
                $status = $action === 'activate' ? CommonStatusEnum::ACTIVE : CommonStatusEnum::INACTIVE;
                if ($model->status === $status) {
                    return false;
                }
                $model->status = $status;

                return $model->save();

            case 'delete':
                if ($model instanceof Seo && $model->og_image) {
                    Storage::disk('public')->delete($model->og_image);
                }

                return (bool) $model->delete();

            default:
                $status = Str::after($action, 'mark-');
                $model->status = $status;
                if ($status === 'new') {
                    $model->seen_at = null;
                    $model->seen_by = null;
                } elseif (! $model->seen_at) {
                    $model->seen_at = now();
                    $model->seen_by = $actor->id;
                }

                return $model->isDirty() ? $model->save() : false;
        }
    }
}
