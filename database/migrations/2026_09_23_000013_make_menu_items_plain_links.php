<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SOURCES = [
        'page' => ['pages', 'title', '/{slug}'],
        'post' => ['blogs', 'title', '/blog/{slug}'],
        'category' => ['blog_categories', 'name', '/blog/category/{slug}'],
    ];

    public function up(): void
    {
        foreach (DB::table('menu_items')->where('type', '!=', 'link')->orderByDesc('id')->get() as $item) {
            [$label, $url] = $this->linkFor($item);

            if ($url === null) {
                DB::table('menu_items')->where('parent_id', $item->id)->update(['parent_id' => $item->parent_id]);
                DB::table('menu_items')->where('id', $item->id)->delete();

                continue;
            }

            DB::table('menu_items')->where('id', $item->id)->update([
                'type' => 'link',
                'label' => $item->label ?: $label,
                'url' => $url,
            ]);
        }

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['type', 'linkable_id']);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['type', 'linkable_id']);
        });

        DB::table('menu_items')->whereNull('label')->update(['label' => 'Link']);
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('type', 20)->default('link')->after('position');
            $table->unsignedBigInteger('linkable_id')->nullable()->after('type');
            $table->index(['type', 'linkable_id']);
        });
    }

    private function linkFor(object $item): array
    {
        if ($item->type === 'home') {
            return ['Home', '/'];
        }

        [$table, $column, $pattern] = self::SOURCES[$item->type] ?? [null, null, null];
        $record = $table ? DB::table($table)->where('id', $item->linkable_id)->first() : null;

        if (! $record) {
            return [null, null];
        }

        $slug = $record->slug ?? (json_decode($record->deleted_data ?? '', true)['slug'] ?? null);

        return [$record->{$column}, $slug ? str_replace('{slug}', ltrim($slug, '/'), $pattern) : '/'];
    }
};
