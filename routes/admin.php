<?php

use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\Auth\AdminAuthController;
use App\Http\Controllers\Admin\Auth\PasswordResetController;
use App\Http\Controllers\Admin\Auth\ProfileController;
use App\Http\Controllers\Admin\Auth\SessionController;
use App\Http\Controllers\Admin\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Admin\Auth\TwoFactorController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\Blog\BlogCategoryController;
use App\Http\Controllers\Admin\Blog\BlogController;
use App\Http\Controllers\Admin\BulkActionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\GalleryMediaController;
use App\Http\Controllers\Admin\GalleryUploadController;
use App\Http\Controllers\Admin\ImageController;
use App\Http\Controllers\Admin\MediaLibraryController;
use App\Http\Controllers\Admin\MediaUploadController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\NotFoundController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\Setting\ActivityLogController;
use App\Http\Controllers\Admin\Setting\BasicSettingController;
use App\Http\Controllers\Admin\Setting\ClearCacheController;
use App\Http\Controllers\Admin\Setting\DateTimeSettingController;
use App\Http\Controllers\Admin\Setting\DbDownloadController;
use App\Http\Controllers\Admin\Setting\EmailTemplateController;
use App\Http\Controllers\Admin\Setting\LogController;
use App\Http\Controllers\Admin\Setting\MailSettingController;
use App\Http\Controllers\Admin\Setting\MaintenanceController;
use App\Http\Controllers\Admin\Setting\RobotsController;
use App\Http\Controllers\Admin\Setting\ScriptSettingController;
use App\Http\Controllers\Admin\Setting\SecuritySettingController;
use App\Http\Controllers\Admin\Setting\SettingController;
use App\Http\Controllers\Admin\Setting\SitemapController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\TransferController;
use App\Http\Controllers\Admin\TrashController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('log.admin.activity')->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('login', [AdminAuthController::class, 'index'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])->middleware('bot.protect:email,login')->name('login.store');

        Route::controller(PasswordResetController::class)->name('password.')->group(function () {
            Route::get('forgot-password', 'create')->name('request');
            Route::post('forgot-password', 'store')->middleware(['throttle:password-reset', 'bot.protect:email,login'])->name('email');
            Route::get('reset-password/{token}', 'edit')->name('reset');
            Route::post('reset-password', 'update')->middleware('throttle:password-reset')->name('update');
        });

        Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
        Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:two-factor')->name('two-factor.verify');
    });

    Route::middleware(['auth:web', 'idle.timeout', 'two-factor.required'])->group(function () {
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');
        Route::post('session/ping', fn () => response()->json(['ok' => true]))->name('session.ping');

        Route::prefix('account/two-factor')->name('two-factor.')->controller(TwoFactorController::class)->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('/', 'start')->middleware('throttle:10,1')->name('start');
            Route::post('confirm', 'confirm')->middleware('throttle:10,1')->name('confirm');
            Route::delete('setup', 'cancel')->name('cancel');
            Route::post('recovery-codes', 'recoveryCodes')->middleware('throttle:10,1')->name('recovery-codes');
            Route::delete('/', 'destroy')->middleware('throttle:10,1')->name('destroy');
            Route::delete('trusted-device', 'forgetDevice')->name('forget-device');
        });

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('update-password', [ProfileController::class, 'updatePassword'])->name('update-password');
        Route::delete('profile/sessions/{key}', [SessionController::class, 'destroy'])->where('key', '[a-f0-9]{64}')->name('profile.sessions.destroy');
        Route::delete('profile/sessions', [SessionController::class, 'destroyOthers'])->name('profile.sessions.destroy-others');

        Route::post('images', [ImageController::class, 'store'])->name('images.store');

        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::resource('seo', SeoController::class)->except('show');

        Route::prefix('appearance')->name('appearance.')->controller(AppearanceController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::put('/', 'update')->name('update');
            Route::post('check', 'check')->name('check');
            Route::post('reset', 'reset')->name('reset');
            Route::get('preview', 'preview')->name('preview');
            Route::post('fonts/upload', 'storeUploadedFont')->name('fonts.upload');
            Route::post('fonts/google', 'storeGoogleFont')->name('fonts.google');
            Route::post('fonts/{font}/files', 'addFontFiles')->name('fonts.files.store');
            Route::delete('fonts/{font}/files/{index}', 'destroyFontFile')->whereNumber('index')->name('fonts.files.destroy');
            Route::post('fonts/{font}/hosting', 'toggleHosting')->name('fonts.hosting');
            Route::delete('fonts/{font}', 'destroyFont')->name('fonts.destroy');
        });

        Route::prefix('menus')->name('menus.')->controller(MenuController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{key}', 'edit')->where('key', '[a-z0-9-]+')->name('edit');
            Route::put('{key}', 'update')->where('key', '[a-z0-9-]+')->name('update');
        });

        Route::prefix('settings')->name('settings.')->group(function () {

            Route::get('/', SettingController::class)->name('index');
            Route::post('/basic', BasicSettingController::class)->name('basic.update');
            Route::post('/scripts', ScriptSettingController::class)->name('scripts.update');
            Route::get('/clear-cache', ClearCacheController::class)->name('clear-cache');
            Route::get('/download-db', DbDownloadController::class)->name('download-db');

            Route::prefix('sitemap')->name('sitemap.')->group(function () {
                Route::post('generate', [SitemapController::class, 'generate'])->name('generate');
                Route::get('download', [SitemapController::class, 'download'])->name('download');
                Route::post('upload', [SitemapController::class, 'upload'])->name('upload');
            });

            Route::prefix('logs')->name('logs.')->group(function () {
                Route::get('/', [LogController::class, 'index'])->name('index');
                Route::get('/show', [LogController::class, 'show'])->name('show');
                Route::delete('/destroy', [LogController::class, 'destroy'])->name('destroy');
                Route::delete('/destroy-all', [LogController::class, 'destroyAll'])->name('destroy-all');
            });

            Route::post('robots', RobotsController::class)->name('robots.update');

            Route::post('email', [MailSettingController::class, 'update'])->name('email.update');
            Route::post('email/test', [MailSettingController::class, 'test'])->middleware('throttle:email-test')->name('email.test');

            Route::put('date-time', DateTimeSettingController::class)->name('date-time.update');

            Route::prefix('email-templates')->name('email-templates.')->controller(EmailTemplateController::class)->group(function () {
                Route::put('design', 'design')->name('design');
                Route::put('{template}', 'update')->name('update');
                Route::delete('{template}', 'destroy')->name('destroy');
                Route::post('{template}/preview', 'preview')->middleware('throttle:email-preview')->name('preview');
                Route::post('{template}/test', 'test')->middleware('throttle:email-test')->name('test');
            });

            Route::prefix('security')->name('security.')->controller(SecuritySettingController::class)->group(function () {
                Route::put('/', 'update')->name('update');
                Route::post('blocks', 'block')->name('blocks.store');
                Route::delete('blocks/{block}', 'unblock')->name('blocks.destroy');
                Route::delete('log', 'clearLog')->name('log.clear');
            });

            Route::post('maintenance', [MaintenanceController::class, 'update'])->name('maintenance.update');
            Route::get('maintenance/preview', [MaintenanceController::class, 'preview'])->name('maintenance.preview');
        });

        Route::prefix('activity-logs')->name('activity-logs.')->group(function () {
            Route::get('/', [ActivityLogController::class, 'index'])->name('index');
            Route::delete('/clear', [ActivityLogController::class, 'clear'])->name('clear');
            Route::get('/{activityLog}', [ActivityLogController::class, 'show'])->name('show');
        });

        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [RolePermissionController::class, 'index'])->name('index');

            Route::post('/', [RolePermissionController::class, 'storeRole'])->name('store');
            Route::delete('/{role}', [RolePermissionController::class, 'destroyRole'])->name('destroy');

            Route::post('/{role}/toggle-permission', [RolePermissionController::class, 'togglePermission'])->name('toggle-permission');
            Route::post('/{role}/two-factor', [RolePermissionController::class, 'toggleTwoFactor'])->name('two-factor');
        });

        Route::prefix('permissions')->name('permissions.')->group(function () {
            Route::post('/', [RolePermissionController::class, 'storePermission'])->name('store');
            Route::delete('/{permission}', [RolePermissionController::class, 'destroyPermission'])->name('destroy');
        });

        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::delete('/users/{user}/sessions/{key}', [UserSessionController::class, 'destroy'])->where('key', '[a-f0-9]{64}')->name('users.sessions.destroy');
        Route::delete('/users/{user}/sessions', [UserSessionController::class, 'destroyAll'])->name('users.sessions.destroy-all');
        Route::delete('/users/{user}/two-factor', [TwoFactorController::class, 'reset'])->name('users.two-factor.reset');
        Route::post('/users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');
        Route::resource('users', UserController::class);

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/list', [NotificationController::class, 'list'])->name('list');
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('markAllRead');
            Route::post('/{id}/mark-read', [NotificationController::class, 'markRead'])->name('markRead');
            Route::get('/{id}/view', [NotificationController::class, 'view'])->name('view');
        });

        Route::patch('/blog-categories/{blogCategory}/toggle-status', [BlogCategoryController::class, 'toggleStatus'])->name('blog-categories.toggle-status');
        Route::resource('blog-categories', BlogCategoryController::class);

        Route::patch('blogs/{blog}/toggle-status', [BlogController::class, 'toggleStatus'])->name('blogs.toggle-status');
        Route::resource('blogs', BlogController::class);

        Route::patch('pages/{page}/toggle-status', [PageController::class, 'toggleStatus'])->name('pages.toggle-status');
        Route::resource('pages', PageController::class)->except(['show']);

        Route::patch('galleries/{gallery}/toggle-status', [GalleryController::class, 'toggleStatus'])->name('galleries.toggle-status');
        Route::resource('galleries', GalleryController::class)->except(['show']);

        Route::prefix('galleries/{gallery}/media')->name('galleries.media.')->controller(GalleryMediaController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('youtube', 'youtube')->name('youtube');
            Route::post('reorder', 'reorder')->name('reorder');
            Route::post('bulk-delete', 'bulkDestroy')->name('bulk-destroy');
            Route::post('cover', 'cover')->name('cover');
        });
        Route::prefix('gallery-media/{item}')->name('gallery-media.')->controller(GalleryMediaController::class)->group(function () {
            Route::post('/', 'update')->name('update');
            Route::delete('/', 'destroy')->name('destroy');
            Route::get('stream', 'stream')->name('stream');
        });

        Route::post('galleries/{gallery}/uploads', [GalleryUploadController::class, 'store'])->name('galleries.uploads.store');
        Route::prefix('gallery-uploads/{upload}')->name('gallery-uploads.')->controller(GalleryUploadController::class)->group(function () {
            Route::post('chunks/{index}', 'chunk')->whereNumber('index')->name('chunk');
            Route::post('complete', 'complete')->name('complete');
            Route::delete('/', 'destroy')->name('destroy');
        });

        Route::prefix('transfer')->name('transfer.')->controller(TransferController::class)->group(function () {
            Route::get('{type}/export', 'export')->name('export');
            Route::get('{type}/download-template', 'template')->name('template');
            Route::get('{type}/import', 'create')->name('create');
            Route::post('{type}/import', 'store')->name('store');
            Route::get('imports/{import}', 'show')->name('imports.show');
            Route::post('imports/{import}/step', 'step')->name('imports.step');
            Route::delete('imports/{import}', 'destroy')->name('imports.destroy');
        });

        Route::post('bulk/{type}', BulkActionController::class)->name('bulk');

        Route::prefix('backups')->name('backups.')->controller(BackupController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('settings', 'saveSettings')->name('settings');
            Route::post('{backup}/step', 'step')->name('step');
            Route::get('{backup}/download/{file}', 'download')->where('file', '[A-Za-z0-9._-]+')->name('download');
            Route::delete('{backup}', 'destroy')->name('destroy');
        });

        Route::prefix('system-health')->name('system-health.')->controller(SystemHealthController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('sizes', 'sizes')->name('sizes');
            Route::post('mail-check', 'mailCheck')->middleware('throttle:6,1')->name('mail-check');
            Route::post('storage-link', 'storageLink')->name('storage-link');
            Route::post('failed-jobs/retry', 'retryFailed')->name('failed-jobs.retry');
            Route::delete('failed-jobs', 'deleteFailed')->name('failed-jobs.delete');
        });

        Route::prefix('trash')->name('trash.')->controller(TrashController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('restore', 'restore')->name('restore');
            Route::delete('/', 'destroy')->name('destroy');
            Route::delete('empty', 'empty')->name('empty');
        });

        Route::prefix('media-library')->name('media-library.')->group(function () {
            Route::controller(MediaLibraryController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('rescan', 'rescan')->name('rescan');
                Route::get('{file}/details', 'show')->whereNumber('file')->name('show');
                Route::get('{file}/thumb', 'thumb')->whereNumber('file')->name('thumb');
                Route::delete('{file}', 'destroy')->whereNumber('file')->name('destroy');
                Route::get('{file}/versions/{version}/preview', 'versionPreview')->whereNumber(['file', 'version'])->name('versions.preview');
                Route::post('{file}/versions/{version}/restore', 'restore')->whereNumber(['file', 'version'])->name('versions.restore');
            });

            Route::controller(MediaUploadController::class)->prefix('uploads')->name('uploads.')->group(function () {
                Route::post('/', 'store')->name('store');
                Route::post('{upload}/chunks/{index}', 'chunk')->whereNumber('index')->name('chunk');
                Route::post('{upload}/complete', 'complete')->name('complete');
                Route::delete('{upload}', 'destroy')->name('destroy');
            });
        });

        Route::patch('testimonials/{testimonial}/toggle-status', [TestimonialController::class, 'toggleStatus'])->name('testimonials.toggle-status');
        Route::resource('testimonials', TestimonialController::class)->except(['show']);

        Route::prefix('not-found')->name('not-found.')->controller(NotFoundController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('redirect', 'redirect')->name('redirect');
            Route::post('ignore', 'ignore')->name('ignore');
            Route::delete('/', 'destroy')->name('destroy');
            Route::delete('clear', 'clear')->name('clear');
        });

        Route::patch('redirects/{redirect}/toggle-status', [RedirectController::class, 'toggleStatus'])->name('redirects.toggle-status');
        Route::resource('redirects', RedirectController::class)->except(['show']);

        Route::resource('enquiries', EnquiryController::class)->whereNumber('enquiry');

        Route::prefix('enquiries/{enquiry}')->name('enquiries.')->controller(EnquiryController::class)->whereNumber('enquiry')->group(function () {
            Route::post('status', 'status')->name('status');
            Route::post('notes', 'note')->name('notes.store');
            Route::delete('notes/{activity}', 'destroyNote')->whereNumber('activity')->name('notes.destroy');
            Route::post('assign', 'assign')->name('assign');
            Route::post('follow-up', 'followUp')->name('follow-up');
            Route::post('reply', 'reply')->middleware('throttle:20,1')->name('reply');
        });
    });
});
