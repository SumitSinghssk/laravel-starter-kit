<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommonStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Blog;
use App\Models\Enquiry;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    private const CHART_DAYS = 14;

    public function __invoke(Request $request)
    {
        $user = $request->user();

        $canEnquiries = $user->can('admin.enquiries.view');
        $canBlogs = $user->can('admin.blogs.view');
        $canPages = $user->can('admin.pages.view');
        $canUsers = $user->can('admin.users.view');
        $canActivity = $user->can('admin.activity-logs.view');

        return view('admin.dashboard.index', [
            'greeting' => $this->greeting(),
            'stats' => $this->stats($canEnquiries, $canBlogs, $canPages, $canUsers),
            'chart' => $canEnquiries ? $this->enquiryChart() : null,
            'recentEnquiries' => $canEnquiries ? Enquiry::latest()->take(5)->get(['id', 'data', 'status', 'seen_at', 'source', 'created_at']) : collect(),
            'recentPosts' => $canBlogs ? Blog::with('categories:id,name')->latest('updated_at')->take(5)->get(['id', 'title', 'slug', 'status', 'published_at', 'featured_image', 'updated_at', 'created_at']) : collect(),
            'activity' => $canActivity ? ActivityLog::with('user:id,name,avatar')->where('action', '!=', 'viewed')->latest()->take(6)->get(['id', 'user_id', 'action', 'description', 'model_name', 'page_title', 'created_at']) : collect(),
            'health' => $canBlogs ? $this->contentHealth() : [],
        ]);
    }

    private function greeting(): string
    {
        $hour = now()->hour;

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    private function stats(bool $canEnquiries, bool $canBlogs, bool $canPages, bool $canUsers): array
    {
        $stats = [];

        if ($canEnquiries) {
            $thisWeek = Enquiry::where('created_at', '>=', now()->subDays(7))->count();
            $lastWeek = Enquiry::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();

            $stats[] = [
                'label' => 'Enquiries this week',
                'value' => $thisWeek,
                'delta' => $this->delta($thisWeek, $lastWeek),
                'hint' => 'vs previous 7 days',
                'icon' => 'inbox',
                'tone' => 'blue',
                'url' => route('admin.enquiries.index'),
            ];

            $unread = Enquiry::whereNull('seen_at')->count();
            $stats[] = [
                'label' => 'Unread enquiries',
                'value' => $unread,
                'delta' => null,
                'hint' => $unread ? 'Waiting for a reply' : 'All caught up',
                'icon' => 'mail-open',
                'tone' => $unread ? 'amber' : 'emerald',
                'url' => route('admin.enquiries.index', ['seen' => 'unseen']),
            ];
        }

        if ($canBlogs) {
            $published = Blog::where('status', CommonStatusEnum::ACTIVE->value)
                ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
                ->count();

            $stats[] = [
                'label' => 'Published posts',
                'value' => $published,
                'delta' => null,
                'hint' => Blog::count() - $published.' draft or scheduled',
                'icon' => 'newspaper',
                'tone' => 'violet',
                'url' => route('admin.blogs.index'),
            ];
        }

        if ($canPages) {
            $stats[] = [
                'label' => 'Live pages',
                'value' => Page::published()->count(),
                'delta' => null,
                'hint' => Page::count().' pages in total',
                'icon' => 'file-text',
                'tone' => 'emerald',
                'url' => route('admin.pages.index'),
            ];
        } elseif ($canUsers) {
            $stats[] = [
                'label' => 'Active users',
                'value' => User::where('status', CommonStatusEnum::ACTIVE->value)->count(),
                'delta' => null,
                'hint' => 'Admin accounts',
                'icon' => 'users',
                'tone' => 'emerald',
                'url' => route('admin.users.index'),
            ];
        }

        return $stats;
    }

    private function enquiryChart(): array
    {
        $start = now()->subDays(self::CHART_DAYS - 1)->startOfDay();

        $counts = Enquiry::where('created_at', '>=', $start)
            ->pluck('created_at')
            ->countBy(fn (Carbon $date) => $date->toDateString());

        $days = collect(range(0, self::CHART_DAYS - 1))->map(function (int $offset) use ($start, $counts) {
            $date = $start->copy()->addDays($offset);

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'short' => $date->format('j'),
                'weekday' => $date->format('D'),
                'count' => $counts->get($date->toDateString(), 0),
            ];
        });

        $total = $days->sum('count');
        $previous = Enquiry::whereBetween('created_at', [$start->copy()->subDays(self::CHART_DAYS), $start])->count();

        return [
            'days' => $days->all(),
            'total' => $total,
            'max' => max(1, $days->max('count')),
            'delta' => $this->delta($total, $previous),
            'busiest' => $total ? $days->sortByDesc('count')->first() : null,
        ];
    }

    private function contentHealth(): array
    {
        return [
            [
                'label' => 'Posts without a featured image',
                'count' => Blog::whereNull('featured_image')->count(),
                'url' => route('admin.blogs.index'),
            ],
            [
                'label' => 'Posts without an excerpt',
                'count' => Blog::where(fn ($q) => $q->whereNull('excerpt')->orWhere('excerpt', ''))->count(),
                'url' => route('admin.blogs.index'),
            ],
            [
                'label' => 'Scheduled posts',
                'count' => Blog::where('status', CommonStatusEnum::ACTIVE->value)->where('published_at', '>', now())->count(),
                'url' => route('admin.blogs.index'),
                'neutral' => true,
            ],
            [
                'label' => 'Inactive (draft) posts',
                'count' => Blog::where('status', CommonStatusEnum::INACTIVE->value)->count(),
                'url' => route('admin.blogs.index', ['status' => CommonStatusEnum::INACTIVE->value]),
                'neutral' => true,
            ],
            [
                'label' => 'Sitemap file generated',
                'count' => file_exists(public_path('sitemap.xml')) ? 0 : 1,
                'url' => route('admin.settings.index', ['tab' => 'sitemap']),
                'boolean' => true,
            ],
        ];
    }

    private function delta(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }
}
