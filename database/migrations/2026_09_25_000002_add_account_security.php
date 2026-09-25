<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = ['admin.settings.security.view', 'admin.settings.security.update', 'admin.users.unlock'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('failed_logins')->default(0)->after('two_factor_confirmed_at');
            $table->timestamp('locked_at')->nullable()->after('failed_logins');
            $table->timestamp('locked_until')->nullable()->after('locked_at');
        });

        Schema::create('ip_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 64)->index();
            $table->string('reason')->nullable();
            $table->boolean('automatic')->default(false);
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('blocked_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 64)->index();
            $table->string('reason', 30)->index();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::where('name', 'super admin')->first()?->givePermissionTo(self::PERMISSIONS);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_requests');
        Schema::dropIfExists('ip_blocks');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['failed_logins', 'locked_at', 'locked_until']);
        });

        Permission::whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
