<?php

namespace App\Support;

use App\Models\User;

class AdminSearchIndex
{
    public static function shortcuts(User $user): array
    {
        $settings = fn (string $tab, ?string $section = null) => route('admin.settings.index', ['tab' => $tab]).($section ? '#settings-'.$section : '');

        $entries = [
            ['Business profile', 'Settings', 'building', $settings('basic', 'general'), 'admin.settings.basic-details.view', 'site name company business details general'],
            ['Logo & favicon', 'Settings · Business profile', 'palette', $settings('basic', 'branding'), 'admin.settings.basic-details.view', 'branding logo favicon icon brand'],
            ['Addresses', 'Settings · Business profile', 'map-pin', $settings('basic', 'addresses'), 'admin.settings.basic-details.view', 'address location office map'],
            ['Contact details', 'Settings · Business profile', 'phone', $settings('basic', 'contact'), 'admin.settings.basic-details.view', 'phone email whatsapp contact numbers'],
            ['Social links', 'Settings · Business profile', 'share', $settings('basic', 'social'), 'admin.settings.basic-details.view', 'social facebook instagram linkedin twitter x youtube'],
            ['Date & time', 'Settings', 'clock', $settings('date-time'), 'admin.settings.date-time.view', 'timezone time zone date format clock'],
            ['Account lockout', 'Settings · Security', 'lock', $settings('security', 'lockout'), 'admin.settings.security.view', 'lock locked failed login attempts brute force'],
            ['Idle sign-out', 'Settings · Security', 'clock', $settings('security', 'idle'), 'admin.settings.security.view', 'idle timeout session inactivity logout sign out'],
            ['Rate limits', 'Settings · Security', 'zap', $settings('security', 'limits'), 'admin.settings.security.view', 'rate limit throttle too many requests'],
            ['Bot protection & CAPTCHA', 'Settings · Security', 'bot', $settings('security', 'bots'), 'admin.settings.security.view', 'captcha recaptcha turnstile hcaptcha honeypot spam bots'],
            ['Blocked IP addresses', 'Settings · Security', 'x-circle', $settings('security', 'blocked-ips'), 'admin.settings.security.view', 'ip block ban firewall blacklist'],
            ['Blocked requests log', 'Settings · Security', 'list', $settings('security', 'blocked-log'), 'admin.settings.security.view', 'blocked requests firewall log attacks'],
            ['Email (SMTP)', 'Settings', 'mail', $settings('email'), 'admin.settings.email.view', 'smtp mail email server gmail outlook sending'],
            ['Email templates', 'Settings', 'mail-open', $settings('email-templates'), 'admin.settings.email-templates.view', 'email templates wording text password reset notification'],
            ['Scripts & CSS', 'Settings', 'code', $settings('scripts'), 'admin.settings.scripts.view', 'google analytics tag manager pixel tracking scripts css head'],
            ['Sitemap', 'Settings', 'sitemap', $settings('sitemap'), 'admin.settings.sitemap.view', 'sitemap xml google search console'],
            ['Application logs', 'Settings', 'scroll', $settings('logs'), 'admin.log-settings.view', 'logs errors laravel log debug'],
            ['Robots.txt', 'Settings', 'bot', $settings('robots'), 'admin.settings.robots.view', 'robots crawler google index noindex'],
            ['Maintenance mode', 'Settings', 'lock', $settings('maintenance'), 'admin.settings.maintenance.view', 'maintenance offline coming soon under construction'],
            ['Clear cache', 'Action', 'refresh', route('admin.settings.clear-cache'), 'admin.settings.clear-cache', 'cache clear flush refresh'],
            ['Two-factor sign-in', 'My account', 'shield-check', route('admin.two-factor.show'), null, '2fa two factor authenticator otp security code'],
        ];

        return collect($entries)
            ->filter(fn (array $entry) => $entry[4] === null || $user->can($entry[4]))
            ->map(fn (array $entry) => [
                'group' => $entry[1] === 'Action' ? 'Actions' : 'Settings',
                'title' => $entry[0],
                'hint' => $entry[1] === 'Action' ? '' : $entry[1],
                'icon' => $entry[2],
                'url' => $entry[3],
                'keywords' => $entry[5],
            ])
            ->values()
            ->all();
    }

    public static function pageKeywords(): array
    {
        return [
            'Dashboard' => 'home overview stats',
            'Enquiries' => 'leads contact form messages inbox',
            'Blogs' => 'posts articles news',
            'Categories' => 'blog categories tags',
            'Pages' => 'content static pages',
            'Testimonials' => 'reviews feedback quotes',
            'Media Library' => 'images files uploads photos documents',
            'Gallery' => 'albums photos events',
            'SEO' => 'meta title description schema',
            'Redirects' => '301 302 url redirect',
            '404 Errors' => 'not found broken links',
            'Users' => 'admins team staff people accounts',
            'Roles & Permissions' => 'roles access rights permissions',
            'Appearance' => 'theme colours colors fonts design',
            'Menus' => 'navigation header footer links',
            'Settings' => 'configuration options',
            'Activity Logs' => 'audit history who did what logins',
            'Backups' => 'backup restore database download',
            'Trash' => 'deleted restore recycle bin',
            'System health' => 'status php disk queue scheduler health check',
        ];
    }
}
