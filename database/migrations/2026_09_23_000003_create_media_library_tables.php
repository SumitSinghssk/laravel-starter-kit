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
        'admin.media.view',
        'admin.media.upload',
        'admin.media.replace',
        'admin.media.delete',
    ];

    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('location', 16);
            $table->string('path', 500);
            $table->string('folder', 500)->default('');
            $table->string('filename');
            $table->string('extension', 10);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamp('modified_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->unique(['location', 'path']);
            $table->index(['location', 'folder']);
            $table->index('extension');
            $table->index('usage_count');
            $table->index('size');
        });

        Schema::create('media_file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_file_id')->constrained()->cascadeOnDelete();
            $table->string('backup_path');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('media_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_file_id')->constrained()->cascadeOnDelete();
            $table->string('source', 64);
            $table->string('title')->nullable();
            $table->string('detail')->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('media_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_file_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('folder', 500)->nullable();
            $table->string('fingerprint', 64);
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('total_chunks');
            $table->timestamps();

            $table->index(['user_id', 'fingerprint']);
        });

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::where('name', 'super admin')->first()?->givePermissionTo(self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploads');
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_file_versions');
        Schema::dropIfExists('media_files');

        Permission::whereIn('name', self::PERMISSIONS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
