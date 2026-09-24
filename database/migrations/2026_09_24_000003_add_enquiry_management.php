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
    private const PERMISSIONS = ['admin.enquiries.create', 'admin.enquiries.reply'];

    private const STATUS_MAP = ['seen' => 'new', 'pending' => 'in_progress'];

    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('seen_by')->constrained('users')->nullOnDelete();
            $table->timestamp('follow_up_at')->nullable()->after('assigned_to')->index();
            $table->foreignId('created_by')->nullable()->after('follow_up_at')->constrained('users')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable()->after('status');
            $table->index('status');
        });

        Schema::create('enquiry_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['enquiry_id', 'created_at']);
        });

        foreach (self::STATUS_MAP as $old => $new) {
            DB::table('enquiries')->where('status', $old)->update(['status' => $new]);
        }

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::where('name', 'super admin')->first()?->givePermissionTo(self::PERMISSIONS);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_activities');

        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('created_by');
            $table->dropIndex(['follow_up_at']);
            $table->dropColumn(['follow_up_at', 'status_changed_at']);
            $table->dropIndex(['status']);
        });

        foreach (array_flip(self::STATUS_MAP) as $new => $old) {
            DB::table('enquiries')->where('status', $new)->update(['status' => $old]);
        }
        DB::table('enquiries')->whereIn('status', ['replied', 'on_hold', 'spam'])->update(['status' => 'pending']);

        Permission::whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
