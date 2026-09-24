<?php

namespace App\View\Components;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Services\Health\SystemHealth;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Admin extends Component
{
    public array $links;

    public function __construct()
    {
        $item = fn (string $title, string $route, string $icon, string $active, string $permission) => [
            'title' => $title,
            'route' => route($route),
            'icon' => $icon,
            'active' => $active,
            'permission' => $permission,
        ];

        $this->links = [
            [
                'key' => 'overview',
                'section' => 'Overview',
                'icon' => 'dashboard',
                'collapsible' => false,
                'items' => [
                    $item('Dashboard', 'admin.dashboard', 'dashboard', 'admin.dashboard', 'dashboard.view'),
                    [
                        ...$item('Enquiries', 'admin.enquiries.index', 'inbox', 'admin.enquiries.*', 'admin.enquiries.view'),
                        'badge' => auth()->user()?->can('admin.enquiries.view') ? Enquiry::where('status', EnquiryStatus::NEW->value)->count() : 0,
                        'badgeLabel' => 'new enquiries',
                    ],
                ],
            ],
            [
                'key' => 'content',
                'section' => 'Content',
                'icon' => 'newspaper',
                'items' => [
                    $item('Blogs', 'admin.blogs.index', 'newspaper', 'admin.blogs.*', 'admin.blogs.view'),
                    $item('Categories', 'admin.blog-categories.index', 'tag', 'admin.blog-categories.*', 'admin.blog-categories.view'),
                    $item('Pages', 'admin.pages.index', 'file-text', 'admin.pages.*', 'admin.pages.view'),
                    $item('Testimonials', 'admin.testimonials.index', 'message', 'admin.testimonials.*', 'admin.testimonials.view'),
                ],
            ],
            [
                'key' => 'media',
                'section' => 'Media',
                'icon' => 'image',
                'items' => [
                    $item('Media Library', 'admin.media-library.index', 'folder', 'admin.media-library.*', 'admin.media.view'),
                    $item('Gallery', 'admin.galleries.index', 'image', 'admin.galleries.*', 'admin.galleries.view'),
                ],
            ],
            [
                'key' => 'seo',
                'section' => 'SEO & traffic',
                'icon' => 'globe',
                'items' => [
                    $item('SEO', 'admin.seo.index', 'globe', 'admin.seo.*', 'admin.seo.view'),
                    $item('Redirects', 'admin.redirects.index', 'redirect', 'admin.redirects.*', 'admin.redirects.view'),
                    $item('404 Errors', 'admin.not-found.index', 'alert-triangle', 'admin.not-found.*', 'admin.not-found.view'),
                ],
            ],
            [
                'key' => 'people',
                'section' => 'Users & access',
                'icon' => 'users',
                'items' => [
                    $item('Users', 'admin.users.index', 'users', 'admin.users.*', 'admin.users.view'),
                    $item('Roles & Permissions', 'admin.roles.index', 'shield-check', 'admin.roles.*', 'admin.roles.view'),
                ],
            ],
            [
                'key' => 'website',
                'section' => 'Website',
                'icon' => 'palette',
                'items' => [
                    $item('Appearance', 'admin.appearance.index', 'palette', 'admin.appearance.*', 'admin.appearance.view'),
                    $item('Menus', 'admin.menus.index', 'menu', 'admin.menus.*', 'admin.menus.view'),
                    $item('Settings', 'admin.settings.index', 'settings', 'admin.settings.*', 'admin.settings.view'),
                ],
            ],
            [
                'key' => 'tools',
                'section' => 'Tools',
                'icon' => 'sliders',
                'items' => [
                    $item('Activity Logs', 'admin.activity-logs.index', 'activity', 'admin.activity-logs.*', 'admin.activity-logs.view'),
                    $item('Backups', 'admin.backups.index', 'save', 'admin.backups.*', 'admin.backups.view'),
                    $item('Trash', 'admin.trash.index', 'trash', 'admin.trash.*', 'admin.trash.view'),
                    [
                        ...$item('System health', 'admin.system-health.index', 'heart-pulse', 'admin.system-health.*', 'admin.system-health.view'),
                        'badge' => SystemHealth::summary()['errors'] ?? 0,
                        'badgeTone' => 'danger',
                        'badgeLabel' => 'problems found',
                    ],
                ],
            ],
        ];
    }

    protected function quickCreate(): array
    {
        $user = auth()->user();

        return collect([
            ['title' => 'Blog post', 'icon' => 'newspaper', 'route' => 'admin.blogs.create', 'permission' => 'admin.blogs.create'],
            ['title' => 'Page', 'icon' => 'file-text', 'route' => 'admin.pages.create', 'permission' => 'admin.pages.create'],
            ['title' => 'Gallery album', 'icon' => 'image', 'route' => 'admin.galleries.create', 'permission' => 'admin.galleries.create'],
            ['title' => 'Testimonial', 'icon' => 'message', 'route' => 'admin.testimonials.create', 'permission' => 'admin.testimonials.create'],
            ['title' => 'Blog category', 'icon' => 'tag', 'route' => 'admin.blog-categories.create', 'permission' => 'admin.blog-categories.create'],
            ['title' => 'SEO record', 'icon' => 'globe', 'route' => 'admin.seo.create', 'permission' => 'admin.seo.create'],
            ['title' => 'Redirect', 'icon' => 'redirect', 'route' => 'admin.redirects.create', 'permission' => 'admin.redirects.create'],
            ['title' => 'User', 'icon' => 'users', 'route' => 'admin.users.create', 'permission' => 'admin.users.create'],
        ])
            ->filter(fn ($item) => $user?->can($item['permission']))
            ->map(fn ($item) => [...$item, 'url' => route($item['route'])])
            ->values()
            ->all();
    }

    public function render(): View|Closure|string
    {
        return view('layouts.admin', [
            'links' => $this->links,
            'quickCreate' => $this->quickCreate(),
        ]);
    }
}
