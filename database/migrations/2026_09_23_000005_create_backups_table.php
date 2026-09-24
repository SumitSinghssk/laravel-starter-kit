<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'admin.backups.view',
        'admin.backups.create',
        'admin.backups.download',
        'admin.backups.delete',
        'admin.backups.settings',
    ];

    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('trigger', 16);
            $table->boolean('includes_database')->default(true);
            $table->boolean('includes_files')->default(true);
            $table->string('status', 16)->default('running');
            $table->string('stage', 16)->default('database');
            $table->json('progress')->nullable();
            $table->json('cursor')->nullable();
            $table->json('parts')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_step_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['trigger', 'scheduled_for']);
        });

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::where('name', 'super admin')->first()?->givePermissionTo(self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');

        Permission::whereIn('name', self::PERMISSIONS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
