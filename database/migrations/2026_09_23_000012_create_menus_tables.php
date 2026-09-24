<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = ['admin.menus.view', 'admin.menus.update'];

    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('type', 20);
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->string('label', 120)->nullable();
            $table->string('url', 2000)->nullable();
            $table->boolean('new_tab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'position']);
            $table->index(['type', 'linkable_id']);
        });

        DB::table('menus')->insert([
            ['key' => 'header', 'name' => 'Header menu', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'footer', 'name' => 'Footer menu', 'created_at' => now(), 'updated_at' => now()],
        ]);

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::where('name', 'super admin')->first()?->givePermissionTo(self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');

        Permission::whereIn('name', self::PERMISSIONS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
