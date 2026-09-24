<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'profile.view',
            'profile.update',
            'profile.update-password',

            'admin.seo.view',
            'admin.seo.create',
            'admin.seo.edit',
            'admin.seo.delete',

            'admin.settings.view',
            'admin.appearance.view',
            'admin.appearance.update',
            'admin.menus.view',
            'admin.menus.update',
            'admin.settings.basic-details.view',
            'admin.settings.basic-details.update',
            'admin.settings.scripts.view',
            'admin.settings.scripts.update',
            'admin.settings.clear-cache',
            'admin.settings.download-db',
            'admin.settings.sitemap.view',
            'admin.settings.sitemap.update',
            'admin.settings.robots.view',
            'admin.settings.robots.update',
            'admin.settings.maintenance.view',
            'admin.settings.maintenance.update',
            'admin.settings.email.view',
            'admin.settings.email.update',

            'admin.activity-logs.view',
            'admin.activity-logs.clear',

            'admin.roles.view',
            'admin.roles.create',
            'admin.roles.update',
            'admin.roles.delete',

            'admin.permissions.view',
            'admin.permissions.create',
            'admin.permissions.delete',

            'admin.users.view',
            'admin.users.create',
            'admin.users.edit',
            'admin.users.delete',
            'admin.users.toogle-status',
            'admin.users.sessions',

            'admin.notifications.view',
            'admin.notifications.mark-all-as-read',

            'admin.blog-categories.view',
            'admin.blog-categories.create',
            'admin.blog-categories.edit',
            'admin.blog-categories.delete',
            'admin.blog-categories.toogle-status',

            'admin.blogs.view',
            'admin.blogs.create',
            'admin.blogs.edit',
            'admin.blogs.delete',
            'admin.blogs.toogle-status',

            'admin.pages.view',
            'admin.pages.create',
            'admin.pages.edit',
            'admin.pages.delete',
            'admin.pages.toogle-status',

            'admin.log-settings.view',
            'admin.log-settings.delete',

            'admin.enquiries.view',
            'admin.enquiries.delete',
            'admin.enquiries.edit',

            'admin.redirects.view',
            'admin.redirects.create',
            'admin.redirects.edit',
            'admin.redirects.delete',
            'admin.redirects.toogle-status',

            'admin.not-found.view',
            'admin.not-found.manage',

            'admin.testimonials.view',
            'admin.testimonials.create',
            'admin.testimonials.edit',
            'admin.testimonials.delete',
            'admin.testimonials.toogle-status',

            'admin.galleries.view',
            'admin.galleries.create',
            'admin.galleries.edit',
            'admin.galleries.delete',
            'admin.galleries.toogle-status',

            'admin.media.view',
            'admin.media.upload',
            'admin.media.replace',
            'admin.media.delete',

            'admin.trash.view',
            'admin.trash.restore',
            'admin.trash.delete',

            'admin.backups.view',
            'admin.backups.create',
            'admin.backups.download',
            'admin.backups.delete',
            'admin.backups.settings',

            'admin.system-health.view',
            'admin.system-health.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }

        $roles = ['super admin', 'admin', 'developer', 'sales'];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role]);
        }

        Role::findByName('super admin')->syncPermissions(Permission::all());

        Role::findByName('admin')->syncPermissions([
            'dashboard.view',
            'profile.view',
            'profile.update',
            'profile.update-password',
        ]);

        Role::findByName('developer')->syncPermissions([
            'dashboard.view',
            'profile.view',
        ]);

        Role::findByName('sales')->syncPermissions([
            'dashboard.view',
        ]);
    }
}
