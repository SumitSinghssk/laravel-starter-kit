<?php

namespace App\Services\Menus;

use App\Models\Menu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MenuBuilder
{
    public const LABEL_MAX = 80;

    public const DESCRIPTION_MAX = 120;

    public const STYLES = ['dropdown', 'mega'];

    public function config(string $key): ?array
    {
        return config("menus.menus.$key");
    }

    public function menu(string $key): Menu
    {
        $config = $this->config($key) ?? abort(404);

        return Menu::firstOrCreate(['key' => $key], ['name' => $config['name']]);
    }

    public function editorRows(Menu $menu): array
    {
        $items = $menu->items()->get();
        $rows = [];

        $append = function ($parentId, int $depth) use (&$append, &$rows, $items) {
            foreach ($items->where('parent_id', $parentId)->sortBy('position') as $item) {
                $rows[] = [
                    'label' => (string) $item->label,
                    'url' => (string) $item->url,
                    'style' => $item->style ?: 'dropdown',
                    'image' => (string) $item->image,
                    'description' => (string) $item->description,
                    'new_tab' => $item->new_tab,
                    'is_active' => $item->is_active,
                    'depth' => $depth,
                ];
                $append($item->id, $depth + 1);
            }
        };
        $append(null, 0);

        return $rows;
    }

    public function version(Menu $menu): string
    {
        return md5(json_encode($this->editorRows($menu)));
    }

    public function validate(Menu $menu, array $input): array
    {
        $config = $this->config($menu->key);
        $rich = (bool) ($config['rich'] ?? false);
        $rows = array_values(array_filter((array) ($input['items'] ?? []), 'is_array'));
        $errors = [];
        $clean = [];
        $previousDepth = -1;

        if (count($rows) > $config['max_items']) {
            $errors['items'] = "A menu can have at most {$config['max_items']} links.";
        }

        foreach ($rows as $i => $row) {
            $depth = (int) ($row['depth'] ?? 0);
            $label = trim((string) ($row['label'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            $style = (string) ($row['style'] ?? 'dropdown');
            $image = $rich ? trim((string) ($row['image'] ?? '')) : '';
            $description = $rich ? trim((string) ($row['description'] ?? '')) : '';
            $hasChildren = isset($rows[$i + 1]) && (int) ($rows[$i + 1]['depth'] ?? 0) > $depth;

            if ($depth < 0 || $depth >= $config['depth']) {
                $errors["items.$i.depth"] = 'Links can be nested at most '.($config['depth'] - 1).' levels deep.';
            } elseif ($depth > $previousDepth + 1) {
                $errors["items.$i.depth"] = 'A nested link needs a link above it to sit under.';
            }

            if ($label === '') {
                $errors["items.$i.label"] = 'Give the link a label.';
            } elseif (mb_strlen($label) > self::LABEL_MAX) {
                $errors["items.$i.label"] = 'Keep the label under '.self::LABEL_MAX.' characters.';
            }

            if ($url === '' && ! $hasChildren) {
                $errors["items.$i.url"] = 'Add a URL, or nest links under it to make it a title.';
            } elseif ($url !== '' && ! $this->safeUrl($url)) {
                $errors["items.$i.url"] = 'Use a web address (https://…), a path on this site (/about), #section, mailto: or tel:.';
            }

            if (! in_array($style, self::STYLES, true)) {
                $errors["items.$i.style"] = 'Choose a dropdown or a mega menu.';
            }

            if ($image !== '' && ! $this->safeImage($image)) {
                $errors["items.$i.image"] = 'Use an image address (https://…) or a path on this site (/storage/…).';
            }

            if (mb_strlen($description) > self::DESCRIPTION_MAX) {
                $errors["items.$i.description"] = 'Keep the caption under '.self::DESCRIPTION_MAX.' characters.';
            }

            $previousDepth = $depth;
            $clean[] = [
                'depth' => max(0, $depth),
                'label' => $label,
                'url' => $url === '' ? null : $url,
                'style' => $rich && $depth === 0 && $style === 'mega' ? 'mega' : null,
                'image' => $image === '' ? null : $image,
                'description' => $description === '' ? null : $description,
                'new_tab' => $url !== '' && filter_var($row['new_tab'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return [$clean, $errors];
    }

    public function save(Menu $menu, array $rows): void
    {
        DB::transaction(function () use ($menu, $rows) {
            $menu->items()->delete();
            $parents = [];
            $positions = [];

            foreach ($rows as $row) {
                $parentId = $row['depth'] > 0 ? ($parents[$row['depth'] - 1] ?? null) : null;
                $slot = $parentId ?? 0;
                $positions[$slot] = ($positions[$slot] ?? 0) + 1;

                $item = $menu->items()->create([
                    'parent_id' => $parentId,
                    'position' => $positions[$slot],
                    'label' => $row['label'],
                    'url' => $row['url'],
                    'style' => $row['style'] ?? null,
                    'image' => $row['image'] ?? null,
                    'description' => $row['description'] ?? null,
                    'new_tab' => $row['new_tab'],
                    'is_active' => $row['is_active'],
                ]);

                $parents[$row['depth']] = $item->id;
            }

            $menu->touch();
        });

        $this->flush();
    }

    public function tree(string $key): array
    {
        if (! $this->config($key)) {
            return [];
        }

        return Cache::rememberForever("menu:$key", fn () => $this->build($key));
    }

    public function flush(): void
    {
        foreach (array_keys(config('menus.menus', [])) as $key) {
            Cache::forget("menu:$key");
        }
    }

    public function safeUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > 2000 || preg_match('/[\x00-\x1F\x7F\s]/', $url)) {
            return false;
        }

        if (preg_match('~^(/(?!/)|#|\?)~', $url) || preg_match('~^(mailto|tel):.+~i', $url)) {
            return true;
        }

        return (bool) preg_match('~^https?://[^/\s]+~i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function safeImage(string $url): bool
    {
        return $this->safeUrl($url) && (bool) preg_match('~^(https?://|/(?!/))~i', $url);
    }

    private function build(string $key): array
    {
        $menu = Menu::where('key', $key)->first();
        $items = $menu ? $menu->items()->where('is_active', true)->get() : collect();
        $resolve = fn (?string $url) => blank($url) ? null : (str_starts_with($url, '/') ? url($url) : $url);

        $branch = function ($parentId) use (&$branch, $items, $resolve) {
            return $items->where('parent_id', $parentId)->sortBy('position')->map(fn ($item) => [
                'label' => $item->label,
                'url' => $resolve($item->url),
                'style' => $item->style ?: 'dropdown',
                'image' => $resolve($item->image),
                'description' => $item->description,
                'new_tab' => $item->new_tab,
                'children' => $branch($item->id),
            ])->filter(fn ($item) => $item['url'] !== null || $item['children'])->values()->all();
        };

        return $branch(null);
    }
}
