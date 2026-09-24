<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('style', 20)->nullable()->after('url');
            $table->string('image', 2000)->nullable()->after('style');
            $table->string('description', 120)->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['style', 'image', 'description']);
        });
    }
};
